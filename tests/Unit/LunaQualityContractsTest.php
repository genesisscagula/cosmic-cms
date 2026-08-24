<?php

namespace Tests\Unit;

use App\AI\Images\VisualQueryBuilder;
use App\Services\LunaSmartSparkEditingService;
use App\Services\LunaIntentGateway;
use App\Services\PageTemplateCatalog;
use App\Services\TemplateCompositionBalancer;
use App\Services\TemplateQualityAuditor;
use Tests\TestCase;

class LunaQualityContractsTest extends TestCase
{
    public function test_active_slider_item_limits_duplicate_value_matching_to_that_slide(): void
    {
        $service = app(LunaSmartSparkEditingService::class);
        $block = [
            'type' => 'hero_slider_fade',
            'slides' => [
                ['heading' => 'A shared heading', 'text' => 'First'],
                ['heading' => 'A shared heading', 'text' => 'Second'],
            ],
        ];

        $paths = $service->resolveMatchedPaths($block, [
            'collectionKey' => 'slides',
            'itemIndex' => 1,
            'currentValue' => 'A shared heading',
            'type' => 'heading',
        ], 'Change this heading');

        $this->assertSame(['slides.1.heading'], $paths);
        $this->assertSame(
            'Done — I updated heading on Slide 2 only.',
            $service->verifiedTargetReply(['collectionKey' => 'slides', 'itemIndex' => 1, 'type' => 'heading'], 'Change this heading', 'complete')
        );
    }

    public function test_explicit_whole_collection_request_can_match_every_slide(): void
    {
        $service = app(LunaSmartSparkEditingService::class);
        $block = [
            'type' => 'hero_slider_fade',
            'slides' => [
                ['heading' => 'A shared heading'],
                ['heading' => 'A shared heading'],
            ],
        ];

        $paths = $service->resolveMatchedPaths($block, [
            'collectionKey' => 'slides',
            'itemIndex' => 1,
            'currentValue' => 'A shared heading',
        ], 'Change every slide heading');

        $this->assertSame(['slides.0.heading', 'slides.1.heading'], $paths);
    }

    public function test_false_repeater_target_can_recover_a_unique_section_cta(): void
    {
        $service = app(LunaSmartSparkEditingService::class);
        $block = [
            'type' => 'cta_image_split_premium',
            'button_label' => 'Book a Table',
            'button_url' => '/reservations',
            'items' => [
                ['title' => 'Plan Your Visit', 'text' => 'Choose a relaxed meal.'],
                ['title' => 'Bring the Experience Along', 'text' => 'Ask about catering.'],
            ],
        ];

        $paths = $service->resolveMatchedPaths($block, [
            'collectionKey' => 'items',
            'itemIndex' => 0,
            'currentValue' => 'Book a Table',
            'url' => '/reservations',
            'type' => 'button',
        ], 'Change this button');

        $this->assertSame(['button_label', 'button_url'], $paths);
    }

    public function test_false_repeater_target_never_falls_back_to_ambiguous_copy(): void
    {
        $service = app(LunaSmartSparkEditingService::class);
        $block = [
            'type' => 'cards',
            'button_label' => 'Learn more',
            'items' => [
                ['title' => 'First', 'button_label' => 'View first'],
                ['title' => 'Second', 'button_label' => 'Learn more'],
            ],
        ];

        $paths = $service->resolveMatchedPaths($block, [
            'collectionKey' => 'items',
            'itemIndex' => 0,
            'currentValue' => 'Learn more',
            'type' => 'button',
        ], 'Change this button');

        $this->assertSame([], $paths);
    }

    public function test_concrete_can_you_theme_request_is_an_action(): void
    {
        $gateway = app(LunaIntentGateway::class);

        $this->assertSame('action', $gateway->route('Can you change the theme to charcoal?')['intent']);
        $this->assertSame('action', $gateway->route('Can you change the theme to emerald?')['intent']);
        $this->assertSame('chat', $gateway->route('What themes do you support?')['intent']);
    }

    public function test_standalone_named_theme_command_resolves_without_ai_planning(): void
    {
        $controller = app(\App\Http\Controllers\CustomSparkController::class);
        $method = new \ReflectionMethod($controller, 'lunaStandaloneNamedThemeKey');

        $this->assertSame('charcoal', $method->invoke($controller, 'Can you change the theme to charcoal?'));
        $this->assertSame('emerald', $method->invoke($controller, 'Please switch the website theme to emerald.'));
        $this->assertNull($method->invoke($controller, 'Change the theme to emerald and make the heading smaller.'));
    }

