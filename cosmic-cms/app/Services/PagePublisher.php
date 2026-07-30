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
     * Editable draft content never enters this payload. A page with an existing
     * published snapshot stays in the package while the customer prepares its
     * next draft revision.
     */
    public function publishedPackage(Website $website): array
    {
        $theme = $website->published_theme_settings ?? $website->theme_settings ?? [];
        $primaryColor = $theme['primary'] ?? 'emerald';
        $header = $website->published_global_header ?? $website->global_header;
        $footer = $website->published_global_footer ?? $website->global_footer;
        $pages = $website->pages()
            ->where(function ($query) {
                $query->where('status', 'published')
                    ->orWhereNotNull('published_html')
                    ->orWhereNotNull('published_blocks');
            })
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'parent_id', 'title', 'slug', 'page_type', 'published_html', 'published_blocks', 'blocks']);

        $publishedPostsByPage = $website->blogPosts()
            ->where('status', 'published')
            ->whereIn('page_id', $pages->where('page_type', 'blog')->pluck('id'))
            ->orderByDesc('is_featured')
            ->latest('published_at')
            ->get(['id', 'page_id', 'title', 'slug', 'excerpt', 'content', 'category', 'tags', 'image_url', 'is_featured', 'published_at'])
            ->groupBy('page_id');

        $pagePaths = $this->pagePaths($pages);
        $header = $this->staticNavigationHeader($header, $pages, $pagePaths);

        return [
            'status' => 'success',
            'website_name' => $website->name,
            'global_header' => is_array($header) ? CmsHtmlCompiler::compile([$header], $primaryColor) : '',
            'global_footer' => is_array($footer) ? CmsHtmlCompiler::compile([$footer], $primaryColor) : '',
            'pages' => $pages
                ->flatMap(function (Page $page) use ($primaryColor, $publishedPostsByPage, $pagePaths) {
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
                        'title' => $page->title,
                        'slug' => $page->slug,
                        // Every page gets its own directory. This makes
                        // parent/child routes predictable: /about/team/.
                        'output_path' => ($pageDirectory === '' ? 'index.html' : $pageDirectory . '/index.html'),
                        'html' => $page->page_type === 'blog'
                            ? CmsHtmlCompiler::compile($blocks, $primaryColor, ['blog_posts' => $posts])
                            : ($page->published_html ?? CmsHtmlCompiler::compile($blocks, $primaryColor)),
                    ]];

                    if ($page->page_type !== 'blog') {
                        return $pagePackage;
                    }

                    $articlePackage = $publishedPostsByPage->get($page->id, collect())
                        ->map(fn ($post) => [
                            'title' => $post->title,
                            'slug' => $postDirectory . '/' . $post->slug,
                            'output_path' => $postDirectory . '/' . $post->slug . '.html',
                            'html' => str_replace(
                                "href='blog/'",
                                "href='" . $postDirectory . "/'",
                                CmsHtmlCompiler::compileBlogPost($post->toArray(), $primaryColor)
                            ),
                        ])
                        ->all();

                    return array_merge($pagePackage, $articlePackage);
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * Convert menu targets that match published page slugs into static-file
     * links. External URLs and in-page anchors intentionally stay untouched.
     */
    private function staticNavigationHeader(?array $header, $pages, array $pagePaths): ?array
    {
        if (! is_array($header) || ! is_array($header['menu'] ?? null)) {
            return $header;
        }

        $publishedTargets = [];
        foreach ($pages as $page) {
            $path = trim((string) ($pagePaths[$page->id] ?? $page->slug), '/');
            $url = $path === '' ? './' : $path . '/';
            $publishedTargets[strtolower($path)] = $url;
            $publishedTargets[strtolower(trim((string) $page->slug, '/'))] ??= $url;
        }

        $header['menu'] = array_map(function ($item) use ($publishedTargets) {
            if (! is_array($item)) {
                return $item;
            }

            $item['url'] = $this->staticNavigationTarget((string) ($item['url'] ?? ''), $publishedTargets);
            if (is_array($item['children'] ?? null)) {
                $item['children'] = array_map(function ($child) use ($publishedTargets) {
                    if (! is_array($child)) return $child;
                    $child['url'] = $this->staticNavigationTarget((string) ($child['url'] ?? ''), $publishedTargets);
                    if (is_array($child['children'] ?? null)) {
                        $child['children'] = array_map(function ($grandchild) use ($publishedTargets) {
                            if (! is_array($grandchild)) return $grandchild;
                            $grandchild['url'] = $this->staticNavigationTarget((string) ($grandchild['url'] ?? ''), $publishedTargets);
                            return $grandchild;
                        }, $child['children']);
                    }
                    return $child;
                }, $item['children']);
            }

            return $item;
        }, $header['menu']);

        if (array_key_exists('cta_url', $header)) {
            $header['cta_url'] = $this->staticNavigationTarget((string) $header['cta_url'], $publishedTargets);
        }

        return $header;
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
