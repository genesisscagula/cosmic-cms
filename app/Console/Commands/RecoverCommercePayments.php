<?php

namespace App\Console\Commands;

use App\Models\CommerceOrder;
use App\Services\CommercePaymentRecoveryService;
use Illuminate\Console\Command;

class RecoverCommercePayments extends Command
{
    protected $signature = 'commerce:recover-payments
        {--limit=100 : Maximum orders to inspect}
        {--older-than= : Only inspect orders untouched for this many minutes}
        {--dry-run : List candidates without contacting PayPal}';

    protected $description = 'Recover interrupted PayPal commerce payments and expire abandoned pending checkouts safely.';

    public function handle(CommercePaymentRecoveryService $recovery): int
    {
        if (! (bool) config('cosmic-commerce.payment_recovery.enabled', true)) {
            $this->info('Commerce payment recovery is disabled.');
            return self::SUCCESS;
        }

        $limit = max(1, min(1000, (int) $this->option('limit')));
        $olderThan = $this->option('older-than');
        $olderThan = $olderThan === null || $olderThan === ''
            ? (int) config('cosmic-commerce.payment_recovery.older_than_minutes', 5)
            : (int) $olderThan;
        $olderThan = max(1, $olderThan);
        $maxAttempts = max(1, (int) config('cosmic-commerce.payment_recovery.max_attempts', 12));

        $orders = CommerceOrder::query()
            ->whereIn('payment_status', ['pending', 'cancelled'])
            ->where(function ($query) use ($olderThan) {
                $query->where(function ($due) use ($olderThan) {
                    $due->whereNull('payment_recovery_next_attempt_at')
                        ->where('updated_at', '<=', now()->subMinutes($olderThan));
                })->orWhere('payment_recovery_next_attempt_at', '<=', now())
                  ->orWhere(function ($expired) {
                      $expired->whereNotNull('checkout_expires_at')
                          ->where('checkout_expires_at', '<=', now());
                  });
            })
            ->where(function ($query) use ($maxAttempts) {
                $query->where('payment_recovery_attempts', '<', $maxAttempts)
                    ->orWhereNull('payment_recovery_attempts')
                    ->orWhere(function ($expired) {
                        $expired->whereNotNull('checkout_expires_at')
                            ->where('checkout_expires_at', '<=', now());
                    });
            })
            ->orderByRaw('COALESCE(payment_recovery_next_attempt_at, updated_at) asc')
            ->limit($limit)
            ->get();

        $counts = [
            'recovered' => 0,
            'waiting' => 0,
            'expired' => 0,
            'already_paid' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($orders as $order) {
            if ($this->option('dry-run')) {
                $this->line(sprintf(
                    '[DRY] %s payment=%s paypal=%s attempts=%d expires=%s',
                    $order->order_number,
                    $order->payment_status,
                    $order->external_checkout_id ?: '-',
                    (int) $order->payment_recovery_attempts,
                    $order->checkout_expires_at?->toDateTimeString() ?: '-',
                ));
                continue;
            }

            try {
                $result = $recovery->recover($order);
                $counts[$result] = ($counts[$result] ?? 0) + 1;
                $this->line(sprintf('[%s] %s', strtoupper($result), $order->order_number));
            } catch (\Throwable $e) {
                report($e);
                $counts['failed']++;
                $this->error(sprintf('[FAILED] %s: %s', $order->order_number, $e->getMessage()));
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Commerce recovery: %d recovered, %d waiting, %d expired, %d failed.',
            $counts['recovered'],
            $counts['waiting'],
            $counts['expired'],
            $counts['failed'],
        ));

        return $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
