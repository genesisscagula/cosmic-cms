<?php

namespace Tests\Unit;

use App\AI\Schemas\SchemaManager;
use App\Services\LunaSmartSparkEditingService;
use App\Services\SparkEditCapabilityRegistry;
use App\Services\SparkEditCapabilityExecutor;
use App\Services\SparkEditMutationValidator;
use App\Services\SparkCatalog;
use Tests\TestCase;

class SparkEditCapabilityRegistryTest extends TestCase
{
    public function test_every_registered_spark_has_a_capability_declaration(): void
    {
        $registry = app(SparkEditCapabilityRegistry::class);
        $declarations = $registry->allDeclarations();

        $parity = json_decode((string) file_get_contents(resource_path('luna/render_parity.json')), true);
        $this->assertCount((int) $parity['builder_registry_count'], SchemaManager::map());
        $this->assertCount(count(SchemaManager::map()), $declarations);
        $this->assertSame(array_keys(SchemaManager::map()), array_keys($declarations));
        foreach ($declarations as $declaration) {
            $this->assertContains('Typography', $declaration['modules']);
            $this->assertContains('Spacing', $declaration['modules']);
            $this->assertContains('Surface', $declaration['modules']);
            $this->assertNotEmpty($declaration['module_contracts']);
            $this->assertArrayHasKey('section', $declaration['targets']);
            $this->assertArrayHasKey('heading', $declaration['targets']);
            $this->assertNotEmpty($declaration['targets']['heading']['operations']);
        }
    }

    public function test_ordinary_edit_loads_only_the_selected_spark_contract(): void
    {
        $payload = app(SparkEditCapabilityRegistry::class)->payloadForRequest(
            'Make this slide heading smaller',
            'hero_slider_fade',
            ['type' => 'hero_slider_fade', 'slides' => [['heading' => 'First']]],
            ['collectionKey' => 'slides', 'itemIndex' => 0, 'type' => 'heading'],
            ['domain' => 'typography', 'operation' => 'font_size'],
        );

        $this->assertSame('selected_spark', $payload['mode']);
        $this->assertSame('hero_slider_fade', $payload['selected_spark']['spark_type']);
        $this->assertContains('Media', $payload['selected_spark']['modules']);
        $this->assertContains('ItemCollection', $payload['selected_spark']['modules']);
        $this->assertArrayNotHasKey('catalog', $payload);
    }

    public function test_structural_request_is_the_only_path_that_loads_the_active_catalog(): void
    {
        $payload = app(SparkEditCapabilityRegistry::class)->payloadForRequest(
            'Add another services section below this one',
            'services_bento',
            ['type' => 'services_bento'],
            [],
            ['domain' => 'section', 'operation' => 'add'],
        );

        $this->assertSame('structural_catalog', $payload['mode']);
        $this->assertCount(count(SparkCatalog::all()), $payload['catalog']);
        $this->assertNotContains('about_chapter_index_premium', array_column($payload['catalog'], 'key'));
    }

    public function test_validator_rejects_unknown_and_protected_fields(): void
    {
        $validator = app(SparkEditMutationValidator::class);
        $result = $validator->validateChanges('hero_headline', [
            'type' => 'hero_headline',
            'heading' => 'Before',
        ], [
            'type' => 'services_bento',
            'heading' => 'After',
            'invented_css' => 'position:fixed',
        ]);

        $this->assertSame(['heading' => 'After'], $result['changes']);
        $this->assertSame('protected_field', $result['rejected']['type']);
        $this->assertSame('unknown_field', $result['rejected']['invented_css']);
    }

    public function test_validator_rejects_invalid_targets_and_target_properties(): void
    {
        $validator = app(SparkEditMutationValidator::class);
        $block = ['type' => 'hero_headline', 'heading' => 'Before'];

        $invalidTarget = $validator->validateChanges('hero_headline', $block, ['heading' => 'After'], 'pricing-cell');
        $invalidProperty = $validator->validateMutation('hero_headline', $block, 'heading', 'replace_video');
        $validProperty = $validator->validateMutation('hero_headline', $block, 'heading', 'font_size');

        $this->assertSame('unsupported_target', $invalidTarget['rejected']['_target']);
        $this->assertFalse($invalidProperty['valid']);
        $this->assertSame('unsupported_property', $invalidProperty['reason']);
        $this->assertTrue($validProperty['valid']);
    }

    public function test_relative_mutations_are_bounded_and_persisted_in_real_token_paths(): void
    {
        $validator = app(SparkEditMutationValidator::class);
        $result = $validator->applyRelative(
            ['type' => 'hero_headline', 'heading' => 'Hero'],
            'font_size',
            'decrease',
            ['role' => 'h1', 'current_value' => '52px'],
        );

        $this->assertTrue($result['applied']);
        $this->assertSame('luna_typography_overrides.h1_size', $result['path']);
        $this->assertSame('48px', $result['after']);
        $this->assertSame('48px', data_get($result, 'block.luna_typography_overrides.h1_size'));

        $again = $validator->applyRelative($result['block'], 'font_size', 'decrease', ['role' => 'h1']);
        $this->assertSame('44px', $again['after']);
    }

