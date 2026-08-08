<?php

namespace App\Services;

use App\AI\Images\Providers\UnsplashProvider;
use Illuminate\Support\Facades\Log;

class TrialRemoteImageService
{
    public function __construct(private readonly UnsplashProvider $unsplash)
    {
    }

    /**
     * Resolve a small remote-only image pool for trial previews.
     * No image bytes are downloaded or written to Cosmic storage.
     *
     * @return array<int, array<string, mixed>>
     */
    public function resolve(array $visualIntent, int $target): array
    {
        return $this->resolveUnsplashPool(
            $visualIntent,
            $target,
            (bool) config('openai.remote_preview_images_enabled', config('openai.trial_remote_images_enabled', true)),
            'TrialRemoteImages'
        );
    }

    /**
     * Logged-in Builder equivalent of the /start remote image flow.
     * Images remain provider-hosted URLs; no Cosmic storage write occurs here.
     */
    public function resolveForRegistered(array $visualIntent, int $target): array
    {
        return $this->resolveUnsplashPool(
            $visualIntent,
            $target,
            (bool) config('openai.registered_remote_images_enabled', true),
            'RegisteredRemoteImages'
        );
    }

    private function resolveUnsplashPool(array $visualIntent, int $target, bool $enabled, string $logChannel): array
    {
        if (! $enabled || $target < 1) {
            return [];
        }

        if (! $this->unsplash->isEnabled()) {
            Log::notice('['.$logChannel.'] Unsplash is unavailable; keeping industry fallbacks.');
            return [];
        }

        $query = collect($visualIntent['image_keywords'] ?? [])
            ->push($visualIntent['business_type'] ?? null)
            ->push($visualIntent['visual_style'] ?? null)
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => trim($value))
            ->unique()
            ->take(5)
            ->implode(' ');

        if ($query === '') {
            $query = 'professional business editorial photography';
        }

        $startedAt = microtime(true);

        try {
            $results = collect($this->unsplash->searchMany($query, ['orientation' => 'landscape']))
                ->unique(fn ($result) => $result->sourceUrl ?: $result->url)
                ->take(min(10, $target))
                ->map(function ($result) use ($query) {
                    return [
                        'url' => $this->previewUrl($result->url, $result->downloadParameters),
                        'provider' => 'unsplash',
                        'source_url' => $result->sourceUrl,
                        'photographer' => $result->photographer,
                        'description' => $result->description,
                        'query' => $query,
                        'remote' => true,
                    ];
                })
                ->filter(fn ($item) => is_string($item['url'] ?? null) && ($item['url'] ?? '') !== '')
                ->values()
                ->all();

            Log::info('['.$logChannel.'] Remote preview pool resolved.', [
                'provider' => 'unsplash',
                'query' => $query,
                'requested' => $target,
                'resolved' => count($results),
                'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return $results;
        } catch (\Throwable $exception) {
            Log::warning('['.$logChannel.'] Remote preview lookup failed; keeping industry fallbacks.', [
                'provider' => 'unsplash',
                'query' => $query,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    public function assignToBlocks(array $blocks, array $images): array
    {
        $urls = collect($images)->pluck('url')->filter()->values()->all();
        if ($urls === []) {
            return $blocks;
        }

        $cursor = 0;
        $assign = function (
            mixed $value,
            ?string $key = null,
            bool $insideImageCollection = false
        ) use (&$assign, &$cursor, $urls): mixed {
            $currentIsImageField = is_string($key) && $this->isImageField($key);
            $imageContext = $insideImageCollection || $currentIsImageField;

            if (is_array($value)) {
                foreach ($value as $childKey => $childValue) {
                    $childName = is_string($childKey) ? $childKey : null;
                    $value[$childKey] = $assign($childValue, $childName, $imageContext);
                }

                return $value;
            }

            if (! is_string($value) || (! $imageContext && ! $currentIsImageField)) {
                return $value;
            }

            // Branding and deliberately curated people assets are not generated
            // page photography and must never be replaced by provider imagery.
            $normalizedKey = strtolower((string) $key);
            if (
                str_contains($normalizedKey, 'logo')
                || str_contains($value, '/cms-images/avatars/')
                || str_contains($value, '/storage/branding/')
            ) {
                return $value;
            }

            // Only image-like scalar values inside image fields/collections are
            // candidates. This prevents captions/alt text nested beside images
            // from being mistaken for URLs.
            if (! $this->looksLikeImageReference($value)) {
                return $value;
            }

            $replacement = $urls[$cursor % count($urls)];
            $cursor++;

            return $replacement;
        };

        return $assign($blocks);
    }

    private function previewUrl(string $url, array $parameters): string
    {
        if ($url === '') {
            return '';
        }

        $parameters = array_merge([
            'auto' => 'format',
            'fit' => 'crop',
            'crop' => 'entropy',
            'w' => 1600,
            'h' => 1000,
            'q' => 82,
        ], $parameters);

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query($parameters);
    }

    private function looksLikeImageReference(string $value): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }

        return str_starts_with($trimmed, 'http://')
            || str_starts_with($trimmed, 'https://')
            || str_starts_with($trimmed, '/')
            || preg_match('/\.(?:avif|gif|jpe?g|png|svg|webp)(?:\?.*)?$/i', $trimmed) === 1;
    }

    private function isImageField(string $key): bool
    {
        $normalized = strtolower($key);

        return str_contains($normalized, 'image')
            || str_contains($normalized, 'photo')
            || str_contains($normalized, 'poster');
    }
}
