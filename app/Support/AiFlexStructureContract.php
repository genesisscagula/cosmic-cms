<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Canonical AI Flex Rows -> Columns -> Extras contract.
 *
 * Storage stays in the existing `elements/children` Universal Elements tree so
 * Builder + exporter parity is preserved. Logical collection names are mapped
 * to physical storage paths for Luna structural mutations.
 */
final class AiFlexStructureContract
{
    public const VERSION = 1;
    public const CONTRACT = 'rows_columns_extras_v1';
    public const STORAGE_KEY = 'elements';
    public const ID_KEY = '_cosmic_id';

    public static function canonicalizeBlock(array $block): array
    {
        if (($block['type'] ?? '') !== 'luna_custom_section') return $block;
        $elements = is_array($block[self::STORAGE_KEY] ?? null) ? $block[self::STORAGE_KEY] : [];
        if ($elements === []) return $block;
        $block[self::STORAGE_KEY] = self::canonicalizeElements($elements);
        $ai = is_array($block['ai_flex'] ?? null) ? $block['ai_flex'] : [];
        $ai['structure_contract'] = self::CONTRACT;
        $ai['structure_version'] = self::VERSION;
        $ai['structure_storage'] = self::STORAGE_KEY;
        $block['ai_flex'] = $ai;
        return $block;
    }

    /** Conservative save-time normalizer: legacy arbitrary trees keep their shape. */
    public static function normalizePersistedBlock(array $block): array
    {
        if (($block['type'] ?? '') !== 'luna_custom_section') return $block;
        $elements = is_array($block[self::STORAGE_KEY] ?? null) ? $block[self::STORAGE_KEY] : [];
        if ($elements === []) return $block;
        $ai = is_array($block['ai_flex'] ?? null) ? $block['ai_flex'] : [];
        if (($ai['structure_contract'] ?? '') === self::CONTRACT || self::isCanonical($elements)) {
            return self::canonicalizeBlock($block);
        }
        $used = [];
        $block[self::STORAGE_KEY] = self::ensureIds($elements, 'extra', $used);
        return $block;
    }

    public static function canonicalizeElements(array $elements): array
    {
        $source = array_values(array_filter($elements, 'is_array'));
        if ($source === []) return [];
        $rows = [];
        $pending = [];
        $flush = static function () use (&$rows, &$pending): void {
            if ($pending === []) return;
            $rows[] = ['type' => 'row', 'children' => [self::makeColumn($pending, 100)]];
            $pending = [];
        };

        foreach ($source as $node) {
            $type = strtolower(trim((string) ($node['type'] ?? '')));
            if ($type === 'row') {
                $flush();
                $rows[] = self::normalizeRow($node);
            } elseif ($type === 'column') {
                $flush();
                $node['type'] = 'column';
                $node['children'] = is_array($node['children'] ?? null) ? array_values($node['children']) : [];
                $rows[] = ['type' => 'row', 'children' => [$node]];
            } else {
                $pending[] = $node;
            }
        }
        $flush();

        $used = [];
        $out = [];
        foreach ($rows as $row) {
            $row[self::ID_KEY] = self::uniqueId($row[self::ID_KEY] ?? null, 'row', $used);
            $columns = is_array($row['children'] ?? null) ? array_values($row['children']) : [];
            foreach ($columns as &$column) {
                if (! is_array($column)) $column = self::makeColumn([], 100);
                $column['type'] = 'column';
                $column[self::ID_KEY] = self::uniqueId($column[self::ID_KEY] ?? null, 'column', $used);
                $extras = is_array($column['children'] ?? null) ? array_values($column['children']) : [];
                $column['children'] = self::ensureIds($extras, 'extra', $used);
            }
            unset($column);
            if ($columns === []) $columns = [self::makeColumn([], 100)];
            foreach ($columns as &$column) {
                if (! isset($column[self::ID_KEY])) $column[self::ID_KEY] = self::uniqueId(null, 'column', $used);
            }
            unset($column);
            $row['children'] = array_values($columns);
            $out[] = $row;
        }
        return array_values($out);
    }

