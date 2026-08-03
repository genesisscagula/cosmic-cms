<?php

namespace App\Services;

use App\Models\CreditTransaction;
use App\Models\PaymentOrder;
use Illuminate\Support\Facades\DB;
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
                app(CreditService::class)->grant(
                    $order->user,
                    (int) $order->credits,
                    $order->product_type === 'plan'
                        ? ucfirst($order->product_key).' plan credits'
                        : 'Credit purchase: '.$order->product_key,
                    $creditReference,
                    [
                        'payment_order_id' => $order->id,
                        'provider' => $order->provider,
                        'product_type' => $order->product_type,
                    ],
                );
            }

            if ($order->product_type === 'plan') {
                $nextBilling = data_get($metadata, 'paypal_next_billing_time');

                $order->user->update([
                    'plan_key' => $order->product_key,
                    'plan_status' => 'active',
                    'plan_provider' => $order->provider,
                    'plan_renews_at' => $nextBilling ?: now()->addMonth(),
                    'plan_cancel_at_period_end' => false,
                    'plan_cancelled_at' => null,
                ]);
            }

            $order->update(['fulfilled_at' => now()]);

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

        $this->assertRenewalMatches($order, $metadata);

        // If the payment webhook arrives before activation, fulfill the initial
        // plan once here. The first sale then becomes the recorded initial charge,
        // not an extra monthly credit grant.
        if (! $order->fulfilled_at) {
            $this->fulfillOrder(
                $order->reference,
                $paymentId,
                $subscriptionId,
                array_merge($metadata, ['initial_subscription_payment_id' => $paymentId]),
            );
        }

        return DB::transaction(function () use ($order, $subscriptionId, $paymentId, $metadata) {
            $lockedOrder = PaymentOrder::query()->lockForUpdate()->findOrFail($order->id);
            $orderMetadata = $lockedOrder->metadata ?? [];

            if ((string) ($orderMetadata['initial_subscription_payment_id'] ?? '') === '') {
                $lockedOrder->update([
                    'external_payment_id' => $paymentId,
                    'paid_at' => $lockedOrder->paid_at ?? now(),
                    'metadata' => array_merge($orderMetadata, $metadata, [
                        'initial_subscription_payment_id' => $paymentId,
                    ]),
                ]);

                return true;
            }

            $renewalReference = 'payment:paypal:renewal:'.$paymentId;

            if (CreditTransaction::query()->where('reference', $renewalReference)->exists()) {
                return true;
            }

            app(CreditService::class)->grant(
                $lockedOrder->user,
                (int) $lockedOrder->credits,
                ucfirst($lockedOrder->product_key).' monthly plan renewal credits',
                $renewalReference,
                array_merge([
                    'payment_order_id' => $lockedOrder->id,
                    'provider' => 'paypal',
                    'product_type' => 'plan_renewal',
                    'subscription_id' => $subscriptionId,
                    'payment_id' => $paymentId,
                ], $metadata),
            );

            $nextBilling = data_get($metadata, 'next_billing_time');

            $lockedOrder->user->update([
                'plan_key' => $lockedOrder->product_key,
                'plan_status' => 'active',
                'plan_provider' => 'paypal',
                'plan_renews_at' => $nextBilling ?: now()->addMonth(),
                'plan_cancel_at_period_end' => false,
                'plan_cancelled_at' => null,
            ]);

            $lockedOrder->update([
                'status' => 'paid',
                'external_payment_id' => $paymentId,
                'paid_at' => now(),
                'metadata' => array_merge($lockedOrder->metadata ?? [], [
                    'last_renewal_payment_id' => $paymentId,
                    'last_renewal_at' => now()->toIso8601String(),
                ], $metadata),
            ]);

            return true;
        });
    }

    public function updateSubscriptionStatus(
        string $subscriptionId,
        string $status,
        array $metadata = [],
    ): void {
        if ($subscriptionId === '') {
            return;
        }

        $order = PaymentOrder::query()
            ->where('external_subscription_id', $subscriptionId)
            ->latest('id')
            ->first();

        if (! $order?->user) {
            return;
        }

        $cancelled = in_array($status, ['cancelled', 'expired'], true);
        $currentOrder = $order->user->paymentOrders()
            ->where('provider', 'paypal')
            ->where('product_type', 'plan')
            ->whereNotNull('external_subscription_id')
            ->whereNotIn('status', ['failed', 'cancelled', 'expired', 'replaced'])
            ->latest('id')
            ->first();

        // Do not let a late webhook from an old/replaced subscription overwrite
        // the user's newer active plan.
        if (! $currentOrder || $currentOrder->id === $order->id) {
            $order->user->update([
                'plan_status' => $status,
                'plan_cancel_at_period_end' => $cancelled,
                'plan_cancelled_at' => $cancelled ? ($order->user->plan_cancelled_at ?? now()) : null,
                'plan_renews_at' => $status === 'expired' ? null : $order->user->plan_renews_at,
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
