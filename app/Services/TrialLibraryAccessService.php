<?php

namespace App\Services;

use App\Models\TrialGeneration;
use Illuminate\Support\Str;

class TrialLibraryAccessService
{
    public function sparkKeys(TrialGeneration $trial): array
    {
        return $this->keysFor('sparks', $trial);
    }

    public function templateKeys(TrialGeneration $trial): array
    {
        return $this->keysFor('templates', $trial);
    }

    public function allowsSpark(TrialGeneration $trial, string $key): bool
    {
        return in_array($key, $this->sparkKeys($trial), true);
    }

    public function allowsTemplate(TrialGeneration $trial, string $key): bool
    {
        return in_array($key, $this->templateKeys($trial), true);
    }

    private function keysFor(string $type, TrialGeneration $trial): array
    {
        $config = (array) config("cosmic-trial-library.{$type}", []);
        $defaults = array_values((array) ($config['default'] ?? []));
        $industrySets = (array) ($config['industry'] ?? []);
        $industry = Str::of((string) $trial->industry)->lower()->replace(['-', '_', '/'], ' ')->squish()->toString();

        $specific = collect($industrySets)
            ->first(function ($keys, $needle) use ($industry) {
                $needle = Str::of((string) $needle)->lower()->replace(['-', '_', '/'], ' ')->squish()->toString();
                return $needle !== '' && str_contains($industry, $needle);
            }, []);

        $limit = max(1, (int) ($config['limit'] ?? count($defaults)));

        return collect([...(array) $specific, ...$defaults])
            ->filter(fn ($key) => is_string($key) && $key !== '')
            ->unique()
            ->take($limit)
            ->values()
            ->all();
    }
}
