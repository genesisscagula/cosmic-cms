<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CommerceProductVariant extends Model
{
    protected $table = 'commerce_product_variants';

    protected $fillable = [
        'public_id',
        'website_id',
        'product_id',
        'combination_signature',
        'is_enabled',
        'is_default',
        'sort_order',
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
        'image_url',
        'image_alt',
        'metadata',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
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
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (CommerceProductVariant $variant): void {
            if (! $variant->public_id) {
                $variant->public_id = (string) Str::uuid();
            }
        });
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(CommerceProduct::class, 'product_id');
    }

    public function inventoryAdjustments(): HasMany
    {
        return $this->hasMany(CommerceInventoryAdjustment::class, 'commerce_product_variant_id')->latest();
    }

    public function values(): BelongsToMany
    {
        return $this->belongsToMany(
            CommerceProductOptionValue::class,
            'commerce_product_variant_values',
            'variant_id',
            'option_value_id'
        )->withPivot('option_id')->withTimestamps();
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function optionLabel(): string
    {
        $values = $this->relationLoaded('values') ? $this->values : $this->values()->with('option')->get();

        return $values
            ->sortBy(fn (CommerceProductOptionValue $value) => $value->option?->sort_order ?? 0)
            ->pluck('label')
            ->filter()
            ->implode(' / ');
    }

    public function isOnSale(?\DateTimeInterface $at = null): bool
    {
        if ($this->sale_price_minor === null) {
            return false;
        }

        $regular = $this->regular_price_minor ?? $this->product?->regular_price_minor;
        if ($regular === null || $this->sale_price_minor >= $regular) {
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

        if ($this->regular_price_minor !== null) {
            return $this->regular_price_minor;
        }

        return $this->product?->effectivePriceMinor($at);
    }

    public function effectiveWeightGrams(): ?int
    {
        return $this->weight_grams ?? $this->product?->weight_grams;
    }

    public function effectiveShippingClass(): ?string
    {
        return $this->shipping_class ?: $this->product?->shipping_class;
    }

    public function isPurchasable(): bool
    {
        if (! $this->is_enabled || ! $this->product || $this->product->status !== CommerceProduct::STATUS_PUBLISHED) {
            return false;
        }

        if ($this->effectivePriceMinor() === null) {
            return false;
        }

        if (! $this->track_inventory) {
            return $this->stock_status !== CommerceProduct::STOCK_OUT_OF_STOCK;
        }

        if (($this->stock_quantity ?? 0) > 0) {
            return true;
        }

        return $this->allow_backorders;
    }
}
