<?php

namespace App\Support;

/**
 * Canonical registry for AI Flex primitives.
 *
 * This is intentionally capability-aware: components may be registered before
 * their renderer is enabled. Luna/validators must only emit renderer-ready
 * primitives, while future batches can activate reserved primitives without
 * introducing a second schema vocabulary.
 */
final class AiFlexComponentRegistry
{
    public const VERSION = 1;
    public const CONTRACT = 'ai_flex_components_v1';

    /** @return array<string,array<string,mixed>> */
    public static function definitions(): array
    {
        return [
            // Layout primitives already supported by the Universal Elements renderer.
            'group' => self::container('layout', true, ['*']),
            'row' => self::container('layout', true, ['column']),
            'column' => self::container('layout', true, ['*']),
            'grid' => self::container('layout', true, ['*']),
            'stack' => self::container('layout', true, ['*']),
            'card' => self::container('ui', true, ['*']),

            // Content/media primitives already supported today.
            'heading' => self::leaf('content', true, ['text']),
            'text' => self::leaf('content', true, ['text']),
            'button' => self::leaf('action', true, ['label', 'text', 'url']),
            'image' => self::leaf('media', true, ['src', 'alt', 'image_query']),
            'video' => self::leaf('media', true, ['src', 'poster', 'controls', 'autoplay', 'muted', 'loop', 'plays_inline']),
            'icon' => self::leaf('content', true, ['icon']),
            'badge' => self::leaf('content', true, ['label', 'text']),
            'list' => self::leaf('content', true, ['items']),
            'divider' => self::leaf('decorative', true, []),
            'stat' => self::leaf('content', true, ['value', 'text', 'label']),
            'spacer' => self::leaf('decorative', true, []),
            'form' => self::leaf('form', true, ['title', 'note', 'button_label', 'success_message', 'columns', 'button_alignment', 'fields']),

            // Canonical future primitives. Registered now, renderer activation follows
            // in the dedicated implementation batches so schema and rendering cannot drift.
            'background_image' => self::container('media_background', true, ['*'], 'batch_2', ['src','alt','image_query']),
            'background_video' => self::container('media_background', true, ['*'], 'batch_2', ['src','poster','controls','autoplay','muted','loop','plays_inline']),
            'overlay' => self::container('decorative', true, ['*'], 'batch_2'),
            'slider' => self::container('interactive', true, ['slide'], 'batch_3', ['autoplay','interval','loop','show_arrows','show_dots','show_counter','transition']),
            'slide' => self::container('interactive', true, ['*'], 'batch_3'),
            'button_group' => self::container('action', true, ['button'], 'batch_4'),
            'media_group' => self::container('media', true, ['image', 'video'], 'batch_4'),
        ];
    }

    /** @return list<string> */
    public static function rendererReadyTypes(): array
    {
        return array_values(array_keys(array_filter(
            self::definitions(),
            static fn (array $definition): bool => (bool) ($definition['renderer_ready'] ?? false),
        )));
    }

    /** @return list<string> */
    public static function plannedTypes(): array
    {
        return array_values(array_keys(array_filter(
            self::definitions(),
            static fn (array $definition): bool => ! (bool) ($definition['renderer_ready'] ?? false),
        )));
    }

    /** @return list<string> */
    public static function containerTypes(bool $rendererReadyOnly = true): array
    {
        return array_values(array_keys(array_filter(
            self::definitions(),
            static fn (array $definition): bool => (bool) ($definition['container'] ?? false)
                && (! $rendererReadyOnly || (bool) ($definition['renderer_ready'] ?? false)),
        )));
    }

    public static function isKnown(string $type): bool
    {
        return isset(self::definitions()[strtolower(trim($type))]);
    }

    public static function isRendererReady(string $type): bool
    {
        return (bool) (self::definitions()[strtolower(trim($type))]['renderer_ready'] ?? false);
    }

    /** @return list<string> */
    public static function allowedChildren(string $type): array
    {
        $children = self::definitions()[strtolower(trim($type))]['allowed_children'] ?? [];
        return is_array($children) ? array_values(array_map('strval', $children)) : [];
    }

    /** @return list<string> */
    public static function scalarKeys(string $type): array
    {
        $keys = self::definitions()[strtolower(trim($type))]['scalar_keys'] ?? [];
        return is_array($keys) ? array_values(array_map('strval', $keys)) : [];
    }

    /** Shared safe style vocabulary for renderer-ready Universal Elements. */
    public static function styleKeys(): array
    {
        return [
            'gap','columns','width','max_width','min_height','padding','padding_x','padding_y','radius',
            'background','color','border_color','border_width','shadow','align','justify','text_align',
            'font_size','font_weight','line_height','aspect_ratio','object_fit','object_position','opacity',
            'position','top','right','bottom','left','z_index','overflow','order','grow','basis','self_align',
            'tablet_width','mobile_width','tablet_columns','mobile_columns','tablet_gap','mobile_gap',
            'tablet_padding','mobile_padding','tablet_order','mobile_order','tablet_position','mobile_position',
            'tablet_min_height','mobile_min_height',
        ];
    }

    /** Compact machine-readable inventory safe to include in Luna system prompts. */
    public static function promptInventory(): string
    {
        $parts = [];
        foreach (self::definitions() as $type => $definition) {
            if (! ($definition['renderer_ready'] ?? false)) continue;
            $children = (array) ($definition['allowed_children'] ?? []);
            $suffix = ($definition['container'] ?? false)
                ? ' children='.implode('|', $children ?: ['none'])
                : '';
            $parts[] = $type.$suffix;
        }
        return implode(', ', $parts);
    }

    /** @return array<string,mixed> */
    private static function container(string $family, bool $rendererReady, array $allowedChildren, ?string $activationBatch = null, array $scalarKeys = []): array
    {
        return [
            'family' => $family,
            'container' => true,
            'renderer_ready' => $rendererReady,
            'allowed_children' => array_values($allowedChildren),
            'scalar_keys' => array_values($scalarKeys),
            'activation_batch' => $activationBatch,
        ];
    }

    /** @return array<string,mixed> */
    private static function leaf(string $family, bool $rendererReady, array $scalarKeys, ?string $activationBatch = null): array
    {
        return [
            'family' => $family,
            'container' => false,
            'renderer_ready' => $rendererReady,
            'allowed_children' => [],
            'scalar_keys' => array_values($scalarKeys),
            'activation_batch' => $activationBatch,
        ];
    }
}
