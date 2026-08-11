<?php

namespace App\Services;

use App\Models\CommerceOrder;
use App\Models\CommerceProduct;
use App\Models\CommerceProductVariant;
use App\Models\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class CommerceOrderService
{
    public function __construct(
        private readonly CommerceCartService $cart,
        private readonly CommerceShippingService $shipping,
        private readonly CommerceTaxService $tax,
        private readonly CommerceCouponService $coupons,
        private readonly CommerceInventoryService $inventory,
    ) {}

    public function createPending(Website $website, array $customer, string $country, string $region, ?int $shippingRateId, ?string $couponCode = null, ?string $idempotencyKey = null): CommerceOrder
    {
        $cart = $this->cart->summary($website);
        if ($cart['count'] < 1) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }

        if (! empty($cart['adjustments'])) {
            throw ValidationException::withMessages([
                'cart' => 'Your cart changed because of current price or availability. Review the updated cart before paying.',
            ]);
        }

        $coupon = $this->coupons->quote($website, $cart, $couponCode, $customer['email'] ?? null, filled($couponCode));
        $discountedCart = $coupon['cart'];

        $shipping = $cart['requires_shipping']
            ? $this->shipping->quote($website, $country, $discountedCart['subtotal_minor'], $shippingRateId)
            : ['available' => true, 'selected' => ['id' => null, 'name' => 'Digital delivery', 'amount_minor' => 0], 'zone' => null];

        if ($cart['requires_shipping'] && (! $shipping['available'] || ! data_get($shipping, 'selected'))) {
            throw ValidationException::withMessages(['country' => 'Shipping is not available for this destination.']);
        }

        $shippingMinor = (int) data_get($shipping, 'selected.amount_minor', 0);
        $tax = $this->tax->quote($website, $discountedCart, $country, $region, $shippingMinor);
        $currency = strtoupper((string) ($website->commerceSetting?->currency ?: config('cosmic-commerce.default_currency', 'USD')));
        $idempotencyKey = filled($idempotencyKey) ? strtolower(trim((string) $idempotencyKey)) : (string) Str::uuid();
        $checkoutFingerprint = $this->checkoutFingerprint($website, $cart, $coupon, $shipping, $tax, $country, $region, $currency);

        return DB::transaction(function () use ($website, $customer, $country, $region, $shipping, $shippingMinor, $tax, $cart, $coupon, $currency, $idempotencyKey, $checkoutFingerprint) {
            $existing = CommerceOrder::query()
                ->where('website_id', $website->id)
                ->where('checkout_idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if (! hash_equals((string) $existing->checkout_fingerprint, $checkoutFingerprint)) {
                    throw ValidationException::withMessages([
                        'cart' => 'Checkout details changed after this payment attempt started. Review the checkout totals and try again.',
                    ]);
                }

                // A retry of the same browser checkout reuses the same local order.
                // Customer/contact fields may be corrected while the monetary quote
                // stays identical; PayPal does not receive these NO_SHIPPING fields.
                if (in_array($existing->payment_status, ['pending', 'cancelled'], true)) {
                    $existing->forceFill([
                        'status' => 'pending',
                        'payment_status' => 'pending',
                        'cancelled_at' => null,
                        'payment_attempted_at' => now(),
                        'payment_recovery_next_attempt_at' => now()->addMinutes(max(1, (int) config('cosmic-commerce.payment_recovery.older_than_minutes', 5))),
                        'payment_attention_required_at' => null,
                        'checkout_expires_at' => now()->addHours(max(1, (int) config('cosmic-commerce.payment_recovery.expire_after_hours', 24))),
                        'customer_email' => $customer['email'],
                        'customer_first_name' => $customer['first_name'],
                        'customer_last_name' => $customer['last_name'],
                        'billing_address' => [
                            'country' => $country,
                            'region' => $region,
                            'address1' => $customer['address1'] ?? null,
                            'city' => $customer['city'] ?? null,
                            'postal_code' => $customer['postal_code'] ?? null,
                        ],
                        'shipping_address' => $cart['requires_shipping'] ? [
                            'country' => $country,
                            'region' => $region,
                            'address1' => $customer['address1'],
                            'city' => $customer['city'],
                            'postal_code' => $customer['postal_code'],
                        ] : null,
                    ])->save();
                }

                $existing->load('items');
                $this->inventory->reserveForOrder($existing);
                $metadata = is_array($existing->metadata) ? $existing->metadata : [];
                $metadata['inventory_reserved_at'] = now()->toIso8601String();
                $metadata['inventory_reservation_expires_at'] = $existing->checkout_expires_at?->toIso8601String();
                $existing->forceFill(['metadata' => $metadata])->save();
                return $existing;
            }

            $order = CommerceOrder::create([
                'website_id' => $website->id,
                'commerce_coupon_id' => $coupon['coupon']?->id,
                'coupon_code' => $coupon['applied'] ? $coupon['code'] : null,
                'public_id' => (string) Str::uuid(),
                'order_number' => $this->orderNumber(),
                'status' => 'pending',
                'payment_status' => 'pending',
                'payment_provider' => 'paypal',
                'checkout_idempotency_key' => $idempotencyKey,
                'checkout_fingerprint' => $checkoutFingerprint,
                'payment_attempted_at' => now(),
                'checkout_expires_at' => now()->addHours(max(1, (int) config('cosmic-commerce.payment_recovery.expire_after_hours', 24))),
                'payment_recovery_attempts' => 0,
                'payment_recovery_last_attempt_at' => null,
                'payment_recovery_next_attempt_at' => now()->addMinutes(max(1, (int) config('cosmic-commerce.payment_recovery.older_than_minutes', 5))),
                'payment_attention_required_at' => null,
                'currency' => $currency,
                'subtotal_minor' => (int) $cart['subtotal_minor'],
                'discount_minor' => (int) $coupon['discount_minor'],
                'shipping_minor' => $shippingMinor,
                'tax_minor' => (int) $tax['tax_minor'],
                'total_minor' => (int) $tax['total_minor'],
                'prices_include_tax' => (bool) $tax['prices_include_tax'],
                'shipping_method' => (string) data_get($shipping, 'selected.name', 'Digital delivery'),
                'customer_email' => $customer['email'],
                'customer_first_name' => $customer['first_name'],
                'customer_last_name' => $customer['last_name'],
                'billing_address' => [
                    'country' => $country,
                    'region' => $region,
                    'address1' => $customer['address1'] ?? null,
                    'city' => $customer['city'] ?? null,
                    'postal_code' => $customer['postal_code'] ?? null,
                ],
                'shipping_address' => $cart['requires_shipping'] ? [
                    'country' => $country,
                    'region' => $region,
                    'address1' => $customer['address1'],
                    'city' => $customer['city'],
                    'postal_code' => $customer['postal_code'],
                ] : null,
                'tax_snapshot' => [
                    'country' => $country,
                    'region' => $region,
                    'rule_names' => $tax['rule_names'],
                    'shipping_tax_minor' => $tax['shipping_tax_minor'],
                    'lines' => $tax['lines']->map(fn ($line) => [
                        'key' => $line['key'],
                        'tax_class' => $line['tax_class'],
                        'rate_basis_points' => $line['rate_basis_points'],
                        'tax_minor' => $line['tax_minor'],
                    ])->values()->all(),
                ],
                'metadata' => [
                    'shipping_rate_id' => data_get($shipping, 'selected.id'),
                    'shipping_zone_id' => data_get($shipping, 'zone.id'),
                    'cart_count' => $cart['count'],
                    'coupon_label' => $coupon['label'],
                    'coupon_eligible_subtotal_minor' => $coupon['eligible_subtotal_minor'],
                ],
            ]);

            $taxByKey = $tax['lines']->keyBy('key');
            foreach ($cart['items'] as $line) {
                $lineDiscount = (int) ($coupon['allocations'][$line['key']] ?? 0);
                $discountedLineSubtotal = max(0, (int) $line['line_total_minor'] - $lineDiscount);
                $lineTax = (int) data_get($taxByKey->get($line['key']), 'tax_minor', 0);
                $lineTotal = $tax['prices_include_tax']
                    ? $discountedLineSubtotal
                    : $discountedLineSubtotal + $lineTax;

                $order->items()->create([
                    'commerce_product_id' => $line['product']->id,
                    'commerce_product_variant_id' => $line['variant']?->id,
                    'product_public_id' => $line['product']->public_id,
                    'variant_public_id' => $line['variant']?->public_id,
                    'title' => $line['product']->title,
                    'sku' => $line['sku'],
                    'option_label' => $line['option_label'],
                    'image_url' => $line['image_url'],
                    'quantity' => (int) $line['quantity'],
                    'unit_price_minor' => (int) $line['unit_price_minor'],
                    'line_subtotal_minor' => $discountedLineSubtotal,
                    'tax_minor' => $lineTax,
                    'line_total_minor' => $lineTotal,
                    'snapshot' => [
                        'product_type' => $line['product']->type,
                        'taxable' => (bool) $line['product']->taxable,
                        'tax_class' => $line['product']->tax_class,
                        'original_line_subtotal_minor' => (int) $line['line_total_minor'],
                        'discount_minor' => $lineDiscount,
                    ],
                ]);
            }

            $order->load('items');
            $this->inventory->reserveForOrder($order);
            $metadata = is_array($order->metadata) ? $order->metadata : [];
            $metadata['inventory_reserved_at'] = now()->toIso8601String();
            $metadata['inventory_reservation_expires_at'] = $order->checkout_expires_at?->toIso8601String();
            $order->forceFill(['metadata' => $metadata])->save();
            return $order;
        }, 3);
    }

    public function releasePendingReservations(CommerceOrder $order): int
    {
        return DB::transaction(function () use ($order) {
            $locked = CommerceOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->payment_status, ['pending', 'cancelled'], true)) return 0;
            $released = $this->inventory->releaseReservations($locked);
            if ($released > 0) {
                $metadata = is_array($locked->metadata) ? $locked->metadata : [];
                $metadata['inventory_reservations_released_at'] = now()->toIso8601String();
                $locked->forceFill(['metadata' => $metadata])->save();
            }
            return $released;
        });
    }

    public function cancelPending(CommerceOrder $order, string $reason = 'PayPal checkout was cancelled by the customer.'): CommerceOrder
    {
        return DB::transaction(function () use ($order, $reason) {
            $locked = CommerceOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->payment_status, ['paid', 'partially_refunded', 'refunded'], true)) return $locked;
            if (! in_array($locked->payment_status, ['pending', 'cancelled'], true)) return $locked;

            $this->inventory->releaseReservations($locked);
            $metadata = is_array($locked->metadata) ? $locked->metadata : [];
            $metadata['payment_cancel_reason'] = $reason;
            $metadata['inventory_reservations_released_at'] = now()->toIso8601String();
            $locked->forceFill([
                'status' => 'cancelled',
                'payment_status' => 'cancelled',
                'cancelled_at' => $locked->cancelled_at ?: now(),
                'metadata' => $metadata,
            ])->save();
            return $locked;
        });
    }

    public function linkExternalCheckout(CommerceOrder $order, string $externalCheckoutId): CommerceOrder
    {
        $externalCheckoutId = trim($externalCheckoutId);
        if ($externalCheckoutId === '') {
            throw new RuntimeException('PayPal did not return a checkout ID.');
        }

        return DB::transaction(function () use ($order, $externalCheckoutId) {
            $locked = CommerceOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (filled($locked->external_checkout_id)) {
                if (! hash_equals((string) $locked->external_checkout_id, $externalCheckoutId)) {
                    throw new RuntimeException('This checkout attempt is already linked to another PayPal order.');
                }
                return $locked;
            }

            $alreadyLinked = CommerceOrder::query()
                ->where('external_checkout_id', $externalCheckoutId)
                ->whereKeyNot($locked->id)
                ->exists();
            if ($alreadyLinked) {
                throw new RuntimeException('This PayPal checkout is already linked to another order.');
            }

            $locked->forceFill(['external_checkout_id' => $externalCheckoutId])->save();
            return $locked;
        });
    }

    public function markPaid(CommerceOrder $order, string $externalPaymentId, string $source = 'return'): CommerceOrder
    {
        return DB::transaction(function () use ($order, $externalPaymentId, $source) {
            $locked = CommerceOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->payment_status, ['paid', 'partially_refunded', 'refunded'], true)) {
                if (filled($externalPaymentId) && filled($locked->external_payment_id)
                    && ! hash_equals((string) $locked->external_payment_id, $externalPaymentId)) {
                    throw new RuntimeException('A different PayPal capture is already attached to this order.');
                }
                return $locked->load('items');
            }
            if (! in_array($locked->payment_status, ['pending', 'cancelled'], true)) {
                throw new RuntimeException('This order can no longer be paid.');
            }

            if ($externalPaymentId === '') {
                throw new RuntimeException('PayPal did not return a capture ID.');
            }

            $captureUsedElsewhere = CommerceOrder::query()
                ->where('external_payment_id', $externalPaymentId)
                ->whereKeyNot($locked->id)
                ->exists();
            if ($captureUsedElsewhere) {
                throw new RuntimeException('This PayPal capture is already attached to another order.');
            }

            $inventoryIssue = $this->inventory->deductForPaidOrder($locked);

            $metadata = is_array($locked->metadata) ? $locked->metadata : [];
            if ($inventoryIssue) $metadata['inventory_attention_required'] = true;
            $metadata['payment_completion_source'] = $source;
            $metadata['payment_completed_at'] = now()->toIso8601String();

            $locked->forceFill([
                'status' => $inventoryIssue ? 'on_hold' : 'processing',
                'payment_status' => 'paid',
                'external_payment_id' => $externalPaymentId,
                'paid_at' => now(),
                'payment_recovered_at' => $source === 'recovery' ? now() : $locked->payment_recovered_at,
                'payment_recovery_next_attempt_at' => null,
                'payment_attention_required_at' => null,
                'payment_recovery_last_error' => null,
                'metadata' => $metadata,
            ])->save();

            if ($locked->commerce_coupon_id && (int) $locked->discount_minor > 0) {
                \App\Models\CommerceCouponUsage::query()->firstOrCreate(
                    ['commerce_order_id' => $locked->id],
                    [
                        'commerce_coupon_id' => $locked->commerce_coupon_id,
                        'website_id' => $locked->website_id,
                        'customer_email' => mb_strtolower(trim((string) $locked->customer_email)),
                        'discount_minor' => (int) $locked->discount_minor,
                        'used_at' => now(),
                    ]
                );
            }

            return $locked->load('items');
        });
    }

    public function recordRecoveryAttempt(CommerceOrder $order, ?string $error = null): CommerceOrder
    {
        return DB::transaction(function () use ($order, $error) {
            $locked = CommerceOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $attempts = min(65535, ((int) $locked->payment_recovery_attempts) + 1);
            $backoff = array_values(array_filter((array) config('cosmic-commerce.payment_recovery.backoff_minutes', [5, 10, 20, 30, 60, 120]), fn ($value) => (int) $value > 0));
            $delay = (int) ($backoff[min(max(0, $attempts - 1), max(0, count($backoff) - 1))] ?? 60);
            $attentionAfter = max(1, (int) config('cosmic-commerce.payment_recovery.attention_after_attempts', 6));

            $locked->forceFill([
                'payment_recovery_attempts' => $attempts,
                'payment_recovery_last_attempt_at' => now(),
                'payment_recovery_next_attempt_at' => now()->addMinutes(max(1, $delay)),
                'payment_attention_required_at' => $attempts >= $attentionAfter
                    ? ($locked->payment_attention_required_at ?: now())
                    : null,
                'payment_recovery_last_error' => $error ? mb_substr($error, 0, 2000) : null,
            ])->save();
            return $locked;
        });
    }

    public function resetRecoverySchedule(CommerceOrder $order): CommerceOrder
    {
        return DB::transaction(function () use ($order) {
            $locked = CommerceOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->payment_status, ['pending', 'cancelled'], true)) {
                return $locked;
            }

            $locked->forceFill([
                'payment_recovery_next_attempt_at' => now(),
                'payment_attention_required_at' => null,
            ])->save();

            return $locked;
        });
    }

    public function expirePending(CommerceOrder $order, string $reason = 'Checkout expired before payment completed.'): CommerceOrder
    {
        return DB::transaction(function () use ($order, $reason) {
            $locked = CommerceOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->payment_status, ['paid', 'partially_refunded', 'refunded'], true)) {
                return $locked;
            }

            if (! in_array($locked->payment_status, ['pending', 'cancelled'], true)) {
                return $locked;
            }

            $this->inventory->releaseReservations($locked);
            $metadata = is_array($locked->metadata) ? $locked->metadata : [];
            $metadata['payment_expiry_reason'] = $reason;
            $metadata['inventory_reservations_released_at'] = now()->toIso8601String();
            $metadata['payment_expired_at'] = now()->toIso8601String();

            $locked->forceFill([
                'status' => 'cancelled',
                'payment_status' => 'cancelled',
                'cancelled_at' => $locked->cancelled_at ?: now(),
                'checkout_expires_at' => null,
                'payment_recovery_next_attempt_at' => null,
                'payment_attention_required_at' => null,
                'payment_recovery_last_error' => null,
                'metadata' => $metadata,
            ])->save();

            return $locked;
        });
    }

    private function checkoutFingerprint(
        Website $website,
        array $cart,
        array $coupon,
        array $shipping,
        array $tax,
        string $country,
        string $region,
        string $currency,
    ): string {
        $items = collect($cart['items'])->map(fn ($line) => [
            'key' => (string) $line['key'],
            'product' => (string) $line['product']->public_id,
            'variant' => (string) ($line['variant']?->public_id ?? ''),
            'quantity' => (int) $line['quantity'],
            'unit_price_minor' => (int) $line['unit_price_minor'],
            'line_total_minor' => (int) $line['line_total_minor'],
            'discount_minor' => (int) ($coupon['allocations'][$line['key']] ?? 0),
        ])->sortBy('key')->values()->all();

        $payload = [
            'website_id' => (int) $website->id,
            'currency' => $currency,
            'country' => $country,
            'region' => $region,
            'items' => $items,
            'subtotal_minor' => (int) $cart['subtotal_minor'],
            'coupon_id' => (int) ($coupon['coupon']?->id ?? 0),
            'coupon_code' => (string) ($coupon['code'] ?? ''),
            'discount_minor' => (int) $coupon['discount_minor'],
            'shipping_rate_id' => (int) data_get($shipping, 'selected.id', 0),
            'shipping_minor' => (int) data_get($shipping, 'selected.amount_minor', 0),
            'tax_minor' => (int) $tax['tax_minor'],
            'total_minor' => (int) $tax['total_minor'],
            'prices_include_tax' => (bool) $tax['prices_include_tax'],
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function orderNumber(): string
    {
        do {
            $number = 'CC-'.now()->format('Ymd').'-'.strtoupper(Str::random(8));
        } while (CommerceOrder::query()->where('order_number', $number)->exists());
        return $number;
    }
}
