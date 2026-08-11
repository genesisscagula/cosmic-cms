<?php

namespace Tests\Feature;

use App\Models\CommerceOrder;
use App\Models\User;
use App\Models\Website;
use App\Services\CommerceRefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CommerceRefundIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function order(int $totalMinor = 10000): CommerceOrder
    {
        $user = User::factory()->create();
        $website = Website::create([
            'user_id' => $user->id,
            'name' => 'Commerce QA',
            'domain' => null,
            'api_token' => 'qa-'.bin2hex(random_bytes(16)),
        ]);

        return CommerceOrder::create([
            'website_id' => $website->id,
            'public_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'order_number' => 'CC-QA-REFUND-1',
            'status' => 'processing',
            'payment_status' => 'paid',
            'payment_provider' => 'paypal',
            'external_payment_id' => 'CAPTURE-QA-1',
            'currency' => 'USD',
            'subtotal_minor' => $totalMinor,
            'discount_minor' => 0,
            'shipping_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => $totalMinor,
            'refunded_minor' => 0,
            'refund_status' => 'none',
            'customer_email' => 'buyer@example.test',
            'customer_first_name' => 'QA',
            'customer_last_name' => 'Buyer',
            'paid_at' => now(),
        ]);
    }

    public function test_same_paypal_refund_event_is_idempotent(): void
    {
        $order = $this->order();
        $service = app(CommerceRefundService::class);

        $service->record($order, 'REFUND-1', 2500, 'USD', 'completed', ['id' => 'REFUND-1'], 'QA');
        $fresh = $service->record($order->fresh(), 'REFUND-1', 2500, 'USD', 'completed', ['id' => 'REFUND-1'], 'QA');

        $this->assertSame(1, $fresh->refunds()->count());
        $this->assertSame(2500, (int) $fresh->refunded_minor);
        $this->assertSame('partially_refunded', $fresh->payment_status);
    }

    public function test_refunds_cannot_exceed_captured_total(): void
    {
        $order = $this->order(5000);
        $service = app(CommerceRefundService::class);
        $service->record($order, 'REFUND-1', 4000, 'USD', 'completed', ['id' => 'REFUND-1']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Recorded refunds exceed the captured order total.');
        $service->record($order->fresh(), 'REFUND-2', 2000, 'USD', 'completed', ['id' => 'REFUND-2']);
    }

    public function test_refund_currency_must_match_order_currency(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PayPal refund currency does not match this order.');
        app(CommerceRefundService::class)->record($this->order(), 'REFUND-1', 1000, 'EUR', 'completed', []);
    }
}
