<?php

namespace App\Services;

use App\Helpers\CmsHtmlCompiler;
use App\Models\Page;
use App\Models\Website;

class PagePublisher
{
    /**
     * Compile the approved Builder state into the snapshot stored by Laravel.
     *
     * Deployment is intentionally separate: the manual static-site sync reads
     * published snapshots through the existing API when the operator runs it.
     */
    public function publish(Page $page, Website $website): string
    {
        $theme = $website->theme_settings ?? [];
        $primaryColor = $theme['primary'] ?? 'emerald';
        return CmsHtmlCompiler::compile($page->blocks ?? [], $primaryColor);
    }

    /**
     * Build the approved website package consumed by the deployment connector.
     * Draft content never enters this payload.
     */
    public function publishedPackage(Website $website): array
    {
        $theme = $website->published_theme_settings ?? $website->theme_settings ?? [];
        $primaryColor = $theme['primary'] ?? 'emerald';
        $header = $website->published_global_header ?? $website->global_header;
        $footer = $website->published_global_footer ?? $website->global_footer;
        $pages = $website->pages()
            ->where('status', 'published')
            ->orderBy('id')
            ->get(['title', 'slug', 'published_html', 'published_blocks', 'blocks']);

        $header = $this->staticNavigationHeader($header, $pages->pluck('slug')->all());

        return [
            'status' => 'success',
            'website_name' => $website->name,
            'global_header' => is_array($header) ? CmsHtmlCompiler::compile([$header], $primaryColor) : '',
            'global_footer' => is_array($footer) ? CmsHtmlCompiler::compile([$footer], $primaryColor) : '',
            'pages' => $pages
                ->map(fn (Page $page) => [
                    'title' => $page->title,
                    'slug' => $page->slug,
                    'html' => $page->published_html
                        ?? CmsHtmlCompiler::compile($page->published_blocks ?? $page->blocks ?? [], $primaryColor),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Convert menu targets that match published page slugs into static-file
     * links. External URLs and in-page anchors intentionally stay untouched.
     */
    private function staticNavigationHeader(?array $header, array $publishedSlugs): ?array
    {
        if (! is_array($header) || ! is_array($header['menu'] ?? null)) {
            return $header;
        }

        $publishedSlugs = array_flip(array_map(
            static fn ($slug) => strtolower(trim((string) $slug, '/')),
            $publishedSlugs
        ));

        $header['menu'] = array_map(function ($item) use ($publishedSlugs) {
            if (! is_array($item)) {
                return $item;
            }

            $target = trim((string) ($item['url'] ?? ''));

            if ($target === '' || $target === '#' || str_starts_with($target, '#') || preg_match('/^(https?:|mailto:|tel:)/i', $target)) {
                return $item;
            }

            $slug = strtolower(trim(preg_replace('/\.html$/i', '', $target), '/'));

            if (! isset($publishedSlugs[$slug])) {
                // A plain slug represents a CMS page. Avoid exporting a broken
                // relative link when that page has not been published yet.
                if (preg_match('/^[a-z0-9-]+$/', $slug)) {
                    $item['url'] = '#';
                }

                return $item;
            }

            $item['url'] = in_array($slug, ['', 'home'], true) ? './' : $slug . '.html';

            return $item;
        }, $header['menu']);

        if (array_key_exists('cta_url', $header)) {
            $header['cta_url'] = $this->staticNavigationTarget((string) $header['cta_url'], $publishedSlugs);
        }

        return $header;
    }

    private function staticNavigationTarget(string $target, array $publishedSlugs): string
    {
        $target = trim($target);

        if ($target === '' || $target === '#' || str_starts_with($target, '#') || preg_match('/^(https?:|mailto:|tel:)/i', $target)) {
            return $target;
        }

        $slug = strtolower(trim(preg_replace('/\.html$/i', '', $target), '/'));

        if (! isset($publishedSlugs[$slug])) {
            return preg_match('/^[a-z0-9-]+$/', $slug) ? '#' : $target;
        }

        return in_array($slug, ['', 'home'], true) ? './' : $slug . '.html';
    }
}
