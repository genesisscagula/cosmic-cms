<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceTemplate extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    public const PLAN_STARTER = 'starter';
    public const PLAN_GROWTH = 'growth';
    public const PLAN_PRO = 'pro';

    protected $fillable = [
        'slug', 'name', 'industry_slug', 'industry_label', 'style_slug', 'plan', 'status',
        'monthly_price_cents', 'credit_price', 'currency', 'page_count', 'summary', 'description',
        'thumbnail_url', 'preview_url', 'theme_key', 'theme_settings', 'global_header',
        'global_footer', 'features', 'tags', 'seo', 'onboarding_schema', 'source_bundle_key',
        'is_featured', 'is_customizable', 'ai_personalization_enabled', 'website_care_included',
        'sort_order', 'version', 'published_at',
    ];

    protected $casts = [
        'theme_settings' => 'array',
        'global_header' => 'array',
        'global_footer' => 'array',
        'features' => 'array',
        'tags' => 'array',
        'seo' => 'array',
        'onboarding_schema' => 'array',
        'is_featured' => 'boolean',
        'is_customizable' => 'boolean',
        'ai_personalization_enabled' => 'boolean',
        'website_care_included' => 'boolean',
        'credit_price' => 'integer',
        'published_at' => 'datetime',
    ];

    public function pages(): HasMany
    {
        return $this->hasMany(MarketplaceTemplatePage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function navigationItems(): HasMany
    {
        return $this->hasMany(MarketplaceTemplateNavigationItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /** Legacy monthly_price_cents is intentionally retained for migration compatibility only. */
    public function getCreditPriceLabelAttribute(): string
    {
        return number_format((int) $this->credit_price).' Cosmic Credits';
    }
}