    public function test_whole_page_hero_heading_request_updates_the_hero_h1_locally(): void
    {
        $controller = app(\App\Http\Controllers\CustomSparkController::class);
        $method = new \ReflectionMethod($controller, 'lunaTypographyAction');
        $blocks = [
            ['type' => 'hero_cinematic_slider_premium', 'heading' => 'Clear Materials. Better Builds.'],
            ['type' => 'services_bento_premium', 'heading' => 'Glass for every project'],
        ];

        $result = $method->invoke(
            $controller,
            "Make the hero heading slightly smaller.\n[V5 action hints: update hero typography across the site]",
            'page',
            -1,
            $blocks,
            []
        );

        $this->assertSame('clamp(2.7rem,5.4vw,5.175rem)', $result['blocks'][0]['luna_typography_overrides']['h1_size']);
        $this->assertArrayNotHasKey('luna_typography_overrides', $result['blocks'][1]);
        $this->assertSame([], $result['typography_settings']);
        $this->assertSame('typography_local', $result['applied_operations'][0]['action']);
        $this->assertSame(0, $result['applied_operations'][0]['index']);
        $this->assertSame('h1', $result['applied_operations'][0]['role']);

        $misclassified = $method->invoke(
            $controller,
            'Make the hero heading smaller.',
            'page',
            -1,
            $blocks,
            [],
            [
                'intent' => 'action', 'action' => 'update', 'domain' => 'typography',
                'leaf_operation' => 'font_size', 'operation' => 'update', 'scope' => 'section',
                'target' => ['type' => 'heading', 'key' => 'hero.heading', 'level' => 'h2'],
                'changes' => ['relative_size' => ['direction' => 'decrease', 'amount' => 'slight']],
            ]
        );
        $this->assertSame('h1', $misclassified['applied_operations'][0]['role']);
        $this->assertArrayHasKey('h1_size', $misclassified['blocks'][0]['luna_typography_overrides']);
        $this->assertArrayNotHasKey('h2_size', $misclassified['blocks'][0]['luna_typography_overrides']);
    }

    public function test_contextual_typography_followups_scale_the_verified_local_value(): void
    {
        $controller = app(\App\Http\Controllers\CustomSparkController::class);
        $method = new \ReflectionMethod($controller, 'lunaTypographyAction');
        $blocks = [
            [
                'type' => 'hero_cinematic_slider_premium',
                'heading' => 'Clear Materials. Better Builds.',
                'luna_typography_overrides' => ['h1_size' => 'clamp(2.7rem,5.4vw,5.175rem)'],
            ],
            ['type' => 'services_bento_premium', 'heading' => 'Glass for every project'],
        ];
        $canonical = [
            'intent' => 'action',
            'action' => 'update',
            'domain' => 'typography',
            'leaf_operation' => 'font_size',
            'operation' => 'update',
            'scope' => 'section',
            'target' => ['type' => 'heading', 'key' => 'hero.h1', 'label' => 'hero heading', 'index' => 0, 'level' => 'h1'],
            'changes' => ['relative_size' => ['direction' => 'decrease', 'amount' => 'slight']],
        ];

        $smaller = $method->invoke($controller, 'A little more.', 'page', -1, $blocks, [], $canonical);
        $this->assertSame('clamp(2.43rem,4.86vw,4.658rem)', $smaller['blocks'][0]['luna_typography_overrides']['h1_size']);
        $this->assertSame('clamp(2.7rem,5.4vw,5.175rem)', $smaller['before_value']);

        $canonical['changes']['relative_size']['direction'] = 'increase';
        $larger = $method->invoke($controller, 'Too much.', 'page', -1, $smaller['blocks'], [], $canonical);
        $this->assertSame('clamp(2.722rem,5.443vw,5.217rem)', $larger['blocks'][0]['luna_typography_overrides']['h1_size']);

        $canonical['target'] = ['type' => 'heading', 'tagName' => 'H2', 'index' => 1];
        $canonical['changes']['relative_size']['direction'] = 'decrease';
        $sameHere = $method->invoke($controller, 'Same for this heading.', 'section', 1, $larger['blocks'], [], $canonical);
        $this->assertSame('clamp(2.025rem,3.645vw,3.6rem)', $sameHere['blocks'][1]['luna_typography_overrides']['h2_size']);
        $this->assertSame(1, $sameHere['applied_operations'][0]['index']);
    }

