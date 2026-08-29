<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class SparkTailwindSchemaContract
{
    public function storageKey(): string
    {
        return (string) config('spark-tailwind-schema.storage_key', 'luna_tailwind_schema');
    }

    /**
     * Tailwind schema V2 keeps the old flat `slots` map as a compatibility
     * bridge while adding semantic shared `styles` and scoped collection/item
     * overrides. Existing Sparks therefore keep rendering unchanged until a
     * later migration batch starts writing V2 styles.
     *
     * V2 shape:
     * {
     *   version: 2,
     *   spark_type: "services_cards",
     *   styles: { card: {...}, title: {...} },
     *   collections: {
     *     items: [
     *       { key: "...", styles: { card: {...} }, collections: {...} }
     *     ]
     *   },
     *   slots: { ...legacy/shared compatibility slots... }
     * }
     */
    public function emptySchema(string $sparkType): array
    {
        return [
            'version' => (int) config('spark-tailwind-schema.version', 2),
            'spark_type' => $sparkType,
            'styles' => [],
            'collections' => [],
            'slots' => [],
        ];
    }

    /**
     * Return a normalized per-Spark-instance Tailwind schema without mutating
     * the block. Missing schemas are expected during the migration period.
     * Unknown metadata is preserved so future schema additions remain forwards
     * compatible, but the core maps are always normalized to arrays.
     */
    public function fromBlock(string $sparkType, array $block): array
    {
        $raw = Arr::get($block, $this->storageKey());
        if (! is_array($raw)) {
            return $this->emptySchema($sparkType);
        }

        $raw['version'] = (int) ($raw['version'] ?? config('spark-tailwind-schema.version', 2));
        $raw['spark_type'] = (string) ($raw['spark_type'] ?? $sparkType);
        $raw['styles'] = is_array($raw['styles'] ?? null) ? $raw['styles'] : [];
        $raw['collections'] = is_array($raw['collections'] ?? null) ? $raw['collections'] : [];
        $raw['slots'] = is_array($raw['slots'] ?? null) ? $raw['slots'] : [];

        return $raw;
    }

    public function declaration(string $sparkType, array $block = []): array
    {
        $schema = $this->fromBlock($sparkType, $block);

        return [
            'version' => (int) config('spark-tailwind-schema.version', 2),
            'storage_key' => $this->storageKey(),
            'scope' => 'spark_instance',
            'spark_type' => $sparkType,
            'styles' => array_keys((array) ($schema['styles'] ?? [])),
            'collections' => array_keys((array) ($schema['collections'] ?? [])),
            'legacy_slots' => array_keys((array) ($schema['slots'] ?? [])),
            'canonical_slots' => (array) config('spark-tailwind-schema.canonical_slots', []),
            'supports_shared_styles' => true,
            'supports_item_overrides' => true,
            'supports_nested_item_overrides' => true,
            'supports_responsive_variants' => true,
            'supports_state_variants' => true,
            'migration_state' => $this->isSchemaBacked($block) ? 'schema_backed' : 'legacy_fallback',
        ];
    }

    /** @return array<int,string> */
    public function tokenize(string|array|null $classes): array
    {
        if (is_array($classes)) {
            $classes = implode(' ', array_filter($classes, 'is_string'));
        }
        if (! is_string($classes) || trim($classes) === '') return [];

        // Tailwind arbitrary values may contain spaces inside square brackets,
        // so split only on whitespace while bracket depth is zero.
        $tokens = [];
        $buffer = '';
        $depth = 0;
        $escaped = false;
        $length = strlen($classes);

        for ($i = 0; $i < $length; $i++) {
            $char = $classes[$i];
            if ($escaped) {
                $buffer .= $char;
                $escaped = false;
                continue;
            }
            if ($char === '\\') {
                $buffer .= $char;
                $escaped = true;
                continue;
            }
            if ($char === '[') $depth++;
            if ($char === ']' && $depth > 0) $depth--;

            if (ctype_space($char) && $depth === 0) {
                if ($buffer !== '') $tokens[] = $buffer;
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        if ($buffer !== '') $tokens[] = $buffer;

        return array_values(array_unique(array_filter(array_map('trim', $tokens))));
    }

    public function normalizeSlot(string $slot): string
    {
        return Str::of($slot)->trim()->lower()->replace(['-', ' '], '_')->toString();
    }

    /**
     * Normalize an item scope path. Accepted inputs:
     *   ['items', 1]
     *   ['items', 1, 'features', 2]
     *   'items.1.features.2'
     *
     * Paths alternate collection name / item selector. A selector may be a
     * numeric index or a stable item `key` string.
     *
     * @return array<int,string|int>
     */
    public function normalizeScopePath(string|array|null $path): array
    {
        if (is_string($path)) {
            $path = array_values(array_filter(explode('.', trim($path, '.')), fn ($part): bool => $part !== ''));
        }
        if (! is_array($path) || $path === []) return [];

        $normalized = [];
        foreach (array_values($path) as $index => $part) {
            if ($index % 2 === 0) {
                $name = $this->normalizeSlot((string) $part);
                if ($name === '') return [];
                $normalized[] = $name;
                continue;
            }

            if (is_int($part) || (is_string($part) && ctype_digit($part))) {
                $normalized[] = (int) $part;
                continue;
            }

            $selector = trim((string) $part);
            if ($selector === '') return [];
            $normalized[] = $selector;
        }

        return count($normalized) % 2 === 0 ? $normalized : [];
    }

    public function scopePathString(string|array|null $path): string
    {
        return implode('.', array_map(fn ($part): string => (string) $part, $this->normalizeScopePath($path)));
    }

    public function scopeMarker(string|array|null $path, string $slot): string
    {
        $parts = $this->normalizeScopePath($path);
        $parts[] = $this->normalizeSlot($slot);
        $value = implode('__', array_map(
            function ($part): string {
                $normalized = preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string) $part);
                return $normalized === null || $normalized === '' ? 'x' : $normalized;
            },
            $parts,
        ));

        return 'cosmic-tw-path--'.Str::lower($value);
    }

    /**
     * Resolve the complete class string owned by a shared semantic style/slot.
     * V2 `styles` wins over legacy `slots`; absent definitions fall back to the
     * Spark renderer's hard-coded class string.
     */
    public function resolveSlot(array $block, string $slot, string|array|null $fallback = ''): string
    {
        $sparkType = (string) ($block['type'] ?? '');
        $schema = $this->fromBlock($sparkType, $block);
        $slot = $this->normalizeSlot($slot);

        if (array_key_exists($slot, $schema['styles'])) {
            return $this->resolveDefinition($schema['styles'][$slot], $fallback);
        }

        if (array_key_exists($slot, $schema['slots'])) {
            return $this->resolveDefinition($schema['slots'][$slot], $fallback);
        }

        return implode(' ', $this->tokenize($fallback));
    }

    /**
     * Resolve a per-item/nested-item style. Item style inherits the shared style
     * for the same semantic alias, which in turn inherits the renderer fallback.
     */
    public function resolveScopedStyle(
        array $block,
        string|array|null $path,
        string $slot,
        string|array|null $fallback = ''
    ): string {
        $shared = $this->resolveSlot($block, $slot, $fallback);
        $sparkType = (string) ($block['type'] ?? '');
        $schema = $this->fromBlock($sparkType, $block);
        $scope = $this->scopeAtPath($schema, $path);
        $slot = $this->normalizeSlot($slot);

        if (! is_array($scope) || ! array_key_exists($slot, (array) ($scope['styles'] ?? []))) {
            return $shared;
        }

        return $this->resolveDefinition($scope['styles'][$slot], $shared);
    }

    public function hasSlot(array $block, string $slot): bool
    {
        $sparkType = (string) ($block['type'] ?? '');
        $schema = $this->fromBlock($sparkType, $block);
        $slot = $this->normalizeSlot($slot);

        return array_key_exists($slot, $schema['styles']) || array_key_exists($slot, $schema['slots']);
    }

    public function hasScopedStyle(array $block, string|array|null $path, string $slot): bool
    {
        $sparkType = (string) ($block['type'] ?? '');
        $scope = $this->scopeAtPath($this->fromBlock($sparkType, $block), $path);
        if (! is_array($scope)) return false;

        return array_key_exists($this->normalizeSlot($slot), (array) ($scope['styles'] ?? []));
    }

    public function isSchemaBacked(array $block): bool
    {
        $sparkType = (string) ($block['type'] ?? '');
        $schema = $this->fromBlock($sparkType, $block);

        return ($schema['styles'] ?? []) !== []
            || ($schema['collections'] ?? []) !== []
            || ($schema['slots'] ?? []) !== [];
    }

    /**
     * Read one collection item scope from the V2 schema without mutating it.
     */
    public function scopeAtPath(array $schema, string|array|null $path): ?array
    {
        $path = $this->normalizeScopePath($path);
        if ($path === []) return null;

        $collections = is_array($schema['collections'] ?? null) ? $schema['collections'] : [];
        $scope = null;
        for ($i = 0; $i < count($path); $i += 2) {
            $collection = (string) $path[$i];
            $selector = $path[$i + 1] ?? null;
            $items = $collections[$collection] ?? null;
            if (! is_array($items)) return null;

            $scope = $this->findCollectionItem($items, $selector);
            if (! is_array($scope)) return null;
            $collections = is_array($scope['collections'] ?? null) ? $scope['collections'] : [];
        }

        return $scope;
    }

    private function findCollectionItem(array $items, mixed $selector): ?array
    {
        if (is_int($selector) && array_key_exists($selector, $items) && is_array($items[$selector])) {
            return $items[$selector];
        }

        if (is_string($selector) && ctype_digit($selector)) {
            $index = (int) $selector;
            if (array_key_exists($index, $items) && is_array($items[$index])) return $items[$index];
        }

        $needle = trim((string) $selector);
        if ($needle === '') return null;
        foreach ($items as $item) {
            if (! is_array($item)) continue;
            if ((string) ($item['key'] ?? '') === $needle) return $item;
        }

        return null;
    }

    private function resolveDefinition(mixed $definition, string|array|null $fallback): string
    {
        $fallbackTokens = $this->tokenize($fallback);

        if (! is_array($definition)) {
            return implode(' ', $this->tokenize(is_string($definition) ? $definition : ''));
        }

        $base = $this->tokenize($definition['base'] ?? []);
        $protected = $this->tokenize($definition['protected'] ?? []);
        $invariants = array_values(array_unique(array_merge($base, $protected)));
        $isPatch = ! array_key_exists('classes', $definition)
            && (array_key_exists('add', $definition) || array_key_exists('remove', $definition));

        if ($isPatch) {
            $tokens = $fallbackTokens;
            $remove = array_flip($this->tokenize($definition['remove'] ?? []));
            $tokens = array_values(array_filter($tokens, fn (string $token): bool => ! isset($remove[$token])));
            foreach ($this->tokenize($definition['add'] ?? []) as $token) {
                if (! in_array($token, $tokens, true)) $tokens[] = $token;
            }
            // Base classes are renderer invariants. They are always restored even
            // if a visual patch attempted to remove them.
            foreach (array_reverse($invariants) as $token) {
                $tokens = array_values(array_filter($tokens, fn (string $existing): bool => $existing !== $token));
                array_unshift($tokens, $token);
            }
            return implode(' ', $tokens);
        }

        $classes = array_key_exists('classes', $definition) ? $definition['classes'] : [];
        $tokens = $this->tokenize(is_string($classes) || is_array($classes) ? $classes : '');
        foreach (array_reverse($invariants) as $token) {
            $tokens = array_values(array_filter($tokens, fn (string $existing): bool => $existing !== $token));
            array_unshift($tokens, $token);
        }

        return implode(' ', $tokens);
    }
}
