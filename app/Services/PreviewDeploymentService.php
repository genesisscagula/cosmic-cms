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
        if (! filled($website->preview_slug)) {
            return null;
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

    private function composeHtml(string $body, string $header, string $footer, string $title, string $outputPath): string
    {
        $title = e($title);
        $outputDirectory = trim(str_replace('\\', '/', dirname($outputPath)), './');
        $baseTag = $outputDirectory !== ''
            ? "<base href='".str_repeat('../', substr_count($outputDirectory, '/') + 1)."'>\n"
            : '';

        $heroPreload = '';
        if (preg_match('/<img[^>]+src=[\"\']([^\"\']+)[\"\']/i', $body, $match) === 1) {
            $heroSrc = e((string) $match[1]);
            $heroPreload = "<link rel='preload' as='image' href='{$heroSrc}' fetchpriority='high'>\n";
        }

        return "<!DOCTYPE html>\n<html lang='en'>\n<head>\n<meta charset='UTF-8'>\n<meta name='viewport' content='width=device-width, initial-scale=1.0'>\n{$baseTag}<title>{$title}</title>\n<link rel='preconnect' href='https://fonts.bunny.net' crossorigin>\n<link rel='preconnect' href='https://cdn.tailwindcss.com' crossorigin>\n{$heroPreload}<link href='https://fonts.bunny.net/css?family=manrope:400,500,600,700,800&display=swap' rel='stylesheet'>\n<script src='https://cdn.tailwindcss.com'></script>\n<style>html,body,button,input,select,textarea{font-family:Manrope,ui-sans-serif,system-ui,sans-serif!important}[data-cosmic-contact-form] select{color-scheme:dark;background-color:#334b67;color:#f8fafc}[data-cosmic-contact-form] select option{background-color:#334b67;color:#f8fafc}[data-cosmic-contact-form] input[type=date]{color-scheme:dark}[data-cosmic-spark]{padding-top:50px!important;padding-bottom:50px!important}main>[data-cosmic-spark]:not(:first-child){content-visibility:auto;contain-intrinsic-size:800px}@media(min-width:640px){[data-cosmic-spark]{padding-top:80px!important;padding-bottom:80px!important}}</style>\n</head>\n<body class='bg-[#0b0f19] text-slate-100 min-h-screen m-0 p-0 flex flex-col'>\n{$header}\n<main class='w-full flex-grow'>{$body}</main>\n{$footer}\n</body>\n</html>";
    }
    private function previewScheme(): string
    {
        $scheme = strtolower((string) config('cosmic_preview.scheme', 'https'));

        return in_array($scheme, ['http', 'https'], true) ? $scheme : 'https';
    }

}
