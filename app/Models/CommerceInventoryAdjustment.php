<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceInventoryAdjustment extends Model
{
    protected $fillable = [
        'website_id', 'commerce_product_id', 'commerce_product_variant_id', 'commerce_order_id', 'user_id',
        'reason', 'quantity_delta', 'quantity_before', 'quantity_after', 'note', 'metadata',
    ];

    protected $casts = [
        'quantity_delta' => 'integer',
        'quantity_before' => 'integer',
        'quantity_after' => 'integer',
        'metadata' => 'array',
    ];

    public function product(): BelongsTo { return $this->belongsTo(CommerceProduct::class, 'commerce_product_id'); }
    public function variant(): BelongsTo { return $this->belongsTo(CommerceProductVariant::class, 'commerce_product_variant_id'); }
    public function order(): BelongsTo { return $this->belongsTo(CommerceOrder::class, 'commerce_order_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
