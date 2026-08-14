<?php
    $range = $priceRange($product);
    $simpleAvailable = null;
    $simpleAvailability = 'Available';

    if (!$product->isVariable()) {
        if ($product->track_inventory && !$product->allow_backorders) {
            $simpleAvailable = max(0, (int) ($product->stock_quantity ?? 0));
            $threshold = max(0, (int) ($product->low_stock_threshold ?? 0));
            $simpleAvailability = $simpleAvailable <= $threshold && $simpleAvailable > 0
                ? 'Only '.$simpleAvailable.' left'
                : ($simpleAvailable > 0 ? 'In stock' : 'Currently unavailable');
        } elseif ($product->allow_backorders && (int) ($product->stock_quantity ?? 0) <= 0) {
            $simpleAvailability = 'Available on backorder';
        } else {
            $simpleAvailability = $product->isPurchasable() ? 'In stock' : 'Currently unavailable';
        }
    }

    $productBadges = [];
    if (!$product->isPurchasable()) {
        $productBadges[] = ['label' => 'Out of stock', 'class' => 'out'];
    } elseif ($product->isOnSale()) {
        $productBadges[] = ['label' => 'Sale', 'class' => 'sale'];
    }
    if ($product->is_featured) {
        $productBadges[] = ['label' => 'Featured', 'class' => 'featured'];
    }
    if ($product->created_at && $product->created_at->gte(now()->subDays(30))) {
        $productBadges[] = ['label' => 'New', 'class' => 'new'];
    }
?>

<section class="product-shell">
    <div class="wrap product-main">
        <div>
            <div class="gallery-main">
                <?php if (count($productBadges)): ?>
                    <div class="product-badges">
                        <?php foreach ($productBadges as $badge): ?>
                            <span class="product-badge <?php echo e($badge['class']); ?>"><?php echo e($badge['label']); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($product->featured_image_url): ?>
                    <img id="main-product-image" src="<?php echo e($assetUrl($product->featured_image_url)); ?>" alt="<?php echo e($product->featured_image_alt ?: $product->title); ?>">
                <?php else: ?>
                    <div class="placeholder"><?php echo e($product->title); ?></div>
                <?php endif; ?>
            </div>

            <?php if ($product->images->count()): ?>
                <div class="thumbs">
                    <?php foreach ($product->images->take(10) as $image): ?>
                        <button class="thumb" type="button" onclick="document.getElementById('main-product-image').src=this.dataset.src" data-src="<?php echo e($assetUrl($image->url)); ?>">
                            <img src="<?php echo e($assetUrl($image->url)); ?>" alt="<?php echo e($image->alt_text ?: $product->title); ?>">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="product-info">
            <div class="eyebrow"><?php echo e($activeCategory?->name ?: 'Product'); ?></div>
            <h1><?php echo e($product->title); ?></h1>
            <div class="product-price">
                <?php if ($range['min'] !== null && $range['max'] !== null && $range['min'] !== $range['max']): ?>
                    <?php echo e($money($range['min'])); ?> – <?php echo e($money($range['max'])); ?>

                <?php else: ?>
                    <?php echo e($money($range['min'])); ?>

                <?php endif; ?>
                <?php if (!$product->isVariable() && $product->isOnSale()): ?>
                    <span class="old"><?php echo e($money($product->regular_price_minor)); ?></span>
                <?php endif; ?>
            </div>

            <?php if ($product->short_description): ?>
                <p class="lead"><?php echo e($product->short_description); ?></p>
            <?php endif; ?>

            <div class="stock <?php echo e($product->isPurchasable() ? '' : 'out'); ?>">
                <?php echo e($product->isVariable() ? ($product->isPurchasable() ? 'Choose options' : 'Currently unavailable') : $simpleAvailability); ?>

            </div>

            <?php if ($product->isVariable()): ?>
                <div class="options" id="variant-options">
                    <?php foreach ($product->options as $option): ?>
                        <?php
                            $optionKey = strtolower(trim((string) $option->name));
                            $isColorOption = str_contains($optionKey, 'color') || str_contains($optionKey, 'colour');
                            $isSizeOption = str_contains($optionKey, 'size');
                        ?>
                        <div class="option <?php echo e($isColorOption ? 'option--color' : ($isSizeOption ? 'option--size' : '')); ?>" data-option-id="<?php echo e($option->id); ?>">
                            <div class="option-label"><?php echo e($option->name); ?></div>
                            <div class="option-values">
                                <?php foreach ($option->values->where('is_active', true) as $value): ?>
                                    <button type="button" class="option-value <?php echo e($isColorOption ? 'option-value--color' : ($isSizeOption ? 'option-value--size' : '')); ?>" data-option-id="<?php echo e($option->id); ?>" data-value-id="<?php echo e($value->id); ?>" aria-pressed="false">
                                        <?php if ($value->swatch_hex): ?>
                                            <i class="swatch" style="background:<?php echo e($value->swatch_hex); ?>"></i>
                                        <?php elseif ($isColorOption): ?>
                                            <i class="swatch swatch--fallback"><?php echo e(strtoupper(substr($value->label, 0, 1))); ?></i>
                                        <?php endif; ?>
                                        <span><?php echo e($value->label); ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div id="product-sku" class="muted" style="font-size:13px;margin-top:16px">
                <?php if ($product->sku): ?>SKU: <?php echo e($product->sku); ?><?php endif; ?>
            </div>

            <?php if ($product->isPurchasable() && ($commerceEnabled ?? false)): ?>
                <form class="purchase-form" method="post" action="<?php echo e($cartAddUrl); ?>" data-commerce-add-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="product" value="<?php echo e($product->public_id); ?>">
                    <input id="selected-variant" type="hidden" name="variant" value="<?php echo e($product->isVariable() ? ($product->defaultVariant()?->public_id ?? '') : ''); ?>">
                    <input id="purchase-quantity" class="input qty" type="number" name="quantity" min="1" max="<?php echo e(!$product->isVariable() && $simpleAvailable !== null ? min(99, max(1, $simpleAvailable)) : 99); ?>" value="1" aria-label="Quantity">
                    <button id="add-to-cart" class="btn" type="submit">Add to cart</button>
                </form>
            <?php elseif (!($commerceEnabled ?? false)): ?>
                <div class="notice">Shopping is currently disabled for this website. Product preview remains available.</div>
            <?php else: ?>
                <div class="notice">This product is currently unavailable.</div>
            <?php endif; ?>

            <div class="product-trust" aria-label="Shopping benefits">
                <span><strong>Secure checkout</strong><small>Protected payment flow</small></span>
                <span><strong>Live stock</strong><small>Availability updates by variation</small></span>
                <span><strong>Order support</strong><small>Order status and email confirmation</small></span>
            </div>
        </div>
    </div>

    <?php if ($product->description): ?>
        <div class="wrap description">
            <h2>Product details</h2>
            <div><?php echo nl2br(e($product->description)); ?></div>
        </div>
    <?php endif; ?>
