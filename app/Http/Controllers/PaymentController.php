<?php

namespace App\Http\Controllers;

use App\Models\PaymentOrder;
use App\Services\PaymentCheckoutService;
use App\Services\PaymentCountryResolver;
use App\Services\PaymentFulfillmentService;
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

    public function success(
        Request $request,
        PayPalService $paypal,
        PaymentFulfillmentService $fulfillment,
        SubscriptionManagementService $subscriptions,
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
                            $subscriptions->syncOrder($paymentOrder->fresh());
                        } catch (Throwable $syncException) {
                            report($syncException);
                        }
                    }
                }
            }

            return redirect()
                ->route('credits.index')
                ->with('status', 'Payment received. PayPal is confirming the transaction.');
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('credits.index')
                ->with('payment_error', $exception->getMessage() ?: 'PayPal could not confirm the payment.');
        }
    }

    public function cancel(Request $request)
    {
        $reference = (string) $request->query('order');

        if ($reference !== '') {
            PaymentOrder::query()
                ->where('reference', $reference)
                ->where('user_id', $request->user()->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'cancelled',
                    'metadata' => ['cancelled_at' => now()->toIso8601String()],
                ]);
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
