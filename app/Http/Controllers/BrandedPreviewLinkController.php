<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Website;
use App\Models\WebsitePreviewLink;
use App\Services\PlanCapabilityService;
use App\Services\WorkspaceWhiteLabelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class BrandedPreviewLinkController extends Controller
{
    public function store(Request $request, Website $website, PlanCapabilityService $plans)
    {
        $this->authorize('update', $website);
        $workspace = $website->workspace;
        abort_unless($workspace && (int) $workspace->owner_user_id === (int) $request->user()->id, 403);
        $capabilities = $plans->forUser($request->user());
        abort_unless(($capabilities['plan_family'] ?? null) === 'agency' && data_get($capabilities, 'capabilities.white_label_level', 'none') !== 'none', 403, 'Branded preview links require an eligible Agency plan.');

        $validated = $request->validate([
            'label' => ['nullable','string','max:100'],
            'expires_in_days' => ['nullable','integer','in:1,3,7,14,30,90'],
        ]);

        $link = WebsitePreviewLink::create([
            'workspace_id' => $workspace->id,
            'website_id' => $website->id,
            'created_by_user_id' => $request->user()->id,
            'token' => (string) Str::uuid(),
            'label' => $validated['label'] ?? null,
            'expires_at' => filled($validated['expires_in_days'] ?? null) ? now()->addDays((int) $validated['expires_in_days']) : null,
        ]);

        return back()->with('success', 'Branded preview link created.')->with('preview_url', route('preview-links.show', $link->token));
    }

    public function revoke(Request $request, WebsitePreviewLink $previewLink)
    {
        $website = $previewLink->website;
        $this->authorize('update', $website);
        abort_unless((int) $website->workspace?->owner_user_id === (int) $request->user()->id, 403);
        $previewLink->update(['revoked_at' => now()]);
        return back()->with('success', 'Preview link revoked.');
    }

    public function show(string $token, PlanCapabilityService $plans, WorkspaceWhiteLabelService $whiteLabel)
    {
        $link = WebsitePreviewLink::with(['website.pages','workspace.owner'])->where('token', $token)->firstOrFail();
        abort_unless($link->isUsable(), 410, 'This preview link has expired or been revoked.');

        DB::table('website_preview_links')->where('id', $link->id)->update([
            'views' => DB::raw('views + 1'), 'last_viewed_at' => now(), 'updated_at' => now(),
        ]);

        $website = $link->website;
        $workspace = $link->workspace;
        $owner = $workspace?->owner;
        $level = $owner ? (string) data_get($plans->forUser($owner), 'capabilities.white_label_level', 'none') : 'none';
        abort_unless($level !== 'none', 403, 'This branded preview is no longer available.');

        $pages = $website->pages->sortBy([['parent_id','asc'],['sort_order','asc'],['id','asc']])->map(fn(Page $page) => [
            'id'=>$page->id,'title'=>$page->title,'slug'=>$page->slug,'status'=>$page->status ?: 'draft',
            'updated_at'=>$page->updated_at?->diffForHumans(),'has_preview'=>filled($page->published_html),
            'preview_html'=>$this->safePreviewHtml($page->published_html),
        ])->values();

        return Inertia::render('Public/BrandedPreview', [
            'branding'=>$whiteLabel->forWorkspace($workspace, $level),
            'website'=>['name'=>$website->name ?: 'Untitled Website','industry'=>$website->industry,'location'=>$website->location],
            'pages'=>$pages,
            'link'=>['label'=>$link->label,'expires_at'=>$link->expires_at?->toIso8601String()],
        ]);
    }

    private function safePreviewHtml(?string $html): ?string
    {
        if (! filled($html)) return null;
        return preg_replace('/<base\\b[^>]*>/i', '', $html) ?: $html;
    }
}
