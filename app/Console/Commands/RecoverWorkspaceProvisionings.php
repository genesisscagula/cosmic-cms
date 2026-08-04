<?php

namespace App\Console\Commands;

use App\Services\WorkspaceProvisioningService;
use Illuminate\Console\Command;

class RecoverWorkspaceProvisionings extends Command
{
    protected $signature = 'provisioning:recover {--limit=100} {--dry-run}';
    protected $description = 'Retry failed or stale workspace provisioning records safely.';

    public function handle(WorkspaceProvisioningService $service): int
    {
        $result = $service->recoverDue((int) $this->option('limit'), (bool) $this->option('dry-run'));
        $this->table(['Found', 'Recovered', 'Failed', 'Dry run'], [[
            $result['found'], $result['recovered'], $result['failed'], $result['dry_run'] ? 'yes' : 'no',
        ]]);
        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
