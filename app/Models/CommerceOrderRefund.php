<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommerceOrderRefund extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'provider_payload' => 'array',
            'refunded_at' => 'datetime',
            'provider_synced_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(CommerceOrder::class, 'commerce_order_id');
    }
}
