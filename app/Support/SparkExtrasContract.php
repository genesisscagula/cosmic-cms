<?php

namespace App\Support;

/**
 * Server-side mirror of the Spark Extras v1 storage contract.
 *
 * This keeps saved block payloads safe and backward compatible before later
 * batches teach Luna/renderers how to create and display the extras.
 */
final class SparkExtrasContract
{
    public const VERSION = 2;
    public const STORAGE_KEY = 'field_extras';
    public const PLACEMENTS = ['before', 'after'];
    public const TYPES = [
        'text',
        'heading',
        'image',
        'button',
        'icon',
        'badge',
        'video',
        'divider',
        'spacer',
        'list',
        'quote',
        'stat',
    ];

    private const MAX_TEXT_LENGTH = 5000;
    private const MAX_URL_LENGTH = 2048;
    private const MAX_TARGET_PATH_LENGTH = 320;
    private const MAX_EXTRA_ITEMS_PER_PLACEMENT = 40;

    public static function normalizeBlocks(array $blocks): array
    {
        return array_values(array_map(
            fn ($block) => is_array($block) ? self::normalizeBlock($block) : $block,
            $blocks
        ));
    }

    public static function normalizeBlock(array $block): array
    {
        $block[self::STORAGE_KEY] = self::normalizeState(
            is_array($block[self::STORAGE_KEY] ?? null) ? $block[self::STORAGE_KEY] : []
        );

        // Batch 4: preserve one authoritative AI Flex tree while attaching stable
        // row/column/extra identities. Legacy arbitrary element trees keep their
        // original shape unless they already opt into the canonical contract.
        return class_exists(AiFlexStructureContract::class)
            ? AiFlexStructureContract::normalizePersistedBlock($block)
            : $block;
    }

    public static function normalizeState(array $fieldExtras): array
    {
        $normalized = [];
        $usedIds = [];

        foreach ($fieldExtras as $rawTarget => $rawSlots) {
            $target = self::normalizeTargetPath((string) $rawTarget);
            if ($target === null || ! is_array($rawSlots)) {
                continue;
            }

            $slots = self::normalizeSlots($rawSlots, $usedIds);
            if ($slots['before'] !== [] || $slots['after'] !== []) {
                $normalized[$target] = $slots;
            }
        }

        return $normalized;
    }

