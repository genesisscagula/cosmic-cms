<?php

namespace App\Services;

use App\Helpers\CmsHtmlCompiler;
use App\Http\Controllers\ContentWorkspaceController;
use App\Models\ContentEntry;
use App\Models\ContentType;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use App\Support\PageStyleRegistry;

class ContentStorefrontService
{
    public function __construct(
        private readonly ThemeColorResolver $themes,
        private readonly PreviewDeploymentService $previews,
        private readonly DynamicContentTemplateRenderer $templateRenderer,
    ) {
    }

    public function renderIfContentPath(string $previewSlug, ?string $path, Request $request): ?Response
    {
        $normalized = trim((string) $path, '/');
        if ($normalized === '') return null;

        $website = Website::query()->where('preview_slug', $previewSlug)->first();
        if (! $website) return null;

        ContentWorkspaceController::ensureDefaults($website);
        $firstSegment = rawurldecode(explode('/', $normalized, 2)[0] ?? '');
        $type = $website->contentTypes()->where('slug', $firstSegment)->first();
        if (! $type) return null;

        $segments = array_values(array_filter(explode('/', $normalized), fn ($value) => $value !== ''));
        if (count($segments) === 1) {
            // Once a content type has an installed Standard Page with the same slug, that
            // Builder page becomes the canonical public archive experience. Its Content
            // Loop Sparks still read from this structured-content engine, while direct
            // single-entry routes remain dynamic. Without an installed page, keep the
            // built-in archive renderer as the SEO-safe fallback.
            $installedPage = $website->pages()->where('slug', $type->slug)->where('page_type', 'standard')->exists();
            if ($installedPage) {
                // A freshly installed Content Type page may not be part of the latest
                // static preview deployment yet. Do not fall through to the generic
                // 404 in that window: serve the dynamic archive until the Builder page
                // exists in the deployed preview, then let the normal static page win.
                $deployedPage = $this->previews->resolveFile($previewSlug, $type->slug);
                if ($deployedPage !== null) return null;
            }

            return $this->archive($website, $type, $previewSlug, $request);
        }

        if (count($segments) !== 2) return null;
        $entrySlug = rawurldecode($segments[1]);
        $entry = $type->entries()->where('slug', $entrySlug)->first();
        if (! $entry) return null;

        // Published entries are public on the preview storefront. Draft/pending entries are
        // also previewable from the authenticated website workspace so the editor's View
        // action never falls through to the static 404 page immediately after saving.
        if ($entry->status !== 'published') {
            $user = $request->user();
            if (! $user || ! Gate::forUser($user)->allows('update', $website)) return null;
        }

        return $this->entry($website, $type, $entry, $previewSlug);
    }

    private function archive(Website $website, ContentType $type, string $previewSlug, Request $request): Response
    {
        $type->loadMissing('archiveTemplate');
        $entries = $type->entries()
            ->where('status', 'published')
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->get();

        $category = trim((string) $request->query('category', ''));
        $tag = trim((string) $request->query('tag', ''));
        if ($category !== '') $entries = $entries->filter(fn (ContentEntry $entry) => $entry->category === $category)->values();
        if ($tag !== '') $entries = $entries->filter(fn (ContentEntry $entry) => in_array($tag, $entry->tags ?: [], true))->values();

        return $this->response($website, $previewSlug, 'archive', [
            'contentType' => $type,
            'entries' => $entries,
            'activeEntry' => null,
            'relatedEntries' => collect(),
            'categories' => $type->entries()->where('status', 'published')->whereNotNull('category')->pluck('category')->filter()->unique()->sort()->values(),
            'tags' => $type->entries()->where('status', 'published')->get()->flatMap(fn (ContentEntry $entry) => $entry->tags ?: [])->filter()->unique()->sort()->values(),
            'activeCategory' => $category,
            'activeTag' => $tag,
            'title' => $type->name,
            'description' => $type->description ?: 'Explore the latest '.$type->name.'.',
            'dynamicMiniBannerImage' => trim((string) data_get($type->archiveTemplate?->metadata, 'mini_banner_image_url', '')),
        ]);
    }

