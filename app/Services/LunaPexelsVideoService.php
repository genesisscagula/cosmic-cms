<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class LunaPexelsVideoService
{
    private const FALLBACK = '/storage/cms-videos/hero-placeholder.mp4';

    private const VIDEO_BLOCKS = [
        'hero_video_premium',
        'hero_video_background',
        'hero_video_cinematic_premium',
        'hero_video_split_premium',
        'testimonials_video_premium',
    ];

    /**
     * Resolve one landscape clip for a universal section background without
     * requiring the Spark itself to expose a native video_url field.
     */
    public function backgroundFor(string $prompt, array $block = []): array
    {
        $query = $this->buildQuery($prompt, $block, 'universal_background_video');

        if (! filled(config('services.pexels.api_key'))) {
            return [
                'url' => '',
                'source' => 'unavailable',
                'query' => $query,
                'attribution' => null,
            ];
        }

        $video = $this->searchBest($query, false);
        if (! $video) {
            return [
                'url' => '',
                'source' => 'unavailable',
                'query' => $query,
                'attribution' => null,
            ];
        }

        return [
            'url' => (string) $video['url'],
            'source' => 'pexels',
            'query' => $query,
            'attribution' => [
                'provider' => 'Pexels',
                'creator' => (string) ($video['creator'] ?? ''),
                'source_url' => (string) ($video['source_url'] ?? ''),
            ],
        ];
    }

    public function apply(string $prompt, array $blocks): array
    {
        if (! filled(config('services.pexels.api_key'))) {
            return $this->ensureFallbacks($blocks);
        }

        foreach ($blocks as $index => $block) {
            if (! is_array($block) || ! in_array((string) ($block['type'] ?? ''), self::VIDEO_BLOCKS, true)) {
                continue;
            }

            $type = (string) $block['type'];
            $query = $this->buildQuery($prompt, $block, $type);
            $video = $this->searchBest($query, $type === 'testimonials_video_premium');

            $blocks[$index]['video_url'] = $video['url'] ?? self::FALLBACK;
            $blocks[$index]['video_source'] = $video ? 'pexels' : 'local_fallback';
            $blocks[$index]['video_search_query'] = $query;

            if ($video) {
                $blocks[$index]['video_attribution'] = [
                    'provider' => 'Pexels',
                    'creator' => $video['creator'],
                    'source_url' => $video['source_url'],
                ];
            }
        }

        return $blocks;
    }

    private function searchBest(string $query, bool $preferPeople): ?array
    {
        $key = 'luna:pexels-video:v1:'.sha1(strtolower($query).'|'.($preferPeople ? 'people' : 'hero'));

        return Cache::remember($key, now()->addDays(7), function () use ($query, $preferPeople) {
            $timeout = max(2, (int) config('services.smart_images.timeout', 8));
            $response = Http::acceptJson()
                ->withHeaders(['Authorization' => config('services.pexels.api_key')])
                ->connectTimeout(min(4, $timeout))
                ->timeout($timeout)
                ->retry(2, 250, throw: false)
                ->get('https://api.pexels.com/videos/search', [
                    'query' => $query,
                    'orientation' => 'landscape',
                    'size' => 'medium',
                    'per_page' => 15,
                    'page' => 1,
                ]);

            if (! $response->successful()) {
                Log::warning('[LunaVideo] Pexels video search failed.', [
                    'query' => $query,
                    'status' => $response->status(),
                ]);
                return null;
            }

            $candidates = collect($response->json('videos', []))
                ->filter(fn ($video) => is_array($video) && (int) ($video['width'] ?? 0) > (int) ($video['height'] ?? 0))
                ->map(function (array $video) use ($preferPeople) {
                    $files = collect($video['video_files'] ?? [])
                        ->filter(fn ($file) => is_array($file)
                            && str_contains(strtolower((string) ($file['file_type'] ?? '')), 'mp4')
                            && filled($file['link'] ?? null))
                        ->sortByDesc(function ($file) {
                            $width = (int) ($file['width'] ?? 0);
                            $height = (int) ($file['height'] ?? 0);
                            $resolution = $width * $height;
                            $sweetSpot = $width >= 1280 && $width <= 1920 ? 2000000 : 0;
                            return $resolution + $sweetSpot;
                        });

                    $file = $files->first();
                    if (! $file) return null;

                    $duration = (int) ($video['duration'] ?? 0);
                    $score = 0;
                    if ($duration >= 5 && $duration <= 30) $score += 5;
                    elseif ($duration <= 45) $score += 2;
                    if ((int) ($file['width'] ?? 0) >= 1280) $score += 4;
                    if ((int) ($file['width'] ?? 0) <= 1920) $score += 2;
                    if ($preferPeople) $score += 1;

                    return [
                        'url' => (string) $file['link'],
                        'creator' => (string) data_get($video, 'user.name', ''),
                        'source_url' => (string) ($video['url'] ?? ''),
                        'score' => $score,
                    ];
                })
                ->filter()
                ->sortByDesc('score')
                ->values();

            return $candidates->first();
        });
    }

    private function buildQuery(string $prompt, array $block, string $type): string
    {
        $prompt = Str::of($prompt)
            ->replaceMatches('/\s+/', ' ')
            ->replaceMatches('/[^\pL\pN\s\-]/u', ' ')
            ->trim()
            ->limit(150, '')
            ->toString();

        $context = collect([
            $block['eyebrow'] ?? null,
            $block['heading'] ?? null,
            $block['tagline'] ?? null,
        ])->filter()->implode(' ');

        $intent = $type === 'testimonials_video_premium'
            ? 'customer people authentic interview lifestyle'
            : 'cinematic business atmosphere wide shot';

        return Str::of(trim($prompt.' '.$context.' '.$intent))
            ->replaceMatches('/\s+/', ' ')
            ->limit(190, '')
            ->toString();
    }

    private function ensureFallbacks(array $blocks): array
    {
        foreach ($blocks as $index => $block) {
            if (is_array($block) && in_array((string) ($block['type'] ?? ''), self::VIDEO_BLOCKS, true)) {
                $blocks[$index]['video_url'] = filled($block['video_url'] ?? null) ? $block['video_url'] : self::FALLBACK;
                $blocks[$index]['video_source'] = 'local_fallback';
            }
        }
        return $blocks;
    }
}
