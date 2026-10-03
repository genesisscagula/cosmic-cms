<?php

namespace App\Http\Controllers;

use App\Models\PaymentOrder;
use App\Models\PendingOnboarding;
use App\Services\MarketplaceCheckoutService;
use App\Services\MarketplaceCreditTopUpService;
use App\Services\PaymentCheckoutService;
use App\Services\PaymentCountryResolver;
use App\Services\PaymentFulfillmentService;
use App\Services\WorkspaceProvisioningService;
use App\Services\PayPalService;
use App\Services\SubscriptionManagementService;
use App\Support\SubscriptionStatus;
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
        MarketplaceCheckoutService $marketplaceCheckouts,
        MarketplaceCreditTopUpService $marketplaceCreditTopUps,
    ): JsonResponse {
        $user = $request->user();

        $onboarding = PendingOnboarding::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending_payment', 'payment_cancelled'])
            ->firstOrFail();

        try {
            $validated = $request->validate([
                'marketplace_credit_option' => ['nullable', 'string', 'max:80'],
            ]);

            $marketplaceCheckout = $marketplaceCheckouts->forOnboarding($onboarding);
            $paymentContext = [
                'onboarding_id' => $onboarding->id,
                'checkout_source' => $marketplaceCheckout ? 'marketplace_agency_onboarding' : 'paid_onboarding',
            ];

            if ($marketplaceCheckout) {
                // Marketplace template installation still spends Cosmic Credits,
                // while the Agency subscription remains recurring. If the plan's
                // one-time included credits do not cover the selected template, the
                // missing credits are sold as a one-time PayPal subscription setup fee.
                $marketplaceCheckouts->assertAgencyPlan((string) $onboarding->selected_plan);
                $marketplaceCheckout->loadMissing('template');

                $topUp = $marketplaceCheckout->template
                    ? $marketplaceCreditTopUps->selected(
                        (string) $onboarding->selected_plan,
                        $marketplaceCheckout->template,
                        $validated['marketplace_credit_option'] ?? null,
                    )
                    : null;

                $paymentContext = array_merge(
                    $paymentContext,
                    $marketplaceCheckouts->paymentMetadata($marketplaceCheckout),
                    [
                        'payment_purpose' => 'agency_subscription_with_marketplace_credits',
                        'marketplace_credit_option_key' => $topUp['key'] ?? null,
                        'marketplace_topup_credits' => (int) ($topUp['credits'] ?? 0),
                        'marketplace_topup_amount_minor' => (int) ($topUp['price_minor'] ?? 0),
                        'marketplace_topup_price_usd' => (float) ($topUp['price_usd'] ?? 0),
                        'marketplace_topup_bundle' => $topUp['bundle'] ?? null,
                    ],
                );
            }

            $result = $checkout->create($user, 'paypal', 'plan', $onboarding->selected_plan, $paymentContext);

            $order = PaymentOrder::query()
                ->where('reference', $result['order_reference'])
                ->firstOrFail();

            if ($marketplaceCheckout) {
                $marketplaceCheckouts->linkPaymentOrder($marketplaceCheckout, $order);
            }

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
        WorkspaceProvisioningService $provisioning,
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
                            $status = SubscriptionStatus::normalize((string) ($subscription['status'] ?? ''));

                            if ($status === SubscriptionStatus::ACTIVE) {
                                // The return callback and webhook intentionally share the
                                // same idempotent fulfillment path. Whichever arrives first
                                // completes the order; the other safely becomes a no-op.
                                $fulfillment->fulfillOrder(
                                    $paymentOrder->reference,
                                    '',
                                    $subscriptionId,
                                    [
                                        'paypal_subscription_status' => (string) ($subscription['status'] ?? 'ACTIVE'),
                                        'paypal_next_billing_time' => data_get($subscription, 'billing_info.next_billing_time'),
                                        'confirmation_source' => 'return_url_sync',
                                    ],
                                );

                                $subscriptions->finalizeSwitch($paymentOrder->fresh());
                                $paymentOrder->refresh();

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

                                    $provisioningResult = $provisioning->start($onboarding->fresh(), $paymentOrder->fresh(), 'return_url');

                                    $marketplaceCheckoutId = (int) data_get($paymentOrder->metadata, 'marketplace_checkout_id', 0);
                                    if ($marketplaceCheckoutId > 0) {
                                        $marketplaceCheckout = \App\Models\MarketplaceCheckout::query()
                                            ->with('template')
                                            ->whereKey($marketplaceCheckoutId)
                                            ->where('user_id', $request->user()->id)
                                            ->first();

                                        if ($marketplaceCheckout?->template?->slug) {
                                            return redirect()
                                                ->to($this->marketplaceCheckoutUrl(
                                                    $marketplaceCheckout->template->slug,
                                                    ['agency_subscription' => 'active'],
                                                ))
                                                ->with('status', 'Agency subscription active and your selected credit top-up is applied. Confirm the Marketplace template Cosmic Credit spend to finish installation.');
                                        }
                                    }

                                    return redirect()
                                        ->route('dashboard')
                                        ->with('status', 'Payment confirmed. Your Cosmic CMS workspace is ready.');
                                }

                                // Legacy recovery only: Batch 2 no longer creates direct Marketplace
                                // template PaymentOrders. Any payment explicitly marked as an Agency
                                // subscription must return to Cosmic Credit confirmation instead.
                                $legacyDirectMarketplacePayment = (int) data_get($paymentOrder->metadata, 'marketplace_checkout_id', 0) > 0
                                    && ! in_array((string) data_get($paymentOrder->metadata, 'payment_purpose', ''), ['agency_subscription', 'agency_subscription_with_marketplace_credits'], true)
                                    // New Marketplace acquisitions always declare the Cosmic Credit mode.
                                    // Only historical Marketplace payment orders that predate that marker may
                                    // use the direct subscription recovery path below.
                                    && (string) data_get($paymentOrder->metadata, 'marketplace_template_payment_mode', '') !== 'cosmic_credits';
                                if ($legacyDirectMarketplacePayment) {
                                    $checkout = \App\Models\MarketplaceCheckout::query()
                                        ->find((int) data_get($paymentOrder->metadata, 'marketplace_checkout_id'));
                                    if ($checkout) {
                                        $website = app(\App\Services\MarketplaceWebsiteProvisioningService::class)->provision($checkout);
                                        return redirect()
                                            ->to(app(\App\Services\MarketplaceWebsiteProvisioningService::class)->builderUrl($website))
                                            ->with('status', 'Subscription confirmed. Your Marketplace website is ready — Luna can personalize it now.');
                                    }
                                }

                                $agencyMarketplacePayment = (int) data_get($paymentOrder->metadata, 'marketplace_checkout_id', 0) > 0
                                    && in_array((string) data_get($paymentOrder->metadata, 'payment_purpose', ''), ['agency_subscription', 'agency_subscription_with_marketplace_credits'], true);
                                if ($agencyMarketplacePayment) {
                                    $checkout = \App\Models\MarketplaceCheckout::query()
                                        ->with('template')
                                        ->whereKey((int) data_get($paymentOrder->metadata, 'marketplace_checkout_id'))
                                        ->where('user_id', $request->user()->id)
                                        ->first();

                                    if ($checkout?->template?->slug) {
                                        return redirect()
                                            ->to($this->marketplaceCheckoutUrl($checkout->template->slug, ['agency_subscription' => 'active']))
                                            ->with('status', 'Agency subscription active and your selected credit top-up is applied. Confirm the Marketplace template Cosmic Credit spend to continue.');
                                    }
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

                    if ((int) data_get($paymentOrder->metadata, 'marketplace_checkout_id', 0) > 0) {
                        return redirect()
                            ->route('dashboard')
                            ->with('status', 'PayPal approved your Agency subscription. We are confirming activation before returning you to Marketplace.');
                    }
                }
            }

            return redirect()
                ->route('credits.index')
                ->with('status', 'Payment received. PayPal is confirming the transaction.');
        } catch (Throwable $exception) {
            report($exception);

            $isOnboarding = false;
            $isMarketplace = false;

            if (isset($paymentOrder) && $paymentOrder instanceof PaymentOrder) {
                $isOnboarding = (int) data_get($paymentOrder->metadata, 'onboarding_id', 0) > 0;
                $isMarketplace = (int) data_get($paymentOrder->metadata, 'marketplace_checkout_id', 0) > 0;
            }

            return redirect()
                ->route($isOnboarding ? 'onboarding.pending' : ($isMarketplace ? 'dashboard' : 'credits.index'))
                ->with('payment_error', $exception->getMessage() ?: 'PayPal could not confirm the payment. Please try again.');
        }
    }

    public function cancel(Request $request, MarketplaceCheckoutService $marketplaceCheckouts)
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
                    'status' => 'pending',
                    'metadata' => array_merge($order->metadata ?? [], [
                        'checkout_cancelled_at' => now()->toIso8601String(),
                        'checkout_resume_available' => true,
                    ]),
                ]);
            }
        }

        $marketplaceCheckouts->markCancelled($order);
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
                ->with('payment_error', 'Payment was cancelled. Your saved PayPal checkout is ready to resume.');
        }

        $marketplaceSlug = (string) data_get($order?->metadata, 'marketplace_template_slug', '');
        if ($marketplaceSlug !== '') {
            return redirect()->away($this->marketplaceCheckoutUrl($marketplaceSlug, ['payment' => 'cancelled']));
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

    public function recoverSubscription(
        Request $request,
        SubscriptionManagementService $subscriptions,
    ): JsonResponse {
        try {
            $result = $subscriptions->recover($request->user());

            return response()->json([
                'message' => $result['recovered']
                    ? 'Billing recovered. Your subscription is active again.'
                    : 'Recovery request completed. PayPal still reports '.str_replace('_', ' ', $result['after']).'.',
                'status' => $result['after'],
                'recovered' => $result['recovered'],
                'next_billing_time' => data_get($result, 'subscription.billing_info.next_billing_time'),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage() ?: 'Unable to recover the subscription.',
            ], 422);
        }
    }


    private function marketplaceCheckoutUrl(string $templateSlug, array $query = []): string
    {
        if (app()->environment('production')) {
            $base = rtrim((string) config('cosmic_marketplace.scheme', 'https').'://'.config('cosmic_marketplace.domain', 'marketplace.cosmiccms.com'), '/');
        } else {
            $base = rtrim((string) config('cosmic_marketplace.core_url', config('app.url')), '/')
                .'/'.trim((string) config('cosmic_marketplace.local_prefix', 'marketplace'), '/');
        }

        $url = $base.'/checkout/'.rawurlencode($templateSlug);

        return $query ? $url.'?'.http_build_query($query) : $url;
    }

}
