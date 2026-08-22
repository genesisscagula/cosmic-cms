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
        // Prefer Imagick when available. This avoids making logo correctness
        // depend on GD alone and keeps alpha-bound trimming deterministic on
        // hosts that provide ImageMagick instead.
        if (class_exists(\Imagick::class)) {
            try {
                $image = new \Imagick();
                $image->readImageBlob($bytes);
                $image->setImageFormat('png');
                $image->setImageAlphaChannel(\Imagick::ALPHACHANNEL_ACTIVATE);

                // trimImage uses the transparent border as the reference.
                // A small fuzz ignores ultra-faint generator noise/glow.
                if (defined('Imagick::FUZZ')) {
                    $image->setImageArtifact('trim:fuzz', '4%');
                }
                $image->trimImage(0.04 * \Imagick::getQuantum());

                $width = $image->getImageWidth();
                $height = $image->getImageHeight();
                if ($width > 0 && $height > 0 && $padding > 0) {
                    $image->borderImage(new \ImagickPixel('transparent'), $padding, $padding);
                }

                $result = $image->getImagesBlob();
                $image->clear();
                $image->destroy();

                if (is_string($result) && $result !== '') {
                    return $result;
                }
            } catch (\Throwable $exception) {
                logger()->warning('Logo alpha trim failed with Imagick; trying GD.', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if (! function_exists('imagecreatefromstring')) {
            // The browser crop-save path performs the same alpha-bound trim
            // before sending the PNG, so this is a safety fallback rather than
            // a silent dependency. Log it so production configuration issues
            // are visible instead of being mistaken for a successful trim.
            logger()->warning('Logo alpha trim backend unavailable: install GD or Imagick. Browser-trimmed PNG retained.');
            return $bytes;
        }

        $source = @imagecreatefromstring($bytes);
        if (! $source) {
            logger()->warning('Logo alpha trim could not decode PNG with GD. Browser-trimmed PNG retained.');
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

                $opacity = 1 - ($alpha / 127);
                $brightness = max($r, $g, $b) / 255;
                $significance = $opacity * max(0.35, $brightness);

                if ($alpha < 121 && $significance >= 0.045) {
                    $left = min($left, $x);
                    $right = max($right, $x);
                    $top = min($top, $y);
                    $bottom = max($bottom, $y);
                }
            }
        }

        if ($right < $left || $bottom < $top) {
            imagedestroy($source);
            logger()->warning('Logo alpha trim found no significant visible pixels; original PNG retained.');
            return $bytes;
        }

        $left = max(0, $left - $padding);
        $top = max(0, $top - $padding);
        $right = min($width - 1, $right + $padding);
        $bottom = min($height - 1, $bottom + $padding);
        $targetWidth = $right - $left + 1;
        $targetHeight = $bottom - $top + 1;

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

        if ($result === '') {
            logger()->warning('Logo alpha trim produced an empty GD result; browser-trimmed PNG retained.');
            return $bytes;
        }

        return $result;
    }

    /**
     * Place a transparent PNG inside an exact transparent canvas without
     * changing its aspect ratio. This is used for website-header logos so the
     * saved asset has predictable 650x200 dimensions while preserving every
     * visible pixel.
     */
    public function fitTransparentPngToCanvas(string $bytes, int $canvasWidth = 650, int $canvasHeight = 200, int|float $padding = 0.12): string
    {
        // Generated logos are normalized automatically: trim generator whitespace,
        // retain 10–15% breathing room, then fit the visible mark to the standard
        // horizontal header canvas. No cropper interaction is required.
        if (! function_exists('imagecreatefromstring')) {
            if (class_exists(\Imagick::class)) {
                try {
                    $image = new \Imagick();
                    $image->readImageBlob($this->trimTransparentPng($bytes, 2));
                    $image->setImageFormat('png');
                    $padX = (int) round($canvasWidth * (is_float($padding) ? $padding : 0.12));
                    $padY = (int) round($canvasHeight * (is_float($padding) ? $padding : 0.12));
                    $image->thumbnailImage(max(1,$canvasWidth-$padX*2), max(1,$canvasHeight-$padY*2), true, true);
                    $canvas = new \Imagick();
                    $canvas->newImage($canvasWidth, $canvasHeight, new \ImagickPixel('transparent'), 'png');
                    $x=(int) floor(($canvasWidth-$image->getImageWidth())/2);
                    $y=(int) floor(($canvasHeight-$image->getImageHeight())/2);
                    $canvas->compositeImage($image, \Imagick::COMPOSITE_OVER, $x, $y);
                    $result=$canvas->getImagesBlob();
                    $image->clear(); $canvas->clear();
                    return is_string($result) && $result!=='' ? $result : $bytes;
                } catch (\Throwable $e) {
                    logger()->warning('Automatic logo canvas fit failed.', ['message'=>$e->getMessage()]);
                }
            }
            return $bytes;
        }

        $trimmed = $this->trimTransparentPng($bytes, 2);
        $source = @imagecreatefromstring($trimmed);
        if (! $source) {
            return $trimmed;
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        // A fractional padding value is interpreted per-axis. 0.12 means 12%
        // transparent safety space on the left/right AND top/bottom, matching
        // the Cosmic 650x200 header cropper contract. Integer values remain
        // backward-compatible pixel padding.
        if (is_float($padding) && $padding > 0 && $padding < 0.5) {
            $paddingX = (int) round($canvasWidth * $padding);
            $paddingY = (int) round($canvasHeight * $padding);
        } else {
            $paddingX = max(0, (int) round($padding));
            $paddingY = $paddingX;
        }

        $availableWidth = max(1, $canvasWidth - ($paddingX * 2));
        $availableHeight = max(1, $canvasHeight - ($paddingY * 2));
        $scale = min($availableWidth / max(1, $sourceWidth), $availableHeight / max(1, $sourceHeight));
        $targetWidth = max(1, (int) floor($sourceWidth * $scale));
        $targetHeight = max(1, (int) floor($sourceHeight * $scale));
        $targetX = (int) floor(($canvasWidth - $targetWidth) / 2);
        $targetY = (int) floor(($canvasHeight - $targetHeight) / 2);

        $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $canvasWidth, $canvasHeight, $transparent);

        imagecopyresampled(
            $canvas,
            $source,
            $targetX,
            $targetY,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight
        );

        ob_start();
        imagepng($canvas, null, 9);
        $result = (string) ob_get_clean();
        imagedestroy($source);
        imagedestroy($canvas);

        return $result !== '' ? $result : $trimmed;
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
