<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use App\Models\Website;
use App\Models\Page;
use App\Models\Workspace;
use App\Models\CreditTransaction;
use App\Models\CosmicUnlock;
use App\Models\Spark;
use App\Services\PlanCapabilityService;
use App\Services\PlanEntitlementService;
use App\Services\PersonalPlanEntitlementService;
use App\Services\AgencyPlanEntitlementService;
use App\Cosmic\Plans\PlanDefinition;
use App\Cosmic\Plans\PlanResolver;
use App\Cosmic\Capabilities\CapabilityDecision;
use App\Cosmic\Capabilities\CapabilityEngine;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'business_name',
        'email',
        'avatar_path',
        'location',
        'phone',
        'industry',
        'timezone',
        'locale',
        'profile_settings',
        'profile_completed_at',
        'password',
        'account_type',
        'onboarding_status',
        'credits',
        'plan_key',
        'plan_status',
        'plan_provider',
        'plan_renews_at',
        'plan_cancel_at_period_end',
        'plan_cancelled_at',
        'plan_status_changed_at',
        'plan_past_due_at',
        'plan_suspended_at',
        'plan_expired_at',
        'plan_last_synced_at',
        'plan_recovery_attempted_at',
        'plan_recovery_error',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'account_type',
        'onboarding_status',
        'credits',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'profile_settings' => 'array',
            'profile_completed_at' => 'datetime',
            'password' => 'hashed',
            'credits' => 'integer',
            'plan_renews_at' => 'datetime',
            'plan_cancel_at_period_end' => 'boolean',
            'plan_cancelled_at' => 'datetime',
            'plan_status_changed_at' => 'datetime',
            'plan_past_due_at' => 'datetime',
            'plan_suspended_at' => 'datetime',
            'plan_expired_at' => 'datetime',
            'plan_last_synced_at' => 'datetime',
            'plan_recovery_attempted_at' => 'datetime',
        ];
    }

    // --- KINING MGA RELASYON DAPAT NAA SA SULOD DINHI ---

    public function websites()
    {
        return $this->hasMany(Website::class);
    }

    public function pages()
    {
        return $this->hasMany(Page::class);
    }

    public function ownedWorkspaces()
    {
        return $this->hasMany(Workspace::class, 'owner_user_id');
    }

    public function workspaces()
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function pendingOnboarding()
    {
        return $this->hasOne(PendingOnboarding::class);
    }

    public function creditTransactions()
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function paymentOrders()
    {
        return $this->hasMany(PaymentOrder::class);
    }

    public function billingTransactions()
    {
        return $this->hasMany(BillingTransaction::class);
    }

    public function workspaceProvisionings()
    {
        return $this->hasMany(WorkspaceProvisioning::class);
    }

    public function sparkFavorites()
    {
        return $this->hasMany(CosmicSparkFavorite::class);
    }

    public function cosmicUnlocks()
    {
        return $this->hasMany(CosmicUnlock::class);
    }

    public function sparks()
    {
        return $this->belongsToMany(Spark::class, 'user_sparks')
            ->withPivot(['credits_paid', 'unlocked_at'])
            ->withTimestamps();
    }

    public function plan(): PlanDefinition
    {
        return app(PlanResolver::class)->forUser($this);
    }

    public function planCapabilities(): array
    {
        return app(PlanCapabilityService::class)->forUser($this);
    }

    public function planEntitlements(): array
    {
        return app(PlanEntitlementService::class)->summary($this);
    }

    public function personalPlanEntitlements(): array
    {
        return app(PersonalPlanEntitlementService::class)->summary($this);
    }

    public function agencyPlanEntitlements(): array
    {
        return app(AgencyPlanEntitlementService::class)->summary($this);
    }

    public function hasPlanCapability(string $capability, mixed $expected = true): bool
    {
        return app(CapabilityEngine::class)->allows($this, $capability, $expected);
    }

    public function planCapability(string $capability, mixed $default = null): mixed
    {
        return app(CapabilityEngine::class)->value($this, $capability, $default);
    }

    public function planCapabilityDecision(string $capability, mixed $expected = true): CapabilityDecision
    {
        return app(CapabilityEngine::class)->decide($this, $capability, $expected);
    }

    public function isWithinPlanLimit(string $capability, int $currentUsage, int $increment = 1): bool
    {
        return app(CapabilityEngine::class)->withinLimit($this, $capability, $currentUsage, $increment)->allowed;
    }

    public function isPlatformOwner(): bool
    {
        return $this->account_type === 'platform_owner'
            || strtolower((string) $this->email) === strtolower((string) config('cosmic.platform_owner_email'));
    }

    public function isClient(): bool
    {
        return $this->account_type === 'client';
    }
    public function assignedWebsites()
    {
        return $this->belongsToMany(Website::class, 'website_user')
            ->withPivot('assigned_by_user_id')
            ->withTimestamps();
    }

}
