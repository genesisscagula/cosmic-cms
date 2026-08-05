<?php

namespace App\Console\Commands;

use App\Services\CosmicBackupService;
use Illuminate\Console\Command;
use Throwable;

class CreateCosmicBackup extends Command
{
    protected $signature = 'cosmic:backup {--prune : Apply retention after a successful backup}';
    protected $description = 'Create a verified Cosmic CMS database and uploads backup';

    public function handle(CosmicBackupService $backups): int
    {
        try {
            $result = $backups->create();
            $this->info('Backup created: '.$result['path']);
            $this->line('Size: '.number_format($result['bytes']).' bytes');
            $this->line('SHA-256: '.$result['sha256']);
            if ($this->option('prune')) $this->info('Expired backups removed: '.$backups->prune());
            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            $this->error('Backup failed: '.$e->getMessage());
            return self::FAILURE;
        }
    }
}
