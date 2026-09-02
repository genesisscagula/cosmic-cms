<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceTemplatePage extends Model
{
    protected $fillable = [
        'marketplace_template_id', 'parent_id', 'name', 'slug', 'page_intent', 'page_style',
        'sort_order', 'is_home', 'seo', 'metadata',
    ];

    protected $casts = [
        'is_home' => 'boolean',
        'seo' => 'array',
        'metadata' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(MarketplaceTemplate::class, 'marketplace_template_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(MarketplaceTemplateBlock::class)->orderBy('sort_order')->orderBy('id');
    }
}
