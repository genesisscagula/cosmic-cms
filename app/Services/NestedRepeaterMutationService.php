<?php

namespace App\Services;

use App\Support\AiFlexStructureContract;
use App\Support\SparkExtrasContract;
use Illuminate\Support\Str;

/**
 * Generic path-based repeater mutations for registered Sparks.
 *
 * The service intentionally knows nothing about Services/Testimonial/etc.
 * Arrays of object rows can be addressed at any depth, including future
 * rows.{n}.columns.{n}.extras collections. Numeric and @stable-id selectors are
 * supported. Field-extra and Tailwind item addresses are remapped whenever
 * array indexes move.
 */
final class NestedRepeaterMutationService
{
    public const VERSION = 1;
    public const COSMIC_ID_KEY = '_cosmic_id';

    private const MAX_DEPTH = 8;
    private const SKIP_KEYS = [
        'field_extras', 'luna_tailwind_schema', 'tailwind_schema',
        'images', 'gallery_images', 'media',
    ];

    public function __construct(private readonly SparkTailwindSchemaContract $tailwindContract)
    {
    }

    /** @return array<int,array{path:array,path_string:string,collection:string,items:array}> */
    public function discover(array $block): array
    {
        $found = [];
        $walk = function (mixed $value, array $path = [], int $depth = 0) use (&$walk, &$found): void {
            if ($depth > self::MAX_DEPTH || ! is_array($value)) return;
            if ($this->isObjectRepeater($value)) {
                $found[] = [
                    'path' => $path,
                    'path_string' => implode('.', array_map('strval', $path)),
                    'collection' => (string) ($path[count($path) - 1] ?? ''),
                    'items' => array_values($value),
                ];
                foreach (array_values($value) as $index => $item) {
                    if (is_array($item)) $walk($item, [...$path, $index], $depth + 1);
                }
                return;
            }
            foreach ($value as $key => $child) {
                if (in_array(Str::lower((string) $key), self::SKIP_KEYS, true)) continue;
                if (is_array($child)) $walk($child, [...$path, (string) $key], $depth + 1);
            }
        };
        $walk($block);
        return $found;
    }

    public function primary(array $block): ?array
    {
        return $this->discover($block)[0] ?? null;
    }

    public function repeaterForContext(array $block, array $context = []): ?array
    {
        $repeaters = $this->discover($block);
        if ($repeaters === []) return null;

        $explicit = $this->normalizePath($context['collectionPath'] ?? $context['collection_path'] ?? null);
        if ($explicit !== []) {
            foreach ($repeaters as $repeater) {
                if ($this->samePath($repeater['path'], $explicit)) return $repeater;
            }
        }

        $fieldPath = $this->normalizePath($context['fieldPath'] ?? $context['field_path'] ?? null);
        $hint = trim((string) ($context['collectionKey'] ?? $context['collection_key'] ?? ''));
        $candidates = array_values(array_filter($repeaters, function (array $repeater) use ($fieldPath, $hint): bool {
            if ($hint !== '' && $repeater['collection'] !== $hint) return false;
            if ($fieldPath === []) return true;
            if (count($repeater['path']) >= count($fieldPath)) return false;
            foreach ($repeater['path'] as $offset => $segment) {
                if ((string) ($fieldPath[$offset] ?? '') !== (string) $segment) return false;
            }
            return true;
        }));
        usort($candidates, fn (array $a, array $b): int => count($b['path']) <=> count($a['path']));
        if ($candidates !== []) return $candidates[0];

        if ($hint !== '') {
            foreach ($repeaters as $repeater) if ($repeater['collection'] === $hint) return $repeater;
        }
        return $repeaters[0];
    }

    public function valueAtPath(array $source, string|array|null $path): mixed
    {
        $segments = ($source['type'] ?? '') === 'luna_custom_section'
            ? $this->normalizePath($path)
            : $this->normalizeRegisteredPath($path);
        if ($segments === []) return $source;
        $cursor = $source;
        foreach ($segments as $segment) {
            if (! is_array($cursor)) return null;
            if (array_is_list($cursor)) {
                $index = $this->resolveSelector($cursor, $segment);
                if ($index === null) return null;
                $cursor = $cursor[$index] ?? null;
                continue;
            }
            if (! array_key_exists((string) $segment, $cursor)) return null;
            $cursor = $cursor[(string) $segment];
        }
        return $cursor;
    }

