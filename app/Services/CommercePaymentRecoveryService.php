<?php

namespace App\Services;

use App\Models\CommerceOrder;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class CommercePaymentRecoveryService
{
    public function __construct(
        private readonly CommercePayPalService $paypal,
        private readonly CommerceOrderService $orders,
        private readonly CommerceOrderNotificationService $notifications,
    ) {}

    /**
     * Reconcile one pending/cancelled commerce order with PayPal.
     *
     * @return string recovered|waiting|expired|already_paid|skipped
     */
    public function recover(CommerceOrder $order): string
    {
        $order = $order->fresh()->loadMissing('website');

        if (in_array($order->payment_status, ['paid', 'partially_refunded', 'refunded'], true)) {
            return 'already_paid';
        }

        if (! in_array($order->payment_status, ['pending', 'cancelled'], true)) {
            return 'skipped';
        }

        $expired = $order->checkout_expires_at && $order->checkout_expires_at->isPast();

        if (! filled($order->external_checkout_id)) {
            if ($expired) {
                $this->orders->expirePending($order, 'No PayPal checkout was linked before the checkout recovery window expired.');
                return 'expired';
            }
            return 'waiting';
        }

        try {
            $remote = $this->paypal->inspect($order);
            $status = strtoupper((string) ($remote['status'] ?? ''));

            if (in_array($status, ['APPROVED', 'COMPLETED'], true)) {
                $this->orders->recordRecoveryAttempt($order);
                $payload = $this->paypal->captureAndVerify($order->fresh(), (string) $order->external_checkout_id);
                $paid = $this->orders->markPaid($order->fresh(), $this->paypal->captureId($payload), 'recovery');
                $this->notifications->paid($paid);
                return 'recovered';
            }

            if ($expired && in_array($status, ['CREATED', 'PAYER_ACTION_REQUIRED', 'VOIDED'], true)) {
                $this->orders->recordRecoveryAttempt($order);
                $this->orders->expirePending($order, 'PayPal checkout expired without a completed payment.');
                return 'expired';
            }

            $this->orders->recordRecoveryAttempt($order);
            return 'waiting';
        } catch (RuntimeException $e) {
            $this->orders->recordRecoveryAttempt($order, $e->getMessage());
            Log::warning('Commerce payment recovery attempt failed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'external_checkout_id' => $order->external_checkout_id,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
