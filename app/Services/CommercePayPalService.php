<?php

namespace App\Services;

use App\Models\CommerceOrder;
use RuntimeException;

final class CommercePayPalService
{
    public function __construct(private readonly PayPalService $paypal) {}

    public function create(CommerceOrder $order, string $returnUrl, string $cancelUrl): array
    {
        if (! config('payments.paypal.enabled')) {
            throw new RuntimeException('PayPal checkout is not enabled.');
        }

        $response = $this->paypal->client($this->requestId('create', $order))->post('/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $order->public_id,
                'custom_id' => $order->public_id,
                'invoice_id' => $order->order_number,
                'description' => 'Order '.$order->order_number,
                'amount' => [
                    'currency_code' => $order->currency,
                    'value' => $this->major($order->total_minor, $order->currency),
                ],
            ]],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'brand_name' => $order->website?->name ?: 'Cosmic Commerce',
                        'shipping_preference' => 'NO_SHIPPING',
                        'user_action' => 'PAY_NOW',
                        'return_url' => $returnUrl,
                        'cancel_url' => $cancelUrl,
                    ],
                ],
            ],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException($response->json('details.0.description') ?? $response->json('message') ?? 'PayPal could not create the checkout.');
        }

        $payload = $response->json();
        $links = collect($payload['links'] ?? []);
        $approveUrl = data_get($links->firstWhere('rel', 'payer-action'), 'href')
            ?: data_get($links->firstWhere('rel', 'approve'), 'href');
        if (! is_string($approveUrl) || $approveUrl === '') {
            throw new RuntimeException('PayPal did not return an approval URL.');
        }
        return ['id' => (string) ($payload['id'] ?? ''), 'approve_url' => $approveUrl, 'payload' => $payload];
    }

    public function inspect(CommerceOrder $order): array
    {
        $paypalOrderId = trim((string) $order->external_checkout_id);
        if ($paypalOrderId === '') {
            throw new RuntimeException('This order does not have a PayPal checkout ID.');
        }

        $payload = $this->paypal->getOrder($paypalOrderId);
        $this->assertOrderResourceMatches($order, $payload, requireCompleted: false);
        return $payload;
    }

    public function captureAndVerify(CommerceOrder $order, string $paypalOrderId): array
    {
        if ($order->external_checkout_id && ! hash_equals((string) $order->external_checkout_id, $paypalOrderId)) {
            throw new RuntimeException('PayPal checkout ID does not match this order.');
        }
        $payload = $this->paypal->captureOrder($paypalOrderId, $this->requestId('capture', $order));
        $this->assertOrderResourceMatches($order, $payload, requireCompleted: true);
        return $payload;
    }


    private function assertOrderResourceMatches(CommerceOrder $order, array $payload, bool $requireCompleted): void
    {
        $status = strtoupper((string) data_get($payload, 'status'));
        $reference = (string) (data_get($payload, 'purchase_units.0.custom_id') ?: data_get($payload, 'purchase_units.0.reference_id'));
        $currency = strtoupper((string) data_get($payload, 'purchase_units.0.payments.captures.0.amount.currency_code', data_get($payload, 'purchase_units.0.amount.currency_code')));
        $value = (string) data_get($payload, 'purchase_units.0.payments.captures.0.amount.value', data_get($payload, 'purchase_units.0.amount.value'));

        if ($requireCompleted && $status !== 'COMPLETED') {
            throw new RuntimeException('PayPal has not completed this payment.');
        }
        if ($status === '') {
            throw new RuntimeException('PayPal did not return an order status.');
        }
        if ($reference === '' || ! hash_equals((string) $order->public_id, $reference)) {
            throw new RuntimeException('PayPal order reference does not match.');
        }
        if ($currency !== strtoupper((string) $order->currency)) {
            throw new RuntimeException('PayPal currency does not match this order.');
        }
        if ($this->minor($value, $order->currency) !== (int) $order->total_minor) {
            throw new RuntimeException('PayPal amount does not match this order.');
        }
    }

    public function refund(CommerceOrder $order, int $amountMinor, string $requestKey): array
    {
        if (! config('payments.paypal.enabled')) {
            throw new RuntimeException('PayPal refunds are not enabled.');
        }
        if (! $order->external_payment_id) {
            throw new RuntimeException('This order does not have a PayPal capture ID.');
        }
        if ($amountMinor < 1) {
            throw new RuntimeException('Refund amount must be greater than zero.');
        }

        $remaining = max(0, (int) $order->total_minor - (int) $order->refunded_minor);
        if ($amountMinor > $remaining) {
            throw new RuntimeException('Refund amount exceeds the remaining refundable balance.');
        }

        $requestKey = trim($requestKey);
        if ($requestKey === '') {
            throw new RuntimeException('Refund request key is required.');
        }

        $response = $this->paypal->client('cosmic-commerce-refund-'.$order->public_id.'-'.$requestKey)->post('/v2/payments/captures/'.rawurlencode((string) $order->external_payment_id).'/refund', [
            'amount' => [
                'value' => $this->major($amountMinor, $order->currency),
                'currency_code' => $order->currency,
            ],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException($response->json('details.0.description') ?? $response->json('message') ?? 'PayPal could not issue the refund.');
        }

        $payload = $response->json();
        $status = strtoupper((string) data_get($payload, 'status'));
        if (! in_array($status, ['COMPLETED', 'PENDING'], true)) {
            throw new RuntimeException('PayPal returned an unexpected refund status.');
        }

        return $payload;
    }

    public function captureId(array $payload): string
    {
        return (string) (data_get($payload, 'purchase_units.0.payments.captures.0.id') ?: data_get($payload, 'id') ?: '');
    }

    public function assertCaptureResourceMatches(CommerceOrder $order, array $resource): void
    {
        $status = strtoupper((string) ($resource['status'] ?? ''));
        $reference = (string) ($resource['custom_id'] ?? '');
        $invoice = (string) ($resource['invoice_id'] ?? '');
        $currency = strtoupper((string) data_get($resource, 'amount.currency_code'));
        $value = (string) data_get($resource, 'amount.value');

        if ($status !== 'COMPLETED') {
            throw new RuntimeException('PayPal has not completed this payment.');
        }
        if ($reference !== '' && ! hash_equals((string) $order->public_id, $reference)) {
            throw new RuntimeException('PayPal capture reference does not match this order.');
        }
        if ($reference === '' && $invoice !== '' && ! hash_equals((string) $order->order_number, $invoice)) {
            throw new RuntimeException('PayPal capture invoice does not match this order.');
        }
        if ($currency !== strtoupper((string) $order->currency)) {
            throw new RuntimeException('PayPal currency does not match this order.');
        }
        if ($this->minor($value, $order->currency) !== (int) $order->total_minor) {
            throw new RuntimeException('PayPal amount does not match this order.');
        }
    }

    private function requestId(string $operation, CommerceOrder $order): string
    {
        return 'cosmic-commerce-'.$operation.'-'.$order->public_id;
    }

    private function major(int $minor, string $currency): string
    {
        $decimals = (int) data_get(config('cosmic-commerce.currencies.'.$currency), 'decimals', 2);
        return number_format($minor / (10 ** $decimals), $decimals, '.', '');
    }

    private function minor(string $major, string $currency): int
    {
        $decimals = (int) data_get(config('cosmic-commerce.currencies.'.$currency), 'decimals', 2);
        return (int) round(((float) $major) * (10 ** $decimals));
    }
}
