<?php
$cartVisual = is_array($cartVisual ?? null) ? $cartVisual : [];
$cartVisualType = (string) ($cartVisual['type'] ?? 'commerce_cart_split');
$cartVisualKey = match ($cartVisualType) {
    'commerce_cart_compact' => 'compact',
    'commerce_cart_classic' => 'classic',
    default => 'split',
};
$cartHeading = trim((string) ($cartVisual['heading'] ?? '')) ?: ($cartVisualKey === 'compact' ? 'Your bag' : ($cartVisualKey === 'classic' ? 'Your cart' : 'Review your bag'));
$cartText = trim((string) ($cartVisual['text'] ?? '')) ?: 'Review your items before checkout.';
$checkoutLabel = trim((string) ($cartVisual['checkout_label'] ?? '')) ?: ($cartVisualKey === 'split' ? 'Secure checkout' : 'Proceed to checkout');
$continueLabel = trim((string) ($cartVisual['continue_label'] ?? '')) ?: 'Continue shopping';
?>
<section class="cart-shell commerce-runtime commerce-runtime--cart cart-visual--<?= e($cartVisualKey) ?>">
    <div class="wrap">
        <div class="runtime-intro">
            <div><div class="runtime-kicker">Cart</div><h1><?= e($cartHeading) ?></h1><p><?= e($cartText) ?></p></div>
            <a class="btn ghost runtime-continue" href="<?= e($shopUrl) ?>"><?= e($continueLabel) ?></a>
        </div>
        @if(!empty($cart['adjustments']))
            <div class="cart-warning"><strong>Your cart was updated</strong>@foreach($cart['adjustments'] as $adjustment)<div>{{ $adjustment['message'] }}</div>@endforeach</div>
        @endif
        @if($cart['count'] > 0)
            <div class="cart-layout">
                <div class="cart-panel">
                    @foreach($cart['items'] as $line)
                        <div class="cart-line">
                            <div class="cart-image">@if($line['image_url'])<img src="{{ $line['image_url'] }}" alt="{{ $line['product']->title }}">@endif</div>
                            <div class="cart-line-copy">
                                <a class="cart-title" href="{{ $productUrl($line['product']) }}">{{ $line['product']->title }}</a>
                                @if($line['option_label'])<div class="muted">{{ $line['option_label'] }}</div>@endif
                                <div class="cart-unit-price">{{ $money($line['unit_price_minor']) }}</div>
                                <div class="low-stock">{{ $line['availability_label'] ?? '' }}</div>
                                <div class="cart-actions">
                                    <form method="post" action="{{ $cartUpdateUrl }}">@csrf<input type="hidden" name="line" value="{{ $line['key'] }}"><input class="input qty" type="number" name="quantity" min="0" max="{{ $line['max_quantity'] ?? 99 }}" value="{{ $line['quantity'] }}"><button class="btn ghost" type="submit">Update</button></form>
                                    <form method="post" action="{{ $cartRemoveUrl }}">@csrf<input type="hidden" name="line" value="{{ $line['key'] }}"><button class="btn ghost" type="submit">Remove</button></form>
                                </div>
                            </div>
                            <strong class="cart-line-total">{{ $money($line['line_total_minor']) }}</strong>
                        </div>
                    @endforeach
                </div>
                <aside class="summary-card">
                    <div class="summary-eyebrow">Order summary</div>
                    <h2>Ready when you are</h2>
                    <div class="summary-row"><span>Items</span><strong>{{ $cart['count'] }}</strong></div>
                    <div class="summary-row summary-total"><span>Subtotal</span><span>{{ $money($cart['subtotal_minor']) }}</span></div>
                    <p class="muted">Shipping and taxes are calculated securely in checkout when enabled.</p>
                    <a class="btn runtime-primary-action" href="{{ $checkoutUrl }}"><?= e($checkoutLabel) ?></a>
                </aside>
            </div>
        @else
            <div class="empty runtime-empty"><strong>Your cart is empty</strong><div style="margin:8px 0 18px">Browse the shop and add something you like.</div><a class="btn" href="{{ $shopUrl }}"><?= e($continueLabel) ?></a></div>
        @endif
    </div>
</section>
