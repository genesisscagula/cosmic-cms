<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceCheckout extends Model
{
    public const STATUS_SELECTED = 'selected';
    public const STATUS_ACCOUNT_CREATED = 'account_created';
    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PAYMENT_CANCELLED = 'payment_cancelled';
    public const STATUS_PAID = 'paid';
    public const STATUS_SUBSCRIPTION_READY = 'subscription_ready';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'uuid',
        'user_id',
        'marketplace_template_id',
        'pending_onboarding_id',
        'payment_order_id',
        'selected_plan',
        'status',
        'amount_minor',
        'currency',
        'source',
        'metadata',
        'expires_at',
        'paid_at',
        'completed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MarketplaceTemplate::class, 'marketplace_template_id');
    }

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(PendingOnboarding::class, 'pending_onboarding_id');
    }

    public function paymentOrder(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class, 'payment_order_id');
    }
}
