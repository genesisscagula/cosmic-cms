<?php

namespace App\Services;

use App\Cosmic\Pricing\CreditPackageRegistry;
use App\Models\PaymentOrder;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentCheckoutService
{
    public function __construct(private readonly SubscriptionManagementService $subscriptions)
    {
    }
    public function create(User $user, string $provider, string $productType, string $productKey): array
    {
        $product = match ($productType) {
            'credits' => CreditPackageRegistry::get($productKey),
            'plan' => config("payments.plans.{$productKey}"),
            default => null,
        };

        if (! is_array($product)) {
            throw new RuntimeException('The selected product is unavailable.');
        }

        if (! in_array($provider, ['paypal', 'paymongo'], true)) {
            throw new RuntimeException('The selected payment provider is unavailable.');
        }

        if (! (bool) config("payments.{$provider}.enabled")) {
            throw new RuntimeException(ucfirst($provider).' is not enabled.');
        }

        if ($provider === 'paymongo' && $productType !== 'credits') {
            throw new RuntimeException('Monthly plans currently use PayPal. PayMongo is available for Philippine credit top-ups.');
        }

        $currency = $provider === 'paymongo' ? 'PHP' : 'USD';
        $priceKey = $provider === 'paymongo' ? 'price_php' : 'price_usd';

        if (! isset($product[$priceKey]) || ! is_numeric($product[$priceKey])) {
            throw new RuntimeException("The selected product has no {$currency} price configured.");
        }

        $amountMinor = (int) round(((float) $product[$priceKey]) * 100);

        $previousSubscription = $productType === 'plan'
            ? $this->subscriptions->assertCanStartPlanCheckout($user, $productKey)
            : null;

        $order = PaymentOrder::create([
            'reference' => (string) Str::uuid(),
            'user_id' => $user->id,
            'provider' => $provider,
            'product_type' => $productType,
            'product_key' => $productKey,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'credits' => (int) ($product['credits'] ?? 0),
            'status' => 'pending',
            'metadata' => array_filter([
                'country_routed' => true,
                'previous_subscription_id' => $previousSubscription?->external_subscription_id,
                'previous_plan_key' => $previousSubscription?->product_key,
                'plan_change_type' => $previousSubscription
                    ? ($this->planRank($productKey) > $this->planRank((string) $previousSubscription->product_key) ? 'upgrade' : 'downgrade')
                    : null,
            ]),
        ]);

        try {
            return match ($provider) {
                'paypal' => $this->paypal($order, $user, $product),
                'paymongo' => $this->paymongo($order, $user, $product),
            };
        } catch (\Throwable $exception) {
            $order->update([
                'status' => 'failed',
                'metadata' => array_merge($order->metadata ?? [], [
                    'checkout_error' => $exception->getMessage(),
                ]),
            ]);

            throw $exception;
        }
    }

    private function paypal(PaymentOrder $order, User $user, array $product): array
    {
        $client = $this->paypalClient();

        if ($order->product_type === 'plan') {
            return $this->paypalSubscription($client, $order, $user, $product);
        }

        return $this->paypalOrder($client, $order, $product);
    }

    private function paypalOrder(PendingRequest $client, PaymentOrder $order, array $product): array
    {
        $amount = number_format($order->amount_minor / 100, 2, '.', '');

        $response = $client->post('/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $order->reference,
                'custom_id' => $order->reference,
                'description' => $product['label'].' Cosmic Credits',
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => $amount,
                ],
            ]],
            'application_context' => [
                'brand_name' => config('app.name', 'Cosmic CMS'),
                'landing_page' => 'NO_PREFERENCE',
                'user_action' => 'PAY_NOW',
                'return_url' => route('payments.success').'?provider=paypal&order='.$order->reference,
                'cancel_url' => route('payments.cancel').'?provider=paypal&order='.$order->reference,
            ],
        ]);

        $approvalUrl = collect($response->json('links', []))
            ->firstWhere('rel', 'approve')['href'] ?? null;

        if (! $response->successful() || ! $response->json('id') || ! $approvalUrl) {
            throw new RuntimeException(
                $response->json('details.0.description')
                    ?? $response->json('message')
                    ?? 'PayPal could not create checkout.'
            );
        }

        $order->update(['external_checkout_id' => $response->json('id')]);

        return [
            'checkout_url' => $approvalUrl,
            'order_reference' => $order->reference,
        ];
    }

    private function paypalSubscription(
        PendingRequest $client,
        PaymentOrder $order,
        User $user,
        array $product,
    ): array {
        $planId = (string) config("payments.paypal.plan_ids.{$order->product_key}");

        if ($planId === '') {
            throw new RuntimeException(
                'The PayPal subscription plan ID for '.$order->product_key.' is not configured.'
            );
        }

        $name = trim((string) $user->name);
        $nameParts = preg_split('/\s+/', $name, 2) ?: [];

        $payload = [
            'plan_id' => $planId,
            'custom_id' => $order->reference,
            'subscriber' => [
                'name' => [
                    'given_name' => $nameParts[0] ?? 'Cosmic',
                    'surname' => $nameParts[1] ?? 'Customer',
                ],
                'email_address' => $user->email,
            ],
            'application_context' => [
                'brand_name' => config('app.name', 'Cosmic CMS'),
                'locale' => 'en-US',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'SUBSCRIBE_NOW',
                'return_url' => route('payments.success').'?provider=paypal&order='.$order->reference,
                'cancel_url' => route('payments.cancel').'?provider=paypal&order='.$order->reference,
            ],
        ];

        $response = $client->post('/v1/billing/subscriptions', $payload);

        $approvalUrl = collect($response->json('links', []))
            ->firstWhere('rel', 'approve')['href'] ?? null;

        if (! $response->successful() || ! $response->json('id') || ! $approvalUrl) {
            throw new RuntimeException(
                $response->json('details.0.description')
                    ?? $response->json('message')
                    ?? 'PayPal could not create the subscription.'
            );
        }

        $order->update([
            'external_checkout_id' => $response->json('id'),
            'external_subscription_id' => $response->json('id'),
        ]);

        return [
            'checkout_url' => $approvalUrl,
            'order_reference' => $order->reference,
        ];
    }

    private function planRank(string $planKey): int
    {
        return array_search($planKey, array_keys(config('payments.plans', [])), true) ?: 0;
    }

    private function paypalClient(): PendingRequest
    {
        $clientId = (string) config('payments.paypal.client_id');
        $clientSecret = (string) config('payments.paypal.client_secret');
        $baseUrl = rtrim((string) config('payments.paypal.base_url'), '/');

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('PayPal is not configured.');
        }

        $tokenResponse = Http::asForm()
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

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->withToken($accessToken)
            ->withHeaders([
                'PayPal-Request-Id' => (string) Str::uuid(),
            ]);
    }

    private function paymongo(PaymentOrder $order, User $user, array $product): array
    {
        $secret = (string) config('payments.paymongo.secret');

        if ($secret === '') {
            throw new RuntimeException('PayMongo is not configured.');
        }

        $response = Http::withBasicAuth($secret, '')
            ->acceptJson()
            ->post('https://api.paymongo.com/v1/checkout_sessions', [
                'data' => [
                    'attributes' => [
                        'billing' => [
                            'name' => $user->name,
                            'email' => $user->email,
                        ],
                        'line_items' => [[
                            'amount' => $order->amount_minor,
                            'currency' => 'PHP',
                            'name' => $product['label'].' Cosmic Credits',
                            'description' => number_format($product['credits']).' Cosmic Credits',
                            'quantity' => 1,
                        ]],
                        'payment_method_types' => config('payments.paymongo.methods'),
                        'reference_number' => $order->reference,
                        'success_url' => route('payments.success').'?provider=paymongo&order='.$order->reference,
                        'cancel_url' => route('payments.cancel').'?provider=paypal&order='.$order->reference,
                        'description' => 'Cosmic CMS credit top-up',
                        'send_email_receipt' => true,
                        'show_line_items' => true,
                    ],
                ],
            ]);

        $url = $response->json('data.attributes.checkout_url');

        if (! $response->successful() || ! $url) {
            throw new RuntimeException(
                $response->json('errors.0.detail', 'PayMongo could not create checkout.')
            );
        }

        $order->update(['external_checkout_id' => $response->json('data.id')]);

        return [
            'checkout_url' => $url,
            'order_reference' => $order->reference,
        ];
    }
}
