<?php

namespace App\Services;

use App\Helpers\CmsHtmlCompiler;
use App\Models\Page;
use App\Models\Website;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PagePublisher
{
    /**
     * Compile the page for the live bridge and notify an optional deployment webhook.
     * A configured webhook is authoritative: a non-success response prevents publication.
     */
    public function publish(Page $page, Website $website): string
    {
        $theme = $website->theme_settings ?? [];
        $primaryColor = $theme['primary'] ?? 'emerald';
        $html = CmsHtmlCompiler::compile($page->blocks ?? [], $primaryColor);

        $this->syncStaticSite($page, $website, $html, $primaryColor);

        $webhookUrl = config('services.cosmic.publish_webhook_url');

        if (! $webhookUrl) {
            return $html;
        }

        $response = Http::timeout(15)
            ->acceptJson()
            ->post($webhookUrl, [
                'website_id' => $website->id,
                'page_id' => $page->id,
                'slug' => $page->slug,
                'html' => $html,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('The deployment webhook did not accept this publish request.');
        }

        return $html;
    }

    private function syncStaticSite(Page $page, Website $website, string $candidateHtml, string $primaryColor): void
    {
        $syncUrl = config('services.cosmic.static_sync_url');
        $syncToken = config('services.cosmic.static_sync_token');

        // Local/manual bridge mode remains available for installations that
        // have not configured a reachable static-site receiver yet.
        if (! $syncUrl && ! $syncToken) {
            return;
        }

        if (! $syncUrl || ! $syncToken) {
            throw new RuntimeException('Static publishing is not fully configured.');
        }

        $header = $website->global_header;
        $footer = $website->global_footer;
        $pages = $website->pages()
            ->where('status', 'published')
            ->where('id', '!=', $page->id)
            ->orderBy('id')
            ->get(['title', 'slug', 'published_html', 'published_blocks', 'blocks'])
            ->map(fn (Page $publishedPage) => [
                'title' => $publishedPage->title,
                'slug' => $publishedPage->slug,
                'html' => $publishedPage->published_html
                    ?? CmsHtmlCompiler::compile($publishedPage->published_blocks ?? $publishedPage->blocks ?? [], $primaryColor),
            ])
            ->all();

        $pages[] = [
            'title' => $page->title,
            'slug' => $page->slug,
            'html' => $candidateHtml,
        ];

        $response = Http::timeout(20)
            ->acceptJson()
            ->withHeaders(['X-Cosmic-Sync-Secret' => $syncToken])
            ->post($syncUrl, [
                'status' => 'success',
                'website_name' => $website->name,
                'global_header' => is_array($header) ? CmsHtmlCompiler::compile([$header], $primaryColor) : '',
                'global_footer' => is_array($footer) ? CmsHtmlCompiler::compile([$footer], $primaryColor) : '',
                'pages' => $pages,
            ]);

        if (! $response->successful() || $response->json('status') !== 'success') {
            throw new RuntimeException('The static site could not confirm this publish request.');
        }
    }
}
