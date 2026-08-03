<?php

namespace App\Console\Commands;

use App\Models\PaymentOrder;
use App\Services\SubscriptionManagementService;
use Illuminate\Console\Command;

class ReconcilePayPalSubscriptions extends Command
{
    protected $signature = 'payments:reconcile-paypal {--user= : Only reconcile one user ID} {--limit=500 : Maximum subscriptions to inspect}';

    protected $description = 'Synchronize local PayPal subscription status and billing dates with PayPal.';

    public function handle(SubscriptionManagementService $subscriptions): int
    {
        $query = PaymentOrder::query()
            ->with('user')
            ->where('provider', 'paypal')
            ->where('product_type', 'plan')
            ->whereNotNull('external_subscription_id')
            ->whereNotIn('status', ['failed', 'expired', 'replaced']);

        if ($this->option('user')) {
            $query->where('user_id', (int) $this->option('user'));
        }

        $orders = $query->latest('id')->limit((int) $this->option('limit'))->get();
        $ok = 0;
        $failed = 0;

        foreach ($orders as $order) {
            try {
                $subscription = $subscriptions->syncOrder($order);
                $this->line(sprintf(
                    '[OK] order=%d user=%d subscription=%s status=%s',
                    $order->id,
                    $order->user_id,
                    $order->external_subscription_id,
                    strtolower((string) ($subscription['status'] ?? 'unknown')),
                ));
                $ok++;
            } catch (\Throwable $exception) {
                report($exception);
                $this->error(sprintf(
                    '[FAILED] order=%d subscription=%s: %s',
                    $order->id,
                    $order->external_subscription_id,
                    $exception->getMessage(),
                ));
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Reconciliation complete: {$ok} synced, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
