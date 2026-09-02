<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceTemplateNavigationItem extends Model
{
    protected $fillable = [
        'marketplace_template_id', 'marketplace_template_page_id', 'parent_id', 'label', 'url',
        'sort_order', 'is_cta', 'target', 'metadata',
    ];

    protected $casts = [
        'is_cta' => 'boolean',
        'metadata' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(MarketplaceTemplate::class, 'marketplace_template_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(MarketplaceTemplatePage::class, 'marketplace_template_page_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }
}
