<?php

namespace App\Console\Commands;

use App\Models\CommerceOrder;
use App\Services\CommerceOrderNotificationService;
use Illuminate\Console\Command;

class RetryCommerceNotifications extends Command
{
    protected $signature = 'commerce:retry-notifications {--limit=100}';
    protected $description = 'Retry failed or missing paid-order commerce notifications.';

    public function handle(CommerceOrderNotificationService $notifications): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $orders = CommerceOrder::query()
            ->whereIn('payment_status', ['paid', 'partially_refunded', 'refunded'])
            ->where(function ($query) {
                $query->whereNull('order_confirmation_sent_at')->orWhereNull('merchant_notification_sent_at');
            })
            ->where(function ($query) {
                $query->whereNull('notification_next_attempt_at')->orWhere('notification_next_attempt_at', '<=', now());
            })
            ->oldest('notification_next_attempt_at')
            ->limit($limit)
            ->get();

        foreach ($orders as $order) $notifications->paid($order);
        $this->info('Checked '.$orders->count().' commerce order notification set(s).');
        return self::SUCCESS;
    }
}