    public function mutate(array $block, string|array $path, string $action, ?int $itemIndex = null, ?int $toIndex = null): array
    {
        $path = ($block['type'] ?? '') === 'luna_custom_section'
            ? $this->normalizePath($path)
            : $this->normalizeRegisteredPath($path);
        $path = $this->resolvedNumericPath($block, $path);
        $items = $this->valueAtPath($block, $path);
        if (! $this->isObjectRepeater($items)) return ['block' => $block, 'changed' => false, 'reason' => 'invalid_repeater'];
        $items = array_values($items);
        $count = count($items);
        $mutation = null;
        $newIndex = $itemIndex;

        if ($action === 'add') {
            $sourceIndex = $itemIndex !== null && isset($items[$itemIndex]) ? $itemIndex : $count - 1;
            $clone = $this->freshClone((array) $items[$sourceIndex], false);
            $newIndex = count($items);
            array_splice($items, $newIndex, 0, [$clone]);
            $mutation = ['action' => 'add', 'index' => $newIndex, 'source_index' => $sourceIndex];
        } elseif ($action === 'duplicate') {
            if ($itemIndex === null || ! isset($items[$itemIndex])) return ['block' => $block, 'changed' => false, 'reason' => 'item_unresolved'];
            $clone = $this->freshClone((array) $items[$itemIndex], true);
            $newIndex = $itemIndex + 1;
            array_splice($items, $newIndex, 0, [$clone]);
            $mutation = ['action' => 'duplicate', 'index' => $newIndex, 'source_index' => $itemIndex];
        } elseif ($action === 'remove') {
            if ($count <= 1) return ['block' => $block, 'changed' => false, 'reason' => 'minimum_items'];
            if ($itemIndex === null || ! isset($items[$itemIndex])) return ['block' => $block, 'changed' => false, 'reason' => 'item_unresolved'];
            array_splice($items, $itemIndex, 1);
            $mutation = ['action' => 'remove', 'index' => $itemIndex];
        } elseif ($action === 'move') {
            if ($itemIndex === null || ! isset($items[$itemIndex]) || $toIndex === null) return ['block' => $block, 'changed' => false, 'reason' => 'item_unresolved'];
            $toIndex = max(0, min($count - 1, $toIndex));
            if ($toIndex === $itemIndex) return ['block' => $block, 'changed' => false, 'reason' => 'no_change'];
            $moving = $items[$itemIndex];
            array_splice($items, $itemIndex, 1);
            array_splice($items, $toIndex, 0, [$moving]);
            $newIndex = $toIndex;
            $mutation = ['action' => 'move', 'from_index' => $itemIndex, 'to_index' => $toIndex];
        } else {
            return ['block' => $block, 'changed' => false, 'reason' => 'unsupported_action'];
        }

        $next = $this->setAtPath($block, $path, $items);
        $next[SparkExtrasContract::STORAGE_KEY] = $this->remapFieldExtras(
            is_array($next[SparkExtrasContract::STORAGE_KEY] ?? null) ? $next[SparkExtrasContract::STORAGE_KEY] : [],
            $path,
            $mutation,
        );
        $next = $this->remapTailwind($next, $path, $mutation);
        // Canonical AI Flex blocks re-scan the whole tree after duplicate/move so
        // descendant row/column/extra identities can never collide.
        $next = AiFlexStructureContract::normalizePersistedBlock($next);

        return [
            'block' => $next,
            'changed' => true,
            'path' => $path,
            'path_string' => implode('.', array_map('strval', $path)),
            'collection' => (string) ($path[count($path) - 1] ?? ''),
            'item_index' => $newIndex,
            'source_index' => $mutation['source_index'] ?? $itemIndex,
            'mutation' => $mutation,
        ];
    }

    /**
     * Insert one exact object into any repeater path, including an empty AI Flex
     * columns/extras collection. Unlike mutate(..., add), this does not clone a
     * sibling shape; the caller supplies the already-sanitized item.
     */
    public function insertItem(array $block, string|array $path, array $item, ?int $atIndex = null, bool $preserveIdentity = false): array
    {
        $path = ($block['type'] ?? '') === 'luna_custom_section'
            ? $this->normalizePath($path)
            : $this->normalizeRegisteredPath($path);
        $path = $this->resolvedNumericPath($block, $path);
        if ($path === []) return ['block' => $block, 'changed' => false, 'reason' => 'invalid_repeater'];
        $items = $this->valueAtPath($block, $path);
        if (! is_array($items) || ! array_is_list($items)) return ['block' => $block, 'changed' => false, 'reason' => 'invalid_repeater'];
        foreach ($items as $existing) if (! is_array($existing) || array_is_list($existing)) return ['block' => $block, 'changed' => false, 'reason' => 'invalid_repeater'];

        $count = count($items);
        $index = $atIndex === null ? $count : max(0, min($count, $atIndex));
        $item = $preserveIdentity ? $item : $this->freshIdentityTree($item);
        if ($preserveIdentity && $this->stableId($item) === '') $item = $this->freshIdentityTree($item);
        array_splice($items, $index, 0, [$item]);
        $mutation = ['action' => 'add', 'index' => $index, 'source_index' => max(0, $count - 1)];

        $next = $this->setAtPath($block, $path, array_values($items));
        $next[SparkExtrasContract::STORAGE_KEY] = $this->remapFieldExtras(
            is_array($next[SparkExtrasContract::STORAGE_KEY] ?? null) ? $next[SparkExtrasContract::STORAGE_KEY] : [],
            $path,
            $mutation,
        );
        $next = $this->remapTailwind($next, $path, $mutation);
        $next = AiFlexStructureContract::normalizePersistedBlock($next);

        return [
            'block' => $next,
            'changed' => true,
            'path' => $path,
            'path_string' => implode('.', array_map('strval', $path)),
            'collection' => (string) ($path[count($path) - 1] ?? ''),
            'item_index' => $index,
            'stable_id' => $this->stableId($item),
            'mutation' => $mutation,
        ];
    }

