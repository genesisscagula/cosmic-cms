<?php

namespace App\Services;

use App\Http\Controllers\PageController;
use App\Models\Page;
use App\Models\Website;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Batch 6 publish action adapter.
 *
 * Luna classifies publish intent; this service delegates real publication to
 * Cosmic's existing controllers/services. It never compiles or deploys its own
 * parallel copy of the publishing workflow.
 */
final class LunaPublishActionService
{
    public function __construct(
        private readonly PreviewDeploymentService $previews,
    ) {}

    /** @return array<string,mixed>|null */
    public function apply(Website $website, Request $request, string $prompt, array $canonicalIntent, ?Page $currentPage = null): ?array
    {
        if ((string) data_get($canonicalIntent, 'routing.menu_scope') !== 'publish') return null;
        $action = (string) data_get($canonicalIntent, 'routing.scope_action', '');
        if ($action === '') return null;

        return match ($action) {
            'publish_page', 'republish_page' => $this->publishPage($website, $request, $prompt, $currentPage, $action),
            'publish_site', 'republish_site' => $this->publishSite($website, $request, $action),
            'unpublish_page' => $this->unpublishPage($website, $prompt, $currentPage),
            'preview_page' => $this->previewPage($website, $prompt, $currentPage),
            'preview_site' => $this->previewSite($website),
            'export_site' => $this->exportSite($website),
            'publish_status' => $this->status($website, $prompt, $currentPage),
            default => ['handled' => false, 'scope_action' => $action],
        };
    }

    private function publishPage(Website $website, Request $request, string $prompt, ?Page $currentPage, string $action): array
    {
        $page = $this->resolvePage($website, $prompt, $currentPage);
        if (! $page) return $this->fail($action, 'I could not safely identify which page to publish.');

        try {
            /** @var JsonResponse $response */
            $response = app()->call([app(PageController::class), 'publish'], [
                'request' => $request,
                'page' => $page,
            ]);
            return $this->fromJsonResponse($action, $response, [
                ['op' => 'publish.page', 'page_id' => $page->id],
            ]);
        } catch (Throwable $e) {
            report($e);
            return $this->fail($action, 'Publishing failed. Your previous published version remains available.');
        }
    }

    private function publishSite(Website $website, Request $request, string $action): array
    {
        $pages = $website->pages()->whereNotIn('page_type', ['commerce'])->orderBy('sort_order')->orderBy('id')->get();
        if ($pages->isEmpty()) return $this->fail($action, 'This website has no publishable pages yet.');

        $ops = [];
        $published = [];
        foreach ($pages as $page) {
            try {
                /** @var JsonResponse $response */
                $response = app()->call([app(PageController::class), 'publish'], [
                    'request' => $request,
                    'page' => $page,
                ]);
                $payload = (array) $response->getData(true);
                if ($response->getStatusCode() >= 300) {
                    return [
                        'handled' => true, 'success' => false, 'scope_action' => $action,
                        'message' => (string) ($payload['message'] ?? 'One of the website pages could not be published.'),
                        'status_code' => $response->getStatusCode(), 'operations' => $ops,
                        'published_page_ids' => $published,
                        'publish_result' => $payload,
                    ];
                }
                $published[] = $page->id;
                $ops[] = ['op' => 'publish.page', 'page_id' => $page->id];
            } catch (Throwable $e) {
                report($e);
                return $this->fail($action, 'Website publishing stopped because one page failed. Pages published before the failure remain published.', $ops);
            }
        }

        $website->refresh();
        return [
            'handled' => true, 'success' => true, 'scope_action' => $action,
            'operations' => $ops,
            'published_page_ids' => $published,
            'preview_url' => $this->previews->url($website),
        ];
    }

    private function unpublishPage(Website $website, string $prompt, ?Page $currentPage): array
    {
        $page = $this->resolvePage($website, $prompt, $currentPage);
        if (! $page) return $this->fail('unpublish_page', 'I could not safely identify which page to unpublish.');

        $page->forceFill([
            'status' => 'draft',
            'published_blocks' => null,
            'published_html' => null,
            'published_at' => null,
            'last_published_at' => null,
            'publish_error' => null,
        ])->save();

        $previewUrl = null;
        $remaining = $website->pages()->where(function ($q) {
            $q->where('status', 'published')->orWhereNotNull('published_html')->orWhereNotNull('published_blocks');
        })->exists();
        try {
            if ($remaining) $previewUrl = $this->previews->deploy($website->fresh());
            else $this->previews->remove($website->fresh());
        } catch (Throwable $e) {
            report($e);
            return [
                'handled' => true, 'success' => true, 'scope_action' => 'unpublish_page',
                'message' => 'The page was unpublished, but the preview deployment could not be refreshed.',
                'operations' => [['op' => 'publish.page.unpublish', 'page_id' => $page->id]],
                'preview_deployment_failed' => true,
            ];
        }

        return [
            'handled' => true, 'success' => true, 'scope_action' => 'unpublish_page',
            'operations' => [['op' => 'publish.page.unpublish', 'page_id' => $page->id]],
            'page' => $page->fresh(), 'preview_url' => $previewUrl,
        ];
    }

