<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceTaxRule extends Model
{
    protected $fillable = [
        'website_id', 'name', 'country_code', 'region_code', 'tax_class',
        'rate_basis_points', 'tax_shipping', 'is_enabled', 'priority',
    ];

    protected $casts = [
        'rate_basis_points' => 'integer',
        'tax_shipping' => 'boolean',
        'is_enabled' => 'boolean',
        'priority' => 'integer',
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
