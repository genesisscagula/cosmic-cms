<?php

namespace App\Services;

final class ImageSlotResolver
{
    /**
     * Discover every assignable image slot in a block, including nested arrays
     * such as slides[].image_url and studies[].image_url.
     *
     * @return array<int, array{path:string,key:string,role:string,value:mixed}>
     */
    public function slots(array $block): array
    {
        $type = (string) ($block['type'] ?? 'website section');
        $slots = [];

        $walk = function (mixed $value, string $path = '', ?string $key = null) use (&$walk, &$slots, $type): void {
            if (is_array($value)) {
                foreach ($value as $childKey => $childValue) {
                    $childKeyString = (string) $childKey;
                    $childPath = $path === '' ? $childKeyString : $path.'.'.$childKeyString;
                    $walk($childValue, $childPath, is_string($childKey) ? $childKey : null);
                }

                return;
            }

            if (! is_string($key) || ! $this->isAssignableImageField($key)) {
                return;
            }

            if ($this->isProtectedPath($path, $key, $value)) {
                return;
            }

            // Testimonials are intentionally hydrated from Cosmic's local avatar
            // library after content generation. Never turn those avatar fields
            // into provider/Unsplash slots; team/profile photography remains eligible.
            $normalizedType = strtolower($type);
            $normalizedKey = strtolower($key);
            if (str_contains($normalizedType, 'testimonial')
                && ($normalizedKey === 'avatar' || $normalizedKey === 'avatar_url' || str_ends_with($normalizedKey, '_avatar'))) {
                return;
            }

            $slots[] = [
                'path' => $path,
                'key' => $key,
                'role' => $this->roleFor($type, $path, $key),
                'value' => $value,
            ];
        };

        $walk($block);

        return $slots;
    }

    public function count(array $block): int
    {
        return count($this->slots($block));
    }

    public function isAssignableImageField(string $key): bool
    {
        $normalized = strtolower(trim($key));

        if ($normalized === '') {
            return false;
        }

        if (str_contains($normalized, 'logo')
            || str_contains($normalized, 'badge')
            || str_contains($normalized, 'caption')
            || str_contains($normalized, 'label')
            || str_contains($normalized, 'alt')
            || str_contains($normalized, 'video_url')) {
            return false;
        }

        // Sparks use several equivalent naming styles: image_url, image_url_2,
        // image_one_url, before_image_url, poster_image_url, etc. Keep this
        // resolver deliberately schema-based so every visual Spark participates
        // in the same Unsplash hydration pass instead of requiring per-Spark fixes.
        $imageLikeUrl = preg_match('/(^|_)(?:image|photo|poster|thumbnail)(?:_[a-z0-9]+)*_url$/', $normalized) === 1
            || preg_match('/^(?:image|photo|poster|thumbnail)_url(?:_[a-z0-9]+)+$/', $normalized) === 1;

        return $normalized === 'avatar'
            || str_ends_with($normalized, '_avatar')
            || $normalized === 'image'
            || $normalized === 'photo'
            || $normalized === 'poster'
            || $normalized === 'thumbnail'
            || $imageLikeUrl
            || str_contains($normalized, 'poster_image');
    }

    public function looksLikeImageReference(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $trimmed = trim($value);

        // Empty image URL fields are valid provider-assignment targets because
        // Luna is explicitly instructed to leave these blank.
        if ($trimmed === '') {
            return true;
        }

        return str_starts_with($trimmed, 'http://')
            || str_starts_with($trimmed, 'https://')
            || str_starts_with($trimmed, '/')
            || preg_match('/\.(?:avif|gif|jpe?g|png|svg|webp)(?:\?.*)?$/i', $trimmed) === 1;
    }

    private function isProtectedPath(string $path, string $key, mixed $value): bool
    {
        $normalizedKey = strtolower($key);
        $normalizedPath = strtolower($path);
        $stringValue = is_string($value) ? strtolower($value) : '';

        return str_contains($normalizedKey, 'logo')
            || str_contains($normalizedPath, 'logo')
            || str_contains($stringValue, '/storage/branding/');
    }

    private function roleFor(string $type, string $path, string $key): string
    {
        $normalizedPath = strtolower($path);
        $normalizedKey = strtolower($key);

        if ($normalizedKey === 'avatar'
            || str_contains($normalizedPath, 'member')
            || str_contains($normalizedPath, 'testimonial')
            || str_contains($type, 'team')
            || str_contains($type, 'testimonial')) {
            return 'people';
        }

        if (str_contains($normalizedPath, 'slide') || str_starts_with($type, 'hero_')) {
            return 'hero';
        }

        if (str_contains($type, 'case_stud')
            || str_contains($type, 'gallery')
            || str_contains($type, 'portfolio')) {
            return 'gallery';
        }

        if (str_contains($type, 'service') || str_contains($type, 'feature')) {
            return 'services';
        }

        return 'general';
    }
}
