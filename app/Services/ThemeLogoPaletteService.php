<?php

namespace App\Services;

class ThemeLogoPaletteService
{
    /**
     * Curated logo palettes. Each theme gets three compatible variations.
     * The first color remains anchored to the theme family; secondary/tertiary
     * add enough contrast so AI logos do not collapse into one flat color.
     */
    private const PALETTES = [
        'emerald' => [
            ['#0B5D4B', '#34D399', '#FBBF24'],
            ['#0B5D4B', '#2DD4BF', '#A7F3D0'],
            ['#0B5D4B', '#60A5FA', '#F8FAFC'],
        ],
        'coffee' => [
            ['#4A2A14', '#F59E0B', '#FDE68A'],
            ['#4A2A14', '#C08457', '#FFF7ED'],
            ['#4A2A14', '#2DD4BF', '#FB923C'],
        ],
        'rose' => [
            ['#A13D63', '#FB7185', '#FBCFE8'],
            ['#A13D63', '#C084FC', '#F8FAFC'],
            ['#A13D63', '#F59E0B', '#FFE4E6'],
        ],
        'dark' => [
            ['#1F2937', '#94A3B8', '#60A5FA'],
            ['#1F2937', '#34D399', '#F8FAFC'],
            ['#1F2937', '#F59E0B', '#CBD5E1'],
        ],
        'ocean' => [
            ['#24598F', '#38BDF8', '#A5F3FC'],
            ['#24598F', '#2DD4BF', '#F8FAFC'],
            ['#24598F', '#FBBF24', '#BAE6FD'],
        ],
        'indigo' => [
            ['#4F46A5', '#A78BFA', '#E0E7FF'],
            ['#4F46A5', '#38BDF8', '#F8FAFC'],
            ['#4F46A5', '#F59E0B', '#C4B5FD'],
        ],
        'amber' => [
            ['#A16207', '#FBBF24', '#FEF3C7'],
            ['#A16207', '#FB923C', '#FFF7ED'],
            ['#A16207', '#0F766E', '#FDE68A'],
        ],
        'charcoal' => [
            ['#3A3A3A', '#D4D4D8', '#60A5FA'],
            ['#3A3A3A', '#34D399', '#F8FAFC'],
            ['#3A3A3A', '#F59E0B', '#E4E4E7'],
        ],
        'violet' => [
            ['#5B3FA3', '#C084FC', '#E9D5FF'],
            ['#5B3FA3', '#38BDF8', '#F8FAFC'],
            ['#5B3FA3', '#FB7185', '#DDD6FE'],
        ],
        'teal' => [
            ['#186B66', '#2DD4BF', '#99F6E4'],
            ['#186B66', '#38BDF8', '#F8FAFC'],
            ['#186B66', '#FBBF24', '#CCFBF1'],
        ],
        'ruby' => [
            ['#A12649', '#FB7185', '#FECDD3'],
            ['#A12649', '#F59E0B', '#FFF1F2'],
            ['#A12649', '#C084FC', '#F8FAFC'],
        ],
        'forest' => [
            ['#2E5E3E', '#4ADE80', '#BBF7D0'],
            ['#2E5E3E', '#FBBF24', '#F0FDF4'],
            ['#2E5E3E', '#38BDF8', '#86EFAC'],
        ],
        'midnight' => [
            ['#243447', '#60A5FA', '#A78BFA'],
            ['#243447', '#38BDF8', '#F8FAFC'],
            ['#243447', '#F59E0B', '#CBD5E1'],
        ],
        'obsidian' => [
            ['#171717', '#E5E7EB', '#60A5FA'],
            ['#171717', '#34D399', '#F8FAFC'],
            ['#171717', '#F59E0B', '#D4D4D8'],
        ],
        'navy' => [
            ['#214B7A', '#60A5FA', '#BFDBFE'],
            ['#214B7A', '#2DD4BF', '#F8FAFC'],
            ['#214B7A', '#FBBF24', '#DBEAFE'],
        ],
        'void' => [
            ['#111827', '#818CF8', '#C4B5FD'],
            ['#111827', '#38BDF8', '#F8FAFC'],
            ['#111827', '#34D399', '#A5B4FC'],
        ],
        'espresso' => [
            ['#4B2E1E', '#FB923C', '#FED7AA'],
            ['#4B2E1E', '#F59E0B', '#FFF7ED'],
            ['#4B2E1E', '#2DD4BF', '#FDBA74'],
        ],
        'terracotta' => [
            ['#A04A2C', '#FB923C', '#FED7AA'],
            ['#A04A2C', '#FBBF24', '#FFF7ED'],
            ['#A04A2C', '#2DD4BF', '#FCA5A5'],
        ],
        'asphalt' => [
            ['#2A2A2A', '#A1A1AA', '#60A5FA'],
            ['#2A2A2A', '#34D399', '#F4F4F5'],
            ['#2A2A2A', '#F59E0B', '#D4D4D8'],
        ],
        'sapphire' => [
            ['#0F4C81', '#38BDF8', '#BAE6FD'],
            ['#0F4C81', '#2DD4BF', '#F8FAFC'],
            ['#0F4C81', '#FBBF24', '#7DD3FC'],
        ],
        'plum' => [
            ['#5B214A', '#E879F9', '#F5D0FE'],
            ['#5B214A', '#FB7185', '#F8FAFC'],
            ['#5B214A', '#F59E0B', '#F0ABFC'],
        ],
        'olive' => [
            ['#4D5D2D', '#A3E635', '#D9F99D'],
            ['#4D5D2D', '#FBBF24', '#F7FEE7'],
            ['#4D5D2D', '#2DD4BF', '#BEF264'],
        ],
        'stone' => [
            ['#57534E', '#78716C', '#D6D3D1'],
            ['#57534E', '#4F46E5', '#F5F5F4'],
            ['#57534E', '#0F766E', '#E7E5E4'],
        ],
        'white' => [
            ['#4F46E5', '#818CF8', '#0F172A'],
            ['#0F766E', '#2DD4BF', '#0F172A'],
            ['#24598F', '#38BDF8', '#1E293B'],
        ],
        'slate' => [
            ['#475569', '#93C5FD', '#CBD5E1'],
            ['#475569', '#34D399', '#F8FAFC'],
            ['#475569', '#F59E0B', '#E2E8F0'],
        ],
    ];