    public function test_verified_typography_executor_supplies_context_even_when_router_domain_is_generic(): void
    {
        $controller = app(\App\Http\Controllers\CustomSparkController::class);
        $actionMethod = new \ReflectionMethod($controller, 'lunaTypographyAction');
        $verifyMethod = new \ReflectionMethod($controller, 'lunaVerifyTypographyAction');
        $blocks = [
            ['type' => 'hero_cinematic_slider_premium', 'heading' => 'Clear Materials. Better Builds.'],
        ];
        $genericCanonical = [
            'intent' => 'action', 'action' => 'update', 'domain' => 'element',
            'leaf_operation' => 'update', 'operation' => 'update', 'scope' => 'page',
            'target' => ['type' => 'element', 'key' => 'heading'], 'changes' => [],
        ];
        $result = $actionMethod->invoke(
            $controller, 'Make the hero heading smaller.', 'page', -1, $blocks, [], $genericCanonical
        );
        $verified = $verifyMethod->invoke(
            $controller,
            $result,
            $blocks,
            [],
            $genericCanonical,
            app(\App\Services\LunaExecutionVerificationService::class),
            app(\App\Services\LunaContextStateService::class),
            [],
            ['surface' => 'builder', 'current_page_id' => 182, 'ui_scope' => 'page', 'target_index' => -1]
        );

        $this->assertSame('complete', $verified['execution_verification']['status']);
        $last = $verified['site_memory']['context_state']['last_verified_action'];
        $this->assertSame('typography', $last['domain']);
        $this->assertSame('decrease', $last['changes']['relative_size']['direction']);
        $this->assertSame('hero.h1', $last['target']['key']);
        $this->assertStringContainsString('slightly smaller', $verified['reply']);
        $resolved = app(\App\Services\LunaContextResolverService::class)->resolve(
            'A little more.',
            ['current' => ['page_id' => 182], 'last_verified_action' => $last],
            []
        );
        $this->assertTrue($resolved['execution_allowed']);
        $this->assertSame('decrease', $resolved['inherit']['changes']['relative_size']['direction']);

        $reverseCanonical = $genericCanonical;
        $reverseCanonical['domain'] = 'typography';
        $reverseCanonical['leaf_operation'] = 'font_size';
        $reverseCanonical['scope'] = 'section';
        $reverseCanonical['target'] = $last['target'];
        $reverseCanonical['changes'] = ['relative_size' => ['direction' => 'increase', 'amount' => 'slight']];
        $reverseResult = $actionMethod->invoke(
            $controller, 'Too much.', 'page', -1, $verified['blocks'], [], $reverseCanonical
        );
        $reverseVerified = $verifyMethod->invoke(
            $controller,
            $reverseResult,
            $verified['blocks'],
            [],
            $reverseCanonical,
            app(\App\Services\LunaExecutionVerificationService::class),
            app(\App\Services\LunaContextStateService::class),
            $verified['site_memory'],
            ['surface' => 'builder', 'current_page_id' => 182]
        );
        $this->assertStringContainsString('slightly larger', $reverseVerified['reply']);
        $this->assertStringNotContainsString('smaller', $reverseVerified['reply']);
    }

    public function test_noop_typography_executor_cannot_claim_success_or_replace_verified_context(): void
    {
        $controller = app(\App\Http\Controllers\CustomSparkController::class);
        $verifyMethod = new \ReflectionMethod($controller, 'lunaVerifyTypographyAction');
        $value = 'clamp(2.7rem,5.4vw,5.175rem)';
        $blocks = [[
            'type' => 'hero_cinematic_slider_premium',
            'luna_typography_overrides' => ['h1_size' => $value],
        ]];
        $result = [
            'reply' => 'Updated only this section h1 size.',
            'blocks' => $blocks,
            'typography_settings' => [],
            'before_value' => $value,
            'after_value' => $value,
            'relative_direction' => 'decrease',
            'applied_operations' => [[
                'action' => 'typography_local', 'index' => 0, 'role' => 'h1',
                'property' => 'size', 'value' => $value,
            ]],
        ];
        $canonical = [
            'intent' => 'action', 'action' => 'update', 'domain' => 'typography',
            'leaf_operation' => 'font_size', 'operation' => 'update', 'scope' => 'section',
            'target' => ['type' => 'heading', 'key' => 'hero.h1'],
            'changes' => ['relative_size' => ['direction' => 'decrease', 'amount' => 'slight']],
        ];

        $verified = $verifyMethod->invoke(
            $controller, $result, $blocks, [], $canonical,
            app(\App\Services\LunaExecutionVerificationService::class),
            app(\App\Services\LunaContextStateService::class),
            [],
            ['surface' => 'builder', 'current_page_id' => 182]
        );

        $this->assertSame('failed', $verified['execution_verification']['status']);
        $this->assertFalse($verified['execution_verification']['can_claim_complete']);
        $this->assertSame([], $verified['applied_operations']);
        $this->assertNull($verified['site_memory']['context_state']['last_verified_action']);
        $this->assertStringContainsString('did not mark it as complete', $verified['reply']);
    }

