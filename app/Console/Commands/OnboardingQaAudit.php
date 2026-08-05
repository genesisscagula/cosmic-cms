<?php

namespace App\Console\Commands;

use App\Models\PaymentOrder;
use App\Models\PendingOnboarding;
use App\Models\User;
use App\Models\WorkspaceProvisioning;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OnboardingQaAudit extends Command
{
    protected $signature = 'cosmic:onboarding-qa
        {--user= : Limit checks to one user ID or email}
        {--strict : Treat warnings as a failed command}
        {--json : Print machine-readable JSON}';

    protected $description = 'Audit registration, payment, provisioning, trial transfer, and workspace onboarding consistency without changing data.';

    /** @var array<int, array{severity:string,code:string,message:string,context:array}> */
    private array $findings = [];

    public function handle(): int
    {
        $required = ['users', 'pending_onboardings', 'workspace_provisionings', 'payment_orders', 'workspaces', 'websites'];
        $missing = array_values(array_filter($required, fn (string $table) => ! Schema::hasTable($table)));

        if ($missing !== []) {
            $this->add('error', 'missing_tables', 'Onboarding QA cannot run because required tables are missing.', ['tables' => $missing]);
            return $this->finish();
        }

        $user = $this->resolveUser();
        if ($this->option('user') && ! $user) {
            $this->add('error', 'user_not_found', 'The requested onboarding QA user could not be found.', ['selector' => $this->option('user')]);
            return $this->finish();
        }

        $this->checkPendingOnboardingLifecycle($user);
        $this->checkProvisioningLifecycle($user);
        $this->checkWorkspaceOwnership($user);
        $this->checkTrialTransfer($user);
        $this->checkPaymentBinding($user);

        if ($this->findings === []) {
            $this->add('ok', 'onboarding_consistent', 'No onboarding consistency problems were detected.', []);
        }

        return $this->finish();
    }

    private function resolveUser(): ?User
    {
        $selector = trim((string) $this->option('user'));
        if ($selector === '') {
            return null;
        }

        return User::query()->where(function (Builder $query) use ($selector) {
            $query->where('email', $selector);
            if (ctype_digit($selector)) {
                $query->orWhereKey((int) $selector);
            }
        })->first();
    }

    private function checkPendingOnboardingLifecycle(?User $user): void
    {
        $staleHours = max(1, (int) config('cosmic-onboarding-qa.stale_pending_hours', 24));
        $stale = PendingOnboarding::query()
            ->when($user, fn (Builder $q) => $q->where('user_id', $user->id))
            ->whereIn('status', ['pending', 'payment_pending', 'processing'])
            ->where('updated_at', '<', now()->subHours($staleHours))
            ->get(['id', 'user_id', 'selected_plan', 'status', 'workspace_id', 'website_id', 'updated_at']);

        foreach ($stale as $row) {
            $this->add('warning', 'stale_onboarding', 'An onboarding has remained incomplete beyond the configured threshold.', $row->toArray());
        }

        PendingOnboarding::query()
            ->when($user, fn (Builder $q) => $q->where('user_id', $user->id))
            ->whereIn('status', ['completed', 'subscription_ready'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    if (! $row->workspace_id || ! DB::table('workspaces')->where('id', $row->workspace_id)->exists()) {
                        $this->add('error', 'completed_missing_workspace', 'A completed onboarding does not reference an existing workspace.', ['onboarding_id' => $row->id, 'workspace_id' => $row->workspace_id]);
                    }
                    if (! $row->website_id || ! DB::table('websites')->where('id', $row->website_id)->exists()) {
                        $this->add('error', 'completed_missing_website', 'A completed onboarding does not reference an existing website.', ['onboarding_id' => $row->id, 'website_id' => $row->website_id]);
                    }
                }
            });
    }

    private function checkProvisioningLifecycle(?User $user): void
    {
        $staleMinutes = max(5, (int) config('cosmic-onboarding-qa.stale_processing_minutes', 30));
        $processing = WorkspaceProvisioning::query()
            ->when($user, fn (Builder $q) => $q->where('user_id', $user->id))
            ->where('status', WorkspaceProvisioning::STATUS_PROCESSING)
            ->where('updated_at', '<', now()->subMinutes($staleMinutes))
            ->get(['id', 'user_id', 'pending_onboarding_id', 'payment_order_id', 'attempts', 'updated_at']);

        foreach ($processing as $row) {
            $this->add('error', 'stuck_provisioning', 'A workspace provisioning attempt appears stuck.', $row->toArray());
        }

        WorkspaceProvisioning::query()
            ->when($user, fn (Builder $q) => $q->where('user_id', $user->id))
            ->where('status', WorkspaceProvisioning::STATUS_COMPLETED)
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    foreach (['workspace_id' => 'workspaces', 'website_id' => 'websites'] as $column => $table) {
                        if (! $row->{$column} || ! DB::table($table)->where('id', $row->{$column})->exists()) {
                            $this->add('error', 'completed_provisioning_missing_resource', 'Completed provisioning references a missing resource.', [
                                'provisioning_id' => $row->id,
                                'column' => $column,
                                'value' => $row->{$column},
                            ]);
                        }
                    }

                    if (! $row->completed_at) {
                        $this->add('warning', 'completed_without_timestamp', 'A completed provisioning has no completion timestamp.', ['provisioning_id' => $row->id]);
                    }
                }
            });

        $duplicates = WorkspaceProvisioning::query()
            ->select('pending_onboarding_id', DB::raw('COUNT(*) as aggregate'))
            ->whereNotNull('pending_onboarding_id')
            ->when($user, fn (Builder $q) => $q->where('user_id', $user->id))
            ->groupBy('pending_onboarding_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $this->add('error', 'duplicate_provisioning', 'One onboarding is linked to multiple provisioning records.', [
                'pending_onboarding_id' => $duplicate->pending_onboarding_id,
                'count' => (int) $duplicate->aggregate,
            ]);
        }
    }

    private function checkWorkspaceOwnership(?User $user): void
    {
        PendingOnboarding::query()
            ->when($user, fn (Builder $q) => $q->where('user_id', $user->id))
            ->whereNotNull('workspace_id')
            ->whereNotNull('website_id')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $workspaceOwner = DB::table('workspaces')->where('id', $row->workspace_id)->value('owner_user_id');
                    $website = DB::table('websites')->where('id', $row->website_id)->first(['user_id', 'workspace_id']);

                    if ((int) $workspaceOwner !== (int) $row->user_id) {
                        $this->add('error', 'workspace_owner_mismatch', 'The onboarding user is not the owner of the resulting workspace.', ['onboarding_id' => $row->id, 'user_id' => $row->user_id, 'workspace_owner_user_id' => $workspaceOwner]);
                    }
                    if ($website && ((int) $website->user_id !== (int) $row->user_id || (int) $website->workspace_id !== (int) $row->workspace_id)) {
                        $this->add('error', 'website_ownership_mismatch', 'The onboarding website owner or workspace binding is inconsistent.', ['onboarding_id' => $row->id, 'website_id' => $row->website_id]);
                    }

                    if (Schema::hasTable('workspace_user')) {
                        $role = DB::table('workspace_user')->where('workspace_id', $row->workspace_id)->where('user_id', $row->user_id)->value('role');
                        if ($role !== 'owner') {
                            $this->add('error', 'owner_membership_missing', 'The onboarding user does not have an owner workspace membership.', ['onboarding_id' => $row->id, 'role' => $role]);
                        }
                    }
                }
            });
    }

    private function checkTrialTransfer(?User $user): void
    {
        if (! Schema::hasTable('trial_generations')) {
            return;
        }

        PendingOnboarding::query()
            ->when($user, fn (Builder $q) => $q->where('user_id', $user->id))
            ->whereNotNull('trial_generation_id')
            ->whereIn('status', ['completed', 'subscription_ready'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $trial = DB::table('trial_generations')->where('id', $row->trial_generation_id)->first();
                    if (! $trial) {
                        $this->add('error', 'missing_trial_generation', 'A completed onboarding references a missing trial generation.', ['onboarding_id' => $row->id, 'trial_generation_id' => $row->trial_generation_id]);
                        continue;
                    }
                    if (isset($trial->claimed_by_user_id) && (int) $trial->claimed_by_user_id !== (int) $row->user_id) {
                        $this->add('error', 'trial_claim_owner_mismatch', 'The transferred trial is claimed by a different user.', ['onboarding_id' => $row->id, 'claimed_by_user_id' => $trial->claimed_by_user_id]);
                    }
                    if (isset($trial->claimed_at) && ! $trial->claimed_at) {
                        $this->add('warning', 'trial_not_marked_claimed', 'A completed onboarding trial has no claimed timestamp.', ['onboarding_id' => $row->id]);
                    }
                }
            });
    }

    private function checkPaymentBinding(?User $user): void
    {
        WorkspaceProvisioning::query()
            ->when($user, fn (Builder $q) => $q->where('user_id', $user->id))
            ->whereNotNull('payment_order_id')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $order = PaymentOrder::query()->find($row->payment_order_id);
                    if (! $order) {
                        $this->add('error', 'missing_payment_order', 'Provisioning references a missing payment order.', ['provisioning_id' => $row->id, 'payment_order_id' => $row->payment_order_id]);
                        continue;
                    }
                    if ((int) $order->user_id !== (int) $row->user_id) {
                        $this->add('error', 'payment_user_mismatch', 'Provisioning and payment order belong to different users.', ['provisioning_id' => $row->id, 'payment_order_id' => $order->id]);
                    }
                    if (in_array($row->status, [WorkspaceProvisioning::STATUS_SUBSCRIPTION_READY, WorkspaceProvisioning::STATUS_COMPLETED], true) && (! $order->fulfilled_at || $order->status !== 'paid')) {
                        $this->add('error', 'provisioned_without_confirmed_payment', 'Provisioning reached a ready state without a confirmed fulfilled payment.', ['provisioning_id' => $row->id, 'payment_order_id' => $order->id, 'order_status' => $order->status]);
                    }
                    if ($row->bound_plan_key && (string) $row->bound_plan_key !== (string) $order->product_key) {
                        $this->add('error', 'bound_plan_mismatch', 'The provisioned plan does not match the paid order.', ['provisioning_id' => $row->id, 'bound_plan_key' => $row->bound_plan_key, 'order_plan_key' => $order->product_key]);
                    }
                }
            });
    }

    private function add(string $severity, string $code, string $message, array $context): void
    {
        $this->findings[] = compact('severity', 'code', 'message', 'context');
    }

    private function finish(): int
    {
        $errors = count(array_filter($this->findings, fn (array $finding) => $finding['severity'] === 'error'));
        $warnings = count(array_filter($this->findings, fn (array $finding) => $finding['severity'] === 'warning'));

        if ($this->option('json')) {
            $this->line(json_encode(['summary' => ['errors' => $errors, 'warnings' => $warnings], 'findings' => $this->findings], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($this->findings as $finding) {
                $line = '['.strtoupper($finding['severity']).'] '.$finding['code'].': '.$finding['message'];
                match ($finding['severity']) {
                    'error' => $this->error($line),
                    'warning' => $this->warn($line),
                    default => $this->info($line),
                };
                if ($finding['context'] !== []) {
                    $this->line('  '.json_encode($finding['context'], JSON_UNESCAPED_SLASHES));
                }
            }
            $this->newLine();
            $this->line("Onboarding QA summary: {$errors} error(s), {$warnings} warning(s).");
        }

        return ($errors > 0 || ($warnings > 0 && $this->option('strict'))) ? self::FAILURE : self::SUCCESS;
    }
}
