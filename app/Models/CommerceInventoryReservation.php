<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommerceInventoryReservation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'reserved_at' => 'datetime',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function order() { return $this->belongsTo(CommerceOrder::class, 'commerce_order_id'); }
    public function item() { return $this->belongsTo(CommerceOrderItem::class, 'commerce_order_item_id'); }
}
