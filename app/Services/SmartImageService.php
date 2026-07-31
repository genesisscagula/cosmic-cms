<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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
    public function find(string $query, string $fallbackFolder = 'default'): string
    {
        $query = $this->normalizeQuery($query);
        $fallbackFolder = Str::slug($fallbackFolder) ?: 'default';

        if ($query === '' || ! $this->remoteSearchIsConfigured()) {
            return $this->localFallback($fallbackFolder);
        }

        try {
            $cacheKey = 'cosmic-smart-image-url:v2:' . sha1($query);
            $cachedUrl = Cache::get($cacheKey);

            if (is_string($cachedUrl) && $cachedUrl !== '' && $this->publicUrlExists($cachedUrl)) {
                return $cachedUrl;
            }

            $photo = $this->searchUnsplash($query);

            if (! $photo) {
                return $this->localFallback($fallbackFolder);
            }

            $publicUrl = $this->downloadUnsplashPhoto($photo, $query);

            if (! $publicUrl) {
                return $this->localFallback($fallbackFolder);
            }

            Cache::put($cacheKey, $publicUrl, now()->addDays(30));
            $this->triggerUnsplashDownload(data_get($photo, 'links.download_location'));

            return $publicUrl;
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

    private function remoteSearchIsConfigured(): bool
    {
        return config('services.smart_images.provider', 'unsplash') === 'unsplash'
            && filled(config('services.unsplash.access_key'));
    }

    private function searchUnsplash(string $query): ?array
    {
        $response = Http::acceptJson()
            ->withHeaders([
                'Authorization' => 'Client-ID ' . config('services.unsplash.access_key'),
                'Accept-Version' => 'v1',
            ])
            ->connectTimeout(4)
            ->timeout(10)
            ->retry(2, 250, throw: false)
            ->get('https://api.unsplash.com/search/photos', [
                'query' => $query,
                'orientation' => 'landscape',
                'content_filter' => 'high',
                'per_page' => 12,
                'page' => 1,
            ]);

        if (! $response->successful()) {
            logger()->warning('[SmartImageService] Unsplash search was not successful.', [
                'status' => $response->status(),
                'query' => $query,
                'body' => Str::limit($response->body(), 300),
            ]);

            return null;
        }

        $results = collect($response->json('results', []))
            ->filter(fn ($photo) => is_array($photo) && filled(data_get($photo, 'urls.raw')))
            ->take(12)
            ->values();

        if ($results->isEmpty()) {
            return null;
        }

        // Choose the most relevant result rather than a random one. Random
        // selection allowed yacht searches to return computers or code images.
        $tokens = collect(preg_split('/\s+/', strtolower($query)) ?: [])
            ->filter(fn ($token) => strlen($token) >= 4)
            ->unique()
            ->values();

        return $results
            ->map(function (array $photo, int $index) use ($tokens) {
                $haystack = strtolower(implode(' ', array_filter([
                    data_get($photo, 'alt_description'),
                    data_get($photo, 'description'),
                    collect(data_get($photo, 'tags', []))->pluck('title')->implode(' '),
                ])));

                $score = $tokens->sum(fn ($token) => str_contains($haystack, $token) ? 1 : 0);

                return ['photo' => $photo, 'score' => $score, 'index' => $index];
            })
            ->sortByDesc(fn ($item) => ($item['score'] * 100) - $item['index'])
            ->first()['photo'];
    }

    /**
     * Save remote files directly under public/ so generated images work even
     * when php artisan storage:link has not been run yet.
     */
    private function downloadUnsplashPhoto(array $photo, string $query): ?string
    {
        $url = data_get($photo, 'urls.raw') ?: data_get($photo, 'urls.regular');

        if (! is_string($url) || $url === '') {
            return null;
        }

        $response = Http::connectTimeout(5)
            ->timeout(20)
            ->retry(2, 300, throw: false)
            ->get($url, [
                'auto' => 'format',
                'fit' => 'crop',
                'crop' => 'entropy',
                'w' => 1600,
                'h' => 1000,
                'q' => 82,
            ]);

        if (! $response->successful() || $response->body() === '') {
            logger()->warning('[SmartImageService] Unsplash image download failed.', [
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

    private function triggerUnsplashDownload(?string $downloadLocation): void
    {
        if (! is_string($downloadLocation) || $downloadLocation === '') {
            return;
        }

        try {
            Http::acceptJson()
                ->withHeaders([
                    'Authorization' => 'Client-ID ' . config('services.unsplash.access_key'),
                    'Accept-Version' => 'v1',
                ])
                ->connectTimeout(3)
                ->timeout(6)
                ->get($downloadLocation);
        } catch (\Throwable $exception) {
            logger()->notice('[SmartImageService] Unsplash download tracking failed.', [
                'message' => $exception->getMessage(),
            ]);
        }
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
