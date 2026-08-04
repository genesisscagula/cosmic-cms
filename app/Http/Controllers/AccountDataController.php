<?php

namespace App\Http\Controllers;

use App\Services\AccountDataService;
use App\Services\SubscriptionManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountDataController extends Controller
{
    public function show(Request $request, string $section, AccountDataService $data, SubscriptionManagementService $subscriptions): JsonResponse
    {
        validator(['section' => $section], [
            'section' => ['required', Rule::in(['credits', 'subscription', 'workspace', 'profile', 'settings'])],
        ])->validate();

        $user = $request->user()->fresh();
        $payload = match ($section) {
            'credits' => $data->credits($user),
            'subscription' => $data->subscription($user, $subscriptions->currentOrder($user)),
            'workspace' => $data->workspace($user),
            'profile' => $data->profile($user),
            'settings' => $data->settings($user),
        };

        return response()->json([
            'data' => $payload,
            'meta' => [
                'section' => $section,
                'generated_at' => now()->toIso8601String(),
                'contract_version' => '1.0',
            ],
        ]);
    }
}
