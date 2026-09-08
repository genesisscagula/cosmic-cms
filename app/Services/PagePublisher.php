<?php

namespace App\Services;

use App\Helpers\CmsHtmlCompiler;
use App\Models\Page;
use App\Models\Website;
use App\Support\PageStyleRegistry;
use App\Support\HeaderFooterVariantContract;
use App\Support\AiFlexStructureContract;

class PagePublisher
{
    public function __construct(
        private readonly MediaAssetSafetyService $mediaSafety,
        private readonly DynamicContentTemplateRenderer $contentTemplates,
    ) {}
    /**
     * Compile the approved Builder state into the snapshot stored by Laravel.
     *
     * Deployment is intentionally separate: the manual static-site sync reads
     * published snapshots through the existing API when the operator runs it.
     */
    public function publish(Page $page, Website $website): string
    {
        if (config('cosmic_media.localize_remote_images', false)) {
            $remote = $this->mediaSafety->providerUrls($page->blocks ?? []);
            if ($remote !== []) {
                throw new \RuntimeException('Publish blocked: remote preview images must be localized first.');
            }
        }

        $theme = $website->theme_settings ?? [];
        $primaryColor = $theme['primary'] ?? 'midnight';
        $brandPalette = is_array($theme['brand_palette'] ?? null)
            ? $theme['brand_palette']
            : (is_array(data_get($theme, 'custom_brand_theme.palette')) ? data_get($theme, 'custom_brand_theme.palette') : []);
        return $this->compileBlocks($page->blocks ?? [], $primaryColor, [
            'page_style' => PageStyleRegistry::normalize($website->page_style ?: $page->page_style),
            'typography' => is_array($theme['typography'] ?? null) ? $theme['typography'] : [],
            'section_layout' => is_array($theme['section_layout'] ?? null) ? $theme['section_layout'] : [],
            'background_style' => is_array($theme['background_style'] ?? null) ? $theme['background_style'] : [],
            'components' => is_array($theme['components'] ?? null) ? $theme['components'] : [],
            'brand_palette' => $brandPalette,
        ]);
    }

    /**
     * Normalize Builder-owned AI Flex/Lego structures before static compilation.
     *
     * The Builder can mutate rows/columns independently while the user is editing.
     * Publishing must always feed the compiler the canonical persisted contract so a
     * transient/mixed tree cannot turn a valid Page Builder page into a 502.
     */
    private function compileBlocks(array $blocks, string $primaryColor, array $context = []): string
    {
        $normalized = [];
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            if (($block['type'] ?? '') === 'luna_custom_section') {
                if (($block['ai_flex']['source'] ?? '') === 'build_your_own') {
                    $block = AiFlexStructureContract::canonicalizeBlock($block);
                } else {
                    $block = AiFlexStructureContract::normalizePersistedBlock($block);
                }
            }

            $normalized[] = $block;
        }

