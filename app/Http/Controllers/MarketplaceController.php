<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceTemplate;
use App\Services\MarketplaceTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

final class MarketplaceController extends Controller
{
    public function __construct(private readonly MarketplaceTemplateService $templates)
    {
    }

    public function index(Request $request): Response
    {
        $context = $this->context($request);
        $catalogReady = Schema::hasTable('marketplace_templates');

        return Inertia::render('Marketplace/Index', array_merge($context, [
            'catalogReady' => $catalogReady,
            'featuredTemplates' => $catalogReady
                ? $this->templates->published(['featured' => true])->take(8)->map(fn (MarketplaceTemplate $template) => $this->card($request, $template))->values()->all()
                : [],
            'industries' => $catalogReady ? $this->templates->industries() : [],
            'plans' => $this->plans(),
            'catalogPath' => $this->marketplacePath($request, '/templates'),
        ]));
    }

    public function catalog(Request $request, ?string $industry = null): Response
    {
        $context = $this->context($request);
        $catalogReady = Schema::hasTable('marketplace_templates');
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'industry' => $industry ?: trim((string) $request->query('industry', '')),
            'plan' => trim((string) $request->query('plan', '')),
            'style' => trim((string) $request->query('style', '')),
            'sort' => trim((string) $request->query('sort', 'popular')),
        ];

        if (! in_array($filters['plan'], ['', 'starter', 'growth', 'pro'], true)) {
            $filters['plan'] = '';
        }

        $catalog = $catalogReady
            ? $this->templates->catalogPage($filters, 12)->withQueryString()
            : null;

        $items = $catalog
            ? collect($catalog->items())->map(fn (MarketplaceTemplate $template) => $this->card($request, $template))->values()->all()
            : [];

