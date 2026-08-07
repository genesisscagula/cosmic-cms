<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class IndustryImageManifest
{
    /** @var array<string, array<string, mixed>> */
    private array $manifestCache = [];

    /** @var array<string, true> */
    private array $synchronized = [];


    public function provision(string $industry): void
    {
        $industry = $this->normalizeIndustry($industry);
        File::ensureDirectoryExists($this->directory($industry));

        if (! File::exists($this->path($industry))) {
            $timestamp = now()->toIso8601String();
            $this->write($industry, [
                'version' => 1,
                'industry' => $industry,
                'images' => [],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }
    }

    public function record(string $industry, string $filename, array $metadata = []): void
    {
        $industry = $this->normalizeIndustry($industry);
        $directory = $this->directory($industry);
        File::ensureDirectoryExists($directory);

        $manifest = $this->read($industry);
        $relativeUrl = '/storage/cms-images/'.$industry.'/'.basename($filename);
        $existing = $manifest['images'][$relativeUrl] ?? [];
        $timestamp = now()->toIso8601String();

        $manifest['industry'] = $industry;
        $manifest['updated_at'] = $timestamp;
        $manifest['images'][$relativeUrl] = array_merge([
            'file' => basename($filename),
            'url' => $relativeUrl,
            'role' => 'general',
            'query' => null,
            'provider' => 'local',
            'source_url' => null,
            'created_at' => $timestamp,
        ], $existing, $metadata);

        $this->write($industry, $manifest);
    }


    public function countForRole(string $industry, string $role): int
    {
        $industry = $this->normalizeIndustry($industry);
        $role = Str::slug($role) ?: 'general';
        $manifest = $this->read($industry);

        return collect($manifest['images'] ?? [])
            ->filter(fn ($image) => is_array($image) && Str::slug((string) ($image['role'] ?? 'general')) === $role)
            ->filter(fn ($image) => is_string($image['url'] ?? null) && $this->urlExists($image['url']))
            ->count();
    }

    public function all(string $industry): array
    {
        $industry = $this->normalizeIndustry($industry);

        return array_values($this->read($industry)['images'] ?? []);
    }

    public function candidates(string $industry, ?string $role = null, ?string $query = null): array
    {
        $industry = $this->normalizeIndustry($industry);
        $manifest = $this->read($industry);
        $images = array_values($manifest['images'] ?? []);
        $role = $role ? Str::slug($role) : null;
        $queryTerms = collect(preg_split('/\s+/', Str::lower((string) $query)) ?: [])
            ->filter(fn ($term) => strlen($term) >= 4)
            ->values();

        usort($images, function (array $a, array $b) use ($role, $queryTerms): int {
            $score = function (array $image) use ($role, $queryTerms): int {
                $value = 0;
                if ($role && Str::slug((string) ($image['role'] ?? 'general')) === $role) {
                    $value += 100;
                }

                $haystack = Str::lower(implode(' ', [
                    $image['query'] ?? '',
                    $image['file'] ?? '',
                    $image['role'] ?? '',
                ]));

                foreach ($queryTerms as $term) {
                    if (str_contains($haystack, $term)) {
                        $value += 5;
                    }
                }

                return $value;
            };

            return $score($b) <=> $score($a);
        });

        return collect($images)
            ->pluck('url')
            ->filter(fn ($url) => is_string($url) && $this->urlExists($url))
            ->values()
            ->all();
    }

    /**
     * Index existing local images once per service/request.
     * The previous implementation rewrote manifest.json once per image and
     * repeated that work for every image-bearing Spark.
     */
    public function syncExisting(string $industry): void
    {
        $industry = $this->normalizeIndustry($industry);

        if (isset($this->synchronized[$industry])) {
            return;
        }

        $this->synchronized[$industry] = true;
        $directory = $this->directory($industry);

        if (! File::isDirectory($directory)) {
            return;
        }

        $manifest = $this->read($industry);
        $images = is_array($manifest['images'] ?? null) ? $manifest['images'] : [];
        $changed = false;
        $timestamp = now()->toIso8601String();

        foreach (File::files($directory) as $file) {
            $filename = $file->getFilename();

            if (preg_match('/\.(avif|webp|png|jpe?g)$/i', $filename) !== 1) {
                continue;
            }

            $relativeUrl = '/storage/cms-images/'.$industry.'/'.basename($filename);

            if (isset($images[$relativeUrl])) {
                continue;
            }

            $images[$relativeUrl] = [
                'file' => basename($filename),
                'url' => $relativeUrl,
                'role' => 'general',
                'query' => null,
                'provider' => 'local',
                'source_url' => null,
                'created_at' => $timestamp,
            ];
            $changed = true;
        }

        if (! $changed) {
            return;
        }

        $manifest['version'] = 1;
        $manifest['industry'] = $industry;
        $manifest['images'] = $images;
        $manifest['updated_at'] = $timestamp;

        $this->write($industry, $manifest);
    }

    private function read(string $industry): array
    {
        $industry = $this->normalizeIndustry($industry);

        if (array_key_exists($industry, $this->manifestCache)) {
            return $this->manifestCache[$industry];
        }

        $path = $this->path($industry);
        $fallback = ['version' => 1, 'industry' => $industry, 'images' => []];

        if (! File::exists($path)) {
            return $this->manifestCache[$industry] = $fallback;
        }

        $decoded = json_decode((string) File::get($path), true);

        return $this->manifestCache[$industry] = is_array($decoded) ? $decoded : $fallback;
    }

    private function write(string $industry, array $manifest): void
    {
        $industry = $this->normalizeIndustry($industry);
        File::ensureDirectoryExists($this->directory($industry));

        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (! is_string($json)) {
            return;
        }

        $path = $this->path($industry);
        $temporaryPath = $path.'.tmp';

        File::put($temporaryPath, $json, true);

        if (File::exists($path)) {
            File::delete($path);
        }

        File::move($temporaryPath, $path);
        $this->manifestCache[$industry] = $manifest;
    }

    private function normalizeIndustry(string $industry): string
    {
        return Str::slug($industry) ?: 'default';
    }

    private function directory(string $industry): string
    {
        return storage_path('app/public/cms-images/'.$industry);
    }

    private function path(string $industry): string
    {
        return $this->directory($industry).'/manifest.json';
    }

    private function urlExists(string $url): bool
    {
        if (! str_starts_with($url, '/storage/')) {
            return false;
        }

        return File::exists(storage_path('app/public/'.Str::after($url, '/storage/')));
    }
}
