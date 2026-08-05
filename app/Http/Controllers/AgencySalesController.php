<?php

namespace App\Http\Controllers;

use App\Models\SalesEvent;
use App\Models\Website;
use App\Services\PlanCapabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgencySalesController extends Controller
{
    public function store(Request $request, PlanCapabilityService $capabilities)
    {
        $websiteIds = $this->assertAccess($request, $capabilities);
        $validated = $request->validate([
            'website_id' => ['required', 'integer'],
            'contact_submission_id' => ['nullable', 'integer'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'product' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'currency' => ['required', 'string', 'size:3'],
            'status' => ['required', 'in:completed,pending,refunded,failed'],
            'source' => ['nullable', 'string', 'max:48'],
            'occurred_at' => ['required', 'date'],
        ]);

        abort_unless($websiteIds->contains((int) $validated['website_id']), 404);

        if (! empty($validated['contact_submission_id'])) {
            abort_unless(\App\Models\ContactSubmission::query()
                ->whereKey($validated['contact_submission_id'])
                ->where('website_id', (int) $validated['website_id'])
                ->exists(), 422);
        }

        $payload = $validated;
        unset($payload['amount']);

        $event = SalesEvent::create([
            ...$payload,
            'amount_minor' => (int) round(((float) $validated['amount']) * 100),
            'currency' => strtoupper($validated['currency']),
            'source' => $validated['source'] ?: 'manual',
        ]);

        return response()->json(['message' => 'Sale recorded.', 'sale' => $event->load('website:id,name')], 201);
    }

    public function export(Request $request, PlanCapabilityService $capabilities): StreamedResponse
    {
        $websiteIds = $this->assertAccess($request, $capabilities);
        $query = SalesEvent::query()->with('website:id,name')->whereIn('website_id', $websiteIds);

        if ($request->filled('website') && $request->input('website') !== 'all') {
            $query->where('website_id', (int) $request->input('website'));
        }
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('source') && $request->input('source') !== 'all') {
            $query->where('source', $request->input('source'));
        }
        if ($request->filled('start')) {
            $query->whereDate('occurred_at', '>=', $request->date('start'));
        }
        if ($request->filled('end')) {
            $query->whereDate('occurred_at', '<=', $request->date('end'));
        }

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Website', 'Customer', 'Email', 'Product', 'Amount', 'Currency', 'Status', 'Source', 'Occurred']);
            $query->latest('occurred_at')->chunk(250, function ($events) use ($handle) {
                foreach ($events as $event) {
                    fputcsv($handle, [
                        $event->website?->name,
                        $event->customer_name,
                        $event->customer_email,
                        $event->product,
                        number_format($event->amount_minor / 100, 2, '.', ''),
                        $event->currency,
                        Str::headline($event->status),
                        Str::headline($event->source),
                        $event->occurred_at?->toIso8601String(),
                    ]);
                }
            });
            fclose($handle);
        }, 'cosmic-agency-sales-' . now()->format('Y-m-d-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function assertAccess(Request $request, PlanCapabilityService $capabilities)
    {
        $user = $request->user();
        $plan = $capabilities->forUser($user);
        $levels = (array) ($plan['capabilities'] ?? []);
        abort_unless(($plan['plan_family'] ?? null) === 'agency' && in_array(($levels['sales_level'] ?? 'none'), ['summary', 'full_agency'], true), 403);

        return Website::query()->where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhereHas('workspace', function ($workspaceQuery) use ($user) {
                    $workspaceQuery->where('owner_user_id', $user->id)
                        ->orWhereHas('users', fn ($memberQuery) => $memberQuery->whereKey($user->id));
                });
        })->pluck('id');
    }
}
