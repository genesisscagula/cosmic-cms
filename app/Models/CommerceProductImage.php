<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceProductImage extends Model
{
    protected $table = 'commerce_product_images';

    protected $fillable = [
        'product_id',
        'url',
        'alt_text',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(CommerceProduct::class, 'product_id');
    }
}
