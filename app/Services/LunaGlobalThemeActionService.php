<?php

namespace App\Services;

use App\Models\Website;
use Illuminate\Support\Str;

final class LunaGlobalThemeActionService
{
    public function __construct(private readonly ThemeColorResolver $colors)
    {
    }

    /**
     * Execute bounded Global/Theme contract mutations without asking Luna to
     * invent fields. Returns null when the request needs the existing planner.
     */
    public function apply(Website $website, string $prompt, array $intent): ?array
    {
        if (($intent['intent'] ?? '') !== 'action') return null;

        $scope = (string) data_get($intent, 'routing.menu_scope', '');
        $action = (string) data_get($intent, 'routing.scope_action', '');
        if (! in_array($scope, ['global', 'theme'], true)) return null;

        return $scope === 'theme'
            ? $this->applyTheme($website, $prompt, $action)
            : $this->applyGlobal($website, $prompt, $action);
    }

    private function applyTheme(Website $website, string $prompt, string $action): ?array
    {
        $settings = is_array($website->theme_settings) ? $website->theme_settings : [];
        $current = trim((string) ($settings['primary'] ?? 'midnight')) ?: 'midnight';

        if ($action === 'reset_theme') {
            return $this->success('theme', 'reset_theme', [
                'theme_key' => 'midnight',
                'operations' => [[
                    'action' => 'update', 'target' => 'theme.primary', 'value' => 'midnight', 'verified' => true,
                ]],
            ]);
        }

        if ($action === 'theme_from_logo') {
            // Existing logo-analysis flow owns this operation; do not create a
            // second implementation here.
            return null;
        }

        if (preg_match('/#[0-9a-fA-F]{6}\b/', $prompt, $m)) {
            $hex = strtoupper($m[0]);
            $palette = $this->colors->fromCustomHex($hex, [], $current === 'my-brand' ? 'midnight' : $current);
            $mode = $this->modeFor($palette['primary'] ?? '#243447');
            return $this->success('theme', $action ?: 'brand_theme', [
                'brand_color_family' => $palette,
                'brand_theme_mode' => $mode,
                'brand_theme_name' => $this->customThemeName($hex, $mode),
                'operations' => [[
                    'action' => 'update', 'target' => 'theme.brand_palette.primary', 'value' => $hex, 'verified' => true,
                ]],
            ]);
        }

        $family = $this->namedThemeFamily($prompt);
        if ($family !== null) {
            return $this->success('theme', $action ?: 'change_theme', [
                'theme_key' => $family,
                'operations' => [[
                    'action' => 'update', 'target' => 'theme.primary', 'value' => $family, 'verified' => true,
                ]],
            ]);
        }

        // Brand-theme wording without a concrete family/HEX is intentionally
        // left to the existing AI brand flow.
        return null;
    }