    public static function normalizeTargetPath(string $value): ?string
    {
        $raw = substr(trim(str_replace("\0", '', $value)), 0, self::MAX_TARGET_PATH_LENGTH);
        $raw = preg_replace('/\[([0-9]+)\]/', '.$1', $raw) ?? $raw;
        $raw = preg_replace('/^\.+|\.+$/', '', $raw) ?? $raw;
        $raw = preg_replace('/\.{2,}/', '.', $raw) ?? $raw;

        if ($raw === '') {
            return null;
        }

        $segments = explode('.', $raw);
        foreach ($segments as $segment) {
            if ($segment === '' || in_array($segment, ['__proto__', 'prototype', 'constructor'], true)) {
                return null;
            }

            if (preg_match('/^[0-9]+$/', $segment)) {
                continue;
            }

            if (preg_match('/^@[A-Za-z0-9_-]{1,128}$/', $segment)) {
                continue;
            }

            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_-]{0,127}$/', $segment)) {
                return null;
            }
        }

        return implode('.', $segments);
    }

    public static function normalizeItem(array $item): ?array
    {
        $type = strtolower(self::cleanString($item['type'] ?? '', 80));
        if (! in_array($type, self::TYPES, true)) {
            return null;
        }

        $normalized = [
            'id' => self::normalizeId($item['id'] ?? null),
            'type' => $type,
            'data' => self::normalizeData($type, is_array($item['data'] ?? null) ? $item['data'] : []),
        ];
        if (is_array($item['style'] ?? null)) $normalized['style'] = array_slice($item['style'], 0, 64, true);
        if (is_array($item['meta'] ?? null)) $normalized['meta'] = [
            'style_mode' => self::cleanEnum($item['meta']['style_mode'] ?? '', ['global','custom'], 'global'),
            'responsive_mode' => self::cleanEnum($item['meta']['responsive_mode'] ?? '', ['auto','custom'], 'auto'),
            'auto_align' => self::cleanBoolean($item['meta']['auto_align'] ?? true, true),
        ];
        return $normalized;
    }

    private static function normalizeSlots(array $slots, array &$usedIds): array
    {
        return [
            'before' => self::normalizeList(is_array($slots['before'] ?? null) ? $slots['before'] : [], $usedIds),
            'after' => self::normalizeList(is_array($slots['after'] ?? null) ? $slots['after'] : [], $usedIds),
        ];
    }

    private static function normalizeList(array $items, array &$usedIds): array
    {
        $normalized = [];

        foreach (array_slice($items, 0, self::MAX_EXTRA_ITEMS_PER_PLACEMENT) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $extra = self::normalizeItem($item);
            if ($extra === null) {
                continue;
            }

            while (isset($usedIds[$extra['id']])) {
                $extra['id'] = self::createId();
            }

            $usedIds[$extra['id']] = true;
            $normalized[] = $extra;
        }

        return $normalized;
    }

    private static function normalizeData(string $type, array $data): array
    {
        return match ($type) {
            'text' => [
                'text' => self::cleanString($data['text'] ?? ''),
            ],
            'heading' => [
                'text' => self::cleanString($data['text'] ?? ''),
                'level' => max(1, min(6, (int) ($data['level'] ?? 2) ?: 2)),
            ],
            'image' => [
                'src' => self::sanitizeUrl($data['src'] ?? '', true),
                'alt' => self::cleanString($data['alt'] ?? '', 500),
                'title' => self::cleanString($data['title'] ?? '', 500),
                'loading' => self::cleanEnum($data['loading'] ?? '', ['lazy', 'eager'], 'lazy'),
                'object_fit' => self::cleanEnum($data['object_fit'] ?? '', ['cover', 'contain', 'fill', 'none', 'scale-down'], 'cover'),
            ],
            'button' => [
                'label' => self::cleanString($data['label'] ?? '', 500),
                'url' => self::sanitizeUrl($data['url'] ?? '', false),
                'target' => self::cleanEnum($data['target'] ?? '', ['_self', '_blank'], '_self'),
                'rel' => self::cleanString($data['rel'] ?? '', 250),
            ],
            'icon' => [
                'name' => self::cleanString($data['name'] ?? '', 160),
                'label' => self::cleanString($data['label'] ?? '', 500),
            ],
            'badge' => [
                'text' => self::cleanString($data['text'] ?? '', 500),
            ],
            'video' => [
                'src' => self::sanitizeUrl($data['src'] ?? '', true),
                'poster' => self::sanitizeUrl($data['poster'] ?? '', true),
                'autoplay' => self::cleanBoolean($data['autoplay'] ?? false, false),
                'muted' => self::cleanBoolean($data['muted'] ?? true, true),
                'loop' => self::cleanBoolean($data['loop'] ?? false, false),
                'controls' => self::cleanBoolean($data['controls'] ?? true, true),
                'plays_inline' => self::cleanBoolean($data['plays_inline'] ?? true, true),
            ],
            'divider' => [
                'orientation' => self::cleanEnum($data['orientation'] ?? '', ['horizontal', 'vertical'], 'horizontal'),
            ],
            'spacer' => [
                'size' => self::cleanEnum($data['size'] ?? '', ['xs', 'sm', 'md', 'lg', 'xl', '2xl'], 'md'),
            ],
            'list' => [
                'items' => array_values(array_filter(array_map(fn ($item) => self::cleanString(is_array($item) ? ($item['text'] ?? $item['label'] ?? $item['value'] ?? '') : $item, 1000), array_slice(is_array($data['items'] ?? null) ? $data['items'] : [], 0, 40)))),
            ],
            'quote' => [
                'text' => self::cleanString($data['text'] ?? ''),
                'cite' => self::cleanString($data['cite'] ?? '', 500),
            ],
            'stat' => [
                'value' => self::cleanString($data['value'] ?? '', 500),
                'label' => self::cleanString($data['label'] ?? '', 500),
            ],
            default => [],
        };
    }

    private static function cleanString(mixed $value, int $maxLength = self::MAX_TEXT_LENGTH): string
    {
        return substr(trim(str_replace("\0", '', (string) ($value ?? ''))), 0, $maxLength);
    }

    private static function cleanBoolean(mixed $value, bool $fallback): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (in_array($value, [1, '1', 'true'], true)) {
            return true;
        }

        if (in_array($value, [0, '0', 'false'], true)) {
            return false;
        }

        return $fallback;
    }

    private static function cleanEnum(mixed $value, array $allowed, string $fallback): string
    {
        $normalized = strtolower(self::cleanString($value, 80));
        return in_array($normalized, $allowed, true) ? $normalized : $fallback;
    }

    private static function sanitizeUrl(mixed $value, bool $media): string
    {
        $url = self::cleanString($value, self::MAX_URL_LENGTH);
        if ($url === '') {
            return '';
        }

        if (preg_match('/^(?:\/|\.\/|\.\.\/|#)/', $url)) {
            return $url;
        }

        if (preg_match('/^(?:javascript|vbscript|data):/i', $url)) {
            return '';
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $allowed = $media ? ['http', 'https'] : ['http', 'https', 'mailto', 'tel'];

        return in_array($scheme, $allowed, true) ? $url : '';
    }

    private static function normalizeId(mixed $value): string
    {
        $candidate = self::cleanString($value, 128);
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,127}$/', $candidate)
            ? $candidate
            : self::createId();
    }

    private static function createId(): string
    {
        return 'extra_'.bin2hex(random_bytes(16));
    }
}
