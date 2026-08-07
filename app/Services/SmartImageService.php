<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\AI\Images\DTO\ImageSearchResult;
use App\AI\Images\ImageProviderManager;
use Illuminate\Support\Facades\Log;

class SmartImageService
{
    private ?int $remoteDownloadBudget = null;

    private int $remoteDownloadsUsed = 0;
    /**
     * Find a context-aware stock image and return a browser-safe URL.
     *
     * Priority:
     * 1. Local industry folder (including provider images learned earlier)
     * 2. Enabled remote providers; successful results are stored in that folder in storage/app/public/cms-images/{industry}
     * 3. Local default folder
     * 4. Local background library
     * 5. Bundled public SVG fallback
     */
    public function __construct(
        private readonly ImageProviderManager $providerManager,
        private readonly IndustryImageManifest $manifest,
        private readonly IndustryResolver $industryResolver,
    ) {
    }

    /**
     * Find a context-aware stock image and return a browser-safe URL.
     *
     * Priority:
     * 1. Local industry folder (including provider images learned earlier)
     * 2. Enabled remote providers in configured failover order; results are persisted locally
     * 3. Local default/background libraries in storage/app/public/cms-images/{industry}
     * 4. Local default/background libraries
     * 5. Bundled public SVG fallback
     */
    public function provisionIndustry(string $industry): string
    {
        return $this->industryResolver->resolve($industry, 'default');
    }

    public function industryImageCount(string $industry): int
    {
        $industry = $this->industryResolver->resolve($industry, 'default');

        return count($this->localImageFiles($industry));
    }

    public function learnIndustry(string $industry, array $queries): void
    {
        $industry = $this->provisionIndustry($industry);

        $slots = collect($queries)
            ->filter(fn ($item) => is_array($item) && trim((string) ($item['query'] ?? '')) !== '')
            ->map(fn ($item) => [
                'query' => trim((string) $item['query']),
                'role' => Str::slug((string) ($item['role'] ?? 'general')) ?: 'general',
            ])
            ->values();

        $required = $slots->count();
        $existing = count($this->localImageFiles($industry));
        $missing = max(0, $required - $existing);

        Log::info('[SmartImageService] Industry image requirement calculated.', [
            'industry' => $industry,
            'required' => $required,
            'existing' => $existing,
            'missing' => $missing,
        ]);

        if ($missing === 0) {
            return;
        }

        // Download only the exact shortage. Existing industry images count
        // toward the requirement regardless of their original role.
        $attempt = 0;
        $maxAttempts = max($missing * 3, $missing);

        while ($missing > 0 && $attempt < $maxAttempts) {
            $slot = $slots[$attempt % max(1, $required)] ?? [
                'query' => $industry.' professional business photography',
                'role' => 'general',
            ];

            $sequence = $existing + ($attempt + 1);
            $query = $this->normalizeQuery($slot['query'].' unique photo '.$sequence);

            try {
                $before = count($this->localImageFiles($industry));
                $this->downloadLearningImage($query, $industry, $slot['role']);
                $after = count($this->localImageFiles($industry));

                if ($after > $before) {
                    $missing--;
                }
            } catch (\Throwable $exception) {
                Log::warning('[SmartImageService] Industry image download failed.', [
                    'industry' => $industry,
                    'role' => $slot['role'],
                    'query' => $query,
                    'message' => $exception->getMessage(),
                ]);
            }

            $attempt++;
        }

        Log::info('[SmartImageService] Industry image requirement completed.', [
            'industry' => $industry,
            'required' => $required,
            'available' => count($this->localImageFiles($industry)),
            'remaining_missing' => $missing,
        ]);
    }

    public function replaceFallbackImages(array $blocks, string $industry): array
    {
        foreach ($blocks as &$block) {
            if (! is_array($block)) {
                continue;
            }

            $type = (string) ($block['type'] ?? '');
            $role = match (true) {
                str_starts_with($type, 'hero_') => 'hero',
                str_contains($type, 'team') || str_contains($type, 'testimonial') => 'people',
                str_contains($type, 'gallery') || str_contains($type, 'portfolio') || str_contains($type, 'case_stud') => 'gallery',
                str_contains($type, 'service') || str_contains($type, 'feature') => 'services',
                default => 'general',
            };

            foreach (['image_url', 'poster_image_url', 'before_image_url', 'after_image_url'] as $field) {
                if (! array_key_exists($field, $block) || ! $this->isGenericFallback((string) $block[$field])) {
                    continue;
                }

                $replacement = $this->manifest->candidates($industry, $role, $type)[0] ?? null;
                if ($replacement) {
                    $block[$field] = $replacement;
                }
            }

            if (is_array($block['studies'] ?? null)) {
                $gallery = $this->manifest->candidates($industry, 'gallery', $type);
                foreach ($block['studies'] as $index => &$study) {
                    if (is_array($study) && $this->isGenericFallback((string) ($study['image_url'] ?? '')) && isset($gallery[$index])) {
                        $study['image_url'] = $gallery[$index];
                    }
                }
                unset($study);
            }
        }
        unset($block);

        return $blocks;
    }