        return Inertia::render('Marketplace/Catalog', array_merge($context, [
            'catalogReady' => $catalogReady,
            'templates' => $items,
            'pagination' => $catalog ? [
                'currentPage' => $catalog->currentPage(),
                'lastPage' => $catalog->lastPage(),
                'perPage' => $catalog->perPage(),
                'total' => $catalog->total(),
                'from' => $catalog->firstItem(),
                'to' => $catalog->lastItem(),
                'prevUrl' => $this->localizePaginationUrl($request, $catalog->previousPageUrl()),
                'nextUrl' => $this->localizePaginationUrl($request, $catalog->nextPageUrl()),
            ] : null,
            'industries' => $catalogReady ? $this->templates->industries() : [],
            'styles' => $catalogReady ? $this->templates->styles() : [],
            'plans' => $this->plans(),
            'filters' => $filters,
            'favoritesOnly' => $request->boolean('favorites'),
            'catalogPath' => $this->marketplacePath($request, '/templates'),
            'marketplaceHome' => $this->marketplacePath($request, ''),
        ]));
    }

    /**
     * Public Cosmic CMS discovery page. This intentionally reuses the live
     * Marketplace catalog instead of maintaining a second template registry.
     */
    public function publicTemplates(Request $request): Response
    {
        return Inertia::render('Public/Templates', $this->publicShowcaseData($request, 12));
    }

    /**
     * Public website examples page backed by the same published Marketplace
     * templates, so previews always point at real demo/detail routes.
     */
    public function publicExamples(Request $request): Response
    {
        return Inertia::render('Public/Examples', $this->publicShowcaseData($request, 9));
    }

    public function show(Request $request, string $industry, string $template): Response
    {
        abort_unless(Schema::hasTable('marketplace_templates'), 404);

        $preview = $this->templates->previewPage($template);
        abort_unless($preview && $preview['industry']['slug'] === $industry, 404);

        $context = $this->context($request);
        $detailPath = $this->marketplacePath($request, "/templates/{$template}");
        $demoPath = $this->marketplacePath($request, "/templates/{$template}/demo");
        $preview['detail_path'] = $detailPath;
        $preview['demo_path'] = $demoPath;
        $preview['demo_embed_path'] = $demoPath.'?embed=1';
        $preview['checkout_path'] = $this->marketplacePath($request, '/checkout/'.rawurlencode($template));

        // Detail needs the complete page map and shell, but not the large block payload.
        unset($preview['current_page']['blocks']);

        $related = MarketplaceTemplate::query()
            ->published()
            ->where('industry_slug', $industry)
            ->where('slug', '!=', $template)
            ->with(['pages.blocks'])
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(3)
            ->get()
            ->map(fn (MarketplaceTemplate $item) => $this->card($request, $item))
            ->values()
            ->all();

        return Inertia::render('Marketplace/Show', array_merge($context, [
            'template' => $preview,
            'relatedTemplates' => $related,
            'marketplaceHome' => $this->marketplacePath($request, ''),
            'catalogPath' => $this->marketplacePath($request, '/templates'),
            'seoPath' => "/templates/{$template}",
        ]));
    }

    public function showBySlug(Request $request, string $template): Response
    {
        $website = $this->templates->findPublished($template);
        if (! $website) {
            // The one-segment route also preserves industry browsing URLs such as
            // /templates/accounting without requiring a parallel route format.
            return $this->catalog($request, $template);
        }

        return $this->show($request, (string) $website->industry_slug, $template);
    }

    public function demo(Request $request, string $industry, string $template, ?string $page = null): Response
    {
        abort_unless(Schema::hasTable('marketplace_templates'), 404);

        $preview = $this->templates->previewPage($template, $page);
        abort_unless($preview && $preview['industry']['slug'] === $industry, 404);

        $embed = $request->boolean('embed');
        $context = $this->context($request);
        $baseDemoPath = $this->marketplacePath($request, "/templates/{$template}/demo");
        $currentSlug = (string) ($preview['current_page']['slug'] ?? 'home');
        $currentPath = $currentSlug === 'home' ? $baseDemoPath : $baseDemoPath.'/'.$currentSlug;

        $preview['detail_path'] = $this->marketplacePath($request, "/templates/{$template}");
        $preview['demo_path'] = $baseDemoPath;
        $preview['current_demo_path'] = $currentPath;
        $preview['embed_path'] = $currentPath.'?embed=1';
        $preview['checkout_path'] = $this->marketplacePath($request, '/checkout/'.rawurlencode($template));
        $preview['pages'] = collect($preview['pages'])->map(function (array $item) use ($baseDemoPath, $embed) {
            $path = ($item['slug'] ?? 'home') === 'home' ? $baseDemoPath : $baseDemoPath.'/'.rawurlencode((string) $item['slug']);
            return [
                ...$item,
                'demo_path' => $path.($embed ? '?embed=1' : ''),
            ];
        })->all();

        // The toolbar shell only needs navigation metadata. The embedded route is the
        // true website viewport and receives the selected page's actual Spark payload.
        if (! $embed) {
            unset($preview['current_page']['blocks']);
        }

        return Inertia::render('Marketplace/Demo', array_merge($context, [
            'template' => $preview,
            'embed' => $embed,
            'marketplaceHome' => $this->marketplacePath($request, ''),
            'catalogPath' => $this->marketplacePath($request, '/templates'),
            'seoPath' => "/templates/{$template}/demo".($currentSlug === 'home' ? '' : '/'.$currentSlug),
        ]));
    }

    public function demoBySlug(Request $request, string $template, ?string $page = null): Response
    {
        $website = $this->templates->findPublished($template);
        abort_unless($website, 404);

        return $this->demo($request, (string) $website->industry_slug, $template, $page);
    }

    /** @return array<string, mixed> */
    private function publicShowcaseData(Request $request, int $limit): array
    {
        $catalogReady = Schema::hasTable('marketplace_templates');
        $catalog = $catalogReady
            ? $this->templates->catalogPage(['sort' => 'popular'], max(1, min($limit, 24)))
            : null;

        $items = $catalog
            ? collect($catalog->items())->map(fn (MarketplaceTemplate $template) => $this->card($request, $template))->values()->all()
            : [];

        return [
            'catalogReady' => $catalogReady,
            'templates' => $items,
            'industries' => $catalogReady ? $this->templates->industries() : [],
            'marketplaceHome' => $this->marketplacePath($request, ''),
            'catalogPath' => $this->marketplacePath($request, '/templates'),
        ];
    }

    /** @return array<string, mixed> */
    private function context(Request $request): array
    {
        $domain = (string) config('cosmic_marketplace.domain', 'marketplace.cosmiccms.com');
        $isMarketplaceHost = strtolower((string) $request->getHost()) === strtolower($domain);

        return [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'marketplaceHost' => $isMarketplaceHost,
            'canonicalUrl' => rtrim((string) config('cosmic_marketplace.scheme', 'https').'://'.$domain, '/'),
        ];
    }

    /** @return array<string, mixed> */
    private function card(Request $request, MarketplaceTemplate $template): array
    {
        $template->loadMissing(['pages.blocks']);
        $home = $template->pages->firstWhere('is_home', true) ?: $template->pages->first();
        $hero = $home?->blocks?->first();
        $content = is_array($hero?->content) ? $hero->content : [];
        $headline = (string) ($content['heading'] ?? $content['title'] ?? $template->name);
        $detailPath = $this->marketplacePath($request, "/templates/{$template->slug}");
        $demoPath = $this->marketplacePath($request, "/templates/{$template->slug}/demo");
        $checkoutPath = $this->marketplacePath($request, "/checkout/{$template->slug}");
        return [
            'id' => $template->id,
            'slug' => $template->slug,
            'name' => $template->name,
            'industry' => $template->industry_slug,
            'industryLabel' => $template->industry_label,
            'style' => $template->style_slug,
            'plan' => ucfirst($template->plan),
            'planKey' => $template->plan,
            'creditPrice' => (int) $template->credit_price,
            'priceUnit' => 'cosmic_credits',
            'pages' => (int) $template->page_count,
            'title' => $headline,
            'copy' => $template->summary ?: $template->description,
            'image' => $template->thumbnail_url,
            'features' => array_values(array_slice($template->features ?? [], 0, 5)),
            'tags' => array_values($template->tags ?? []),
            'featured' => (bool) $template->is_featured,
            'customizable' => (bool) $template->is_customizable,
            'aiPersonalization' => (bool) $template->ai_personalization_enabled,
            'websiteCare' => (bool) $template->website_care_included,
            'publishedAt' => optional($template->published_at)->toIso8601String(),
            'detailPath' => $detailPath,
            'demoPath' => $demoPath,
            'checkoutPath' => $checkoutPath,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function plans(): array
    {
        return collect(config('cosmic_marketplace.plans', []))
            ->map(fn (array $plan, string $key) => [
                'key' => $key,
                'name' => (string) ($plan['label'] ?? ucfirst($key)),
                'creditPrice' => (int) ($plan['credit_price'] ?? 0),
                'pageCount' => (int) ($plan['page_count'] ?? 0),
                'priceUnit' => 'cosmic_credits',
            ])->values()->all();
    }

    private function marketplacePath(Request $request, string $path): string
    {
        $domain = strtolower((string) config('cosmic_marketplace.domain', 'marketplace.cosmiccms.com'));
        $onMarketplaceHost = strtolower((string) $request->getHost()) === $domain;
        $path = '/'.ltrim($path, '/');
        $path = $path === '/' ? '' : $path;

        if ($onMarketplaceHost) {
            return $path === '' ? '/' : $path;
        }

        $prefix = '/'.trim((string) config('cosmic_marketplace.local_prefix', 'marketplace'), '/');
        return $prefix.$path;
    }

    private function localizePaginationUrl(Request $request, ?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $query = parse_url($url, PHP_URL_QUERY);
        $path = $this->marketplacePath($request, '/templates');
        return $query ? $path.'?'.$query : $path;
    }
}
