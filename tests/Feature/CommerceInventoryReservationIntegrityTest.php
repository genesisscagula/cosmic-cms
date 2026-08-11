<?php

namespace Tests\Feature;

use App\Models\CommerceInventoryReservation;
use App\Models\CommerceOrder;
use App\Models\CommerceProduct;
use App\Models\User;
use App\Models\Website;
use App\Services\CommerceInventoryService;
use App\Services\CommerceOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommerceInventoryReservationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private Website $website;
    private CommerceProduct $product;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->website = Website::create([
            'user_id' => $user->id,
            'name' => 'Inventory QA',
            'api_token' => 'qa-'.bin2hex(random_bytes(16)),
        ]);
        $this->product = CommerceProduct::create([
            'public_id' => (string) Str::uuid(),
            'website_id' => $this->website->id,
            'type' => 'simple',
            'fulfillment_type' => 'physical',
            'status' => 'published',
            'visibility' => 'catalog',
            'title' => 'Last Item',
            'slug' => 'last-item',
            'regular_price_minor' => 1000,
            'track_inventory' => true,
            'stock_quantity' => 1,
            'allow_backorders' => false,
            'stock_status' => 'in_stock',
            'taxable' => true,
        ]);
    }

    private function order(string $number): CommerceOrder
    {
        $order = CommerceOrder::create([
            'website_id' => $this->website->id,
            'public_id' => (string) Str::uuid(),
            'order_number' => $number,
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_provider' => 'paypal',
            'currency' => 'USD',
            'subtotal_minor' => 1000,
            'discount_minor' => 0,
            'shipping_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => 1000,
            'customer_email' => 'buyer@example.test',
            'customer_first_name' => 'QA',
            'customer_last_name' => 'Buyer',
            'checkout_expires_at' => now()->addHour(),
        ]);
        $order->items()->create([
            'commerce_product_id' => $this->product->id,
            'product_public_id' => $this->product->public_id,
            'title' => $this->product->title,
            'quantity' => 1,
            'unit_price_minor' => 1000,
            'line_subtotal_minor' => 1000,
            'tax_minor' => 0,
            'line_total_minor' => 1000,
        ]);
        return $order;
    }

    public function test_two_pending_checkouts_cannot_reserve_the_same_last_unit(): void
    {
        $inventory = app(CommerceInventoryService::class);
        $first = $this->order('CC-QA-STOCK-1');
        $second = $this->order('CC-QA-STOCK-2');

        $inventory->reserveForOrder($first);
        $this->assertSame(1, CommerceInventoryReservation::where('commerce_order_id', $first->id)->where('status', 'active')->count());

        $this->expectException(ValidationException::class);
        $inventory->reserveForOrder($second);
    }

    public function test_releasing_reservation_makes_stock_available_to_next_checkout(): void
    {
        $inventory = app(CommerceInventoryService::class);
        $first = $this->order('CC-QA-STOCK-1');
        $second = $this->order('CC-QA-STOCK-2');

        $inventory->reserveForOrder($first);
        $this->assertSame(1, $inventory->releaseReservations($first));
        $inventory->reserveForOrder($second);

        $this->assertSame(1, CommerceInventoryReservation::where('commerce_order_id', $second->id)->where('status', 'active')->count());
    }

    public function test_replayed_mark_paid_does_not_deduct_inventory_twice(): void
    {
        // Give the product two units so a broken replay guard would visibly deduct twice.
        $this->product->forceFill(['stock_quantity' => 2])->save();
        $inventory = app(CommerceInventoryService::class);
        $orders = app(CommerceOrderService::class);
        $order = $this->order('CC-QA-STOCK-1');
        $inventory->reserveForOrder($order);

        $orders->markPaid($order, 'CAPTURE-QA-STOCK-1', 'return');
        $orders->markPaid($order->fresh(), 'CAPTURE-QA-STOCK-1', 'webhook');

        $this->assertSame(1, (int) $this->product->fresh()->stock_quantity);
        $this->assertSame(1, $order->inventoryReservations()->where('status', 'consumed')->count());
        $this->assertSame(1, \App\Models\CommerceInventoryAdjustment::where('commerce_order_id', $order->id)->where('reason', 'sale')->count());
    }
}
