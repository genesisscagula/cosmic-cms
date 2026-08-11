<?php

namespace App\Console\Commands;

use App\Models\CommerceInventoryAdjustment;
use App\Models\CommerceInventoryReservation;
use App\Models\CommerceOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CommerceIntegrityAudit extends Command
{
    protected $signature = 'commerce:integrity-audit {--repair} {--strict} {--limit=500}';
    protected $description = 'Audit commerce order, refund, payment and inventory-reservation consistency.';

    public function handle(): int
    {
        $repair = (bool) $this->option('repair');
        $strict = (bool) $this->option('strict');
        $limit = max(1, min(5000, (int) $this->option('limit')));
        $issues = 0;
        $repaired = 0;

        $expired = CommerceInventoryReservation::query()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->limit($limit)
            ->get();

        foreach ($expired as $reservation) {
            $issues++;
            if ($repair) {
                $reservation->forceFill(['status' => 'released', 'released_at' => now()])->save();
                $repaired++;
            }
        }

        $terminalReservations = CommerceInventoryReservation::query()
            ->where('status', 'active')
            ->whereHas('order', fn ($q) => $q->whereIn('payment_status', ['cancelled', 'refunded']))
            ->limit($limit)
            ->get();

        foreach ($terminalReservations as $reservation) {
            $issues++;
            if ($repair) {
                $reservation->forceFill(['status' => 'released', 'released_at' => now()])->save();
                $repaired++;
            }
        }

        $paidActive = CommerceInventoryReservation::query()
            ->where('status', 'active')
            ->whereHas('order', fn ($q) => $q->whereIn('payment_status', ['paid', 'partially_refunded']))
            ->limit($limit)
            ->get();

        foreach ($paidActive as $reservation) {
            $issues++;
            if ($repair) {
                $saleExists = CommerceInventoryAdjustment::query()
                    ->where('commerce_order_id', $reservation->commerce_order_id)
                    ->where('reason', 'sale')
                    ->where(function ($q) use ($reservation) {
                        if ($reservation->commerce_product_variant_id) {
                            $q->where('commerce_product_variant_id', $reservation->commerce_product_variant_id);
                        } else {
                            $q->whereNull('commerce_product_variant_id')
                                ->where('commerce_product_id', $reservation->commerce_product_id);
                        }
                    })
                    ->exists();
                $reservation->forceFill([
                    'status' => $saleExists ? 'consumed' : 'released',
                    'consumed_at' => $saleExists ? now() : null,
                    'released_at' => $saleExists ? null : now(),
                ])->save();
                $repaired++;
            }
        }

        $badPayments = CommerceOrder::query()
            ->whereIn('payment_status', ['paid', 'partially_refunded', 'refunded'])
            ->where(function ($q) {
                $q->whereNull('external_payment_id')->orWhereNull('paid_at');
            })
            ->count();
        $issues += $badPayments;

        $badRefunds = CommerceOrder::query()
            ->whereColumn('refunded_minor', '>', 'total_minor')
            ->count();
        $issues += $badRefunds;

        $duplicateCaptures = CommerceOrder::query()
            ->select('external_payment_id', DB::raw('COUNT(*) as aggregate'))
            ->whereNotNull('external_payment_id')
            ->groupBy('external_payment_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        $issues += $duplicateCaptures->count();

        $this->line("Commerce integrity audit: {$issues} issue(s), {$repaired} repaired.");
        if ($badPayments) $this->warn("{$badPayments} paid/refunded order(s) are missing a capture ID or paid timestamp.");
        if ($badRefunds) $this->error("{$badRefunds} order(s) have refunded totals above captured totals.");
        if ($duplicateCaptures->isNotEmpty()) $this->error($duplicateCaptures->count().' duplicate PayPal capture reference(s) detected.');

        return ($strict && $issues > 0) ? self::FAILURE : self::SUCCESS;
    }
}
