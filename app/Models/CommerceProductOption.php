<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommerceProductOption extends Model
{
    public const DISPLAY_SELECT = 'select';
    public const DISPLAY_BUTTONS = 'buttons';
    public const DISPLAY_SWATCH = 'swatch';

    protected $table = 'commerce_product_options';

    protected $fillable = [
        'product_id',
        'name',
        'slug',
        'display_type',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(CommerceProduct::class, 'product_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(CommerceProductOptionValue::class, 'option_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function activeValues(): HasMany
    {
        return $this->values()->where('is_active', true);
    }
}
