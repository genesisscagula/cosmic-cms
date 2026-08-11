<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CommerceProductCategory extends Model
{
    protected $table = 'commerce_product_categories';

    protected $fillable = [
        'website_id',
        'parent_id',
        'public_id',
        'name',
        'slug',
        'description',
        'image_url',
        'image_alt',
        'banner_image_url',
        'banner_image_alt',
        'seo_title',
        'seo_description',
        'sort_order',
        'is_visible',
        'metadata',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_visible' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (CommerceProductCategory $category): void {
            if (! $category->public_id) {
                $category->public_id = (string) Str::uuid();
            }
        });
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            CommerceProduct::class,
            'commerce_product_category_assignments',
            'category_id',
            'product_id'
        )
            ->withPivot(['is_primary', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function scopeForWebsite(Builder $query, int $websiteId): Builder
    {
        return $query->where('website_id', $websiteId);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function storefrontPath(): string
    {
        return '/shop/category/'.rawurlencode($this->slug);
    }

    public function canonicalSeoTitle(): string
    {
        return trim((string) ($this->seo_title ?: $this->name));
    }
}
