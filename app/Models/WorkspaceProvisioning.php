<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceProvisioning extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_WORKSPACE_READY = 'workspace_ready';
    public const STATUS_WEBSITE_READY = 'website_ready';
    public const STATUS_TRIAL_READY = 'trial_ready';
    public const STATUS_ACCESS_READY = 'access_ready';
    public const STATUS_PROFILE_READY = 'profile_ready';
    public const STATUS_SUBSCRIPTION_READY = 'subscription_ready';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'pending_onboarding_id',
        'payment_order_id',
        'workspace_id',
        'website_id',
        'trial_page_id',
        'bound_plan_key',
        'bound_subscription_id',
        'monthly_credits',
        'credit_balance_at_binding',
        'subscription_bound_at',
        'status',
        'attempts',
        'started_at',
        'completed_at',
        'failed_at',
        'next_retry_at',
        'last_error',
        'metadata',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'monthly_credits' => 'integer',
        'credit_balance_at_binding' => 'integer',
        'subscription_bound_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function pendingOnboarding() { return $this->belongsTo(PendingOnboarding::class); }
    public function paymentOrder() { return $this->belongsTo(PaymentOrder::class); }
    public function workspace() { return $this->belongsTo(Workspace::class); }
    public function website() { return $this->belongsTo(Website::class); }
    public function trialPage() { return $this->belongsTo(Page::class, 'trial_page_id'); }
    public function logs() { return $this->hasMany(WorkspaceProvisioningLog::class)->latest('occurred_at'); }
}
