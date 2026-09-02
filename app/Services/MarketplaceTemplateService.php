<?php

namespace App\Services;

use App\Models\MarketplaceTemplate;
use App\Models\MarketplaceTemplateNavigationItem;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

final class MarketplaceTemplateService
{
    /** @return Collection<int, MarketplaceTemplate> */
    public function published(array $filters = []): Collection
    {
        $query = MarketplaceTemplate::query()
            ->published()
            ->with(['pages.blocks'])
            ->withCount('pages')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('id');

        if (! blank($filters['industry'] ?? null)) {
            $query->where('industry_slug', (string) $filters['industry']);
        }

        if (! blank($filters['plan'] ?? null)) {
            $query->where('plan', (string) $filters['plan']);
        }

        if (! blank($filters['style'] ?? null)) {
            $query->where('style_slug', (string) $filters['style']);
        }

        if (($filters['featured'] ?? false) === true) {
            $query->featured();
        }

        return $query->get();
    }

    /** @return LengthAwarePaginator<int, MarketplaceTemplate> */
    public function catalogPage(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        $query = MarketplaceTemplate::query()
            ->published()
            ->with(['pages.blocks'])
            ->withCount('pages');

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search).'%';
            $query->where(function ($builder) use ($like) {
                $builder->where('name', 'like', $like)
                    ->orWhere('industry_label', 'like', $like)
                    ->orWhere('summary', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('style_slug', 'like', $like);
            });
        }

        if (! blank($filters['industry'] ?? null)) {
            $query->where('industry_slug', (string) $filters['industry']);
        }
        if (! blank($filters['plan'] ?? null)) {
            $query->where('plan', (string) $filters['plan']);
        }
        if (! blank($filters['style'] ?? null)) {
            $query->where('style_slug', (string) $filters['style']);
        }

        match ((string) ($filters['sort'] ?? 'popular')) {
            'newest' => $query->orderByDesc('published_at')->orderByDesc('id'),
            'price-low' => $query->orderBy('monthly_price_cents')->orderBy('sort_order')->orderBy('id'),
            'price-high' => $query->orderByDesc('monthly_price_cents')->orderBy('sort_order')->orderBy('id'),
            'name' => $query->orderBy('name')->orderBy('id'),
            default => $query->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('id'),
        };

