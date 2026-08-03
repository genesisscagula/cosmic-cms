<?php

namespace App\AI\Images\Providers;

use App\AI\Images\Contracts\ImageProviderInterface;
use App\AI\Images\DTO\ImageSearchResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class UnsplashProvider implements ImageProviderInterface
{
    public function name(): string
    {
        return 'unsplash';
    }

    public function isEnabled(): bool
    {
        return filled(config('services.unsplash.access_key'));
    }

    public function search(string $query, array $options = []): ?ImageSearchResult
    {
        return $this->searchMany($query, $options)[0] ?? null;
    }

    public function searchMany(string $query, array $options = []): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        $timeout = max(1, (int) config('services.smart_images.timeout', 8));
        $startedAt = microtime(true);

        $response = Http::acceptJson()
            ->withHeaders([
                'Authorization' => 'Client-ID ' . config('services.unsplash.access_key'),
                'Accept-Version' => 'v1',
            ])
            ->connectTimeout(min(4, $timeout))
            ->timeout($timeout)
            ->retry(2, 250, throw: false)
            ->get('https://api.unsplash.com/search/photos', [
                'query' => $query,
                'orientation' => $options['orientation'] ?? 'landscape',
                'content_filter' => 'high',
                'per_page' => 20,
                'page' => 1,
            ]);

        if (! $response->successful()) {
            logger()->warning('[SmartImageProvider] Unsplash search was not successful.', [
                'provider' => $this->name(), 'status' => $response->status(), 'query' => $query,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'body' => Str::limit($response->body(), 300),
            ]);
            return [];
        }

        $results = collect($response->json('results', []))
            ->filter(fn ($photo) => is_array($photo) && filled(data_get($photo, 'urls.raw')))
            ->reject(fn (array $photo) => $this->containsBlockedBranding($photo))
            ->take(20)
            ->map(function (array $photo) {
                $url = data_get($photo, 'urls.raw') ?: data_get($photo, 'urls.regular');
                return new ImageSearchResult(
                    provider: $this->name(),
                    url: (string) $url,
                    description: (string) (data_get($photo, 'alt_description') ?: data_get($photo, 'description') ?: ''),
                    photographer: (string) data_get($photo, 'user.name', ''),
                    sourceUrl: (string) data_get($photo, 'links.html', ''),
                    width: (int) data_get($photo, 'width', 0),
                    height: (int) data_get($photo, 'height', 0),
                    downloadParameters: ['auto'=>'format','fit'=>'crop','crop'=>'entropy','w'=>1600,'h'=>1000,'q'=>82],
                    meta: [
                        'download_location' => data_get($photo, 'links.download_location'),
                        'id' => data_get($photo, 'id'),
                        'slug' => data_get($photo, 'slug'),
                        'tags' => collect(data_get($photo, 'tags', []))->pluck('title')->implode(' '),
                    ],
                );
            })
            ->values()->all();

        logger()->info('[SmartImageProvider] Candidate pool loaded.', [
            'provider' => $this->name(), 'query' => $query, 'candidate_count' => count($results),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
        return $results;
    }

    public function trackDownload(ImageSearchResult $result): void
    {
        $downloadLocation = $result->meta['download_location'] ?? null;

        if (! is_string($downloadLocation) || $downloadLocation === '' || ! $this->isEnabled()) {
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
            logger()->notice('[SmartImageProvider] Unsplash download tracking failed.', [
                'provider' => $this->name(),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function containsBlockedBranding(array $photo): bool
    {
        $metadata = strtolower(implode(' ', array_filter([
            data_get($photo, 'alt_description'),
            data_get($photo, 'description'),
            data_get($photo, 'slug'),
            collect(data_get($photo, 'tags', []))->pluck('title')->implode(' '),
        ])));

        $blockedTerms = [
            'adobe', 'apple', 'canva', 'figma', 'google', 'microsoft', 'shopify',
            'squarespace', 'webflow', 'wix', 'wordpress', 'logo', 'watermark',
            'brand identity', 'branded interface', 'app screenshot', 'website builder',
        ];

        foreach ($blockedTerms as $term) {
            if (str_contains($metadata, $term)) {
                return true;
            }
        }

        return false;
    }
}
