<?php

namespace App\Services;

use App\Support\PageStyleRegistry;

final class SectionCompositionService
{
    private const LIGHT = ['white', 'surface', 'surface_alt', 'accent_tint'];

    public function resolve(array $block, int $index, ?string $style, ?string $previous = null): string
    {
        $explicit = strtolower(trim((string) ($block['theme'] ?? 'auto')));
        if ($explicit !== '' && $explicit !== 'auto') return $explicit;

        $type = strtolower((string) ($block['type'] ?? ''));
        $pattern = PageStyleRegistry::pattern($style);
        $role = $pattern[$index % max(1, count($pattern))] ?? 'surface';

        // Semantic intent beats a blind alternating slot.
        if ($this->isHero($type)) {
            // Image/video heroes carry their own visual contrast; otherwise keep the brand
            // accent restrained instead of painting the entire hero primary by default.
            $role = $this->hasMedia($block) ? 'surface' : ($style === 'premium' ? 'accent_tint' : 'white');
        } elseif ($this->isCta($type)) {
            $role = $style === 'clean' ? 'accent_tint' : 'neutral_dark';
        } elseif ($this->isProof($type)) {
            $role = $style === 'premium' ? 'surface_alt' : 'surface';
        } elseif ($this->isFaq($type)) {
            $role = 'surface_alt';
        }

        // Avoid a mechanical white/surface loop and identical adjacent treatments.
        if ($previous === $role) {
            $role = match ($role) {
                'white' => 'surface_alt',
                'surface' => 'white',
                'surface_alt' => 'surface',
                'accent_tint' => 'white',
                'neutral_dark', 'primary' => 'surface',
                default => 'surface_alt',
            };
        }

        // Solid brand is an explicit/intentional role only. Auto composition never lands
        // on primary; accent-first means brand color is for controls/details/highlights.
        if ($role === 'primary') $role = 'accent_tint';

        return in_array($role, [...self::LIGHT, 'neutral_dark'], true) ? $role : 'surface';
    }

    private function isHero(string $type): bool { return str_starts_with($type, 'hero_'); }
    private function isCta(string $type): bool { return str_contains($type, 'cta') || str_contains($type, 'contact') || str_contains($type, 'booking'); }
    private function isProof(string $type): bool { return str_contains($type, 'testimonial') || str_contains($type, 'logo') || str_contains($type, 'trust') || str_contains($type, 'stats'); }
    private function isFaq(string $type): bool { return str_contains($type, 'faq'); }
    private function hasMedia(array $block): bool
    {
        foreach ($block as $key => $value) {
            if (! is_string($value) || trim($value) === '') continue;
            $key = strtolower((string) $key);
            if (str_contains($key, 'image') || str_contains($key, 'video') || str_contains($key, 'background')) return true;
        }
        return false;
    }
}