        return $query->paginate(max(1, min($perPage, 48)));
    }

    /** @return array<int, array{key:string,label:string,count:int}> */
    public function industries(): array
    {
        return MarketplaceTemplate::query()
            ->published()
            ->selectRaw('industry_slug, MAX(industry_label) as industry_label, COUNT(*) as template_count')
            ->groupBy('industry_slug')
            ->orderByRaw('MIN(sort_order) asc')
            ->orderBy('industry_slug')
            ->get()
            ->map(fn ($row) => [
                'key' => (string) $row->industry_slug,
                'label' => (string) $row->industry_label,
                'count' => (int) $row->template_count,
            ])->values()->all();
    }

    /** @return array<int, array{key:string,label:string,count:int}> */
    public function styles(): array
    {
        return MarketplaceTemplate::query()
            ->published()
            ->selectRaw('style_slug, COUNT(*) as template_count')
            ->groupBy('style_slug')
            ->orderByDesc('template_count')
            ->orderBy('style_slug')
            ->get()
            ->map(fn ($row) => [
                'key' => (string) $row->style_slug,
                'label' => str($row->style_slug)->replace('-', ' ')->title()->toString(),
                'count' => (int) $row->template_count,
            ])->values()->all();
    }

    public function findPublished(string $slug): ?MarketplaceTemplate
    {
        return MarketplaceTemplate::query()
            ->published()
            ->where('slug', $slug)
            ->first();
    }

    /**
     * Complete normalized bundle used later by checkout/provisioning.
     * Marketplace content remains editable in DB while Spark implementations stay source-controlled.
     *
     * @return array<string, mixed>|null
     */
    public function bundle(string $slug): ?array
    {
        $template = MarketplaceTemplate::query()
            ->published()
            ->where('slug', $slug)
            ->with([
                'pages.blocks',
                'pages.children',
                'navigationItems.page',
                'navigationItems.children.page',
            ])
            ->first();

        if (! $template) {
            return null;
        }

        return [
            'id' => $template->id,
            'slug' => $template->slug,
            'name' => $template->name,
            'industry' => [
                'slug' => $template->industry_slug,
                'label' => $template->industry_label,
            ],
            'style' => $template->style_slug,
            'plan' => $template->plan,
            'monthly_price_cents' => (int) $template->monthly_price_cents,
            'currency' => $template->currency,
            'page_count' => (int) $template->page_count,
            'summary' => $template->summary,
            'description' => $template->description,
            'thumbnail_url' => $template->thumbnail_url,
            'preview_url' => $template->preview_url,
            'theme_key' => $template->theme_key,
            'theme_settings' => $template->theme_settings ?? [],
            'global_header' => $template->global_header ?? [],
            'global_footer' => $template->global_footer ?? [],
            'features' => $template->features ?? [],
            'tags' => $template->tags ?? [],
            'seo' => $template->seo ?? [],
            'onboarding_schema' => $template->onboarding_schema ?? [],
            'source_bundle_key' => $template->source_bundle_key,
            'is_customizable' => (bool) $template->is_customizable,
            'ai_personalization_enabled' => (bool) $template->ai_personalization_enabled,
            'website_care_included' => (bool) $template->website_care_included,
            'version' => (int) $template->version,
            'navigation' => $this->navigationTree($template->navigationItems),
            'pages' => $template->pages->map(fn ($page) => [
                'id' => $page->id,
                'name' => $page->name,
                'slug' => $page->slug,
                'page_intent' => $page->page_intent,
                'page_style' => $page->page_style,
                'sort_order' => (int) $page->sort_order,
                'is_home' => (bool) $page->is_home,
                'parent_slug' => $page->parent_id
                    ? optional($template->pages->firstWhere('id', $page->parent_id))->slug
                    : null,
                'seo' => $page->seo ?? [],
                'metadata' => $page->metadata ?? [],
                'blocks' => $page->blocks->map(fn ($block) => $block->toBuilderBlock())->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * Lightweight public-site preview payload. Only the selected page loads its
     * Spark blocks so 15-page Pro templates stay fast in the marketplace.
     *
     * @return array<string, mixed>|null
     */
    public function previewPage(string $slug, ?string $pageSlug = null): ?array
    {
        $template = MarketplaceTemplate::query()
            ->published()
            ->where('slug', $slug)
            ->with([
                'pages',
                'navigationItems.page',
            ])
            ->first();

        if (! $template) {
            return null;
        }

        $requested = trim((string) ($pageSlug ?? ''));
        $page = $requested !== ''
            ? $template->pages->firstWhere('slug', $requested)
            : ($template->pages->firstWhere('is_home', true) ?: $template->pages->first());

        if (! $page) {
            return null;
        }

        // A requested non-home slug must exist; do not silently fall back to Home.
        if ($requested !== '' && (string) $page->slug !== $requested) {
            return null;
        }

        $page->loadMissing('blocks');

        return [
            'id' => $template->id,
            'slug' => $template->slug,
            'name' => $template->name,
            'industry' => [
                'slug' => $template->industry_slug,
                'label' => $template->industry_label,
            ],
            'style' => $template->style_slug,
            'plan' => $template->plan,
            'plan_label' => ucfirst((string) $template->plan),
            'monthly_price_cents' => (int) $template->monthly_price_cents,
            'monthly_price' => round(((int) $template->monthly_price_cents) / 100, 2),
            'currency' => $template->currency,
            'page_count' => (int) $template->page_count,
            'summary' => $template->summary,
            'description' => $template->description,
            'thumbnail_url' => $template->thumbnail_url,
            'theme_key' => $template->theme_key,
            'theme_settings' => $template->theme_settings ?? [],
            'global_header' => $template->global_header ?? [],
            'global_footer' => $template->global_footer ?? [],
            'features' => array_values($template->features ?? []),
            'tags' => array_values($template->tags ?? []),
            'seo' => $template->seo ?? [],
            'onboarding_schema' => $template->onboarding_schema ?? [],
            'is_customizable' => (bool) $template->is_customizable,
            'ai_personalization_enabled' => (bool) $template->ai_personalization_enabled,
            'website_care_included' => (bool) $template->website_care_included,
            'navigation' => $this->navigationTree($template->navigationItems),
            'pages' => $template->pages->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'slug' => $item->slug,
                'page_intent' => $item->page_intent,
                'page_style' => $item->page_style,
                'sort_order' => (int) $item->sort_order,
                'is_home' => (bool) $item->is_home,
                'parent_slug' => $item->parent_id
                    ? optional($template->pages->firstWhere('id', $item->parent_id))->slug
                    : null,
                'seo' => $item->seo ?? [],
            ])->values()->all(),
            'current_page' => [
                'id' => $page->id,
                'name' => $page->name,
                'slug' => $page->slug,
                'page_intent' => $page->page_intent,
                'page_style' => $page->page_style,
                'is_home' => (bool) $page->is_home,
                'seo' => $page->seo ?? [],
                'metadata' => $page->metadata ?? [],
                'blocks' => $page->blocks->map(fn ($block) => $block->toBuilderBlock())->values()->all(),
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function validationErrors(?MarketplaceTemplate $template = null): array
    {
        $templates = $template
            ? collect([$template->loadMissing(['pages.blocks', 'navigationItems.page'])])
            : MarketplaceTemplate::query()->with(['pages.blocks', 'navigationItems.page'])->get();

        $errors = [];
        $planRanks = ['starter' => 10, 'growth' => 20, 'pro' => 30];

        foreach ($templates as $item) {
            $label = "Marketplace template [{$item->slug}]";
            $planRank = $planRanks[(string) $item->plan] ?? null;

            if ($planRank === null) {
                $errors[] = "{$label} uses unknown plan [{$item->plan}].";
                continue;
            }

            if ($item->pages->count() !== (int) $item->page_count) {
                $errors[] = "{$label} declares {$item->page_count} pages but stores {$item->pages->count()}.";
            }

            $promisedPageCount = (int) config("cosmic_marketplace.plans.{$item->plan}.page_count", 0);
            if ($promisedPageCount > 0 && (int) $item->page_count !== $promisedPageCount) {
                $errors[] = "{$label} is {$item->plan} and must contain {$promisedPageCount} pages; found {$item->page_count}.";
            }

            if ($item->pages->where('is_home', true)->count() !== 1) {
                $errors[] = "{$label} must contain exactly one home page.";
            }

            foreach ($item->pages as $page) {
                if ($page->blocks->isEmpty()) {
                    $errors[] = "{$label} page [{$page->slug}] has no Sparks.";
                }

                foreach ($page->blocks as $block) {
                    $sparkKey = (string) $block->spark_key;
                    $marketplaceSpark = config("cosmic_marketplace.sparks.{$sparkKey}");
                    if (is_array($marketplaceSpark)) {
                        $owner = (string) ($marketplaceSpark['template'] ?? '');
                        if ($owner !== '' && $owner !== (string) $item->slug) {
                            $errors[] = "{$label} page [{$page->slug}] uses Marketplace Spark [{$sparkKey}] owned by [{$owner}].";
                        }
                        continue;
                    }

                    $spark = SparkCatalog::find($sparkKey);
                    if (! $spark) {
                        $errors[] = "{$label} page [{$page->slug}] references missing Spark [{$sparkKey}].";
                        continue;
                    }

                    $sparkRank = $planRanks[(string) ($spark['access_level'] ?? 'pro')] ?? 999;
                    if ($sparkRank > $planRank) {
                        $errors[] = "{$label} is {$item->plan} but page [{$page->slug}] uses {$spark['access_level']} Spark [{$sparkKey}].";
                    }
                }

                $recipe = $page->blocks->sortBy('sort_order')->pluck('spark_key')->values()->all();
                $imageProfile = app(TemplateQualityAuditor::class)->imageProfile($recipe);
                if ($imageProfile['max_consecutive'] > 1) {
                    $errors[] = "{$label} page [{$page->slug}] has {$imageProfile['max_consecutive']} consecutive image-heavy Sparks; maximum is 1.";
                }
            }

            $pageIds = $item->pages->pluck('id')->all();
            foreach ($item->navigationItems as $nav) {
                if ($nav->marketplace_template_page_id && ! in_array($nav->marketplace_template_page_id, $pageIds, true)) {
                    $errors[] = "{$label} navigation item [{$nav->label}] points outside its template.";
                }
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * @param Collection<int, MarketplaceTemplateNavigationItem> $items
     * @return array<int, array<string, mixed>>
     */
    private function navigationTree(Collection $items, ?int $parentId = null): array
    {
        return $items
            ->where('parent_id', $parentId)
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->map(fn (MarketplaceTemplateNavigationItem $item) => [
                'id' => $item->id,
                'label' => $item->label,
                'url' => $item->url ?: ($item->page ? ($item->page->is_home ? '/' : '/'.$item->page->slug) : '#'),
                'page_slug' => $item->page?->slug,
                'is_cta' => (bool) $item->is_cta,
                'target' => $item->target,
                'metadata' => $item->metadata ?? [],
                'children' => $this->navigationTree($items, $item->id),
            ])
            ->values()
            ->all();
    }
}
