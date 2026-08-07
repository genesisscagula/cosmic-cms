<?php

namespace App\Services;

use App\Jobs\PrepareMediaPackJob;
use App\Models\MediaPack;
use App\Models\TrialGeneration;
use App\Models\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MediaAssetLifecycleService
{
    /**
     * Persist the exact remote preview URLs currently present in Builder blocks.
     * Existing provider metadata is preserved whenever possible so localization
     * can later download the same image the customer approved in preview.
     */
    public function syncRemoteManifest(MediaPack $pack, array $blocks, string $mode = 'remote_builder_preview', array $sourceImages = []): MediaPack
    {
        $existingManifest = is_array($pack->manifest) ? $pack->manifest : [];
        $metadataByUrl = collect($existingManifest['images'] ?? [])
            ->merge($sourceImages)
            ->filter(fn ($item) => is_array($item) && is_string($item['url'] ?? null))
            ->keyBy(fn ($item) => (string) $item['url']);

        $remoteUrls = $this->remoteImageUrls($blocks);
        $images = collect($remoteUrls)->map(function (string $url) use ($metadataByUrl) {
            $existing = $metadataByUrl->get($url);
            if (is_array($existing)) {
                return array_merge($existing, ['url' => $url, 'remote' => true]);
            }

            return [
                'url' => $url,
                'provider' => $this->providerFromUrl($url),
                'source_url' => $url,
                'query' => '',
                'remote' => true,
            ];
        })->values()->all();

        $target = min(10, count($images));
        $pack->forceFill([
            'target_image_count' => $target,
            'status' => 'ready',
            'manifest' => array_merge($existingManifest, [
                'mode' => $mode,
                'remote_only' => $target > 0,
                'image_count' => $target,
                'target_image_count' => $target,
                'images' => $images,
                'updated_at' => now()->toIso8601String(),
            ]),
            'completed_at' => now(),
            'last_error' => null,
        ])->save();

        return $pack->fresh();
    }

    public function syncWebsiteRemoteManifest(Website $website, array $blocks, array $keywords = []): MediaPack
    {
        $pack = $website->mediaPack()->first();
        if (! $pack) {
            $pack = MediaPack::query()->create([
                'uuid' => (string) Str::uuid(),
                'owner_type' => 'website',
                'owner_id' => $website->id,
                'website_id' => $website->id,
                'status' => 'ready',
                'target_image_count' => 0,
                'keywords' => [],
            ]);
        }

        $mergedKeywords = collect($pack->keywords ?? [])
            ->merge($keywords)
            ->filter(fn ($keyword) => is_string($keyword) && trim($keyword) !== '')
            ->map(fn ($keyword) => trim($keyword))
            ->unique()
            ->take(8)
            ->values()
            ->all();

        $pack->forceFill(['keywords' => $mergedKeywords])->save();

        return $this->syncRemoteManifest($pack, $blocks, 'remote_logged_in_preview');
    }

    public function queueTrialSave(TrialGeneration $trial): bool
    {
        $pack = $trial->mediaPack;
        $page = $trial->page;
        if (! $pack || ! $page) {
            return false;
        }

        if (in_array($pack->status, ['queued', 'downloading', 'localizing'], true)) {
            return true;
        }

        $pack = $this->syncRemoteManifest($pack, $page->blocks ?? [], 'remote_trial_saved');

        return $this->queueLocalization($pack, 'trial_save');
    }

    public function queueWebsitePublish(Website $website): bool
    {
        // Re-sync across the website before publishing. The DB is canonical, and
        // a MediaPack may not exist yet for manually-created or older websites.
        $blocks = $website->pages()->get(['blocks'])->pluck('blocks')->filter()->values()->all();
        $remoteUrls = collect($blocks)->flatMap(fn ($blockSet) => $this->remoteImageUrls((array) $blockSet))->unique()->values();

        $pack = $website->mediaPack;
        if (! $pack && $remoteUrls->isEmpty()) {
            return false;
        }

        if (! $pack) {
            $pack = $this->syncWebsiteRemoteManifest($website, $blocks);
        }

        if (in_array($pack->status, ['queued', 'downloading', 'localizing'], true)) {
            return true;
        }

        $pack = $this->syncRemoteManifest($pack, $blocks, 'remote_logged_in_publish');

        return $this->queueLocalization($pack, 'publish');
    }

    public function queueLocalization(MediaPack $pack, string $reason, bool $force = false): bool
    {
        $manifest = is_array($pack->manifest) ? $pack->manifest : [];
        $hasRemote = collect($manifest['images'] ?? [])->contains(
            fn ($item) => is_array($item) && filter_var($item['url'] ?? null, FILTER_VALIDATE_URL)
        );

        if (! ($manifest['remote_only'] ?? false) || ! $hasRemote) {
            return false;
        }

        if (! $force && in_array($pack->status, ['queued', 'downloading', 'localizing'], true)) {
            return true;
        }

        $pack->forceFill([
            'status' => 'queued',
            'queued_at' => now(),
            'completed_at' => null,
            'last_error' => null,
            'manifest' => array_merge($manifest, [
                'localization_status' => 'queued',
                'localization_reason' => $reason,
                'localization_queued_at' => now()->toIso8601String(),
            ]),
        ])->save();

        $dispatch = function () use ($pack, $reason): void {
            PrepareMediaPackJob::dispatch($pack->id);
            Log::info('[MediaAssets] Localization dispatched.', [
                'media_pack_id' => $pack->id,
                'owner_type' => $pack->owner_type,
                'owner_id' => $pack->owner_id,
                'reason' => $reason,
                'queue' => (string) config('openai.media_pack_queue', 'images-high'),
            ]);
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($dispatch);
        } else {
            $dispatch();
        }

        return true;
    }

    /** @return array<int, string> */
    public function remoteImageUrls(array $blocks): array
    {
        $urls = [];
        $walk = function (mixed $value, ?string $key = null) use (&$walk, &$urls): void {
            if (is_array($value)) {
                foreach ($value as $childKey => $child) {
                    $walk($child, is_string($childKey) ? $childKey : null);
                }
                return;
            }

            if (! is_string($value) || ! is_string($key) || ! $this->isImageField($key)) {
                return;
            }

            if (! filter_var($value, FILTER_VALIDATE_URL)) {
                return;
            }

            if (! str_starts_with($value, 'http://') && ! str_starts_with($value, 'https://')) {
                return;
            }

            $urls[] = $value;
        };

        $walk($blocks);

        return collect($urls)->unique()->take(10)->values()->all();
    }

    private function providerFromUrl(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (str_contains($host, 'unsplash')) return 'unsplash';
        if (str_contains($host, 'pexels')) return 'pexels';
        return 'remote';
    }

    private function isImageField(string $key): bool
    {
        $normalized = strtolower($key);

        return str_contains($normalized, 'image')
            || str_contains($normalized, 'photo')
            || str_contains($normalized, 'poster');
    }
}
