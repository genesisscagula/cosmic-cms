<?php

namespace App\Console\Commands;

use App\Models\CommerceOrder;
use Illuminate\Console\Command;

class CommercePaymentHealthCheck extends Command
{
    protected $signature = 'commerce:payment-health
        {--website= : Limit to one website ID}
        {--strict : Return a failure code when payment integrity needs attention}';

    protected $description = 'Audit commerce checkout/payment recovery health without contacting PayPal.';

    public function handle(): int
    {
        $query = CommerceOrder::query();
        if (filled($this->option('website'))) {
            $query->where('website_id', (int) $this->option('website'));
        }

        $staleMinutes = max(5, (int) config('cosmic-commerce.payment_recovery.stale_after_minutes', 30));
        $maxAttempts = max(1, (int) config('cosmic-commerce.payment_recovery.max_attempts', 12));
        $attentionAttempts = max(1, (int) config('cosmic-commerce.payment_recovery.attention_after_attempts', 6));

        $pending = (clone $query)->whereIn('payment_status', ['pending', 'cancelled']);
        $stale = (clone $pending)->where('updated_at', '<=', now()->subMinutes($staleMinutes))->count();
        $missingCheckout = (clone $pending)->whereNull('external_checkout_id')->where('created_at', '<=', now()->subMinutes($staleMinutes))->count();
        $withErrors = (clone $pending)->whereNotNull('payment_recovery_last_error')->count();
        $attention = (clone $pending)->where(function ($q) use ($attentionAttempts) {
            $q->whereNotNull('payment_attention_required_at')
                ->orWhere('payment_recovery_attempts', '>=', $attentionAttempts);
        })->count();
        $exhausted = (clone $pending)->where('payment_recovery_attempts', '>=', $maxAttempts)->count();
        $overdue = (clone $pending)->whereNotNull('payment_recovery_next_attempt_at')->where('payment_recovery_next_attempt_at', '<=', now()->subMinutes(15))->count();

        $paidMissingCapture = (clone $query)->whereIn('payment_status', ['paid', 'partially_refunded', 'refunded'])->whereNull('external_payment_id')->count();
        $paidMissingTimestamp = (clone $query)->whereIn('payment_status', ['paid', 'partially_refunded', 'refunded'])->whereNull('paid_at')->count();
        $paidWithRecoveryError = (clone $query)->whereIn('payment_status', ['paid', 'partially_refunded', 'refunded'])->whereNotNull('payment_recovery_last_error')->count();

        $rows = [
            ['Stale pending/cancelled', $stale],
            ['Pending without PayPal checkout ID', $missingCheckout],
            ['Pending with recovery error', $withErrors],
            ['Needs merchant attention', $attention],
            ['Recovery attempts exhausted', $exhausted],
            ['Recovery schedule overdue', $overdue],
            ['Paid without capture ID', $paidMissingCapture],
            ['Paid without paid_at', $paidMissingTimestamp],
            ['Paid with stale recovery error', $paidWithRecoveryError],
        ];

        $this->table(['Check', 'Count'], $rows);

        $critical = $attention + $exhausted + $overdue + $paidMissingCapture + $paidMissingTimestamp + $paidWithRecoveryError;
        if ($critical > 0) {
            $this->warn("Commerce payment health needs attention ({$critical} critical finding(s)).");
        } else {
            $this->info('Commerce payment health OK.');
        }

        return $this->option('strict') && $critical > 0 ? self::FAILURE : self::SUCCESS;
    }
}
