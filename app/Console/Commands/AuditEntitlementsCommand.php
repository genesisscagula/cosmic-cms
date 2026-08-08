<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\EntitlementAuditService;
use Illuminate\Console\Command;

class AuditEntitlementsCommand extends Command
{
    protected $signature = 'cosmic:audit-entitlements
        {--email= : Audit one account by email}
        {--user= : Audit one account by user ID}
        {--only-mismatches : Hide healthy accounts}
        {--json : Emit JSON instead of a table}
        {--strict : Exit non-zero when any mismatch is found}';

    protected $description = 'Audit raw, effective, paid-order, and theme entitlement sources without modifying account data.';

    public function handle(EntitlementAuditService $audit): int
    {
        $query = User::query()->orderBy('id');

        if ($email = trim((string) $this->option('email'))) {
            $query->whereRaw('LOWER(email) = ?', [strtolower($email)]);
        }

        if ($userId = $this->option('user')) {
            $query->whereKey((int) $userId);
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->error('No matching users found.');
            return self::FAILURE;
        }

        $rows = $users->map(fn (User $user) => $audit->auditUser($user));

        if ($this->option('only-mismatches')) {
            $rows = $rows->filter(fn (array $row) => ! $row['healthy'])->values();
        }

        if ($this->option('json')) {
            $this->line(json_encode($rows->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(
                ['ID', 'Email', 'Raw plan', 'Effective', 'Paid order', 'Status', 'Themes', 'Health'],
                $rows->map(function (array $row) {
                    return [
                        $row['user_id'],
                        $row['email'],
                        $row['raw_plan_key'],
                        $row['effective_plan_key'],
                        $row['latest_fulfilled_plan_order']['product_key'] ?? '—',
                        $row['plan_status'],
                        (string) $row['theme_access']['count'],
                        $row['healthy'] ? 'OK' : implode(', ', array_column($row['issues'], 'code')),
                    ];
                })->all(),
            );

            foreach ($rows->where('healthy', false) as $row) {
                $this->newLine();
                $this->warn("{$row['email']}:");
                foreach ($row['issues'] as $issue) {
                    $this->line("  - [{$issue['severity']}] {$issue['message']}");
                }
            }
        }

        $hasMismatch = $rows->contains(fn (array $row) => ! $row['healthy']);

        return $this->option('strict') && $hasMismatch ? self::FAILURE : self::SUCCESS;
    }
}
