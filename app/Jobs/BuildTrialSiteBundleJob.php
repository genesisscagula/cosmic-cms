<?php

namespace App\Jobs;

use App\Models\Page;
use App\Models\TrialGeneration;
use App\Services\AiPageGenerationService;
use App\Services\LunaPexelsVideoService;
use App\Services\LunaTrialShellSelectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class BuildTrialSiteBundleJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 420;
    public array $backoff = [30, 90, 180];
    public int $uniqueFor = 1800;

    public function __construct(public int $trialId, public int $pageId)
    {
        // Public trial pages are intentionally asynchronous even when a local
        // environment keeps QUEUE_CONNECTION=sync for unrelated jobs.
        $this->onConnection((string) config('cosmic-queue.connections.ai_builds', 'database'));
        $this->onQueue((string) config('cosmic-queue.queues.ai_builds', 'ai'));
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return $this->trialId.':'.$this->pageId;
    }

    public function handle(AiPageGenerationService $generator, LunaPexelsVideoService $videos, LunaTrialShellSelectionService $shellSelection): void
    {
        $trial = TrialGeneration::query()->find($this->trialId);
        if (! $trial || $trial->claimed_at || $trial->status !== 'ready') {
            return;
        }

        $recipe = collect((array) data_get($trial->bundle_manifest, 'pages', []))
            ->first(fn (array $page) => (int) ($page['page_id'] ?? 0) === $this->pageId);
        if (! is_array($recipe)) {
            // The bundle may have been explicitly regenerated while an older
            // job was waiting. That payload is obsolete and must not disturb
            // the replacement manifest.
            return;
        }

        $page = Page::query()->where('website_id', $trial->website_id)->find($this->pageId);
        if (! $page) {
            $this->markPage($trial->id, $this->pageId, 'failed', 'Page record is missing.');
            if (! self::dispatchNext($trial->id)) $this->refreshBundleStatus($trial->id);
            return;
        }
        if (is_array($page->blocks) && $page->blocks !== []) {
            if ((bool) ($recipe['is_home'] ?? false) && $trial->website) {
                $shellSelection->apply($trial, $trial->website, array_values($page->blocks), []);
            }
            $this->markPage($trial->id, $page->id, 'ready');
            $status = $this->refreshBundleStatus($trial->id);
            if ($status === 'ready') {
                FinalizeTrialSiteBundleJob::dispatch($trial->id);
            } else {
                self::dispatchNext($trial->id);
            }
            return;
        }

        $trial->forceFill(['bundle_status' => 'building', 'bundle_error' => null])->save();

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

            // Once Home has real generated media, Luna can make the final shell
            // decision before Builder opens. Inner pages inherit this website-level
            // shell and never re-roll it independently.
            if ((bool) ($recipe['is_home'] ?? false)) {
                $freshTrial = TrialGeneration::query()->with('website')->find($trial->id);
                if ($freshTrial?->website) {
                    $shellSelection->apply(
                        $freshTrial,
                        $freshTrial->website,
                        $blocks,
                        is_array($remote['visual_intent'] ?? null) ? $remote['visual_intent'] : [],
                    );
                }
            }
        } catch (Throwable $exception) {
            report($exception);
            // Keep the page non-terminal while Laravel still owns retries for
            // this exact job. `failed()` records the final failed state only
            // after all attempts are exhausted.
            $this->recordAttemptError($trial->id, $page->id, $exception);
            throw $exception;
        }

        $bundleStatus=$this->refreshBundleStatus($trial->id);
        $fresh = TrialGeneration::query()->find($trial->id);
        if ($bundleStatus === 'ready' && $fresh) {
            FinalizeTrialSiteBundleJob::dispatch($trial->id);
        } elseif (in_array($bundleStatus, ['building', 'queued', 'partial'], true)) {
            self::dispatchNext($trial->id);
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
        DB::transaction(function () use ($trialId, $pageId, $status, $error): void {
            $trial = TrialGeneration::query()->lockForUpdate()->find($trialId);
            if (! $trial) return;

            $manifest = is_array($trial->bundle_manifest) ? $trial->bundle_manifest : [];
            $manifest['pages'] = collect($manifest['pages'] ?? [])->map(function (array $page) use ($pageId, $status, $error): array {
                if ((int) ($page['page_id'] ?? 0) !== $pageId) return $page;
                $page['build_status'] = $status;
                $page['build_started_at'] = $status === 'building' ? now()->toIso8601String() : ($page['build_started_at'] ?? null);
                $page['built_at'] = $status === 'ready' ? now()->toIso8601String() : ($page['built_at'] ?? null);
                $page['build_error'] = $error;
                if ($status === 'ready') $page['build_attempts'] = max(1, (int) ($page['build_attempts'] ?? 0));
                return $page;
            })->values()->all();
            $trial->forceFill(['bundle_manifest' => $manifest])->save();
        });
    }

    private function recordAttemptError(int $trialId, int $pageId, Throwable $exception): void
    {
        DB::transaction(function () use ($trialId, $pageId, $exception): void {
            $trial = TrialGeneration::query()->lockForUpdate()->find($trialId);
            if (! $trial) return;
            $manifest = is_array($trial->bundle_manifest) ? $trial->bundle_manifest : [];
            $manifest['pages'] = collect($manifest['pages'] ?? [])->map(function (array $page) use ($pageId, $exception): array {
                if ((int) ($page['page_id'] ?? 0) !== $pageId) return $page;
                $page['build_status'] = 'building';
                $page['build_attempts'] = max((int) ($page['build_attempts'] ?? 0), $this->attempts());
                $page['last_attempt_failed_at'] = now()->toIso8601String();
                $page['build_error'] = $exception->getMessage();
                return $page;
            })->values()->all();
            $trial->forceFill([
                'bundle_manifest' => $manifest,
                'bundle_status' => 'building',
                'bundle_error' => 'Page '.$pageId.' attempt '.$this->attempts().' failed: '.$exception->getMessage(),
            ])->save();
        });
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


    public static function dispatchNext(int $trialId, bool $recoverStale = false): ?int
    {
        $nextPageId = DB::transaction(function () use ($trialId, $recoverStale): ?int {
            $trial = TrialGeneration::query()->lockForUpdate()->find($trialId);
            if (! $trial || $trial->claimed_at) {
                return null;
            }

            $manifest = is_array($trial->bundle_manifest) ? $trial->bundle_manifest : [];
            $pages = collect($manifest['pages'] ?? [])->sortBy('sort_order');
            $staleBefore = now()->subMinutes((int) config('cosmic.trial_bundle_stale_minutes', 15));
            $next = $pages->first(function (array $item) use ($recoverStale, $staleBefore): bool {
                if ((int) ($item['page_id'] ?? 0) < 1) return false;
                $status = (string) ($item['build_status'] ?? 'queued');
                if ($status === 'queued') return true;
                if (! $recoverStale || ! in_array($status, ['scheduled', 'building'], true)) return false;
                $timestamp = $item['build_started_at'] ?? $item['scheduled_at'] ?? null;
                return ! $timestamp || \Illuminate\Support\Carbon::parse($timestamp)->lte($staleBefore);
            });
            if (! is_array($next)) {
                return null;
            }

            $pageId = (int) $next['page_id'];
            $manifest['pages'] = collect($manifest['pages'] ?? [])->map(function (array $item) use ($pageId, $recoverStale): array {
                if ((int) ($item['page_id'] ?? 0) === $pageId) {
                    $item['build_status'] = 'scheduled';
                    $item['scheduled_at'] = now()->toIso8601String();
                    $item['build_error'] = null;
                    $item['recovered_at'] = $recoverStale ? now()->toIso8601String() : ($item['recovered_at'] ?? null);
                }
                return $item;
            })->values()->all();
            $trial->forceFill(['bundle_manifest' => $manifest, 'bundle_status' => 'building'])->save();

            return $pageId;
        });

        if ($nextPageId) {
            // Build trial inner pages back-to-back. A page schedules exactly one
            // successor only after it finishes, so API work remains sequential
            // without the old artificial two-minute gap between pages.
            try {
                self::dispatch($trialId, $nextPageId);
            } catch (Throwable $exception) {
                // Queue insertion itself can fail. Put the recipe back into a
                // recoverable state instead of leaving a phantom `scheduled` page.
                DB::transaction(function () use ($trialId, $nextPageId, $exception): void {
                    $trial = TrialGeneration::query()->lockForUpdate()->find($trialId);
                    if (! $trial) return;
                    $manifest = is_array($trial->bundle_manifest) ? $trial->bundle_manifest : [];
                    $manifest['pages'] = collect($manifest['pages'] ?? [])->map(function (array $item) use ($nextPageId, $exception): array {
                        if ((int) ($item['page_id'] ?? 0) !== $nextPageId) return $item;
                        $item['build_status'] = 'queued';
                        $item['build_error'] = 'Queue dispatch failed: '.$exception->getMessage();
                        return $item;
                    })->values()->all();
                    $trial->forceFill(['bundle_manifest' => $manifest, 'bundle_status' => 'queued', 'bundle_error' => $exception->getMessage()])->save();
                });
                throw $exception;
            }
        }

        return $nextPageId;
    }

    public function failed(?Throwable $exception): void
    {
        $this->markPage($this->trialId, $this->pageId, 'failed', $exception?->getMessage() ?: 'Page generation failed.');
        if (! self::dispatchNext($this->trialId)) {
            $this->refreshBundleStatus($this->trialId);
        }
    }

    private function refreshBundleStatus(int $trialId): string
    {
        return DB::transaction(function () use ($trialId): string {
            $trial = TrialGeneration::query()->lockForUpdate()->find($trialId);
            if (! $trial) return 'missing';

            $pages = collect(data_get($trial->bundle_manifest, 'pages', []));
            $statuses = $pages->pluck('build_status');
            $errors = $pages->pluck('build_error')->filter()->take(5)->implode("\n");
            $status = match (true) {
                $statuses->every(fn ($value) => $value === 'ready') => 'ready',
                $statuses->contains(fn ($value) => in_array($value, ['queued', 'scheduled', 'building'], true)) => 'building',
                $statuses->contains('ready') => 'partial',
                default => 'failed',
            };
            $trial->forceFill(['bundle_status' => $status, 'bundle_error' => $errors !== '' ? $errors : null])->save();
            return $status;
        });
    }
}