    private function downloadLearningImage(string $query, string $industry, string $role): ?string
    {
        foreach ($this->providerManager->searchCandidates($query, ['orientation' => 'landscape']) as $result) {
            $url = $this->downloadRemoteImage($result, $query, $industry, $role);
            if ($url) {
                $this->providerManager->trackDownload($result);
                return $url;
            }
        }

        return null;
    }

    private function learningQueryVariants(string $query, string $role, int $count): array
    {
        $suffixes = match ($role) {
            'hero' => ['wide cinematic scene', 'professional exterior'],
            'services' => ['technician at work', 'service detail'],
            'people' => ['authentic professional portrait', 'team at work'],
            'gallery' => ['completed work detail', 'editorial portfolio'],
            default => ['professional detail'],
        };

        return collect($suffixes)
            ->take(max(1, $count))
            ->map(fn ($suffix) => $this->normalizeQuery($query.' '.$suffix))
            ->values()
            ->all();
    }

    private function isGenericFallback(string $url): bool
    {
        return $url === ''
            || str_contains($url, '/cms-images/default/')
            || str_contains($url, '/cms-images/background/')
            || str_contains($url, '/cosmic-images/cosmic-fallback.svg');
    }

    public function withRemoteDownloadBudget(int $budget, callable $callback): mixed
    {
        $previousBudget = $this->remoteDownloadBudget;
        $previousUsed = $this->remoteDownloadsUsed;

        $this->remoteDownloadBudget = max(0, $budget);
        $this->remoteDownloadsUsed = 0;

        try {
            return $callback();
        } finally {
            $this->remoteDownloadBudget = $previousBudget;
            $this->remoteDownloadsUsed = $previousUsed;
        }
    }

    public function find(string $query, string $fallbackFolder = 'default', ?string $role = null): string
    {
        $query = $this->normalizeQuery($query);
        $fallbackFolder = Str::slug($fallbackFolder) ?: 'default';

        // Local-first: a Luna/AI-resolved industry library always wins.
        // Generic default/background assets are intentionally deferred until
        // after remote providers so a new industry can still get relevant art.
        $localIndustryUrl = $this->localIndustryImage($fallbackFolder, $role, $query);
        if ($localIndustryUrl !== null) {
            logger()->debug('[SmartImageService] Local industry image resolved.', [
                'industry' => $fallbackFolder,
                'url' => $localIndustryUrl,
            ]);

            return $localIndustryUrl;
        }

        if ($query === '' || ! $this->providerManager->hasEnabledProvider()) {
            return $this->localFallback($fallbackFolder);
        }

        if ($this->remoteDownloadBudget !== null && $this->remoteDownloadsUsed >= $this->remoteDownloadBudget) {
            logger()->debug('[SmartImageService] Remote image budget reached; using local fallback.', [
                'industry' => $fallbackFolder,
                'role' => $role,
                'budget' => $this->remoteDownloadBudget,
            ]);

            return $this->localFallback($fallbackFolder);
        }

        try {
            $rankingVersion = (string) config('services.smart_images.ranking_version', '4.2.0.4');
            $cacheKey = 'cosmic-smart-image-url:v5:' . sha1($rankingVersion . '|' . $fallbackFolder . '|' . $query);
            $cacheEnabled = (bool) config('services.smart_images.cache', true);
            $cachedUrl = $cacheEnabled ? Cache::get($cacheKey) : null;

            if (is_string($cachedUrl) && $cachedUrl !== '' && $this->publicUrlExists($cachedUrl)) {
                return $cachedUrl;
            }

            foreach ($this->providerManager->searchCandidates($query, [
                'orientation' => 'landscape',
            ]) as $result) {
                $publicUrl = $this->downloadRemoteImage($result, $query, $fallbackFolder, $role);

                if ($publicUrl && $this->remoteDownloadBudget !== null) {
                    $this->remoteDownloadsUsed++;
                }

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
                    'industry' => $fallbackFolder,
                    'query' => $query,
                    'url' => $publicUrl,
                ]);

                return $publicUrl;
            }

