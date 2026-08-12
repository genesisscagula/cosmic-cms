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
        <?php if(!empty($cart['adjustments'])): ?>
            <div class="cart-warning"><strong>Your cart was updated</strong><?php $__currentLoopData = $cart['adjustments']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $adjustment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div><?php echo e($adjustment['message']); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div>
        <?php endif; ?>
        <?php if($cart['count'] > 0): ?>
            <div class="cart-layout">
                <div class="cart-panel">
                    <?php $__currentLoopData = $cart['items']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $line): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="cart-line">
                            <div class="cart-image"><?php if($line['image_url']): ?><img src="<?php echo e($assetUrl($line['image_url'])); ?>" alt="<?php echo e($line['product']->title); ?>"><?php endif; ?></div>
                            <div class="cart-line-copy">
                                <a class="cart-title" href="<?php echo e($productUrl($line['product'])); ?>"><?php echo e($line['product']->title); ?></a>
                                <?php if($line['option_label']): ?><div class="muted"><?php echo e($line['option_label']); ?></div><?php endif; ?>
                                <div class="cart-unit-price"><?php echo e($money($line['unit_price_minor'])); ?></div>
                                <div class="low-stock"><?php echo e($line['availability_label'] ?? ''); ?></div>
                                <div class="cart-actions">
                                    <form method="post" action="<?php echo e($cartUpdateUrl); ?>"><?php echo csrf_field(); ?><input type="hidden" name="line" value="<?php echo e($line['key']); ?>"><input class="input qty" type="number" name="quantity" min="0" max="<?php echo e($line['max_quantity'] ?? 99); ?>" value="<?php echo e($line['quantity']); ?>"><button class="btn ghost" type="submit">Update</button></form>
                                    <form method="post" action="<?php echo e($cartRemoveUrl); ?>"><?php echo csrf_field(); ?><input type="hidden" name="line" value="<?php echo e($line['key']); ?>"><button class="btn ghost" type="submit">Remove</button></form>
                                </div>
                            </div>
                            <strong class="cart-line-total"><?php echo e($money($line['line_total_minor'])); ?></strong>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
                <aside class="summary-card">
                    <div class="summary-eyebrow">Order summary</div>
                    <h2>Ready when you are</h2>
                    <div class="summary-row"><span>Items</span><strong><?php echo e($cart['count']); ?></strong></div>
                    <div class="summary-row summary-total"><span>Subtotal</span><span><?php echo e($money($cart['subtotal_minor'])); ?></span></div>
                    <p class="muted">Shipping and taxes are calculated securely in checkout when enabled.</p>
                    <a class="btn runtime-primary-action" href="<?php echo e($checkoutUrl); ?>"><?= e($checkoutLabel) ?></a>
                </aside>
            </div>
        <?php else: ?>
            <div class="empty runtime-empty"><strong>Your cart is empty</strong><div style="margin:8px 0 18px">Browse the shop and add something you like.</div><a class="btn" href="<?php echo e($shopUrl); ?>"><?= e($continueLabel) ?></a></div>
        <?php endif; ?>
    </div>
</section>
<?php /**PATH C:\xampp\htdocs\my-custom-cms\resources\views/commerce/storefront-modes/cart.blade.php ENDPATH**/ ?>