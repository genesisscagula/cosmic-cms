<?php

namespace App\Console\Commands;

use App\Models\CommerceInventoryReservation;
use App\Models\CommerceOrder;
use App\Models\CommerceOrderRefund;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CommerceRegressionAudit extends Command
{
    protected $signature = 'commerce:regression-audit {--strict} {--limit=1000}';
    protected $description = 'Run final cross-layer commerce regression checks for checkout, payments, refunds, inventory and operational recovery.';

    /** @var array<int, array{severity:string,code:string,message:string,context:array}> */
    private array $findings = [];

    public function handle(): int
    {
        $strict = (bool) $this->option('strict');
        $limit = max(1, min(5000, (int) $this->option('limit')));

        $this->checkSchema();
        $this->checkConfiguration();
        $this->checkSchedulerContract();

        if ($this->hasCoreTables()) {
            $this->checkOrderStateConsistency($limit);
            $this->checkRefundConsistency($limit);
            $this->checkInventoryReservations($limit);
            $this->checkExternalReferenceUniqueness();
        }

        if ($this->findings === []) {
            $this->info('Commerce regression audit passed: no cross-layer inconsistencies detected.');
            return self::SUCCESS;
        }

        foreach ($this->findings as $finding) {
            $line = '['.strtoupper($finding['severity']).'] '.$finding['code'].': '.$finding['message'];
            match ($finding['severity']) {
                'error' => $this->error($line),
                'warning' => $this->warn($line),
                default => $this->line($line),
            };
        }

        $errors = count(array_filter($this->findings, fn (array $f) => $f['severity'] === 'error'));
        $warnings = count($this->findings) - $errors;
        $this->line("Commerce regression audit: {$errors} error(s), {$warnings} warning(s).");

        return ($errors > 0 || ($strict && $warnings > 0)) ? self::FAILURE : self::SUCCESS;
    }

    private function checkSchema(): void
    {
        $required = [
            'commerce_orders' => [
                'website_id', 'order_number', 'status', 'payment_status', 'currency',
                'subtotal_minor', 'shipping_minor', 'tax_minor', 'discount_minor', 'total_minor',
                'refunded_minor', 'external_checkout_id', 'external_payment_id', 'paid_at',
                'checkout_fingerprint', 'checkout_idempotency_key', 'payment_recovery_attempts',
                'metadata', 'notification_attempts',
            ],
            'commerce_order_items' => ['commerce_order_id', 'commerce_product_id', 'quantity', 'unit_price_minor', 'line_total_minor'],
            'commerce_order_refunds' => ['commerce_order_id', 'external_refund_id', 'amount_minor', 'currency', 'status'],
            'commerce_inventory_reservations' => ['commerce_order_id', 'commerce_product_id', 'quantity', 'status', 'expires_at'],
            'commerce_order_events' => ['commerce_order_id', 'event_type', 'source'],
        ];

        foreach ($required as $table => $columns) {
            if (!Schema::hasTable($table)) {
                $this->add('error', 'missing_table', "Required commerce table {$table} is missing.", ['table' => $table]);
                continue;
            }

            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    $this->add('error', 'missing_column', "Required commerce column {$table}.{$column} is missing.", compact('table', 'column'));
                }
            }
        }
    }

    private function checkConfiguration(): void
    {
        $recovery = config('cosmic-commerce.payment_recovery', []);
        if (!($recovery['enabled'] ?? false)) {
            $this->add('warning', 'payment_recovery_disabled', 'Payment recovery is disabled; interrupted PayPal checkouts will require manual reconciliation.', []);
        }

        if ((int) ($recovery['max_attempts'] ?? 0) < 1) {
            $this->add('error', 'invalid_recovery_attempts', 'Payment recovery max_attempts must be at least 1.', []);
        }

        if (empty($recovery['backoff_minutes']) || !is_array($recovery['backoff_minutes'])) {
            $this->add('error', 'invalid_recovery_backoff', 'Payment recovery backoff_minutes must contain at least one retry interval.', []);
        }

        if (!in_array(strtoupper((string) config('cosmic-commerce.default_currency')), array_keys(config('cosmic-commerce.currencies', [])), true)) {
            $this->add('error', 'unknown_default_currency', 'The commerce default currency is not registered in the currency registry.', []);
        }
    }

    private function checkSchedulerContract(): void
    {
        $path = base_path('routes/console.php');
        $contents = is_file($path) ? (string) file_get_contents($path) : '';
        $required = [
            'commerce:recover-payments',
            'commerce:payment-health',
            'commerce:retry-notifications',
            'commerce:prune-inventory-reservations',
            'commerce:integrity-audit',
        ];

        foreach ($required as $command) {
            if (!str_contains($contents, $command)) {
                $this->add('error', 'scheduler_contract_missing', "Scheduled commerce command {$command} is missing from routes/console.php.", ['command' => $command]);
            }
        }
    }

    private function checkOrderStateConsistency(int $limit): void
    {
        $badPaidTotals = CommerceOrder::query()
            ->whereIn('payment_status', ['paid', 'partially_refunded', 'refunded'])
            ->where(function ($q) {
                $q->whereNull('paid_at')->orWhereNull('external_payment_id');
            })
            ->limit($limit)->count();
        if ($badPaidTotals) {
            $this->add('error', 'paid_reference_incomplete', "{$badPaidTotals} paid/refunded order(s) are missing paid_at or capture references.", ['count' => $badPaidTotals]);
        }

        $unpaidFulfilled = CommerceOrder::query()
            ->whereNotIn('payment_status', ['paid', 'partially_refunded', 'refunded'])
            ->whereIn('status', ['processing', 'completed'])
            ->limit($limit)->count();
        if ($unpaidFulfilled) {
            $this->add('error', 'unpaid_order_fulfilled', "{$unpaidFulfilled} unpaid order(s) are marked processing/completed.", ['count' => $unpaidFulfilled]);
        }

        $badMath = 0;
        CommerceOrder::query()->limit($limit)->get()->each(function (CommerceOrder $order) use (&$badMath): void {
            $discountedSubtotal = max(0, (int) $order->subtotal_minor - (int) $order->discount_minor);
            if ((bool) $order->prices_include_tax) {
                $shippingTax = (int) data_get($order->tax_snapshot, 'shipping_tax_minor', 0);
                $expected = $discountedSubtotal + (int) $order->shipping_minor + $shippingTax;
            } else {
                $expected = $discountedSubtotal + (int) $order->shipping_minor + (int) $order->tax_minor;
            }

            if ((int) $order->total_minor !== $expected) {
                $badMath++;
            }
        });
        if ($badMath) {
            $this->add('error', 'order_total_mismatch', "{$badMath} order(s) have totals that do not reconcile.", ['count' => $badMath]);
        }

        $overRefunded = CommerceOrder::query()->whereColumn('refunded_minor', '>', 'total_minor')->limit($limit)->count();
        if ($overRefunded) {
            $this->add('error', 'refund_exceeds_order', "{$overRefunded} order(s) have refunded totals above the captured order total.", ['count' => $overRefunded]);
        }
    }

    private function checkRefundConsistency(int $limit): void
    {
        if (!Schema::hasTable('commerce_order_refunds')) {
            return;
        }

        $refundSums = CommerceOrderRefund::query()
            ->select('commerce_order_id', DB::raw("SUM(CASE WHEN status = 'completed' THEN amount_minor ELSE 0 END) AS completed_total"))
            ->groupBy('commerce_order_id')
            ->limit($limit)
            ->get();

        foreach ($refundSums as $row) {
            $order = CommerceOrder::find($row->commerce_order_id);
            if ($order && (int) $order->refunded_minor !== (int) $row->completed_total) {
                $this->add('error', 'refund_ledger_mismatch', "Order {$order->order_number} refund ledger does not match refunded_minor.", [
                    'order_id' => $order->id,
                    'stored' => (int) $order->refunded_minor,
                    'ledger' => (int) $row->completed_total,
                ]);
            }
        }

        $duplicates = CommerceOrderRefund::query()
            ->select('external_refund_id', DB::raw('COUNT(*) AS aggregate'))
            ->whereNotNull('external_refund_id')
            ->groupBy('external_refund_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        if ($duplicates->isNotEmpty()) {
            $this->add('error', 'duplicate_refund_reference', $duplicates->count().' duplicate external refund reference(s) detected.', ['count' => $duplicates->count()]);
        }
    }

    private function checkInventoryReservations(int $limit): void
    {
        if (!Schema::hasTable('commerce_inventory_reservations')) {
            return;
        }

        $expiredActive = CommerceInventoryReservation::query()
            ->where('status', 'active')->whereNotNull('expires_at')->where('expires_at', '<=', now())
            ->limit($limit)->count();
        if ($expiredActive) {
            $this->add('warning', 'expired_active_reservation', "{$expiredActive} expired inventory reservation(s) are still active.", ['count' => $expiredActive]);
        }

        $terminalActive = CommerceInventoryReservation::query()
            ->where('status', 'active')
            ->whereHas('order', fn ($q) => $q->whereIn('payment_status', ['cancelled', 'refunded']))
            ->limit($limit)->count();
        if ($terminalActive) {
            $this->add('error', 'terminal_order_has_reservation', "{$terminalActive} reservation(s) remain active for cancelled/refunded orders.", ['count' => $terminalActive]);
        }
    }

    private function checkExternalReferenceUniqueness(): void
    {
        $captureDuplicates = CommerceOrder::query()
            ->select('external_payment_id', DB::raw('COUNT(*) AS aggregate'))
            ->whereNotNull('external_payment_id')
            ->groupBy('external_payment_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        if ($captureDuplicates->isNotEmpty()) {
            $this->add('error', 'duplicate_capture_reference', $captureDuplicates->count().' duplicate PayPal capture reference(s) detected.', ['count' => $captureDuplicates->count()]);
        }

        $checkoutDuplicates = CommerceOrder::query()
            ->select('external_checkout_id', DB::raw('COUNT(*) AS aggregate'))
            ->whereNotNull('external_checkout_id')
            ->groupBy('external_checkout_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        if ($checkoutDuplicates->isNotEmpty()) {
            $this->add('error', 'duplicate_checkout_reference', $checkoutDuplicates->count().' duplicate PayPal checkout reference(s) detected.', ['count' => $checkoutDuplicates->count()]);
        }
    }

    private function hasCoreTables(): bool
    {
        return Schema::hasTable('commerce_orders') && Schema::hasTable('commerce_order_refunds') && Schema::hasTable('commerce_inventory_reservations');
    }

    private function add(string $severity, string $code, string $message, array $context): void
    {
        $this->findings[] = compact('severity', 'code', 'message', 'context');
    }
}
