<?php

namespace App\Http\Controllers;

use App\Models\PaymentOrder;
use App\Services\CreditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PaymentWebhookController extends Controller
{
    public function stripe(Request $request, CreditService $credits): Response
    {
        $payload = $request->getContent();
        abort_unless($this->validStripeSignature($payload, (string) $request->header('Stripe-Signature')), 400, 'Invalid signature.');
        $event = json_decode($payload, true);
        $type = $event['type'] ?? '';
        $object = $event['data']['object'] ?? [];

        if (in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)
            && ($object['payment_status'] ?? '') !== 'unpaid') {
            $reference = $object['metadata']['order_reference'] ?? $object['client_reference_id'] ?? null;
            $this->fulfill($reference, (string) ($object['payment_intent'] ?? $object['id'] ?? ''), (string) ($object['subscription'] ?? ''), $credits);
        }

        if ($type === 'customer.subscription.deleted') {
            PaymentOrder::where('external_subscription_id', $object['id'] ?? '')->first()?->user?->update(['plan_status' => 'cancelled']);
        }

        return response('ok');
    }

    public function paymongo(Request $request, CreditService $credits): Response
    {
        $payload = $request->getContent();
        $event = json_decode($payload, true);
        abort_unless($this->validPaymongoSignature($payload, (string) $request->header('Paymongo-Signature'), (bool) data_get($event, 'data.attributes.livemode')), 400, 'Invalid signature.');

        if (data_get($event, 'data.attributes.type') === 'payment.paid') {
            $payment = data_get($event, 'data.attributes.data', []);
            $reference = data_get($payment, 'attributes.external_reference_number');
            $this->fulfill($reference, (string) ($payment['id'] ?? ''), '', $credits);
        }

        return response('ok');
    }

    private function fulfill(?string $reference, string $paymentId, string $subscriptionId, CreditService $credits): void
    {
        if (! $reference) {
            return;
        }

        DB::transaction(function () use ($reference, $paymentId, $subscriptionId, $credits) {
            $order = PaymentOrder::where('reference', $reference)->lockForUpdate()->first();
            if (! $order || $order->fulfilled_at) {
                return;
            }

            $order->update([
                'status' => 'paid',
                'external_payment_id' => $paymentId ?: null,
                'external_subscription_id' => $subscriptionId ?: null,
                'paid_at' => now(),
            ]);

            $credits->grant(
                $order->user,
                $order->credits,
                $order->product_type === 'plan' ? ucfirst($order->product_key).' plan credits' : 'Credit purchase: '.$order->product_key,
                'payment:'.$order->provider.':'.$order->reference,
                ['payment_order_id' => $order->id, 'provider' => $order->provider, 'product_type' => $order->product_type],
            );

            if ($order->product_type === 'plan') {
                $order->user->update([
                    'plan_key' => $order->product_key,
                    'plan_status' => 'active',
                    'plan_provider' => $order->provider,
                    'plan_renews_at' => now()->addMonth(),
                ]);
            }
            $order->update(['fulfilled_at' => now()]);
        });
    }

    private function validStripeSignature(string $payload, string $header): bool
    {
        $secret = (string) config('payments.stripe.webhook_secret');
        preg_match('/(?:^|,)t=(\d+)/', $header, $time);
        preg_match_all('/(?:^|,)v1=([a-f0-9]+)/i', $header, $signatures);
        if ($secret === '' || empty($time[1]) || abs(time() - (int) $time[1]) > 300) {
            return false;
        }
        $expected = hash_hmac('sha256', $time[1].'.'.$payload, $secret);
        foreach ($signatures[1] ?? [] as $signature) {
            if (hash_equals($expected, $signature)) return true;
        }
        return false;
    }

    private function validPaymongoSignature(string $payload, string $header, bool $live): bool
    {
        $secret = (string) config('payments.paymongo.webhook_secret');
        $parts = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key && $value) $parts[$key] = $value;
        }
        $signature = $parts[$live ? 'li' : 'te'] ?? null;
        if ($secret === '' || ! isset($parts['t']) || ! $signature || abs(time() - (int) $parts['t']) > 300) {
            return false;
        }
        return hash_equals(hash_hmac('sha256', $parts['t'].'.'.$payload, $secret), $signature);
    }
}
