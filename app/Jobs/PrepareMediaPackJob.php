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

    public int $tries = 2;
    public int $timeout = 180;

    public function __construct(public readonly int $mediaPackId)
    {
        $this->onQueue('images');
    }

    public function handle(MediaPackImageService $images): void
    {
        $pack = MediaPack::query()->with(['trialGeneration.page', 'website'])->find($this->mediaPackId);
        if (! $pack) {
            return;
        }

        $lock = Cache::lock('media-pack:prepare:'.$pack->id, 240);
        if (! $lock->get()) {
            Log::info('[MediaPack] Duplicate preparation job skipped.', [
                'media_pack_id' => $pack->id,
            ]);
            return;
        }

        try {
            $this->prepare($pack, $images);
        } finally {
            optional($lock)->release();
        }
    }

    private function prepare(MediaPack $pack, MediaPackImageService $images): void
    {
        $existingCount = count($images->existingImages($pack));
        if ($pack->status === 'ready' && $existingCount >= (int) $pack->target_image_count) {
            return;
        }

        $pack->forceFill(['status' => 'downloading', 'last_error' => null])->save();

        try {
            $manifestImages = $images->populate($pack);
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
