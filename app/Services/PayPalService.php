<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use App\Models\PaymentOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PayPalService
{
    private function http(): PendingRequest
    {
        $options = ['verify' => true];

        // XAMPP's file-based CA bundle may differ from Windows' trusted store.
        // Let cURL use the native roots without disabling peer/hostname checks.
        if (PHP_OS_FAMILY === 'Windows'
            && defined('CURLSSLOPT_NATIVE_CA')
            && defined('CURLOPT_SSL_OPTIONS')) {
            $options['curl'] = [CURLOPT_SSL_OPTIONS => CURLSSLOPT_NATIVE_CA];
        }

        return Http::withOptions($options);
    }

    public function client(?string $requestId = null): PendingRequest
    {
        $clientId = (string) config('payments.paypal.client_id');
        $clientSecret = (string) config('payments.paypal.client_secret');
        $baseUrl = rtrim((string) config('payments.paypal.base_url'), '/');

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('PayPal is not configured.');
        }

        $cacheKey = 'paypal:access-token:'.sha1($baseUrl.'|'.$clientId);
        $accessToken = Cache::get($cacheKey);

        if (! is_string($accessToken) || $accessToken === '') {
            $tokenResponse = $this->http()->asForm()
                ->connectTimeout(5)
                ->timeout(15)
                ->retry(2, 300, throw: false)
                ->withBasicAuth($clientId, $clientSecret)
                ->post($baseUrl.'/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ]);

            $accessToken = $tokenResponse->json('access_token');

            if (! $tokenResponse->successful() || ! is_string($accessToken) || $accessToken === '') {
                throw new RuntimeException(
                    $tokenResponse->json('error_description', 'PayPal authentication failed.')
                );
            }

            $ttl = max(60, ((int) $tokenResponse->json('expires_in', 300)) - 60);
            Cache::put($cacheKey, $accessToken, now()->addSeconds($ttl));
        }

        $requestId = trim((string) $requestId);
        if ($requestId === '') {
            $requestId = (string) Str::uuid();
        }

        // PayPal uses this header to make create/capture POST retries idempotent.
        // Callers that are retrying the same business operation should pass a
        // stable request ID; unrelated calls still receive a fresh UUID.
        $requestId = substr($requestId, 0, 108);

        return $this->http()->baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(20)
            ->retry(2, 300, throw: false)
            ->withToken($accessToken)
            ->withHeaders([
                'PayPal-Request-Id' => $requestId,
            ]);
    }

    public function verifyWebhook(Request $request): bool
    {
        $webhookId = (string) config('payments.paypal.webhook_id');

        if ($webhookId === '') {
            return false;
        }

        $requiredHeaders = [
            'PAYPAL-AUTH-ALGO' => $request->header('PayPal-Auth-Algo'),
            'PAYPAL-CERT-URL' => $request->header('PayPal-Cert-Url'),
            'PAYPAL-TRANSMISSION-ID' => $request->header('PayPal-Transmission-Id'),
            'PAYPAL-TRANSMISSION-SIG' => $request->header('PayPal-Transmission-Sig'),
            'PAYPAL-TRANSMISSION-TIME' => $request->header('PayPal-Transmission-Time'),
        ];

        if (in_array(null, $requiredHeaders, true) || in_array('', $requiredHeaders, true)) {
            return false;
        }

        $event = json_decode($request->getContent(), true);

        if (! is_array($event)) {
            return false;
        }

        $response = $this->client()->post('/v1/notifications/verify-webhook-signature', [
            'auth_algo' => $requiredHeaders['PAYPAL-AUTH-ALGO'],
            'cert_url' => $requiredHeaders['PAYPAL-CERT-URL'],
            'transmission_id' => $requiredHeaders['PAYPAL-TRANSMISSION-ID'],
            'transmission_sig' => $requiredHeaders['PAYPAL-TRANSMISSION-SIG'],
            'transmission_time' => $requiredHeaders['PAYPAL-TRANSMISSION-TIME'],
            'webhook_id' => $webhookId,
            'webhook_event' => $event,
        ]);

        return $response->successful()
            && strtoupper((string) $response->json('verification_status')) === 'SUCCESS';
    }

    public function captureOrder(string $orderId, ?string $requestId = null): array
    {
        if ($orderId === '') {
            throw new RuntimeException('Missing PayPal order ID.');
        }

        $response = $this->client($requestId)->post('/v2/checkout/orders/'.$orderId.'/capture', new \stdClass());

        if (! $response->successful()) {
            $issue = $response->json('details.0.issue');

            if ($issue !== 'ORDER_ALREADY_CAPTURED') {
                throw new RuntimeException(
                    $response->json('details.0.description')
                        ?? $response->json('message')
                        ?? 'PayPal could not capture the order.'
                );
            }

            return $this->getOrder($orderId);
        }

        return $response->json();
    }

    public function getOrder(string $orderId): array
    {
        $response = $this->client()->get('/v2/checkout/orders/'.$orderId);

        if (! $response->successful()) {
            throw new RuntimeException(
                $response->json('message', 'PayPal could not retrieve the order.')
            );
        }

        return $response->json();
    }

    public function assertOrderMatches(PaymentOrder $paymentOrder, array $paypalOrder): void
    {
        $status = strtoupper((string) data_get($paypalOrder, 'status'));
        $reference = (string) $this->orderReference($paypalOrder);
        $currency = strtoupper((string) data_get($paypalOrder, 'purchase_units.0.amount.currency_code'));
        $value = (string) data_get($paypalOrder, 'purchase_units.0.amount.value');
        $amountMinor = (int) round(((float) $value) * 100);

        if ($status !== 'COMPLETED') {
            throw new RuntimeException('PayPal has not completed this payment.');
        }

        if ($reference === '' || ! hash_equals($paymentOrder->reference, $reference)) {
            throw new RuntimeException('PayPal order reference does not match this purchase.');
        }

        if ($currency !== strtoupper((string) $paymentOrder->currency)) {
            throw new RuntimeException('PayPal payment currency does not match this purchase.');
        }

        if ($amountMinor !== (int) $paymentOrder->amount_minor) {
            throw new RuntimeException('PayPal payment amount does not match this purchase.');
        }
    }

    public function orderReference(array $order): ?string
    {
        return data_get($order, 'purchase_units.0.custom_id')
            ?: data_get($order, 'purchase_units.0.reference_id');
    }

    public function captureId(array $order): string
    {
        return (string) (
            data_get($order, 'purchase_units.0.payments.captures.0.id')
            ?: data_get($order, 'id')
            ?: ''
        );
    }

    public function getSubscription(string $subscriptionId): array
    {
        if ($subscriptionId === '') {
            throw new RuntimeException('Missing PayPal subscription ID.');
        }

        $response = $this->client()->get('/v1/billing/subscriptions/'.$subscriptionId);

        if (! $response->successful()) {
            throw new RuntimeException(
                $response->json('details.0.description')
                    ?? $response->json('message')
                    ?? 'PayPal could not retrieve the subscription.'
            );
        }

        return $response->json();
    }

    public function activateSubscription(string $subscriptionId, string $reason = 'Reactivated by the customer from Cosmic CMS.'): void
    {
        if ($subscriptionId === '') {
            throw new RuntimeException('Missing PayPal subscription ID.');
        }

        $response = $this->client()->post('/v1/billing/subscriptions/'.$subscriptionId.'/activate', [
            'reason' => $reason,
        ]);

        if (! $response->successful() && $response->status() !== 204) {
            $name = strtoupper((string) $response->json('name'));

            // PayPal may return UNPROCESSABLE_ENTITY when the subscription is
            // already active. A following GET sync determines the real state.
            if ($name !== 'UNPROCESSABLE_ENTITY') {
                throw new RuntimeException(
                    $response->json('details.0.description')
                        ?? $response->json('message')
                        ?? 'PayPal could not reactivate the subscription.'
                );
            }
        }
    }

    public function cancelSubscription(string $subscriptionId, string $reason): void
    {
        if ($subscriptionId === '') {
            throw new RuntimeException('Missing PayPal subscription ID.');
        }

        $response = $this->client()->post('/v1/billing/subscriptions/'.$subscriptionId.'/cancel', [
            'reason' => $reason,
        ]);

        if (! $response->successful() && $response->status() !== 204) {
            $issue = strtoupper((string) $response->json('name'));
            $message = (string) $response->json('message');

            if (! in_array($issue, ['RESOURCE_NOT_FOUND', 'UNPROCESSABLE_ENTITY'], true)) {
                throw new RuntimeException(
                    $response->json('details.0.description')
                        ?? $message
                        ?: 'PayPal could not cancel the subscription.'
                );
            }
        }
    }
}
