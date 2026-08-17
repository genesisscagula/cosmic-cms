<?php

namespace App\Services;

use App\AI\Images\Providers\UnsplashProvider;
use Illuminate\Support\Facades\Log;

class TrialRemoteImageService
{
    public function __construct(
        private readonly UnsplashProvider $unsplash,
        private readonly ImageSlotResolver $imageSlots,
    )
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


    /**
     * Resolve one provider image per discovered image slot. Queries are produced
     * by AiPageGenerationService/ImageSlotResolver in the exact traversal order
     * used by assignToBlocks(), so nested Slider/Team/Testimonial slots stay aligned.
     *
     * @param array<int, array<string,mixed>> $slotQueries
     * @return array<int, array<string,mixed>>
     */
    public function resolveForQueries(array $visualIntent, array $slotQueries, string $logChannel = 'RemoteSlotImages'): array
    {
        $enabled = $logChannel === 'RegisteredRemoteImages'
            ? (bool) config('openai.registered_remote_images_enabled', true)
            : (bool) config('openai.remote_preview_images_enabled', config('openai.trial_remote_images_enabled', true));

        if (! $enabled || $slotQueries === [] || ! $this->unsplash->isEnabled()) {
            return [];
        }

        $slotQueries = array_slice(array_values($slotQueries), 0, 12);
        $used = [];
        $poolCache = [];
        $poolCursor = [];
        $resolved = [];
        $startedAt = microtime(true);

        foreach ($slotQueries as $index => $slot) {
            $query = trim((string) ($slot['query'] ?? ''));
            $role = (string) ($slot['role'] ?? 'general');
            $orientation = $role === 'people' ? 'portrait' : 'landscape';

            if ($query === '') {
                $query = $this->fallbackQuery($visualIntent, $role);
            }

            $cacheKey = sha1($orientation.'|'.$query);

            try {
                if (! array_key_exists($cacheKey, $poolCache)) {
                    $poolCache[$cacheKey] = $this->unsplash->searchMany($query, ['orientation' => $orientation]);
                    $poolCursor[$cacheKey] = 0;
                }

                $candidate = $this->nextUniqueCandidate($poolCache[$cacheKey], $poolCursor[$cacheKey], $used);

                // A narrow query can occasionally exhaust its unique candidates.
                // Retry once with stable business + role intent before falling back.
                if (! $candidate) {
                    $fallbackQuery = $this->fallbackQuery($visualIntent, $role);
                    $fallbackKey = sha1($orientation.'|'.$fallbackQuery);

                    if (! array_key_exists($fallbackKey, $poolCache)) {
                        $poolCache[$fallbackKey] = $this->unsplash->searchMany($fallbackQuery, ['orientation' => $orientation]);
                        $poolCursor[$fallbackKey] = 0;
                    }

                    $candidate = $this->nextUniqueCandidate($poolCache[$fallbackKey], $poolCursor[$fallbackKey], $used);
                    if ($candidate) {
                        $query = $fallbackQuery;
                    }
                }

                if (! $candidate) {
                    continue;
                }

                $identity = $candidate->sourceUrl ?: $candidate->url;
                $used[$identity] = true;

                $resolved[] = [
                    'url' => $this->previewUrl($candidate->url, $candidate->downloadParameters),
                    'provider' => 'unsplash',
                    'source_url' => $candidate->sourceUrl,
                    'photographer' => $candidate->photographer,
                    'description' => $candidate->description,
                    'query' => $query,
                    'role' => $role,
                    'path' => (string) ($slot['path'] ?? ''),
                    'block_type' => (string) ($slot['block_type'] ?? ''),
                    'block_index' => (int) ($slot['block_index'] ?? 0),
                    'slot_index' => (int) ($slot['slot_index'] ?? $index),
                    'remote' => true,
                ];
            } catch (\Throwable $exception) {
                Log::warning('['.$logChannel.'] Slot image lookup failed; continuing with remaining slots.', [
                    'query' => $query,
                    'role' => $role,
                    'path' => $slot['path'] ?? null,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        Log::info('['.$logChannel.'] Slot-aware remote images resolved.', [
            'requested_slots' => count($slotQueries),
            'resolved_slots' => count($resolved),
            'unique_sources' => count($used),
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $resolved;
    }

    private function nextUniqueCandidate(array $pool, int &$cursor, array $used): mixed
    {
        $count = count($pool);

        while ($cursor < $count) {
            $candidate = $pool[$cursor] ?? null;
            $cursor++;

            if (! $candidate) {
                continue;
            }

            $identity = $candidate->sourceUrl ?: $candidate->url;
            if ($identity === '' || isset($used[$identity])) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    private function fallbackQuery(array $visualIntent, string $role): string
    {
        $business = trim((string) ($visualIntent['business_type'] ?? ''));
        $style = trim((string) ($visualIntent['visual_style'] ?? ''));

        $roleIntent = match ($role) {
            'people' => 'professional natural portrait',
            'hero' => 'editorial hero photography',
            'gallery' => 'project editorial photography',
            'services' => 'professional service photography',
            default => 'professional business editorial photography',
        };

        return trim(implode(' ', array_filter([$business, $style, $roleIntent])));
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

        // Slot-aware results carry block_index + relative field path. Using that
        // mapping prevents one failed lookup from shifting every later image.
        $slotMap = [];
        foreach ($images as $image) {
            if (! is_array($image) || ! is_string($image['url'] ?? null) || ($image['url'] ?? '') === '') {
                continue;
            }

            if (isset($image['block_index']) && is_string($image['path'] ?? null) && ($image['path'] ?? '') !== '') {
                $slotMap[(int) $image['block_index'].':'.$image['path']] = $image['url'];
            }
        }

        if ($slotMap !== []) {
            // Keep a small provider pool available for any image slot whose narrow
            // Unsplash lookup failed. This avoids leaving generic SVG/empty placeholders
            // in otherwise-complete AI pages while still preserving user-selected media.
            $fallbackCursor = 0;

            foreach ($blocks as $blockIndex => $block) {
                if (! is_array($block)) {
                    continue;
                }

                $assignPath = function (mixed $value, string $path = '', ?string $key = null) use (&$assignPath, $slotMap, $blockIndex, $urls, &$fallbackCursor): mixed {
                    if (is_array($value)) {
                        foreach ($value as $childKey => $childValue) {
                            $childName = is_string($childKey) ? $childKey : null;
                            $childPath = $path === '' ? (string) $childKey : $path.'.'.$childKey;
                            $value[$childKey] = $assignPath($childValue, $childPath, $childName);
                        }

                        return $value;
                    }

                    if (! is_string($key) || ! $this->imageSlots->isAssignableImageField($key)) {
                        return $value;
                    }

                    if (str_contains(strtolower($path), 'logo')
                        || (is_string($value) && str_contains(strtolower($value), '/storage/branding/'))) {
                        return $value;
                    }

                    if (! $this->imageSlots->looksLikeImageReference($value)) {
                        return $value;
                    }

                    $replacement = $slotMap[$blockIndex.':'.$path] ?? null;
                    if (is_string($replacement) && $replacement !== '') {
                        return $replacement;
                    }

                    if ($this->isWeakGeneratedFallback($value) && $urls !== []) {
                        $replacement = $urls[$fallbackCursor % count($urls)];
                        $fallbackCursor++;

                        return $replacement;
                    }

                    return $value;
                };

                $blocks[$blockIndex] = $assignPath($block);
            }

            return $blocks;
        }

        // Backwards-compatible sequential assignment for legacy image pools that
        // do not carry explicit slot metadata.
        $cursor = 0;
        $assign = function (mixed $value, ?string $key = null, string $path = '') use (&$assign, &$cursor, $urls): mixed {
            if (is_array($value)) {
                foreach ($value as $childKey => $childValue) {
                    $childName = is_string($childKey) ? $childKey : null;
                    $childPath = $path === '' ? (string) $childKey : $path.'.'.$childKey;
                    $value[$childKey] = $assign($childValue, $childName, $childPath);
                }

                return $value;
            }

            if (! is_string($key) || ! $this->imageSlots->isAssignableImageField($key)) {
                return $value;
            }

            if (str_contains(strtolower($path), 'logo')
                || (is_string($value) && str_contains(strtolower($value), '/storage/branding/'))) {
                return $value;
            }

            if (! $this->imageSlots->looksLikeImageReference($value)) {
                return $value;
            }

            $replacement = $urls[$cursor % count($urls)];
            $cursor++;

            return $replacement;
        };

        return $assign($blocks);
    }


    private function isWeakGeneratedFallback(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $url = trim($value);

        return $url === ''
            || str_contains($url, '/cosmic-images/cosmic-fallback.svg')
            || str_contains($url, '/storage/cms-images/default/')
            || str_contains($url, '/storage/cms-images/background/');
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

}
