<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class PruneCosmicLogs extends Command
{
    protected $signature = 'cosmic:prune-logs {--dry-run : List files without deleting them}';
    protected $description = 'Prune Cosmic CMS log files according to configured retention.';

    public function handle(): int
    {
        $path = storage_path('logs');
        $deleted = 0;

        foreach (glob($path.'/*.log') ?: [] as $file) {
            $name = strtolower(basename($file));
            $days = match (true) {
                Str::contains($name, 'security') => (int) config('cosmic-monitoring.retention.security_days', 90),
                Str::contains($name, 'performance') => (int) config('cosmic-monitoring.retention.performance_days', 14),
                default => (int) config('cosmic-monitoring.retention.application_days', 30),
            };

            if ((int) @filemtime($file) >= now()->subDays(max(1, $days))->getTimestamp()) {
                continue;
            }

            $this->line(($this->option('dry-run') ? 'Would delete: ' : 'Deleting: ').basename($file));
            if (! $this->option('dry-run') && @unlink($file)) {
                $deleted++;
            }
        }

        $this->components->info($this->option('dry-run') ? 'Dry run complete.' : "Deleted {$deleted} expired log file(s).");

        return self::SUCCESS;
    }
}