    public function test_relative_mutation_refuses_to_reset_from_an_unrelated_default(): void
    {
        $result = app(SparkEditMutationValidator::class)->applyRelative(
            ['type' => 'hero_headline', 'heading' => 'Hero'],
            'font_size',
            'decrease',
            ['role' => 'h1'],
        );

        $this->assertFalse($result['applied']);
        $this->assertSame('current_value_required', $result['reason']);
    }

    public function test_card_radius_contract_migrates_legacy_design_storage_to_the_canonical_component_path(): void
    {
        $registry = app(SparkEditCapabilityRegistry::class);
        $block = [
            'type' => 'services_bento',
            'services' => [['title' => 'One']],
            'luna_design_overrides' => ['card_radius' => 24],
        ];

        $contract = $registry->mutationContract('services_bento', $block, 'border_radius');
        $this->assertSame('luna_component_overrides.card_radius', $contract['storage_path']);
        $this->assertSame(['luna_design_overrides.card_radius'], $contract['fallback_paths']);
        $this->assertSame('--cosmic-local-card-radius', $contract['render_token']);

        $result = app(SparkEditMutationValidator::class)->applyRelative($block, 'border_radius', 'increase');
        $this->assertTrue($result['applied']);
        $this->assertSame(24, $result['before']);
        $this->assertSame('28px', $result['after']);
        $this->assertSame('28px', data_get($result, 'block.luna_component_overrides.card_radius'));
        $this->assertSame(24, data_get($result, 'block.luna_design_overrides.card_radius'));
    }

    public function test_card_radius_executes_and_verifies_across_multiple_card_sparks(): void
    {
        $executor = app(SparkEditCapabilityExecutor::class);
        $types = ['services_bento', 'pricing_cards', 'team_modern', 'testimonials_carousel'];

        foreach ($types as $type) {
            $first = $executor->executeRelative(
                [['type' => $type]],
                0,
                'border_radius',
                'increase',
                ['target' => 'cards', 'current_value' => '20px'],
            );

            $this->assertTrue($first['applied'], $type.' should verify the first radius mutation.');
            $this->assertSame('complete', data_get($first, 'verification.status'));
            $this->assertSame('24px', data_get($first, 'blocks.0.luna_component_overrides.card_radius'));
            $this->assertSame('luna_component_overrides.card_radius', data_get($first, 'applied_operations.0.path'));

            $again = $executor->executeRelative(
                $first['blocks'],
                0,
                'border_radius',
                'increase',
                ['target' => 'cards'],
            );

            $this->assertTrue($again['applied'], $type.' should verify the relative follow-up.');
            $this->assertSame('24px', data_get($again, 'mutation.before'));
            $this->assertSame('28px', data_get($again, 'mutation.after'));
            $this->assertSame('28px', data_get($again, 'blocks.0.luna_component_overrides.card_radius'));
        }
    }

    public function test_dark_card_surface_executes_and_verifies_across_multiple_card_sparks(): void
    {
        $executor = app(SparkEditCapabilityExecutor::class);
        $types = ['services_bento', 'pricing_cards', 'team_modern', 'testimonials_carousel'];

        foreach ($types as $type) {
            $dark = $executor->executeSet(
                [['type' => $type]],
                0,
                'card_surface',
                'dark',
                ['target' => 'cards', 'current_value' => 'surface'],
            );

            $this->assertTrue($dark['applied'], $type.' should verify its dark card surface.');
            $this->assertSame('complete', data_get($dark, 'verification.status'));
            $this->assertSame('dark', data_get($dark, 'blocks.0.luna_component_overrides.card_surface'));
            $this->assertSame('luna_component_overrides.card_surface', data_get($dark, 'applied_operations.0.path'));
            $this->assertSame('dark', data_get($dark, 'applied_operations.0.final_state.value'));

            $light = $executor->executeSet(
                $dark['blocks'],
                0,
                'card_surface',
                'surface',
                ['target' => 'cards'],
            );

            $this->assertTrue($light['applied'], $type.' should verify returning to a light surface.');
            $this->assertSame('surface', data_get($light, 'blocks.0.luna_component_overrides.card_surface'));
        }
    }

    public function test_smart_edit_service_applies_only_valid_schema_fields(): void
    {
        $service = app(LunaSmartSparkEditingService::class);
        $result = $service->sanitizeEdit([
            'type' => 'hero_headline',
            'heading' => 'Before',
            'text' => 'Keep me',
        ], [
            'heading' => 'After',
            'unknown' => 'No',
        ]);

        $this->assertSame('After', $result['block']['heading']);
        $this->assertSame('Keep me', $result['block']['text']);
        $this->assertArrayNotHasKey('unknown', $result['block']);
        $this->assertSame('unknown_field', $result['rejected']['unknown']);
    }
}
