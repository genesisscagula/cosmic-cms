<?php

namespace App\Services;

use RuntimeException;

class SvgUploadSanitizer
{
    /**
     * Sanitize a static SVG for safe storage/preview.
     *
     * Empty/editor-added <script> nodes (for example some exported logos)
     * are removed instead of causing a false rejection. Executable/event
     * attributes and external resources remain blocked.
     */
    public function sanitize(string $svg): string
    {
        $svg = trim($svg);
        if ($svg === '' || stripos($svg, '<svg') === false) {
            throw new RuntimeException('The SVG file could not be read.');
        }

        // Remove script elements entirely before the file is persisted. This
        // covers harmless empty exporter metadata as well as real script code.
        $svg = preg_replace('/<\s*script\b[^>]*>.*?<\s*\/\s*script\s*>/is', '', $svg) ?? $svg;
        $svg = preg_replace('/<\s*script\b[^>]*\/\s*>/is', '', $svg) ?? $svg;

        // Browser-active elements are not needed for a logo/media SVG.
        if (preg_match('/<\s*(?:iframe|object|embed|foreignObject|audio|video)\b/i', $svg)) {
            throw new RuntimeException('This SVG contains unsupported active or external content.');
        }

        // Block inline JS/event handlers after script removal.
        if (preg_match('/\son[a-z0-9_:-]+\s*=/i', $svg) || preg_match('/javascript\s*:/i', $svg)) {
            throw new RuntimeException('This SVG contains unsupported active or external content.');
        }

        // Only local fragment references such as href="#mark" are allowed.
        // External/data resources can execute or leak requests in browser SVGs.
        if (preg_match('/(?:href|xlink:href)\s*=\s*[\'\"]\s*(?:https?:|\/\/|data:)/i', $svg)) {
            throw new RuntimeException('This SVG contains unsupported active or external content.');
        }

        // Avoid entity/doctype based XML surprises. Standard exported SVGs do
        // not need custom entities.
        if (preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i', $svg)) {
            throw new RuntimeException('This SVG contains unsupported active or external content.');
        }

        return $svg;
    }

    /**
     * Sanitize an uploaded temporary SVG in place so Laravel stores the clean
     * bytes rather than the original unsafe/editor-noisy document.
     */
    public function sanitizePath(string $absolutePath): string
    {
        $svg = @file_get_contents($absolutePath);
        if (! is_string($svg)) {
            throw new RuntimeException('The SVG file could not be read.');
        }

        $clean = $this->sanitize($svg);
        if (@file_put_contents($absolutePath, $clean) === false) {
            throw new RuntimeException('The SVG file could not be prepared for upload.');
        }

        return $clean;
    }
}
