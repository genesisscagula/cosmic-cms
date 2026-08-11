<?php

namespace App\Services;

use App\Models\CommerceInventoryAdjustment;
use App\Models\CommerceOrder;
use App\Models\CommerceInventoryReservation;
use App\Models\CommerceProduct;
use App\Models\CommerceProductVariant;
use App\Models\Website;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CommerceInventoryService
{
    public function normalizedStatus(bool $trackInventory, ?int $quantity, bool $allowBackorders, string $requestedStatus = CommerceProduct::STOCK_IN_STOCK): string
    {
        if (! $trackInventory) {
            return in_array($requestedStatus, [CommerceProduct::STOCK_IN_STOCK, CommerceProduct::STOCK_OUT_OF_STOCK, CommerceProduct::STOCK_BACKORDER], true)
                ? $requestedStatus
                : CommerceProduct::STOCK_IN_STOCK;
        }

        if (($quantity ?? 0) > 0) return CommerceProduct::STOCK_IN_STOCK;
        return $allowBackorders ? CommerceProduct::STOCK_BACKORDER : CommerceProduct::STOCK_OUT_OF_STOCK;
    }

    public function adjust(Website $website, CommerceProduct $product, ?CommerceProductVariant $variant, int $delta, string $reason, ?string $note = null, ?int $userId = null): CommerceInventoryAdjustment
    {
        if ($variant && ((int) $variant->product_id !== (int) $product->id || (int) $variant->website_id !== (int) $website->id)) abort(404);
        if ((int) $product->website_id !== (int) $website->id) abort(404);

        return DB::transaction(function () use ($website, $product, $variant, $delta, $reason, $note, $userId) {
            $stock = $variant
                ? CommerceProductVariant::query()->whereKey($variant->id)->lockForUpdate()->firstOrFail()
                : CommerceProduct::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            if (! $stock->track_inventory) {
                throw ValidationException::withMessages(['inventory_delta' => 'Enable inventory tracking before adjusting stock.']);
            }

            $before = max(0, (int) ($stock->stock_quantity ?? 0));
            $after = $before + $delta;
            if ($after < 0) {
                throw ValidationException::withMessages(['inventory_delta' => "Stock cannot go below zero. Available: {$before}."]);
            }

            $stock->forceFill([
                'stock_quantity' => $after,
                'stock_status' => $this->normalizedStatus(true, $after, (bool) $stock->allow_backorders, (string) $stock->stock_status),
            ])->save();

            return CommerceInventoryAdjustment::create([
                'website_id' => $website->id,
                'commerce_product_id' => $product->id,
                'commerce_product_variant_id' => $variant?->id,
                'user_id' => $userId,
                'reason' => $reason,
                'quantity_delta' => $delta,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'note' => $note,
            ]);
        });
    }


    /** Reserve tracked stock for a pending checkout under row locks. */
    public function reserveForOrder(CommerceOrder $order): void
    {
        $expiresAt = $order->checkout_expires_at ?: now()->addHours(max(1, (int) config('cosmic-commerce.payment_recovery.expire_after_hours', 24)));

        foreach ($order->items()->get() as $item) {
            $stock = $item->commerce_product_variant_id
                ? CommerceProductVariant::query()->whereKey($item->commerce_product_variant_id)->lockForUpdate()->first()
                : CommerceProduct::query()->whereKey($item->commerce_product_id)->lockForUpdate()->first();

            if (! $stock || ! $stock->track_inventory || $stock->allow_backorders) continue;

            $existing = CommerceInventoryReservation::query()
                ->where('commerce_order_item_id', $item->id)
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->status === 'active' && (! $existing->expires_at || $existing->expires_at->isFuture())) {
                continue;
            }

            $reservedByOthers = CommerceInventoryReservation::query()
                ->where('status', 'active')
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->where('commerce_order_item_id', '!=', $item->id)
                ->when($item->commerce_product_variant_id,
                    fn ($q) => $q->where('commerce_product_variant_id', $item->commerce_product_variant_id),
                    fn ($q) => $q->whereNull('commerce_product_variant_id')->where('commerce_product_id', $item->commerce_product_id)
                )
                ->sum('quantity');

            $available = max(0, (int) ($stock->stock_quantity ?? 0) - (int) $reservedByOthers);
            $required = max(1, (int) $item->quantity);
            if ($available < $required) {
                throw ValidationException::withMessages([
                    'cart' => $available > 0
                        ? "Only {$available} item(s) remain available while other customers are checking out. Review your cart and try again."
                        : 'An item in your cart is temporarily reserved by another checkout or is now out of stock. Review your cart and try again.',
                ]);
            }

            CommerceInventoryReservation::query()->updateOrCreate(
                ['commerce_order_item_id' => $item->id],
                [
                    'website_id' => $order->website_id,
                    'commerce_order_id' => $order->id,
                    'commerce_product_id' => $item->commerce_product_id,
                    'commerce_product_variant_id' => $item->commerce_product_variant_id,
                    'quantity' => $required,
                    'status' => 'active',
                    'reserved_at' => now(),
                    'expires_at' => $expiresAt,
                    'consumed_at' => null,
                    'released_at' => null,
                ]
            );
        }
    }

    /** Release checkout reservations without touching physical stock. */
    public function releaseReservations(CommerceOrder $order): int
    {
        return CommerceInventoryReservation::query()
            ->where('commerce_order_id', $order->id)
            ->where('status', 'active')
            ->update([
                'status' => 'released',
                'released_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /** Mark active reservations consumed after stock deduction succeeds. */
    public function consumeReservations(CommerceOrder $order): int
    {
        return CommerceInventoryReservation::query()
            ->where('commerce_order_id', $order->id)
            ->where('status', 'active')
            ->update([
                'status' => 'consumed',
                'consumed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /** Deduct all tracked order lines under row locks. Returns true when manual inventory attention is required. */
    public function deductForPaidOrder(CommerceOrder $order): bool
    {
        $inventoryIssue = false;
        foreach ($order->items()->get() as $item) {
            $reservation = CommerceInventoryReservation::query()
                ->where('commerce_order_item_id', $item->id)
                ->lockForUpdate()
                ->first();

            $stock = $item->commerce_product_variant_id
                ? CommerceProductVariant::query()->whereKey($item->commerce_product_variant_id)->lockForUpdate()->first()
                : CommerceProduct::query()->whereKey($item->commerce_product_id)->lockForUpdate()->first();

            if (! $stock) {
                $inventoryIssue = true;
                if ($reservation && $reservation->status === 'active') {
                    $reservation->forceFill(['status' => 'released', 'released_at' => now()])->save();
                }
                continue;
            }
            if (! $stock->track_inventory) {
                if ($reservation && $reservation->status === 'active') {
                    $reservation->forceFill(['status' => 'consumed', 'consumed_at' => now()])->save();
                }
                continue;
            }

            $before = max(0, (int) ($stock->stock_quantity ?? 0));
            $qty = (int) $item->quantity;
            $reservedByOthers = (int) CommerceInventoryReservation::query()
                ->where('status', 'active')
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->where('commerce_order_item_id', '!=', $item->id)
                ->when($item->commerce_product_variant_id,
                    fn ($q) => $q->where('commerce_product_variant_id', $item->commerce_product_variant_id),
                    fn ($q) => $q->whereNull('commerce_product_variant_id')->where('commerce_product_id', $item->commerce_product_id)
                )
                ->sum('quantity');
            $unreservedAvailable = max(0, $before - $reservedByOthers);

            if (! $stock->allow_backorders && $unreservedAvailable < $qty) {
                $inventoryIssue = true;
                if ($reservation && $reservation->status === 'active') {
                    $reservation->forceFill(['status' => 'released', 'released_at' => now()])->save();
                }
                continue;
            }

            $after = max(0, $before - $qty);
            $stock->forceFill([
                'stock_quantity' => $after,
                'stock_status' => $this->normalizedStatus(true, $after, (bool) $stock->allow_backorders, (string) $stock->stock_status),
            ])->save();

            CommerceInventoryAdjustment::create([
                'website_id' => $order->website_id,
                'commerce_product_id' => $item->commerce_product_id,
                'commerce_product_variant_id' => $item->commerce_product_variant_id,
                'commerce_order_id' => $order->id,
                'reason' => 'sale',
                'quantity_delta' => $after - $before,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'note' => 'Automatic stock deduction for '.$order->order_number,
                'metadata' => [
                    'order_item_id' => $item->id,
                    'ordered_quantity' => $qty,
                    'backordered_quantity' => max(0, $qty - $before),
                    'reserved_by_other_checkouts' => $reservedByOthers,
                ],
            ]);

            if ($reservation && $reservation->status === 'active') {
                $reservation->forceFill(['status' => 'consumed', 'consumed_at' => now()])->save();
            }
        }
        return $inventoryIssue;
    }

    /** Restock an order once. Used only when explicitly requested by a merchant. */
    public function restockOrder(CommerceOrder $order, ?int $userId = null, string $note = 'Merchant restock'): int
    {
        return DB::transaction(function () use ($order, $userId, $note) {
            $locked = CommerceOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $metadata = is_array($locked->metadata) ? $locked->metadata : [];
            if (! empty($metadata['inventory_restocked_at'])) return 0;

            $restored = 0;
            $sales = CommerceInventoryAdjustment::query()
                ->where('commerce_order_id', $locked->id)
                ->where('reason', 'sale')
                ->where('quantity_delta', '<', 0)
                ->get();

            foreach ($sales as $sale) {
                $stock = $sale->commerce_product_variant_id
                    ? CommerceProductVariant::query()->whereKey($sale->commerce_product_variant_id)->lockForUpdate()->first()
                    : CommerceProduct::query()->whereKey($sale->commerce_product_id)->lockForUpdate()->first();
                if (! $stock || ! $stock->track_inventory) continue;

                $qty = abs((int) $sale->quantity_delta);
                if ($qty < 1) continue;
                $before = max(0, (int) ($stock->stock_quantity ?? 0));
                $after = $before + $qty;
                $stock->forceFill([
                    'stock_quantity' => $after,
                    'stock_status' => $this->normalizedStatus(true, $after, (bool) $stock->allow_backorders, (string) $stock->stock_status),
                ])->save();

                CommerceInventoryAdjustment::create([
                    'website_id' => $locked->website_id,
                    'commerce_product_id' => $sale->commerce_product_id,
                    'commerce_product_variant_id' => $sale->commerce_product_variant_id,
                    'commerce_order_id' => $locked->id,
                    'user_id' => $userId,
                    'reason' => 'restock',
                    'quantity_delta' => $qty,
                    'quantity_before' => $before,
                    'quantity_after' => $after,
                    'note' => $note,
                    'metadata' => ['source_adjustment_id' => $sale->id],
                ]);
                $restored += $qty;
            }

            $metadata['inventory_restocked_at'] = now()->toIso8601String();
            $metadata['inventory_restocked_quantity'] = $restored;
            $locked->forceFill(['metadata' => $metadata])->save();
            return $restored;
        });
    }
}