    private function entry(Website $website, ContentType $type, ContentEntry $entry, string $previewSlug): Response
    {
        $tags = $entry->tags ?: [];
        $related = $type->entries()
            ->where('status', 'published')
            ->where('id', '!=', $entry->id)
            ->latest('published_at')
            ->get()
            ->sortByDesc(function (ContentEntry $candidate) use ($entry, $tags): int {
                $score = 0;
                if ($entry->category && $candidate->category === $entry->category) $score += 4;
                $score += count(array_intersect($tags, $candidate->tags ?: []));
                return $score;
            })
            ->take(3)
            ->values();

        $type->loadMissing('singleTemplate');
        $renderedTemplateMarkup = $this->templateRenderer->renderSingle($type, $entry, $type->singleTemplate);

        return $this->response($website, $previewSlug, 'entry', [
            'contentType' => $type,
            'entries' => collect(),
            'activeEntry' => $entry,
            'relatedEntries' => $related,
            'categories' => collect(),
            'tags' => collect(),
            'activeCategory' => '',
            'activeTag' => '',
            'title' => $entry->seo_title ?: $entry->title,
            'description' => $entry->seo_description ?: $entry->excerpt,
            'renderedTemplateMarkup' => $renderedTemplateMarkup,
            'dynamicMiniBannerImage' => trim((string) data_get($type->singleTemplate?->metadata, 'mini_banner_image_url', '')),
        ]);
    }

