<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteCommerceSetting extends Model
{
    protected $fillable = [
        'website_id',
        'public_key',
        'enabled',
        'currency',
        'tax_enabled',
        'prices_include_tax',
        'tax_strategy',
        'settings',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'tax_enabled' => 'boolean',
        'prices_include_tax' => 'boolean',
        'settings' => 'array',
    ];

    public function website()
    {
        return $this->belongsTo(Website::class);
    }
}
