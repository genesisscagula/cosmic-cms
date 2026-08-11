<?php

namespace Tests\Unit;

use App\Models\CommerceOrder;
use App\Services\CommercePayPalService;
use App\Services\PayPalService;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CommercePayPalIntegrityTest extends TestCase
{
    private function service(): CommercePayPalService
    {
        return new CommercePayPalService(Mockery::mock(PayPalService::class));
    }

    private function order(): CommerceOrder
    {
        return new CommerceOrder([
            'public_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'order_number' => 'CC-QA-1001',
            'currency' => 'USD',
            'total_minor' => 2599,
        ]);
    }

    public function test_completed_capture_with_exact_reference_currency_and_amount_is_accepted(): void
    {
        $this->service()->assertCaptureResourceMatches($this->order(), [
            'status' => 'COMPLETED',
            'custom_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'amount' => ['currency_code' => 'USD', 'value' => '25.99'],
        ]);

        $this->addToAssertionCount(1);
    }

    #[DataProvider('invalidCaptureProvider')]
    public function test_capture_mismatch_is_rejected(array $changes, string $message): void
    {
        $resource = array_replace_recursive([
            'status' => 'COMPLETED',
            'custom_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'amount' => ['currency_code' => 'USD', 'value' => '25.99'],
        ], $changes);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);
        $this->service()->assertCaptureResourceMatches($this->order(), $resource);
    }

    public static function invalidCaptureProvider(): array
    {
        return [
            'not completed' => [['status' => 'PENDING'], 'PayPal has not completed this payment.'],
            'wrong reference' => [['custom_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'], 'PayPal capture reference does not match this order.'],
            'wrong currency' => [['amount' => ['currency_code' => 'EUR']], 'PayPal currency does not match this order.'],
            'wrong amount' => [['amount' => ['value' => '26.00']], 'PayPal amount does not match this order.'],
        ];
    }
}