    /** Replace one exact repeater object without changing sibling indexes. */
    public function replaceItem(array $block, string|array $path, int|string $selector, array $item): array
    {
        $path = ($block['type'] ?? '') === 'luna_custom_section'
            ? $this->normalizePath($path)
            : $this->normalizeRegisteredPath($path);
        $path = $this->resolvedNumericPath($block, $path);
        $items = $this->valueAtPath($block, $path);
        if (! $this->isObjectRepeater($items)) return ['block' => $block, 'changed' => false, 'reason' => 'invalid_repeater'];
        $items = array_values($items);
        $index = $this->resolveSelector($items, $selector);
        if ($index === null) return ['block' => $block, 'changed' => false, 'reason' => 'item_unresolved'];
        $stable = $this->stableId((array) $items[$index]);
        if ($stable !== '') $item[self::COSMIC_ID_KEY] = $stable;
        elseif (! isset($item[self::COSMIC_ID_KEY])) $item[self::COSMIC_ID_KEY] = 'item_'.Str::uuid()->toString();
        $items[$index] = $item;
        $next = $this->setAtPath($block, $path, array_values($items));
        $next = AiFlexStructureContract::normalizePersistedBlock($next);
        return [
            'block' => $next, 'changed' => true, 'path' => $path,
            'path_string' => implode('.', array_map('strval', $path)),
            'collection' => (string) ($path[count($path) - 1] ?? ''), 'item_index' => $index,
        ];
    }

