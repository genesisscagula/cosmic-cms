<?php

namespace App\Services;

use App\Jobs\SendBillingReceiptJob;
use App\Models\BillingTransaction;
use App\Models\CreditTransaction;
use App\Models\MarketplaceCheckout;
use App\Models\PaymentOrder;
use App\Support\SubscriptionStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentFulfillmentService
{
    public function fulfillOrder(
        ?string $reference,
        string $paymentId = '',
        string $subscriptionId = '',
        array $metadata = [],
    ): bool {
        if (! $reference) {
            return false;
        }

        return DB::transaction(function () use ($reference, $paymentId, $subscriptionId, $metadata) {
            $order = PaymentOrder::query()
                ->where('reference', $reference)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                return false;
            }

            if ($order->fulfilled_at) {
                return true;
            }

            $order->update([
                'status' => 'paid',
                'external_payment_id' => $paymentId !== '' ? $paymentId : $order->external_payment_id,
                'external_subscription_id' => $subscriptionId !== ''
                    ? $subscriptionId
                    : $order->external_subscription_id,
                'paid_at' => $order->paid_at ?? now(),
                'metadata' => array_merge($order->metadata ?? [], $metadata),
            ]);

            $creditReference = 'payment:'.$order->provider.':'.$order->reference;

            if (! CreditTransaction::query()->where('reference', $creditReference)->exists()) {
                $creditMetadata = [
                    'payment_order_id' => $order->id,
                    'provider' => $order->provider,
                    'product_type' => $order->product_type,
                ];

                if ($order->product_type === 'plan') {
                    // Included plan credits are a one-time signup benefit, not a
                    // recurring monthly allowance. A later upgrade/downgrade or
                    // re-subscription must never grant the signup allocation again.
                    $alreadyReceivedSignupCredits = CreditTransaction::query()
                        ->where('user_id', $order->user_id)
                        ->where('type', 'credit')
                        ->where('category', 'subscription')
                        ->exists();

                    if (! $alreadyReceivedSignupCredits) {
                        // The first paid allocation replaces guest/trial credits so
                        // the purchased plan starts with the advertised exact balance.
                        app(CreditWalletService::class)->setBalance(
                            $order->user,
                            (int) $order->credits,
                            ucfirst(str_replace('_', ' ', $order->product_key)).' one-time signup credit allocation',
                            'subscription',
                            $creditReference,
                            array_merge($creditMetadata, ['allocation' => 'one_time_signup']),
                        );
                    }

                    $marketplaceTopUpCredits = max(0, (int) data_get($order->metadata, 'marketplace_topup_credits', 0));
                    if ($marketplaceTopUpCredits > 0) {
                        $topUpReference = $creditReference.':marketplace_topup';
                        if (! CreditTransaction::query()->where('reference', $topUpReference)->exists()) {
                            app(CreditService::class)->grant(
                                $order->user,
                                $marketplaceTopUpCredits,
                                'Marketplace checkout Cosmic Credit top-up',
                                $topUpReference,
                                array_merge($creditMetadata, [
                                    'category' => 'purchase',
                                    'allocation' => 'marketplace_checkout_topup',
                                    'price_minor' => max(0, (int) data_get($order->metadata, 'marketplace_topup_amount_minor', 0)),
                                    'credit_option_key' => data_get($order->metadata, 'marketplace_credit_option_key'),
                                ]),
                            );
                        }
                    }
                } else {
                    app(CreditService::class)->grant(
                        $order->user,
                        (int) $order->credits,
                        'Credit purchase: '.$order->product_key,
                        $creditReference,
                        $creditMetadata,
                    );
                }
            }

            if ($order->product_type === 'plan' && ! $order->user->isPlatformOwner()) {
                $nextBilling = data_get($metadata, 'paypal_next_billing_time');

                $order->user->update([
                    'plan_key' => $order->product_key,
                    'plan_status' => SubscriptionStatus::ACTIVE,
                    'plan_provider' => $order->provider,
                    'plan_renews_at' => $nextBilling ?: now()->addMonth(),
                    'plan_cancel_at_period_end' => false,
                    'plan_cancelled_at' => null,
                    'plan_status_changed_at' => now(),
                    'plan_past_due_at' => null,
                    'plan_suspended_at' => null,
                    'plan_expired_at' => null,
                ]);
            }

            $order->update(['fulfilled_at' => now()]);

            $marketplaceCheckoutId = (int) data_get($order->metadata, 'marketplace_checkout_id', 0);
            if ($marketplaceCheckoutId > 0) {
                $marketplaceCheckout = MarketplaceCheckout::query()->lockForUpdate()->find($marketplaceCheckoutId);
                if ($marketplaceCheckout) {
                    $marketplaceCheckout->forceFill([
                        'payment_order_id' => $order->id,
                        'status' => MarketplaceCheckout::STATUS_PAID,
                        'paid_at' => $marketplaceCheckout->paid_at ?? now(),
                        'metadata' => array_merge($marketplaceCheckout->metadata ?? [], [
                            'payment_fulfilled_at' => now()->toIso8601String(),
                            'payment_order_reference' => $order->reference,
                            'external_subscription_id' => $order->external_subscription_id,
                        ]),
                    ])->save();

                    // Marketplace purchases without PendingOnboarding (existing account /
                    // direct upgrade) still need a real website. New-account purchases are
                    // provisioned by WorkspaceProvisioningService so the same paid website
                    // is reused instead of creating a duplicate.
                    $legacyDirectMarketplaceSubscription = (int) data_get($order->metadata, 'onboarding_id', 0) <= 0
                        && ! in_array((string) data_get($order->metadata, 'payment_purpose', ''), ['agency_subscription', 'agency_subscription_with_marketplace_credits'], true)
                        // Batch 1+ Marketplace orders explicitly carry cosmic_credits. Do not let a
                        // payment/webhook retry on a modern checkout accidentally invoke the retired
                        // direct-subscription template provisioning path. Historical orders without
                        // that marker remain recoverable for backward compatibility.
                        && (string) data_get($order->metadata, 'marketplace_template_payment_mode', '') !== 'cosmic_credits';

                    if ($legacyDirectMarketplaceSubscription) {
                        try {
                            app(MarketplaceWebsiteProvisioningService::class)->provision($marketplaceCheckout->fresh());
                        } catch (\Throwable $provisioningException) {
                            // Billing has already succeeded. Do not roll back or replay a
                            // customer's subscription because website setup needs recovery.
                            // Persist the error so support/recovery can safely retry Batch 6.
                            report($provisioningException);
                            $marketplaceCheckout->forceFill([
                                'metadata' => array_merge($marketplaceCheckout->metadata ?? [], [
                                    'website_provisioning_error' => Str::limit($provisioningException->getMessage(), 1000),
                                    'website_provisioning_failed_at' => now()->toIso8601String(),
                                ]),
                            ])->save();
                        }
                    }
                }
            }

            return true;
        });
    }

    public function fulfillSubscriptionRenewal(
        string $subscriptionId,
        string $paymentId,
        array $metadata = [],
    ): bool {
        if ($subscriptionId === '' || $paymentId === '') {
            return false;
        }

        $order = PaymentOrder::query()
            ->where('provider', 'paypal')
            ->where('product_type', 'plan')
            ->where('external_subscription_id', $subscriptionId)
            ->latest('id')
            ->first();

        if (! $order) {
            return false;
        }

        $setupFeeMinor = max(0, (int) data_get($order->metadata, 'paypal_setup_fee_minor', data_get($order->metadata, 'marketplace_topup_amount_minor', 0)));
        $saleAmountMinor = max(0, (int) ($metadata['amount_minor'] ?? 0));
        $setupFeePaymentId = (string) data_get($order->metadata, 'paypal_setup_fee_payment_id', '');

        // PayPal can emit the subscription setup-fee sale separately from the
        // recurring monthly sale. Consume that webhook once without treating the
        // one-time Marketplace credit charge as a monthly renewal.
        if ($setupFeeMinor > 0 && $setupFeePaymentId === '' && $saleAmountMinor === $setupFeeMinor) {
            $order->update([
                'metadata' => array_merge($order->metadata ?? [], $metadata, [
                    'paypal_setup_fee_payment_id' => $paymentId,
                    'paypal_setup_fee_paid_at' => now()->toIso8601String(),
                ]),
            ]);

            $this->recordBillingTransaction(
                $order->fresh(),
                'credit_topup',
                'completed',
                $paymentId,
                $subscriptionId,
                max(0, (int) data_get($order->metadata, 'marketplace_topup_credits', 0)),
                $metadata,
            );

            return true;
        }

        $this->assertRenewalMatches($order, $metadata);

        // PayPal may deliver the first recurring SALE before ACTIVATED. Fulfill the initial
        // purchase once, record it as the initial charge, and stop here so the
        // the same sale cannot be mistaken for a renewal. Included credits are signup-only.
        if (! $order->fulfilled_at) {
            $fulfilled = $this->fulfillOrder(
                $order->reference,
                $paymentId,
                $subscriptionId,
                array_merge($metadata, ['initial_subscription_payment_id' => $paymentId]),
            );

            if ($fulfilled) {
                $freshOrder = $order->fresh();
                $this->recordBillingTransaction($freshOrder, 'initial', 'completed', $paymentId, $subscriptionId, $this->creditsGrantedForOrder($freshOrder), $metadata);
            }

            return $fulfilled;
        }

        $result = DB::transaction(function () use ($order, $subscriptionId, $paymentId, $metadata) {
            $lockedOrder = PaymentOrder::query()->lockForUpdate()->findOrFail($order->id);
            $orderMetadata = $lockedOrder->metadata ?? [];

            // Activation was processed first. The first SALE identifies the
            // initial charge but must not grant another monthly allocation.
            if ((string) ($orderMetadata['initial_subscription_payment_id'] ?? '') === '') {
                $lockedOrder->update([
                    'external_payment_id' => $paymentId,
                    'paid_at' => $lockedOrder->paid_at ?? now(),
                    'metadata' => array_merge($orderMetadata, $metadata, [
                        'initial_subscription_payment_id' => $paymentId,
                    ]),
                ]);

                $freshOrder = $lockedOrder->fresh();
                $this->recordBillingTransaction($freshOrder, 'initial', 'completed', $paymentId, $subscriptionId, $this->creditsGrantedForOrder($freshOrder), $metadata);

                return true;
            }

            // Renewals extend subscription access only. Cosmic plan credits are
            // granted once on the customer's first successful plan purchase.

            $nextBilling = data_get($metadata, 'next_billing_time');
            $fallbackNextBilling = $lockedOrder->user->plan_renews_at?->isFuture()
                ? $lockedOrder->user->plan_renews_at->copy()->addMonth()
                : now()->addMonth();
            $resolvedNextBilling = $nextBilling
                ? \Illuminate\Support\Carbon::parse($nextBilling)
                : $fallbackNextBilling;

            if (! $lockedOrder->user->isPlatformOwner()) {
                $lockedOrder->user->update([
                    'plan_key' => $lockedOrder->product_key,
                    'plan_status' => SubscriptionStatus::ACTIVE,
                    'plan_provider' => 'paypal',
                    'plan_renews_at' => $resolvedNextBilling,
                    'plan_cancel_at_period_end' => false,
                    'plan_cancelled_at' => null,
                    'plan_status_changed_at' => now(),
                    'plan_past_due_at' => null,
                    'plan_suspended_at' => null,
                    'plan_expired_at' => null,
                ]);
            }

            $lockedOrder->update([
                'status' => 'paid',
                'external_payment_id' => $paymentId,
                'paid_at' => now(),
                'metadata' => array_merge($lockedOrder->metadata ?? [], [
                    'last_renewal_payment_id' => $paymentId,
                    'last_renewal_at' => now()->toIso8601String(),
                    'next_billing_time' => $resolvedNextBilling->toIso8601String(),
                ], $metadata),
            ]);

            $this->recordBillingTransaction($lockedOrder->fresh(), 'renewal', 'completed', $paymentId, $subscriptionId, 0, $metadata);

            return true;
        });

        return $result;
    }

    public function updateSubscriptionStatus(
        string $subscriptionId,
        string $status,
        array $metadata = [],
    ): void {
        if ($subscriptionId === '') {
            return;
        }

        $status = SubscriptionStatus::normalize($status);

        $order = PaymentOrder::query()
            ->where('external_subscription_id', $subscriptionId)
            ->latest('id')
            ->first();

        if (! $order?->user) {
            return;
        }

        if ($status === SubscriptionStatus::PAST_DUE) {
            $failureId = (string) ($metadata['payment_failure_id'] ?? 'failure:'.$subscriptionId.':'.now()->timestamp);
            $this->recordBillingTransaction($order, 'renewal', 'failed', $failureId, $subscriptionId, 0, $metadata);
        }

        $cancelled = $status === SubscriptionStatus::CANCELLED;
        $currentOrder = $order->user->paymentOrders()
            ->where('provider', 'paypal')
            ->where('product_type', 'plan')
            ->whereNotNull('external_subscription_id')
            ->whereNotIn('status', ['failed', SubscriptionStatus::CANCELLED, SubscriptionStatus::EXPIRED, 'replaced'])
            ->latest('id')
            ->first();

        // Do not let a late webhook from an old/replaced subscription overwrite
        // the user's newer active plan.
        if ((! $currentOrder || $currentOrder->id === $order->id) && ! $order->user->isPlatformOwner()) {
            $order->user->update([
                'plan_status' => $status,
                'plan_cancel_at_period_end' => $cancelled,
                'plan_cancelled_at' => $cancelled ? ($order->user->plan_cancelled_at ?? now()) : null,
                'plan_renews_at' => $status === SubscriptionStatus::EXPIRED ? null : $order->user->plan_renews_at,
                'plan_status_changed_at' => $order->user->plan_status !== $status ? now() : $order->user->plan_status_changed_at,
                'plan_past_due_at' => $status === SubscriptionStatus::PAST_DUE ? ($order->user->plan_past_due_at ?? now()) : null,
                'plan_suspended_at' => $status === SubscriptionStatus::SUSPENDED ? ($order->user->plan_suspended_at ?? now()) : null,
                'plan_expired_at' => $status === SubscriptionStatus::EXPIRED ? ($order->user->plan_expired_at ?? now()) : null,
            ]);
        }

        $order->update([
            'status' => $status,
            'metadata' => array_merge($order->metadata ?? [], $metadata, [
                'subscription_status' => $status,
                'subscription_status_updated_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    private function recordBillingTransaction(
        PaymentOrder $order,
        string $type,
        string $status,
        string $externalId,
        string $subscriptionId,
        int $creditsGranted,
        array $metadata = [],
    ): BillingTransaction {
        $transaction = BillingTransaction::query()->firstOrCreate(
            [
                'provider' => 'paypal',
                'external_id' => $externalId,
            ],
            [
                'user_id' => $order->user_id,
                'payment_order_id' => $order->id,
                'type' => $type,
                'status' => $status,
                'subscription_id' => $subscriptionId,
                'amount_minor' => (int) ($metadata['amount_minor'] ?? $order->amount_minor),
                'currency' => strtoupper((string) ($metadata['currency'] ?? $order->currency)),
                'credits_granted' => $creditsGranted,
                'occurred_at' => now(),
                'metadata' => $metadata ?: null,
            ],
        );

        if ($transaction->wasRecentlyCreated && $status === 'completed') {
            SendBillingReceiptJob::dispatch($transaction->id);
        }

        return $transaction;
    }

    private function creditsGrantedForOrder(PaymentOrder $order): int
    {
        $reference = 'payment:'.$order->provider.':'.$order->reference;

        return max(0, (int) CreditTransaction::query()
            ->where('user_id', $order->user_id)
            ->whereIn('reference', [$reference, $reference.':marketplace_topup'])
            ->sum('amount'));
    }

    private function assertRenewalMatches(PaymentOrder $order, array $metadata): void
    {
        $state = strtoupper((string) ($metadata['paypal_sale_state'] ?? 'COMPLETED'));
        $currency = strtoupper((string) ($metadata['currency'] ?? $order->currency));
        $amountMinor = (int) ($metadata['amount_minor'] ?? $order->amount_minor);

        if ($state !== '' && ! in_array($state, ['COMPLETED', 'COMPLETED_WITH_WARNING'], true)) {
            throw new RuntimeException('PayPal renewal payment is not completed.');
        }

        if ($currency !== strtoupper((string) $order->currency)) {
            throw new RuntimeException('PayPal renewal currency does not match the subscription.');
        }

        if ($amountMinor > 0 && $amountMinor !== (int) $order->amount_minor) {
            throw new RuntimeException('PayPal renewal amount does not match the subscription.');
        }
    }
}
