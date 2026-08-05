<?php

namespace App\Http\Controllers;

use App\Cosmic\Pricing\CreditPackageRegistry;
use App\Services\CreditService;
use App\Services\AccountDataService;
use App\Services\PaymentCountryResolver;
use App\Services\PlanRegistry;
use App\Services\SubscriptionManagementService;
use App\Support\SubscriptionStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CreditController extends Controller
{
    public function index(Request $request, PaymentCountryResolver $paymentCountry, SubscriptionManagementService $subscriptions, AccountDataService $accountData, PlanRegistry $plans): Response
    {
        $user = $request->user();
        $activeSubscriptionOrder = $subscriptions->currentOrder($user);

        $planKey = (string) ($user->plan_key ?? '');
        $lifecycleStatus = SubscriptionStatus::normalize($user->plan_status);
        $statusBadge = SubscriptionStatus::badge($lifecycleStatus, (bool) $user->plan_cancel_at_period_end);
        $planConfig = $planKey !== '' ? $plans->find($planKey) : null;

        $resumableOrder = $user->paymentOrders()
            ->where('provider', 'paypal')
            ->where('product_type', 'plan')
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subDay())
            ->latest('id')
            ->first();
        $resumableUrl = (string) data_get($resumableOrder?->metadata, 'checkout_url', '');

        $creditsSummary = $accountData->credits($user);


        return Inertia::render('Credits/Index', [
            'balance' => (int) $user->credits,
            'creditsSummary' => $creditsSummary,
            'packages' => CreditPackageRegistry::all(),
            'plans' => $plans->forClient(),
            'currentPlan' => $planConfig ? [
                'key' => $planKey,
                'label' => $planConfig['label'] ?? ucfirst($planKey),
                'credits' => (int) ($planConfig['credits'] ?? 0),
                'price_usd' => (float) ($planConfig['price_usd'] ?? 0),
                'status' => $lifecycleStatus,
                'status_badge' => $statusBadge,
                'provider' => (string) ($user->plan_provider ?: ''),
                'renews_at' => $user->plan_renews_at?->toIso8601String(),
                'subscription_id' => $activeSubscriptionOrder?->external_subscription_id,
                'started_at' => ($activeSubscriptionOrder?->paid_at
                    ?? $activeSubscriptionOrder?->fulfilled_at
                    ?? $activeSubscriptionOrder?->created_at)?->toIso8601String(),
                'price_usd' => (float) ($planConfig['price_usd'] ?? 0),
                'billing_cycle' => 'Monthly',
                'order_reference' => $activeSubscriptionOrder?->reference,
                'order_status' => $activeSubscriptionOrder?->status,
                'last_payment_at' => $user->billingTransactions()
                    ->where('status', 'completed')
                    ->latest('occurred_at')
                    ->first()?->occurred_at?->toIso8601String(),
                'cancel_at_period_end' => (bool) $user->plan_cancel_at_period_end,
                'cancelled_at' => $user->plan_cancelled_at?->toIso8601String(),
                'has_access' => SubscriptionStatus::grantsAccess(
                    $lifecycleStatus,
                    (bool) $user->plan_cancel_at_period_end,
                    $user->plan_renews_at,
                ),
                'status_changed_at' => $user->plan_status_changed_at?->toIso8601String(),
                'past_due_at' => $user->plan_past_due_at?->toIso8601String(),
                'suspended_at' => $user->plan_suspended_at?->toIso8601String(),
                'expired_at' => $user->plan_expired_at?->toIso8601String(),
                'last_synced_at' => $user->plan_last_synced_at?->toIso8601String(),
                'recovery_attempted_at' => $user->plan_recovery_attempted_at?->toIso8601String(),
                'recovery_error' => $user->plan_recovery_error,
                'can_recover' => in_array($lifecycleStatus, [SubscriptionStatus::PAST_DUE, SubscriptionStatus::SUSPENDED], true),
            ] : null,
            'pendingCheckout' => ($resumableOrder && $resumableUrl !== '') ? [
                'order_reference' => $resumableOrder->reference,
                'plan_key' => $resumableOrder->product_key,
                'plan_label' => data_get($plans->find($resumableOrder->product_key), 'label', ucfirst($resumableOrder->product_key)),
                'checkout_url' => $resumableUrl,
                'cancelled_at' => data_get($resumableOrder->metadata, 'checkout_cancelled_at'),
                'change_type' => data_get($resumableOrder->metadata, 'plan_change_type'),
                'previous_plan_key' => data_get($resumableOrder->metadata, 'previous_plan_key'),
                'is_plan_change' => (bool) data_get($resumableOrder->metadata, 'previous_subscription_id'),
            ] : null,
            'paymentRouting' => $paymentCountry->payload($request),
            'developerPurchasesEnabled' => app()->environment(['local', 'testing']),
            'statusMessage' => session('status')
                ?: ($request->boolean('subscription_cancelled') ? 'Subscription cancelled. Your plan remains available until the end of the paid period.' : null)
                ?: ($request->boolean('billing_recovered') ? 'Billing recovered. Your subscription is active again.' : null),
            'paymentError' => session('payment_error'),
            'billingTransactions' => $user->billingTransactions()
                ->latest('occurred_at')
                ->limit(10)
                ->get()
                ->map(fn ($transaction) => [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'status' => $transaction->status,
                    'provider' => $transaction->provider,
                    'amount_minor' => (int) $transaction->amount_minor,
                    'currency' => $transaction->currency,
                    'credits_granted' => (int) $transaction->credits_granted,
                    'occurred_at' => $transaction->occurred_at?->toIso8601String(),
                ]),
            'transactions' => $user->creditTransactions()
                ->with('website:id,name')
                ->latest()
                ->paginate(20)
                ->through(fn ($transaction) => [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'category' => $transaction->category ?: 'other',
                    'amount' => $transaction->amount,
                    'balance_after' => $transaction->balance_after,
                    'description' => $transaction->description,
                    'reference' => $transaction->reference,
                    'website' => $transaction->website?->only(['id', 'name']),
                    'created_at' => $transaction->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function balance(Request $request, AccountDataService $accountData): JsonResponse
    {
        $summary = $accountData->credits($request->user()->fresh());

        return response()->json([
            'credit_balance' => $summary['current_balance'],
            'credits_summary' => $summary,
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
