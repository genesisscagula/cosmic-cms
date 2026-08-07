<?php

namespace App\Services;

use App\Models\Website;

class MediaAssetSafetyService
{
    /** @return array<int, string> */
    public function providerUrls(mixed $value): array
    {
        $urls = [];
        $walk = function (mixed $node, ?string $key = null) use (&$walk, &$urls): void {
            if (is_array($node)) {
                foreach ($node as $childKey => $child) {
                    $walk($child, is_string($childKey) ? $childKey : null);
                }
                return;
            }

            if (! is_string($node) || ! filter_var($node, FILTER_VALIDATE_URL)) {
                return;
            }

            if ($key !== null && ! $this->isImageField($key)) {
                return;
            }

            $host = strtolower((string) parse_url($node, PHP_URL_HOST));
            if ($this->isRemoteProviderHost($host)) {
                $urls[] = $node;
            }
        };

        $walk($value);

        return collect($urls)->unique()->values()->all();
    }

    /** @return array<int, string> */
    public function draftProviderUrls(Website $website): array
    {
        $urls = [];
        foreach ($website->pages()->get(['blocks']) as $page) {
            $urls = array_merge($urls, $this->providerUrls($page->blocks ?? []));
        }

        $urls = array_merge(
            $urls,
            $this->providerUrls($website->global_header ?? []),
            $this->providerUrls($website->global_footer ?? []),
        );

        return collect($urls)->unique()->values()->all();
    }

    /** @return array<int, string> */
    public function publishedProviderUrls(Website $website): array
    {
        $urls = [];
        foreach ($website->pages()->get(['published_blocks', 'blocks']) as $page) {
            $urls = array_merge($urls, $this->providerUrls($page->published_blocks ?? $page->blocks ?? []));
        }

        foreach ($website->blogPosts()->where('status', 'published')->get(['image_url']) as $post) {
            $urls = array_merge($urls, $this->providerUrls(['image_url' => $post->image_url]));
        }

        $urls = array_merge(
            $urls,
            $this->providerUrls($website->published_global_header ?? $website->global_header ?? []),
            $this->providerUrls($website->published_global_footer ?? $website->global_footer ?? []),
        );

        return collect($urls)->unique()->values()->all();
    }

    public function hasDraftProviderUrls(Website $website): bool
    {
        return $this->draftProviderUrls($website) !== [];
    }

    public function hasPublishedProviderUrls(Website $website): bool
    {
        return $this->publishedProviderUrls($website) !== [];
    }

    private function isRemoteProviderHost(string $host): bool
    {
        return str_contains($host, 'unsplash.com')
            || str_contains($host, 'pexels.com')
            || str_contains($host, 'images.pexels.com');
    }

    private function isImageField(string $key): bool
    {
        $key = strtolower($key);

        return str_contains($key, 'image')
            || str_contains($key, 'photo')
            || str_contains($key, 'poster')
            || str_contains($key, 'thumbnail');
    }
}
