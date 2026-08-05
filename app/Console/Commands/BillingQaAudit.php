<?php

namespace App\Console\Commands;

use App\Models\BillingTransaction;
use App\Models\CreditTransaction;
use App\Models\PaymentOrder;
use App\Models\PaymentWebhookEvent;
use App\Models\User;
use App\Support\SubscriptionStatus;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BillingQaAudit extends Command
{
    protected $signature = 'cosmic:billing-qa
        {--user= : Limit checks to one user ID or email}
        {--strict : Treat warnings as a failed command}
        {--json : Print machine-readable JSON}';

    protected $description = 'Audit billing, subscription, webhook, and credit consistency without mutating production data.';

    /** @var array<int, array{severity:string,code:string,message:string,context:array}> */
    private array $findings = [];

    public function handle(): int
    {
        $required = ['users', 'payment_orders', 'credit_transactions', 'billing_transactions', 'payment_webhook_events'];
        $missing = array_values(array_filter($required, fn (string $table) => ! Schema::hasTable($table)));

        if ($missing !== []) {
            $this->add('error', 'missing_tables', 'Billing QA cannot run because required tables are missing.', ['tables' => $missing]);
            return $this->finish();
        }

        $user = $this->resolveUser();
        if ($this->option('user') && ! $user) {
            $this->add('error', 'user_not_found', 'The requested billing QA user could not be found.', ['selector' => $this->option('user')]);
            return $this->finish();
        }

        $this->checkOrderLifecycle($user);
        $this->checkCreditFulfillment($user);
        $this->checkSubscriptionBindings($user);
        $this->checkBillingLedger($user);
        $this->checkWebhookPipeline();

        if ($this->findings === []) {
            $this->add('ok', 'billing_consistent', 'No billing consistency problems were detected.', []);
        }

        return $this->finish();
    }

    private function resolveUser(): ?User
    {
        $selector = trim((string) $this->option('user'));
        if ($selector === '') {
            return null;
        }

        return User::query()
            ->where(function (Builder $query) use ($selector) {
                $query->where('email', $selector);
                if (ctype_digit($selector)) {
                    $query->orWhereKey((int) $selector);
                }
            })
            ->first();
    }

    private function checkOrderLifecycle(?User $user): void
    {
        $orders = PaymentOrder::query()->when($user, fn (Builder $query) => $query->where('user_id', $user->id));

        $paidNotFulfilled = (clone $orders)
            ->whereNotNull('paid_at')
            ->whereNull('fulfilled_at')
            ->whereNotIn('status', ['pending', 'failed', 'expired'])
            ->get(['id', 'reference', 'user_id', 'status']);

        foreach ($paidNotFulfilled as $order) {
            $this->add('error', 'paid_not_fulfilled', 'A paid order has not completed fulfillment.', $order->toArray());
        }

        $fulfilledWithoutPaid = (clone $orders)
            ->whereNotNull('fulfilled_at')
            ->whereNull('paid_at')
            ->get(['id', 'reference', 'user_id', 'status']);

        foreach ($fulfilledWithoutPaid as $order) {
            $this->add('error', 'fulfilled_without_paid_at', 'A fulfilled order has no paid timestamp.', $order->toArray());
        }

        $staleCutoff = now()->subHours(max(1, (int) config('cosmic-billing-qa.stale_pending_hours', 24)));
        $stalePending = (clone $orders)
            ->where('status', 'pending')
            ->where('created_at', '<', $staleCutoff)
            ->get(['id', 'reference', 'user_id', 'product_type', 'product_key', 'created_at']);

        foreach ($stalePending as $order) {
            $this->add('warning', 'stale_pending_order', 'A checkout has remained pending beyond the configured QA threshold.', $order->toArray());
        }

        $duplicates = PaymentOrder::query()
            ->select('external_subscription_id', DB::raw('COUNT(*) as aggregate'))
            ->whereNotNull('external_subscription_id')
            ->whereNotIn('status', ['replaced', 'expired', 'failed'])
            ->when($user, fn (Builder $query) => $query->where('user_id', $user->id))
            ->groupBy('external_subscription_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $this->add('error', 'duplicate_live_subscription_binding', 'A PayPal subscription is bound to multiple live orders.', [
                'subscription_id' => $duplicate->external_subscription_id,
                'order_count' => (int) $duplicate->aggregate,
            ]);
        }
    }

    private function checkCreditFulfillment(?User $user): void
    {
        PaymentOrder::query()
            ->whereNotNull('fulfilled_at')
            ->where('credits', '>', 0)
            ->when($user, fn (Builder $query) => $query->where('user_id', $user->id))
            ->orderBy('id')
            ->chunkById(200, function ($orders): void {
                foreach ($orders as $order) {
                    $reference = 'payment:'.$order->provider.':'.$order->reference;
                    $count = CreditTransaction::query()->where('reference', $reference)->count();

                    if ($count === 0) {
                        $this->add('error', 'missing_initial_credit_grant', 'A fulfilled order has no matching credit transaction.', [
                            'order_id' => $order->id,
                            'reference' => $order->reference,
                            'expected_credit_reference' => $reference,
                            'credits' => (int) $order->credits,
                        ]);
                    } elseif ($count > 1) {
                        $this->add('error', 'duplicate_initial_credit_grant', 'A fulfilled order has duplicate credit transactions.', [
                            'order_id' => $order->id,
                            'credit_reference' => $reference,
                            'count' => $count,
                        ]);
                    }
                }
            });

        BillingTransaction::query()
            ->where('status', 'completed')
            ->where('type', 'renewal')
            ->where('credits_granted', '>', 0)
            ->when($user, fn (Builder $query) => $query->where('user_id', $user->id))
            ->orderBy('id')
            ->chunkById(200, function ($transactions): void {
                foreach ($transactions as $transaction) {
                    $reference = 'payment:paypal:renewal:'.$transaction->external_id;
                    $count = CreditTransaction::query()->where('reference', $reference)->count();

                    if ($count !== 1) {
                        $this->add('error', $count === 0 ? 'missing_renewal_credit_grant' : 'duplicate_renewal_credit_grant', 'Renewal ledger and credit wallet are inconsistent.', [
                            'billing_transaction_id' => $transaction->id,
                            'external_id' => $transaction->external_id,
                            'expected_credit_reference' => $reference,
                            'count' => $count,
                        ]);
                    }
                }
            });
    }

    private function checkSubscriptionBindings(?User $user): void
    {
        $activeStatuses = [SubscriptionStatus::ACTIVE, SubscriptionStatus::PAST_DUE, SubscriptionStatus::SUSPENDED, SubscriptionStatus::CANCELLED];

        User::query()
            ->whereIn('plan_status', $activeStatuses)
            ->when($user, fn (Builder $query) => $query->whereKey($user->id))
            ->orderBy('id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $account) {
                    $order = PaymentOrder::query()
                        ->where('user_id', $account->id)
                        ->where('provider', $account->plan_provider ?: 'paypal')
                        ->where('product_type', 'plan')
                        ->whereNotNull('fulfilled_at')
                        ->whereNotNull('external_subscription_id')
                        ->whereNotIn('status', ['failed', 'expired', 'replaced'])
                        ->latest('id')
                        ->first();

                    if (! $order) {
                        $this->add('error', 'plan_without_subscription_order', 'An account has a billable plan status but no fulfilled subscription order.', [
                            'user_id' => $account->id,
                            'email' => $account->email,
                            'plan_key' => $account->plan_key,
                            'plan_status' => $account->plan_status,
                        ]);
                        continue;
                    }

                    if ((string) $account->plan_key !== (string) $order->product_key) {
                        $this->add('error', 'account_order_plan_mismatch', 'The account plan does not match its current fulfilled order.', [
                            'user_id' => $account->id,
                            'account_plan' => $account->plan_key,
                            'order_plan' => $order->product_key,
                            'order_id' => $order->id,
                        ]);
                    }
                }
            });
    }

    private function checkBillingLedger(?User $user): void
    {
        $orphaned = BillingTransaction::query()
            ->whereNotNull('payment_order_id')
            ->whereDoesntHave('paymentOrder')
            ->when($user, fn (Builder $query) => $query->where('user_id', $user->id))
            ->get(['id', 'user_id', 'payment_order_id', 'external_id']);

        foreach ($orphaned as $transaction) {
            $this->add('error', 'orphaned_billing_transaction', 'A billing transaction references a missing payment order.', $transaction->toArray());
        }

        $wrongOwners = BillingTransaction::query()
            ->join('payment_orders', 'payment_orders.id', '=', 'billing_transactions.payment_order_id')
            ->whereColumn('billing_transactions.user_id', '!=', 'payment_orders.user_id')
            ->when($user, fn ($query) => $query->where('billing_transactions.user_id', $user->id))
            ->get([
                'billing_transactions.id',
                'billing_transactions.user_id',
                'billing_transactions.payment_order_id',
                'payment_orders.user_id as order_user_id',
            ]);

        foreach ($wrongOwners as $transaction) {
            $this->add('error', 'billing_owner_mismatch', 'A billing transaction belongs to a different user than its payment order.', (array) $transaction->getAttributes());
        }
    }

    private function checkWebhookPipeline(): void
    {
        $stuckCutoff = now()->subMinutes(max(1, (int) config('cosmic-billing-qa.stuck_webhook_minutes', 15)));
        $stuck = PaymentWebhookEvent::query()
            ->where('status', 'processing')
            ->where('processing_started_at', '<', $stuckCutoff)
            ->get(['id', 'event_id', 'event_type', 'attempts', 'processing_started_at']);

        foreach ($stuck as $event) {
            $this->add('error', 'stuck_webhook', 'A webhook event appears stuck in processing.', $event->toArray());
        }

        $failedCount = PaymentWebhookEvent::query()->where('status', 'failed')->count();
        if ($failedCount >= max(1, (int) config('cosmic-billing-qa.failed_webhook_warning_count', 1))) {
            $this->add('warning', 'failed_webhooks_present', 'Failed payment webhook events require review or retry.', ['count' => $failedCount]);
        }

        $retryDue = PaymentWebhookEvent::query()
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now())
            ->whereNotIn('status', ['processed', 'ignored'])
            ->count();

        if ($retryDue > 0) {
            $this->add('warning', 'webhook_retries_due', 'Payment webhook events are due for retry.', ['count' => $retryDue]);
        }
    }

    private function add(string $severity, string $code, string $message, array $context): void
    {
        $this->findings[] = compact('severity', 'code', 'message', 'context');
    }

    private function finish(): int
    {
        $errors = count(array_filter($this->findings, fn (array $finding) => $finding['severity'] === 'error'));
        $warnings = count(array_filter($this->findings, fn (array $finding) => $finding['severity'] === 'warning'));

        if ($this->option('json')) {
            $this->line(json_encode([
                'ok' => $errors === 0 && (! $this->option('strict') || $warnings === 0),
                'errors' => $errors,
                'warnings' => $warnings,
                'findings' => $this->findings,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($this->findings as $finding) {
                $line = '['.strtoupper($finding['severity']).'] '.$finding['code'].': '.$finding['message'];
                match ($finding['severity']) {
                    'error' => $this->error($line),
                    'warning' => $this->warn($line),
                    default => $this->info($line),
                };

                if ($finding['context'] !== []) {
                    $this->line('  '.json_encode($finding['context'], JSON_UNESCAPED_SLASHES));
                }
            }

            $this->newLine();
            $this->line("Billing QA summary: {$errors} error(s), {$warnings} warning(s).");
        }

        return $errors > 0 || ($this->option('strict') && $warnings > 0)
            ? self::FAILURE
            : self::SUCCESS;
    }
}
