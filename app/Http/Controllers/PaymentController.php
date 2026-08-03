<?php

namespace App\Http\Controllers;

use App\Models\PaymentOrder;
use App\Models\PendingOnboarding;
use App\Services\PaymentCheckoutService;
use App\Services\PaymentCountryResolver;
use App\Services\PaymentFulfillmentService;
use App\Services\OnboardingWorkspaceService;
use App\Services\PayPalService;
use App\Services\SubscriptionManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class PaymentController extends Controller
{
    public function provider(Request $request, PaymentCountryResolver $resolver): JsonResponse
    {
        return response()->json($resolver->payload($request));
    }

    public function checkout(
        Request $request,
        PaymentCheckoutService $checkout,
        PaymentCountryResolver $resolver,
    ): JsonResponse {
        $validated = $request->validate([
            'provider' => ['nullable', Rule::in(['paypal', 'paymongo', 'stripe'])],
            'product_type' => ['required', Rule::in(['credits', 'plan'])],
            'product_key' => ['required', 'string', 'max:40'],
        ]);

        $provider = $resolver->provider($request, $validated['product_type']);

        return response()->json($checkout->create(
            $request->user(),
            $provider,
            $validated['product_type'],
            $validated['product_key'],
        ) + [
            'provider' => $provider,
            'country' => $resolver->country($request),
        ]);
    }


    public function onboardingCheckout(
        Request $request,
        PaymentCheckoutService $checkout,
    ): JsonResponse {
        $user = $request->user();

        $onboarding = PendingOnboarding::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending_payment', 'payment_cancelled'])
            ->firstOrFail();

        $existing = PaymentOrder::query()
            ->where('user_id', $user->id)
            ->where('provider', 'paypal')
            ->where('product_type', 'plan')
            ->where('product_key', $onboarding->selected_plan)
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(30))
            ->latest('id')
            ->first();

        $existingUrl = (string) data_get($existing?->metadata, 'checkout_url', '');

        if ($existing && $existingUrl !== '') {
            $onboarding->update([
                'status' => 'pending_payment',
                'metadata' => array_merge($onboarding->metadata ?? [], [
                    'payment_order_reference' => $existing->reference,
                    'checkout_resumed_at' => now()->toIso8601String(),
                ]),
            ]);

            return response()->json([
                'checkout_url' => $existingUrl,
                'order_reference' => $existing->reference,
                'resumed' => true,
            ]);
        }

        if ($existing) {
            $existing->update([
                'status' => 'expired',
                'metadata' => array_merge($existing->metadata ?? [], [
                    'expired_reason' => 'missing_checkout_url',
                    'expired_at' => now()->toIso8601String(),
                ]),
            ]);
        }

        try {
            $result = $checkout->create($user, 'paypal', 'plan', $onboarding->selected_plan);

            $order = PaymentOrder::query()
                ->where('reference', $result['order_reference'])
                ->firstOrFail();

            $order->update([
                'metadata' => array_merge($order->metadata ?? [], [
                    'onboarding_id' => $onboarding->id,
                    'checkout_source' => 'paid_onboarding',
                ]),
            ]);

            $onboarding->update([
                'status' => 'pending_payment',
                'metadata' => array_merge($onboarding->metadata ?? [], [
                    'payment_order_reference' => $order->reference,
                    'checkout_started_at' => now()->toIso8601String(),
                ]),
            ]);

            return response()->json($result + ['resumed' => false]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage() ?: 'Unable to open PayPal checkout.',
            ], 422);
        }
    }

    public function success(
        Request $request,
        PayPalService $paypal,
        PaymentFulfillmentService $fulfillment,
        SubscriptionManagementService $subscriptions,
        OnboardingWorkspaceService $workspaceSetup,
    ) {
        $provider = (string) $request->query('provider');
        $reference = (string) $request->query('order');

        try {
            if ($provider === 'paypal' && $reference !== '') {
                $paymentOrder = PaymentOrder::query()
                    ->where('reference', $reference)
                    ->where('user_id', $request->user()->id)
                    ->firstOrFail();

                if ($paymentOrder->product_type === 'credits') {
                    $paypalOrderId = (string) (
                        $request->query('token')
                        ?: $paymentOrder->external_checkout_id
                    );

                    $captured = $paypal->captureOrder($paypalOrderId);
                    $paypal->assertOrderMatches($paymentOrder, $captured);

                    $fulfillment->fulfillOrder(
                        $paymentOrder->reference,
                        $paypal->captureId($captured),
                        '',
                        [
                            'paypal_order_id' => $paypalOrderId,
                            'paypal_capture_source' => 'return_url',
                        ],
                    );

                    return redirect()
                        ->route('credits.index')
                        ->with('status', number_format($paymentOrder->credits).' credits were added successfully.');
                }

                if ($paymentOrder->product_type === 'plan') {
                    $subscriptionId = (string) ($request->query('subscription_id') ?: $request->query('ba_token'));

                    if ($subscriptionId !== '') {
                        $paymentOrder->update([
                            'external_subscription_id' => $subscriptionId,
                            'metadata' => array_merge($paymentOrder->metadata ?? [], [
                                'paypal_return_received_at' => now()->toIso8601String(),
                            ]),
                        ]);

                        try {
                            $subscription = $subscriptions->syncOrder($paymentOrder->fresh());
                            $status = strtolower((string) ($subscription['status'] ?? ''));

                            if (in_array($status, ['active', 'approved'], true)) {
                                $onboardingId = (int) data_get($paymentOrder->metadata, 'onboarding_id', 0);

                                if ($onboardingId > 0) {
                                    $onboarding = PendingOnboarding::query()
                                        ->whereKey($onboardingId)
                                        ->where('user_id', $request->user()->id)
                                        ->firstOrFail();

                                    $onboarding->update([
                                        'status' => 'payment_confirmed',
                                        'metadata' => array_merge($onboarding->metadata ?? [], [
                                            'payment_confirmed_at' => now()->toIso8601String(),
                                            'paypal_subscription_id' => $subscriptionId,
                                        ]),
                                    ]);

                                    $workspaceSetup->finalize($onboarding->fresh(), $paymentOrder->fresh());

                                    return redirect()
                                        ->route('onboarding.success')
                                        ->with('status', 'Payment confirmed. Your website workspace is ready.');
                                }
                            }
                        } catch (Throwable $syncException) {
                            report($syncException);
                        }
                    }

                    if ((int) data_get($paymentOrder->metadata, 'onboarding_id', 0) > 0) {
                        return redirect()
                            ->route('onboarding.pending')
                            ->with('status', 'PayPal approved your subscription. We are confirming activation now.');
                    }
                }
            }

            return redirect()
                ->route('credits.index')
                ->with('status', 'Payment received. PayPal is confirming the transaction.');
        } catch (Throwable $exception) {
            report($exception);

            $isOnboarding = false;

            if (isset($paymentOrder) && $paymentOrder instanceof PaymentOrder) {
                $isOnboarding = (int) data_get($paymentOrder->metadata, 'onboarding_id', 0) > 0;
            }

            return redirect()
                ->route($isOnboarding ? 'onboarding.pending' : 'credits.index')
                ->with('payment_error', $exception->getMessage() ?: 'PayPal could not confirm the payment. Please try again.');
        }
    }

    public function cancel(Request $request)
    {
        $reference = (string) $request->query('order');

        $order = null;

        if ($reference !== '') {
            $order = PaymentOrder::query()
                ->where('reference', $reference)
                ->where('user_id', $request->user()->id)
                ->where('status', 'pending')
                ->first();

            if ($order) {
                $order->update([
                    'status' => 'cancelled',
                    'metadata' => array_merge($order->metadata ?? [], [
                        'cancelled_at' => now()->toIso8601String(),
                    ]),
                ]);
            }
        }

        $onboardingId = (int) data_get($order?->metadata, 'onboarding_id', 0);

        if ($onboardingId > 0) {
            $onboarding = PendingOnboarding::query()
                ->whereKey($onboardingId)
                ->where('user_id', $request->user()->id)
                ->first();

            if ($onboarding) {
                $onboarding->update([
                    'status' => 'payment_cancelled',
                    'metadata' => array_merge($onboarding->metadata ?? [], [
                        'payment_cancelled_at' => now()->toIso8601String(),
                    ]),
                ]);
            }

            return redirect()
                ->route('onboarding.pending')
                ->with('payment_error', 'Payment was cancelled. Your account and business details are still saved.');
        }

        return redirect()
            ->route('credits.index')
            ->with('payment_error', 'Payment was cancelled. No credits were added.');
    }


    public function cancelSubscription(
        Request $request,
        SubscriptionManagementService $subscriptions,
    ): JsonResponse {
        try {
            return response()->json($subscriptions->cancelCurrent($request->user()));
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage() ?: 'Unable to cancel the subscription.',
            ], 422);
        }
    }

    public function syncSubscription(
        Request $request,
        SubscriptionManagementService $subscriptions,
    ): JsonResponse {
        try {
            $subscription = $subscriptions->sync($request->user());

            return response()->json([
                'message' => 'Subscription status synced with PayPal.',
                'status' => strtolower((string) ($subscription['status'] ?? '')),
                'next_billing_time' => data_get($subscription, 'billing_info.next_billing_time'),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage() ?: 'Unable to sync the subscription.',
            ], 422);
        }
    }
}
