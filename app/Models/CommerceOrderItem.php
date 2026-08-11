<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommerceOrderItem extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['snapshot' => 'array']; }
    public function order() { return $this->belongsTo(CommerceOrder::class, 'commerce_order_id'); }
    public function product() { return $this->belongsTo(CommerceProduct::class, 'commerce_product_id'); }
    public function variant() { return $this->belongsTo(CommerceProductVariant::class, 'commerce_product_variant_id'); }
    public function inventoryReservation() { return $this->hasOne(CommerceInventoryReservation::class, 'commerce_order_item_id'); }
}
