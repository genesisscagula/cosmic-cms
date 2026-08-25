<?php

namespace App\Services;

use App\AI\Schemas\SchemaManager;
use Illuminate\Support\Str;

final class SparkEditCapabilityRegistry
{
    public function __construct(private readonly SparkTailwindSchemaContract $tailwindSchema)
    {
    }

    public const BASE_MODULES = ['Typography', 'Spacing', 'Surface', 'Responsive', 'Visibility'];
    private ?array $catalogByKey = null;

    public function declaration(string $sparkType, array $block = []): array
    {
        $schemaMap = SchemaManager::map();
        if (! isset($schemaMap[$sparkType]) && $sparkType !== 'luna_custom_section') {
            throw new \InvalidArgumentException("Unknown registered Spark [{$sparkType}].");
        }

        $catalog = $this->catalogByKey()[$sparkType] ?? [];
        $modules = $this->inferModules($sparkType, $block, $catalog);
        $moduleContracts = (array) config('spark-edit-capabilities.modules', []);

        return [
            'spark_type' => $sparkType,
            'schema_reference' => $schemaMap[$sparkType] ?? 'internalCustomSparkSchema',
            'catalog' => array_intersect_key($catalog, array_flip(['name', 'category', 'media', 'layout', 'capabilities'])),
            'modules' => array_values($modules),
            'module_contracts' => collect($modules)
                ->mapWithKeys(fn (string $module): array => [$module => $moduleContracts[$module] ?? []])
                ->all(),
            'targets' => $this->targetContracts($modules, $moduleContracts),
            'writable_fields' => $this->writableFields($block),
            'semantic_treatments' => (array) config('spark-edit-capabilities.semantic_treatments', []),
            'tailwind_schema' => $this->tailwindSchema->declaration($sparkType, $block),
        ];
    }

    public function allDeclarations(): array
    {
        return collect(array_keys(SchemaManager::map()))
            ->mapWithKeys(fn (string $type): array => [$type => $this->declaration($type)])
            ->all();
    }

    public function selectedSparkPayload(?string $sparkType, array $block = [], array $elementContext = []): array
    {
        if ($sparkType === null || (! isset(SchemaManager::map()[$sparkType]) && $sparkType !== 'luna_custom_section')) {
            return ['mode' => 'selected_spark', 'selected_spark' => null, 'element_context' => $elementContext];
        }

        return [
            'mode' => 'selected_spark',
            'selected_spark' => $this->declaration($sparkType, $block),
            'element_context' => $elementContext,
        ];
    }

    public function structuralCatalog(): array
    {
        return collect(SparkCatalog::all())->map(function (array $spark): array {
            $type = (string) ($spark['key'] ?? '');
            return [
                'key' => $type,
                'name' => $spark['name'] ?? Str::headline($type),
                'category' => $spark['category'] ?? 'Other',
                'aliases' => $spark['aliases'] ?? [],
                'media' => $spark['media'] ?? 'mixed',
                'layout' => $spark['layout'] ?? ['standard'],
                'intent' => $spark['intent'] ?? ['general'],
                'capability_modules' => $this->inferModules($type, [], $spark),
            ];
        })->values()->all();
    }

    public function payloadForRequest(
        string $prompt,
        ?string $sparkType,
        array $block = [],
        array $elementContext = [],
        array $canonicalIntent = [],
    ): array {
        if ($this->isStructuralRequest($prompt, $canonicalIntent)) {
            return ['mode' => 'structural_catalog', 'catalog' => $this->structuralCatalog()];
        }

        return $this->selectedSparkPayload($sparkType, $block, $elementContext);
    }

