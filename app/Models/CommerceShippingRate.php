<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommerceShippingRate extends Model
{
    protected $fillable = ['shipping_zone_id', 'name', 'rate_minor', 'free_above_minor', 'is_enabled', 'sort_order', 'metadata'];

    protected $casts = [
        'rate_minor' => 'integer',
        'free_above_minor' => 'integer',
        'is_enabled' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    public function zone() { return $this->belongsTo(CommerceShippingZone::class, 'shipping_zone_id'); }
}
