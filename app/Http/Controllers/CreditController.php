<?php

namespace App\Http\Controllers;

use App\Cosmic\Pricing\CreditPackageRegistry;
use App\Services\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CreditController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Credits/Index', [
            'balance' => (int) $user->credits,
            'packages' => CreditPackageRegistry::all(),
            'paymentsEnabled' => false,
            'developerPurchasesEnabled' => app()->environment(['local', 'testing']),
            'transactions' => $user->creditTransactions()
                ->with('website:id,name')
                ->latest()
                ->paginate(20)
                ->through(fn ($transaction) => [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount,
                    'balance_after' => $transaction->balance_after,
                    'description' => $transaction->description,
                    'reference' => $transaction->reference,
                    'website' => $transaction->website?->only(['id', 'name']),
                    'created_at' => $transaction->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function balance(Request $request): JsonResponse
    {
        return response()->json([
            'credit_balance' => (int) $request->user()->fresh()->credits,
        ]);
    }

    public function purchase(Request $request, CreditService $credits): JsonResponse
    {
        abort_unless(app()->environment(['local', 'testing']), 403, 'Credit purchases are not enabled yet.');

        $packageKeys = array_keys(CreditPackageRegistry::all());
        $validated = $request->validate([
            'package' => ['required', 'string', Rule::in([...$packageKeys, 'developer_1000'])],
        ]);

        if ($validated['package'] === 'developer_1000') {
            $package = ['label' => 'Developer Test Top-up', 'credits' => 1000, 'price_usd' => 0];
        } else {
            $package = CreditPackageRegistry::get($validated['package']);
        }

        $transaction = $credits->grant(
            $request->user(),
            (int) $package['credits'],
            $package['label'].' credit purchase (developer simulation)',
            'dev-credit-purchase-'.uniqid(),
            [
                'package' => $validated['package'],
                'price_usd' => $package['price_usd'],
                'simulated' => true,
            ],
        );

        return response()->json([
            'message' => $package['credits'].' credits added successfully.',
            'credits_added' => (int) $package['credits'],
            'credit_balance' => (int) $transaction->balance_after,
        ]);
    }
}
