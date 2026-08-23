<?php

namespace App\Jobs;

use App\Models\Page;
use App\Models\TrialGeneration;
use App\Services\AiPageGenerationService;
use App\Services\LunaPexelsVideoService;
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

    public int $tries = 1;
    public int $timeout = 1200;

    public function __construct(public int $trialId)
    {
        $this->onQueue((string) config('cosmic-queue.queues.ai_builds', 'ai-builds'));
        $this->afterCommit();
    }

    public function handle(AiPageGenerationService $generator, LunaPexelsVideoService $videos): void
    {
        $trial = TrialGeneration::query()->find($this->trialId);
        if (! $trial || $trial->claimed_at || $trial->status !== 'ready') {
            return;
        }

        $trial->forceFill(['bundle_status' => 'building', 'bundle_error' => null])->save();
        $failures = [];

        foreach ((array) data_get($trial->bundle_manifest, 'pages', []) as $recipe) {
            if (($recipe['is_home'] ?? false) || ($recipe['build_status'] ?? '') === 'ready') {
                continue;
            }

            $page = Page::query()
                ->where('website_id', $trial->website_id)
                ->find((int) ($recipe['page_id'] ?? 0));
            if (! $page) {
                $failures[] = ($recipe['title'] ?? 'Page').': missing page record';
                $this->markPage($trial->id, (int) ($recipe['page_id'] ?? 0), 'failed', 'Page record is missing.');
                continue;
            }
            if (is_array($page->blocks) && $page->blocks !== []) {
                $this->markPage($trial->id, $page->id, 'ready');
                continue;
            }

            try {
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
                $failures[] = $page->title.': '.$exception->getMessage();
                $this->markPage($trial->id, $page->id, 'failed', $exception->getMessage());
            }
        }

        $fresh = TrialGeneration::query()->find($trial->id);
        $statuses = collect(data_get($fresh?->bundle_manifest, 'pages', []))->pluck('build_status');
        $ready = $statuses->every(fn ($status) => $status === 'ready');
        $fresh?->forceFill([
            'bundle_status' => $ready ? 'ready' : ($statuses->contains('ready') ? 'partial' : 'failed'),
            'bundle_error' => $failures === [] ? null : implode("\n", array_slice($failures, 0, 5)),
        ])->save();

        Log::info('[TrialSiteBundle] Background bundle build finished.', [
            'trial_id' => $trial->id,
            'bundle_key' => data_get($trial->bundle_manifest, 'bundle_key'),
            'pages' => $statuses->count(),
            'ready' => $statuses->filter(fn ($status) => $status === 'ready')->count(),
            'failures' => count($failures),
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
}
