<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QueueHealthCheck extends Command
{
    protected $signature = 'cosmic:queue-health';
    protected $description = 'Check pending, stale, and failed queue jobs against production safety thresholds.';

    public function handle(): int
    {
        if (! Schema::hasTable('jobs') || ! Schema::hasTable('failed_jobs')) {
            $this->error('Queue tables are missing. Run php artisan migrate --force.');
            return self::FAILURE;
        }

        $pending = DB::table('jobs')->count();
        $failed = DB::table('failed_jobs')->count();
        $cutoff = now()->subMinutes((int) config('cosmic-queue.health.stale_after_minutes', 15))->timestamp;
        $stale = DB::table('jobs')->whereNotNull('reserved_at')->where('reserved_at', '<=', $cutoff)->count();

        $imageTimeout = (int) config('cosmic-queue.performance.image_job_timeout', 150);
        $retryAfter = (int) config('queue.connections.database.retry_after', 240);
        $unsafeRetryWindow = config('queue.default') === 'database' && $retryAfter <= $imageTimeout;

        $this->table(['Pending', 'Stale reserved', 'Failed', 'Retry after', 'Image timeout'], [[$pending, $stale, $failed, $retryAfter.'s', $imageTimeout.'s']]);

        if ($unsafeRetryWindow) {
            $this->warn('DB_QUEUE_RETRY_AFTER must be greater than COSMIC_IMAGE_JOB_TIMEOUT to avoid duplicate processing.');
        }

        $unhealthy = $pending > (int) config('cosmic-queue.health.max_pending_jobs', 500)
            || $failed > (int) config('cosmic-queue.health.max_failed_jobs', 25)
            || $stale > 0
            || $unsafeRetryWindow;

        $unhealthy ? $this->error('Queue health thresholds exceeded.') : $this->info('Queue health is within configured thresholds.');

        return $unhealthy ? self::FAILURE : self::SUCCESS;
    }
}
