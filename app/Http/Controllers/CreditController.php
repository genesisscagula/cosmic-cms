<?php

namespace App\Http\Controllers;

use App\Cosmic\Pricing\CreditPackageRegistry;
use App\Services\CreditService;
use App\Services\PaymentCountryResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CreditController extends Controller
{
    public function index(Request $request, PaymentCountryResolver $paymentCountry): Response
    {
        $user = $request->user();
        $activeSubscriptionOrder = $user->paymentOrders()
            ->where('product_type', 'plan')
            ->whereNotNull('external_subscription_id')
            ->latest('id')
            ->first();

        $planKey = (string) ($user->plan_key ?? '');
        $planConfig = $planKey !== ''
            ? config("payments.plans.{$planKey}")
            : null;

        return Inertia::render('Credits/Index', [
            'balance' => (int) $user->credits,
            'packages' => CreditPackageRegistry::all(),
            'plans' => config('payments.plans', []),
            'currentPlan' => $planConfig ? [
                'key' => $planKey,
                'label' => $planConfig['label'] ?? ucfirst($planKey),
                'credits' => (int) ($planConfig['credits'] ?? 0),
                'price_usd' => (float) ($planConfig['price_usd'] ?? 0),
                'status' => (string) ($user->plan_status ?: 'inactive'),
                'provider' => (string) ($user->plan_provider ?: ''),
                'renews_at' => $user->plan_renews_at?->toIso8601String(),
                'subscription_id' => $activeSubscriptionOrder?->external_subscription_id,
                'cancel_at_period_end' => (bool) $user->plan_cancel_at_period_end,
                'cancelled_at' => $user->plan_cancelled_at?->toIso8601String(),
                'has_access' => in_array(strtolower((string) $user->plan_status), ['active', 'approved'], true)
                    || ((bool) $user->plan_cancel_at_period_end && $user->plan_renews_at?->isFuture()),
            ] : null,
            'paymentRouting' => $paymentCountry->payload($request),
            'developerPurchasesEnabled' => app()->environment(['local', 'testing']),
            'statusMessage' => session('status'),
            'paymentError' => session('payment_error'),
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
