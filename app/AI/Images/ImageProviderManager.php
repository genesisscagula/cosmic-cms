<?php

namespace App\AI\Images;

use App\AI\Images\Contracts\ImageProviderInterface;
use App\AI\Images\DTO\ImageSearchResult;
use App\AI\Images\Providers\PexelsProvider;
use App\AI\Images\Providers\PixabayProvider;
use App\AI\Images\Providers\UnsplashProvider;
use Illuminate\Support\Str;

final class ImageProviderManager
{
    /** @var array<string, ImageProviderInterface> */
    private array $providers;

    public function __construct(private readonly ImageRankingEngine $rankingEngine)
    {
        $this->providers = collect([new UnsplashProvider(), new PexelsProvider(), new PixabayProvider()])
            ->keyBy(fn (ImageProviderInterface $provider) => $provider->name())->all();
    }

    public function hasEnabledProvider(): bool
    {
        return collect($this->orderedProviders())->contains(fn ($provider) => $provider->isEnabled());
    }

    public function search(string $query, array $options = []): ?ImageSearchResult
    {
        foreach ($this->searchCandidates($query, $options) as $result) return $result;
        return null;
    }

    /** @return \Generator<int, ImageSearchResult> */
    public function searchCandidates(string $query, array $options = []): \Generator
    {
        foreach ($this->orderedProviders() as $provider) {
            if (! $provider->isEnabled()) {
                logger()->debug('[SmartImageProvider] Provider skipped.', ['provider'=>$provider->name(),'reason'=>'disabled_or_missing_credentials']);
                continue;
            }
            try {
                $pool = $provider->searchMany($query, $options);
                $result = $this->rankingEngine->best($pool, $query, $options);
                if ($result) { yield $result; continue; }
                logger()->info('[SmartImageProvider] Continuing to next provider.', ['provider'=>$provider->name(),'query'=>$query,'reason'=>'no_candidate_above_minimum_score']);
            } catch (\Throwable $exception) {
                logger()->warning('[SmartImageProvider] Provider search failed.', ['provider'=>$provider->name(),'query'=>$query,'message'=>$exception->getMessage()]);
            }
        }
    }

    public function trackDownload(ImageSearchResult $result): void
    {
        $provider = $this->providers[$result->provider] ?? null;
        if ($provider instanceof ImageProviderInterface) $provider->trackDownload($result);
    }

    /** @return array<int, ImageProviderInterface> */
    private function orderedProviders(): array
    {
        $configured = config('services.smart_images.providers', ['unsplash']);
        if (is_string($configured)) $configured = explode(',', $configured);
        $names = collect(is_array($configured) ? $configured : ['unsplash'])->map(fn($name)=>Str::lower(trim((string)$name)))->filter()->unique()->values();
        if ($names->isEmpty()) $names = collect([Str::lower(trim((string) config('services.smart_images.provider','unsplash'))) ?: 'unsplash']);
        return $names->map(fn($name)=>$this->providers[$name] ?? null)->filter(fn($provider)=>$provider instanceof ImageProviderInterface)->values()->all();
    }
}