    public function isStructuralRequest(string $prompt, array $canonicalIntent = []): bool
    {
        $domain = Str::lower((string) ($canonicalIntent['domain'] ?? ''));
        $operation = Str::lower((string) ($canonicalIntent['operation'] ?? $canonicalIntent['leaf_operation'] ?? ''));
        if (in_array($domain, ['page', 'section', 'site'], true)
            && in_array($operation, ['add', 'create', 'remove', 'delete', 'reorder', 'move', 'redesign', 'replace'], true)) {
            return true;
        }

        return (bool) preg_match(
            '/\b(?:add|insert|create|remove|delete|replace|swap|reorder|move)\s+(?:a|an|the|this|another|new)?\s*(?:spark|section|block)\b|\b(?:redesign|rebuild)\s+(?:the\s+)?(?:whole|entire|full)?\s*(?:page|site|website|section)\b|\b(?:change|turn|convert)\s+(?:this|the|selected)?\s*(?:banner|hero|section|block)\s+(?:to|into)\b|\b(?:make|improve|polish|refresh|rework|restyle)\s+(?:this|the|selected)?\s*(?:section|block)\s+(?:better|premium|modern|polished|different|more\s+premium|more\s+modern|more\s+polished)?\b|\buse\s+(?:a|an)\s+(?:slider|carousel|video hero|different section layout)\b/i',
            $prompt,
        );
    }

    /**
     * Canonical persistence/render contract for deterministic Spark mutations.
     *
     * Keeping this beside the capability declarations prevents the router,
     * validator, renderer and verifier from each inventing a different field
     * for the same user-facing property. Legacy paths are read-only migration
     * fallbacks; every new mutation is written to storage_path.
     */
    public function mutationContract(string $sparkType, array $block, string $property, array $context = []): ?array
    {
        $property = Str::lower(trim($property));
        $modules = $this->inferModules($sparkType, $block, $this->catalogByKey()[$sparkType] ?? []);
        $role = $this->typographyRole($sparkType, $context);

        $definitions = [
            'font_size' => [
                'module' => 'Typography',
                'storage_path' => 'luna_typography_overrides.'.$role.'_size',
                'fallback_paths' => [],
                'render_token' => '--cosmic-local-'.$role.'-size',
                'step' => 4, 'min' => 12, 'max' => 112, 'unit' => 'px',
            ],
            'padding' => [
                'module' => 'Spacing',
                'storage_path' => 'luna_section_overrides.py',
                'fallback_paths' => ['luna_design_overrides.section_padding_y'],
                'render_token' => '--cosmic-local-section-py',
                'step' => 8, 'min' => 0, 'max' => 240, 'unit' => 'px',
            ],
            'gap' => [
                'module' => 'Spacing',
                'storage_path' => 'luna_section_overrides.gap',
                'fallback_paths' => ['luna_design_overrides.content_gap'],
                'render_token' => '--cosmic-local-section-gap',
                'step' => 4, 'min' => 0, 'max' => 120, 'unit' => 'px',
            ],
            'section_height' => [
                'module' => 'Spacing',
                'storage_path' => 'luna_section_overrides.min_height',
                'fallback_paths' => ['luna_design_overrides.section_min_height'],
                'render_token' => '--cosmic-local-section-min-height',
                'step' => 40, 'min' => 0, 'max' => 1200, 'unit' => 'px',
            ],
            'border_radius' => [
                'module' => 'Surface',
                'storage_path' => 'luna_component_overrides.card_radius',
                'fallback_paths' => ['luna_design_overrides.card_radius'],
                'render_token' => '--cosmic-local-card-radius',
                'default' => '24px',
                'step' => 4, 'min' => 0, 'max' => 80, 'unit' => 'px',
            ],
            'card_surface' => [
                'module' => 'Surface',
                'storage_path' => 'luna_component_overrides.card_surface',
                'fallback_paths' => [],
                'render_token' => '--cosmic-local-card-bg',
                'default' => 'surface',
                'allowed' => ['surface', 'dark'],
            ],
            'overlay_opacity' => [
                'module' => 'Overlay',
                'storage_path' => 'overlayOpacity',
                'fallback_paths' => ['luna_background_overrides.overlay_primary_strong'],
                'render_token' => '--cosmic-overlay-opacity',
                'step' => 6, 'min' => 0, 'max' => 100, 'unit' => '',
            ],
        ];

        $definition = $definitions[$property] ?? null;
        if ($definition === null || ! in_array($definition['module'], $modules, true)) return null;

        return ['property' => $property] + $definition;
    }

