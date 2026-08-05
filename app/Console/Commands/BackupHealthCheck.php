<?php

namespace App\Console\Commands;

use App\Services\CosmicBackupService;
use Illuminate\Console\Command;

class BackupHealthCheck extends Command
{
    protected $signature = 'cosmic:backup-health';
    protected $description = 'Verify that a recent, non-empty Cosmic CMS backup exists';

    public function handle(CosmicBackupService $backups): int
    {
        $health = $backups->health();
        if ($health['healthy']) {
            $this->info($health['message']);
        } else {
            $this->error($health['message']);
        }
        if (isset($health['latest'])) {
            $this->line('Latest: '.$health['latest']['path']);
            $this->line('Age: '.$health['age_hours'].' hours');
            $this->line('Size: '.number_format($health['latest']['bytes']).' bytes');
        }
        return $health['healthy'] ? self::SUCCESS : self::FAILURE;
    }
}
