<?php

namespace App\Console\Commands;

use App\Jobs\BuildTrialSiteBundleJob;
use App\Jobs\FinalizeTrialSiteBundleJob;
use App\Models\TrialGeneration;
use Illuminate\Console\Command;

final class RecoverTrialBundles extends Command
{
    protected $signature = 'trials:recover-bundles {--limit=100 : Maximum active trials to inspect}';

    protected $description = 'Resume queued or stale trial site bundles using their existing manifest state.';

    public function handle(): int
    {
        $inspected = 0;
        $dispatched = 0;

        TrialGeneration::query()
            ->where('status', 'ready')
            ->whereNull('claimed_at')
            ->whereIn('bundle_status', ['queued', 'building', 'partial', 'ready'])
            ->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->pluck('id')
            ->each(function (int $trialId) use (&$inspected, &$dispatched): void {
                $inspected++;
                $trial = TrialGeneration::query()->find($trialId);
                if (! $trial) return;
                if ($trial->bundle_status === 'ready') {
                    if (! data_get($trial->bundle_manifest, 'staging_url')) {
                        FinalizeTrialSiteBundleJob::dispatch($trialId);
                        $dispatched++;
                    }
                    return;
                }
                if (BuildTrialSiteBundleJob::dispatchNext($trialId, true)) $dispatched++;
            });

        $this->info("Inspected {$inspected} active trial bundles; dispatched {$dispatched} resumable page jobs.");

        return self::SUCCESS;
    }
}