    public function test_explicit_all_h2_request_still_updates_the_global_token(): void
    {
        $controller = app(\App\Http\Controllers\CustomSparkController::class);
        $method = new \ReflectionMethod($controller, 'lunaTypographyAction');

        $result = $method->invoke(
            $controller,
            'Make all H2 headings smaller across the site.',
            'page',
            -1,
            [['type' => 'hero_cinematic_slider_premium'], ['type' => 'services_bento_premium']],
            []
        );

        $this->assertSame('clamp(2.025rem,3.645vw,3.6rem)', $result['typography_settings']['h2_size']);
        $this->assertSame('typography_global', $result['applied_operations'][0]['action']);
        $this->assertSame('h2', $result['applied_operations'][0]['role']);
    }

    public function test_restaurant_evening_template_has_a_balanced_image_content_rhythm(): void
    {
        $template = PageTemplateCatalog::find('restaurant-evening-story');
        $this->assertNotNull($template);

        $audit = app(TemplateQualityAuditor::class)->audit($template);

        $this->assertLessThanOrEqual(0.60, $audit['image_heavy_ratio']);
        $this->assertLessThanOrEqual(1, $audit['max_consecutive_image_heavy']);
        $this->assertGreaterThanOrEqual(4, $audit['content_mode_count']);
        $this->assertSame([], $audit['invalid_sections']);
    }

    public function test_image_only_compositions_are_flagged_for_rhythm_review(): void
    {
        $audit = app(TemplateQualityAuditor::class)->audit([
            'style' => ['cinematic', 'image-led'],
            'industry' => ['hospitality'],
            'sections' => [
                'hero_ken_burns_premium',
                'restaurant_signature_dishes_premium',
                'restaurant_story_menu_premium',
                'restaurant_atmosphere_gallery_premium',
                'testimonials_featured_story_premium',
                'restaurant_reservation_cta_premium',
            ],
        ]);

        $this->assertGreaterThan(0.60, $audit['image_heavy_ratio']);
        $this->assertGreaterThan(1, $audit['max_consecutive_image_heavy']);
        $this->assertNotEmpty($audit['composition_issues']);
    }

    public function test_registry_description_marks_non_obvious_image_rich_sparks_as_visual(): void
    {
        $auditor = app(TemplateQualityAuditor::class);

        $this->assertTrue($auditor->isImageHeavySection('brand_value_cards_premium'));
        $this->assertTrue($auditor->isImageHeavySection('testimonials_editorial_quotes_premium'));
        $this->assertTrue($auditor->isImageHeavySection('restaurant_reservation_cta_premium'));
    }

    public function test_catalog_balancer_breaks_visual_runs_without_replacing_the_hero(): void
    {
        $template = app(TemplateCompositionBalancer::class)->balance([
            'key' => 'synthetic-restaurant-run',
            'style' => ['cinematic', 'image-led'],
            'industry' => ['restaurant'],
            'sections' => [
                'hero_ken_burns_premium',
                'restaurant_story_menu_premium',
                'brand_value_cards_premium',
                'testimonials_editorial_quotes_premium',
                'restaurant_reservation_cta_premium',
            ],
        ]);

        $profile = app(TemplateQualityAuditor::class)->imageProfile($template['sections']);

        $this->assertSame('hero_ken_burns_premium', $template['sections'][0]);
        $this->assertLessThanOrEqual(1, $profile['max_consecutive']);
        $this->assertTrue($template['auto_balanced']);
        $this->assertNotEmpty($template['balanced_replacements']);
    }

    public function test_coffee_template_alternates_visual_and_non_visual_sections(): void
    {
        $template = PageTemplateCatalog::find('coffee-brand-atmosphere');
        $profile = app(TemplateQualityAuditor::class)->imageProfile($template['sections']);

        $this->assertLessThanOrEqual(0.60, $profile['ratio']);
        $this->assertLessThanOrEqual(1, $profile['max_consecutive']);
        $this->assertSame([
            'hero_ken_burns_premium',
            'services_minimal_luxury',
            'restaurant_story_menu_premium',
            'about_mission_grid',
            'gallery_image_rail_premium',
            'cta_gradient_premium',
        ], $template['sections']);
    }

    public function test_restaurant_visual_queries_change_subject_by_section_role(): void
    {
        $builder = app(VisualQueryBuilder::class);
        $prompt = 'Premium restaurant website for Milagrina in Ormoc City';

        $hero = $builder->build($prompt, ['heading' => 'Welcome'], 'hero_ken_burns_premium', 'restaurant');
        $menu = $builder->build($prompt, ['heading' => 'Our menu'], 'restaurant_story_menu_premium', 'restaurant');
        $location = $builder->build($prompt, ['heading' => 'Plan your visit'], 'location_photo_cards_premium', 'restaurant');

        $this->assertNotSame($hero, $menu);
        $this->assertNotSame($menu, $location);
        $this->assertStringContainsString('cuisine', $menu);
        $this->assertStringContainsString('storefront', $location);
    }
}
