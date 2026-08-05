<?php

namespace App\Http\Controllers;

use App\Models\ContactSubmission;
use App\Models\Website;
use App\Services\PlanCapabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgencyLeadController extends Controller
{
    public function update(Request $request, ContactSubmission $lead, PlanCapabilityService $capabilities)
    {
        $this->assertAccess($request, $lead, $capabilities);

        $validated = $request->validate([
            'lead_status' => ['required', 'in:new,contacted,qualified,customer,lost'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $status = $validated['lead_status'];
        $lead->update([
            'lead_status' => $status,
            'notes' => array_key_exists('notes', $validated) ? $validated['notes'] : $lead->notes,
            'read_at' => $status === 'new' ? $lead->read_at : ($lead->read_at ?? now()),
            'qualified_at' => $status === 'qualified' ? ($lead->qualified_at ?? now()) : $lead->qualified_at,
            'converted_at' => $status === 'customer' ? ($lead->converted_at ?? now()) : ($status === 'lost' ? null : $lead->converted_at),
        ]);

        return response()->json([
            'message' => 'Lead status updated.',
            'lead' => $lead->fresh()->load('website:id,name,industry'),
        ]);
    }

    public function export(Request $request, PlanCapabilityService $capabilities): StreamedResponse
    {
        $user = $request->user();
        $plan = $capabilities->forUser($user);
        $levels = (array) ($plan['capabilities'] ?? []);

        abort_unless(($plan['plan_family'] ?? null) === 'agency' && in_array(($levels['leads_level'] ?? 'none'), ['aggregated', 'full_agency'], true), 403);

        $websiteIds = $this->websiteIdsFor($user);
        $query = ContactSubmission::query()->with('website:id,name,industry')->whereIn('website_id', $websiteIds);

        if ($request->filled('website') && $request->input('website') !== 'all') {
            $query->where('website_id', (int) $request->input('website'));
        }
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('lead_status', $request->input('status'));
        }
        if ($request->filled('source') && $request->input('source') !== 'all') {
            $query->where('source', $request->input('source'));
        }
        if ($request->filled('start')) {
            $query->whereDate('received_at', '>=', $request->date('start'));
        }
        if ($request->filled('end')) {
            $query->whereDate('received_at', '<=', $request->date('end'));
        }

        $filename = 'cosmic-agency-leads-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Name', 'Email', 'Phone', 'Website', 'Industry', 'Source', 'Status', 'Message', 'Received']);
            $query->latest('received_at')->chunk(250, function ($leads) use ($handle) {
                foreach ($leads as $lead) {
                    fputcsv($handle, [
                        $lead->name,
                        $lead->email,
                        $lead->phone,
                        $lead->website?->name,
                        $lead->website?->industry,
                        Str::headline($lead->source),
                        Str::headline($lead->lead_status),
                        $lead->message,
                        $lead->received_at?->toIso8601String(),
                    ]);
                }
            });
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function assertAccess(Request $request, ContactSubmission $lead, PlanCapabilityService $capabilities): void
    {
        $user = $request->user();
        $plan = $capabilities->forUser($user);
        $levels = (array) ($plan['capabilities'] ?? []);

        abort_unless(($plan['plan_family'] ?? null) === 'agency' && in_array(($levels['leads_level'] ?? 'none'), ['aggregated', 'full_agency'], true), 403);
        abort_unless($this->websiteIdsFor($user)->contains($lead->website_id), 404);
    }

    private function websiteIdsFor($user)
    {
        return Website::query()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('workspace', function ($workspaceQuery) use ($user) {
                        $workspaceQuery->where('owner_user_id', $user->id)
                            ->orWhereHas('users', fn ($memberQuery) => $memberQuery->whereKey($user->id));
                    });
            })
            ->pluck('id');
    }
}
