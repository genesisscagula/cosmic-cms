<?php

namespace App\Services;

use App\Mail\CommerceOrderMail;
use App\Models\CommerceOrder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class CommerceOrderNotificationService
{
    public function paid(CommerceOrder $order): void
    {
        $order->loadMissing(['website.user', 'items', 'refunds']);
        $failed = [];

        if (! $order->order_confirmation_sent_at && $order->customer_email) {
            if ($this->send($order->customer_email, new CommerceOrderMail($order, 'confirmation'), $error)) {
                $order->forceFill(['order_confirmation_sent_at' => now(), 'last_customer_notification_at' => now()])->save();
            } else { $failed[] = 'customer confirmation: '.$error; }
        }

        $merchantEmail = $order->website?->contact_email ?: $order->website?->user?->email;
        if (! $order->merchant_notification_sent_at && $merchantEmail) {
            if ($this->send($merchantEmail, new CommerceOrderMail($order, 'merchant_new_order'), $error)) {
                $order->forceFill(['merchant_notification_sent_at' => now()])->save();
            } else { $failed[] = 'merchant alert: '.$error; }
        }

        $this->recordAttempt($order->fresh(), $failed);
    }

    public function fulfillment(CommerceOrder $order, bool $trackingChanged = false): void
    {
        if (! $order->customer_email || ! in_array($order->payment_status, ['paid', 'partially_refunded'], true)) return;
        $order->loadMissing(['website', 'items', 'refunds']);
        $kind = $order->status === 'completed' ? 'fulfilled' : ($trackingChanged ? 'tracking' : null);
        if (! $kind) return;
        if ($this->send($order->customer_email, new CommerceOrderMail($order, $kind), $error)) {
            $order->forceFill(['last_customer_notification_at' => now()])->save();
        } else {
            Log::warning('Commerce fulfillment notification failed', ['order_id' => $order->id, 'message' => $error]);
        }
    }

    public function refund(CommerceOrder $order, int $amountMinor): void
    {
        if (! $order->customer_email) return;
        $order->loadMissing(['website', 'items', 'refunds']);
        if ($this->send($order->customer_email, new CommerceOrderMail($order, 'refund', $amountMinor), $error)) {
            $order->forceFill(['last_customer_notification_at' => now()])->save();
        } else {
            Log::warning('Commerce refund notification failed', ['order_id' => $order->id, 'message' => $error]);
        }
    }

    private function recordAttempt(CommerceOrder $order, array $failed): void
    {
        if ($order->order_confirmation_sent_at && $order->merchant_notification_sent_at) {
            $order->forceFill([
                'notification_next_attempt_at' => null,
                'notification_last_error' => null,
                'notification_attention_required_at' => null,
            ])->save();
            return;
        }
        if (! $failed) return;

        $attempts = min(65535, ((int) $order->notification_attempts) + 1);
        $delays = [5, 15, 30, 60, 180, 360];
        $delay = $delays[min($attempts - 1, count($delays) - 1)];
        $order->forceFill([
            'notification_attempts' => $attempts,
            'notification_last_attempt_at' => now(),
            'notification_next_attempt_at' => now()->addMinutes($delay),
            'notification_last_error' => mb_substr(implode(' | ', $failed), 0, 2000),
            'notification_attention_required_at' => $attempts >= 6 ? ($order->notification_attention_required_at ?: now()) : null,
        ])->save();
    }

    private function send(string $email, CommerceOrderMail $mail, ?string &$error = null): bool
    {
        try {
            Mail::to($email)->send($mail);
            $error = null;
            return true;
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            Log::warning('Commerce order email failed', ['email' => $email, 'message' => $error]);
            return false;
        }
    }
}
