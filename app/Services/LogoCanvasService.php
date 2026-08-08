<?php

namespace App\Services;

class LogoCanvasService
{
    public const WIDTH = 650;
    public const HEIGHT = 150;

    public function normalizePng(string $bytes): string
    {
        if (! function_exists('imagecreatefromstring')) {
            return $bytes;
        }

        $source = @imagecreatefromstring($bytes);
        if (! $source) {
            return $bytes;
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        if ($sourceWidth < 1 || $sourceHeight < 1) {
            imagedestroy($source);
            return $bytes;
        }

        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, self::WIDTH, self::HEIGHT, $transparent);

        $paddingX = 18;
        $paddingY = 12;
        $maxWidth = self::WIDTH - ($paddingX * 2);
        $maxHeight = self::HEIGHT - ($paddingY * 2);
        $scale = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));
        $x = (int) floor((self::WIDTH - $targetWidth) / 2);
        $y = (int) floor((self::HEIGHT - $targetHeight) / 2);

        imagecopyresampled($canvas, $source, $x, $y, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);
        ob_start();
        imagepng($canvas, null, 9);
        $normalized = (string) ob_get_clean();
        imagedestroy($source);
        imagedestroy($canvas);

        return $normalized !== '' ? $normalized : $bytes;
    }
    public function normalizePngToPrimary(string $bytes, string $primaryHex): string
    {
        $bytes = $this->normalizePng($bytes);

        if (! function_exists('imagecreatefromstring')) {
            return $bytes;
        }

        $primaryHex = strtoupper(trim($primaryHex));
        if (! preg_match('/^#[0-9A-F]{6}$/', $primaryHex)) {
            return $bytes;
        }

        $image = @imagecreatefromstring($bytes);
        if (! $image) {
            return $bytes;
        }

        $targetR = hexdec(substr($primaryHex, 1, 2));
        $targetG = hexdec(substr($primaryHex, 3, 2));
        $targetB = hexdec(substr($primaryHex, 5, 2));
        $width = imagesx($image);
        $height = imagesy($image);

        imagealphablending($image, false);
        imagesavealpha($image, true);

        // Recolor meaningful chromatic logo pixels to the exact theme primary.
        // Neutral black/gray/white wordmark pixels stay untouched. Alpha is preserved
        // so anti-aliased edges and the transparent canvas remain clean.
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

                // Only replace pixels that visibly carry a hue. This avoids turning
                // black/gray/white typography into the theme color.
                if ($chroma < 18) {
                    continue;
                }

                $replacement = imagecolorallocatealpha($image, $targetR, $targetG, $targetB, $alpha);
                imagesetpixel($image, $x, $y, $replacement);
            }
        }

        ob_start();
        imagepng($image, null, 9);
        $result = (string) ob_get_clean();
        imagedestroy($image);

        return $result !== '' ? $result : $bytes;
    }

}
