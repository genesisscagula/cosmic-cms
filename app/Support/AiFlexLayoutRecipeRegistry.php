<?php

namespace App\Support;

/**
 * Deterministic geometry recipes for AI Flex.
 *
 * Recipes are not Sparks/templates. They describe only trusted layout geometry
 * that Luna may fill with arbitrary renderer-ready primitives.
 */
final class AiFlexLayoutRecipeRegistry
{
    public const VERSION = 1;
    public const CONTRACT = 'ai_flex_layout_recipes_v1';

    /** @return array<string,array<string,mixed>> */
    public static function definitions(): array
    {
        return [
            'single_centered' => self::recipe('Single centered content', 'row', [100], 'Centered general-purpose content.', ['centered','single column','one column','1 column']),
            'content_narrow' => self::recipe('Narrow centered content', 'row', [100], 'Centered copy constrained to a readable measure.', ['narrow','narrow content','reading width']),
            'two_equal' => self::recipe('Two equal columns', 'row', [50,50], 'Balanced 50/50 two-column split.', ['two equal','equal columns','50/50','50 50','two columns','2 columns']),
            'two_40_60' => self::recipe('40 / 60 split', 'row', [40,60], 'Asymmetric two-column split with smaller left column.', ['40/60','40 60','40-60']),
            'two_60_40' => self::recipe('60 / 40 split', 'row', [60,40], 'Asymmetric two-column split with larger left column.', ['60/40','60 40','60-40']),
            'three_equal' => self::recipe('Three equal columns', 'row', [33.333,33.333,33.334], 'Three balanced columns.', ['three columns','3 columns','three equal','3 equal']),
            'four_equal' => self::recipe('Four equal columns', 'row', [25,25,25,25], 'Four balanced columns.', ['four columns','4 columns','four equal','4 equal']),
            'sidebar_left' => self::recipe('Left sidebar', 'row', [30,70], 'Narrow supporting column left, primary content right.', ['left sidebar','sidebar left']),
            'sidebar_right' => self::recipe('Right sidebar', 'row', [70,30], 'Primary content left, narrow supporting column right.', ['right sidebar','sidebar right']),
            'split_media_left' => self::recipe('Media left split', 'row', [52,48], 'Media-forward left column with copy/action column right.', ['image left','image on the left','media left','media on the left','photo left','photo on the left','video left','video on the left']),
            'split_media_right' => self::recipe('Media right split', 'row', [48,52], 'Copy/action column left with media-forward right column.', ['image right','image on the right','media right','media on the right','photo right','photo on the right','video right','video on the right']),
            'bento_2x2' => self::recipe('2 × 2 bento', 'grid', [], 'Balanced two-column bento/grid that collapses safely.', ['bento 2x2','2x2 bento','2 x 2 bento']),
            'bento_featured_left' => self::recipe('Featured-left bento', 'row', [42,58], 'Large featured panel left plus compact two-column grid right.', ['bento featured left','featured left bento','large card left']),
            'bento_featured_right' => self::recipe('Featured-right bento', 'row', [58,42], 'Compact two-column grid left plus large featured panel right.', ['bento featured right','featured right bento','large card right']),
        ];
    }

    /** @return array<string,mixed>|null */
    public static function get(string $key): ?array
    {
        $definition = self::definitions()[strtolower(trim($key))] ?? null;
        return is_array($definition) ? ['key'=>strtolower(trim($key)), ...$definition] : null;
    }

    /** @return array<string,mixed>|null */
    public static function detect(string $request): ?array
    {
        $q = strtolower($request);

        // Explicit ratios/semantic placements win over generic column wording.
        $priority = [
            'two_40_60','two_60_40','sidebar_left','sidebar_right',
            'split_media_left','split_media_right','bento_featured_left','bento_featured_right',
            'bento_2x2','four_equal','three_equal','two_equal','content_narrow','single_centered',
        ];
        foreach ($priority as $key) {
            $definition = self::definitions()[$key];
            foreach ((array) ($definition['aliases'] ?? []) as $alias) {
                if ($alias !== '' && str_contains($q, strtolower((string) $alias))) return ['key'=>$key, ...$definition];
            }
        }

        // Natural-language fallbacks that should remain deterministic.
        if (preg_match('/\b(3|three)[ -]?col(?:umn)?s?\b/', $q)) return self::get('three_equal');
        if (preg_match('/\b(4|four)[ -]?col(?:umn)?s?\b/', $q)) return self::get('four_equal');
        if (preg_match('/\b(2|two)[ -]?col(?:umn)?s?\b/', $q)) return self::get('two_equal');
        if (str_contains($q, 'bento')) return self::get('bento_2x2');
        if (str_contains($q, 'centered') || str_contains($q, 'centred')) return self::get('single_centered');

        return null;
    }

    /** Compact inventory suitable for a model prompt. */
    public static function promptInventory(): string
    {
        $parts = [];
        foreach (self::definitions() as $key => $definition) {
            $geometry = ($definition['kind'] ?? '') === 'row'
                ? ' widths='.implode('/', array_map(static fn ($v) => rtrim(rtrim(number_format((float)$v, 3, '.', ''), '0'), '.'), (array) ($definition['desktop_widths'] ?? [])))
                : ' columns='.(int) ($definition['desktop_columns'] ?? 2);
            $parts[] = $key.'('.$geometry.')';
        }
        return implode(', ', $parts);
    }

    /** @return array<string,mixed> */
    private static function recipe(string $label, string $kind, array $desktopWidths, string $description, array $aliases): array
    {
        $grid = $kind === 'grid';
        return [
            'label' => $label,
            'kind' => $kind,
            'description' => $description,
            'desktop_widths' => array_values($desktopWidths),
            'desktop_columns' => $grid ? 2 : null,
            'tablet_columns' => $grid ? 2 : null,
            'mobile_columns' => 1,
            'tablet_stack' => $kind === 'row',
            'mobile_stack' => true,
            'aliases' => array_values($aliases),
        ];
    }
}
