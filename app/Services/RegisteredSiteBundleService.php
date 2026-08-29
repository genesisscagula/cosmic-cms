<?php

namespace App\Services;

use App\Cosmic\Pricing\ActionPricing;
use App\Jobs\BuildRegisteredSiteBundlePageJob;
use App\Models\Page;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class RegisteredSiteBundleService
{
    public function __construct(
        private readonly LunaSiteBundlePlannerService $planner,
        private readonly CreditService $credits,
        private readonly PreviewDeploymentService $previews,
        private readonly GlobalMegaFooterService $megaFooters,
    ) {
    }

    /** @return array<string, mixed> */
    public function plan(Website $website, string $prompt): array
    {
        $website->loadMissing('pages');
        $prompt = trim($prompt) !== '' ? trim($prompt) : $this->websiteContextPrompt($website);
        $plan = $this->planner->plan($this->planningPrompt($website, $prompt), (string) ($website->industry ?: $prompt));
        $plan['pages'] = $this->starterPages((array) ($plan['pages'] ?? []), $prompt);
        $plan['page_count'] = count($plan['pages']);

        $existing = $website->pages->keyBy(fn (Page $page): string => Str::lower((string) $page->slug));
        $pages = collect($plan['pages'])->map(function (array $recipe) use ($existing): array {
            $page = $existing->get(Str::lower((string) ($recipe['slug'] ?? '')));
            $hasContent = $page && is_array($page->blocks) && $page->blocks !== [];

            return [
                ...$recipe,
                'page_id' => $page?->id,
                'existing' => (bool) $page,
                'has_content' => $hasContent,
                'needs_build' => ! $hasContent,
                'install_action' => $hasContent ? 'preserve' : ($page ? 'build' : 'create'),
            ];
        })->values()->all();
        $buildCount = collect($pages)->where('needs_build', true)->count();

        return [
            ...$plan,
            'pages' => $pages,
            'page_count' => count($pages),
            'build_page_count' => $buildCount,
            'preserved_page_count' => count($pages) - $buildCount,
            'credit_cost_per_page' => ActionPricing::GENERATE_PAGE,
            'credit_cost' => $buildCount * ActionPricing::GENERATE_PAGE,
            'existing_bundle_key' => data_get($website->settings, 'site_bundle.bundle_key'),
        ];
    }

    /** @return array<string, mixed> */
    public function install(Website $website, User $user, string $prompt, array $plan): array
    {
        $currentStatus = (string) data_get($website->settings, 'site_bundle.status', 'idle');
        if (in_array($currentStatus, ['queued', 'building'], true)) {
            throw new RuntimeException('Luna is already building starter pages for this website.');
        }

        $firstBuild = ! $website->pages()->exists();
        $starterFooter = null;
        if ($firstBuild) {
            $menu = collect((array) ($plan['pages'] ?? []))->map(fn (array $page): array => [
                'label' => (string) ($page['title'] ?? 'Page'),
                'url' => (bool) ($page['is_home'] ?? false) ? 'home' : (string) ($page['slug'] ?? ''),
            ])->values()->all();
            $starterFooter = $this->megaFooters->compose(
                $prompt,
                [
                    'business_name' => (string) $website->name,
                    'industry' => (string) ($website->industry ?: ($plan['industry'] ?? '')),
                    'location' => (string) $website->location,
                    'business_description' => (string) ($website->business_description ?: $prompt),
                    'theme' => is_array($website->theme_settings) ? $website->theme_settings : [],
                ],
                $menu,
                is_array($website->global_footer) ? $website->global_footer : [],
            );
        }

        $buildId = (string) Str::uuid();
        $cost = max(0, (int) ($plan['credit_cost'] ?? 0));
        $chargeReference = 'registered-site-bundle-'.$buildId;

        if ($cost > 0) {
            $this->credits->consume(
                $user,
                $cost,
                'Build starter website with Luna',
                $website,
                $chargeReference,
                [
                    'category' => 'ai',
                    'bundle_key' => $plan['bundle_key'] ?? null,
                    'page_count' => $plan['build_page_count'] ?? 0,
                    'cost_per_page' => ActionPricing::GENERATE_PAGE,
                ],
            );
        }

        try {
            $queued = DB::transaction(function () use ($website, $prompt, $plan, $buildId, $user, $chargeReference, $starterFooter): array {
                $locked = Website::query()->lockForUpdate()->findOrFail($website->id);
                $existing = $locked->pages()->get()->keyBy(fn (Page $page): string => Str::lower((string) $page->slug));
                $wasEmpty = $existing->isEmpty();
                $manifestPages = [];
                $queuedPages = [];

                foreach (array_values((array) ($plan['pages'] ?? [])) as $index => $recipe) {
                    $slug = (bool) ($recipe['is_home'] ?? $index === 0)
                        ? 'home'
                        : (Str::slug((string) ($recipe['slug'] ?? $recipe['title'] ?? '')) ?: 'page-'.($index + 1));
                    $page = $existing->get(Str::lower($slug));

                    if (! $page) {
                        $page = $locked->pages()->create([
                            'title' => (string) ($recipe['title'] ?? Str::headline($slug)),
                            'slug' => $slug,
                            'parent_id' => null,
                            'sort_order' => $index + 1,
                            'page_type' => (string) ($recipe['page_type'] ?? 'standard'),
                            'blocks' => [],
                            'status' => 'draft',
                        ]);
                        $existing->put(Str::lower($slug), $page);
                    } else {
                        $page->forceFill([
                            'title' => (string) ($recipe['title'] ?? $page->title),
                            'sort_order' => $index + 1,
                            'page_type' => (string) ($recipe['page_type'] ?? $page->page_type ?? 'standard'),
                        ])->save();
                    }

                    $hasContent = is_array($page->blocks) && $page->blocks !== [];
                    $status = $hasContent ? 'preserved' : 'queued';
                    $manifest = [
                        ...$recipe,
                        'slug' => $slug,
                        'page_id' => $page->id,
                        'build_status' => $status,
                        'build_error' => null,
                        'built_at' => $hasContent ? now()->toIso8601String() : null,
                        'credit_cost' => $hasContent ? 0 : ActionPricing::GENERATE_PAGE,
                    ];
                    $manifestPages[] = $manifest;

                    if (! $hasContent) {
                        $queuedPages[] = [
                            'page_id' => $page->id,
                            'refund_reference' => 'registered-site-bundle-refund-'.$buildId.'-'.$page->id,
                        ];
                    }
                }

                if ($manifestPages === []) {
                    throw new RuntimeException('The selected Luna bundle has no installable pages.');
                }

                $header = is_array($locked->global_header) ? $locked->global_header : [];
                $header['logo_text'] = $header['logo_text'] ?? $locked->name;
                $header['menu'] = collect($manifestPages)->map(fn (array $page): array => [
                    'label' => (string) $page['title'],
                    'url' => (string) $page['slug'],
                ])->values()->all();
                $header['cta_url'] = collect($manifestPages)->contains(fn (array $page): bool => $page['slug'] === 'contact')
                    ? 'contact'
                    : (string) ($header['cta_url'] ?? '#');

                $settings = is_array($locked->settings) ? $locked->settings : [];
                $settings['site_bundle'] = [
                    'build_id' => $buildId,
                    'bundle_key' => (string) ($plan['bundle_key'] ?? ''),
                    'bundle_name' => (string) ($plan['bundle_name'] ?? 'Luna starter bundle'),
                    'bundle_version' => (int) ($plan['bundle_version'] ?? 1),
                    'industry' => (string) ($plan['industry'] ?? $locked->industry ?? 'general'),
                    'archetype' => (string) ($plan['archetype'] ?? 'conversion'),
                    'design_contract' => (array) ($plan['design_contract'] ?? []),
                    'template_candidates' => array_values((array) ($plan['template_candidates'] ?? [])),
                    'pages' => $manifestPages,
                    'page_count' => count($manifestPages),
                    'status' => $queuedPages === [] ? 'ready' : 'queued',
                    'error' => null,
                    'prompt' => $prompt,
                    'installed_by_user_id' => $user->id,
                    'charge_reference' => $chargeReference,
                    'installed_at' => now()->toIso8601String(),
                    'completed_at' => $queuedPages === [] ? now()->toIso8601String() : null,
                    'planner' => (string) ($plan['planner'] ?? 'luna_curated_site_bundle'),
                ];

                $theme = is_array($locked->theme_settings) ? $locked->theme_settings : [];
                $theme['luna_theme_locked'] = true;
                $updates = [
                    'industry' => $locked->industry ?: Str::headline((string) ($plan['industry'] ?? '')),
                    'business_description' => $locked->business_description ?: $prompt,
                    'settings' => $settings,
                    'theme_settings' => $theme,
                    'global_header' => $header,
                ];
                if ($wasEmpty && is_array($starterFooter)) {
                    $updates['global_footer'] = $starterFooter;
                }
                $locked->forceFill($updates)->save();

                return $queuedPages;
            });
        } catch (Throwable $exception) {
            if ($cost > 0) {
                $this->credits->refund(
                    $user,
                    $cost,
                    'Refund failed Luna starter website setup',
                    $website,
                    $chargeReference.'-setup-refund',
                    ['category' => 'refund', 'bundle_key' => $plan['bundle_key'] ?? null],
                );
            }
            throw $exception;
        }

        foreach ($queued as $page) {
            BuildRegisteredSiteBundlePageJob::dispatch(
                $website->id,
                (int) $page['page_id'],
                $user->id,
                $buildId,
                (string) $page['refund_reference'],
            );
        }

        return $this->workspacePayload($website->fresh(), $user);
    }

    /** @return array<string, mixed> */
    public function workspacePayload(Website $website, ?User $user = null): array
    {
        $bundle = data_get($website->settings, 'site_bundle');
        if (! is_array($bundle)) {
            return [
                'installed' => false,
                'status' => 'idle',
                'pages' => [],
                'credit_balance' => $user ? $this->credits->balance($user) : null,
            ];
        }

        $pages = collect((array) ($bundle['pages'] ?? []))->map(function (array $page): array {
            $pageId = (int) ($page['page_id'] ?? 0);
            return [
                'page_id' => $pageId ?: null,
                'title' => (string) ($page['title'] ?? 'Page'),
                'slug' => (string) ($page['slug'] ?? ''),
                'template_key' => (string) ($page['template_key'] ?? ''),
                'template_name' => (string) ($page['template_name'] ?? ''),
                'build_status' => (string) ($page['build_status'] ?? 'queued'),
                'build_error' => $page['build_error'] ?? null,
                'builder_url' => $pageId ? route('pages.builder', ['page' => $pageId], false) : null,
            ];
        })->values();
        $complete = $pages->whereIn('build_status', ['ready', 'preserved', 'failed'])->count();
        $count = $pages->count();

        return [
            'installed' => true,
            'build_id' => $bundle['build_id'] ?? null,
            'bundle_key' => $bundle['bundle_key'] ?? null,
            'bundle_name' => $bundle['bundle_name'] ?? 'Luna starter bundle',
            'archetype' => $bundle['archetype'] ?? null,
            'status' => $bundle['status'] ?? 'queued',
            'error' => $bundle['error'] ?? null,
            'pages' => $pages->all(),
            'page_count' => $count,
            'ready_page_count' => $pages->whereIn('build_status', ['ready', 'preserved'])->count(),
            'progress' => $count > 0 ? (int) round(($complete / $count) * 100) : 0,
            'terminal' => in_array((string) ($bundle['status'] ?? ''), ['ready', 'partial', 'failed'], true),
            'preview_url' => $this->previews->url($website),
            'credit_balance' => $user ? $this->credits->balance($user) : null,
            'installed_at' => $bundle['installed_at'] ?? null,
            'completed_at' => $bundle['completed_at'] ?? null,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function starterPages(array $pages, string $prompt): array
    {
        $pages = collect($pages)->values();
        $home = $pages->first(fn (array $page): bool => (bool) ($page['is_home'] ?? false)) ?? $pages->first();
        $contact = $pages->first(fn (array $page): bool => (string) ($page['slug'] ?? '') === 'contact');
        $normalizedPrompt = Str::lower($prompt);
        $middle = $pages
            ->reject(fn (array $page): bool => $page === $home || $page === $contact)
            ->values()
            ->map(function (array $page, int $index) use ($normalizedPrompt): array {
                $title = Str::lower((string) ($page['title'] ?? ''));
                $slug = Str::lower(str_replace('-', ' ', (string) ($page['slug'] ?? '')));
                $words = collect(preg_split('/[^a-z0-9]+/i', $title.' '.$slug) ?: [])
                    ->filter(fn (string $word): bool => strlen($word) >= 3)
                    ->unique();
                $explicit = Str::contains($normalizedPrompt, array_values(array_filter([$title, $slug])))
                    || $words->contains(fn (string $word): bool => Str::contains($normalizedPrompt, $word));
                $core = Str::contains($slug, ['service', 'menu', 'product', 'solution', 'program', 'course', 'room', 'property', 'practice']);
                return [...$page, '_starter_score' => ($explicit ? 100 : 0) + ($core ? 20 : 0) - $index];
            })
            ->sortByDesc('_starter_score')
            ->take(4)
            ->sortBy('sort_order')
            ->map(function (array $page): array {
                unset($page['_starter_score']);
                return $page;
            });
        $selected = collect([$home])->filter()->merge($middle)->when($contact, fn ($items) => $items->push($contact))->take(6);

        return $selected->values()->map(function (array $page, int $index): array {
            $page['is_home'] = $index === 0;
            $page['slug'] = $index === 0 ? 'home' : (string) ($page['slug'] ?? Str::slug((string) ($page['title'] ?? 'page')));
            $page['sort_order'] = $index + 1;
            return $page;
        })->all();
    }

    private function websiteContextPrompt(Website $website): string
    {
        return trim(collect([
            $website->name,
            $website->industry ? 'Industry: '.$website->industry : null,
            $website->location ? 'Location: '.$website->location : null,
            $website->business_description,
        ])->filter()->implode('. '));
    }

    private function planningPrompt(Website $website, string $prompt): string
    {
        return trim($prompt)."\n\n"
            ."REGISTERED WEBSITE CONTEXT\n"
            ."Website name: ".($website->name ?: 'Untitled website')."\n"
            ."Industry: ".($website->industry ?: 'Infer from the request')."\n"
            ."Location: ".($website->location ?: 'Not specified')."\n"
            ."Business brief: ".($website->business_description ?: $prompt)."\n"
            ."Choose one coherent curated site bundle. Keep every inner page inside that bundle's registered template family.";
    }
}
