<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Workspace;
use App\Support\SubscriptionStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProvisionComplimentaryAgencyPro extends Command
{
    protected $signature = 'cosmic:provision-joel {--password=}';
    protected $description = 'Create or refresh Joel\'s complimentary Agency Pro tester account with 10,000 credits.';

    public function handle(): int
    {
        $email = 'joel@skyrocketmarketing.com.au';
        $password = (string) ($this->option('password') ?: 'Skyrocket!Cosmic26#J');

        $user = User::query()->firstOrNew(['email' => $email]);
        $isNew = ! $user->exists;

        $user->forceFill([
            'name' => $user->name ?: 'Joel',
            'business_name' => $user->business_name ?: 'Skyrocket Marketing',
            'password' => Hash::make($password),
            'email_verified_at' => $user->email_verified_at ?: now(),
            'account_type' => 'customer',
            'onboarding_status' => 'complete',
            'credits' => 10000,
            'plan_key' => 'agency_pro',
            'plan_status' => SubscriptionStatus::ACTIVE,
            'plan_provider' => 'manual',
            'plan_renews_at' => null,
            'plan_cancel_at_period_end' => false,
            'plan_cancelled_at' => null,
            'plan_status_changed_at' => now(),
            'plan_past_due_at' => null,
            'plan_suspended_at' => null,
            'plan_expired_at' => null,
            'plan_last_synced_at' => null,
            'plan_recovery_attempted_at' => null,
            'plan_recovery_error' => null,
        ])->save();

        $workspace = $user->ownedWorkspaces()->first();
        if (! $workspace) {
            $workspaceName = 'Skyrocket Marketing';
            $workspace = Workspace::query()->create([
                'owner_user_id' => $user->id,
                'name' => $workspaceName,
                'slug' => Str::slug($workspaceName).'-'.$user->id,
            ]);
        }

        DB::table('workspace_user')->updateOrInsert(
            ['workspace_id' => $workspace->id, 'user_id' => $user->id],
            ['role' => 'owner', 'created_at' => now(), 'updated_at' => now()]
        );

        $user->websites()->whereNull('workspace_id')->update(['workspace_id' => $workspace->id]);

        $this->info(($isNew ? 'Created' : 'Updated').' complimentary Agency Pro account for '.$email.'.');
        $this->line('Workspace: '.$workspace->name.' (#'.$workspace->id.')');
        $this->line('Credits: 10,000 | Billing: manual / no renewal | Account type: customer');
        $this->warn('Temporary password was set from --password (or the command default). Share it privately and change it after first login.');

        return self::SUCCESS;
    }
}
