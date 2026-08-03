<?php

namespace App\AI\Images\Providers;

use App\AI\Images\Contracts\ImageProviderInterface;
use App\AI\Images\DTO\ImageSearchResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class PixabayProvider implements ImageProviderInterface
{
    public function name(): string
    {
        return 'pixabay';
    }

    public function isEnabled(): bool
    {
        return filled(config('services.pixabay.api_key'));
    }

    public function search(string $query, array $options = []): ?ImageSearchResult
    {
        return $this->searchMany($query, $options)[0] ?? null;
    }

    public function searchMany(string $query, array $options = []): array
    {
        if (! $this->isEnabled()) return [];
        $timeout = max(1, (int) config('services.smart_images.timeout', 8));
        $startedAt = microtime(true);
        $response = Http::acceptJson()->connectTimeout(min(4,$timeout))->timeout($timeout)->retry(2,250,throw:false)
            ->get('https://pixabay.com/api/', ['key'=>config('services.pixabay.api_key'),'q'=>$query,'image_type'=>'photo','orientation'=>$this->orientation($options['orientation'] ?? 'landscape'),'safesearch'=>'true','order'=>'popular','per_page'=>20,'page'=>1]);
        if (! $response->successful()) {
            logger()->warning('[SmartImageProvider] Pixabay search was not successful.', ['provider'=>$this->name(),'status'=>$response->status(),'query'=>$query,'duration_ms'=>(int) round((microtime(true)-$startedAt)*1000),'body'=>Str::limit($response->body(),300)]);
            return [];
        }
        $results=collect($response->json('hits',[]))
            ->filter(fn($item)=>is_array($item)&&filled(data_get($item,'largeImageURL',data_get($item,'webformatURL'))))
            ->reject(fn(array $item)=>$this->containsBlockedBranding($item))->take(20)
            ->map(function(array $photo){$url=data_get($photo,'largeImageURL')?:data_get($photo,'webformatURL'); return new ImageSearchResult(provider:$this->name(),url:(string)$url,description:(string)data_get($photo,'tags',''),photographer:(string)data_get($photo,'user',''),sourceUrl:(string)data_get($photo,'pageURL',''),width:(int)data_get($photo,'imageWidth',0),height:(int)data_get($photo,'imageHeight',0),meta:['id'=>data_get($photo,'id'),'user_id'=>data_get($photo,'user_id'),'tags'=>data_get($photo,'tags','')]);})->values()->all();
        logger()->info('[SmartImageProvider] Candidate pool loaded.', ['provider'=>$this->name(),'query'=>$query,'candidate_count'=>count($results),'duration_ms'=>(int) round((microtime(true)-$startedAt)*1000)]);
        return $results;
    }

    public function trackDownload(ImageSearchResult $result): void
    {
        // Pixabay does not require a separate download-tracking request.
    }

    private function orientation(string $orientation): string
    {
        return match (strtolower($orientation)) {
            'portrait' => 'vertical',
            default => 'horizontal',
        };
    }

    private function containsBlockedBranding(array $photo): bool
    {
        $metadata = strtolower(implode(' ', array_filter([
            data_get($photo, 'tags'),
            data_get($photo, 'pageURL'),
        ])));

        foreach ($this->blockedTerms() as $term) {
            if (str_contains($metadata, $term)) {
                return true;
            }
        }

        return false;
    }

    private function blockedTerms(): array
    {
        return [
            'adobe', 'apple', 'canva', 'figma', 'google', 'microsoft', 'shopify',
            'squarespace', 'webflow', 'wix', 'wordpress', 'logo', 'watermark',
            'brand identity', 'branded interface', 'app screenshot', 'website builder',
        ];
    }
}
