<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\SubscriptionStatus;
use Illuminate\Console\Command;

class GrantPlatformOwnerEntitlement extends Command
{
    protected $signature = 'cosmic:grant-owner-entitlement {--email=} {--plan=}';

    protected $description = 'Grant the configured Cosmic platform owner a manual plan entitlement that PayPal sync cannot downgrade.';

    public function handle(): int
    {
        $email = strtolower(trim((string) ($this->option('email') ?: config('cosmic.platform_owner_email'))));
        $plan = strtolower(trim((string) ($this->option('plan') ?: config('cosmic.platform_owner_plan', 'agency_pro'))));

        if ($email === '') {
            $this->error('No platform owner email is configured.');
            return self::FAILURE;
        }

        if (! array_key_exists($plan, (array) config('payments.plans', []))) {
            $this->error("Unknown plan [{$plan}].");
            return self::FAILURE;
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            $this->error("No user found for [{$email}].");
            return self::FAILURE;
        }

        $user->forceFill([
            'account_type' => 'platform_owner',
            'plan_key' => $plan,
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

        $this->info("Manual owner entitlement granted: {$email} -> {$plan}.");
        $this->line('Existing PayPal orders/subscriptions are preserved for audit history, but can no longer downgrade owner access.');

        return self::SUCCESS;
    }
}
