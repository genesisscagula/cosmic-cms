<?php

namespace App\Services;

class LogoCanvasService
{
    /**
     * Remove transparent excess only. Visible pixels are never cropped.
     * A tiny transparent safety pad keeps anti-aliased edge pixels intact.
     */
    public function trimTransparentPng(string $bytes, int $padding = 6): string
    {
        if (! function_exists('imagecreatefromstring')) {
            return $bytes;
        }

        $source = @imagecreatefromstring($bytes);
        if (! $source) {
            return $bytes;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $left = $width;
        $top = $height;
        $right = -1;
        $bottom = -1;

        // GD alpha: 0 opaque -> 127 transparent. Treat faint anti-alias pixels as visible.
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($source, $x, $y);
                $alpha = ($rgba & 0x7F000000) >> 24;
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;

                // Ignore ultra-faint AI glow/noise around the canvas. Alpha 0 is
                // fully opaque and 127 fully transparent in GD.
                $opacity = 1 - ($alpha / 127);
                $brightness = max($r, $g, $b) / 255;
                $significance = $opacity * max(0.35, $brightness);

                if ($alpha < 116 && $significance >= 0.075) {
                    $left = min($left, $x);
                    $right = max($right, $x);
                    $top = min($top, $y);
                    $bottom = max($bottom, $y);
                }
            }
        }

        if ($right < $left || $bottom < $top) {
            imagedestroy($source);
            return $bytes;
        }

        $left = max(0, $left - $padding);
        $top = max(0, $top - $padding);
        $right = min($width - 1, $right + $padding);
        $bottom = min($height - 1, $bottom + $padding);
        $targetWidth = $right - $left + 1;
        $targetHeight = $bottom - $top + 1;

        // Already tight enough.
        if ($left === 0 && $top === 0 && $targetWidth === $width && $targetHeight === $height) {
            imagedestroy($source);
            return $bytes;
        }

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);
        imagecopy($canvas, $source, 0, 0, $left, $top, $targetWidth, $targetHeight);

        ob_start();
        imagepng($canvas, null, 9);
        $result = (string) ob_get_clean();
        imagedestroy($source);
        imagedestroy($canvas);

        return $result !== '' ? $result : $bytes;
    }

    /**
     * Snap chromatic AI output to the nearest exact palette color.
     * Neutral black/white/gray pixels are preserved.
     */
    public function normalizePngToPalette(string $bytes, array $palette): string
    {
        if (! function_exists('imagecreatefromstring')) {
            return $this->trimTransparentPng($bytes);
        }

        $targets = [];
        foreach (['primary', 'secondary', 'tertiary'] as $key) {
            $hex = strtoupper(trim((string) ($palette[$key] ?? '')));
            if (preg_match('/^#[0-9A-F]{6}$/', $hex)) {
                $targets[] = [
                    'role' => $key,
                    'hex' => $hex,
                    'r' => hexdec(substr($hex, 1, 2)),
                    'g' => hexdec(substr($hex, 3, 2)),
                    'b' => hexdec(substr($hex, 5, 2)),
                ];
            }
        }

        if ($targets === []) {
            return $this->trimTransparentPng($bytes);
        }

        $image = @imagecreatefromstring($bytes);
        if (! $image) {
            return $bytes;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);
        $width = imagesx($image);
        $height = imagesy($image);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba & 0x7F000000) >> 24;
                if ($alpha >= 126) {
                    continue;
                }

                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                $max = max($r, $g, $b);
                $min = min($r, $g, $b);
                $chroma = $max - $min;

                // Preserve neutral typography, white highlights and black details.
                if ($chroma < 20) {
                    continue;
                }

                $nearest = $targets[0];
                $nearestDistance = PHP_INT_MAX;

                foreach ($targets as $target) {
                    $distance = (($r - $target['r']) ** 2)
                        + (($g - $target['g']) ** 2)
                        + (($b - $target['b']) ** 2);

                    // Bias mapping toward PRIMARY so the saved logo keeps the
                    // website theme's visual identity even when the image model
                    // overuses a supporting palette color.
                    $weight = match ($target['role'] ?? 'primary') {
                        'primary' => 0.62,
                        'secondary' => 1.08,
                        'tertiary' => 1.42,
                        default => 1.0,
                    };
                    $weightedDistance = $distance * $weight;

                    if ($weightedDistance < $nearestDistance) {
                        $nearestDistance = $weightedDistance;
                        $nearest = $target;
                    }
                }

                $replacement = imagecolorallocatealpha(
                    $image,
                    $nearest['r'],
                    $nearest['g'],
                    $nearest['b'],
                    $alpha
                );
                imagesetpixel($image, $x, $y, $replacement);
            }
        }

        ob_start();
        imagepng($image, null, 9);
        $mapped = (string) ob_get_clean();
        imagedestroy($image);

        return $this->trimTransparentPng($mapped !== '' ? $mapped : $bytes);
    }

    // Backward-compatible helpers used by older code paths.
    public function normalizePng(string $bytes): string
    {
        return $this->trimTransparentPng($bytes);
    }

    public function normalizePngToPrimary(string $bytes, string $primaryHex): string
    {
        return $this->normalizePngToPalette($bytes, [
            'primary' => $primaryHex,
            'secondary' => $primaryHex,
            'tertiary' => $primaryHex,
        ]);
    }
}
