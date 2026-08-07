<?php

namespace App\Services;

use App\AI\Images\DTO\ImageSearchResult;
use App\AI\Images\ImageProviderManager;
use App\Models\MediaPack;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MediaPackImageService
{
    public function __construct(
        private readonly ImageProviderManager $providers,
        private readonly IndustryResolver $industries,
    ) {
    }


    /** @return array<int, array<string, mixed>> */
    public function existingImages(MediaPack $pack): array
    {
        $directory = storage_path('app/public/'.$pack->storageDirectory());
        $manifest = $this->readManifest($directory, $pack);

        return collect($manifest['images'] ?? [])
            ->filter(fn ($item) => is_array($item) && is_string($item['url'] ?? null) && ($item['url'] ?? '') !== '')
            ->values()
            ->all();
    }

    public function imageSlotCount(array $blocks): int
    {
        $count = 0;
        $walk = function (mixed $value, ?string $key = null) use (&$walk, &$count): void {
            if (is_array($value)) {
                foreach ($value as $childKey => $childValue) {
                    $walk($childValue, is_string($childKey) ? $childKey : null);
                }
                return;
            }

            if (is_string($key) && $this->isImageField($key)) {
                // Dedicated people portraits are not website media-pack slots.
                if (is_string($value) && str_contains($value, '/cms-images/avatars/')) {
                    return;
                }
                $count++;
            }
        };

        $walk($blocks);

        return min(10, $count);
    }

    /** @return array<int, array<string, mixed>> */
    public function populate(MediaPack $pack): array
    {
        $directory = storage_path('app/public/'.$pack->storageDirectory());
        File::ensureDirectoryExists($directory);

        $target = max(0, min(10, (int) $pack->target_image_count));
        $manifest = $this->readManifest($directory, $pack);
        $images = collect($manifest['images'] ?? [])->filter(fn ($item) => is_array($item))->values();
        $industry = $this->resolveIndustry($pack);

        $manifest['industry_key'] = $industry;
        $manifest['provider_priority'] = ['unsplash', 'pexels', 'industry', 'default'];

        if ($images->count() < $target && $target > 0) {
            $keywords = collect($pack->keywords ?? [])
                ->map(fn ($item) => is_array($item) ? ($item['query'] ?? null) : $item)
                ->filter(fn ($query) => is_string($query) && trim($query) !== '')
                ->map(fn ($query) => trim($query))
                ->unique()
                ->values();

            // Luna stays flexible through prompt-specific keywords, while the
            // controlled industry query provides a grounded provider fallback.
            $industryQuery = $this->industries->searchQuery($industry);
            if ($industryQuery !== '') {
                $keywords->push($industryQuery);
            }
            $keywords = $keywords->filter()->unique()->values();

            if ($keywords->isEmpty()) {
                $keywords = collect(['professional business photography']);
            }

            $attempt = 0;
            $maxAttempts = max(4, ($target - $images->count()) * 3);
            $seenSources = $images->pluck('source_url')->filter()->all();

            while ($images->count() < $target && $attempt < $maxAttempts) {
                $query = $keywords[$attempt % $keywords->count()].' editorial website photo '.($attempt + 1);

                try {
                    foreach ($this->providers->searchCandidates($query, ['orientation' => 'landscape']) as $candidate) {
                        if (in_array($candidate->sourceUrl, $seenSources, true)) {
                            continue;
                        }

                        $stored = $this->download($candidate, $directory, $pack, $query);
                        if ($stored !== null) {
                            $this->providers->trackDownload($candidate);
                            $seenSources[] = $candidate->sourceUrl;
                            $images->push($stored);
                            $this->persistProgressManifest($pack, $directory, $manifest, $images->values()->all(), $target, 'downloading');
                            break;
                        }
                    }
                } catch (\Throwable $exception) {
                    Log::warning('[MediaPack] Provider attempt failed; continuing to fallback chain.', [
                        'media_pack_id' => $pack->id,
                        'query' => $query,
                        'message' => $exception->getMessage(),
                    ]);
                }

                $attempt++;
            }
        }

        // A media pack must always complete. Copy curated assets into the pack so
        // every assigned URL remains a permanent /packs/{uuid}/ URL even when both
        // remote providers are unavailable or return too few usable candidates.
        if ($images->count() < $target) {
            $images = $this->appendLocalFallbacks($pack, $directory, $images, $target, $industry);
        }

        $status = $images->count() >= $target ? 'ready' : ($images->count() > 0 ? 'partial' : 'failed');
        $manifest['status'] = $status;
        $manifest['target_image_count'] = $target;
        $manifest['image_count'] = $images->count();
        $manifest['images'] = $images->values()->all();
        $manifest['updated_at'] = now()->toIso8601String();
        $this->writeManifest($directory, $manifest);

        return $images->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function fallback(MediaPack $pack): array
    {
        $directory = storage_path('app/public/'.$pack->storageDirectory());
        File::ensureDirectoryExists($directory);

        $target = max(1, min(10, (int) $pack->target_image_count));
        $manifest = $this->readManifest($directory, $pack);
        $images = collect($manifest['images'] ?? [])->filter(fn ($item) => is_array($item))->values();
        $industry = $this->resolveIndustry($pack);
        $images = $this->appendLocalFallbacks($pack, $directory, $images, $target, $industry);

        $status = $images->isNotEmpty() ? ($images->count() >= $target ? 'ready' : 'partial') : 'failed';
        $manifest['industry_key'] = $industry;
        $manifest['provider_priority'] = ['industry', 'default'];
        $manifest['status'] = $status;
        $manifest['target_image_count'] = $target;
        $manifest['image_count'] = $images->count();
        $manifest['images'] = $images->values()->all();
        $manifest['updated_at'] = now()->toIso8601String();
        $this->writeManifest($directory, $manifest);

        return $images->values()->all();
    }

    public function assignToBlocks(array $blocks, array $images): array
    {
        $urls = collect($images)->pluck('url')->filter()->values()->all();
        if ($urls === []) {
            return $blocks;
        }

        $cursor = 0;
        $assign = function (mixed $value, ?string $key = null) use (&$assign, &$cursor, $urls): mixed {
            if (is_array($value)) {
                foreach ($value as $childKey => $childValue) {
                    $value[$childKey] = $assign($childValue, is_string($childKey) ? $childKey : null);
                }
                return $value;
            }

            if (! is_string($value) || ! $this->isImageField($key) || ! $this->isFallback($value)) {
                return $value;
            }

            // Keep team-member and testimonial portraits exclusive to avatars.
            if (str_contains($value, '/cms-images/avatars/')) {
                return $value;
            }

            $replacement = $urls[$cursor % count($urls)];
            $cursor++;
            return $replacement;
        };

        return $assign($blocks);
    }

    private function appendLocalFallbacks(MediaPack $pack, string $directory, $images, int $target, string $industry)
    {
        foreach (array_unique([$industry, 'default']) as $folder) {
            if ($images->count() >= $target) {
                break;
            }

            $sourceDirectory = storage_path('app/public/cms-images/'.$folder);
            if (! File::isDirectory($sourceDirectory)) {
                continue;
            }

            $candidates = collect(File::files($sourceDirectory))
                ->filter(fn ($file) => in_array(strtolower($file->getExtension()), ['avif', 'webp', 'png', 'jpg', 'jpeg'], true))
                ->shuffle();

            foreach ($candidates as $file) {
                if ($images->count() >= $target) {
                    break;
                }

                $fingerprint = sha1('fallback|'.$folder.'|'.$file->getFilename());
                $extension = strtolower($file->getExtension()) ?: 'jpg';
                $filename = $fingerprint.'.'.$extension;
                $destination = $directory.DIRECTORY_SEPARATOR.$filename;

                if (! File::exists($destination)) {
                    File::copy($file->getPathname(), $destination);
                }

                if (! File::exists($destination) || File::size($destination) === 0) {
                    continue;
                }

                $sourceUrl = '/storage/cms-images/'.$folder.'/'.$file->getFilename();
                if ($images->contains(fn ($item) => ($item['source_url'] ?? null) === $sourceUrl)) {
                    continue;
                }

                $images->push([
                    'filename' => $filename,
                    'url' => $pack->publicBaseUrl().'/'.$filename,
                    'provider' => $folder === 'default' ? 'default' : 'industry',
                    'source_url' => $sourceUrl,
                    'query' => $this->industries->searchQuery($industry),
                    'created_at' => now()->toIso8601String(),
                ]);
            }
        }

        return $images;
    }

    private function resolveIndustry(MediaPack $pack): string
    {
        $pack->loadMissing(['trialGeneration', 'website']);
        $raw = (string) ($pack->website?->industry ?? $pack->trialGeneration?->industry ?? 'default');

        return $this->industries->resolve($raw, 'default');
    }

    private function download(ImageSearchResult $result, string $directory, MediaPack $pack, string $query): ?array
    {
        $fingerprint = sha1($result->provider.'|'.$result->sourceUrl.'|'.$result->url);

        foreach (['avif', 'webp', 'png', 'jpg', 'jpeg'] as $extension) {
            $existing = $directory.DIRECTORY_SEPARATOR.$fingerprint.'.'.$extension;
            if (File::exists($existing) && File::size($existing) > 0) {
                return $this->manifestEntry($pack, basename($existing), $result, $query);
            }
        }

        $response = Http::connectTimeout(max(1, (int) config('cosmic-queue.performance.image_connect_timeout', 4)))
            ->timeout(max(2, (int) config('cosmic-queue.performance.image_download_timeout', 10)))
            ->retry(max(0, (int) config('cosmic-queue.performance.image_download_retries', 1)), 250, throw: false)
            ->get($result->url, $result->downloadParameters);

        if (! $response->successful() || $response->body() === '') {
            return null;
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        $extension = match (true) {
            str_contains($contentType, 'avif') => 'avif',
            str_contains($contentType, 'webp') => 'webp',
            str_contains($contentType, 'png') => 'png',
            default => 'jpg',
        };

        $path = $directory.DIRECTORY_SEPARATOR.$fingerprint.'.'.$extension;
        File::put($path, $response->body());

        if (! File::exists($path) || File::size($path) === 0) {
            File::delete($path);
            return null;
        }

        Log::info('[MediaPack] Image stored.', [
            'media_pack_id' => $pack->id,
            'provider' => $result->provider,
            'query' => $query,
            'file' => basename($path),
        ]);

        return $this->manifestEntry($pack, basename($path), $result, $query);
    }

    private function manifestEntry(MediaPack $pack, string $filename, ImageSearchResult $result, string $query): array
    {
        return [
            'filename' => $filename,
            'url' => $pack->publicBaseUrl().'/'.$filename,
            'provider' => $result->provider,
            'source_url' => $result->sourceUrl,
            'query' => $query,
            'created_at' => now()->toIso8601String(),
        ];
    }


    /**
     * Persist incremental progress so Builder polling shows real percentages
     * while workers are still downloading provider images.
     *
     * @param  array<int, array<string, mixed>>  $images
     */
    private function persistProgressManifest(MediaPack $pack, string $directory, array $manifest, array $images, int $target, string $status): void
    {
        $manifest['status'] = $status;
        $manifest['target_image_count'] = $target;
        $manifest['image_count'] = count($images);
        $manifest['images'] = $images;
        $manifest['updated_at'] = now()->toIso8601String();
        $this->writeManifest($directory, $manifest);

        $pack->forceFill([
            'status' => $status,
            'manifest' => [
                'path' => $pack->storageDirectory().'/manifest.json',
                'image_count' => count($images),
                'target_image_count' => $target,
                'updated_at' => $manifest['updated_at'],
            ],
        ])->save();
    }

    private function readManifest(string $directory, MediaPack $pack): array
    {
        $path = $directory.'/manifest.json';
        if (! File::exists($path)) {
            return [
                'uuid' => $pack->uuid,
                'status' => 'pending_download',
                'target_image_count' => $pack->target_image_count,
                'keywords' => $pack->keywords ?? [],
                'images' => [],
            ];
        }

        $decoded = json_decode((string) File::get($path), true);
        return is_array($decoded) ? $decoded : ['uuid' => $pack->uuid, 'images' => []];
    }

    private function writeManifest(string $directory, array $manifest): void
    {
        $path = $directory.'/manifest.json';
        $temporary = $path.'.tmp';
        File::put($temporary, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::move($temporary, $path);
    }

    private function isImageField(?string $key): bool
    {
        return $key !== null && (
            str_contains($key, 'image') || str_contains($key, 'photo') || str_contains($key, 'poster')
        );
    }

    private function isFallback(string $value): bool
    {
        if ($value === ''
            || str_contains($value, '/cms-images/default/')
            || str_contains($value, '/cms-images/background/')
            || str_contains($value, '/cosmic-images/cosmic-fallback.svg')
            || str_contains($value, '/cosmic-images/media-pack-loading.svg')) {
            return true;
        }

        return str_contains($value, '/storage/cms-images/')
            && ! str_contains($value, '/storage/cms-images/packs/');
    }
}