    private function response(Website $website, string $previewSlug, string $viewMode, array $data): Response
    {
        $themeSettings = is_array($website->theme_settings) && $website->theme_settings !== []
            ? $website->theme_settings
            : (is_array($website->published_theme_settings) ? $website->published_theme_settings : []);
        $themeKey = (string) data_get($themeSettings, 'primary', 'midnight');
        $brandPalette = data_get($themeSettings, 'brand_palette');
        $brandPalette = is_array($brandPalette) ? $brandPalette : data_get($themeSettings, 'custom_brand_theme.palette');
        $palette = $themeKey === 'my-brand' && is_array($brandPalette)
            ? $this->normalizePalette($brandPalette, 'midnight')
            : $this->normalizePalette($this->themes->palette($themeKey), $themeKey);
        $palette = $this->ensureReadablePalette($palette);
        $siteShell = $this->siteShell($website, $previewSlug, $themeKey);
        $dynamicContext = $this->dynamicPageContext(
            $website,
            $data['contentType'] ?? null,
            $siteShell,
            (string) ($data['renderedTemplateMarkup'] ?? ''),
            (string) ($data['dynamicMiniBannerImage'] ?? '')
        );

        $payload = array_merge($data, [
            'viewMode' => $viewMode,
            'website' => $website,
            'previewSlug' => $previewSlug,
            'palette' => $palette,
            'siteHeaderHtml' => $siteShell['header'],
            'siteFooterHtml' => $siteShell['footer'],
            'useSiteShell' => $siteShell['header'] !== '',
            'headerOverlayEnabled' => $dynamicContext['header_overlay_enabled'],
            'dynamicPageStyle' => $dynamicContext['page_style'],
            'dynamicPageStyleDirection' => $dynamicContext['page_style_direction'],
            'dynamicFirstSurface' => $dynamicContext['first_surface'],
            'dynamicMiniBannerImage' => $this->assetUrl((string) ($data['dynamicMiniBannerImage'] ?? '')),
            'archiveUrl' => fn (ContentType $type): string => (string) $this->previews->url($website, $type->slug),
            'entryUrl' => fn (ContentType $type, ContentEntry $entry): string => (string) $this->previews->url($website, $type->slug.'/'.$entry->slug),
            'assetUrl' => fn (?string $url): string => $this->assetUrl($url),
        ]);

        return response()->view('content.storefront', $payload, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    private function siteShell(Website $website, string $previewSlug, string $primaryColor): array
    {
        // Preview must mirror the current Builder shell, including the latest uploaded logo.
        // Published shell values can lag behind Builder edits until the next publish, so prefer
        // the current global blocks and only fall back to the published snapshot.
        $header = $website->global_header ?? $website->published_global_header;
        $footer = $website->global_footer ?? $website->published_global_footer;
        if (! is_array($header) && ! is_array($footer)) return ['header' => '', 'footer' => '', 'header_overlay_enabled' => false];

        if (is_array($header)) {
            $pageTargets = $website->pages()->where('page_type', '!=', 'commerce')->get()
                ->mapWithKeys(fn ($page) => [strtolower(trim((string) $page->slug, '/')) => (string) $this->previews->urlForPage($website, $page)])
                ->filter()->all();
            $contentSlugs = $website->contentTypes()->pluck('slug')->map(fn ($slug) => strtolower((string) $slug))->all();

            $resolve = function (?string $url) use ($website, $pageTargets, $contentSlugs): string {
                $raw = trim((string) $url);
                if ($raw === '' || $raw === '#') return $raw === '' ? '#' : $raw;
                if (str_starts_with($raw, '#') || preg_match('#^(?:https?:)?//#i', $raw) || preg_match('#^(?:mailto|tel):#i', $raw)) return $raw;
                $path = trim((string) parse_url($raw, PHP_URL_PATH), '/');
                if ($path === '' || strtolower($path) === 'home') return (string) $this->previews->url($website);
                $key = strtolower($path);
                if (isset($pageTargets[$key])) return $pageTargets[$key];
                $first = strtolower(explode('/', $path)[0] ?? '');
                if (in_array($first, $contentSlugs, true) || in_array($first, ['shop','product','cart','checkout','account','order','order-lookup'], true)) {
                    return (string) $this->previews->url($website, $path);
                }
                $last = strtolower(basename($path));
                if (isset($pageTargets[$last])) return $pageTargets[$last];
                return (string) $this->previews->url($website, $path);
            };

            $mapMenu = function (array $items) use (&$mapMenu, $resolve): array {
                return array_values(array_map(function ($item) use (&$mapMenu, $resolve) {
                    if (! is_array($item)) return $item;
                    $item['url'] = $resolve($item['url'] ?? '#');
                    $item['children'] = $mapMenu(is_array($item['children'] ?? null) ? $item['children'] : []);
                    return $item;
                }, $items));
            };
            $header['menu'] = $mapMenu(is_array($header['menu'] ?? null) ? $header['menu'] : []);
            if (array_key_exists('cta_url', $header)) $header['cta_url'] = $resolve($header['cta_url']);
        }

        $pageStyle = strtolower(trim((string) ($website->page_style ?: $website->published_page_style ?: 'auto')));
        $shellContext = ['page_style' => $pageStyle];

        return [
            'header' => is_array($header) ? CmsHtmlCompiler::compile([$header], $primaryColor, $shellContext) : '',
            'footer' => is_array($footer) ? CmsHtmlCompiler::compile([$footer], $primaryColor, $shellContext) : '',
            'header_overlay_enabled' => is_array($header) && (bool) ($header['overlay_header_on_banner'] ?? false),
        ];
    }

    /**
     * Dynamic entries are not independent mini-sites. They inherit the visual
     * contract of the Builder page that represents their content type, then
     * fall back to the website home/first standard page. This keeps Single
     * templates aligned with Page Style and the global header overlay switch.
     */
    private function dynamicPageContext(Website $website, mixed $contentType, array $siteShell, string $renderedTemplateMarkup = '', string $miniBannerImage = ''): array
    {
        $typeSlug = $contentType instanceof ContentType ? trim((string) $contentType->slug, '/') : '';

        $contextPage = $typeSlug !== ''
            ? $website->pages()->where('page_type', 'standard')->where('slug', $typeSlug)->first()
            : null;

        if (! $contextPage) {
            $contextPage = $website->pages()->where('page_type', 'standard')
                ->where(function ($query) {
                    $query->where('slug', 'home')->orWhere('slug', '');
                })->first();
        }

        if (! $contextPage) {
            $contextPage = $website->pages()->where('page_type', 'standard')->orderBy('id')->first();
        }

        $pageStyle = trim((string) ($website->page_style ?: $website->published_page_style ?: $contextPage?->page_style ?: $contextPage?->published_page_style ?: 'auto'));
        $style = PageStyleRegistry::all()[$pageStyle] ?? null;
        $pattern = PageStyleRegistry::pattern($pageStyle);
        $firstSurface = strtolower((string) ($pattern[0] ?? 'primary'));
        if (! in_array($firstSurface, ['white', 'surface', 'primary'], true)) $firstSurface = 'primary';

        // Dynamic templates inherit the Builder page-style contract. Clean keeps a
        // light mini banner when no image is selected; Balanced/Premium and other
        // branded styles use the primary surface. Any uploaded mini-banner image
        // always becomes a media-led primary-overlay hero for reliable contrast.
        if ($pageStyle === 'clean') {
            $firstSurface = 'white';
        } elseif (trim($miniBannerImage) !== '') {
            $firstSurface = 'primary';
        } elseif (in_array($pageStyle, ['auto','balanced','premium','luxury','executive','refined','glass','cinematic','bold','creative','dynamic','contrast','immersive','startup','agency'], true)) {
            $firstSurface = 'primary';
        }

        // Dynamic Single templates own their mini-hero surface. Do not let the
        // Builder page-style pattern blindly force a light or white header over
        // a light editorial mini hero (or dark text over a primary mini hero).
        // Prefer an explicit semantic marker from saved/Luna templates, then
        // safely infer older template markup before falling back to page style.
        if (trim($miniBannerImage) === '' && trim($renderedTemplateMarkup) !== '' && !in_array($pageStyle, ['clean','balanced','premium','luxury','executive','refined','glass','cinematic','bold','creative','dynamic','contrast','immersive','startup','agency'], true)) {
            $firstSurface = $this->dynamicTemplateFirstSurface($renderedTemplateMarkup, $firstSurface);
        }

        return [
            'page_style' => $pageStyle !== '' ? $pageStyle : 'auto',
            'page_style_direction' => (string) ($style['direction'] ?? 'clean'),
            'first_surface' => $firstSurface,
            'header_overlay_enabled' => (bool) ($siteShell['header_overlay_enabled'] ?? false),
        ];
    }

    private function dynamicTemplateFirstSurface(string $markup, string $fallback = 'surface'): string
    {
        $fallback = in_array($fallback, ['white', 'surface', 'primary'], true) ? $fallback : 'surface';

        // Only inspect the semantic mini hero, not the post body or supporting Sparks.
        if (! preg_match('/<[^>]*data-cosmic-mini-hero=["\']true["\'][^>]*>/i', $markup, $match)) {
            return $fallback;
        }

        $heroTag = strtolower((string) ($match[0] ?? ''));

        if (preg_match('/data-cosmic-(?:surface|theme)=["\'](white|surface|primary)["\']/i', $heroTag, $semantic)) {
            return strtolower((string) $semantic[1]);
        }

        // Strong primary/dark signals. Luna/premade templates can opt into this
        // explicitly with data-cosmic-surface="primary" going forward.
        $primarySignals = [
            'background:var(--dt-primary)',
            'background-color:var(--dt-primary)',
            'bg-primary',
            'bg-emerald-8',
            'bg-slate-9',
            'bg-zinc-9',
            'bg-neutral-9',
            'bg-black',
            'text-white',
        ];
        foreach ($primarySignals as $signal) {
            if (str_contains($heroTag, $signal)) return 'primary';
        }

        // The built-in dynamic presets use a subtle primary tint mixed into the
        // page background. That is still a light/surface mini banner, not a
        // primary hero, so overlay navigation must use the dark treatment.
        if (str_contains($heroTag, 'color-mix(') && str_contains($heroTag, 'var(--dt-bg)')) {
            return 'surface';
        }

        $whiteSignals = ['bg-white', 'background:#fff', 'background:#ffffff', 'background:white', 'var(--dt-bg)'];
        foreach ($whiteSignals as $signal) {
            if (str_contains($heroTag, $signal)) return 'white';
        }

        $surfaceSignals = ['bg-slate-50', 'bg-slate-100', 'bg-gray-50', 'bg-zinc-50', 'bg-neutral-50', 'var(--dt-surface)'];
        foreach ($surfaceSignals as $signal) {
            if (str_contains($heroTag, $signal)) return 'surface';
        }

        return $fallback;
    }

    private function normalizePalette(array $palette, string $fallbackTheme): array
    {
        $fallback = $this->themes->palette($fallbackTheme);
        $pick = function (string $key, string $fallbackHex) use ($palette): string {
            $value = strtoupper(trim((string) ($palette[$key] ?? '')));
            return preg_match('/^#[0-9A-F]{6}$/', $value) ? $value : $fallbackHex;
        };
        return [
            'primary' => $pick('primary', $fallback['primary']),
            'accent' => $pick('accent', $fallback['accent']),
            'surface' => $pick('surface', $fallback['surface']),
            'text' => $pick('text', $fallback['text']),
            'muted' => $pick('muted', '#64748B'),
            'border' => $pick('border', '#E2E8F0'),
            'background' => $pick('background', '#FFFFFF'),
        ];
    }

    /**
     * Some dark theme families intentionally carry very light text tokens. Structured
     * content can render on a light editorial surface, so enforce readable foreground,
     * muted and border colors against the actual storefront background.
     */
    private function ensureReadablePalette(array $palette): array
    {
        $background = (string) ($palette['background'] ?? '#FFFFFF');
        $isLight = $this->relativeLuminance($background) >= 0.48;

        if ($this->contrastRatio((string) ($palette['text'] ?? '#0F172A'), $background) < 4.5) {
            $palette['text'] = $isLight ? '#0F172A' : '#F8FAFC';
        }
        if ($this->contrastRatio((string) ($palette['muted'] ?? '#64748B'), $background) < 3.5) {
            $palette['muted'] = $isLight ? '#475569' : '#CBD5E1';
        }
        if ($this->contrastRatio((string) ($palette['border'] ?? '#E2E8F0'), $background) < 1.25) {
            $palette['border'] = $isLight ? '#CBD5E1' : '#334155';
        }

        // Keep cards visibly separated from the page even when the theme's surface
        // token is effectively identical to the background.
        if ($this->contrastRatio((string) ($palette['surface'] ?? '#F8FAFC'), $background) < 1.08) {
            $palette['surface'] = $isLight ? '#F8FAFC' : '#111827';
        }

        return $palette;
    }

    private function contrastRatio(string $a, string $b): float
    {
        $l1 = $this->relativeLuminance($a);
        $l2 = $this->relativeLuminance($b);
        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);
        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function relativeLuminance(string $hex): float
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) return 1.0;

        $channels = array_map(function (string $part): float {
            $value = hexdec($part) / 255;
            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, [substr($hex, 0, 2), substr($hex, 2, 2), substr($hex, 4, 2)]);

        return (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);
    }

    private function assetUrl(?string $url): string
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
}
