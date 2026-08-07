<?php

namespace App\Jobs;

use App\Models\MediaPack;
use App\Services\MediaPackImageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PrepareMediaPackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;
    public int $timeout = 150;

    public function __construct(public readonly int $mediaPackId)
    {
        $this->timeout = max(30, (int) config('cosmic-queue.performance.image_job_timeout', 150));
        $this->onQueue((string) config('openai.media_pack_queue', 'images-high'));
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return array_values(config('cosmic-queue.performance.image_retry_backoff', [2, 5, 10, 20]));
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(max(2, (int) config('cosmic-queue.performance.image_job_retry_window_minutes', 10)));
    }

    public function handle(MediaPackImageService $images): void
    {
        $pack = MediaPack::query()->with(['trialGeneration.page', 'website'])->find($this->mediaPackId);
        if (! $pack) {
            return;
        }

        $lock = Cache::lock('media-pack:prepare:'.$pack->id, 240);
        if (! $lock->get()) {
            Log::info('[MediaPack] Preparation already running; job released for retry.', [
                'media_pack_id' => $pack->id,
                'attempt' => $this->attempts(),
            ]);
            $this->release(2);
            return;
        }

        try {
            $this->prepare($pack, $images);
        } finally {
            optional($lock)->release();
        }
    }

    private function localizeRemoteAssets(MediaPack $pack, MediaPackImageService $images): void
    {
        $pack->forceFill(['status' => 'localizing', 'last_error' => null])->save();

        try {
            $capture = $images->localizeRemoteTrialAssets($pack);
            $localized = $capture['images'];
            $replacements = $capture['replacements'];

            // Fill any failed remote downloads with the existing provider/local
            // fallback chain, keeping purchase completion resilient.
            if (count($localized) < (int) $pack->target_image_count) {
                $localized = $images->populate($pack);
            }

            $pack->refresh()->loadMissing(['trialGeneration.page', 'website']);

            DB::transaction(function () use ($pack, $images, $localized, $replacements): void {
                if ($pack->website_id && $pack->website) {
                    $pages = $pack->website->pages()->lockForUpdate()->get();
                    foreach ($pages as $page) {
                        $blocks = $images->replaceRemoteUrls($page->blocks ?? [], $replacements);
                        // Any exact remote download that failed is replaced from
                        // the localized fallback pool, so paid pages never retain
                        // third-party trial URLs after capture completes.
                        $blocks = $images->replaceExternalImageUrls($blocks, $localized);
                        if ($blocks !== ($page->blocks ?? [])) {
                            $page->update(['blocks' => $blocks]);
                        }
                    }
                }

                $trial = $pack->trialGeneration;
                if ($trial?->page) {
                    $page = $trial->page()->lockForUpdate()->first();
                    if ($page) {
                        $blocks = $images->replaceRemoteUrls($page->blocks ?? [], $replacements);
                        $blocks = $images->replaceExternalImageUrls($blocks, $localized);
                        $page->update(['blocks' => $blocks]);
                        $trial->update(['generated_blocks' => $blocks]);
                    }
                }
            });

            $imageCount = count($localized);
            $target = (int) $pack->target_image_count;
            $status = $imageCount >= $target ? 'ready' : ($imageCount > 0 ? 'partial' : 'failed');

            $pack->forceFill([
                'status' => $status,
                'completed_at' => now(),
                'manifest' => [
                    'mode' => 'owned_local_assets',
                    'remote_only' => false,
                    'path' => $pack->storageDirectory().'/manifest.json',
                    'image_count' => $imageCount,
                    'target_image_count' => $target,
                    'localized_at' => now()->toIso8601String(),
                ],
                'last_error' => $status === 'failed' ? 'Remote preview assets could not be localized.' : null,
            ])->save();

            Log::info('[MediaPack] Remote asset localization completed.', [
                'media_pack_id' => $pack->id,
                'website_id' => $pack->website_id,
                'status' => $status,
                'image_count' => $imageCount,
                'target_image_count' => $target,
                'exact_replacements' => count($replacements),
            ]);
        } catch (\Throwable $exception) {
            $pack->forceFill([
                'status' => 'failed',
                'completed_at' => now(),
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ])->save();

            Log::error('[MediaPack] Remote asset localization failed.', [
                'media_pack_id' => $pack->id,
                'website_id' => $pack->website_id,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function assignExistingImages(MediaPack $pack, MediaPackImageService $images, array $manifestImages): void
    {
        $trial = $pack->trialGeneration;

        if ($trial?->page) {
            DB::transaction(function () use ($images, $manifestImages, $trial): void {
                $page = $trial->page()->lockForUpdate()->firstOrFail();
                $updatedBlocks = $images->assignToBlocks($page->blocks ?? [], $manifestImages);
                $page->update(['blocks' => $updatedBlocks]);
                $trial->update(['generated_blocks' => $updatedBlocks]);
            });
        }

        if ($pack->website_id) {
            DB::transaction(function () use ($images, $manifestImages, $pack): void {
                $pages = $pack->website->pages()->lockForUpdate()->get();
                foreach ($pages as $page) {
                    $updatedBlocks = $images->assignToBlocks($page->blocks ?? [], $manifestImages);
                    if ($updatedBlocks !== ($page->blocks ?? [])) {
                        $page->update(['blocks' => $updatedBlocks]);
                    }
                }
            });
        }
    }

    private function prepare(MediaPack $pack, MediaPackImageService $images): void
    {
        $remoteManifest = is_array($pack->manifest) ? $pack->manifest : [];
        $hasRemotePreviewAssets = (bool) ($remoteManifest['remote_only'] ?? false)
            && collect($remoteManifest['images'] ?? [])->contains(
                fn ($item) => is_array($item) && filter_var($item['url'] ?? null, FILTER_VALIDATE_URL)
            );

        if ($hasRemotePreviewAssets) {
            $pack->loadMissing(['trialGeneration.page', 'website']);
            $hasOwner = (bool) $pack->website_id || (bool) $pack->trialGeneration?->page;
            if ($hasOwner) {
                $this->localizeRemoteAssets($pack, $images);
                return;
            }
        }

        $existingCount = count($images->existingImages($pack));
        $pack->loadMissing(['trialGeneration.page', 'website']);
        $hasAssignableOwner = (bool) $pack->website_id || (bool) $pack->trialGeneration?->page;

        // An early 16.2 job may finish downloading before the trial page exists.
        // Do not short-circuit the later post-persist job: it still needs to assign
        // those already-downloaded images into the generated block payload.
        if ($pack->status === 'ready'
            && $existingCount >= (int) $pack->target_image_count
            && $hasAssignableOwner) {
            $this->assignExistingImages($pack, $images, $images->existingImages($pack));
            return;
        }

        $pack->forceFill(['status' => 'downloading', 'last_error' => null])->save();

        try {
            $manifestImages = $images->populate($pack);
            $pack->refresh()->loadMissing(['trialGeneration.page', 'website']);
            $this->assignExistingImages($pack, $images, $manifestImages);

            $pack->refresh();
            $imageCount = count($manifestImages);
            $target = (int) $pack->target_image_count;
            $status = $imageCount >= $target ? 'ready' : ($imageCount > 0 ? 'partial' : 'failed');

            $pack->forceFill([
                'status' => $status,
                'completed_at' => now(),
                'manifest' => [
                    'path' => $pack->storageDirectory().'/manifest.json',
                    'image_count' => $imageCount,
                    'target_image_count' => $target,
                ],
                'last_error' => $status === 'failed' ? 'No provider image could be downloaded.' : null,
            ])->save();

            Log::info('[MediaPack] Pack preparation completed.', [
                'media_pack_id' => $pack->id,
                'status' => $status,
                'image_count' => $imageCount,
                'target_image_count' => $target,
            ]);
        } catch (\Throwable $exception) {
            // A provider or unexpected job error must never leave loading SVGs in
            // the Builder. Make one final local-only pass using the controlled
            // industry folder and then the default folder.
            try {
                $fallbackImages = $images->fallback($pack);
                $trial = $pack->trialGeneration;

                if ($trial?->page && $fallbackImages !== []) {
                    DB::transaction(function () use ($images, $fallbackImages, $trial): void {
                        $page = $trial->page()->lockForUpdate()->firstOrFail();
                        $updatedBlocks = $images->assignToBlocks($page->blocks ?? [], $fallbackImages);
                        $page->update(['blocks' => $updatedBlocks]);
                        $trial->update(['generated_blocks' => $updatedBlocks]);
                    });
                }

                if ($pack->website_id && $fallbackImages !== []) {
                    DB::transaction(function () use ($images, $fallbackImages, $pack): void {
                        $pages = $pack->website->pages()->lockForUpdate()->get();
                        foreach ($pages as $page) {
                            $updatedBlocks = $images->assignToBlocks($page->blocks ?? [], $fallbackImages);
                            if ($updatedBlocks !== ($page->blocks ?? [])) {
                                $page->update(['blocks' => $updatedBlocks]);
                            }
                        }
                    });
                }

                $count = count($fallbackImages);
                $target = (int) $pack->target_image_count;
                $status = $count >= max(1, $target) ? 'ready' : ($count > 0 ? 'partial' : 'failed');
                $pack->forceFill([
                    'status' => $status,
                    'completed_at' => now(),
                    'manifest' => [
                        'path' => $pack->storageDirectory().'/manifest.json',
                        'image_count' => $count,
                        'target_image_count' => $target,
                    ],
                    'last_error' => $exception->getMessage(),
                ])->save();

                Log::warning('[MediaPack] Provider pipeline failed; local fallback finalized the pack.', [
                    'media_pack_id' => $pack->id,
                    'status' => $status,
                    'image_count' => $count,
                    'message' => $exception->getMessage(),
                ]);
            } catch (\Throwable $fallbackException) {
                $pack->forceFill([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'last_error' => $fallbackException->getMessage(),
                ])->save();

                Log::error('[MediaPack] Pack and local fallback both failed.', [
                    'media_pack_id' => $pack->id,
                    'provider_error' => $exception->getMessage(),
                    'fallback_error' => $fallbackException->getMessage(),
                ]);
            }
        }
    }
}
