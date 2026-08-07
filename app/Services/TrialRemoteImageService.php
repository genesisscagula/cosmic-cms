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
        if (! (bool) config('openai.remote_preview_images_enabled', config('openai.trial_remote_images_enabled', true)) || $target < 1) {
            return [];
        }

        if (! $this->unsplash->isEnabled()) {
            Log::notice('[TrialRemoteImages] Unsplash is unavailable; keeping generated local fallbacks.');
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

            Log::info('[TrialRemoteImages] Remote preview pool resolved.', [
                'provider' => 'unsplash',
                'query' => $query,
                'requested' => $target,
                'resolved' => count($results),
                'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return $results;
        } catch (\Throwable $exception) {
            Log::warning('[TrialRemoteImages] Remote preview lookup failed; keeping local fallbacks.', [
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
        $assign = function (mixed $value, ?string $key = null) use (&$assign, &$cursor, $urls): mixed {
            if (is_array($value)) {
                foreach ($value as $childKey => $childValue) {
                    $value[$childKey] = $assign($childValue, is_string($childKey) ? $childKey : null);
                }

                return $value;
            }

            if (! is_string($key) || ! $this->isImageField($key) || ! is_string($value)) {
                return $value;
            }

            // People portraits keep the curated avatar library. Remote trial
            // images are for website photography, banners and feature visuals.
            if (str_contains($value, '/cms-images/avatars/')) {
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

    private function isImageField(string $key): bool
    {
        $normalized = strtolower($key);

        return str_contains($normalized, 'image')
            || str_contains($normalized, 'photo')
            || str_contains($normalized, 'poster');
    }
}
