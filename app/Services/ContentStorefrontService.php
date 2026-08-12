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
use Illuminate\Support\Str;

class ContentStorefrontService
{
    public function __construct(
        private readonly ThemeColorResolver $themes,
        private readonly PreviewDeploymentService $previews,
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
            // Let a normal Builder page own the archive slug when one exists.
            if ($website->pages()->where('slug', $type->slug)->where('page_type', '!=', 'commerce')->exists()) {
                return null;
            }
            return $this->archive($website, $type, $previewSlug, $request);
        }

        if (count($segments) !== 2) return null;
        $entrySlug = rawurldecode($segments[1]);
        $entry = $type->entries()->where('slug', $entrySlug)->where('status', 'published')->first();
        if (! $entry) return null;

        return $this->entry($website, $type, $entry, $previewSlug);
    }

    private function archive(Website $website, ContentType $type, string $previewSlug, Request $request): Response
    {
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
        $siteShell = $this->siteShell($website, $previewSlug, $themeKey);

        $payload = array_merge($data, [
            'viewMode' => $viewMode,
            'website' => $website,
            'previewSlug' => $previewSlug,
            'palette' => $palette,
            'siteHeaderHtml' => $siteShell['header'],
            'siteFooterHtml' => $siteShell['footer'],
            'useSiteShell' => $siteShell['header'] !== '',
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
        $header = $website->published_global_header ?? $website->global_header;
        $footer = $website->published_global_footer ?? $website->global_footer;
        if (! is_array($header) && ! is_array($footer)) return ['header' => '', 'footer' => ''];

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

        return [
            'header' => is_array($header) ? CmsHtmlCompiler::compile([$header], $primaryColor) : '',
            'footer' => is_array($footer) ? CmsHtmlCompiler::compile([$footer], $primaryColor) : '',
        ];
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