    private function applyGlobal(Website $website, string $prompt, string $action): ?array
    {
        $lower = Str::lower($prompt);
        $payload = ['operations' => []];

        if ($action === 'reset_global') {
            // Clearing nested settings requires replacement semantics, while the
            // current Builder response contract merges token objects. Leave this
            // to the existing planner/save path until replacement is explicit.
            return null;
        }

        if (in_array($action, ['typography', 'edit_global'], true)) {
            $typography = [];
            if (preg_match('/\b(?:heading|headings|display)\s+font\s+(?:to|as|:)\s*["“]?([A-Za-z0-9][A-Za-z0-9 _-]{1,60})["”]?/i', $prompt, $m)) {
                $typography['font_display'] = trim($m[1]);
            }
            if (preg_match('/\b(?:body|paragraph|text)\s+font\s+(?:to|as|:)\s*["“]?([A-Za-z0-9][A-Za-z0-9 _-]{1,60})["”]?/i', $prompt, $m)) {
                $typography['font_body'] = trim($m[1]);
            }
            if (preg_match('/\ball\s+headings?\b.*?\b(?:color|colour)\b\s*(?:to|as|:)\s*(#[0-9a-fA-F]{6})\b/i', $prompt, $m)) {
                $typography['heading_color'] = strtoupper($m[1]);
            }
            if (preg_match('/\b(?:body|paragraph|text)\b.*?\b(?:color|colour)\b\s*(?:to|as|:)\s*(#[0-9a-fA-F]{6})\b/i', $prompt, $m)) {
                $typography['body_color'] = strtoupper($m[1]);
            }
            if ($typography !== []) {
                $payload['typography_settings'] = $typography;
                foreach ($typography as $key => $value) {
                    $payload['operations'][] = ['action' => 'update', 'target' => 'global.typography.'.$key, 'value' => $value, 'verified' => true];
                }
            }
        }

        if (in_array($action, ['buttons', 'edit_global'], true)) {
            $components = [];
            if (preg_match('/\b(?:button|buttons)\b.*?\b(?:radius|rounded|rounding)\b.*?(\d{1,4})\s*(px)?\b/i', $prompt, $m)) {
                $components['button_radius'] = ((int) $m[1]).'px';
            } elseif (preg_match('/\b(?:square|sharp)\s+(?:button|buttons)\b|\b(?:button|buttons)\b.*?\b(?:square|sharp)\b/i', $lower)) {
                $components['button_radius'] = '0px';
            } elseif (preg_match('/\b(?:pill|fully rounded|round)\s+(?:button|buttons)\b|\b(?:button|buttons)\b.*?\b(?:pill|fully rounded)\b/i', $lower)) {
                $components['button_radius'] = '999px';
            }
            if ($components !== []) {
                $payload['components'] = $components;
                foreach ($components as $key => $value) {
                    $payload['operations'][] = ['action' => 'update', 'target' => 'global.components.'.$key, 'value' => $value, 'verified' => true];
                }
            }
        }

        if (in_array($action, ['spacing', 'container', 'edit_global'], true)) {
            $layout = [];
            if (preg_match('/\b(?:section|global)\s+(?:vertical\s+)?(?:spacing|padding)\b.*?(\d{1,3})\s*px\b/i', $prompt, $m)) {
                $layout['py'] = max(0, min(200, (int) $m[1])).'px';
            }
            if (preg_match('/\b(?:container|content)\s+(?:max[- ]?)?width\b.*?(\d{3,4})\s*px\b/i', $prompt, $m)) {
                $layout['container'] = max(640, min(1920, (int) $m[1])).'px';
            }
            if ($layout !== []) {
                $payload['section_layout'] = $layout;
                foreach ($layout as $key => $value) {
                    $payload['operations'][] = ['action' => 'update', 'target' => 'global.section_layout.'.$key, 'value' => $value, 'verified' => true];
                }
            }
        }

        if (in_array($action, ['backgrounds', 'edit_global'], true)) {
            $background = [];
            if (preg_match('/\b(?:page|site|website|global)\s+background\b.*?(#[0-9a-fA-F]{6})\b/i', $prompt, $m)) {
                $background['white'] = strtoupper($m[1]);
            }
            if ($background !== []) {
                $payload['background_style'] = $background;
                foreach ($background as $key => $value) {
                    $payload['operations'][] = ['action' => 'update', 'target' => 'global.background_style.'.$key, 'value' => $value, 'verified' => true];
                }
            }
        }

        return $payload['operations'] === [] ? null : $this->success('global', $action ?: 'edit_global', $payload);
    }

    private function namedThemeFamily(string $prompt): ?string
    {
        $path = resource_path('theme/theme-families.json');
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
        $ids = array_values(array_filter(array_map('strval', (array) ($decoded['compilerThemeIds'] ?? []))));
        $lower = Str::lower($prompt);
        foreach ($ids as $id) {
            if ($id !== '' && preg_match('/\b'.preg_quote(Str::lower($id), '/').'\b/i', $lower)) return $id;
        }
        return null;
    }

    private function customThemeName(string $hex, string $mode): string
    {
        return ($mode === 'light' ? 'Luna Light' : 'Luna Deep').' '.strtoupper($hex);
    }

    private function modeFor(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) return 'dark';
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $luminance = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
        return $luminance > 0.62 ? 'light' : 'dark';
    }

    private function success(string $domain, string $action, array $payload): array
    {
        return array_replace([
            'handled' => true,
            'success' => true,
            'domain' => $domain,
            'scope_action' => $action,
            'operations' => [],
        ], $payload);
    }
}
