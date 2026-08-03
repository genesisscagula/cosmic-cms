<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\AI\Images\DTO\ImageSearchResult;
use App\AI\Images\ImageProviderManager;

class SmartImageService
{
    /**
     * Find a context-aware stock image and return a browser-safe URL.
     *
     * Priority:
     * 1. Cached/downloaded remote image in public/cosmic-images/remote
     * 2. Local industry folder in storage/app/public/cms-images/{industry}
     * 3. Local default folder
     * 4. Local background library
     * 5. Bundled public SVG fallback
     */
    public function __construct(
        private readonly ImageProviderManager $providerManager,
    ) {
    }

    /**
     * Find a context-aware stock image and return a browser-safe URL.
     *
     * Priority:
     * 1. Cached/downloaded remote image in public/cosmic-images/remote
     * 2. Enabled remote providers in configured failover order
     * 3. Local industry folder in storage/app/public/cms-images/{industry}
     * 4. Local default/background libraries
     * 5. Bundled public SVG fallback
     */
    public function find(string $query, string $fallbackFolder = 'default'): string
    {
        $query = $this->normalizeQuery($query);
        $fallbackFolder = Str::slug($fallbackFolder) ?: 'default';

        if ($query === '' || ! $this->providerManager->hasEnabledProvider()) {
            return $this->localFallback($fallbackFolder);
        }

        try {
            // Ranking changes must not reuse a previously accepted low-relevance result.
            $rankingVersion = (string) config('services.smart_images.ranking_version', '4.2.0.4');
            $cacheKey = 'cosmic-smart-image-url:v4:' . sha1($rankingVersion . '|' . $query);
            $cacheEnabled = (bool) config('services.smart_images.cache', true);
            $cachedUrl = $cacheEnabled ? Cache::get($cacheKey) : null;

            if (is_string($cachedUrl) && $cachedUrl !== '' && $this->publicUrlExists($cachedUrl)) {
                return $cachedUrl;
            }

            foreach ($this->providerManager->searchCandidates($query, [
                'orientation' => 'landscape',
            ]) as $result) {
                $publicUrl = $this->downloadRemoteImage($result, $query);

                if (! $publicUrl) {
                    logger()->info('[SmartImageService] Continuing after provider download failure.', [
                        'provider' => $result->provider,
                        'query' => $query,
                    ]);
                    continue;
                }

                if ($cacheEnabled) {
                    $ttl = max(60, (int) config('services.smart_images.cache_ttl', 2592000));
                    Cache::put($cacheKey, $publicUrl, now()->addSeconds($ttl));
                }

                $this->providerManager->trackDownload($result);

                logger()->info('[SmartImageService] Remote image resolved.', [
                    'provider' => $result->provider,
                    'query' => $query,
                    'url' => $publicUrl,
                ]);

                return $publicUrl;
            }

            return $this->localFallback($fallbackFolder);
        } catch (\Throwable $exception) {
            logger()->warning('[SmartImageService] Remote image search failed; using local fallback.', [
                'query' => $query,
                'message' => $exception->getMessage(),
            ]);

            return $this->localFallback($fallbackFolder);
        }
    }

    public function localFallback(string $folder = 'default'): string
    {
        $folder = Str::slug($folder) ?: 'default';

        foreach (array_values(array_unique([$folder, 'default', 'background'])) as $candidateFolder) {
            $files = $this->localImageFiles($candidateFolder);

            if ($files !== []) {
                $file = $files[array_rand($files)];
                $relativePath = str_replace('\\', '/', Str::after($file, storage_path('app/public/')));

                // Root-relative URLs avoid APP_URL/port mismatches in local development.
                return '/storage/' . ltrim($relativePath, '/');
            }
        }

        logger()->error('[SmartImageService] No local fallback images were found.', [
            'requested_folder' => $folder,
            'searched' => [$folder, 'default', 'background'],
        ]);

        return '/cosmic-images/cosmic-fallback.svg';
    }

    /**
     * Return unique local images for image-led collections such as case studies.
     *
     * Unlike the general fallback resolver, this intentionally never reaches
     * into the generic background library. The selected industry is primary,
     * then the default industry library, then the bundled SVG fallback.
     */
    public function localFallbacks(string $folder = 'default', int $count = 1): array
    {
        $folder = Str::slug($folder) ?: 'default';
        $count = max(1, $count);
        $selected = [];

        foreach (array_values(array_unique([$folder, 'default'])) as $candidateFolder) {
            $files = $this->localImageFiles($candidateFolder);
            shuffle($files);

            foreach ($files as $file) {
                $relativePath = str_replace('\\', '/', Str::after($file, storage_path('app/public/')));
                $url = '/storage/' . ltrim($relativePath, '/');

                if (! in_array($url, $selected, true)) {
                    $selected[] = $url;
                }

                if (count($selected) >= $count) {
                    return $selected;
                }
            }
        }

        if ($selected === []) {
            logger()->error('[SmartImageService] No case-study fallback images were found.', [
                'requested_folder' => $folder,
                'searched' => [$folder, 'default'],
            ]);
        }

        while (count($selected) < $count) {
            $selected[] = '/cosmic-images/cosmic-fallback.svg';
        }

        return $selected;
    }

    /**
     * Save a provider result under public/ so generated pages work without a
     * storage symlink. Provider-specific URL parameters live on the DTO.
     */
    private function downloadRemoteImage(ImageSearchResult $result, string $query): ?string
    {
        $response = \Illuminate\Support\Facades\Http::connectTimeout(5)
            ->timeout(20)
            ->retry(2, 300, throw: false)
            ->get($result->url, $result->downloadParameters);

        if (! $response->successful() || $response->body() === '') {
            logger()->warning('[SmartImageService] Remote image download failed.', [
                'provider' => $result->provider,
                'status' => $response->status(),
                'query' => $query,
            ]);

            return null;
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        $extension = match (true) {
            str_contains($contentType, 'webp') => 'webp',
            str_contains($contentType, 'png') => 'png',
            default => 'jpg',
        };

        $relativeDirectory = 'cosmic-images/remote/' . now()->format('Y/m');
        $absoluteDirectory = public_path($relativeDirectory);
        File::ensureDirectoryExists($absoluteDirectory);

        $filename = Str::limit(Str::slug($query), 70, '') ?: 'cosmic-image';
        $filename .= '-' . Str::lower(Str::random(10)) . '.' . $extension;
        $absolutePath = $absoluteDirectory . DIRECTORY_SEPARATOR . $filename;

        File::put($absolutePath, $response->body());

        if (! File::exists($absolutePath) || File::size($absolutePath) === 0) {
            return null;
        }

        return '/' . trim($relativeDirectory, '/') . '/' . $filename;
    }

    private function localImageFiles(string $folder): array
    {
        $directory = storage_path('app/public/cms-images/' . $folder);

        if (! File::isDirectory($directory)) {
            return [];
        }

        return collect(File::files($directory))
            ->filter(fn ($file) => preg_match('/\.(avif|webp|png|jpe?g)$/i', $file->getFilename()) === 1)
            ->map(fn ($file) => $file->getPathname())
            ->values()
            ->all();
    }

    private function publicUrlExists(string $url): bool
    {
        if (! str_starts_with($url, '/cosmic-images/')) {
            return false;
        }

        return File::exists(public_path(ltrim($url, '/')));
    }

    private function normalizeQuery(string $query): string
    {
        $query = strip_tags($query);
        $query = preg_replace('/\s+/', ' ', $query) ?? '';

        return Str::limit(trim($query), 140, '');
    }
}
