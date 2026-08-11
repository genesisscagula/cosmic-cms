<?php
$checkoutVisual = is_array($checkoutVisual ?? null) ? $checkoutVisual : [];
$checkoutVisualType = (string) ($checkoutVisual['type'] ?? 'commerce_checkout_split');
$checkoutVisualKey = match ($checkoutVisualType) {
    'commerce_checkout_express' => 'express',
    'commerce_checkout_classic' => 'classic',
    default => 'split',
};
$checkoutHeading = trim((string) ($checkoutVisual['heading'] ?? '')) ?: ($checkoutVisualKey === 'express' ? 'Complete your order' : ($checkoutVisualKey === 'classic' ? 'Checkout' : 'Secure checkout'));
$checkoutText = trim((string) ($checkoutVisual['text'] ?? '')) ?: 'Complete your details and review the order securely.';
$paymentLabel = trim((string) ($checkoutVisual['payment_label'] ?? '')) ?: ($checkoutVisualKey === 'express' ? 'Complete purchase' : ($checkoutVisualKey === 'split' ? 'Pay securely' : 'Continue to payment'));
$checkoutHelpText = trim((string) ($checkoutVisual['help_text'] ?? '')) ?: 'Shipping, tax, coupons and payment stay protected by the commerce runtime.';
?>
        @php
            $selectedCountry = $tax['country'] ?: request('country', '');
            $selectedRegion = $tax['region'] ?: request('region', '');
            $selectedShippingRate = data_get($shipping, 'selected.id');
            $canPay = $selectedCountry !== '' && (!$cart['requires_shipping'] || ($shipping['available'] && $selectedShippingRate)) && empty($cart['adjustments']);
        @endphp
        <section class="cart-shell commerce-runtime commerce-runtime--checkout checkout-visual--<?= e($checkoutVisualKey) ?>"><div class="wrap"><div class="runtime-intro"><div><div class="runtime-kicker">Checkout</div><h1><?= e($checkoutHeading) ?></h1><p><?= e($checkoutText) ?></p></div><a class="btn ghost runtime-continue" href="<?= e($cartUrl) ?>">Back to cart</a></div>@if($errors->any())<div class="flash error" role="alert"><strong>We couldn't continue to payment.</strong><div style="margin-top:4px">{{ $errors->first('payment') ?: $errors->first() }}</div><div style="font-size:12px;margin-top:5px;font-weight:600">Your cart is safe. Review the highlighted details and try again.</div></div>@endif
