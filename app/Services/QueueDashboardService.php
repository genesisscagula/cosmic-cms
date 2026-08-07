<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QueueDashboardService
{
    public function snapshot(): array
    {
        $queues = ['images-high','ai','mail','webhooks','default'];
        $now = now()->timestamp;
        $rows = [];

        foreach ($queues as $queue) {
            $waiting = Schema::hasTable('jobs') ? DB::table('jobs')->where('queue',$queue)->whereNull('reserved_at')->count() : 0;
            $running = Schema::hasTable('jobs') ? DB::table('jobs')->where('queue',$queue)->whereNotNull('reserved_at')->count() : 0;
            $oldest = Schema::hasTable('jobs') ? DB::table('jobs')->where('queue',$queue)->min('available_at') : null;
            $rows[] = [
                'key' => $queue,
                'label' => match($queue) { 'images-high' => 'Image Queue', 'ai' => 'AI Queue', 'mail' => 'Email Queue', 'webhooks' => 'Webhook Queue', default => 'Default Queue' },
                'waiting' => $waiting,
                'running' => $running,
                'failed' => $this->failedCount($queue),
                'oldest_wait_seconds' => $oldest ? max(0, $now - (int)$oldest) : 0,
            ];
        }

        $pending = array_sum(array_column($rows,'waiting'));
        $running = array_sum(array_column($rows,'running'));
        $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $staleCutoff = now()->subMinutes((int)config('cosmic-queue.health.stale_after_minutes',15))->timestamp;
        $stale = Schema::hasTable('jobs') ? DB::table('jobs')->whereNotNull('reserved_at')->where('reserved_at','<=',$staleCutoff)->count() : 0;

        return [
            'queues' => $rows,
            'summary' => compact('pending','running','failed','stale'),
            'healthy' => $pending <= (int)config('cosmic-queue.health.max_pending_jobs',500)
                && $failed <= (int)config('cosmic-queue.health.max_failed_jobs',25) && $stale === 0,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function failedCount(string $queue): int
    {
        if (!Schema::hasTable('failed_jobs')) return 0;
        return DB::table('failed_jobs')->where('queue',$queue)->count();
    }
}
