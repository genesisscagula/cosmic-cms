<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CommerceProduct extends Model
{
    public const TYPE_SIMPLE = 'simple';
    public const TYPE_VARIABLE = 'variable';

    public const FULFILLMENT_PHYSICAL = 'physical';
    public const FULFILLMENT_DIGITAL = 'digital';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    public const VISIBILITY_CATALOG = 'catalog';
    public const VISIBILITY_HIDDEN = 'hidden';

    public const STOCK_IN_STOCK = 'in_stock';
    public const STOCK_OUT_OF_STOCK = 'out_of_stock';
    public const STOCK_BACKORDER = 'backorder';

    protected $table = 'commerce_products';

    protected $fillable = [
        'website_id',
        'public_id',
        'type',
        'fulfillment_type',
        'status',
        'visibility',
        'title',
        'slug',
        'short_description',
        'description',
        'regular_price_minor',
        'sale_price_minor',
        'sale_starts_at',
        'sale_ends_at',
        'sku',
        'barcode',
        'track_inventory',
        'stock_quantity',
        'low_stock_threshold',
        'allow_backorders',
        'stock_status',
        'weight_grams',
        'length_mm',
        'width_mm',
        'height_mm',
        'shipping_class',
        'taxable',
        'tax_class',
        'is_featured',
        'featured_image_url',
        'featured_image_alt',
        'seo_title',
        'seo_description',
        'metadata',
        'published_at',
    ];

    protected $casts = [
        'regular_price_minor' => 'integer',
        'sale_price_minor' => 'integer',
        'sale_starts_at' => 'datetime',
        'sale_ends_at' => 'datetime',
        'track_inventory' => 'boolean',
        'stock_quantity' => 'integer',
        'low_stock_threshold' => 'integer',
        'allow_backorders' => 'boolean',
        'weight_grams' => 'integer',
        'length_mm' => 'integer',
        'width_mm' => 'integer',
        'height_mm' => 'integer',
        'taxable' => 'boolean',
        'is_featured' => 'boolean',
        'metadata' => 'array',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CommerceProduct $product): void {
            if (! $product->public_id) {
                $product->public_id = (string) Str::uuid();
            }
        });
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(CommerceProductImage::class, 'product_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function inventoryAdjustments(): HasMany
    {
        return $this->hasMany(CommerceInventoryAdjustment::class, 'commerce_product_id')->latest();
    }

    public function options(): HasMany
    {
        return $this->hasMany(CommerceProductOption::class, 'product_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(CommerceProductVariant::class, 'product_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function enabledVariants(): HasMany
    {
        return $this->variants()->where('is_enabled', true);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            CommerceProductCategory::class,
            'commerce_product_category_assignments',
            'product_id',
            'category_id'
        )
            ->withPivot(['is_primary', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function primaryCategory(): ?CommerceProductCategory
    {
        if ($this->relationLoaded('categories')) {
            return $this->categories->firstWhere('pivot.is_primary', true) ?: $this->categories->first();
        }

        return $this->categories()
            ->orderByDesc('commerce_product_category_assignments.is_primary')
            ->orderBy('commerce_product_category_assignments.sort_order')
            ->first();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeCatalogVisible(Builder $query): Builder
    {
        return $query->where('visibility', self::VISIBILITY_CATALOG);
    }

    public function scopeForWebsite(Builder $query, int $websiteId): Builder
    {
        return $query->where('website_id', $websiteId);
    }

    public function isVariable(): bool
    {
        return $this->type === self::TYPE_VARIABLE;
    }

    public function isPhysical(): bool
    {
        return $this->fulfillment_type === self::FULFILLMENT_PHYSICAL;
    }

    public function defaultVariant(): ?CommerceProductVariant
    {
        if ($this->relationLoaded('variants')) {
            return $this->variants->firstWhere('is_default', true)
                ?: $this->variants->firstWhere('is_enabled', true);
        }

        return $this->variants()
            ->where('is_enabled', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->first();
    }

    /** @return array{min:?int,max:?int} */
    public function variantPriceRangeMinor(): array
    {
        if (! $this->isVariable()) {
            $price = $this->effectivePriceMinor();

            return ['min' => $price, 'max' => $price];
        }

        $variants = $this->relationLoaded('variants')
            ? $this->variants->where('is_enabled', true)
            : $this->enabledVariants()->get();

        $prices = $variants
            ->map(fn (CommerceProductVariant $variant): ?int => $variant->effectivePriceMinor())
            ->filter(fn ($price): bool => $price !== null)
            ->values();

        return [
            'min' => $prices->isEmpty() ? null : $prices->min(),
            'max' => $prices->isEmpty() ? null : $prices->max(),
        ];
    }

    public function requiresShipping(): bool
    {
        return $this->isPhysical();
    }

    public function isOnSale(?\DateTimeInterface $at = null): bool
    {
        if ($this->sale_price_minor === null || $this->regular_price_minor === null) {
            return false;
        }

        if ($this->sale_price_minor >= $this->regular_price_minor) {
            return false;
        }

        $at ??= now();

        if ($this->sale_starts_at && $at < $this->sale_starts_at) {
            return false;
        }

        if ($this->sale_ends_at && $at > $this->sale_ends_at) {
            return false;
        }

        return true;
    }

    public function effectivePriceMinor(?\DateTimeInterface $at = null): ?int
    {
        if ($this->isOnSale($at)) {
            return $this->sale_price_minor;
        }

        return $this->regular_price_minor;
    }

    public function isPurchasable(): bool
    {
        if ($this->status !== self::STATUS_PUBLISHED) {
            return false;
        }

        if ($this->isVariable()) {
            if ($this->relationLoaded('variants')) {
                return $this->variants->contains(fn (CommerceProductVariant $variant): bool => $variant->isPurchasable());
            }

            return $this->enabledVariants()
                ->get()
                ->contains(fn (CommerceProductVariant $variant): bool => $variant->isPurchasable());
        }

        if ($this->effectivePriceMinor() === null) {
            return false;
        }

        if (! $this->track_inventory) {
            return $this->stock_status !== self::STOCK_OUT_OF_STOCK;
        }

        if (($this->stock_quantity ?? 0) > 0) {
            return true;
        }

        return $this->allow_backorders;
    }
}
