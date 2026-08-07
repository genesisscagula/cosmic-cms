<?php

namespace App\Console\Commands;

use App\Models\MediaPack;
use App\Services\MediaAssetLifecycleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RetryPendingMediaLocalizations extends Command
{
    protected $signature = 'cosmic:retry-media-localizations {--minutes=5 : Minimum stale age before retry} {--limit=50 : Maximum packs per run}';

    protected $description = 'Retry stale or failed remote-to-local media localization jobs.';

    public function handle(MediaAssetLifecycleService $lifecycle): int
    {
        $minutes = max(2, (int) $this->option('minutes'));
        $limit = max(1, min(250, (int) $this->option('limit')));
        $cutoff = now()->subMinutes($minutes);

        $packs = MediaPack::query()
            ->whereIn('status', ['pending', 'queued', 'downloading', 'localizing', 'failed'])
            ->where('updated_at', '<=', $cutoff)
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        $retried = 0;
        foreach ($packs as $pack) {
            $manifest = is_array($pack->manifest) ? $pack->manifest : [];
            if (! ($manifest['remote_only'] ?? false)) {
                continue;
            }

            $hasRemote = collect($manifest['images'] ?? [])->contains(
                fn ($item) => is_array($item) && filter_var($item['url'] ?? null, FILTER_VALIDATE_URL)
            );
            if (! $hasRemote) {
                continue;
            }

            if ($lifecycle->queueLocalization($pack, 'scheduler_retry', true)) {
                $retried++;
            }
        }

        Log::info('[MediaAssets] Localization retry sweep completed.', [
            'stale_minutes' => $minutes,
            'scanned' => $packs->count(),
            'retried' => $retried,
        ]);

        $this->info("Media localization retry sweep: {$retried} job(s) requeued.");

        return self::SUCCESS;
    }
}
