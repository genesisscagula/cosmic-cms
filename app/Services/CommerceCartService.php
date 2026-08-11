<?php

namespace App\Services;

use App\Models\CommerceProduct;
use App\Models\CommerceProductVariant;
use App\Models\Website;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class CommerceCartService
{
    public function add(Website $website, string $productPublicId, ?string $variantPublicId, int $quantity = 1): array
    {
        $quantity = max(1, min(99, $quantity));
        [$product, $variant] = $this->resolvePurchasable($website, $productPublicId, $variantPublicId);
        $key = $this->lineKey($product, $variant);
        $cart = $this->raw($website);
        $existing = (int) data_get($cart, "items.$key.quantity", 0);
        $requested = min(99, $existing + $quantity);
        $this->assertQuantityAvailable($product, $variant, $requested);

        $cart['items'][$key] = [
            'product_public_id' => $product->public_id,
            'variant_public_id' => $variant?->public_id,
            'quantity' => $requested,
            'unit_price_minor' => $variant?->effectivePriceMinor() ?? $product->effectivePriceMinor(),
            'title' => $product->title,
            'option_label' => $variant?->optionLabel(),
        ];
        $cart['updated_at'] = now()->toIso8601String();
        $this->put($website, $cart);

        return $this->summary($website);
    }

    public function update(Website $website, string $lineKey, int $quantity): array
    {
        $cart = $this->raw($website);
        if (! isset($cart['items'][$lineKey])) {
            throw ValidationException::withMessages(['cart' => 'That cart item is no longer available.']);
        }

        if ($quantity <= 0) {
            unset($cart['items'][$lineKey]);
            $this->put($website, $cart);
            return $this->summary($website);
        }

        $quantity = min(99, $quantity);
        $line = $cart['items'][$lineKey];
        [$product, $variant] = $this->resolvePurchasable(
            $website,
            (string) ($line['product_public_id'] ?? ''),
            filled($line['variant_public_id'] ?? null) ? (string) $line['variant_public_id'] : null
        );
        $this->assertQuantityAvailable($product, $variant, $quantity);
        $cart['items'][$lineKey]['quantity'] = $quantity;
        $cart['updated_at'] = now()->toIso8601String();
        $this->put($website, $cart);

        return $this->summary($website);
    }

    public function remove(Website $website, string $lineKey): array
    {
        $cart = $this->raw($website);
        unset($cart['items'][$lineKey]);
        $cart['updated_at'] = now()->toIso8601String();
        $this->put($website, $cart);

        return $this->summary($website);
    }

    public function clear(Website $website): void
    {
        session()->forget($this->sessionKey($website));
    }

    public function count(Website $website): int
    {
        return (int) collect($this->raw($website)['items'] ?? [])->sum(fn ($line) => max(0, (int) ($line['quantity'] ?? 0)));
    }

    /** @return array{items:Collection,subtotal_minor:int,count:int,requires_shipping:bool,adjustments:array} */
    public function summary(Website $website): array
    {
        $raw = $this->raw($website);
        $resolved = collect();
        $clean = [];
        $subtotal = 0;
        $count = 0;
        $requiresShipping = false;
        $adjustments = [];

        foreach (($raw['items'] ?? []) as $key => $line) {
            $product = CommerceProduct::query()
                ->forWebsite($website->id)
                ->published()
                ->where('public_id', (string) ($line['product_public_id'] ?? ''))
                ->with(['variants.values.option'])
                ->first();

            if (! $product) {
                $adjustments[] = ['type' => 'removed', 'message' => ((string) ($line['title'] ?? 'An item')).' was removed because it is no longer available.'];
                continue;
            }

            $variant = null;
            if (filled($line['variant_public_id'] ?? null)) {
                $variant = $product->variants->firstWhere('public_id', (string) $line['variant_public_id']);
            }

            if ($product->isVariable() && (! $variant || ! $variant->isPurchasable())) {
                $adjustments[] = ['type' => 'removed', 'message' => $product->title.' was removed because the selected variation is unavailable.'];
                continue;
            }
            if (! $product->isVariable() && ! $product->isPurchasable()) {
                $adjustments[] = ['type' => 'removed', 'message' => $product->title.' was removed because it is currently unavailable.'];
                continue;
            }

            $quantity = max(1, min(99, (int) ($line['quantity'] ?? 1)));
            $available = $this->availableQuantity($product, $variant);
            if ($available !== null) {
                $originalQuantity = $quantity;
                $quantity = min($quantity, max(0, $available));
                if ($quantity < 1) {
                    $adjustments[] = ['type' => 'removed', 'message' => $product->title.' was removed because it is out of stock.'];
                    continue;
                }
                if ($quantity !== $originalQuantity) {
                    $adjustments[] = ['type' => 'quantity', 'message' => $product->title.' quantity was adjusted to '.$quantity.' based on current stock.'];
                }
            }

            $unit = $variant?->effectivePriceMinor() ?? $product->effectivePriceMinor();
            if ($unit === null) {
                continue;
            }

            $previousUnit = isset($line['unit_price_minor']) ? (int) $line['unit_price_minor'] : null;
            if ($previousUnit !== null && $previousUnit !== $unit) {
                $adjustments[] = ['type' => 'price', 'message' => $product->title.' price changed since it was added to your cart.'];
            }

            $lineTotal = $unit * $quantity;
            $subtotal += $lineTotal;
            $count += $quantity;
            $requiresShipping = $requiresShipping || $product->requiresShipping();
            $clean[$key] = [
                'product_public_id' => $product->public_id,
                'variant_public_id' => $variant?->public_id,
                'quantity' => $quantity,
                'unit_price_minor' => $unit,
                'title' => $product->title,
                'option_label' => $variant?->optionLabel(),
            ];
            $resolved->push([
                'key' => $key,
                'product' => $product,
                'variant' => $variant,
                'quantity' => $quantity,
                'unit_price_minor' => $unit,
                'line_total_minor' => $lineTotal,
                'option_label' => $variant?->optionLabel(),
                'image_url' => $variant?->image_url ?: $product->featured_image_url,
                'sku' => $variant?->sku ?: $product->sku,
                'max_quantity' => $available === null ? 99 : min(99, max(1, $available)),
                'backorder' => (bool) (($variant ?: $product)->allow_backorders ?? false),
                'availability_label' => $available === null
                    ? (((bool) (($variant ?: $product)->allow_backorders ?? false)) ? 'Available on backorder' : 'In stock')
                    : ($available <= max(0, (int) (($variant ?: $product)->low_stock_threshold ?? 0)) ? 'Only '.$available.' left' : 'In stock'),
            ]);
        }

        if ($clean !== ($raw['items'] ?? [])) {
            $raw['items'] = $clean;
            $this->put($website, $raw);
        }

        return [
            'items' => $resolved,
            'subtotal_minor' => $subtotal,
            'count' => $count,
            'requires_shipping' => $requiresShipping,
            'adjustments' => $adjustments,
        ];
    }

    private function resolvePurchasable(Website $website, string $productPublicId, ?string $variantPublicId): array
    {
        $product = CommerceProduct::query()
            ->forWebsite($website->id)
            ->published()
            ->where('public_id', $productPublicId)
            ->with(['variants.values.option'])
            ->first();

        if (! $product || $product->visibility === CommerceProduct::VISIBILITY_HIDDEN) {
            throw ValidationException::withMessages(['product' => 'This product is no longer available.']);
        }

        if ($product->isVariable()) {
            if (! $variantPublicId) {
                throw ValidationException::withMessages(['variant' => 'Choose your product options first.']);
            }
            $variant = $product->variants->firstWhere('public_id', $variantPublicId);
            if (! $variant || ! $variant->isPurchasable()) {
                throw ValidationException::withMessages(['variant' => 'That variation is currently unavailable.']);
            }
            return [$product, $variant];
        }

        if (! $product->isPurchasable()) {
            throw ValidationException::withMessages(['product' => 'This product is currently unavailable.']);
        }

        return [$product, null];
    }

    private function assertQuantityAvailable(CommerceProduct $product, ?CommerceProductVariant $variant, int $quantity): void
    {
        $available = $this->availableQuantity($product, $variant);
        if ($available !== null && $quantity > $available) {
            throw ValidationException::withMessages([
                'quantity' => $available > 0
                    ? "Only {$available} item(s) are currently available."
                    : 'This item is currently out of stock.',
            ]);
        }
    }

    private function availableQuantity(CommerceProduct $product, ?CommerceProductVariant $variant): ?int
    {
        $stock = $variant ?: $product;
        if (! $stock->track_inventory || $stock->allow_backorders) {
            return null;
        }
        return max(0, (int) ($stock->stock_quantity ?? 0));
    }

    private function lineKey(CommerceProduct $product, ?CommerceProductVariant $variant): string
    {
        return $product->public_id.':'.($variant?->public_id ?: 'simple');
    }

    private function raw(Website $website): array
    {
        $cart = session()->get($this->sessionKey($website), []);
        return is_array($cart) ? array_merge(['items' => []], $cart) : ['items' => []];
    }

    private function put(Website $website, array $cart): void
    {
        session()->put($this->sessionKey($website), $cart);
    }

    private function sessionKey(Website $website): string
    {
        return 'cosmic_commerce_cart.'.$website->id;
    }
}