    public function normalizePath(string|array|null $path): array
    {
        // AI Flex exposes friendly logical paths (rows -> columns -> extras)
        // while persisted storage stays in elements/children for renderer parity.
        $logical = AiFlexStructureContract::logicalToStoragePath($path);
        if ($logical !== []) return $logical;

        if (is_array($path)) $parts = array_values($path);
        else {
            $raw = trim((string) $path);
            $raw = preg_replace('/\[([0-9]+)\]/', '.$1', $raw) ?? $raw;
            $parts = array_values(array_filter(explode('.', trim($raw, '.')), fn ($part) => $part !== ''));
        }
        $valid = [];
        foreach ($parts as $part) {
            $segment = trim((string) $part);
            if ($segment === '' || in_array($segment, ['__proto__', 'prototype', 'constructor'], true)) return [];
            if (ctype_digit($segment)) { $valid[] = (int) $segment; continue; }
            if (preg_match('/^@[A-Za-z0-9_-]{1,128}$/', $segment)) { $valid[] = $segment; continue; }
            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_-]{0,127}$/', $segment)) return [];
            $valid[] = $segment;
        }
        return $valid;
    }

    /** Registered Sparks may legitimately name a collection `rows`; only AI
     * Flex blocks are allowed to translate that name into elements/children. */
    private function normalizeRegisteredPath(string|array|null $path): array
    {
        if (is_array($path)) $parts = array_values($path);
        else {
            $raw = trim((string) $path);
            $raw = preg_replace('/\[([0-9]+)\]/', '.$1', $raw) ?? $raw;
            $parts = array_values(array_filter(explode('.', trim($raw, '.')), fn ($part) => $part !== ''));
        }
        $valid = [];
        foreach ($parts as $part) {
            $segment = trim((string) $part);
            if ($segment === '' || in_array($segment, ['__proto__', 'prototype', 'constructor'], true)) return [];
            if (ctype_digit($segment)) { $valid[] = (int) $segment; continue; }
            if (preg_match('/^@[A-Za-z0-9_-]{1,128}$/', $segment)) { $valid[] = $segment; continue; }
            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_-]{0,127}$/', $segment)) return [];
            $valid[] = $segment;
        }
        return $valid;
    }

    private function isObjectRepeater(mixed $value): bool
    {
        if (! is_array($value) || $value === [] || ! array_is_list($value)) return false;
        foreach ($value as $item) if (! is_array($item) || array_is_list($item)) return false;
        return true;
    }

    private function stableId(array $item): string
    {
        foreach ([self::COSMIC_ID_KEY, 'id', '_id', 'uuid', 'key'] as $key) {
            $value = trim((string) ($item[$key] ?? ''));
            if ($value !== '') return $value;
        }
        return '';
    }

    /** Resolve @stable-id segments once so data, field extras, and Tailwind scopes
     * all receive the same canonical numeric mutation path. */
    private function resolvedNumericPath(array $source, array $path): array
    {
        $cursor = $source;
        $resolved = [];
        foreach ($path as $segment) {
            if (! is_array($cursor)) return $path;
            if (array_is_list($cursor)) {
                $index = $this->resolveSelector($cursor, $segment);
                if ($index === null) return $path;
                $resolved[] = $index;
                $cursor = $cursor[$index] ?? null;
                continue;
            }
            $key = (string) $segment;
            if (! array_key_exists($key, $cursor)) return $path;
            $resolved[] = $key;
            $cursor = $cursor[$key];
        }
        return $resolved;
    }

    private function resolveSelector(array $items, mixed $selector): ?int
    {
        if (is_int($selector) || (is_string($selector) && ctype_digit($selector))) {
            $index = (int) $selector;
            return isset($items[$index]) ? $index : null;
        }
        $raw = trim((string) $selector);
        if (! str_starts_with($raw, '@')) return null;
        $needle = substr($raw, 1);
        foreach ($items as $index => $item) if (is_array($item) && $this->stableId($item) === $needle) return (int) $index;
        return null;
    }

    private function setAtPath(array $source, array $path, mixed $value): array
    {
        if ($path === []) return is_array($value) ? $value : $source;
        $segment = array_shift($path);
        if (array_is_list($source)) {
            $index = $this->resolveSelector($source, $segment);
            if ($index === null) return $source;
            $source[$index] = $path === [] ? $value : $this->setAtPath((array) ($source[$index] ?? []), $path, $value);
            return array_values($source);
        }
        $key = (string) $segment;
        $source[$key] = $path === [] ? $value : $this->setAtPath((array) ($source[$key] ?? []), $path, $value);
        return $source;
    }

    private function freshIdentityTree(array $item, string $role = 'item'): array
    {
        foreach (['id', '_id', 'uuid', 'key', '_key', 'slug', self::COSMIC_ID_KEY] as $key) unset($item[$key]);
        $prefix = match (strtolower((string) ($item['type'] ?? $role))) {
            'row' => 'row_',
            'column' => 'column_',
            default => 'extra_',
        };
        $item[self::COSMIC_ID_KEY] = $prefix.Str::uuid()->toString();
        if (is_array($item['children'] ?? null)) {
            $item['children'] = array_values(array_map(
                fn ($child) => is_array($child) ? $this->freshIdentityTree($child, 'extra') : $child,
                $item['children']
            ));
        }
        return $item;
    }

    private function freshClone(array $item, bool $duplicate): array
    {
        foreach (['id', '_id', 'uuid', 'key', '_key', 'slug', self::COSMIC_ID_KEY] as $key) unset($item[$key]);
        $item[self::COSMIC_ID_KEY] = 'item_'.Str::uuid()->toString();
        foreach ($item as $key => $value) {
            if (! is_array($value) || ! array_is_list($value)) continue;
            $item[$key] = array_values(array_map(function ($child) use ($duplicate) {
                return is_array($child) && ! array_is_list($child)
                    ? $this->freshClone($child, $duplicate)
                    : $child;
            }, $value));
        }
        if (! $duplicate) {
            foreach ($item as $key => &$value) {
                if (! is_string($value)) continue;
                if (preg_match('/(title|heading|name|label)$/i', (string) $key)) $value = 'New item';
                elseif (preg_match('/(description|body|content|text)$/i', (string) $key)) $value = 'Add your content here.';
            }
            unset($value);
        }
        return $item;
    }

    private function samePath(array $a, array $b): bool
    {
        if (count($a) !== count($b)) return false;
        foreach ($a as $i => $part) if ((string) $part !== (string) ($b[$i] ?? '')) return false;
        return true;
    }

    private function indexMap(int $index, array $mutation): ?int
    {
        $action = $mutation['action'];
        if (in_array($action, ['add', 'duplicate'], true)) return $index >= $mutation['index'] ? $index + 1 : $index;
        if ($action === 'remove') {
            if ($index === $mutation['index']) return null;
            return $index > $mutation['index'] ? $index - 1 : $index;
        }
        if ($action === 'move') {
            $from = $mutation['from_index']; $to = $mutation['to_index'];
            if ($index === $from) return $to;
            if ($from < $to && $index > $from && $index <= $to) return $index - 1;
            if ($from > $to && $index >= $to && $index < $from) return $index + 1;
        }
        return $index;
    }

    private function remapFieldExtras(array $state, array $repeaterPath, array $mutation): array
    {
        $normalized = SparkExtrasContract::normalizeState($state);
        $prefix = array_map('strval', $repeaterPath);
        $next = [];
        $copies = [];
        foreach ($normalized as $target => $slots) {
            $segments = explode('.', $target);
            $matches = count($segments) > count($prefix);
            foreach ($prefix as $i => $part) if (($segments[$i] ?? null) !== $part) { $matches = false; break; }
            if (! $matches || ! ctype_digit((string) ($segments[count($prefix)] ?? ''))) {
                $next[$target] = $slots; continue;
            }
            $old = (int) $segments[count($prefix)];
            $mapped = $this->indexMap($old, $mutation);
            if ($mapped === null) continue;
            $mappedSegments = $segments; $mappedSegments[count($prefix)] = (string) $mapped;
            $next[implode('.', $mappedSegments)] = $slots;

            if ($mutation['action'] === 'duplicate' && $old === ($mutation['source_index'] ?? -1)) {
                $copySegments = $segments; $copySegments[count($prefix)] = (string) $mutation['index'];
                $copies[implode('.', $copySegments)] = $this->cloneExtraSlots($slots);
            }
        }
        foreach ($copies as $path => $slots) $next[$path] = $slots;
        return SparkExtrasContract::normalizeState($next);
    }

    private function cloneExtraSlots(array $slots): array
    {
        foreach (SparkExtrasContract::PLACEMENTS as $placement) {
            $slots[$placement] = array_values(array_map(function ($item): array {
                $item = is_array($item) ? $item : [];
                $item['id'] = 'extra_'.bin2hex(random_bytes(16));
                return $item;
            }, is_array($slots[$placement] ?? null) ? $slots[$placement] : []));
        }
        return $slots;
    }

    private function remapTailwind(array $block, array $repeaterPath, array $mutation): array
    {
        $storage = $this->tailwindContract->storageKey();
        if (! is_array($block[$storage] ?? null) || $repeaterPath === []) return $block;
        $schema = $this->tailwindContract->fromBlock((string) ($block['type'] ?? ''), $block);
        $collection = (string) $repeaterPath[count($repeaterPath) - 1];
        $parentPath = array_slice($repeaterPath, 0, -1);
        $scope =& $schema;

        for ($i = 0; $i < count($parentPath); $i += 2) {
            $parentCollection = (string) ($parentPath[$i] ?? '');
            $selector = $parentPath[$i + 1] ?? null;
            if (! isset($scope['collections'][$parentCollection]) || ! is_array($scope['collections'][$parentCollection])) return $block;
            $index = $this->resolveSelector($scope['collections'][$parentCollection], $selector);
            if ($index === null || ! is_array($scope['collections'][$parentCollection][$index] ?? null)) return $block;
            $scope =& $scope['collections'][$parentCollection][$index];
        }

        if (! isset($scope['collections'][$collection]) || ! is_array($scope['collections'][$collection])) return $block;
        $scopes =& $scope['collections'][$collection];
        if ($mutation['action'] === 'add') {
            array_splice($scopes, $mutation['index'], 0, [['styles' => [], 'collections' => []]]);
        } elseif ($mutation['action'] === 'duplicate') {
            $source = $scopes[$mutation['source_index']] ?? ['styles' => [], 'collections' => []];
            array_splice($scopes, $mutation['index'], 0, [is_array($source) ? $source : ['styles' => [], 'collections' => []]]);
        } elseif ($mutation['action'] === 'remove' && isset($scopes[$mutation['index']])) {
            array_splice($scopes, $mutation['index'], 1);
        } elseif ($mutation['action'] === 'move') {
            $from = $mutation['from_index']; $to = $mutation['to_index'];
            if (isset($scopes[$from])) {
                $moving = $scopes[$from]; array_splice($scopes, $from, 1); array_splice($scopes, $to, 0, [$moving]);
            }
        }
        $scopes = array_values($scopes);
        $block[$storage] = $schema;
        return $block;
    }
}
