<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CommerceProductOptionValue extends Model
{
    protected $table = 'commerce_product_option_values';

    protected $fillable = [
        'option_id',
        'label',
        'slug',
        'swatch_hex',
        'image_url',
        'is_active',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    public function option(): BelongsTo
    {
        return $this->belongsTo(CommerceProductOption::class, 'option_id');
    }

    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(
            CommerceProductVariant::class,
            'commerce_product_variant_values',
            'option_value_id',
            'variant_id'
        )->withPivot('option_id')->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
