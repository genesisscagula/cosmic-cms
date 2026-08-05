<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\AgencyInsightsService;
use App\Services\PlanCapabilityService;
use Illuminate\Http\Request;
use App\Services\WorkspaceWhiteLabelService;
use Inertia\Inertia;

class AgencyReportController extends Controller
{
    public function index(Request $request, PlanCapabilityService $plans, AgencyInsightsService $insights, WorkspaceWhiteLabelService $whiteLabel)
    {
        $user = $request->user();
        $workspace = Workspace::query()->where('owner_user_id', $user->id)->first();
        abort_unless($workspace, 404, 'Agency workspace not found.');

        $plan = $plans->forUser($user);
        $level = (string) data_get($plan, 'capabilities.white_label_level', 'none');
        abort_unless(($plan['plan_family'] ?? null) === 'agency' && $level !== 'none', 403, 'Branded reports are not available on the current plan.');

        $validated = $request->validate([
            'website_id' => ['nullable', 'integer'],
            'days' => ['nullable', 'integer', 'in:7,30,90'],
        ]);

        $filters = [
            'website_id' => $validated['website_id'] ?? null,
            'days' => $validated['days'] ?? 30,
        ];
        $report = $insights->build($user, $plan, $filters);
        $branding = $whiteLabel->forWorkspace($workspace, $level);

        return Inertia::render('Agency/Report', [
            'report' => $report,
            'filters' => $filters,
            'branding' => array_merge($branding, [
                'remove_cosmic_branding' => $branding['cosmic_branding_removed'],
            ]),
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
