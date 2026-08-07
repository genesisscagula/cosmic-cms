<?php

namespace App\Services;

use Illuminate\Support\Str;

final class IndustryResolver
{
    public function resolve(string $value, string $fallback = 'default'): string
    {
        $candidate = $this->extractIndustryLabel($value);
        $normalized = $this->normalize($candidate);
        $catalog = $this->catalog();

        if ($normalized === '') {
            return array_key_exists($fallback, $catalog) ? $fallback : 'default';
        }

        foreach ($catalog as $folder => $definition) {
            if ($folder === 'default') {
                continue;
            }

            $needles = array_merge([$folder, (string) ($definition['label'] ?? '')], (array) ($definition['aliases'] ?? []));

            foreach ($needles as $needle) {
                $alias = $this->normalize((string) $needle);
                if ($alias !== '' && ($normalized === $alias || str_contains($normalized, $alias))) {
                    return $folder;
                }
            }
        }

        // Keep Luna grounded to the curated industry folders. Unknown labels
        // intentionally use default instead of creating free-form directories.
        return array_key_exists($fallback, $catalog) ? $fallback : 'default';
    }

    public function displayName(string $value, string $fallback = 'General Business'): string
    {
        $folder = $this->resolve($value, 'default');

        return (string) ($this->catalog()[$folder]['label'] ?? $fallback);
    }

    /** @return array<string, array<string, mixed>> */
    public function catalog(): array
    {
        return (array) config('cosmic-industries', []);
    }

    /** @return array<int, string> */
    public function keys(bool $includeDefault = false): array
    {
        $keys = array_keys($this->catalog());

        return $includeDefault ? $keys : array_values(array_filter($keys, fn (string $key) => $key !== 'default'));
    }

    public function isSupported(string $industry): bool
    {
        return array_key_exists(Str::slug($industry), $this->catalog());
    }

    public function searchQuery(string $industry): string
    {
        $industry = Str::slug($industry) ?: 'default';

        return (string) ($this->catalog()[$industry]['query'] ?? $this->catalog()['default']['query'] ?? 'professional business photography');
    }

    private function extractIndustryLabel(string $value): string
    {
        if (preg_match('/^industry:\s*([^\r\n.]+)/mi', $value, $matches) === 1) {
            return trim($matches[1]);
        }

        return trim($value);
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replace(['&', '/', '_'], ' ')
            ->replaceMatches('/[^\pL\pN\s-]+/u', ' ')
            ->squish()
            ->toString();
    }
}