@if(!empty($cart['adjustments']))<div class="cart-warning"><strong>Review your updated cart before paying</strong>@foreach($cart['adjustments'] as $adjustment)<div>{{ $adjustment['message'] }}</div>@endforeach<div style="margin-top:10px"><a href="{{ $cartUrl }}" style="font-weight:850;text-decoration:underline">Review cart</a></div></div>@endif<div class="checkout-grid"><div class="checkout-card">
            <h2>Delivery & tax destination</h2>
            <form method="get" action="{{ $checkoutUrl }}" class="field-grid" style="margin-bottom:24px">
                <div class="field full"><label>Country / region</label><select name="country" onchange="this.form.submit()"><option value="">Select country / region</option>@foreach($countries as $code => $name)<option value="{{ $code }}" @selected($selectedCountry === $code)>{{ $name }}</option>@endforeach</select></div>
                <div class="field full"><label>State / province / region</label><input name="region" value="{{ $selectedRegion }}" placeholder="Optional code or name, e.g. CA"></div>
                <div class="field full"><label>Coupon / promo code</label><input name="coupon" value="{{ $coupon['code'] ?? request('coupon', '') }}" placeholder="Optional promo code" autocomplete="off">@if(!empty($coupon['error']))<div style="font-size:12px;color:#be123c;margin-top:6px">{{ $coupon['error'] }}</div>@elseif(!empty($coupon['applied']))<div style="font-size:12px;color:#047857;margin-top:6px">{{ $coupon['label'] }} applied — you save {{ $money($coupon['discount_minor']) }}.</div>@endif</div>
                @if($cart['requires_shipping'] && $selectedCountry !== '' && $shipping['available'])
                    <div class="field full"><label>Shipping method</label><select name="shipping_rate">@foreach($shipping['rates'] as $rate)<option value="{{ $rate['id'] }}" @selected((int) $selectedShippingRate === (int) $rate['id'])>{{ $rate['name'] }} — {{ $rate['amount_minor'] === 0 ? 'Free' : $money($rate['amount_minor']) }}</option>@endforeach</select></div>
                @endif
                <div class="field full"><button class="btn ghost" type="submit">Recalculate totals</button></div>
            </form>
            @if($cart['requires_shipping'] && $selectedCountry !== '' && !$shipping['available'])
                <div class="notice" style="margin:0 0 20px">Shipping is not available to this country yet. Choose another country or contact the store.</div>
            @elseif($cart['requires_shipping'] && ($shipping['zone'] ?? null))
                <div class="muted" style="margin:-10px 0 20px;font-size:12px">Shipping zone: {{ $shipping['zone']->name }}</div>
            @endif

            <h2>Customer details</h2>
            <form method="post" action="{{ $checkoutPayPalUrl }}" class="field-grid" data-commerce-payment-form data-commerce-checkout-details-form>@csrf
                <input type="hidden" name="checkout_idempotency_key" value="{{ $checkoutIdempotencyKey }}">
                <input type="hidden" name="country" value="{{ $selectedCountry }}">
                <input type="hidden" name="region" value="{{ $selectedRegion }}">
                @if(!empty($coupon['applied']))<input type="hidden" name="coupon" value="{{ $coupon['code'] }}">@endif
                @if($selectedShippingRate)<input type="hidden" name="shipping_rate" value="{{ $selectedShippingRate }}">@endif
                <div class="field"><label>First name</label><input name="first_name" type="text" autocomplete="given-name" value="{{ old('first_name') }}" required>@error('first_name')<span class="field-error">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Last name</label><input name="last_name" type="text" autocomplete="family-name" value="{{ old('last_name') }}" required>@error('last_name')<span class="field-error">{{ $message }}</span>@enderror</div>
                <div class="field full"><label>Email</label><input name="email" type="email" autocomplete="email" value="{{ old('email') }}" placeholder="you@example.com" required>@error('email')<span class="field-error">{{ $message }}</span>@enderror</div>
                @if($cart['requires_shipping'])
                    <div class="field full"><label>Street address</label><input name="address1" type="text" autocomplete="street-address" value="{{ old('address1') }}" required>@error('address1')<span class="field-error">{{ $message }}</span>@enderror</div>
                    <div class="field"><label>City</label><input name="city" type="text" autocomplete="address-level2" value="{{ old('city') }}" required>@error('city')<span class="field-error">{{ $message }}</span>@enderror</div>
                    <div class="field"><label>Postal code</label><input name="postal_code" type="text" autocomplete="postal-code" value="{{ old('postal_code') }}" required>@error('postal_code')<span class="field-error">{{ $message }}</span>@enderror</div>
                @endif
                <div class="field full">
                    <label class="remember-checkout-row" style="display:flex;align-items:flex-start;gap:10px;font-weight:750;cursor:pointer">
                        <input type="checkbox" data-commerce-remember-details style="width:18px;height:18px;min-height:18px;margin-top:2px;flex:0 0 auto">
                        <span>
                            <span style="display:block">Remember my details on this device</span>
                            <small class="muted" style="display:block;margin-top:3px;font-weight:600;line-height:1.45">Saves only your checkout contact and address details in this browser. Payment details are never stored.</small>
                        </span>
                    </label>
                    <button type="button" class="btn ghost" data-commerce-forget-details style="display:none;width:max-content;margin-top:9px;padding:8px 12px;min-height:auto;font-size:12px">Forget saved details</button>
                    <div class="muted" data-commerce-remember-status style="font-size:12px;margin-top:7px" aria-live="polite"></div>
                </div>
                <div class="field full"><div class="notice" style="margin:6px 0 0"><?= e($checkoutHelpText) ?> Product prices, shipping, and tax are recalculated on the server before PayPal checkout.</div></div>
                <div class="field full"><button class="btn" type="submit" style="width:100%;font-size:16px" @disabled(!$canPay || !config('payments.paypal.enabled'))>{{ config('payments.paypal.enabled') ? $paymentLabel : 'PayPal is not configured' }}</button>@if(!$canPay)<div class="muted" style="font-size:12px;margin-top:7px">Choose a valid destination{{ $cart['requires_shipping'] ? ' and shipping method' : '' }} before payment.</div>@endif</div>
            </form>
        </div><aside class="summary-card"><div class="summary-eyebrow">Order summary</div><h2>Your order</h2>@foreach($cart['items'] as $line)<div class="summary-row"><span>{{ $line['product']->title }} @if($line['option_label'])<small class="muted">({{ $line['option_label'] }})</small>@endif × {{ $line['quantity'] }}</span><strong>{{ $money($line['line_total_minor']) }}</strong></div>@endforeach<div class="summary-row"><span>Subtotal</span><strong>{{ $money($cart['subtotal_minor']) }}</strong></div>@if(!empty($coupon['applied']))<div class="summary-row"><span>Discount <small class="muted">({{ $coupon['code'] }})</small></span><strong>-{{ $money($coupon['discount_minor']) }}</strong></div>@endif
<?php if ($cart['requires_shipping']): ?>
    <div class="summary-row">
        <span>Shipping</span>
        <strong>
            <?php if ($selectedCountry === ''): ?>
                Select country
            <?php elseif (! $shipping['available']): ?>
                Unavailable
            <?php else: ?>
                <?= e($shippingMinor === 0 ? 'Free' : $money($shippingMinor)) ?>
            <?php endif; ?>
        </strong>
    </div>
<?php endif; ?>
<?php if ($website->commerceSetting?->tax_enabled): ?>
    <div class="summary-row">
        <span>Tax <?php if ($tax['prices_include_tax']): ?><small class="muted">(included)</small><?php endif; ?></span>
        <strong><?= e($selectedCountry === '' ? 'Select country' : $money($tax['tax_minor'])) ?></strong>
    </div>
    <?php if (! empty($tax['rule_names'])): ?>
        <div class="muted" style="font-size:12px;padding-bottom:8px"><?= e(implode(', ', $tax['rule_names'])) ?></div>
    <?php endif; ?>
<?php endif; ?>
<div class="summary-row summary-total"><span>Total</span><span>{{ $money($checkoutTotalMinor) }}</span></div><a class="btn ghost" style="display:grid;place-items:center;margin-top:16px" href="{{ $cartUrl }}">Back to cart</a></aside></div></div></section>
