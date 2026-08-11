<?php

namespace App\Http\Controllers;

use App\Models\PaymentOrder;
use App\Models\CommerceOrder;
use App\Models\PaymentWebhookEvent;
use App\Services\PaymentFulfillmentService;
use App\Services\CommerceOrderService;
use App\Services\CommercePayPalService;
use App\Services\CommerceOrderNotificationService;
use App\Services\CommerceRefundService;
use App\Services\WorkspaceProvisioningService;
use App\Services\PaymentWebhookEventService;
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
        PaymentWebhookEventService $events,
        WorkspaceProvisioningService $provisioning,
        CommerceOrderService $commerceOrders,
        CommercePayPalService $commercePayPal,
        CommerceOrderNotificationService $commerceNotifications,
        CommerceRefundService $commerceRefunds,
    ): Response {
        abort_unless($paypal->verifyWebhook($request), 400, 'Invalid PayPal webhook signature.');

        $event = json_decode($request->getContent(), true) ?: [];
        $eventId = (string) ($event['id'] ?? '');
        $type = (string) ($event['event_type'] ?? '');
        $resource = is_array($event['resource'] ?? null) ? $event['resource'] : [];
        $resource['_webhook_event_id'] = $eventId;

        abort_if($eventId === '' || $type === '', 400, 'Invalid PayPal webhook payload.');

        $eventRecord = $events->claim('paypal', $eventId, $type, $event);

        // Already processed, or another request is actively processing it.
        if (! $eventRecord) {
            return response('ok');
        }

        try {
            $handled = match ($type) {
                'CHECKOUT.ORDER.APPROVED' => $this->captureApprovedOrder($resource, $paypal, $fulfillment, $commerceOrders, $commercePayPal, $commerceNotifications),
                'PAYMENT.CAPTURE.COMPLETED' => $this->fulfillPayPalCapture($resource, $fulfillment, $commerceOrders, $commercePayPal, $commerceNotifications),
                'PAYMENT.CAPTURE.REFUNDED' => $this->syncCommerceRefund($resource, $commerceRefunds, $commerceNotifications),
                'BILLING.SUBSCRIPTION.ACTIVATED' => $this->activateSubscription($resource, $fulfillment, $subscriptions, $provisioning),
                'BILLING.SUBSCRIPTION.UPDATED' => $this->syncSubscriptionUpdate($resource, $subscriptions),
                'PAYMENT.SALE.COMPLETED' => $this->renewSubscription($resource, $fulfillment),
                'BILLING.SUBSCRIPTION.PAYMENT.FAILED',
                'PAYMENT.SALE.DENIED' => $this->subscriptionPaymentFailed($resource, $fulfillment),
                'BILLING.SUBSCRIPTION.SUSPENDED' => $this->applySubscriptionStatus($resource, $fulfillment, 'suspended'),
                'BILLING.SUBSCRIPTION.CANCELLED' => $this->applySubscriptionStatus($resource, $fulfillment, 'cancelled'),
                'BILLING.SUBSCRIPTION.EXPIRED' => $this->applySubscriptionStatus($resource, $fulfillment, 'expired'),
                default => false,
            };

            if ($handled === false) {
                $events->ignored($eventRecord, 'Unsupported PayPal event type.');
            } else {
                $events->processed($eventRecord);
            }
        } catch (\Throwable $exception) {
            $events->failed($eventRecord, $exception);
            report($exception);

            // Return a retriable response so PayPal can deliver the event again.
            return response('Webhook processing failed.', 500);
        }

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
        abort(410, 'Stripe checkout is disabled.');
    }

    private function captureApprovedOrder(
        array $resource,
        PayPalService $paypal,
        PaymentFulfillmentService $fulfillment,
        CommerceOrderService $commerceOrders,
        CommercePayPalService $commercePayPal,
        CommerceOrderNotificationService $commerceNotifications,
    ): bool {
        $orderId = (string) ($resource['id'] ?? '');

        if ($orderId === '') {
            return true;
        }

        $reference = (string) $paypal->orderReference($resource);
        $commerceOrder = CommerceOrder::query()
            ->when($reference !== '', fn ($query) => $query->where('public_id', $reference))
            ->when($reference === '', fn ($query) => $query->where('external_checkout_id', $orderId))
            ->first();

        if ($commerceOrder) {
            $commerceOrders->linkExternalCheckout($commerceOrder, $orderId);
            $capturedOrder = $commercePayPal->captureAndVerify($commerceOrder->fresh()->loadMissing('website'), $orderId);
            $paid = $commerceOrders->markPaid($commerceOrder, $commercePayPal->captureId($capturedOrder), 'webhook_approved');
            $commerceNotifications->paid($paid);
            return true;
        }

        $capturedOrder = $paypal->captureOrder($orderId, 'cosmic-platform-capture-'.$orderId);
        $reference = $paypal->orderReference($capturedOrder);
        $paymentOrder = PaymentOrder::query()->where('reference', $reference)->first();

        if (! $paymentOrder || $paymentOrder->product_type !== 'credits') {
            return true;
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

        return true;
    }

    private function fulfillPayPalCapture(
        array $resource,
        PaymentFulfillmentService $fulfillment,
        CommerceOrderService $commerceOrders,
        CommercePayPalService $commercePayPal,
        CommerceOrderNotificationService $commerceNotifications,
    ): bool {
        $reference = (string) (
            $resource['custom_id']
            ?? $resource['invoice_id']
            ?? ''
        );
        $orderId = (string) data_get($resource, 'supplementary_data.related_ids.order_id');

        $commerceOrder = ($reference !== '' || $orderId !== '')
            ? CommerceOrder::query()
                ->where(function ($query) use ($reference, $orderId) {
                    if ($reference !== '') {
                        $query->where('public_id', $reference)->orWhere('order_number', $reference);
                    }
                    if ($orderId !== '') {
                        $query->orWhere('external_checkout_id', $orderId);
                    }
                })
                ->first()
            : null;

        if ($commerceOrder) {
            if ($orderId !== '') {
                $commerceOrders->linkExternalCheckout($commerceOrder, $orderId);
            }
            $commercePayPal->assertCaptureResourceMatches($commerceOrder, $resource);
            $paid = $commerceOrders->markPaid($commerceOrder, (string) ($resource['id'] ?? ''), 'webhook_capture');
            $commerceNotifications->paid($paid);
            return true;
        }

        if ($reference === '' && $orderId !== '') {
            $reference = (string) PaymentOrder::query()
                ->where('external_checkout_id', $orderId)
                ->value('reference');
        }

        $paymentOrder = PaymentOrder::query()
            ->where('reference', $reference)
            ->where('product_type', 'credits')
            ->first();

        if (! $paymentOrder) {
            throw new \RuntimeException('PayPal capture arrived before its payment order was available.');
        }

        $status = strtoupper((string) ($resource['status'] ?? ''));
        $currency = strtoupper((string) data_get($resource, 'amount.currency_code'));
        $amountMinor = (int) round(((float) data_get($resource, 'amount.value')) * 100);

        if (
            $status !== 'COMPLETED'
            || $currency !== strtoupper((string) $paymentOrder->currency)
            || $amountMinor !== (int) $paymentOrder->amount_minor
        ) {
            throw new \RuntimeException('PayPal capture details did not match the local payment order.');
        }

        $fulfillment->fulfillOrder(
            $paymentOrder->reference,
            (string) ($resource['id'] ?? ''),
            '',
            ['paypal_capture_source' => 'payment_capture_webhook'],
        );

        return true;
    }

    private function syncCommerceRefund(array $resource, CommerceRefundService $refunds, CommerceOrderNotificationService $notifications): bool
    {
        $order = $refunds->syncWebhook($resource);
        if ($order) {
            $amountMinor = (int) $order->refunds->where('external_refund_id', (string) ($resource['id'] ?? ''))->first()?->amount_minor;
            if ($amountMinor > 0 && strtolower((string) ($resource['status'] ?? '')) === 'completed') {
                $notifications->refund($order, $amountMinor);
            }
        }
        return true;
    }

    private function activateSubscription(
        array $resource,
        PaymentFulfillmentService $fulfillment,
        SubscriptionManagementService $subscriptions,
        WorkspaceProvisioningService $provisioning,
    ): bool {
        $reference = (string) ($resource['custom_id'] ?? '');
        $subscriptionId = (string) ($resource['id'] ?? '');

        if ($reference === '' && $subscriptionId !== '') {
            $reference = (string) PaymentOrder::query()
                ->where('external_subscription_id', $subscriptionId)
                ->value('reference');
        }

        if ($reference === '') {
            throw new \RuntimeException('Subscription activation arrived before the local checkout was linked.');
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
                $subscriptions->syncOrder($order);
                $subscriptions->finalizeSwitch($order->fresh());

                $onboardingId = (int) data_get($order->metadata, 'onboarding_id', 0);
                if ($onboardingId > 0) {
                    $onboarding = \App\Models\PendingOnboarding::query()->find($onboardingId);
                    if ($onboarding && $onboarding->status !== 'completed') {
                        $onboarding->update([
                            'status' => 'payment_confirmed',
                            'metadata' => array_merge($onboarding->metadata ?? [], [
                                'payment_confirmed_at' => now()->toIso8601String(),
                                'paypal_subscription_id' => $order->external_subscription_id,
                                'confirmed_by' => 'webhook',
                            ]),
                        ]);
                        $provisioning->start($onboarding->fresh(), $order->fresh(), 'webhook');
                    }
                }
            }
        }

        return true;
    }

    private function syncSubscriptionUpdate(array $resource, SubscriptionManagementService $subscriptions): bool
    {
        $subscriptionId = (string) ($resource['id'] ?? '');

        if ($subscriptionId === '') {
            return true;
        }

        $order = PaymentOrder::query()
            ->where('external_subscription_id', $subscriptionId)
            ->latest('id')
            ->first();

        if (! $order) {
            throw new \RuntimeException('Subscription update arrived before the local subscription was linked.');
        }

        $subscriptions->syncOrder($order);

        return true;
    }

    private function renewSubscription(
        array $resource,
        PaymentFulfillmentService $fulfillment,
    ): bool {
        $subscriptionId = (string) (
            $resource['billing_agreement_id']
            ?? data_get($resource, 'supplementary_data.related_ids.subscription_id')
            ?? ''
        );

        $fulfilled = $fulfillment->fulfillSubscriptionRenewal(
            $subscriptionId,
            (string) ($resource['id'] ?? ''),
            [
                'paypal_sale_state' => (string) ($resource['state'] ?? ''),
                'amount_minor' => (int) round(((float) data_get($resource, 'amount.total')) * 100),
                'currency' => strtoupper((string) data_get($resource, 'amount.currency')),
                'next_billing_time' => data_get($resource, 'billing_info.next_billing_time'),
            ],
        );

        if (! $fulfilled) {
            throw new \RuntimeException('Renewal arrived before the local subscription was linked.');
        }

        return true;
    }

    private function subscriptionPaymentFailed(
        array $resource,
        PaymentFulfillmentService $fulfillment,
    ): bool {
        $subscriptionId = (string) (
            $resource['billing_agreement_id']
            ?? $resource['id']
            ?? data_get($resource, 'supplementary_data.related_ids.subscription_id')
            ?? ''
        );

        $this->assertKnownSubscription($subscriptionId);

        $fulfillment->updateSubscriptionStatus($subscriptionId, 'past_due', [
            'payment_failure_id' => (string) ($resource['_webhook_event_id'] ?? $resource['id'] ?? ''),
            'payment_failure_at' => now()->toIso8601String(),
        ]);

        return true;
    }

    private function applySubscriptionStatus(array $resource, PaymentFulfillmentService $fulfillment, string $status): bool
    {
        $subscriptionId = (string) ($resource['id'] ?? '');
        $this->assertKnownSubscription($subscriptionId);
        $fulfillment->updateSubscriptionStatus($subscriptionId, $status, [
            'provider_event_id' => (string) ($resource['_webhook_event_id'] ?? ''),
        ]);

        return true;
    }

    private function assertKnownSubscription(string $subscriptionId): void
    {
        if ($subscriptionId === '' || ! PaymentOrder::query()->where('external_subscription_id', $subscriptionId)->exists()) {
            throw new \RuntimeException('Webhook arrived before the local subscription was linked.');
        }
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
