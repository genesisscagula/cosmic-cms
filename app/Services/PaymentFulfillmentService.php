<?php

namespace App\Services;

use App\Models\CreditTransaction;
use App\Models\PaymentOrder;
use Illuminate\Support\Facades\DB;

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
                $order->user->update([
                    'plan_key' => $order->product_key,
                    'plan_status' => 'active',
                    'plan_provider' => $order->provider,
                    'plan_renews_at' => now()->addMonth(),
                'plan_cancel_at_period_end' => false,
                'plan_cancelled_at' => null,
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

        return DB::transaction(function () use ($subscriptionId, $paymentId, $metadata) {
            $order = PaymentOrder::query()
                ->where('provider', 'paypal')
                ->where('product_type', 'plan')
                ->where('external_subscription_id', $subscriptionId)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (! $order) {
                return false;
            }

            $renewalReference = 'payment:paypal:renewal:'.$paymentId;

            if (CreditTransaction::query()->where('reference', $renewalReference)->exists()) {
                return true;
            }

            app(CreditService::class)->grant(
                $order->user,
                (int) $order->credits,
                ucfirst($order->product_key).' monthly plan renewal credits',
                $renewalReference,
                array_merge([
                    'payment_order_id' => $order->id,
                    'provider' => 'paypal',
                    'product_type' => 'plan_renewal',
                    'subscription_id' => $subscriptionId,
                    'payment_id' => $paymentId,
                ], $metadata),
            );

            $order->user->update([
                'plan_key' => $order->product_key,
                'plan_status' => 'active',
                'plan_provider' => 'paypal',
                'plan_renews_at' => now()->addMonth(),
            ]);

            $order->update([
                'status' => 'paid',
                'external_payment_id' => $paymentId,
                'paid_at' => now(),
                'metadata' => array_merge($order->metadata ?? [], [
                    'last_renewal_payment_id' => $paymentId,
                ], $metadata),
            ]);

            return true;
        });
    }

    public function updateSubscriptionStatus(string $subscriptionId, string $status): void
    {
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

        $order->user->update([
            'plan_status' => $status,
            'plan_cancel_at_period_end' => $cancelled,
            'plan_cancelled_at' => $cancelled ? ($order->user->plan_cancelled_at ?? now()) : null,
            'plan_renews_at' => $status === 'expired' ? null : $order->user->plan_renews_at,
        ]);

        $order->update([
            'status' => $status,
            'metadata' => array_merge($order->metadata ?? [], [
                'subscription_status' => $status,
                'subscription_status_updated_at' => now()->toIso8601String(),
            ]),
        ]);
    }
}
