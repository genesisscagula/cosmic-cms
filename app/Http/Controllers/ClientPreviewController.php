<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Website;
use App\Services\PlanCapabilityService;
use App\Services\WorkspaceWhiteLabelService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClientPreviewController extends Controller
{
    public function show(Request $request, Website $website, PlanCapabilityService $plans, WorkspaceWhiteLabelService $whiteLabel)
    {
        $user = $request->user();

        abort_unless($user?->isClient(), 403, 'Client preview access is only available to client accounts.');
        $this->authorize('view', $website);

        $pages = $website->pages()
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Page $page) => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'status' => $page->status ?: 'draft',
                'published_at' => $page->published_at?->toIso8601String(),
                'updated_at' => $page->updated_at?->diffForHumans(),
                'has_preview' => filled($page->published_html),
                'preview_html' => $this->safePreviewHtml($page->published_html),
            ])
            ->values();

        $workspace = $website->workspace;
        $owner = $workspace?->owner;
        $level = $owner ? (string) data_get($plans->forUser($owner), 'capabilities.white_label_level', 'none') : 'none';

        return Inertia::render('Client/Preview', [
            'branding' => $whiteLabel->forWorkspace($workspace, $level),
            'website' => [
                'id' => $website->id,
                'name' => $website->name ?: 'Untitled Website',
                'domain' => $website->domain,
                'industry' => $website->industry,
                'location' => $website->location,
                'status' => $website->status ?: 'active',
                'updated_at' => $website->updated_at?->diffForHumans(),
            ],
            'pages' => $pages,
        ]);
    }

    private function safePreviewHtml(?string $html): ?string
    {
        if (! filled($html)) {
            return null;
        }

        // Published HTML is rendered in a sandboxed iframe. Remove base tags so a
        // generated document cannot redirect asset/navigation resolution outside
        // the isolated client preview surface.
        return preg_replace('/<base\b[^>]*>/i', '', $html) ?: $html;
    }
}
