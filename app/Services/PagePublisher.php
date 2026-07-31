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
        return CmsHtmlCompiler::compile($page->blocks ?? [], $primaryColor, ['page_style' => $page->page_style]);
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
            ->get(['id', 'parent_id', 'title', 'slug', 'page_type', 'page_style', 'published_page_style', 'published_html', 'published_blocks', 'blocks']);

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
                            ? CmsHtmlCompiler::compile($blocks, $primaryColor, ['blog_posts' => $posts, 'page_style' => $page->published_page_style ?? $page->page_style])
                            : ($page->published_html ?? CmsHtmlCompiler::compile($blocks, $primaryColor, ['page_style' => $page->published_page_style ?? $page->page_style])),
                    ]];

                    if ($page->page_type !== 'blog') {
                        return $pagePackage;
                    }

                    $articlePackage = $publishedPostsByPage->get($page->id, collect())
                        ->map(fn ($post) => [
                            'title' => $post->title,
                            'slug' => $postDirectory . '/' . $post->slug,
                            'output_path' => $postDirectory . '/' . $post->slug . '.html',
                            // Compile the complete Blog page composition so the live
                            // article keeps the same Mini Header, Single Post body,
                            // Newsletter, and Latest Resources seen in Builder.
                            'html' => CmsHtmlCompiler::compile($blocks, $primaryColor, [
                                'blog_posts' => $posts,
                                'single_blog_post' => $post->toArray(),
                                'blog_index_url' => $postDirectory . '/',
                                'page_style' => $page->published_page_style ?? $page->page_style,
                            ]),
                        ])
                        ->all();

                    return array_merge($pagePackage, $articlePackage);
                })
                ->values()
                ->all(),
        ];
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

        // Then merge the published page hierarchy into that menu. This adds
        // missing Level 2 and Level 3 links without removing custom/external links.
        $pageTree = $this->publishedPageMenuTree($pages, $pagePaths);
        $header['menu'] = $this->mergePublishedMenu($menu, $pageTree, 0);

        if (array_key_exists('cta_url', $header)) {
            $header['cta_url'] = $this->staticNavigationTarget((string) $header['cta_url'], $publishedTargets);
        }

        return $header;
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