            return $this->localFallback($fallbackFolder);
        } catch (\Throwable $exception) {
            logger()->warning('[SmartImageService] Remote image search failed; using local fallback.', [
                'industry' => $fallbackFolder,
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
     * Persist a provider result in the resolved local industry library so later
     * generations can reuse it. Provider-specific URL parameters live on the DTO.
     */
    private function downloadRemoteImage(ImageSearchResult $result, string $query, string $industry, ?string $role = null): ?string
    {
        $industry = Str::slug($industry) ?: 'default';
        $sourceFingerprint = sha1($result->provider . '|' . $result->sourceUrl . '|' . $result->url);

        // New/unknown Luna industries are provisioned lazily. Once an image is
        // downloaded it lives in the local industry library and is reused by
        // every later generation before another provider request is attempted.
        $absoluteDirectory = storage_path('app/public/cms-images/' . $industry);
        File::ensureDirectoryExists($absoluteDirectory);

        foreach (['avif', 'webp', 'png', 'jpg', 'jpeg'] as $existingExtension) {
            $existingPath = $absoluteDirectory . DIRECTORY_SEPARATOR . $sourceFingerprint . '.' . $existingExtension;
            if (File::exists($existingPath) && File::size($existingPath) > 0) {
                $url = '/storage/cms-images/' . $industry . '/' . basename($existingPath);
                $this->manifest->record($industry, basename($existingPath), ['role' => $role ?: 'general', 'query' => $query, 'provider' => $result->provider, 'source_url' => $result->sourceUrl]);
                return $url;
            }
        }

        $response = \Illuminate\Support\Facades\Http::connectTimeout(5)
            ->timeout(20)
            ->retry(2, 300, throw: false)
            ->get($result->url, $result->downloadParameters);

        if (! $response->successful() || $response->body() === '') {
            logger()->warning('[SmartImageService] Remote image download failed.', [
                'provider' => $result->provider,
                'industry' => $industry,
                'status' => $response->status(),
                'query' => $query,
            ]);

            return null;
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        $extension = match (true) {
            str_contains($contentType, 'avif') => 'avif',
            str_contains($contentType, 'webp') => 'webp',
            str_contains($contentType, 'png') => 'png',
            default => 'jpg',
        };

        $absolutePath = $absoluteDirectory . DIRECTORY_SEPARATOR . $sourceFingerprint . '.' . $extension;
        File::put($absolutePath, $response->body());

        if (! File::exists($absolutePath) || File::size($absolutePath) === 0) {
            File::delete($absolutePath);
            return null;
        }

        $this->manifest->record($industry, basename($absolutePath), [
            'role' => $role ?: 'general',
            'query' => $query,
            'provider' => $result->provider,
            'source_url' => $result->sourceUrl,
        ]);

        logger()->info('[SmartImageService] Industry image stored for reuse.', [
            'provider' => $result->provider,
            'industry' => $industry,
            'query' => $query,
            'path' => $absolutePath,
        ]);

        return '/storage/cms-images/' . $industry . '/' . basename($absolutePath);
    }

    private function localIndustryImage(string $folder, ?string $role = null, ?string $query = null): ?string
    {
        if ($folder === 'default' || $folder === 'background') {
            return null;
        }

        $this->manifest->syncExisting($folder);
        $manifestCandidates = $this->manifest->candidates($folder, $role, $query);
        if ($manifestCandidates !== []) {
            return $manifestCandidates[array_rand($manifestCandidates)];
        }

        $files = $this->localImageFiles($folder);
        if ($files === []) {
            return null;
        }

        $file = $files[array_rand($files)];
        $relativePath = str_replace('\\', '/', Str::after($file, storage_path('app/public/')));

        return '/storage/' . ltrim($relativePath, '/');
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
        if (str_starts_with($url, '/storage/cms-images/')) {
            $relativePath = Str::after($url, '/storage/');
            return File::exists(storage_path('app/public/' . $relativePath));
        }

        if (str_starts_with($url, '/cosmic-images/')) {
            return File::exists(public_path(ltrim($url, '/')));
        }

        return false;
    }

    private function normalizeQuery(string $query): string
    {
        $query = strip_tags($query);
        $query = preg_replace('/\s+/', ' ', $query) ?? '';

        return Str::limit(trim($query), 140, '');
    }
}
