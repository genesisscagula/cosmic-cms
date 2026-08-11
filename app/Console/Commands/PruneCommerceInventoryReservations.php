<?php

namespace App\Console\Commands;

use App\Models\CommerceOrder;
use App\Services\CommerceOrderService;
use Illuminate\Console\Command;

class PruneCommerceInventoryReservations extends Command
{
    protected $signature = 'commerce:prune-inventory-reservations {--limit=250}';
    protected $description = 'Release expired inventory reservations and close abandoned unpaid commerce orders.';

    public function handle(CommerceOrderService $orders): int
    {
        $limit = max(1, min(2000, (int) $this->option('limit')));
        $count = 0;

        CommerceOrder::query()
            ->whereIn('payment_status', ['pending', 'cancelled'])
            ->whereNotNull('checkout_expires_at')
            ->where('checkout_expires_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (CommerceOrder $order) use ($orders, &$count) {
                $orders->expirePending($order, 'Checkout expired and reserved inventory was released.');
                $count++;
            });

        $this->info("Released inventory reservations for {$count} expired checkout(s).");
        return self::SUCCESS;
    }
}
