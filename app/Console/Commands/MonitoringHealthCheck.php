<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitoringHealthCheck extends Command
{
    protected $signature = 'cosmic:monitoring-health {--json : Return machine-readable JSON}';
    protected $description = 'Check Cosmic CMS logging and monitoring health.';

    public function handle(): int
    {
        $logPath = storage_path('logs');
        $checks = [];

        $checks['log_path_exists'] = is_dir($logPath);
        $checks['log_path_writable'] = is_dir($logPath) && is_writable($logPath);

        $freeBytes = @disk_free_space(storage_path()) ?: 0;
        $freeMb = round($freeBytes / 1024 / 1024, 2);
        $checks['free_disk_mb'] = $freeMb;
        $checks['free_disk_ok'] = $freeMb >= (float) config('cosmic-monitoring.health.minimum_free_disk_mb', 1024);

        $files = glob($logPath.'/*.log') ?: [];
        $largest = 0;
        $newest = 0;
        foreach ($files as $file) {
            $largest = max($largest, (int) @filesize($file));
            $newest = max($newest, (int) @filemtime($file));
        }

        $checks['log_files'] = count($files);
        $checks['largest_log_mb'] = round($largest / 1024 / 1024, 2);
        $checks['largest_log_ok'] = $checks['largest_log_mb'] <= (float) config('cosmic-monitoring.health.max_log_size_mb', 100);
        $checks['newest_log_age_minutes'] = $newest > 0 ? round((time() - $newest) / 60, 2) : null;

        $healthy = $checks['log_path_exists']
            && $checks['log_path_writable']
            && $checks['free_disk_ok']
            && $checks['largest_log_ok'];

        $payload = ['healthy' => $healthy, 'checked_at' => now()->toIso8601String(), 'checks' => $checks];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->info($healthy ? 'Monitoring is healthy.' : 'Monitoring requires attention.');
            $this->table(['Check', 'Value'], collect($checks)->map(fn ($value, $key) => [$key, is_bool($value) ? ($value ? 'yes' : 'no') : $value])->values()->all());
        }

        if (! $healthy) {
            Log::channel('cosmic_errors')->critical('Monitoring health check failed.', $checks);
        }

        return $healthy ? self::SUCCESS : self::FAILURE;
    }
}