</section>

<?php if ($relatedProducts->count()): ?>
    <section class="related">
        <div class="wrap">
            <h2>You may also like</h2>
            <div class="grid">
                <?php foreach ($relatedProducts as $item): ?>
                    <?php $relatedRange = $priceRange($item); ?>
                    <a class="product-card" href="<?php echo e($productUrl($item)); ?>">
                        <?php if (!$item->isPurchasable()): ?><span class="badge out">Out of stock</span>
                        <?php elseif ($item->isOnSale()): ?><span class="badge sale">Sale</span>
                        <?php elseif ($item->is_featured): ?><span class="badge">Featured</span>
                        <?php elseif ($item->created_at && $item->created_at->gte(now()->subDays(30))): ?><span class="badge new">New</span><?php endif; ?>
                        <div class="card-media">
                            <?php if ($item->featured_image_url): ?>
                                <img src="<?php echo e($assetUrl($item->featured_image_url)); ?>" alt="<?php echo e($item->featured_image_alt ?: $item->title); ?>" loading="lazy">
                            <?php else: ?>
                                <div class="placeholder"><?php echo e($item->title); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <h3 class="card-title"><?php echo e($item->title); ?></h3>
                            <div class="price"><?php echo e($money($relatedRange['min'])); ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($product->isVariable()): ?>
<?php
    $variantPayload = $product->variants->where('is_enabled', true)->map(function ($variant) use ($money, $assetUrl) {
        $available = ($variant->track_inventory && !$variant->allow_backorders)
            ? max(0, (int) ($variant->stock_quantity ?? 0))
            : null;
        $threshold = max(0, (int) ($variant->low_stock_threshold ?? 0));
        $label = $variant->allow_backorders && (int) ($variant->stock_quantity ?? 0) <= 0
            ? 'Available on backorder'
            : ($available !== null && $available > 0 && $available <= $threshold
                ? 'Only '.$available.' left'
                : ($variant->isPurchasable() ? 'In stock' : 'Currently unavailable'));

        return [
            'id' => $variant->public_id,
            'valueIds' => $variant->values->pluck('id')->map(fn ($id) => (int) $id)->values(),
            'price' => $money($variant->effectivePriceMinor()),
            'sku' => $variant->sku ?: $variant->product?->sku,
            'purchasable' => $variant->isPurchasable(),
            'maxQuantity' => $available === null ? 99 : min(99, max(1, $available)),
            'availabilityLabel' => $label,
            'image' => $variant->image_url ? $assetUrl($variant->image_url) : null,
            'isDefault' => (bool) $variant->is_default,
        ];
    })->values();
