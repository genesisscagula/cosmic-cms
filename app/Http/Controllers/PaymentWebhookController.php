<?php

namespace App\Http\Controllers;

use App\Models\PaymentOrder;
use App\Services\PaymentFulfillmentService;
use App\Services\PayPalService;
use App\Services\SubscriptionManagementService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PaymentWebhookController extends Controller
{
    public function paypal(
        Request $request,
        PayPalService $paypal,
        PaymentFulfillmentService $fulfillment,
        SubscriptionManagementService $subscriptions,
    ): Response {
        abort_unless($paypal->verifyWebhook($request), 400, 'Invalid PayPal webhook signature.');

        $event = json_decode($request->getContent(), true) ?: [];
        $type = (string) ($event['event_type'] ?? '');
        $resource = $event['resource'] ?? [];

        match ($type) {
            'CHECKOUT.ORDER.APPROVED' => $this->captureApprovedOrder($resource, $paypal, $fulfillment),
            'PAYMENT.CAPTURE.COMPLETED' => $this->fulfillPayPalCapture($resource, $fulfillment),
            'BILLING.SUBSCRIPTION.ACTIVATED' => $this->activateSubscription($resource, $fulfillment, $subscriptions),
            'PAYMENT.SALE.COMPLETED' => $this->renewSubscription($resource, $fulfillment),
            'BILLING.SUBSCRIPTION.SUSPENDED' => $fulfillment->updateSubscriptionStatus(
                (string) ($resource['id'] ?? ''),
                'suspended',
            ),
            'BILLING.SUBSCRIPTION.CANCELLED' => $fulfillment->updateSubscriptionStatus(
                (string) ($resource['id'] ?? ''),
                'cancelled',
            ),
            'BILLING.SUBSCRIPTION.EXPIRED' => $fulfillment->updateSubscriptionStatus(
                (string) ($resource['id'] ?? ''),
                'expired',
            ),
            default => null,
        };

        return response('ok');
    }

    public function paymongo(
        Request $request,
        PaymentFulfillmentService $fulfillment,
    ): Response {
        $payload = $request->getContent();
        $event = json_decode($payload, true);

        abort_unless(
            $this->validPaymongoSignature(
                $payload,
                (string) $request->header('Paymongo-Signature'),
                (bool) data_get($event, 'data.attributes.livemode'),
            ),
            400,
            'Invalid PayMongo signature.',
        );

        if (data_get($event, 'data.attributes.type') === 'payment.paid') {
            $payment = data_get($event, 'data.attributes.data', []);
            $reference = data_get($payment, 'attributes.external_reference_number');

            $fulfillment->fulfillOrder(
                $reference,
                (string) ($payment['id'] ?? ''),
                '',
                ['paymongo_event_id' => data_get($event, 'data.id')],
            );
        }

        return response('ok');
    }

    public function stripe(Request $request): Response
    {
        /*
         * Stripe remains intentionally disabled in Cosmic Payments V1.
         * Preserve the endpoint for older integrations without fulfilling data.
         */
        abort(410, 'Stripe checkout is disabled.');
    }

    private function captureApprovedOrder(
        array $resource,
        PayPalService $paypal,
        PaymentFulfillmentService $fulfillment,
    ): void {
        $orderId = (string) ($resource['id'] ?? '');

        if ($orderId === '') {
            return;
        }

        $capturedOrder = $paypal->captureOrder($orderId);
        $reference = $paypal->orderReference($capturedOrder);
        $paymentOrder = PaymentOrder::query()->where('reference', $reference)->first();

        if (! $paymentOrder || $paymentOrder->product_type !== 'credits') {
            return;
        }

        $paypal->assertOrderMatches($paymentOrder, $capturedOrder);

        $fulfillment->fulfillOrder(
            $paymentOrder->reference,
            $paypal->captureId($capturedOrder),
            '',
            [
                'paypal_order_id' => $orderId,
                'paypal_capture_source' => 'webhook',
            ],
        );
    }

    private function fulfillPayPalCapture(
        array $resource,
        PaymentFulfillmentService $fulfillment,
        SubscriptionManagementService $subscriptions,
    ): void {
        $reference = (string) (
            $resource['custom_id']
            ?? $resource['invoice_id']
            ?? ''
        );

        if ($reference === '') {
            $orderId = (string) data_get($resource, 'supplementary_data.related_ids.order_id');

            $reference = (string) PaymentOrder::query()
                ->where('external_checkout_id', $orderId)
                ->value('reference');
        }

        $paymentOrder = PaymentOrder::query()
            ->where('reference', $reference)
            ->where('product_type', 'credits')
            ->first();

        if (! $paymentOrder) {
            return;
        }

        $status = strtoupper((string) ($resource['status'] ?? ''));
        $currency = strtoupper((string) data_get($resource, 'amount.currency_code'));
        $amountMinor = (int) round(((float) data_get($resource, 'amount.value')) * 100);

        if (
            $status !== 'COMPLETED'
            || $currency !== strtoupper((string) $paymentOrder->currency)
            || $amountMinor !== (int) $paymentOrder->amount_minor
        ) {
            return;
        }

        $fulfillment->fulfillOrder(
            $paymentOrder->reference,
            (string) ($resource['id'] ?? ''),
            '',
            ['paypal_capture_source' => 'payment_capture_webhook'],
        );
    }

    private function activateSubscription(
        array $resource,
        PaymentFulfillmentService $fulfillment,
        SubscriptionManagementService $subscriptions,
    ): void {
        $reference = (string) ($resource['custom_id'] ?? '');
        $subscriptionId = (string) ($resource['id'] ?? '');

        if ($reference === '' && $subscriptionId !== '') {
            $reference = (string) PaymentOrder::query()
                ->where('external_subscription_id', $subscriptionId)
                ->value('reference');
        }

        $fulfilled = $fulfillment->fulfillOrder(
            $reference,
            '',
            $subscriptionId,
            [
                'paypal_subscription_status' => (string) ($resource['status'] ?? 'ACTIVE'),
                'paypal_next_billing_time' => data_get($resource, 'billing_info.next_billing_time'),
            ],
        );

        if ($fulfilled) {
            $order = PaymentOrder::query()->where('reference', $reference)->first();

            if ($order) {
                try {
                    $subscriptions->syncOrder($order);
                    $subscriptions->finalizeSwitch($order->fresh());
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }
        }
    }

    private function renewSubscription(
        array $resource,
        PaymentFulfillmentService $fulfillment,
    ): void {
        $subscriptionId = (string) (
            $resource['billing_agreement_id']
            ?? data_get($resource, 'supplementary_data.related_ids.subscription_id')
            ?? ''
        );

        $fulfillment->fulfillSubscriptionRenewal(
            $subscriptionId,
            (string) ($resource['id'] ?? ''),
            [
                'paypal_sale_state' => (string) ($resource['state'] ?? ''),
            ],
        );
    }

    private function validPaymongoSignature(string $payload, string $header, bool $live): bool
    {
        $secret = (string) config('payments.paymongo.webhook_secret');
        $parts = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if ($key && $value) {
                $parts[$key] = $value;
            }
        }

        $signature = $parts[$live ? 'li' : 'te'] ?? null;

        if (
            $secret === ''
            || ! isset($parts['t'])
            || ! $signature
            || abs(time() - (int) $parts['t']) > 300
        ) {
            return false;
        }

        return hash_equals(
            hash_hmac('sha256', $parts['t'].'.'.$payload, $secret),
            $signature,
        );
    }
}
