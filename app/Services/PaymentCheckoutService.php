<?php

namespace App\Services;

use App\Cosmic\Pricing\CreditPackageRegistry;
use App\Models\PaymentOrder;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentCheckoutService
{
    public function create(User $user, string $provider, string $productType, string $productKey): array
    {
        if ($productType === 'credits') {
            $product = CreditPackageRegistry::get($productKey);
        } elseif ($productType === 'plan') {
            $product = config("payments.plans.{$productKey}");
        } else {
            $product = null;
        }

        if (! is_array($product)) {
            throw new RuntimeException('The selected product is unavailable.');
        }
        if ($provider === 'paymongo' && $productType !== 'credits') {
            throw new RuntimeException('Monthly plans currently use Stripe. PayMongo is available for credit top-ups.');
        }

        $currency = $provider === 'paymongo' ? 'PHP' : 'USD';
        $amountMinor = (int) (($provider === 'paymongo' ? $product['price_php'] : $product['price_usd']) * 100);
        $order = PaymentOrder::create([
            'reference' => (string) Str::uuid(),
            'user_id' => $user->id,
            'provider' => $provider,
            'product_type' => $productType,
            'product_key' => $productKey,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'credits' => (int) $product['credits'],
            'status' => 'pending',
        ]);

        try {
            return $provider === 'stripe'
                ? $this->stripe($order, $user, $product)
                : $this->paymongo($order, $user, $product);
        } catch (\Throwable $exception) {
            $order->update(['status' => 'failed', 'metadata' => ['checkout_error' => $exception->getMessage()]]);
            throw $exception;
        }
    }

    private function stripe(PaymentOrder $order, User $user, array $product): array
    {
        $secret = (string) config('payments.stripe.secret');
        if ($secret === '') {
            throw new RuntimeException('Stripe is not configured.');
        }

        $mode = $order->product_type === 'plan' ? 'subscription' : 'payment';
        $payload = [
            'mode' => $mode,
            'customer_email' => $user->email,
            'client_reference_id' => $order->reference,
            'success_url' => route('payments.success').'?provider=stripe&order='.$order->reference,
            'cancel_url' => route('credits.index').'?payment=cancelled',
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => $order->amount_minor,
                    'product_data' => ['name' => $product['label'].($order->product_type === 'credits' ? ' Cosmic Credits' : ' Plan')],
                ],
            ]],
            'metadata' => ['order_reference' => $order->reference, 'product_type' => $order->product_type],
        ];
        if ($mode === 'subscription') {
            $payload['line_items'][0]['price_data']['recurring'] = ['interval' => 'month'];
            $payload['subscription_data'] = ['metadata' => ['order_reference' => $order->reference]];
        }

        $response = Http::asForm()->withBasicAuth($secret, '')->post('https://api.stripe.com/v1/checkout/sessions', $payload);
        if (! $response->successful() || ! $response->json('url')) {
            throw new RuntimeException($response->json('error.message', 'Stripe could not create checkout.'));
        }
        $order->update(['external_checkout_id' => $response->json('id')]);
        return ['checkout_url' => $response->json('url'), 'order_reference' => $order->reference];
    }

    private function paymongo(PaymentOrder $order, User $user, array $product): array
    {
        $secret = (string) config('payments.paymongo.secret');
        if ($secret === '') {
            throw new RuntimeException('PayMongo is not configured.');
        }

        $response = Http::withBasicAuth($secret, '')->acceptJson()->post('https://api.paymongo.com/v1/checkout_sessions', [
            'data' => ['attributes' => [
                'billing' => ['name' => $user->name, 'email' => $user->email],
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
                'cancel_url' => route('credits.index').'?payment=cancelled',
                'description' => 'Cosmic CMS credit top-up',
                'send_email_receipt' => true,
                'show_line_items' => true,
            ]],
        ]);
        $url = $response->json('data.attributes.checkout_url');
        if (! $response->successful() || ! $url) {
            throw new RuntimeException($response->json('errors.0.detail', 'PayMongo could not create checkout.'));
        }
        $order->update(['external_checkout_id' => $response->json('data.id')]);
        return ['checkout_url' => $url, 'order_reference' => $order->reference];
    }
}
