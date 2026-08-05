<?php

namespace App\Console\Commands;

use App\Models\WebsiteOwnershipTransfer;
use App\Models\WebsitePreviewLink;
use App\Models\WorkspaceInvitation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneExpiredAccess extends Command
{
    protected $signature = 'cosmic:prune-expired-access {--dry-run}';
    protected $description = 'Expire stale invitations, preview links, and website handoffs without deleting audit history.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $now = now();

        $counts = [
            'invitations' => WorkspaceInvitation::query()->where('status', 'pending')->whereNotNull('expires_at')->where('expires_at', '<=', $now)->count(),
            'preview_links' => WebsitePreviewLink::query()->whereNull('revoked_at')->whereNotNull('expires_at')->where('expires_at', '<=', $now)->count(),
            'handoffs' => WebsiteOwnershipTransfer::query()->where('status', 'pending')->whereNotNull('expires_at')->where('expires_at', '<=', $now)->count(),
        ];

        if (! $dryRun) {
            DB::transaction(function () use ($now): void {
                WorkspaceInvitation::query()->where('status', 'pending')->whereNotNull('expires_at')->where('expires_at', '<=', $now)->update(['status' => 'expired']);
                WebsitePreviewLink::query()->whereNull('revoked_at')->whereNotNull('expires_at')->where('expires_at', '<=', $now)->update(['revoked_at' => $now]);
                WebsiteOwnershipTransfer::query()->where('status', 'pending')->whereNotNull('expires_at')->where('expires_at', '<=', $now)->update(['status' => 'expired', 'cancelled_at' => $now]);
            });
        }

        $this->table(['Invitations', 'Preview links', 'Handoffs', 'Dry run'], [[...array_values($counts), $dryRun ? 'yes' : 'no']]);

        return self::SUCCESS;
    }
}
