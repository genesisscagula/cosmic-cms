<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commerce_product_options')) {
            Schema::create('commerce_product_options', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('commerce_products')->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('slug', 120);
                $table->string('display_type', 24)->default('select'); // select | buttons | swatch
                $table->boolean('is_required')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['product_id', 'slug']);
                $table->index(['product_id', 'sort_order']);
            });
        }

        if (! Schema::hasTable('commerce_product_option_values')) {
            Schema::create('commerce_product_option_values', function (Blueprint $table) {
                $table->id();
                $table->foreignId('option_id')->constrained('commerce_product_options')->cascadeOnDelete();
                $table->string('label', 120);
                $table->string('slug', 120);
                $table->string('swatch_hex', 16)->nullable();
                $table->string('image_url', 2048)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['option_id', 'slug']);
                $table->index(['option_id', 'is_active', 'sort_order'], 'commerce_optval_active_sort_idx');
            });
        }

        if (! Schema::hasTable('commerce_product_variants')) {
            Schema::create('commerce_product_variants', function (Blueprint $table) {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('website_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('commerce_products')->cascadeOnDelete();
                $table->char('combination_signature', 64);

                $table->boolean('is_enabled')->default(true);
                $table->boolean('is_default')->default(false);
                $table->unsignedInteger('sort_order')->default(0);

                // Null price means inherit the parent product price. This keeps common
                // variants lightweight while still allowing per-variant overrides.
                $table->unsignedBigInteger('regular_price_minor')->nullable();
                $table->unsignedBigInteger('sale_price_minor')->nullable();
                $table->timestamp('sale_starts_at')->nullable();
                $table->timestamp('sale_ends_at')->nullable();

                $table->string('sku', 120)->nullable();
                $table->string('barcode', 120)->nullable();

                $table->boolean('track_inventory')->default(false);
                $table->unsignedInteger('stock_quantity')->nullable();
                $table->unsignedInteger('low_stock_threshold')->nullable();
                $table->boolean('allow_backorders')->default(false);
                $table->string('stock_status', 24)->default('in_stock');

                // Null shipping values inherit the parent product values.
                $table->unsignedInteger('weight_grams')->nullable();
                $table->unsignedInteger('length_mm')->nullable();
                $table->unsignedInteger('width_mm')->nullable();
                $table->unsignedInteger('height_mm')->nullable();
                $table->string('shipping_class', 120)->nullable();

                $table->string('image_url', 2048)->nullable();
                $table->string('image_alt')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['product_id', 'combination_signature'], 'commerce_variant_signature_unique');
                $table->unique(['website_id', 'sku'], 'commerce_variant_website_sku_unique');
                $table->index(['product_id', 'is_enabled', 'sort_order'], 'commerce_variant_product_sort_idx');
                $table->index(['website_id', 'stock_status'], 'commerce_variant_stock_idx');
            });
        }

        if (! Schema::hasTable('commerce_product_variant_values')) {
            Schema::create('commerce_product_variant_values', function (Blueprint $table) {
                $table->id();
                $table->foreignId('variant_id')->constrained('commerce_product_variants')->cascadeOnDelete();
                $table->foreignId('option_id')->constrained('commerce_product_options')->cascadeOnDelete();
                $table->foreignId('option_value_id')->constrained('commerce_product_option_values')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['variant_id', 'option_id'], 'commerce_variant_one_value_per_option');
                $table->unique(['variant_id', 'option_value_id'], 'commerce_variant_value_unique');
                $table->index(['option_value_id', 'variant_id'], 'commerce_variant_value_lookup_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_product_variant_values');
        Schema::dropIfExists('commerce_product_variants');
        Schema::dropIfExists('commerce_product_option_values');
        Schema::dropIfExists('commerce_product_options');
    }
};
