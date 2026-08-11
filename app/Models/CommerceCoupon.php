<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommerceCoupon extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'product_ids' => 'array',
            'category_ids' => 'array',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function website() { return $this->belongsTo(Website::class); }
    public function usages() { return $this->hasMany(CommerceCouponUsage::class); }
}
