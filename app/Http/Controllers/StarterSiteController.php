<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Services\RegisteredSiteBundleService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class StarterSiteController extends Controller
{
    public function plan(Request $request, Website $website, RegisteredSiteBundleService $bundles)
    {
        $this->authorize('update', $website);
        $validated = $request->validate(['prompt' => ['nullable', 'string', 'max:6000']]);
        $prompt = trim((string) ($validated['prompt'] ?? ''));
        $plan = $bundles->plan($website, $prompt);
        $settings = is_array($website->settings) ? $website->settings : [];
        $settings['starter_bundle_preview'] = ['bundle_key' => $plan['bundle_key'] ?? null, 'plan' => $plan, 'planned_at' => now()->toIso8601String()];
        $website->forceFill(['settings' => $settings])->save();

        return response()->json([
            'reply' => "I found a cohesive {$plan['bundle_name']} direction and prepared {$plan['page_count']} matching pages.",
            'plan' => $plan,
            'credit_balance' => $bundles->workspacePayload($website, $request->user())['credit_balance'],
        ]);
    }

    public function install(Request $request, Website $website, RegisteredSiteBundleService $bundles)
    {
        $this->authorize('update', $website);
        $validated = $request->validate([
            'bundle_key' => ['required', 'string', 'max:120'],
            'confirmed' => ['accepted'],
        ]);
        $preview = data_get($website->settings, 'starter_bundle_preview');
        $plan = is_array($preview) && is_array($preview['plan'] ?? null) ? $preview['plan'] : null;
        if (! is_array($plan) || ! hash_equals((string) $validated['bundle_key'], (string) ($plan['bundle_key'] ?? ''))) {
            throw ValidationException::withMessages([
                'bundle_key' => 'The starter bundle preview is stale. Reopen Starter Pages to refresh the recommended bundle.',
            ]);
        }

        $prompt = trim(collect([
            $website->name,
            $website->industry,
            $website->location,
            $website->business_description,
        ])->filter()->implode('. '));
        $payload = $bundles->install($website, $request->user(), $prompt, $plan);

        return response()->json([
            'message' => $payload['status'] === 'ready'
                ? 'Your matching starter pages are ready.'
                : 'Luna started building your matching starter pages.',
            'starter_site' => $payload,
        ], $payload['status'] === 'ready' ? 200 : 202);
    }

    public function status(Request $request, Website $website, RegisteredSiteBundleService $bundles)
    {
        $this->authorize('view', $website);

        return response()->json($bundles->workspacePayload($website->fresh(), $request->user()));
    }
}
