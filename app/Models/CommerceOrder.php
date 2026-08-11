<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommerceOrder extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'prices_include_tax' => 'boolean',
            'billing_address' => 'array',
            'shipping_address' => 'array',
            'tax_snapshot' => 'array',
            'metadata' => 'array',
            'paid_at' => 'datetime',
            'payment_attempted_at' => 'datetime',
            'checkout_expires_at' => 'datetime',
            'payment_recovery_last_attempt_at' => 'datetime',
            'payment_recovery_next_attempt_at' => 'datetime',
            'payment_recovered_at' => 'datetime',
            'payment_attention_required_at' => 'datetime',
            'order_confirmation_sent_at' => 'datetime',
            'merchant_notification_sent_at' => 'datetime',
            'last_customer_notification_at' => 'datetime',
            'notification_last_attempt_at' => 'datetime',
            'notification_next_attempt_at' => 'datetime',
            'notification_attention_required_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function website() { return $this->belongsTo(Website::class); }
    public function items() { return $this->hasMany(CommerceOrderItem::class); }
    public function refunds() { return $this->hasMany(CommerceOrderRefund::class); }
    public function events() { return $this->hasMany(CommerceOrderEvent::class)->latest('created_at')->latest('id'); }
    public function inventoryReservations() { return $this->hasMany(CommerceInventoryReservation::class, 'commerce_order_id'); }
    public function coupon() { return $this->belongsTo(CommerceCoupon::class, 'commerce_coupon_id'); }
    public function couponUsage() { return $this->hasOne(CommerceCouponUsage::class, 'commerce_order_id'); }
}
