<?php

namespace App\Services;

use App\Models\User;
use App\Support\SubscriptionStatus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AccountDataService
{
    public function __construct(
        private readonly PlanRegistry $plans,
        private readonly CreditWalletService $creditWallet,
    ) {
    }
    public function credits(User $user): array
    {
        $effectivePlanKey = $user->effectivePlanKey();
        $plan = $this->plans->find($effectivePlanKey) ?? [];
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $usageByCategory = $user->creditTransactions()
            ->selectRaw("COALESCE(category, 'other') as category, ABS(SUM(amount)) as total")
            ->where('amount', '<', 0)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->groupBy('category')
            ->pluck('total', 'category')
            ->map(fn ($value) => (int) $value)
            ->all();

        return [
            'current_balance' => $this->creditWallet->balance($user),
            'signup_included' => (int) ($plan['credits'] ?? 0),
            'purchased_total' => (int) $user->creditTransactions()
                ->where('type', 'credit')
                ->where(function ($query) {
                    $query->where('metadata->product_type', 'credits')
                        ->orWhere('reference', 'like', 'dev-credit-purchase-%');
                })
                ->sum('amount'),
            'used_this_month' => abs((int) $user->creditTransactions()
                ->where('amount', '<', 0)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('amount')),
            'granted_this_month' => (int) $user->creditTransactions()
                ->where('amount', '>', 0)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('amount'),
            'period_label' => now()->format('F Y'),
            'usage_by_category' => $usageByCategory,
            'wallet_scope' => 'account',
        ];
    }

    public function subscription(User $user, ?object $order = null): ?array
    {
        $effectivePlanKey = $user->effectivePlanKey();
        $plan = config("payments.plans.{$effectivePlanKey}", []);
        $status = $user->isPlatformOwner()
            ? SubscriptionStatus::ACTIVE
            : SubscriptionStatus::normalize($user->plan_status);

        return [
            'key' => $effectivePlanKey,
            'label' => $plan['label'] ?? Str::headline($effectivePlanKey),
            'credits' => (int) ($plan['credits'] ?? 0),
            'price_usd' => (float) ($plan['price_usd'] ?? 0),
            'billing_cycle' => 'Monthly',
            'status' => $status,
            'status_label' => Str::headline(str_replace('_', ' ', $status)),
            'status_badge' => SubscriptionStatus::badge($status, (bool) $user->plan_cancel_at_period_end),
            'provider' => $user->isPlatformOwner() ? 'manual' : $user->plan_provider,
            'manual_owner_entitlement' => $user->isPlatformOwner(),
            'renews_at' => $user->plan_renews_at?->toIso8601String(),
            'subscription_id' => $order?->external_subscription_id,
            'order_reference' => $order?->reference,
            'order_status' => $order?->status,
            'started_at' => ($order?->paid_at ?? $order?->fulfilled_at ?? $order?->created_at)?->toIso8601String(),
            'cancel_at_period_end' => (bool) $user->plan_cancel_at_period_end,
            'cancelled_at' => $user->plan_cancelled_at?->toIso8601String(),
            'has_access' => SubscriptionStatus::grantsAccess($status, (bool) $user->plan_cancel_at_period_end, $user->plan_renews_at),
            'status_changed_at' => $user->plan_status_changed_at?->toIso8601String(),
            'past_due_at' => $user->plan_past_due_at?->toIso8601String(),
            'suspended_at' => $user->plan_suspended_at?->toIso8601String(),
            'expired_at' => $user->plan_expired_at?->toIso8601String(),
            'last_synced_at' => $user->plan_last_synced_at?->toIso8601String(),
            'recovery_attempted_at' => $user->plan_recovery_attempted_at?->toIso8601String(),
            'recovery_error' => $user->plan_recovery_error,
            'can_recover' => in_array($status, [SubscriptionStatus::PAST_DUE, SubscriptionStatus::SUSPENDED], true),
            'last_payment_at' => $user->billingTransactions()
                ->where('status', 'completed')
                ->latest('occurred_at')
                ->first()?->occurred_at?->toIso8601String(),
        ];
    }

    public function profile(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_path ? Storage::disk('public')->url($user->avatar_path) : null,
            'business_name' => $user->business_name,
            'phone' => $user->phone,
            'industry' => $user->industry,
            'location' => $user->location,
            'timezone' => $user->timezone,
            'locale' => $user->locale,
            'member_since' => $user->created_at?->toIso8601String(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'profile_completed_at' => $user->profile_completed_at?->toIso8601String(),
        ];
    }

    public function workspace(User $user): array
    {
        $workspace = $user->ownedWorkspaces()->with(['owner:id,name,email', 'users:id,name,email'])->first()
            ?? $user->workspaces()->with(['owner:id,name,email', 'users:id,name,email'])->first();

        $websites = $workspace
            ? $workspace->websites()->withCount(['pages', 'pages as published_pages_count' => fn ($query) => $query->where('status', 'published')])->latest('updated_at')->get()
            : $user->websites()->withCount(['pages', 'pages as published_pages_count' => fn ($query) => $query->where('status', 'published')])->latest('updated_at')->get();

        return [
            'id' => $workspace?->id,
            'name' => $workspace?->name ?? (($user->business_name ?: $user->name).' Workspace'),
            'slug' => $workspace?->slug,
            'role' => $workspace?->roleFor($user) ?? 'owner',
            'status' => $websites->isNotEmpty() ? 'Active' : 'Setup required',
            'created_at' => $workspace?->created_at?->toIso8601String(),
            'owner' => [
                'name' => $workspace?->owner?->name ?? $user->name,
                'email' => $workspace?->owner?->email ?? $user->email,
            ],
            'members_count' => $workspace ? $workspace->users->count() : 1,
            'websites_count' => $websites->count(),
            'published_websites_count' => $websites->where('published_pages_count', '>', 0)->count(),
            'websites' => $websites->map(fn ($website) => [
                'id' => $website->id,
                'name' => $website->name,
                'industry' => $website->industry,
                'location' => $website->location,
                'domain' => $website->domain,
                'status' => $website->published_pages_count > 0 ? 'Published' : 'Draft',
                'pages_count' => (int) $website->pages_count,
                'published_pages_count' => (int) $website->published_pages_count,
                'deployment_status' => $website->last_deployed_at ? 'Deployed' : ($website->deployment_verified_at ? 'Connected' : 'Not connected'),
                'updated_at' => $website->updated_at?->toIso8601String(),
            ])->values(),
        ];
    }

    public function settings(User $user): array
    {
        return [
            'websites' => $user->websites()->latest('updated_at')->get()->map(function ($website) {
                $settings = $website->settings ?? [];

                return [
                    'id' => $website->id,
                    'name' => $website->name,
                    'domain' => $website->domain,
                    'industry' => $website->industry,
                    'location' => $website->location,
                    'business_description' => $website->business_description,
                    'contact_email' => $website->contact_email,
                    'contact_phone' => $website->contact_phone,
                    'timezone' => $website->timezone,
                    'locale' => $website->locale,
                    'business_name' => $settings['business_name'] ?? $website->name,
                    'address' => $settings['address'] ?? $website->location,
                    'company_name' => $settings['company_name'] ?? null,
                    'owner_name' => $settings['owner_name'] ?? null,
                    'registration_number' => $settings['registration_number'] ?? null,
                    'vat_number' => $settings['vat_number'] ?? null,
                    'logo_url' => filled($settings['logo_path'] ?? null) ? Storage::disk('public')->url($settings['logo_path']) : null,
                    'favicon_url' => filled($settings['favicon_path'] ?? null) ? Storage::disk('public')->url($settings['favicon_path']) : null,
                ];
            })->values(),
        ];
    }
}
