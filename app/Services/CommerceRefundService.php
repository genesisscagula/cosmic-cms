<?php

namespace App\Services;

use App\Models\CommerceOrder;
use App\Models\CommerceOrderRefund;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CommerceRefundService
{
    public function record(
        CommerceOrder $order,
        string $externalRefundId,
        int $amountMinor,
        string $currency,
        string $status,
        array $payload,
        ?string $reason = null,
        ?string $requestKey = null,
    ): CommerceOrder {
        $externalRefundId = trim($externalRefundId);
        $currency = strtoupper(trim($currency));
        $status = strtolower(trim($status ?: 'completed'));

        if ($externalRefundId === '') throw new RuntimeException('PayPal did not return a refund ID.');
        if ($amountMinor < 1) throw new RuntimeException('PayPal returned an invalid refund amount.');
        if ($currency !== strtoupper((string) $order->currency)) throw new RuntimeException('PayPal refund currency does not match this order.');
        if (! in_array($status, ['completed', 'pending'], true)) throw new RuntimeException('PayPal returned an unsupported refund status.');

        return DB::transaction(function () use ($order, $externalRefundId, $amountMinor, $currency, $status, $payload, $reason, $requestKey) {
            $locked = CommerceOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $existing = CommerceOrderRefund::query()->where('external_refund_id', $externalRefundId)->lockForUpdate()->first();

            if ($existing) {
                if ((int) $existing->commerce_order_id !== (int) $locked->id || (int) $existing->amount_minor !== $amountMinor || strtoupper((string) $existing->currency) !== $currency) {
                    throw new RuntimeException('PayPal refund ID conflicts with an existing refund record.');
                }
                $existing->forceFill([
                    'status' => $status,
                    'provider_payload' => $payload,
                    'provider_synced_at' => now(),
                    'refunded_at' => $status === 'completed' ? ($existing->refunded_at ?: now()) : $existing->refunded_at,
                ])->save();
            } else {
                if (filled($requestKey)) {
                    $requestExisting = CommerceOrderRefund::query()
                        ->where('commerce_order_id', $locked->id)
                        ->where('request_key', $requestKey)
                        ->lockForUpdate()
                        ->first();
                    if ($requestExisting) {
                        if ($requestExisting->external_refund_id && ! hash_equals((string) $requestExisting->external_refund_id, $externalRefundId)) {
                            throw new RuntimeException('Refund request was already linked to another PayPal refund.');
                        }
                        $requestExisting->forceFill([
                            'external_refund_id' => $externalRefundId,
                            'status' => $status,
                            'provider_payload' => $payload,
                            'provider_synced_at' => now(),
                            'refunded_at' => $status === 'completed' ? ($requestExisting->refunded_at ?: now()) : $requestExisting->refunded_at,
                        ])->save();
                    } else {
                        $this->createRefund($locked, $externalRefundId, $amountMinor, $currency, $status, $payload, $reason, $requestKey);
                    }
                } else {
                    $this->createRefund($locked, $externalRefundId, $amountMinor, $currency, $status, $payload, $reason, null);
                }
            }

            $completedTotal = (int) CommerceOrderRefund::query()
                ->where('commerce_order_id', $locked->id)
                ->where('status', 'completed')
                ->sum('amount_minor');
            if ($completedTotal > (int) $locked->total_minor) {
                throw new RuntimeException('Recorded refunds exceed the captured order total.');
            }

            $newPaymentStatus = $completedTotal >= (int) $locked->total_minor
                ? 'refunded'
                : ($completedTotal > 0 ? 'partially_refunded' : 'paid');
            $metadata = is_array($locked->metadata) ? $locked->metadata : [];
            $metadata['refund_last_synced_at'] = now()->toIso8601String();

            $locked->forceFill([
                'refunded_minor' => $completedTotal,
                'refund_status' => $newPaymentStatus === 'paid' ? 'none' : $newPaymentStatus,
                'payment_status' => $newPaymentStatus,
                'status' => $newPaymentStatus === 'refunded' ? 'cancelled' : $locked->status,
                'cancelled_at' => $newPaymentStatus === 'refunded' ? ($locked->cancelled_at ?: now()) : $locked->cancelled_at,
                'metadata' => $metadata,
            ])->save();

            return $locked->fresh(['items', 'website.user', 'refunds']);
        }, 3);
    }

    public function syncWebhook(array $resource): ?CommerceOrder
    {
        $refundId = trim((string) ($resource['id'] ?? ''));
        $captureId = trim((string) data_get($resource, 'supplementary_data.related_ids.capture_id'));
        if ($refundId === '' || $captureId === '') return null;

        $order = CommerceOrder::query()->where('external_payment_id', $captureId)->first();
        if (! $order) return null;

        $value = (string) data_get($resource, 'amount.value', '0');
        $currency = (string) data_get($resource, 'amount.currency_code', $order->currency);
        $decimals = (int) data_get(config('cosmic-commerce.currencies.'.strtoupper($currency)), 'decimals', 2);
        $amountMinor = (int) round(((float) $value) * (10 ** $decimals));

        return $this->record(
            $order,
            $refundId,
            $amountMinor,
            $currency,
            (string) ($resource['status'] ?? 'completed'),
            $resource,
            'PayPal webhook reconciliation',
            null,
        );
    }

    private function createRefund(CommerceOrder $order, string $externalRefundId, int $amountMinor, string $currency, string $status, array $payload, ?string $reason, ?string $requestKey): void
    {
        CommerceOrderRefund::create([
            'commerce_order_id' => $order->id,
            'provider' => 'paypal',
            'request_key' => $requestKey,
            'external_refund_id' => $externalRefundId,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'status' => $status,
            'reason' => $reason,
            'provider_payload' => $payload,
            'provider_synced_at' => now(),
            'refunded_at' => $status === 'completed' ? now() : null,
        ]);
    }
}
