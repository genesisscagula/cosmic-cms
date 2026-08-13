<?php

namespace App\Services;

use App\Models\Page;
use App\Models\Website;
use Illuminate\Support\Facades\Storage;
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
                    $page,
                    (array) ($package['theme_palette'] ?? []),
                );

                if (! $disk->put($temporaryRoot.'/'.$outputPath, $html)) {
                    throw new RuntimeException("Unable to write preview page: {$outputPath}");
                }
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
                    if (! $disk->move($file, $backupRoot.'/'.$relative)) {
                        throw new RuntimeException("Unable to protect previous preview page: {$relative}");
                    }
                }
                $disk->deleteDirectory($root);
            }

            foreach ($disk->allFiles($temporaryRoot) as $file) {
                $relative = Str::after($file, $temporaryRoot.'/');
                if (! $disk->move($file, $root.'/'.$relative)) {
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
                    $disk->move($file, $root.'/'.$relative);
                }
            }

            $disk->deleteDirectory($backupRoot);
            throw $exception;
        }
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
        $candidates = $requested === ''
            ? ['index.html']
            : [$requested, $requested.'.html', $requested.'/index.html'];

        $disk = Storage::disk(config('cosmic_preview.disk', 'local'));
        $root = $this->websiteRoot($slug);

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

    private function composeHtml(string $body, string $header, string $footer, string $title, string $outputPath, string $metaDescription = '', array $page = [], array $themePalette = []): string
    {
        $title = e($title);
        $metaDescription = trim($metaDescription);
        $metaTag = $metaDescription !== '' ? "<meta name='description' content='".e($metaDescription)."'>\n" : '';
        $ogType = e((string) ($page['og_type'] ?? 'website'));
        $ogImage = trim((string) ($page['og_image'] ?? ''));
        $socialMeta = "<meta property='og:type' content='{$ogType}'>\n<meta property='og:title' content='{$title}'>\n";
        if ($metaDescription !== '') $socialMeta .= "<meta property='og:description' content='".e($metaDescription)."'>\n";
        if ($ogImage !== '') $socialMeta .= "<meta property='og:image' content='".e($ogImage)."'>\n";
        if (! empty($page['structured_content'])) {
            if (! empty($page['published_at'])) $socialMeta .= "<meta property='article:published_time' content='".e((string) $page['published_at'])."'>\n";
            if (! empty($page['updated_at'])) $socialMeta .= "<meta property='article:modified_time' content='".e((string) $page['updated_at'])."'>\n";
        }
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

        return "<!DOCTYPE html>\n<html lang='en'>\n<head>\n<meta charset='UTF-8'>\n<meta name='viewport' content='width=device-width, initial-scale=1.0'>\n{$baseTag}{$metaTag}{$socialMeta}<title>{$title}</title>\n<link rel='preconnect' href='https://fonts.bunny.net' crossorigin>\n<link rel='preconnect' href='https://cdn.tailwindcss.com' crossorigin>\n{$heroPreload}<link href='https://fonts.bunny.net/css?family=manrope:400,500,600,700,800&display=swap' rel='stylesheet'>\n<script src='https://cdn.tailwindcss.com'></script>\n<style>{$themeVars}html,body,button,input,select,textarea{font-family:Manrope,ui-sans-serif,system-ui,sans-serif!important}[data-cosmic-contact-form][data-cosmic-contact-scheme='light'] select{color-scheme:light;background:#fff;color:#0f172a}[data-cosmic-contact-form][data-cosmic-contact-scheme='light'] select option{background:#fff;color:#0f172a}[data-cosmic-contact-form][data-cosmic-contact-scheme='dark'] select{color-scheme:dark;background:rgba(15,23,42,.38);color:#f8fafc}[data-cosmic-contact-form][data-cosmic-contact-scheme='dark'] select option{background:#0f172a;color:#f8fafc}[data-cosmic-contact-form][data-cosmic-contact-scheme='light'] input[type=date]{color-scheme:light}[data-cosmic-contact-form][data-cosmic-contact-scheme='dark'] input[type=date]{color-scheme:dark}[data-cosmic-spark]{padding-top:50px!important;padding-bottom:50px!important}main>[data-cosmic-spark]:not(:first-child){content-visibility:auto;contain-intrinsic-size:800px}@media(min-width:640px){[data-cosmic-spark]{padding-top:80px!important;padding-bottom:80px!important}}.entry-template-runtime{position:relative;isolation:isolate;background:var(--bg)}.entry-template-runtime[data-cosmic-header-overlay='true'].cosmic-static-overlay-first-spark{padding-top:0!important}.entry-template-runtime[data-cosmic-header-overlay='true'] [data-cosmic-dynamic-single]>:first-child{padding-top:0!important}.entry-template-runtime[data-cosmic-header-overlay='true'] [data-cosmic-mini-hero='true']{padding-top:calc(var(--cosmic-overlay-header-height,80px) + clamp(3.25rem,5vw,5.5rem))!important}.entry-template-runtime[data-cosmic-header-overlay='false'] [data-cosmic-mini-hero='true']{scroll-margin-top:1.5rem}.entry-template-runtime[data-cosmic-first-surface='primary'] [data-cosmic-mini-hero='true']{color:#fff!important}.entry-template-runtime[data-cosmic-first-surface='primary'] [data-cosmic-mini-hero='true'] h1,.entry-template-runtime[data-cosmic-first-surface='primary'] [data-cosmic-mini-hero='true'] h2,.entry-template-runtime[data-cosmic-first-surface='primary'] [data-cosmic-mini-hero='true'] h3{color:#fff!important}.entry-template-runtime[data-cosmic-first-surface='primary'] [data-cosmic-mini-hero='true'] p,.entry-template-runtime[data-cosmic-first-surface='primary'] [data-cosmic-mini-hero='true'] [data-cosmic-tags='true']{color:rgba(255,255,255,.82)!important}.entry-template-runtime[data-cosmic-first-surface='white'] [data-cosmic-mini-hero='true'],.entry-template-runtime[data-cosmic-first-surface='surface'] [data-cosmic-mini-hero='true']{color:var(--t)!important}.entry-template-runtime[data-cosmic-first-surface='white'] [data-cosmic-mini-hero='true'] h1,.entry-template-runtime[data-cosmic-first-surface='white'] [data-cosmic-mini-hero='true'] h2,.entry-template-runtime[data-cosmic-first-surface='white'] [data-cosmic-mini-hero='true'] h3,.entry-template-runtime[data-cosmic-first-surface='surface'] [data-cosmic-mini-hero='true'] h1,.entry-template-runtime[data-cosmic-first-surface='surface'] [data-cosmic-mini-hero='true'] h2,.entry-template-runtime[data-cosmic-first-surface='surface'] [data-cosmic-mini-hero='true'] h3{color:var(--t)!important}@media(max-width:640px){.entry-template-runtime[data-cosmic-header-overlay='true'] [data-cosmic-mini-hero='true']{padding-top:calc(var(--cosmic-overlay-header-height,72px) + 2.75rem)!important}}[data-cosmic-dynamic-single]{width:100%;overflow:hidden}[data-cosmic-dynamic-single] [data-cosmic-richtext]{font-size:1.0625rem;line-height:1.88;text-wrap:pretty}[data-cosmic-dynamic-single] [data-cosmic-richtext]>:first-child{margin-top:0}[data-cosmic-dynamic-single] [data-cosmic-richtext]>:last-child{margin-bottom:0}[data-cosmic-dynamic-single] [data-cosmic-richtext] p{margin:0 0 1.35em}[data-cosmic-dynamic-single] [data-cosmic-richtext] h2{font-size:clamp(1.75rem,3vw,2.35rem);line-height:1.15;margin:1.8em 0 .65em;letter-spacing:-.035em}[data-cosmic-dynamic-single] [data-cosmic-richtext] h3{font-size:clamp(1.35rem,2.4vw,1.75rem);line-height:1.22;margin:1.6em 0 .6em;letter-spacing:-.025em}[data-cosmic-dynamic-single] [data-cosmic-richtext] ul,[data-cosmic-dynamic-single] [data-cosmic-richtext] ol{margin:1.2em 0;padding-left:1.4em}[data-cosmic-dynamic-single] [data-cosmic-richtext] li{margin:.45em 0}[data-cosmic-dynamic-single] [data-cosmic-richtext] blockquote{margin:1.6em 0;padding:1rem 1.25rem;border-left:4px solid currentColor;border-radius:0 1rem 1rem 0;background:rgba(148,163,184,.09);font-size:1.08em;font-weight:500}[data-cosmic-dynamic-single] [data-cosmic-richtext] a{text-decoration:underline;text-decoration-thickness:.08em;text-underline-offset:.18em}[data-cosmic-dynamic-single] [data-cosmic-richtext] img{height:auto;border-radius:1.25rem;margin:1.6rem auto}[data-cosmic-dynamic-single] [data-cosmic-richtext] figcaption{margin-top:.65rem;text-align:center;font-size:.82rem;opacity:.72}[data-cosmic-dynamic-single] [data-cosmic-richtext] pre{overflow:auto;border-radius:1rem;padding:1rem 1.1rem;background:#0f172a;color:#e2e8f0;font-size:.9rem;line-height:1.7}[data-cosmic-dynamic-single] [data-cosmic-richtext] table{display:block;width:100%;overflow-x:auto;border-collapse:collapse;margin:1.7rem 0}[data-cosmic-dynamic-single] [data-cosmic-richtext] th,[data-cosmic-dynamic-single] [data-cosmic-richtext] td{padding:.8rem .9rem;border:1px solid rgba(148,163,184,.28);text-align:left}[data-cosmic-gallery] figure{aspect-ratio:4/3}[data-cosmic-gallery] img{width:100%;height:100%;object-fit:cover;transition:transform .35s ease}[data-cosmic-gallery] figure:hover img{transform:scale(1.025)}@media(max-width:640px){[data-cosmic-dynamic-single] [data-cosmic-richtext]{font-size:1rem;line-height:1.8}}</style>\n</head>\n<body class='bg-[#0b0f19] text-slate-100 min-h-screen m-0 p-0 flex flex-col'>\n{$header}\n<main class='w-full flex-grow'>{$body}</main>\n{$footer}\n</body>\n</html>";
    }
    private function previewScheme(): string
    {
        $scheme = strtolower((string) config('cosmic_preview.scheme', 'https'));

        return in_array($scheme, ['http', 'https'], true) ? $scheme : 'https';
    }

}
