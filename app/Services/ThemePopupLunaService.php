<?php

namespace App\Services;

use App\AI\Clients\OpenAIClient;
use Illuminate\Support\Str;
use Throwable;

final class ThemePopupLunaService
{
    public function __construct(
        private readonly OpenAIClient $client,
        private readonly ThemeColorResolver $colors,
    ) {}

    public function classify(string $prompt, array $themeIds = []): array
    {
        $prompt = trim($prompt);
        $lower = Str::lower($prompt);
        $themeIds = array_values(array_unique(array_filter(array_map('strval', $themeIds))));

        if ($prompt === '') {
            return ['type' => 'chat', 'action' => null, 'reply' => 'Tell Luna what you want to change about the theme.'];
        }

        if (preg_match('/#[0-9a-fA-F]{6}\b/', $prompt, $match)) {
            return [
                'type' => 'action',
                'action' => 'custom_theme',
                'seed_color' => strtoupper($match[0]),
                'direction' => $prompt,
            ];
        }

        foreach ($themeIds as $themeId) {
            $needle = Str::lower(str_replace(['-', '_'], ' ', $themeId));
            $normalizedPrompt = str_replace(['-', '_'], ' ', $lower);
            if ($needle !== '' && Str::contains($normalizedPrompt, $needle) && preg_match('/\b(use|select|switch|choose|change|apply|theme)\b/i', $prompt)) {
                return ['type' => 'action', 'action' => 'select_theme', 'theme_key' => $themeId];
            }
        }

        $system = <<<'PROMPT'
You are Luna's Theme Popup router. Return JSON only.
Classify the user's message into exactly one of these shapes:
1) {"type":"chat","action":null,"reply":"short helpful theme-only reply"}
2) {"type":"action","action":"select_theme","theme_key":"one supplied theme id"}
3) {"type":"action","action":"custom_theme","seed_color":"#RRGGBB or null","direction":"concise design direction"}

Rules:
- select_theme only when the user clearly asks to use/switch/select an EXISTING supplied theme.
- custom_theme when they ask to generate/create/design a new palette/theme/color family, including mood-based requests or a supplied HEX.
- chat for questions, explanations, comparisons, or unclear requests that do not ask for a change.
- Never invent a theme_key outside AVAILABLE_THEME_IDS.
- Keep replies concise.
PROMPT;

        $user = "AVAILABLE_THEME_IDS:\n".json_encode($themeIds, JSON_UNESCAPED_SLASHES)
            ."\n\nUSER:\n".$prompt;

        try {
            $decoded = $this->decodeJson((string) $this->client->chat($system, $user));
            $type = ($decoded['type'] ?? '') === 'action' ? 'action' : 'chat';
            $action = in_array(($decoded['action'] ?? null), ['select_theme', 'custom_theme'], true) ? $decoded['action'] : null;

            if ($type === 'action' && $action === 'select_theme') {
                $themeKey = (string) ($decoded['theme_key'] ?? '');
                if (in_array($themeKey, $themeIds, true)) {
                    return ['type' => 'action', 'action' => 'select_theme', 'theme_key' => $themeKey];
                }
                return ['type' => 'chat', 'action' => null, 'reply' => 'I could not match that request to a theme in this library.'];
            }

            if ($type === 'action' && $action === 'custom_theme') {
                $seed = $this->normalizeHex($decoded['seed_color'] ?? null);
                return [
                    'type' => 'action',
                    'action' => 'custom_theme',
                    'seed_color' => $seed,
                    'direction' => trim((string) ($decoded['direction'] ?? $prompt)) ?: $prompt,
                ];
            }

            return [
                'type' => 'chat',
                'action' => null,
                'reply' => trim((string) ($decoded['reply'] ?? '')) ?: 'I can help compare themes or create a new color direction for this website.',
            ];
        } catch (Throwable $exception) {
            report($exception);
            return ['type' => 'chat', 'action' => null, 'reply' => 'I can help select an existing theme or create a new custom color family.'];
        }
    }

    public function generate(string $direction, ?string $seedColor, array $schema, string $baseTheme = 'midnight'): array
    {
        $direction = trim($direction);
        $seedColor = $this->normalizeHex($seedColor);
        $schema = $schema ?: $this->defaultSchema();

        $system = <<<'PROMPT'
You are Luna's custom theme designer. Return JSON only with this exact top-level shape:
{"name":"short theme name","mode":"light|dark","brand_color_family":{...}}

Design a restrained, premium, accessible semantic color family. Do not return commentary.
Every color value must be a six-digit #RRGGBB.
If SEED_COLOR is supplied, brand_color_family.primary and brand_color_family.buttonPrimary MUST equal it exactly.
Use the supplied COLOR_FAMILY_SCHEMA keys exactly. Do not omit keys.
Treat every foreground/background pair as semantic, not decorative: primaryText must be readable on primary; secondaryText on secondary; accentText on accent; backgroundText on background; surfaceText on surface; buttonText on buttonPrimary; buttonSecondaryText on buttonSecondary.
Do not reuse a dark heading/text color on a dark primary or button background. Do not reuse white text on a light surface. The server will validate and repair contrast, but your proposed pairs should already be accessible.
PROMPT;

        $user = "DESIGN_DIRECTION:\n{$direction}\n\nSEED_COLOR:\n".($seedColor ?: 'none')
            ."\n\nCOLOR_FAMILY_SCHEMA:\n".json_encode($schema, JSON_UNESCAPED_SLASHES);

        $raw = [];
        try {
            $raw = $this->decodeJson((string) $this->client->chat($system, $user));
        } catch (Throwable $exception) {
            report($exception);
        }

        $candidate = is_array($raw['brand_color_family'] ?? null) ? $raw['brand_color_family'] : [];
        $primary = $seedColor ?: $this->normalizeHex($candidate['primary'] ?? null) ?: '#243447';
        $family = $this->colors->fromCustomHex($primary, $candidate, $baseTheme ?: 'midnight');
        $mode = in_array(($raw['mode'] ?? null), ['light', 'dark'], true)
            ? $raw['mode']
            : $this->modeFor($family['primary'] ?? $primary);
        $name = trim((string) ($raw['name'] ?? ''));

        return [
            'name' => $name !== '' ? Str::limit($name, 42, '') : 'Luna Theme',
            'mode' => $mode,
            'brand_color_family' => $family,
        ];
    }

    public function defaultSchema(): array
    {
        return array_fill_keys([
            'sourceColor','primary','primaryText','primaryHover','primarySoft','secondary','secondaryText','accent','accentText','background','backgroundText','surface','surfaceMuted','surfaceText','heading','text','muted','border','buttonPrimary','buttonText','buttonSecondary','buttonSecondaryText','success','warning','error','onPrimary','onDark'
        ], '#RRGGBB') + [
            'gradient' => ['from'=>'#RRGGBB','via'=>'#RRGGBB','to'=>'#RRGGBB','glow'=>'#RRGGBB','angle'=>125],
        ];
    }

    private function decodeJson(string $raw): array
    {
        $raw = trim($raw);
        $raw = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $raw) ?? $raw;
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) throw new \RuntimeException('Invalid Theme Popup Luna JSON.');
        return $decoded;
    }

    private function normalizeHex(mixed $value): ?string
    {
        $value = strtoupper(trim((string) $value));
        return preg_match('/^#[0-9A-F]{6}$/', $value) ? $value : null;
    }

    private function modeFor(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) return 'dark';
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return ((0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255) > 0.62 ? 'light' : 'dark';
    }
}
