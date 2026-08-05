<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Services\PlanCapabilityService;
use App\Services\WorkspaceWhiteLabelService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgencyPortalController extends Controller
{
    public function index(Request $request, PlanCapabilityService $plans, WorkspaceWhiteLabelService $whiteLabel)
    {
        $user = $request->user();
        abort_unless($user->isClient(), 403, 'The custom agency portal is available to client accounts.');

        $websites = Website::query()
            ->whereHas('assignedUsers', fn ($query) => $query->whereKey($user->id))
            ->with(['workspace.owner'])
            ->withCount([
                'pages',
                'pages as published_pages_count' => fn ($query) => $query->where('status', 'published'),
            ])
            ->latest('updated_at')
            ->get();

        $workspace = $websites->pluck('workspace')->filter()->first();
        abort_unless($workspace && $workspace->owner, 404, 'No agency workspace is assigned to this client account.');

        $level = (string) data_get($plans->forUser($workspace->owner), 'capabilities.white_label_level', 'none');
        abort_unless($level === 'full', 403, 'This agency portal requires Pro Agency white-label access.');

        $branding = $whiteLabel->forWorkspace($workspace, $level);
        $brandingSettings = (array) data_get($workspace->settings, 'branding', []);

        return Inertia::render('Agency/Portal', [
            'client' => ['name' => $user->name, 'email' => $user->email],
            'branding' => array_merge($branding, [
                'portal_title' => $brandingSettings['portal_title'] ?? 'Welcome to your client portal',
                'portal_welcome' => $brandingSettings['portal_welcome'] ?? 'Review your websites, check publication status, and open secure previews from one place.',
            ]),
            'websites' => $websites->map(fn (Website $website) => [
                'id' => $website->id,
                'name' => $website->name ?: 'Untitled Website',
                'domain' => $website->domain,
                'industry' => $website->industry,
                'location' => $website->location,
                'pages_count' => (int) $website->pages_count,
                'published_pages_count' => (int) $website->published_pages_count,
                'status' => $website->published_pages_count > 0 ? 'Published' : 'Draft',
                'updated_at' => $website->updated_at?->diffForHumans(),
                'preview_url' => route('client.websites.preview', $website),
            ])->values(),
        ]);
    }
}
