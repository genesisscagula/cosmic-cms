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
        $validated = $request->validate(['prompt' => ['required', 'string', 'min:12', 'max:6000']]);
        $plan = $bundles->plan($website, trim((string) $validated['prompt']));

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
            'prompt' => ['required', 'string', 'min:12', 'max:6000'],
            'bundle_key' => ['required', 'string', 'max:120'],
            'confirmed' => ['accepted'],
        ]);
        $plan = $bundles->plan($website, trim((string) $validated['prompt']));
        if (! hash_equals((string) $validated['bundle_key'], (string) ($plan['bundle_key'] ?? ''))) {
            throw ValidationException::withMessages([
                'bundle_key' => 'The Luna plan changed before installation. Review the refreshed page plan and confirm again.',
            ]);
        }

        $payload = $bundles->install($website, $request->user(), trim((string) $validated['prompt']), $plan);

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
