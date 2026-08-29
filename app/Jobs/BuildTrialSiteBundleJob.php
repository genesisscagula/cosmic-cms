<?php

namespace App\Jobs;

use App\Models\Page;
use App\Models\TrialGeneration;
use App\Services\AiPageGenerationService;
use App\Services\LunaPexelsVideoService;
use App\Services\TrialStagingPublisherService;
use App\Jobs\SendTrialBundleReadyJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class BuildTrialSiteBundleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 420;
    public array $backoff = [30, 90, 180];

    public function __construct(public int $trialId, public int $pageId)
    {
        // Public trial pages are intentionally asynchronous even when a local
        // environment keeps QUEUE_CONNECTION=sync for unrelated jobs.
        $this->onConnection('database');
        $this->onQueue((string) config('cosmic-queue.queues.ai_builds', 'ai'));
        $this->afterCommit();
    }

    public function handle(AiPageGenerationService $generator, LunaPexelsVideoService $videos, TrialStagingPublisherService $staging): void
    {
        $trial = TrialGeneration::query()->find($this->trialId);
        if (! $trial || $trial->claimed_at || $trial->status !== 'ready') {
            return;
        }

        $trial->forceFill(['bundle_status' => 'building', 'bundle_error' => null])->save();
        $recipe = collect((array) data_get($trial->bundle_manifest, 'pages', []))
            ->first(fn (array $page) => (int) ($page['page_id'] ?? 0) === $this->pageId);
        if (! is_array($recipe)) {
            return;
        }

        $page = Page::query()->where('website_id', $trial->website_id)->find($this->pageId);
        if (! $page) {
            $this->markPage($trial->id, $this->pageId, 'failed', 'Page record is missing.');
            $this->refreshBundleStatus($trial->id);
            return;
        }
        if (is_array($page->blocks) && $page->blocks !== []) {
            $this->markPage($trial->id, $page->id, 'ready');
            $status = $this->refreshBundleStatus($trial->id);
            if ($status === 'ready') {
                try { $staging->publish($trial->fresh()); } catch (Throwable $exception) { report($exception); }
                SendTrialBundleReadyJob::dispatch($trial->id);
            } else {
                $this->dispatchNextQueuedPage($trial->id);
            }
            return;
        }

        try {
            $this->markPage($trial->id, $page->id, 'building');
            $prompt = $this->pagePrompt($trial, $recipe);
            $blocks = $generator->imagesWithoutRemoteDownloads(
                fn () => $generator->generateBlocks($prompt, array_values($recipe['sections'] ?? []))
            );
            $blocks = $videos->apply($prompt, $blocks);
            $remote = $generator->applyStartPageRemoteImages($prompt, $blocks);
            $blocks = array_values($remote['blocks'] ?? $blocks);

            DB::transaction(function () use ($trial, $page, $blocks, $remote): void {
                $lockedPage = Page::query()->lockForUpdate()->findOrFail($page->id);
                if (! is_array($lockedPage->blocks) || $lockedPage->blocks === []) {
                    $lockedPage->forceFill([
                        'blocks' => $blocks,
                        'status' => 'draft',
                        'publish_error' => null,
                    ])->save();
                }
                $this->mergeMedia($trial->id, $remote);
                $this->markPage($trial->id, $page->id, 'ready');
            });
        } catch (Throwable $exception) {
            report($exception);
            $this->markPage($trial->id, $page->id, 'failed', $exception->getMessage());
            throw $exception;
        }

        $bundleStatus=$this->refreshBundleStatus($trial->id);
        $fresh = TrialGeneration::query()->find($trial->id);
        if ($bundleStatus === 'ready' && $fresh) {
            try { $staging->publish($fresh); } catch (Throwable $exception) { report($exception); }
            SendTrialBundleReadyJob::dispatch($trial->id);
        } elseif (in_array($bundleStatus, ['building', 'queued', 'partial'], true)) {
            $this->dispatchNextQueuedPage($trial->id);
        }
        $statuses = collect(data_get($fresh?->bundle_manifest, 'pages', []))->pluck('build_status');

        Log::info('[TrialSiteBundle] Background bundle build finished.', [
            'trial_id' => $trial->id,
            'bundle_key' => data_get($trial->bundle_manifest, 'bundle_key'),
            'page_id' => $page->id,
            'ready' => $statuses->filter(fn ($status) => $status === 'ready')->count(),
            'total' => $statuses->count(),
        ]);
    }

    private function pagePrompt(TrialGeneration $trial, array $recipe): string
    {
        return "Build one page in an existing multi-page website.\n\n"
            ."Business Name: {$trial->business_name}\n"
            ."Industry: {$trial->industry}\n"
            ."Location: {$trial->location}\n"
            ."Business brief: {$trial->business_description}\n"
            ."Original user request: {$trial->latest_user_prompt}\n"
            ."Current page: {$recipe['title']}\n"
            ."Page purpose: {$recipe['page_intent']}\n"
            ."Selected site bundle: ".data_get($trial->bundle_manifest, 'bundle_name')."\n"
            ."Existing website brand constraint: preserve the Home page theme, typography, spacing rhythm and visual tone. "
            ."Write content only for this page and do not duplicate the Home page narrative. "
            ."Do not invent awards, certifications, employee names, statistics, addresses or claims.";
    }

    private function markPage(int $trialId, int $pageId, string $status, ?string $error = null): void
    {
        $trial = TrialGeneration::query()->lockForUpdate()->find($trialId);
        if (! $trial) {
            return;
        }

        $manifest = is_array($trial->bundle_manifest) ? $trial->bundle_manifest : [];
        $manifest['pages'] = collect($manifest['pages'] ?? [])->map(function (array $page) use ($pageId, $status, $error): array {
            if ((int) ($page['page_id'] ?? 0) !== $pageId) {
                return $page;
            }
            $page['build_status'] = $status;
            $page['built_at'] = $status === 'ready' ? now()->toIso8601String() : ($page['built_at'] ?? null);
            $page['build_error'] = $error;
            return $page;
        })->values()->all();
        $trial->forceFill(['bundle_manifest' => $manifest])->save();
    }

    private function mergeMedia(int $trialId, array $remote): void
    {
        $trial = TrialGeneration::query()->with('mediaPack')->find($trialId);
        $pack = $trial?->mediaPack;
        if (! $pack) {
            return;
        }

        $manifest = is_array($pack->manifest) ? $pack->manifest : [];
        $images = collect([...(array) ($manifest['images'] ?? []), ...(array) ($remote['remote_images'] ?? [])])
            ->filter(fn ($image) => is_array($image) && filled($image['url'] ?? null))
            ->unique('url')
            ->values()
            ->all();
        $target = min(100, max(count($images), (int) ($pack->target_image_count ?? 0) + (int) ($remote['target_image_count'] ?? 0)));

        $pack->forceFill([
            'target_image_count' => $target,
            'manifest' => [
                ...$manifest,
                'mode' => 'remote_trial_bundle',
                'provider' => 'unsplash',
                'remote_only' => true,
                'image_count' => count($images),
                'target_image_count' => $target,
                'images' => $images,
                'updated_at' => now()->toIso8601String(),
            ],
        ])->save();
    }


    private function dispatchNextQueuedPage(int $trialId): void
    {
        $nextPageId = DB::transaction(function () use ($trialId): ?int {
            $trial = TrialGeneration::query()->lockForUpdate()->find($trialId);
            if (! $trial || $trial->claimed_at) {
                return null;
            }

            $manifest = is_array($trial->bundle_manifest) ? $trial->bundle_manifest : [];
            $pages = collect($manifest['pages'] ?? [])->sortBy('sort_order');
            $next = $pages->first(fn (array $item) => ($item['build_status'] ?? null) === 'queued'
                && (int) ($item['page_id'] ?? 0) > 0);
            if (! is_array($next)) {
                return null;
            }

            $pageId = (int) $next['page_id'];
            $manifest['pages'] = collect($manifest['pages'] ?? [])->map(function (array $item) use ($pageId): array {
                if ((int) ($item['page_id'] ?? 0) === $pageId) {
                    $item['build_status'] = 'scheduled';
                    $item['scheduled_at'] = now()->toIso8601String();
                    $item['build_error'] = null;
                }
                return $item;
            })->values()->all();
            $trial->forceFill(['bundle_manifest' => $manifest, 'bundle_status' => 'building'])->save();

            return $pageId;
        });

        if ($nextPageId) {
            $delayMinutes = max(0, (int) config('cosmic.trial_inner_page_delay_minutes', 2));
            self::dispatch($trialId, $nextPageId)
                ->delay(now()->addMinutes($delayMinutes));
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->markPage($this->trialId, $this->pageId, 'failed', $exception?->getMessage() ?: 'Page generation failed.');
        $this->refreshBundleStatus($this->trialId);
        $this->dispatchNextQueuedPage($this->trialId);
    }

    private function refreshBundleStatus(int $trialId): string
    {
        $trial = TrialGeneration::query()->lockForUpdate()->find($trialId);
        if (! $trial) {
            return 'missing';
        }

        $pages = collect(data_get($trial->bundle_manifest, 'pages', []));
        $statuses = $pages->pluck('build_status');
        $errors = $pages->pluck('build_error')->filter()->take(5)->implode("\n");
        $status = match (true) {
            $statuses->every(fn ($value) => $value === 'ready') => 'ready',
            $statuses->contains(fn ($value) => in_array($value, ['queued', 'scheduled', 'building'], true)) => 'building',
            $statuses->contains('ready') => 'partial',
            default => 'failed',
        };

        $trial->forceFill([
            'bundle_status' => $status,
            'bundle_error' => $errors !== '' ? $errors : null,
        ])->save();
        return $status;
    }
}
