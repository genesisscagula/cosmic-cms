<?php

namespace App\Services;

final class ColorContrastGuard
{
    public function normalizeHex(mixed $value, ?string $fallback = null): ?string
    {
        $value = strtoupper(trim((string) $value));
        if (preg_match('/^#[0-9A-F]{6}$/', $value)) return $value;
        if ($fallback === null) return null;
        $fallback = strtoupper(trim($fallback));
        return preg_match('/^#[0-9A-F]{6}$/', $fallback) ? $fallback : null;
    }

    public function mix(string $foreground, string $background, float $foregroundWeight): string
    {
        [$fr, $fg, $fb] = $this->rgb($foreground);
        [$br, $bg, $bb] = $this->rgb($background);
        $weight = max(0.0, min(1.0, $foregroundWeight));
        return sprintf('#%02X%02X%02X',
            (int) round(($fr * $weight) + ($br * (1 - $weight))),
            (int) round(($fg * $weight) + ($bg * (1 - $weight))),
            (int) round(($fb * $weight) + ($bb * (1 - $weight))),
        );
    }

    public function luminance(string $hex): float
    {
        $channels = $this->rgb($hex);
        $weights = [0.2126, 0.7152, 0.0722];
        $sum = 0.0;
        foreach ($channels as $index => $channel) {
            $value = $channel / 255;
            $linear = $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            $sum += $linear * $weights[$index];
        }
        return $sum;
    }

    public function contrastRatio(string $foreground, string $background): float
    {
        $foregroundLuminance = $this->luminance($foreground);
        $backgroundLuminance = $this->luminance($background);
        return (max($foregroundLuminance, $backgroundLuminance) + 0.05)
            / (min($foregroundLuminance, $backgroundLuminance) + 0.05);
    }

    public function readableForeground(string $background, ?string $preferred = null, float $minimumRatio = 4.5): string
    {
        $background = $this->normalizeHex($background, '#FFFFFF') ?? '#FFFFFF';
        $preferred = $this->normalizeHex($preferred);
        if ($preferred !== null && $this->contrastRatio($preferred, $background) >= $minimumRatio) return $preferred;
        $candidates = array_values(array_unique(array_filter([$preferred, '#FFFFFF', '#F8FAFC', '#0F172A', '#111827', '#000000'])));
        usort($candidates, fn (string $left, string $right): int =>
            $this->contrastRatio($right, $background) <=> $this->contrastRatio($left, $background)
        );
        return $candidates[0] ?? '#0F172A';
    }

    public function ensureContrast(string $foreground, string $background, float $minimumRatio = 4.5): string
    {
        $foreground = $this->normalizeHex($foreground, '#0F172A') ?? '#0F172A';
        $background = $this->normalizeHex($background, '#FFFFFF') ?? '#FFFFFF';
        return $this->contrastRatio($foreground, $background) >= $minimumRatio
            ? $foreground
            : $this->readableForeground($background, $foreground, $minimumRatio);
    }

    public function isLight(string $hex): bool
    {
        return $this->luminance($hex) >= 0.46;
    }

    private function rgb(string $hex): array
    {
        $hex = ltrim($this->normalizeHex($hex, '#243447') ?? '#243447', '#');
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
