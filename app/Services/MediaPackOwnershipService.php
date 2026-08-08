<?php

namespace App\Services;

use App\Jobs\PrepareMediaPackJob;
use App\Models\MediaPack;
use App\Models\TrialGeneration;
use App\Models\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MediaPackOwnershipService
{
    public function claimForWebsite(?TrialGeneration $trial, Website $website): ?MediaPack
    {
        if (! $trial) {
            return null;
        }

        $pack = $trial->mediaPack()->lockForUpdate()->first();
        if (! $pack) {
            return null;
        }

        $manifest = is_array($pack->manifest) ? $pack->manifest : [];
        $hasRemoteTrialAssets = (bool) ($manifest['remote_only'] ?? false)
            && collect($manifest['images'] ?? [])->contains(
                fn ($image) => is_array($image) && filter_var($image['url'] ?? null, FILTER_VALIDATE_URL)
            );

        $pack->forceFill([
            'owner_type' => 'website',
            'owner_id' => $website->id,
            'website_id' => $website->id,
            'trial_generation_id' => $trial->id,
            'status' => $hasRemoteTrialAssets && config('cosmic_media.localize_remote_images', false) ? 'queued' : 'ready',
            'queued_at' => $hasRemoteTrialAssets && config('cosmic_media.localize_remote_images', false) ? now() : null,
            'completed_at' => $hasRemoteTrialAssets && config('cosmic_media.localize_remote_images', false) ? null : now(),
            'last_error' => null,
        ])->save();

        if ($hasRemoteTrialAssets && config('cosmic_media.localize_remote_images', false)) {
            // Provisioning runs inside a transaction. Dispatch only after commit so
            // the worker always sees the transferred website/page ownership.
            DB::afterCommit(function () use ($pack): void {
                PrepareMediaPackJob::dispatch($pack->id);

                Log::info('[MediaPack] Purchase asset capture dispatched.', [
                    'media_pack_id' => $pack->id,
                    'queue' => (string) config('openai.media_pack_queue', 'images-high'),
                ]);
            });
        } elseif ($hasRemoteTrialAssets) {
            Log::info('[MediaPack] Remote provider images retained after purchase; localization disabled.', [
                'media_pack_id' => $pack->id,
                'website_id' => $website->id,
            ]);
        }

        Log::info('[MediaPack] Ownership transferred to website.', [
            'media_pack_id' => $pack->id,
            'media_pack_uuid' => $pack->uuid,
            'trial_generation_id' => $trial->id,
            'website_id' => $website->id,
        ]);

        return $pack->fresh();
    }

    /**
     * Return the website-owned pack, creating one only for legacy/existing
     * websites that pre-date the media-pack flow. No folder move is required.
     */
    public function ensureForWebsite(Website $website, array $keywords = [], int $targetImageCount = 0): MediaPack
    {
        $existing = $website->mediaPack()->first();
        if ($existing) {
            return $existing;
        }

        $pack = MediaPack::query()->create([
            'uuid' => (string) Str::uuid(),
            'owner_type' => 'website',
            'owner_id' => $website->id,
            'website_id' => $website->id,
            'status' => 'pending',
            'target_image_count' => max(0, min(10, $targetImageCount)),
            'keywords' => collect($keywords)
                ->filter(fn ($keyword) => is_string($keyword) && trim($keyword) !== '')
                ->map(fn ($keyword) => trim($keyword))
                ->unique()
                ->take(8)
                ->values()
                ->all(),
            'queued_at' => null,
        ]);

        Log::info('[MediaPack] Website pack created for legacy website.', [
            'media_pack_id' => $pack->id,
            'media_pack_uuid' => $pack->uuid,
            'website_id' => $website->id,
        ]);

        return $pack;
    }

    public function deletePack(MediaPack $pack): void
    {
        $directory = storage_path('app/public/'.$pack->storageDirectory());

        if (File::isDirectory($directory)) {
            File::deleteDirectory($directory);
        }

        $pack->delete();
    }
}
