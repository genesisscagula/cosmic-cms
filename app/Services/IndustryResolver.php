<?php

namespace App\Services;

use Illuminate\Support\Str;

final class IndustryResolver
{
    public function resolve(string $value, string $fallback = 'default'): string
    {
        // Read an explicit line before stripping directives. The directive pass
        // intentionally joins prompt segments, which would otherwise erase the
        // line boundary required by `Industry: ...` extraction.
        $candidate = $this->explicitIndustryLabel($value)
            ?? $this->extractIndustryLabel($this->stripNegativeDirectives($value));
        $normalized = $this->normalize($candidate);
        $catalog = $this->catalog();

        if ($normalized === '') {
            return array_key_exists($fallback, $catalog) ? $fallback : 'default';
        }

        // Prefer exact canonical labels/aliases before considering a phrase
        // contained in a longer business brief.
        foreach ($catalog as $folder => $definition) {
            if ($folder === 'default') {
                continue;
            }

            $needles = array_merge([$folder, (string) ($definition['label'] ?? '')], (array) ($definition['aliases'] ?? []));

            foreach ($needles as $needle) {
                $alias = $this->normalize((string) $needle);
                if ($alias !== '' && $normalized === $alias) {
                    return $folder;
                }
            }
        }

        foreach ($catalog as $folder => $definition) {
            if ($folder === 'default') {
                continue;
            }

            $needles = array_merge([$folder, (string) ($definition['label'] ?? '')], (array) ($definition['aliases'] ?? []));

            foreach ($needles as $needle) {
                $alias = $this->normalize((string) $needle);
                if ($alias !== '' && $this->containsPhrase($normalized, $alias)) {
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

    private function explicitIndustryLabel(string $value): ?string
    {
        if (preg_match('/^industry:\s*([^\r\n.]+)/mi', $value, $matches) !== 1) {
            return null;
        }

        $label = trim($matches[1]);

        return $label !== '' ? $label : null;
    }

    private function containsPhrase(string $value, string $phrase): bool
    {
        return preg_match('/(?:^|\s)'.preg_quote($phrase, '/').'(?=\s|$)/u', $value) === 1;
    }

    private function stripNegativeDirectives(string $value): string
    {
        // Industry detection must describe what the business IS, not what the
        // prompt explicitly says to avoid. Drop negative instruction sentences
        // before alias matching (e.g. "Avoid construction imagery").
        $segments = preg_split('/(?<=[.!?])\s+|\R+/u', $value) ?: [$value];

        $positive = array_filter($segments, function (string $segment): bool {
            $segment = trim($segment);

            if ($segment === '') {
                return false;
            }

            return preg_match('/^(?:avoid|do\s+not|don[’\']t|never|exclude|without|no\s+(?:generic|construction|stock|corporate))\b/iu', $segment) !== 1;
        });

        return trim(implode(' ', $positive));
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replace(['&', '/', '_', '-'], ' ')
            ->replaceMatches('/[^\pL\pN\s]+/u', ' ')
            ->squish()
            ->toString();
    }
}
