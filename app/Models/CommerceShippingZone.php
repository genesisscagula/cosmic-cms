<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommerceShippingZone extends Model
{
    protected $fillable = ['website_id', 'name', 'countries', 'is_rest_of_world', 'is_enabled', 'priority'];

    protected $casts = [
        'countries' => 'array',
        'is_rest_of_world' => 'boolean',
        'is_enabled' => 'boolean',
        'priority' => 'integer',
    ];

    public function website() { return $this->belongsTo(Website::class); }
    public function rates() { return $this->hasMany(CommerceShippingRate::class, 'shipping_zone_id')->orderBy('sort_order')->orderBy('id'); }
}