    private const ALIASES = [
        'light' => 'white',
        'soft' => 'stone',
        'sky' => 'ocean',
        'cream' => 'coffee',
        'slate-light' => 'slate',
        'slate-950' => 'midnight',
    ];

    public function randomFor(string $themeKey, ?array $customPalette = null): array
    {
        if ($themeKey === 'my-brand' && is_array($customPalette)) {
            $colors = [
                $customPalette['primary'] ?? null,
                $customPalette['secondary'] ?? null,
                $customPalette['tertiary'] ?? ($customPalette['accent'] ?? null),
            ];
            $colors = array_values(array_filter(array_map([$this, 'validHexOrNull'], $colors)));
            if (count($colors) >= 3) {
                return [
                    'primary' => $colors[0],
                    'secondary' => $colors[1],
                    'tertiary' => $colors[2],
                    'variation' => 'brand',
                ];
            }
        }

        $key = self::ALIASES[$themeKey] ?? $themeKey;
        $variants = self::PALETTES[$key] ?? self::PALETTES['midnight'];
        $index = random_int(0, count($variants) - 1);
        [$primary, $secondary, $tertiary] = $variants[$index];

        return compact('primary', 'secondary', 'tertiary') + ['variation' => $index];
    }

    public function forPrimary(string $themeKey, string $primaryHex): array
    {
        $palette = $this->randomFor($themeKey);
        $palette['primary'] = strtoupper($primaryHex);
        return $palette;
    }

    private function validHexOrNull(mixed $value): ?string
    {
        $value = strtoupper(trim((string) $value));
        return preg_match('/^#[0-9A-F]{6}$/', $value) ? $value : null;
    }
}