?>
<script>
(() => {
    const variants = <?= json_encode($variantPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const selected = new Map();
    const buttons = [...document.querySelectorAll('[data-value-id]')];
    const price = document.querySelector('.product-price');
    const sku = document.getElementById('product-sku');
    const stock = document.querySelector('.stock');
    const mainImage = document.getElementById('main-product-image');
    const variantInput = document.getElementById('selected-variant');
    const addButton = document.getElementById('add-to-cart');
    const quantityInput = document.getElementById('purchase-quantity');
    const originalPrice = price ? price.innerHTML : '';
    const originalSku = sku ? sku.innerHTML : '';
    const originalImage = mainImage ? mainImage.src : '';
    const optionCount = new Set(buttons.map(button => button.dataset.optionId)).size;

    function applyVariant(variant) {
        if (!variant) {
            if (price) price.innerHTML = originalPrice;
            if (sku) sku.innerHTML = originalSku;
            if (stock) { stock.textContent = 'Choose options'; stock.classList.remove('out'); }
            if (mainImage && originalImage) mainImage.src = originalImage;
            if (variantInput) variantInput.value = '';
            if (addButton) addButton.disabled = true;
            if (quantityInput) { quantityInput.max = '99'; quantityInput.value = Math.max(1, Math.min(99, Number(quantityInput.value || 1))); }
            return;
        }
        if (price) price.textContent = variant.price;
        if (sku) sku.textContent = variant.sku ? `SKU: ${variant.sku}` : '';
        if (stock) {
            stock.textContent = variant.availabilityLabel || (variant.purchasable ? 'In stock' : 'Currently unavailable');
            stock.classList.toggle('out', !variant.purchasable);
        }
        if (mainImage && variant.image) mainImage.src = variant.image;
        if (variantInput) variantInput.value = variant.id;
        if (addButton) addButton.disabled = !variant.purchasable;
        if (quantityInput) {
            quantityInput.max = String(variant.maxQuantity || 99);
            quantityInput.value = String(Math.max(1, Math.min(Number(quantityInput.value || 1), Number(quantityInput.max))));
            quantityInput.disabled = !variant.purchasable;
        }
    }

    function refreshChoices() {
        buttons.forEach(button => {
            const optionId = button.dataset.optionId;
            const candidateId = Number(button.dataset.valueId);
            const required = [...selected.entries()]
                .filter(([selectedOption]) => selectedOption !== optionId)
                .map(([, valueId]) => Number(valueId));
            const possible = variants.some(variant => variant.purchasable && variant.valueIds.map(Number).includes(candidateId) && required.every(valueId => variant.valueIds.map(Number).includes(valueId)));
            button.disabled = !possible;
        });
    }

    function resolve() {
        refreshChoices();
        if (selected.size !== optionCount) return applyVariant(null);
        const ids = [...selected.values()].map(Number).sort((a, b) => a - b);
        const match = variants.find(v => [...v.valueIds].map(Number).sort((a, b) => a - b).join(',') === ids.join(','));
        applyVariant(match || null);
    }

    buttons.forEach(button => button.addEventListener('click', () => {
        const optionId = button.dataset.optionId;
        selected.set(optionId, Number(button.dataset.valueId));
        buttons.filter(candidate => candidate.dataset.optionId === optionId).forEach(candidate => { const active = candidate === button; candidate.classList.toggle('selected', active); candidate.setAttribute('aria-pressed', active ? 'true' : 'false'); });
        resolve();
    }));

    const defaultVariant = variants.find(v => v.isDefault) || variants[0];
    if (defaultVariant) {
        defaultVariant.valueIds.forEach(valueId => {
            const button = buttons.find(candidate => Number(candidate.dataset.valueId) === Number(valueId));
            if (button) {
                selected.set(button.dataset.optionId, Number(valueId));
                button.classList.add('selected');
            }
        });
        resolve();
    }
})();
</script>
<?php endif; ?>

<script type="application/ld+json"><?php echo json_encode([
    '<?php $__contextArgs = [];
if (context()->has($__contextArgs[0])) :
if (isset($value)) { $__contextPrevious[] = $value; }
$value = context()->get($__contextArgs[0]); ?>' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $product->title,
    'description' => $product->seo_description ?: $product->short_description,
    'image' => array_values(array_filter(array_merge([$product->featured_image_url], $product->images->pluck('url')->all()))),
    'sku' => $product->sku,
    'offers' => [
        '@type' => 'Offer',
        'priceCurrency' => $currency,
        'price' => $range['min'] !== null ? number_format($range['min'] / (10 ** ($currencyMeta['decimals'] ?? 2)), $currencyMeta['decimals'] ?? 2, '.', '') : null,
        'availability' => $product->isPurchasable() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        'url' => request()->fullUrl(),
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
<?php /**PATH C:\xampp\htdocs\my-custom-cms\resources\views/commerce/storefront-modes/product.blade.php ENDPATH**/ ?>