        return CmsHtmlCompiler::compile(array_values($normalized), $primaryColor, $context);
    }

    /**
     * Build the approved website package consumed by the deployment connector.
     * Editable draft content never enters this payload. A page with an existing
     * published snapshot stays in the package while the customer prepares its
     * next draft revision.
     */
    public function publishedPackage(Website $website): array
    {
        if (config('cosmic_media.localize_remote_images', false)) {
            $remainingRemote = $this->mediaSafety->publishedProviderUrls($website);
            if ($remainingRemote !== []) {
                throw new \RuntimeException('Export blocked: published content still contains remote Unsplash/Pexels image URLs.');
            }
        }

        $theme = $website->published_theme_settings ?? $website->theme_settings ?? [];
        $primaryColor = $theme['primary'] ?? 'midnight';
        $themePalette = $primaryColor === 'my-brand'
            ? ((array) data_get($theme, 'brand_palette', data_get($theme, 'custom_brand_theme.palette', [])))
            : app(ThemeColorResolver::class)->palette((string) $primaryColor);
        // Global header is an immediately-saved website shell in the Builder.
        // Export/Live must mirror that current shell state (especially
        // overlay_header_on_banner) instead of resurrecting a stale published
        // header snapshot while the page content remains snapshot-based.
        $header = HeaderFooterVariantContract::normalizeHeader($website->global_header ?? $website->published_global_header);
        $footer = HeaderFooterVariantContract::normalizeFooter($website->global_footer ?? $website->published_global_footer);
        $pages = $website->pages()
            ->where(function ($query) {
                $query->where('status', 'published')
                    ->orWhereNotNull('published_html')
                    ->orWhereNotNull('published_blocks');
            })
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'parent_id', 'title', 'slug', 'page_type', 'page_style', 'published_page_style', 'published_html', 'published_blocks', 'blocks', 'seo_title', 'meta_description', 'og_image_url', 'canonical_url', 'is_indexable']);

        $publishedPostsByPage = $website->blogPosts()
            ->where('status', 'published')
            ->whereIn('page_id', $pages->where('page_type', 'blog')->pluck('id'))
            ->orderByDesc('is_featured')
            ->latest('published_at')
            ->get(['id', 'page_id', 'title', 'slug', 'excerpt', 'content', 'category', 'tags', 'image_url', 'is_featured', 'published_at', 'updated_at'])
            ->groupBy('page_id');

        $pagePaths = $this->pagePaths($pages);
        $header = $this->staticNavigationHeader($header, $pages, $pagePaths);
        $footer = $this->staticNavigationFooter($footer, $pages, $pagePaths);
        $commerceContext = $this->commerceExportContext($website);
        $contentContext = $this->structuredContentExportContext($website);
        $contentEntryPages = $this->structuredContentEntryPages($website);

        $publishedShellStyle = PageStyleRegistry::normalize($website->published_page_style ?: $website->page_style);
        $publishedTypography = is_array($theme['typography'] ?? null) ? $theme['typography'] : [];
        $publishedSectionLayout = is_array($theme['section_layout'] ?? null) ? $theme['section_layout'] : [];
        $publishedBackgroundStyle = is_array($theme['background_style'] ?? null) ? $theme['background_style'] : [];
        $publishedComponents = is_array($theme['components'] ?? null) ? $theme['components'] : [];
        $publishedShellContext = [
            'page_style' => $publishedShellStyle,
            'typography' => $publishedTypography,
            'section_layout' => $publishedSectionLayout,
            'background_style' => $publishedBackgroundStyle,
            'components' => $publishedComponents,
            'brand_palette' => $themePalette,
        ];

        return [
            'status' => 'success',
            'render_contract' => CmsHtmlCompiler::renderContractVersion(),
            'website_name' => $website->name,
            'theme_palette' => $themePalette,
            'global_header' => is_array($header) ? CmsHtmlCompiler::compile([$header], $primaryColor, $publishedShellContext) : '',
            'global_footer' => is_array($footer) ? CmsHtmlCompiler::compile([$footer], $primaryColor, $publishedShellContext) : '',
            'pages' => $pages
                ->flatMap(function (Page $page) use ($website, $primaryColor, $publishedPostsByPage, $pagePaths, $commerceContext, $contentContext, $publishedShellStyle, $publishedTypography, $publishedSectionLayout, $publishedBackgroundStyle, $publishedComponents) {
                    // Commerce pages are dynamic Laravel storefront endpoints. Keep
                    // them in the page registry/navigation map, but never export a
                    // static index.html that could shadow /shop, /cart, /checkout,
                    // /account or /order on the live connector.
                    if ($page->page_type === 'commerce') {
                        return [];
                    }

                    $blocks = $page->published_blocks ?? $page->blocks ?? [];
                    // A Posts / updates page owns its static directory. This keeps
                    // /blog, /news, and any future post hub aligned with its page slug.
                    $pagePath = $pagePaths[$page->id] ?? trim((string) $page->slug, '/');
                    $pageDirectory = $pagePath === '' ? '' : $pagePath;
                    $postDirectory = $page->page_type === 'blog'
                        ? ($pageDirectory ?: 'blog')
                        : null;
                    $posts = $publishedPostsByPage->get($page->id, collect())
                        ->map(fn ($post) => [
                            'title' => $post->title,
                            'slug' => $post->slug,
                            'excerpt' => $post->excerpt,
                            'content' => $post->content,
                            'category' => $post->category,
                            'tags' => $post->tags,
                            'image_url' => $post->image_url,
                            'is_featured' => $post->is_featured,
                            'url' => $postDirectory . '/' . $post->slug,
                        ])
                        ->values()
                        ->all();

                    $pagePackage = [[
                        'title' => $page->seo_title ?: $page->title,
                        'slug' => $page->slug,
                        'meta_description' => $page->meta_description,
                        'og_image' => $page->og_image_url,
                        'canonical_url' => $page->canonical_url,
                        'is_indexable' => (bool) $page->is_indexable,
                        'page_style' => $publishedShellStyle,
                        // Every page gets its own directory. This makes
                        // parent/child routes predictable: /about/team/.
                        'output_path' => ($pageDirectory === '' ? 'index.html' : $pageDirectory . '/index.html'),
                        // Recompile the approved snapshot for every live push. Reusing
                        // published_html would preserve stale localhost asset URLs that
                        // were generated before the export normalizer was introduced.
                        'html' => $this->compileBlocks(
                            $blocks,
                            $primaryColor,
                            $page->page_type === 'blog'
                                ? array_merge($commerceContext, $contentContext, ['blog_posts' => $posts, 'page_style' => $website->published_page_style ?: $website->page_style ?: $page->published_page_style ?: $page->page_style, 'typography' => $publishedTypography, 'section_layout' => $publishedSectionLayout, 'background_style' => $publishedBackgroundStyle, 'components' => $publishedComponents])
                                : array_merge($commerceContext, $contentContext, ['page_style' => $website->published_page_style ?: $website->page_style ?: $page->published_page_style ?: $page->page_style, 'typography' => $publishedTypography, 'section_layout' => $publishedSectionLayout, 'background_style' => $publishedBackgroundStyle, 'components' => $publishedComponents])
                        ),
                    ]];

                    if ($page->page_type !== 'blog') {
                        return $pagePackage;
                    }

                    $articlePackage = $publishedPostsByPage->get($page->id, collect())
                        ->map(fn ($post) => [
                            'title' => $post->title,
                            'slug' => $postDirectory . '/' . $post->slug,
                            'page_style' => $publishedShellStyle,
                            'output_path' => $postDirectory . '/' . $post->slug . '/index.html',
                            'meta_description' => $post->excerpt,
                            'structured_content' => true,
                            'og_type' => 'article',
                            'og_image' => $this->commerceAssetUrl($post->image_url),
                            'published_at' => optional($post->published_at)->toISOString(),
                            'updated_at' => optional($post->updated_at)->toISOString(),
                            // Compile the complete Blog page composition so the live
                            // article keeps the same Mini Header, Single Post body,
                            // Newsletter, and Latest Resources seen in Builder.
                            'html' => $this->compileBlocks($blocks, $primaryColor, array_merge($commerceContext, $contentContext, [
                                'blog_posts' => $posts,
                                'single_blog_post' => $post->toArray(),
                                'blog_index_url' => $postDirectory . '/',
                                'page_style' => $website->published_page_style ?: $website->page_style ?: $page->published_page_style ?: $page->page_style,
                                'typography' => $publishedTypography,
                                'section_layout' => $publishedSectionLayout,
                                'background_style' => $publishedBackgroundStyle,
                                'components' => $publishedComponents,
                            ])),
                        ])
                        ->all();

                    return array_merge($pagePackage, $articlePackage);
                })
                ->values()
                ->concat($contentEntryPages)
                ->unique('output_path')
                ->values()
                ->all(),
        ];
    }

    /**
     * Export every published structured-content entry as its own static HTML
     * body. The deployment connector wraps this body with the same global
     * header/footer, Manrope and Tailwind runtime used by normal Builder pages.
     */
    private function structuredContentEntryPages(Website $website): array
    {
        \App\Http\Controllers\ContentWorkspaceController::ensureDefaults($website);

        return $website->contentTypes()
            ->with([
                'singleTemplate',
                'entries' => fn ($query) => $query->where('status', 'published')->orderByDesc('is_featured')->latest('published_at'),
            ])
            ->orderBy('sort_order')
            ->get()
            ->flatMap(function ($type) use ($website) {
                $directory = trim((string) $type->slug, '/');
                if ($directory === '') return [];

                return $type->entries->map(function ($entry) use ($type, $directory, $website) {
                    $slug = trim((string) $entry->slug, '/');
                    if ($slug === '') return null;

                    return [
                        'title' => $entry->seo_title ?: $entry->title,
                        'slug' => $directory.'/'.$slug,
                        'page_style' => PageStyleRegistry::normalize($website->published_page_style ?: $website->page_style),
                        // Export clean entry URLs as directories so standard Nginx/Apache
                        // index resolution serves /blog/my-post/ without requiring
                        // a custom try_files rule for /blog/my-post.html.
                        'output_path' => $directory.'/'.$slug.'/index.html',
                        'html' => $this->structuredContentSingleHtml($type, $entry, $type->singleTemplate),
                        'meta_description' => $entry->seo_description ?: $entry->excerpt,
                        'structured_content' => true,
                        'og_type' => 'article',
                        'og_image' => $this->commerceAssetUrl($entry->og_image_url ?: $entry->featured_image_url),
                        'published_at' => optional($entry->published_at)->toISOString(),
                        'updated_at' => optional($entry->updated_at)->toISOString(),
                        'content_entry_id' => $entry->id,
                        'content_type_id' => $type->id,
                    ];
                })->filter();
            })
            ->values()
            ->all();
    }


    /**
     * Keep structured-content live export on the exact same semantic shell used
     * by the preview storefront. The global header runtime reads these data
     * attributes to choose overlay nav/logo/CTA contrast, while the shared
     * export CSS applies the same mini-hero offset and readability guards.
     */
    private function structuredContentSingleHtml($type, $entry, $template): string
    {
        $rendered = $this->contentTemplates->exportSingle($type, $entry, $template);
        $rendered = $this->normalizeStructuredContentAssetUrls($rendered);
        $miniBannerImage = trim((string) data_get($template?->metadata, 'mini_banner_image_url', ''));
        $surface = $this->dynamicTemplateFirstSurface($rendered);
        $website = $type->website;
        $header = HeaderFooterVariantContract::normalizeHeader($website?->global_header ?? $website?->published_global_header);
        $overlay = is_array($header) && (bool) ($header['overlay_header_on_banner'] ?? false);

        $contextPage = $website?->pages()->where('page_type', 'standard')->where('slug', $type->slug)->first();
        if (! $contextPage) {
            $contextPage = $website?->pages()->where('page_type', 'standard')
                ->where(function ($query) { $query->where('slug', 'home')->orWhere('slug', ''); })
                ->first();
        }
        $pageStyle = PageStyleRegistry::normalize($website?->published_page_style ?: $website?->page_style ?: $contextPage?->published_page_style ?: $contextPage?->page_style);
        if ($pageStyle === 'clean') {
            $surface = 'white';
        } elseif ($miniBannerImage !== '') {
            $surface = 'primary';
        } elseif (in_array($pageStyle, ['balanced','premium'], true)) {
            $surface = 'primary';
        }

        return '<section class="entry-template-runtime"'
            .' data-cosmic-dynamic-page-style="'.e($pageStyle).'"'
            .' data-cosmic-first-surface="'.e($surface).'"'
            .' data-cosmic-resolved-theme="'.e($surface).'"'
            .' data-cosmic-block-type="'.($miniBannerImage !== '' ? 'hero_background_image' : 'dynamic_single_hero').'"'
            .' data-cosmic-mini-banner-image="'.($miniBannerImage !== '' ? 'true' : 'false').'"'
            .($miniBannerImage !== '' ? ' style="--cosmic-mini-banner-image:url(\''.e($this->commerceAssetUrl($miniBannerImage)).'\')"' : '')
            .' data-cosmic-header-overlay="'.($overlay ? 'true' : 'false').'">'
            .$rendered
            .'</section>';
    }


    private function normalizeStructuredContentAssetUrls(string $html): string
    {
        $normalize = fn (string $url): string => $this->commerceAssetUrl($url);

        $html = preg_replace_callback('/\\b(src|poster)=("|\\\')([^"\\\']+)\\2/i', function (array $match) use ($normalize): string {
            $url = trim((string) ($match[3] ?? ''));
            if ($url === '' || str_starts_with($url, 'data:')) return $match[0];
            if (! str_starts_with($url, '/storage/') && ! preg_match('#^https?://(?:localhost|127\\.0\\.0\\.1|[^/]+\\.local)(?::\\d+)?/storage/#i', $url)) return $match[0];
            return $match[1].'='.$match[2].e($normalize($url)).$match[2];
        }, $html) ?? $html;

        return preg_replace_callback('/url\\(([^)]+)\\)/i', function (array $match) use ($normalize): string {
            $raw = trim((string) ($match[1] ?? ''), " \\t\\n\\r\\0\\x0B\\\"'");
            if (! str_starts_with($raw, '/storage/') && ! preg_match('#^https?://(?:localhost|127\\.0\\.0\\.1|[^/]+\\.local)(?::\\d+)?/storage/#i', $raw)) return $match[0];
            return "url('".e($normalize($raw))."')";
        }, $html) ?? $html;
    }


    private function dynamicTemplateFirstSurface(string $markup): string
    {
        if (! preg_match('/<[^>]*data-cosmic-mini-hero=["\\\']true["\\\'][^>]*>/i', $markup, $match)) {
            return 'surface';
        }

        $hero = strtolower((string) ($match[0] ?? ''));
        if (preg_match('/data-cosmic-(?:surface|theme)=["\\\'](white|surface|primary)["\\\']/i', $hero, $semantic)) {
            return strtolower((string) $semantic[1]);
        }
        foreach (['background:var(--dt-primary)', 'bg-primary', 'bg-black', 'bg-slate-9', 'bg-zinc-9', 'text-white'] as $signal) {
            if (str_contains($hero, $signal)) return 'primary';
        }
        if ((str_contains($hero, 'color-mix(') && str_contains($hero, 'var(--dt-bg)')) || str_contains($hero, 'var(--dt-surface)')) {
            return 'surface';
        }
        foreach (['bg-white', 'background:#fff', 'background:#ffffff', 'background:white', 'var(--dt-bg)'] as $signal) {
            if (str_contains($hero, $signal)) return 'white';
        }
        return 'surface';
    }


    private function structuredContentExportContext(Website $website): array
    {
        \App\Http\Controllers\ContentWorkspaceController::ensureDefaults($website);
        $types = $website->contentTypes()
            ->with(['entries' => fn ($query) => $query->where('status', 'published')->orderByDesc('is_featured')->latest('published_at')])
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->name,
                'singular_name' => $type->singular_name,
                'slug' => $type->slug,
                'entries' => $type->entries->map(fn ($entry) => [
                    'id' => $entry->id,
                    'title' => $entry->title,
                    'slug' => $entry->slug,
                    'excerpt' => $entry->excerpt,
                    'content' => $entry->content,
                    'category' => $entry->category,
                    'tags' => $entry->tags ?: [],
                    'featured_image_url' => $entry->featured_image_url,
                    'gallery' => $entry->gallery ?: [],
                    'custom_fields' => $entry->custom_fields ?: [],
                    'is_featured' => (bool) $entry->is_featured,
                    'published_at' => optional($entry->published_at)->toISOString(),
                    'updated_at' => optional($entry->updated_at)->toISOString(),
                    'url' => trim($type->slug, '/').'/'.trim($entry->slug, '/'),
                ])->values()->all(),
            ])->values()->all();

        return ['content_types' => $types];
    }

    private function commerceExportContext(Website $website): array
    {
        $settings = $website->commerceSetting()->first();
        // The exported Shop/catalog is a read-only storefront surface and should
        // continue to show published, visible products even when checkout is
        // temporarily disabled. Commerce `enabled` only gates transactional UI
        // such as Add to cart / Checkout / mini cart, not catalog visibility.
        if (! filled($website->preview_slug)) {
            return ['commerce' => [], 'commerce_runtime_endpoint' => ''];
        }

        // Most trial/service websites do not have a Commerce settings row yet.
        // Publishing those sites must still export an empty storefront context
        // using Cosmic's default currency instead of dereferencing null.
        $currency = strtoupper((string) ($settings?->currency ?: config('cosmic-commerce.default_currency', 'USD')));
        $decimals = (int) config("cosmic-commerce.currencies.$currency.decimals", 2);
        $previews = app(PreviewDeploymentService::class);
        $runtimeBase = rtrim((string) config('services.cosmic.asset_base_url', config('app.url')), '/');
        $runtimeEndpoint = $runtimeBase.'/commerce/runtime/'.$website->preview_slug.'/catalog';
        $assetUrl = fn (?string $url): string => $this->commerceAssetUrl($url);

        $products = $website->commerceProducts()
            ->with(['images', 'categories', 'options.values'])
            ->where('status', 'published')
            ->where('visibility', '!=', 'hidden')
            ->orderByDesc('is_featured')
            ->latest('updated_at')
            ->get()
            ->map(fn ($product) => [
                'id' => $product->id,
                'title' => $product->title,
                'slug' => $product->slug,
                'storefront_url' => $previews->url($website, 'product/'.$product->slug),
                'regular_price_minor' => $product->regular_price_minor,
                'sale_price_minor' => $product->sale_price_minor,
                'track_inventory' => (bool) $product->track_inventory,
                'stock_quantity' => $product->stock_quantity,
                'allow_backorders' => (bool) $product->allow_backorders,
                'stock_status' => $product->stock_status,
                'is_featured' => (bool) $product->is_featured,
                'featured_image_url' => $assetUrl($product->featured_image_url),
                'featured_image_alt' => $product->featured_image_alt,
                'category_ids' => $product->categories->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                'gallery' => $product->images->map(fn ($image) => ['url' => $assetUrl($image->url), 'alt_text' => $image->alt_text])->values()->all(),
                'options' => $product->options->map(fn ($option) => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'values' => $option->values->where('is_active', true)->map(fn ($value) => ['id' => $value->id, 'label' => $value->label, 'swatch_hex' => $value->swatch_hex])->values()->all(),
                ])->values()->all(),
            ])->values()->all();

        $categories = $website->commerceProductCategories()->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'image_url' => $assetUrl($category->image_url),
                'image_alt' => $category->image_alt,
                'storefront_url' => $previews->url($website, 'shop/category/'.$category->slug),
            ])->values()->all();

        return [
            'commerce_runtime_endpoint' => $runtimeEndpoint,
            'commerce' => [
                'currency' => $currency,
                'currency_decimals' => $decimals,
                'storefront_url' => $previews->url($website, 'shop'),
                'products' => $products,
                'categories' => $categories,
                'countries' => collect(config('cosmic-commerce.countries', []))->map(fn ($name, $code) => ['code' => $code, 'name' => $name])->values()->all(),
            ],
        ];
    }

    private function commerceAssetUrl(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '' || str_starts_with($url, 'data:')) return $url;
        if (preg_match('#^(?:https?:)?//([^/]+)(/.*)?$#i', $url, $matches)) {
            $host = strtolower(preg_replace('/:\d+$/', '', $matches[1]) ?? $matches[1]);
            if (! in_array($host, ['localhost', '127.0.0.1', '::1'], true) && ! str_ends_with($host, '.local')) return $url;
            $url = $matches[2] ?? '/';
        }
        $base = rtrim((string) config('services.cosmic.asset_base_url', config('app.url')), '/');
        return $base.'/'.ltrim(str_replace('\\', '/', $url), '/');
    }

    /**
     * Convert menu targets to static clean URLs and ensure every published page
     * is represented in the live navigation. Cosmic intentionally supports a
     * maximum of three page levels: page, child, and nested page.
     */
    private function staticNavigationHeader(?array $header, $pages, array $pagePaths): ?array
    {
        if (! is_array($header)) {
            return $header;
        }

        $header['menu'] = is_array($header['menu'] ?? null) ? $header['menu'] : [];

        $publishedTargets = [];
        foreach ($pages as $page) {
            $path = trim((string) ($pagePaths[$page->id] ?? $page->slug), '/');
            $url = $path === '' ? './' : $path . '/';
            $publishedTargets[strtolower($path)] = $url;
            $publishedTargets[strtolower(trim((string) $page->slug, '/'))] ??= $url;
        }

        // First preserve the customer's custom menu while converting any page
        // targets it already contains to the correct nested static URL.
        $menu = $this->mapMenuItems($header['menu'], function (array $item) use ($publishedTargets): array {
            $item['url'] = $this->staticNavigationTarget((string) ($item['url'] ?? ''), $publishedTargets);
            return $item;
        }, 0);

        // The registered header menu is the single source of truth for live
        // navigation. Published pages remain exportable, but they are never
        // auto-appended to the menu unless the customer explicitly registered
        // them in Website Settings. This preserves custom order, nesting,
        // external URLs, anchors, and intentionally hidden pages.
        $header['menu'] = $menu;

        if (array_key_exists('cta_url', $header)) {
            $header['cta_url'] = $this->staticNavigationTarget((string) $header['cta_url'], $publishedTargets);
        }

        return $header;
    }

    private function staticNavigationFooter(?array $footer, $pages, array $pagePaths): ?array
    {
        if (!is_array($footer)) return $footer;

        $publishedTargets=[];
        foreach($pages as $page){
            $path=trim((string)($pagePaths[$page->id]??$page->slug),'/');
            $url=$path===''?'./':$path.'/';
            $publishedTargets[strtolower($path)]=$url;
            $publishedTargets[strtolower(trim((string)$page->slug,'/'))]??=$url;
        }

        foreach(['privacy_url','terms_url'] as $key){
            if(array_key_exists($key,$footer)){
                $footer[$key]=$this->staticNavigationTarget((string)$footer[$key],$publishedTargets);
            }
        }

        if(is_array($footer['mega_footer']??null)){
            $mega=$footer['mega_footer'];
            if(array_key_exists('primary_url',$mega)){
                $mega['primary_url']=$this->staticNavigationTarget((string)$mega['primary_url'],$publishedTargets);
            }
            if(is_array($mega['columns']??null)){
                $mega['columns']=array_values(array_map(function($column) use($publishedTargets){
                    if(!is_array($column)) return $column;
                    $items=is_array($column['items']??null)?$column['items']:[];
                    $column['items']=array_values(array_map(function($item) use($publishedTargets){
                        if(!is_array($item)) return $item;
                        $item['url']=$this->staticNavigationTarget((string)($item['url']??'#'),$publishedTargets);
                        return $item;
                    },$items));
                    return $column;
                },$mega['columns']));
            }
            $footer['mega_footer']=$mega;
        }

        if(is_array($footer['social_links']??null)){
            $footer['social_links']=array_values(array_map(function($item) use($publishedTargets){
                if(!is_array($item)) return $item;
                $item['url']=$this->staticNavigationTarget((string)($item['url']??'#'),$publishedTargets);
                return $item;
            },$footer['social_links']));
        }

        return $footer;
    }

    /** Recursively map menu items, hard-capped at Cosmic's three menu levels. */
    private function mapMenuItems(array $items, callable $callback, int $depth): array
    {
        if ($depth >= 3) {
            return [];
        }

        return array_values(array_map(function ($item) use ($callback, $depth) {
            if (! is_array($item)) {
                return $item;
            }

            $item = $callback($item);
            $children = is_array($item['children'] ?? null) ? $item['children'] : [];
            $item['children'] = $this->mapMenuItems($children, $callback, $depth + 1);

            return $item;
        }, $items));
    }

    /** Build the published page hierarchy used by the static header. */
    private function publishedPageMenuTree($pages, array $pagePaths): array
    {
        $childrenByParent = $pages
            ->groupBy(fn (Page $page) => $page->parent_id ?: 0);

        $build = function ($parentId, int $depth) use (&$build, $childrenByParent, $pagePaths): array {
            if ($depth >= 3) {
                return [];
            }

            return $childrenByParent->get($parentId, collect())
                ->map(function (Page $page) use (&$build, $depth, $pagePaths) {
                    $path = trim((string) ($pagePaths[$page->id] ?? $page->slug), '/');

                    return [
                        'label' => $page->title,
                        'url' => $path === '' ? './' : $path . '/',
                        '_page_slug' => trim((string) $page->slug, '/'),
                        '_page_title' => $page->title,
                        'children' => $build($page->id, $depth + 1),
                    ];
                })
                ->values()
                ->all();
        };

        return $build(0, 0);
    }

    /** Merge generated page links into the existing customer-authored menu. */
    private function mergePublishedMenu(array $existing, array $published, int $depth): array
    {
        if ($depth >= 3) {
            return [];
        }

        foreach ($published as $pageItem) {
            $matchIndex = $this->findMenuMatch($existing, $pageItem);

            if ($matchIndex === null) {
                $existing[] = [
                    'label' => $pageItem['label'],
                    'url' => $pageItem['url'],
                    'children' => $this->mergePublishedMenu([], $pageItem['children'] ?? [], $depth + 1),
                ];
                continue;
            }

            $existing[$matchIndex]['url'] = $pageItem['url'];
            $currentChildren = is_array($existing[$matchIndex]['children'] ?? null)
                ? $existing[$matchIndex]['children']
                : [];
            $existing[$matchIndex]['children'] = $this->mergePublishedMenu(
                $currentChildren,
                $pageItem['children'] ?? [],
                $depth + 1
            );
        }

        return array_values($existing);
    }

    private function findMenuMatch(array $items, array $pageItem): ?int
    {
        $pageUrl = strtolower(trim((string) ($pageItem['url'] ?? ''), './'));
        $pageSlug = $this->normaliseMenuKey((string) ($pageItem['_page_slug'] ?? ''));
        $pageTitle = $this->normaliseMenuKey((string) ($pageItem['_page_title'] ?? $pageItem['label'] ?? ''));
        $pageFirstWord = explode('-', $pageTitle)[0] ?? '';

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $itemUrl = strtolower(trim((string) ($item['url'] ?? ''), './'));
            $itemLabel = $this->normaliseMenuKey((string) ($item['label'] ?? ''));

            if (($pageUrl !== '' && $itemUrl === $pageUrl)
                || ($pageSlug !== '' && $itemUrl === $pageSlug)
                || ($pageTitle !== '' && $itemLabel === $pageTitle)
                || ($pageFirstWord !== '' && $itemLabel === $pageFirstWord)) {
                return $index;
            }
        }

        return null;
    }

    private function normaliseMenuKey(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }

    private function staticNavigationTarget(string $target, array $publishedTargets): string
    {
        $target = trim($target);

        if ($target === '' || $target === '#' || str_starts_with($target, '#') || preg_match('/^(https?:|mailto:|tel:)/i', $target)) {
            return $target;
        }

        $slug = strtolower(trim(preg_replace('/\.html$/i', '', $target), '/'));

        if (! isset($publishedTargets[$slug])) {
            return preg_match('/^[a-z0-9-]+$/', $slug) ? '#' : $target;
        }

        return $publishedTargets[$slug];
    }

    /** Build safe clean-URL folders from the stored parent chain. */
    private function pagePaths($pages): array
    {
        $byId = $pages->keyBy('id');
        $paths = [];

        foreach ($pages as $page) {
            $segments = [];
            $cursor = $page;
            $guard = 0;
            while ($cursor && $guard++ < 3) {
                $slug = trim((string) $cursor->slug, '/');
                if ($slug !== '' && ! ($cursor->parent_id === null && $slug === 'home')) {
                    array_unshift($segments, $slug);
                }
                $cursor = $cursor->parent_id ? $byId->get($cursor->parent_id) : null;
            }
            $paths[$page->id] = implode('/', $segments);
        }

        return $paths;
    }

}