    public function inferModules(string $sparkType, array $block = [], array $catalog = []): array
    {
        $text = Str::lower($sparkType.' '.($block['semantic_type'] ?? '').' '.($block['category'] ?? '').' '.($block['source_type'] ?? '').' '.json_encode([
            $catalog['media'] ?? null,
            $catalog['layout'] ?? null,
            $catalog['capabilities'] ?? null,
        ]));
        $keys = array_keys($block);
        $modules = self::BASE_MODULES;

        if (preg_match('/hero|image|photo|gallery|portfolio|video|media|logo|avatar|slider|carousel/', $text)
            || $this->keysMatch($keys, '/image|photo|avatar|poster|logo|video|media/i')) {
            $modules[] = 'Media';
            $modules[] = 'Dimensions';
        }
        if (preg_match('/hero|banner|image|video|media|slider|cinematic|parallax/', $text)
            || array_key_exists('overlayOpacity', $block)) {
            $modules[] = 'Overlay';
        }
        if (preg_match('/grid|cards|bento|mosaic|gallery|pricing|team|services|features|stats|portfolio|columns|split/', $text)
            || $this->keysMatch($keys, '/columns|layout|grid/i')) {
            $modules[] = 'GridLayout';
        }
        if (preg_match('/hero|cta|contact|pricing|lead|sales|commerce/', $text)
            || $this->keysMatch($keys, '/button|cta|primary_label|secondary_label/i')) {
            $modules[] = 'Buttons';
        }

        $collectionKeys = (array) config('spark-edit-capabilities.modules.ItemCollection.collection_keys', []);
        if (collect($collectionKeys)->contains(fn (string $key): bool => isset($block[$key]) && is_array($block[$key]))
            || preg_match('/cards|grid|gallery|slider|carousel|team|pricing|services|features|steps|timeline|faq|testimonials|logos/', $text)) {
            $modules[] = 'ItemCollection';
        }

        return array_values(array_unique($modules));
    }

    private function writableFields(array $block): array
    {
        $protected = (array) config('spark-edit-capabilities.protected_fields', []);
        return collect(array_keys($block))
            ->filter(fn ($key): bool => is_string($key) && ! in_array($key, $protected, true))
            ->values()->all();
    }

    /**
     * Compact target declarations are derived from reusable modules rather
     * than copying hundreds of near-identical Spark schemas. A target is only
     * exposed when its renderer/storage capability is present.
     */
    private function targetContracts(array $modules, array $moduleContracts): array
    {
        $definitions = [
            'section' => ['Spacing', 'Surface', 'Responsive', 'Visibility'],
            'heading' => ['Typography', 'Responsive', 'Visibility'],
            'text' => ['Typography', 'Responsive', 'Visibility'],
            'media' => ['Media', 'Dimensions', 'Responsive', 'Visibility'],
            'overlay' => ['Overlay'],
            'grid' => ['GridLayout', 'Spacing', 'Responsive'],
            'button' => ['Buttons', 'Typography', 'Responsive', 'Visibility'],
            'collection' => ['ItemCollection', 'GridLayout', 'Spacing', 'Surface', 'Responsive'],
            'item' => ['ItemCollection', 'Typography', 'Surface', 'Media', 'Buttons', 'Visibility'],
        ];

        return collect($definitions)->map(function (array $required) use ($modules, $moduleContracts): array {
            $supported = array_values(array_intersect($required, $modules));
            return [
                'modules' => $supported,
                'operations' => collect($supported)
                    ->flatMap(fn (string $module): array => (array) ($moduleContracts[$module]['operations'] ?? []))
                    ->unique()->values()->all(),
            ];
        })->filter(fn (array $contract): bool => $contract['modules'] !== [])->all();
    }

    private function keysMatch(array $keys, string $pattern): bool
    {
        return collect($keys)->contains(fn ($key): bool => is_string($key) && (bool) preg_match($pattern, $key));
    }

    private function typographyRole(string $sparkType, array $context): string
    {
        $role = Str::lower((string) ($context['role'] ?? $context['type'] ?? ''));
        if (in_array($role, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'body', 'lead', 'eyebrow', 'button'], true)) return $role;
        return Str::contains($sparkType, 'hero') ? 'h1' : 'h2';
    }

    private function catalogByKey(): array
    {
        if ($this->catalogByKey !== null) return $this->catalogByKey;
        return $this->catalogByKey = collect(SparkCatalog::all())->keyBy('key')->all();
    }
}
