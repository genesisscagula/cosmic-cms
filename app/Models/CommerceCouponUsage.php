<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommerceCouponUsage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['used_at' => 'datetime'];
    }

    public function coupon() { return $this->belongsTo(CommerceCoupon::class, 'commerce_coupon_id'); }
    public function order() { return $this->belongsTo(CommerceOrder::class, 'commerce_order_id'); }
    public function website() { return $this->belongsTo(Website::class); }
}
