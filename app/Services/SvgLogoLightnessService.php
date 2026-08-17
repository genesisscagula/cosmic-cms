<?php

namespace App\Services;

class SvgLogoLightnessService
{
    /**
     * Detect whether an SVG's explicitly declared visible paint colors are
     * predominantly white/very-light. This is intentionally local and free:
     * it does not call Luna/OpenAI and therefore never consumes credits.
     */
    public function analyze(?string $svg): array
    {
        $svg = (string) $svg;
        if ($svg === '') {
            return ['is_majority_white' => false, 'light_ratio' => 0.0, 'sample_count' => 0];
        }

        $colors = [];

        // Presentation attributes: fill="...", stroke="...", color="...".
        if (preg_match_all('/\b(?:fill|stroke|color)\s*=\s*[\'\"]\s*([^\'\"]+)\s*[\'\"]/i', $svg, $matches)) {
            $colors = array_merge($colors, $matches[1]);
        }

        // Inline <style> blocks / style="..." declarations.
        if (preg_match_all('/\b(?:fill|stroke|color)\s*:\s*([^;}\"\']+)/i', $svg, $matches)) {
            $colors = array_merge($colors, $matches[1]);
        }

        $visible = [];
        foreach ($colors as $raw) {
            $parsed = $this->parseColor(trim((string) $raw));
            if ($parsed !== null) {
                $visible[] = $parsed;
            }
        }

        if ($visible === []) {
            return ['is_majority_white' => false, 'light_ratio' => 0.0, 'sample_count' => 0];
        }

        $light = 0;
        foreach ($visible as [$r, $g, $b]) {
            // "White" here includes near-white artwork that would disappear on
            // the normal light header. Require all channels to stay bright so a
            // vivid yellow/cyan logo is not incorrectly classified as white.
            if ($r >= 225 && $g >= 225 && $b >= 225) {
                $light++;
            }
        }

        $ratio = $light / count($visible);

        return [
            'is_majority_white' => $ratio >= 0.60,
            'light_ratio' => round($ratio, 3),
            'sample_count' => count($visible),
        ];
    }

    private function parseColor(string $value): ?array
    {
        $value = strtolower(trim($value));
        if ($value === '' || in_array($value, ['none', 'transparent', 'currentcolor', 'inherit', 'initial', 'unset'], true)) {
            return null;
        }

        $named = [
            'white' => [255, 255, 255],
            'snow' => [255, 250, 250],
            'whitesmoke' => [245, 245, 245],
            'ivory' => [255, 255, 240],
            'floralwhite' => [255, 250, 240],
        ];
        if (isset($named[$value])) {
            return $named[$value];
        }

        if (preg_match('/^#([0-9a-f]{3})$/i', $value, $m)) {
            return [
                hexdec(str_repeat($m[1][0], 2)),
                hexdec(str_repeat($m[1][1], 2)),
                hexdec(str_repeat($m[1][2], 2)),
            ];
        }

        if (preg_match('/^#([0-9a-f]{6})(?:[0-9a-f]{2})?$/i', $value, $m)) {
            return [hexdec(substr($m[1], 0, 2)), hexdec(substr($m[1], 2, 2)), hexdec(substr($m[1], 4, 2))];
        }

        if (preg_match('/^rgba?\(\s*([0-9.]+)%?\s*[, ]\s*([0-9.]+)%?\s*[, ]\s*([0-9.]+)%?/i', $value, $m)) {
            $percent = str_contains($m[0], '%');
            $scale = static fn ($n) => max(0, min(255, (int) round($percent ? ((float) $n * 2.55) : (float) $n)));
            return [$scale($m[1]), $scale($m[2]), $scale($m[3])];
        }

        return null;
    }
}
