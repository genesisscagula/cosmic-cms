<?php

namespace App\Services;

use App\Models\Page;
use App\Models\Website;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PreviewDeploymentService
{
    public function __construct(private readonly PagePublisher $publisher) {}

    public function deploy(Website $website): string
    {
        $website = $website->fresh();
        $slug = $this->ensureSlug($website);
        $package = $this->publisher->publishedPackage($website);
        $pages = $package['pages'] ?? [];

        if ($pages === []) {
            throw new RuntimeException('Preview deployment requires at least one published page.');
        }

        $disk = Storage::disk(config('cosmic_preview.disk', 'local'));
        $root = $this->websiteRoot($slug);
        $temporaryRoot = $root.'-next-'.Str::lower(Str::random(8));
        $siteBaseUrl = rtrim((string) $this->url($website), '/');
        // Every staging site shares one prebuilt Tailwind bundle from Cosmic's public folder.
        // Publishing a preview never copies or recompiles Tailwind per website.
        $hasSharedTailwindCss = $this->sharedTailwindAvailable();

        try {
            foreach ($pages as $page) {
                $outputPath = $this->safeOutputPath((string) ($page['output_path'] ?? ''));
                $html = $this->composeHtml(
                    (string) ($page['html'] ?? ''),
                    (string) ($package['global_header'] ?? ''),
                    (string) ($package['global_footer'] ?? ''),
                    (string) ($page['title'] ?? $website->name),
                    $outputPath,
                    (string) ($page['meta_description'] ?? ''),
                    array_merge($page, [
                        'canonical_url' => $this->canonicalUrlForOutputPath($siteBaseUrl, $outputPath),
                        'website_name' => (string) ($package['website_name'] ?? $website->name),
                    ]),
                    (array) ($package['theme_palette'] ?? []),
                    $hasSharedTailwindCss,
                );

                if (! $disk->put($temporaryRoot.'/'.$outputPath, $html)) {
                    throw new RuntimeException("Unable to write preview page: {$outputPath}");
                }
            }

            $sitemap = $this->buildSitemapXml($pages, $siteBaseUrl);
            if (! $disk->put($temporaryRoot.'/sitemap.xml', $sitemap)) {
                throw new RuntimeException('Unable to write preview sitemap.xml');
            }
            if (! $disk->put($temporaryRoot.'/robots.txt', $this->buildRobotsTxt($siteBaseUrl))) {
                throw new RuntimeException('Unable to write preview robots.txt');
            }

            $this->activateDeployment($temporaryRoot, $root);

            $website->forceFill([
                'last_preview_deployed_at' => now(),
                'preview_deployment_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $disk->deleteDirectory($temporaryRoot);
            $website->forceFill(['preview_deployment_error' => $exception->getMessage()])->save();
            throw $exception;
        }

        return $this->url($website->fresh());
    }


    public function remove(Website $website): void
    {
        if (! filled($website->preview_slug)) {
            return;
        }

        Storage::disk(config('cosmic_preview.disk', 'local'))
            ->deleteDirectory($this->websiteRoot($website->preview_slug));
    }

    private function activateDeployment(string $temporaryRoot, string $root): void
    {
        $disk = Storage::disk(config('cosmic_preview.disk', 'local'));
        $backupRoot = $root.'-previous-'.Str::lower(Str::random(8));
        $hadPreviousDeployment = $disk->exists($root);

        try {
            if ($hadPreviousDeployment) {
                foreach ($disk->allFiles($root) as $file) {
                    $relative = Str::after($file, $root.'/');
                    if (! $this->moveFileEnsuringDirectory($disk, $file, $backupRoot.'/'.$relative)) {
                        throw new RuntimeException("Unable to protect previous preview page: {$relative}");
                    }
                }
                $disk->deleteDirectory($root);
            }

            foreach ($disk->allFiles($temporaryRoot) as $file) {
                $relative = Str::after($file, $temporaryRoot.'/');
                if (! $this->moveFileEnsuringDirectory($disk, $file, $root.'/'.$relative)) {
                    throw new RuntimeException("Unable to activate preview page: {$relative}");
                }
            }

            $disk->deleteDirectory($temporaryRoot);
            $disk->deleteDirectory($backupRoot);
        } catch (Throwable $exception) {
            // Remove a partially activated deployment, then restore the complete
            // previous preview package when one existed.
            $disk->deleteDirectory($root);

            if ($hadPreviousDeployment && $disk->exists($backupRoot)) {
                foreach ($disk->allFiles($backupRoot) as $file) {
                    $relative = Str::after($file, $backupRoot.'/');
                    $this->moveFileEnsuringDirectory($disk, $file, $root.'/'.$relative);
                }
            }

            $disk->deleteDirectory($backupRoot);
            throw $exception;
        }
    }

    /**
     * Flysystem's move() does not consistently create nested destination folders.
     * Preview packages can contain paths such as about/team/index.html, so create
     * the parent directory before moving during backup, activation, or rollback.
     */
    private function moveFileEnsuringDirectory($disk, string $source, string $destination): bool
    {
        $directory = trim(str_replace('\\', '/', dirname($destination)), '/');

        if ($directory !== '' && $directory !== '.') {
            $disk->makeDirectory($directory);
        }

        return $disk->move($source, $destination);
    }

    public function url(Website $website, string $path = ''): ?string
    {
        // Dynamic storefront URLs must be usable immediately, even before the first
        // static preview deployment. A newly created website/content entry can be
        // saved before `preview_slug` has ever been assigned; returning null here
        // made the workspace render href="", which simply reopened the admin page
        // in a new tab (and could 404 until a later refresh initialized the slug).
        // Assign the same collision-safe preview slug on demand so Content/Commerce
        // frontend routes are canonical from the very first save.
        if (! filled($website->preview_slug)) {
            $this->ensureSlug($website);
        }

        $path = trim($path, '/');
        $suffix = $path === '' ? '/' : '/'.$path.'/';

        if (config('cosmic_preview.mode') === 'subdomain') {
            return $this->previewScheme().'://'.$website->preview_slug.'.'.config('cosmic_preview.domain').$suffix;
        }

        // Keep a trailing slash in local/path mode. The generated static menu
        // intentionally uses relative clean URLs (about/, services/, ...).
        // Without this slash the browser treats /preview/site as a file and
        // resolves about/ to /preview/about/ instead of /preview/site/about/.
        return rtrim(config('cosmic_preview.base_url'), '/').'/'.$website->preview_slug.$suffix;
    }

    public function urlForPage(Website $website, Page $page): ?string
    {
        if ((int) $page->website_id !== (int) $website->id) {
            return $this->url($website);
        }

        $segments = [];
        $cursor = $page;
        $guard = 0;

        while ($cursor && $guard++ < 3) {
            $slug = trim((string) $cursor->slug, '/');
            $isRootHome = $cursor->parent_id === null && $slug === 'home';

            if ($slug !== '' && ! $isRootHome) {
                array_unshift($segments, $slug);
            }

            $cursor = $cursor->parent_id
                ? Page::query()->where('website_id', $website->id)->find($cursor->parent_id)
                : null;
        }

        return $this->url($website, implode('/', $segments));
    }

    public function resolveFile(string $slug, ?string $path = null): ?string
    {
        $website = Website::where('preview_slug', $slug)->first();
        if (! $website) {
            return null;
        }

        $requested = trim((string) $path, '/');
        $disk = Storage::disk(config('cosmic_preview.disk', 'local'));
        $root = $this->websiteRoot($slug);

        // Static preview assets that belong to an individual site are resolved
        // as exact files. Shared Tailwind is served directly from /public/cosmic/.
        if ($requested !== '' && pathinfo($requested, PATHINFO_EXTENSION) !== '') {
            try {
                $assetPath = $this->safePreviewAssetPath($requested);
            } catch (RuntimeException) {
                return null;
            }

            $file = $root.'/'.$assetPath;
            return $disk->exists($file) ? $disk->get($file) : null;
        }

        $candidates = $requested === ''
            ? ['index.html']
            : [$requested, $requested.'.html', $requested.'/index.html'];

        foreach ($candidates as $candidate) {
            try {
                $candidate = $this->safeOutputPath($candidate);
            } catch (RuntimeException) {
                continue;
            }
            $file = $root.'/'.$candidate;
            if ($disk->exists($file)) {
                return $disk->get($file);
            }
        }

        return null;
    }

    private function sharedTailwindAvailable(): bool
    {
        $path = public_path('cosmic/cosmic-tailwind.css');

        if (! File::isFile($path)) {
            return false;
        }

        $size = File::size($path);
        if (! is_int($size) || $size < 10000) {
            return false;
        }

        $sample = (string) File::get($path);

        return str_contains($sample, '--tw-') || str_contains($sample, '.flex');
    }

    private function ensureSlug(Website $website): string
    {
        if (filled($website->preview_slug)) {
            return $website->preview_slug;
        }

        $base = Str::slug($website->name) ?: 'website-'.$website->id;
        $slug = Str::limit($base, 54, '');
        $reserved = array_map('strtolower', (array) config('cosmic_preview.reserved_slugs', []));
        $incrementBase = $slug;
        $counter = 2;

        // `www` belongs to the main Cosmic CMS host. Keep the display name intact,
        // but start its generated preview slug at www-2 and use the normal
        // collision sequence (www-3, www-4, ...) after that.
        if (strtolower($slug) === 'www') {
            $candidate = 'www-2';
            $incrementBase = 'www';
            $counter = 3;
        } else {
            if (in_array(strtolower($slug), $reserved, true)) {
                $slug = Str::limit($slug.'-site', 54, '');
                $incrementBase = $slug;
            }

            $candidate = $slug;
        }

        while (
            in_array(strtolower($candidate), $reserved, true) ||
            Website::where('preview_slug', $candidate)->whereKeyNot($website->getKey())->exists()
        ) {
            $suffix = '-'.$counter++;
            $candidate = Str::limit($incrementBase, 60 - strlen($suffix), '').$suffix;
        }

        $website->forceFill(['preview_slug' => $candidate])->save();
        return $candidate;
    }

    private function websiteRoot(string $slug): string
    {
        return trim(config('cosmic_preview.root', 'cosmic-previews'), '/').'/'.$slug;
    }

    private function canonicalUrlForOutputPath(string $siteBaseUrl, string $outputPath): string
    {
        $path = trim(str_replace('\\', '/', $outputPath), '/');
        $path = preg_replace('#(?:^|/)index\.html$#i', '', $path) ?? $path;
        $path = trim($path, '/');

        return $siteBaseUrl . ($path === '' ? '/' : '/'.$path.'/');
    }

    private function buildSitemapXml(array $pages, string $siteBaseUrl): string
    {
        $urls = [];
        foreach ($pages as $page) {
            $outputPath = $this->safeOutputPath((string) ($page['output_path'] ?? ''));
            $urls[] = $this->canonicalUrlForOutputPath($siteBaseUrl, $outputPath);
        }
        $urls = array_values(array_unique(array_filter($urls)));

        $entries = collect($urls)->map(static function (string $url): string {
            $escaped = htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            return "  <url><loc>{$escaped}</loc></url>";
        })->implode("\n");

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            ."<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n"
            .$entries."\n</urlset>\n";
    }

    private function buildRobotsTxt(string $siteBaseUrl): string
    {
        return "User-agent: *\nAllow: /\n\nSitemap: ".rtrim($siteBaseUrl, '/')."/sitemap.xml\n";
    }

    private function safePreviewAssetPath(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, '../') || str_contains($path, '..\\')) {
            throw new RuntimeException('Invalid preview asset path.');
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $allowed = ['css', 'js', 'mjs', 'json', 'xml', 'txt', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'ico', 'woff', 'woff2'];
        if (! in_array($extension, $allowed, true)) {
            throw new RuntimeException('Unsupported preview asset type.');
        }

        return $path;
    }

    private function safeOutputPath(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, '../') || str_contains($path, '..\\')) {
            throw new RuntimeException('Invalid preview output path.');
        }
        if (! str_ends_with(strtolower($path), '.html')) {
            throw new RuntimeException('Preview output paths must be HTML files.');
        }
        return $path;
    }

    /**
     * Final production media pass. Preserve spark-authored hints, keep the first
     * body image as the likely LCP candidate, and defer later media safely.
     */
    private function optimizeMediaMarkup(string $html, bool $protectFirstImage = false): string
    {
        $imageIndex = 0;
        $html = preg_replace_callback('/<img\b[^>]*>/i', function (array $match) use (&$imageIndex, $protectFirstImage): string {
            $tag = $match[0];
            $isFirst = $protectFirstImage && $imageIndex++ === 0;

            if (! preg_match('/\balt\s*=/i', $tag)) {
                $tag = preg_replace('/\s*\/?>$/', ' alt="">', rtrim($tag)) ?? $tag;
            }
            if (! preg_match('/\bdecoding\s*=/i', $tag)) {
                $tag = preg_replace('/\s*\/?>$/', ' decoding="async">', rtrim($tag)) ?? $tag;
            }

            if ($isFirst) {
                if (! preg_match('/\bloading\s*=/i', $tag)) {
                    $tag = preg_replace('/\s*\/?>$/', ' loading="eager">', rtrim($tag)) ?? $tag;
                }
                if (! preg_match('/\bfetchpriority\s*=/i', $tag)) {
                    $tag = preg_replace('/\s*\/?>$/', ' fetchpriority="high">', rtrim($tag)) ?? $tag;
                }
            } elseif (! preg_match('/\bloading\s*=/i', $tag)) {
                $tag = preg_replace('/\s*\/?>$/', ' loading="lazy">', rtrim($tag)) ?? $tag;
            }

            return $tag;
        }, $html) ?? $html;

        return preg_replace_callback('/<iframe\b[^>]*>/i', static function (array $match): string {
            $tag = $match[0];
            if (! preg_match('/\bloading\s*=/i', $tag)) {
                $tag = preg_replace('/\s*\/?>$/', ' loading="lazy">', rtrim($tag)) ?? $tag;
            }
            return $tag;
        }, $html) ?? $html;
    }

    private function composeHtml(string $body, string $header, string $footer, string $title, string $outputPath, string $metaDescription = '', array $page = [], array $themePalette = [], bool $hasSharedTailwindCss = false): string
    {
        $rawTitle = trim(html_entity_decode((string) $title, ENT_QUOTES | ENT_HTML5));
        $body = $this->optimizeMediaMarkup($body, true);
        $header = $this->optimizeMediaMarkup($header, false);
        $footer = $this->optimizeMediaMarkup($footer, false);
        $title = e($title);
        $pageStyle = strtolower(trim((string) ($page['page_style'] ?? 'balanced')));
        $pageStyle = in_array($pageStyle, ['clean', 'balanced', 'premium'], true) ? $pageStyle : 'balanced';
        $bodyBaseClass = $pageStyle === 'clean' ? 'bg-white text-slate-900' : 'bg-[#0b0f19] text-slate-100';
        $metaDescription = trim($metaDescription);
        if ($metaDescription === '') {
            $descriptionSource = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $body) ?? $body;
            $descriptionSource = html_entity_decode(strip_tags($descriptionSource), ENT_QUOTES | ENT_HTML5);
            $descriptionSource = preg_replace('/\s+/u', ' ', trim($descriptionSource)) ?? '';
            $metaDescription = Str::limit($descriptionSource, 155, '');
        }
        $metaTag = $metaDescription !== '' ? "<meta name='description' content='".e($metaDescription)."'>\n" : '';
        $ogType = e((string) ($page['og_type'] ?? 'website'));
        $ogImage = trim((string) ($page['og_image'] ?? ''));
        $canonicalUrl = trim((string) ($page['canonical_url'] ?? ''));
        $socialMeta = "<meta property='og:type' content='{$ogType}'>\n<meta property='og:title' content='{$title}'>\n";
        if ($canonicalUrl !== '') $socialMeta .= "<meta property='og:url' content='".e($canonicalUrl)."'>\n";
        if ($metaDescription !== '') $socialMeta .= "<meta property='og:description' content='".e($metaDescription)."'>\n";
        if ($ogImage !== '') $socialMeta .= "<meta property='og:image' content='".e($ogImage)."'>\n";
        if (! empty($page['structured_content'])) {
            if (! empty($page['published_at'])) $socialMeta .= "<meta property='article:published_time' content='".e((string) $page['published_at'])."'>\n";
            if (! empty($page['updated_at'])) $socialMeta .= "<meta property='article:modified_time' content='".e((string) $page['updated_at'])."'>\n";
        }
        $socialMeta .= "<meta name='twitter:card' content='".($ogImage !== '' ? 'summary_large_image' : 'summary')."'>\n<meta name='twitter:title' content='{$title}'>\n";
        if ($metaDescription !== '') $socialMeta .= "<meta name='twitter:description' content='".e($metaDescription)."'>\n";
        if ($ogImage !== '') $socialMeta .= "<meta name='twitter:image' content='".e($ogImage)."'>\n";
        $canonicalTag = $canonicalUrl !== '' ? "<link rel='canonical' href='".e($canonicalUrl)."'>\n" : '';
        $websiteName = trim((string) ($page['website_name'] ?? '')) ?: $rawTitle;
        $schemaNodes = [];
        // Root pages are reliably identified from output_path rather than URL heuristics.
        $isHome = strtolower(str_replace('\\', '/', trim($outputPath, '/'))) === 'index.html';
        if ($isHome && $canonicalUrl !== '') {
            $schemaNodes[] = [
                '@type' => 'WebSite',
                '@id' => rtrim($canonicalUrl, '/').'#website',
                'url' => $canonicalUrl,
                'name' => $websiteName,
            ];
        }
        if (! empty($page['structured_content'])) {
            $article = [
                '@type' => 'Article',
                '@id' => ($canonicalUrl !== '' ? $canonicalUrl : '#article').'#article',
                'headline' => $rawTitle,
                'description' => $metaDescription,
            ];
            if ($canonicalUrl !== '') $article['url'] = $canonicalUrl;
            if ($ogImage !== '') $article['image'] = [$ogImage];
            if (! empty($page['published_at'])) $article['datePublished'] = (string) $page['published_at'];
            if (! empty($page['updated_at'])) $article['dateModified'] = (string) $page['updated_at'];
            $schemaNodes[] = $article;
        } else {
            $webPage = [
                '@type' => 'WebPage',
                '@id' => ($canonicalUrl !== '' ? $canonicalUrl : '#webpage').'#webpage',
                'name' => $rawTitle,
                'description' => $metaDescription,
            ];
            if ($canonicalUrl !== '') $webPage['url'] = $canonicalUrl;
            $schemaNodes[] = $webPage;
        }
        $schemaJson = json_encode(
            ['@context' => 'https://schema.org', '@graph' => $schemaNodes],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        $schemaTag = $schemaJson ? "<script type='application/ld+json'>{$schemaJson}</script>\n" : '';
        $outputDirectory = trim(str_replace('\\', '/', dirname($outputPath)), './');
        $baseTag = $outputDirectory !== ''
            ? "<base href='".str_repeat('../', substr_count($outputDirectory, '/') + 1)."'>\n"
            : '';

        $themeVars = sprintf(
            ':root{--p:%s;--a:%s;--s:%s;--t:%s;--m:%s;--b:%s;--bg:%s}',
            e((string) ($themePalette['primary'] ?? '#243447')),
            e((string) ($themePalette['accent'] ?? '#60A5FA')),
            e((string) ($themePalette['surface'] ?? '#30475E')),
            e((string) ($themePalette['text'] ?? '#F8FAFC')),
            e((string) ($themePalette['muted'] ?? '#64748B')),
            e((string) ($themePalette['border'] ?? '#E2E8F0')),
            e((string) ($themePalette['background'] ?? '#FFFFFF')),
        );

        $heroPreload = '';
        if (preg_match('/<img[^>]+src=[\"\']([^\"\']+)[\"\']/i', $body, $match) === 1) {
            $heroSrc = e((string) $match[1]);
            $heroPreload = "<link rel='preload' as='image' href='{$heroSrc}' fetchpriority='high'>\n";
        }

        $tailwindAsset = $hasSharedTailwindCss
            ? "<link rel='stylesheet' href='/cosmic/cosmic-tailwind.css'>\n"
            : "<link rel='preconnect' href='https://cdn.tailwindcss.com' crossorigin>\n<script src='https://cdn.tailwindcss.com'></script>\n";

        return "<!DOCTYPE html>\n<html lang='en'>\n<head>\n<meta charset='UTF-8'>\n<meta name='viewport' content='width=device-width, initial-scale=1.0'>\n{$baseTag}{$canonicalTag}{$metaTag}{$socialMeta}{$schemaTag}<title>{$title}</title>\n<link rel='preconnect' href='https://fonts.bunny.net' crossorigin>\n{$heroPreload}<link href='https://fonts.bunny.net/css?family=manrope:400,500,600,700,800&display=swap' rel='stylesheet'>\n{$tailwindAsset}<style>{$themeVars}html,body,button,input,select,textarea{font-family:Manrope,ui-sans-serif,system-ui,sans-serif!important}[data-cosmic-contact-form][data-cosmic-contact-scheme='light'] select{color-scheme:light;background:#fff;color:#0f172a}[data-cosmic-contact-form][data-cosmic-contact-scheme='light'] select option{background:#fff;color:#0f172a}[data-cosmic-contact-form][data-cosmic-contact-scheme='dark'] select{color-scheme:dark;background:rgba(15,23,42,.38);color:#f8fafc}[data-cosmic-contact-form][data-cosmic-contact-scheme='dark'] select option{background:#0f172a;color:#f8fafc}[data-cosmic-contact-form][data-cosmic-contact-scheme='light'] input[type=date]{color-scheme:light}[data-cosmic-contact-form][data-cosmic-contact-scheme='dark'] input[type=date]{color-scheme:dark}[data-cosmic-spark]{padding-top:50px!important;padding-bottom:50px!important}[data-cosmic-spark]{box-sizing:border-box;width:100%;max-width:100%;overflow-x:clip}[data-cosmic-spark] :is(img,video,iframe,svg,canvas){max-width:100%}[data-cosmic-spark] :is(h1,h2,h3,h4,h5,h6,p,a,button,label){overflow-wrap:anywhere}[data-cosmic-spark] .grid>*{min-width:0}[data-cosmic-spark] :is(input,select,textarea,button){max-width:100%}@media(max-width:639px){[data-cosmic-spark] table{display:block;width:100%;max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}}main>[data-cosmic-spark]:not(:first-child){content-visibility:auto;contain-intrinsic-size:800px}@media(min-width:640px){[data-cosmic-spark]{padding-top:80px!important;padding-bottom:80px!important}}.entry-template-runtime{position:relative;isolation:isolate;background:var(--bg)}.entry-template-runtime[data-cosmic-dynamic-page-style='clean'] :is(a,button)[class*='bg-primary'],.entry-template-runtime[data-cosmic-dynamic-page-style='clean'] :is(a,button)[class*='bg-[var(--p)]'],.entry-template-runtime[data-cosmic-dynamic-page-style='clean'] :is(a,button).cosmic-brand-bg{background:var(--p)!important;background-color:var(--p)!important;color:#fff!important;-webkit-text-fill-color:#fff!important;border-color:var(--p)!important}.entry-template-runtime[data-cosmic-dynamic-page-style='clean'] :is(a,button)[class*='bg-primary'] *,.entry-template-runtime[data-cosmic-dynamic-page-style='clean'] :is(a,button)[class*='bg-[var(--p)]'] *,.entry-template-runtime[data-cosmic-dynamic-page-style='clean'] :is(a,button).cosmic-brand-bg *{color:#fff!important;-webkit-text-fill-color:#fff!important}body#cosmic-published-page main{padding-top:0!important}body#cosmic-published-page main>.entry-template-runtime:first-child{margin-top:0!important;padding-top:0!important}.entry-template-runtime[data-cosmic-header-overlay='false'] [data-cosmic-dynamic-single]>:first-child{margin-top:0!important}.entry-template-runtime[data-cosmic-header-overlay='false'] [data-cosmic-mini-hero='true']{margin-top:0!important}.entry-template-runtime[data-cosmic-header-overlay='true'].cosmic-static-overlay-first-spark{padding-top:0!important}.entry-template-runtime[data-cosmic-header-overlay='true'] [data-cosmic-dynamic-single]>:first-child{padding-top:0!important}.entry-template-runtime[data-cosmic-header-overlay='true'] [data-cosmic-mini-hero='true']{padding-top:calc(var(--cosmic-overlay-header-height,80px) + clamp(3.25rem,5vw,5.5rem))!important}.entry-template-runtime[data-cosmic-header-overlay='false'] [data-cosmic-mini-hero='true']{scroll-margin-top:1.5rem}.entry-template-runtime[data-cosmic-first-surface='primary'][data-cosmic-mini-banner-image='false'] [data-cosmic-mini-hero='true']{background:var(--p)!important;background-image:none!important;box-shadow:0 0 0 100vmax var(--p);clip-path:inset(0 -100vmax);border-color:transparent!important;color:#fff!important}.entry-template-runtime[data-cosmic-first-surface='primary'] [data-cosmic-mini-hero='true']{color:#fff!important}.entry-template-runtime[data-cosmic-first-surface='white'][data-cosmic-mini-banner-image='false'] [data-cosmic-mini-hero='true']{background:var(--bg)!important;background-image:none!important;box-shadow:0 0 0 100vmax var(--bg);clip-path:inset(0 -100vmax)}.entry-template-runtime[data-cosmic-first-surface='surface'][data-cosmic-mini-banner-image='false'] [data-cosmic-mini-hero='true']{background:var(--s)!important;background-image:none!important;box-shadow:0 0 0 100vmax var(--s);clip-path:inset(0 -100vmax)}.entry-template-runtime[data-cosmic-first-surface='primary'] [data-cosmic-mini-hero='true'] h1,.entry-template-runtime[data-cosmic-first-surface='primary'] [data-cosmic-mini-hero='true'] h2,.entry-template-runtime[data-cosmic-first-surface='primary'] [data-cosmic-mini-hero='true'] h3{color:#fff!important}.entry-template-runtime[data-cosmic-first-surface='primary'] [data-cosmic-mini-hero='true'] p,.entry-template-runtime[data-cosmic-first-surface='primary'] [data-cosmic-mini-hero='true'] [data-cosmic-tags='true']{color:rgba(255,255,255,.82)!important}.entry-template-runtime[data-cosmic-first-surface='white'] [data-cosmic-mini-hero='true'],.entry-template-runtime[data-cosmic-first-surface='surface'] [data-cosmic-mini-hero='true']{color:var(--t)!important}.entry-template-runtime[data-cosmic-first-surface='white'] [data-cosmic-mini-hero='true'] h1,.entry-template-runtime[data-cosmic-first-surface='white'] [data-cosmic-mini-hero='true'] h2,.entry-template-runtime[data-cosmic-first-surface='white'] [data-cosmic-mini-hero='true'] h3,.entry-template-runtime[data-cosmic-first-surface='surface'] [data-cosmic-mini-hero='true'] h1,.entry-template-runtime[data-cosmic-first-surface='surface'] [data-cosmic-mini-hero='true'] h2,.entry-template-runtime[data-cosmic-first-surface='surface'] [data-cosmic-mini-hero='true'] h3{color:var(--t)!important}@media(max-width:640px){.entry-template-runtime[data-cosmic-header-overlay='true'] [data-cosmic-mini-hero='true']{padding-top:calc(var(--cosmic-overlay-header-height,72px) + 2.75rem)!important}}.entry-template-runtime{width:100%;max-width:100%;overflow-x:clip}.entry-template-runtime [data-cosmic-dynamic-single]{width:100%;max-width:100%;overflow:visible}.entry-template-runtime [data-cosmic-mini-hero='true']{box-sizing:border-box}.entry-template-runtime[data-cosmic-mini-banner-image='true'] [data-cosmic-mini-hero='true']{position:relative;isolation:isolate;overflow:hidden;background-image:linear-gradient(color-mix(in srgb,var(--p) 74%,transparent),color-mix(in srgb,var(--p) 74%,transparent)),var(--cosmic-mini-banner-image)!important;background-size:cover!important;background-position:center!important;color:#fff!important;border-radius:0!important;padding-left:clamp(1.25rem,4vw,3.5rem)!important;padding-right:clamp(1.25rem,4vw,3.5rem)!important;padding-bottom:clamp(2.5rem,5vw,5rem)!important}.entry-template-runtime[data-cosmic-mini-banner-image='true'] [data-cosmic-mini-hero='true'] h1,.entry-template-runtime[data-cosmic-mini-banner-image='true'] [data-cosmic-mini-hero='true'] h2,.entry-template-runtime[data-cosmic-mini-banner-image='true'] [data-cosmic-mini-hero='true'] h3,.entry-template-runtime[data-cosmic-mini-banner-image='true'] [data-cosmic-mini-hero='true'] p{color:#fff!important}[data-cosmic-dynamic-single]{width:100%;overflow:visible}[data-cosmic-dynamic-single] [data-cosmic-richtext]{font-size:1.0625rem;line-height:1.88;text-wrap:pretty}[data-cosmic-dynamic-single] [data-cosmic-richtext]>:first-child{margin-top:0}[data-cosmic-dynamic-single] [data-cosmic-richtext]>:last-child{margin-bottom:0}[data-cosmic-dynamic-single] [data-cosmic-richtext] p{margin:0 0 1.35em}[data-cosmic-dynamic-single] [data-cosmic-richtext] h2{font-size:clamp(1.75rem,3vw,2.35rem);line-height:1.15;margin:1.8em 0 .65em;letter-spacing:-.035em}[data-cosmic-dynamic-single] [data-cosmic-richtext] h3{font-size:clamp(1.35rem,2.4vw,1.75rem);line-height:1.22;margin:1.6em 0 .6em;letter-spacing:-.025em}[data-cosmic-dynamic-single] [data-cosmic-richtext] ul,[data-cosmic-dynamic-single] [data-cosmic-richtext] ol{margin:1.2em 0;padding-left:1.4em}[data-cosmic-dynamic-single] [data-cosmic-richtext] li{margin:.45em 0}[data-cosmic-dynamic-single] [data-cosmic-richtext] blockquote{margin:1.6em 0;padding:1rem 1.25rem;border-left:4px solid currentColor;border-radius:0 1rem 1rem 0;background:rgba(148,163,184,.09);font-size:1.08em;font-weight:500}[data-cosmic-dynamic-single] [data-cosmic-richtext] a{text-decoration:underline;text-decoration-thickness:.08em;text-underline-offset:.18em}[data-cosmic-dynamic-single] [data-cosmic-richtext] img{height:auto;border-radius:1.25rem;margin:1.6rem auto}[data-cosmic-dynamic-single] [data-cosmic-richtext] figcaption{margin-top:.65rem;text-align:center;font-size:.82rem;opacity:.72}[data-cosmic-dynamic-single] [data-cosmic-richtext] pre{overflow:auto;border-radius:1rem;padding:1rem 1.1rem;background:#0f172a;color:#e2e8f0;font-size:.9rem;line-height:1.7}[data-cosmic-dynamic-single] [data-cosmic-richtext] table{display:block;width:100%;overflow-x:auto;border-collapse:collapse;margin:1.7rem 0}[data-cosmic-dynamic-single] [data-cosmic-richtext] th,[data-cosmic-dynamic-single] [data-cosmic-richtext] td{padding:.8rem .9rem;border:1px solid rgba(148,163,184,.28);text-align:left}[data-cosmic-gallery] figure{aspect-ratio:4/3}[data-cosmic-gallery] img{width:100%;height:100%;object-fit:cover;transition:transform .35s ease}[data-cosmic-gallery] figure:hover img{transform:scale(1.025)}@media(max-width:640px){[data-cosmic-dynamic-single] [data-cosmic-richtext]{font-size:1rem;line-height:1.8}}.entry-template-runtime[data-cosmic-dynamic-page-style='clean'][data-cosmic-mini-banner-image='true'] [data-cosmic-mini-hero='true']{background-image:linear-gradient(rgba(255,255,255,.90),rgba(255,255,255,.90)),var(--cosmic-mini-banner-image)!important;color:var(--t)!important}.entry-template-runtime[data-cosmic-dynamic-page-style='clean'][data-cosmic-mini-banner-image='true'] [data-cosmic-mini-hero='true'] h1,.entry-template-runtime[data-cosmic-dynamic-page-style='clean'][data-cosmic-mini-banner-image='true'] [data-cosmic-mini-hero='true'] h2,.entry-template-runtime[data-cosmic-dynamic-page-style='clean'][data-cosmic-mini-banner-image='true'] [data-cosmic-mini-hero='true'] h3{color:var(--t)!important}.entry-template-runtime[data-cosmic-dynamic-page-style='clean'][data-cosmic-mini-banner-image='true'] [data-cosmic-mini-hero='true'] p{color:var(--m)!important}
body#cosmic-published-page[data-cosmic-page-style='clean']{background:#fff!important;color:#0f172a!important}body#cosmic-published-page[data-cosmic-page-style='clean'] :is(a,button).cosmic-primary-cta,body#cosmic-published-page[data-cosmic-page-style='clean'] .entry-template-runtime :is(a,button).cosmic-primary-cta,body#cosmic-published-page[data-cosmic-page-style='clean'] [data-cosmic-dynamic-single] :is(a,button).cosmic-primary-cta{background:var(--p,var(--cosmic-primary,#243447))!important;background-color:var(--p,var(--cosmic-primary,#243447))!important;border-color:var(--p,var(--cosmic-primary,#243447))!important;color:#fff!important;-webkit-text-fill-color:#fff!important;opacity:1!important}body#cosmic-published-page[data-cosmic-page-style='clean'] :is(a,button).cosmic-primary-cta *{color:#fff!important;-webkit-text-fill-color:#fff!important}
body#cosmic-published-page[data-cosmic-page-style='clean'] main{background:#fff!important;color:#0f172a!important}
body#cosmic-published-page[data-cosmic-page-style='clean'] main :is(a,button)[class*='bg-[#'],body#cosmic-published-page[data-cosmic-page-style='clean'] main :is(a,button)[class*='bg-primary'],body#cosmic-published-page[data-cosmic-page-style='clean'] main :is(a,button)[class*='bg-[var(--p)]'],body#cosmic-published-page[data-cosmic-page-style='clean'] main :is(a,button).cosmic-brand-bg{background:var(--p)!important;background-color:var(--p)!important;border-color:var(--p)!important;color:#fff!important;-webkit-text-fill-color:#fff!important;opacity:1!important}body#cosmic-published-page[data-cosmic-page-style='clean'] main :is(a,button).cosmic-primary-cta,body#cosmic-published-page[data-cosmic-page-style='clean'] main :is(a,button)[class*='bg-slate-9'],body#cosmic-published-page[data-cosmic-page-style='clean'] main :is(a,button)[class*='bg-black']{background:var(--p)!important;background-color:var(--p)!important;border-color:var(--p)!important;color:#fff!important;-webkit-text-fill-color:#fff!important;opacity:1!important}body#cosmic-published-page[data-cosmic-page-style='clean'] header.cosmic-static-header>nav>a{background:var(--p)!important;background-color:var(--p)!important;border-color:var(--p)!important;color:#fff!important;-webkit-text-fill-color:#fff!important}
body#cosmic-published-page[data-cosmic-page-style='clean'] header.cosmic-static-header.cosmic-static-overlay-header>a,body#cosmic-published-page[data-cosmic-page-style='clean'] header.cosmic-static-header.cosmic-static-overlay-header>nav>ul>li>a{color:#0f172a!important;-webkit-text-fill-color:#0f172a!important}
body#cosmic-published-page[data-cosmic-page-style='clean'] header.cosmic-static-header.cosmic-static-overlay-header>a img{filter:none!important}
body#cosmic-published-page[data-cosmic-page-style='clean'] header.cosmic-static-header.cosmic-static-overlay-header>nav>a{background:var(--p)!important;color:#fff!important;-webkit-text-fill-color:#fff!important}
body#cosmic-published-page[data-cosmic-page-style='clean'] main section[data-cosmic-block-type^='hero_'],body#cosmic-published-page[data-cosmic-page-style='clean'] main section[data-cosmic-block-type='image_cta_banner']{background-color:#fff!important;color:#0f172a!important}
/* Clean hero descendant contract: neutralize legacy white-text utilities without changing media/cards. */
body#cosmic-published-page[data-cosmic-page-style='clean'] main section[data-cosmic-block-type^='hero_'] :is(h1,h2,h3,h4,h5,h6,p,span,small,strong,em)[class*='text-white'],body#cosmic-published-page[data-cosmic-page-style='clean'] main section[data-cosmic-block-type='image_cta_banner'] :is(h1,h2,h3,h4,h5,h6,p,span,small,strong,em)[class*='text-white']{color:#0f172a!important;-webkit-text-fill-color:#0f172a!important}
body#cosmic-published-page[data-cosmic-page-style='clean'] main section[data-cosmic-block-type^='hero_'] :is(h1,h2,h3,h4,h5,h6),body#cosmic-published-page[data-cosmic-page-style='clean'] main section[data-cosmic-block-type='image_cta_banner'] :is(h1,h2,h3,h4,h5,h6){color:#0f172a!important;-webkit-text-fill-color:#0f172a!important}
body#cosmic-published-page[data-cosmic-page-style='clean'] main section[data-cosmic-block-type^='hero_'] :is(p,small)[class*='text-white'],body#cosmic-published-page[data-cosmic-page-style='clean'] main section[data-cosmic-block-type='image_cta_banner'] :is(p,small)[class*='text-white']{color:#475569!important;-webkit-text-fill-color:#475569!important;opacity:1!important}
body#cosmic-published-page[data-cosmic-page-style='clean'] main section[data-cosmic-block-type^='hero_'] :is(a,button)[class*='bg-white'],body#cosmic-published-page[data-cosmic-page-style='clean'] main section[data-cosmic-block-type='image_cta_banner'] :is(a,button)[class*='bg-white']{background:var(--p)!important;border-color:var(--p)!important;color:#fff!important;-webkit-text-fill-color:#fff!important;opacity:1!important}body#cosmic-published-page[data-cosmic-page-style='clean'] main section[data-cosmic-block-type^='hero_'] :is(a,button)[class*='border-white'],body#cosmic-published-page[data-cosmic-page-style='clean'] main section[data-cosmic-block-type='image_cta_banner'] :is(a,button)[class*='border-white']{background:#fff!important;border-color:#cbd5e1!important;color:#0f172a!important;-webkit-text-fill-color:#0f172a!important;opacity:1!important}
body#cosmic-published-page[data-cosmic-page-style='clean'] main section[data-cosmic-block-type^='hero_'] a[class*='bg-primary'],body#cosmic-published-page[data-cosmic-page-style='clean'] main section[data-cosmic-block-type^='hero_'] a[class*='bg-[var(--p)]'],body#cosmic-published-page[data-cosmic-page-style='clean'] main section[data-cosmic-block-type='image_cta_banner'] a[class*='bg-primary']{color:#fff!important;-webkit-text-fill-color:#fff!important}

</style>\n</head>\n<body id='cosmic-published-page' data-cosmic-page-style='{$pageStyle}' class='{$bodyBaseClass} min-h-screen m-0 p-0 flex flex-col'>\n{$header}\n<main class='w-full flex-grow'>{$body}</main>\n{$footer}\n</body>\n</html>";
    }
    private function previewScheme(): string
    {
        $scheme = strtolower((string) config('cosmic_preview.scheme', 'https'));

        return in_array($scheme, ['http', 'https'], true) ? $scheme : 'https';
    }

}