    public static function isCanonical(array $elements): bool
    {
        if ($elements === [] || ! array_is_list($elements)) return false;
        foreach ($elements as $row) {
            if (! is_array($row) || ($row['type'] ?? '') !== 'row') return false;
            $columns = $row['children'] ?? null;
            if (! is_array($columns) || $columns === [] || ! array_is_list($columns)) return false;
            foreach ($columns as $column) {
                if (! is_array($column) || ($column['type'] ?? '') !== 'column' || ! is_array($column['children'] ?? null)) return false;
            }
        }
        return true;
    }

    /** Translate e.g. rows.0.columns.1.extras -> elements.0.children.1.children. */
    public static function logicalToStoragePath(string|array|null $path): array
    {
        $parts = self::segments($path);
        if ($parts === [] || $parts[0] !== 'rows') return [];
        $out = [self::STORAGE_KEY];
        if (count($parts) === 1) return $out;
        $out[] = $parts[1];
        if (count($parts) === 2) return $out;
        if (($parts[2] ?? null) !== 'columns') return [];
        $out[] = 'children';
        if (count($parts) === 3) return $out;
        $out[] = $parts[3];
        if (count($parts) === 4) return $out;
        if (($parts[4] ?? null) !== 'extras') return [];
        $out[] = 'children';
        foreach (array_slice($parts, 5) as $part) $out[] = $part;
        return $out;
    }

    private static function normalizeRow(array $row): array
    {
        $row['type'] = 'row';
        $children = is_array($row['children'] ?? null) ? array_values(array_filter($row['children'], 'is_array')) : [];
        if ($children === []) {
            $row['children'] = [self::makeColumn([], 100)];
            return $row;
        }
        $width = max(10, round(100 / max(1, count($children)), 2));
        $columns = [];
        foreach ($children as $child) {
            if (($child['type'] ?? '') === 'column') {
                $child['children'] = is_array($child['children'] ?? null) ? array_values($child['children']) : [];
                $columns[] = $child;
                continue;
            }
            $childWidth = is_numeric($child['style']['width'] ?? null) ? (float) $child['style']['width'] : $width;
            $columns[] = self::makeColumn([$child], $childWidth);
        }
        $row['children'] = $columns;
        return $row;
    }

    private static function makeColumn(array $children, float|int $width): array
    {
        return [
            'type' => 'column',
            self::ID_KEY => self::newId('column'),
            'style' => ['width' => max(5, min(100, (float) $width))],
            'children' => array_values($children),
        ];
    }

    private static function ensureIds(array $nodes, string $role, array &$used): array
    {
        $out = [];
        foreach (array_slice($nodes, 0, 80) as $node) {
            if (! is_array($node)) continue;
            $node[self::ID_KEY] = self::uniqueId($node[self::ID_KEY] ?? null, $role, $used);
            if (is_array($node['children'] ?? null)) $node['children'] = self::ensureIds(array_values($node['children']), 'extra', $used);
            $out[] = $node;
        }
        return $out;
    }

    private static function uniqueId(mixed $candidate, string $role, array &$used): string
    {
        $id = trim((string) $candidate);
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,127}$/', $id) || isset($used[$id])) $id = self::newId($role);
        while (isset($used[$id])) $id = self::newId($role);
        $used[$id] = true;
        return $id;
    }

    private static function newId(string $role): string
    {
        $prefix = $role === 'row' ? 'row' : ($role === 'column' ? 'column' : 'extra');
        return $prefix.'_'.Str::uuid()->toString();
    }

    private static function segments(string|array|null $path): array
    {
        if (is_array($path)) return array_values(array_map('strval', $path));
        $raw = trim((string) $path);
        $raw = preg_replace('/\[([0-9]+)\]/', '.$1', $raw) ?? $raw;
        return array_values(array_filter(explode('.', trim($raw, '.')), fn ($v) => $v !== ''));
    }
}
