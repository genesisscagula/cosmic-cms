<?php

namespace App\Console\Commands;

use App\Models\MediaPack;
use App\Services\MediaPackOwnershipService;
use Illuminate\Console\Command;

class PruneExpiredMediaPacks extends Command
{
    protected $signature = 'cosmic:prune-media-packs {--days=30 : Delete unclaimed trial packs older than this many days} {--dry-run : Show matches without deleting}';

    protected $description = 'Delete expired, unclaimed trial media packs and their stored image folders.';

    public function handle(MediaPackOwnershipService $ownership): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $query = MediaPack::query()
            ->where('owner_type', 'trial')
            ->whereNull('website_id')
            ->where('created_at', '<', $cutoff)
            ->whereDoesntHave('trialGeneration', fn ($trial) => $trial->whereNotNull('claimed_at'));

        $count = (clone $query)->count();
        if ($count === 0) {
            $this->info('No expired trial media packs found.');
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("{$count} expired trial media pack(s) would be deleted.");
            return self::SUCCESS;
        }

        $deleted = 0;
        $query->orderBy('id')->chunkById(100, function ($packs) use ($ownership, &$deleted): void {
            foreach ($packs as $pack) {
                $ownership->deletePack($pack);
                $deleted++;
            }
        });

        $this->info("Deleted {$deleted} expired trial media pack(s).");
        return self::SUCCESS;
    }
}
