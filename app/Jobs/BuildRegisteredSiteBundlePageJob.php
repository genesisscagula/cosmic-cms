<?php

namespace App\Jobs;

use App\Cosmic\Pricing\ActionPricing;
use App\Models\Page;
use App\Models\User;
use App\Models\Website;
use App\Services\AiPageGenerationService;
use App\Services\CreditService;
use App\Services\LunaPexelsVideoService;
use App\Services\PagePublisher;
use App\Services\PreviewDeploymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class BuildRegisteredSiteBundlePageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(
        public int $websiteId,
        public int $pageId,
        public int $userId,
        public string $buildId,
        public string $refundReference,
    ) {
        $this->onConnection('database');
        $this->onQueue((string) config('cosmic-queue.queues.ai_builds', 'ai'));
        $this->afterCommit();
    }

    public function handle(
        AiPageGenerationService $generator,
        LunaPexelsVideoService $videos,
        PagePublisher $publisher,
        PreviewDeploymentService $previews,
        CreditService $credits,
    ): void {
        $website = Website::query()->find($this->websiteId);
        $user = User::query()->find($this->userId);
        if (! $website || ! $user || (string) data_get($website->settings, 'site_bundle.build_id') !== $this->buildId) {
            return;
        }

        $recipe = collect((array) data_get($website->settings, 'site_bundle.pages', []))
            ->first(fn (array $page): bool => (int) ($page['page_id'] ?? 0) === $this->pageId);
        if (! is_array($recipe) || in_array((string) ($recipe['build_status'] ?? ''), ['ready', 'preserved'], true)) {
            return;
        }

        $this->markPage('building');
        $page = $website->pages()->find($this->pageId);
        if (! $page) {
            $this->failPage('The page record is missing.', $user, $website, $credits);
            return;
        }

        try {
            $prompt = $this->pagePrompt($website, $recipe);
            $blocks = $generator->imagesWithoutRemoteDownloads(
                fn (): array => $generator->generateBlocks($prompt, array_values((array) ($recipe['sections'] ?? [])))
            );
            $blocks = $videos->apply($prompt, $blocks);
            $remote = $generator->applyStartPageRemoteImages($prompt, $blocks);
            $blocks = array_values((array) ($remote['blocks'] ?? $blocks));
            if ($blocks === []) {
                throw new \RuntimeException('Luna did not return any registered page sections.');
            }

            DB::transaction(function () use ($page, $website, $publisher, $blocks): void {
                $lockedPage = Page::query()->lockForUpdate()->findOrFail($page->id);
                $lockedWebsite = Website::query()->lockForUpdate()->findOrFail($website->id);
                $lockedPage->forceFill(['blocks' => $blocks, 'status' => 'draft', 'publish_error' => null])->save();
                $html = $publisher->publish($lockedPage->fresh(), $lockedWebsite->fresh());
                $publishedAt = now();
                $lockedPage->forceFill([
                    'published_blocks' => $blocks,
                    'published_page_style' => $lockedWebsite->page_style ?: $lockedPage->page_style,
                    'published_html' => $html,
                    'status' => 'published',
                    'published_at' => $lockedPage->published_at ?: $publishedAt,
                    'last_published_at' => $publishedAt,
                    'publish_error' => null,
                ])->save();
                $lockedWebsite->forceFill([
                    'published_page_style' => $lockedWebsite->page_style,
                    'published_theme_settings' => $lockedWebsite->theme_settings,
                    'published_global_header' => $lockedWebsite->global_header,
                    'published_global_footer' => $lockedWebsite->global_footer,
                ])->save();
            });

            $status = $this->markPage('ready');
            if (in_array($status, ['ready', 'partial'], true)) {
                try {
                    $previews->deploy($website->fresh());
                    $this->recordPreview(null);
                } catch (Throwable $exception) {
                    report($exception);
                    $this->recordPreview($exception->getMessage());
                }
            }

            Log::info('[RegisteredSiteBundle] Page build completed.', [
                'website_id' => $website->id,
                'page_id' => $page->id,
                'bundle_key' => data_get($website->settings, 'site_bundle.bundle_key'),
                'build_id' => $this->buildId,
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $this->failPage($exception->getMessage(), $user, $website, $credits);
        }
    }

    private function pagePrompt(Website $website, array $recipe): string
    {
        return "Build one inner page inside an existing registered Cosmic site bundle.\n\n"
            ."Business Name: {$website->name}\n"
            ."Industry: {$website->industry}\n"
            ."Location: {$website->location}\n"
            ."Business brief: {$website->business_description}\n"
            ."Original request: ".data_get($website->settings, 'site_bundle.prompt')."\n"
            ."Current page: ".($recipe['title'] ?? 'Page')."\n"
            ."Page purpose: ".($recipe['page_intent'] ?? 'information')."\n"
            ."LOCKED BUNDLE: ".data_get($website->settings, 'site_bundle.bundle_name')."\n"
            ."LOCKED TEMPLATE: ".($recipe['template_key'] ?? '')."\n"
            ."Preserve the website theme, typography, spacing rhythm, component treatment and visual tone. "
            ."Generate content only for the locked registered sections supplied by the server. "
            ."Do not duplicate the Home narrative or invent awards, certifications, employee names, statistics, addresses or claims.";
    }

    private function failPage(string $message, User $user, Website $website, CreditService $credits): void
    {
        $status = $this->markPage('failed', $message);
        if (ActionPricing::GENERATE_PAGE > 0) {
            try {
                $credits->refund(
                    $user,
                    ActionPricing::GENERATE_PAGE,
                    'Refund failed Luna starter page build',
                    $website,
                    $this->refundReference,
                    ['category' => 'refund', 'page_id' => $this->pageId, 'build_id' => $this->buildId],
                );
            } catch (Throwable $refundException) {
                report($refundException);
            }
        }

        if (in_array($status, ['ready', 'partial'], true)) {
            try {
                app(PreviewDeploymentService::class)->deploy($website->fresh());
            } catch (Throwable $exception) {
                report($exception);
                $this->recordPreview($exception->getMessage());
            }
        }
    }

    private function markPage(string $pageStatus, ?string $error = null): string
    {
        return DB::transaction(function () use ($pageStatus, $error): string {
            $website = Website::query()->lockForUpdate()->find($this->websiteId);
            if (! $website || (string) data_get($website->settings, 'site_bundle.build_id') !== $this->buildId) {
                return 'missing';
            }

            $settings = is_array($website->settings) ? $website->settings : [];
            $bundle = (array) ($settings['site_bundle'] ?? []);
            $bundle['pages'] = collect((array) ($bundle['pages'] ?? []))->map(function (array $page) use ($pageStatus, $error): array {
                if ((int) ($page['page_id'] ?? 0) !== $this->pageId) {
                    return $page;
                }
                $page['build_status'] = $pageStatus;
                $page['build_error'] = $error;
                $page['built_at'] = $pageStatus === 'ready' ? now()->toIso8601String() : ($page['built_at'] ?? null);
                return $page;
            })->values()->all();

            $statuses = collect($bundle['pages'])->pluck('build_status');
            $bundleStatus = match (true) {
                $statuses->every(fn ($value): bool => in_array($value, ['ready', 'preserved'], true)) => 'ready',
                $statuses->contains(fn ($value): bool => in_array($value, ['queued', 'building'], true)) => 'building',
                $statuses->contains(fn ($value): bool => in_array($value, ['ready', 'preserved'], true)) => 'partial',
                default => 'failed',
            };
            $bundle['status'] = $bundleStatus;
            $bundle['error'] = collect($bundle['pages'])->pluck('build_error')->filter()->take(5)->implode("\n") ?: null;
            if (in_array($bundleStatus, ['ready', 'partial', 'failed'], true)) {
                $bundle['completed_at'] = now()->toIso8601String();
            }
            $settings['site_bundle'] = $bundle;
            $website->forceFill(['settings' => $settings])->save();

            return $bundleStatus;
        });
    }

    private function recordPreview(?string $error): void
    {
        DB::transaction(function () use ($error): void {
            $website = Website::query()->lockForUpdate()->find($this->websiteId);
            if (! $website || (string) data_get($website->settings, 'site_bundle.build_id') !== $this->buildId) {
                return;
            }
            $settings = is_array($website->settings) ? $website->settings : [];
            $bundle = (array) ($settings['site_bundle'] ?? []);
            $bundle['preview_error'] = $error;
            $bundle['preview_deployed_at'] = $error ? ($bundle['preview_deployed_at'] ?? null) : now()->toIso8601String();
            $settings['site_bundle'] = $bundle;
            $website->forceFill(['settings' => $settings])->save();
        });
    }
}