    private function previewPage(Website $website, string $prompt, ?Page $currentPage): array
    {
        $page = $this->resolvePage($website, $prompt, $currentPage);
        if (! $page) return $this->fail('preview_page', 'I could not safely identify which page to preview.');
        if (! $this->hasPublishedSnapshot($page)) return $this->fail('preview_page', 'Publish this page first so Cosmic has an approved preview snapshot.');

        try {
            $this->previews->deploy($website->fresh());
            return [
                'handled' => true, 'success' => true, 'scope_action' => 'preview_page',
                'operations' => [['op' => 'preview.page', 'page_id' => $page->id]],
                'preview_url' => $this->previews->urlForPage($website->fresh(), $page->fresh()),
                'page' => $page->fresh(),
            ];
        } catch (Throwable $e) {
            report($e);
            return $this->fail('preview_page', $e->getMessage());
        }
    }

    private function previewSite(Website $website): array
    {
        try {
            $url = $this->previews->deploy($website->fresh());
            return [
                'handled' => true, 'success' => true, 'scope_action' => 'preview_site',
                'operations' => [['op' => 'preview.site', 'website_id' => $website->id]],
                'preview_url' => $url,
            ];
        } catch (Throwable $e) {
            report($e);
            return $this->fail('preview_site', $e->getMessage());
        }
    }

    private function exportSite(Website $website): array
    {
        return [
            'handled' => true, 'success' => true, 'scope_action' => 'export_site',
            'operations' => [['op' => 'export.site.prepare', 'website_id' => $website->id]],
            'export_url' => route('websites.deployment-connector.download', $website),
        ];
    }

    private function status(Website $website, string $prompt, ?Page $currentPage): array
    {
        $page = $this->resolvePage($website, $prompt, $currentPage);
        $pages = $website->pages()->orderBy('sort_order')->orderBy('id')->get();
        return [
            'handled' => true, 'success' => true, 'scope_action' => 'publish_status',
            'operations' => [['op' => 'publish.status.read', 'website_id' => $website->id]],
            'publish_status' => [
                'website_id' => $website->id,
                'published_pages' => $pages->filter(fn (Page $p) => $this->hasPublishedSnapshot($p))->count(),
                'total_pages' => $pages->count(),
                'current_page' => $page ? [
                    'id' => $page->id,
                    'title' => $page->title,
                    'slug' => $page->slug,
                    'status' => $page->status,
                    'has_published_snapshot' => $this->hasPublishedSnapshot($page),
                    'last_published_at' => $page->last_published_at?->toISOString(),
                    'publish_error' => $page->publish_error,
                ] : null,
                'last_preview_deployed_at' => $website->last_preview_deployed_at?->toISOString(),
                'last_live_deployed_at' => $website->last_deployed_at?->toISOString(),
                'deployment_error' => $website->deployment_error,
                'preview_deployment_error' => $website->preview_deployment_error,
            ],
        ];
    }

    private function resolvePage(Website $website, string $prompt, ?Page $current): ?Page
    {
        $pages = $website->pages()->orderBy('sort_order')->orderBy('id')->get();
        $q = mb_strtolower($prompt);
        foreach ($pages as $page) {
            $title = mb_strtolower(trim((string) $page->title));
            $slug = mb_strtolower(trim((string) $page->slug));
            if (($title !== '' && str_contains($q, $title)) || ($slug !== '' && preg_match('/\\b'.preg_quote($slug, '/').'\\b/i', $prompt))) return $page;
        }
        return $current && (int) $current->website_id === (int) $website->id ? $current : null;
    }

    private function hasPublishedSnapshot(Page $page): bool
    {
        return $page->status === 'published' || $page->published_html !== null || $page->published_blocks !== null;
    }

    private function fromJsonResponse(string $action, JsonResponse $response, array $ops): array
    {
        $payload = (array) $response->getData(true);
        $ok = $response->getStatusCode() < 300;
        return [
            'handled' => true, 'success' => $ok, 'scope_action' => $action,
            'message' => $ok ? null : (string) ($payload['message'] ?? 'The publish action could not be completed.'),
            'status_code' => $response->getStatusCode(), 'operations' => $ok ? $ops : [],
            'publish_result' => $payload,
            'preview_url' => $payload['preview_url'] ?? null,
        ];
    }

    private function fail(string $action, string $message, array $ops = []): array
    {
        return ['handled' => true, 'success' => false, 'scope_action' => $action, 'message' => $message, 'operations' => $ops];
    }
}
