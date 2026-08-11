<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_products', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();

            $table->string('type', 24)->default('simple'); // simple | variable
            $table->string('fulfillment_type', 24)->default('physical'); // physical | digital
            $table->string('status', 24)->default('draft'); // draft | published | archived
            $table->string('visibility', 24)->default('catalog'); // catalog | hidden

            $table->string('title');
            $table->string('slug');
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();

            // Prices are stored in the website currency's minor unit. This avoids
            // floating-point money errors and keeps checkout math deterministic.
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
            $table->string('stock_status', 24)->default('in_stock'); // in_stock | out_of_stock | backorder

            // Canonical shipping units: grams and millimetres. UI can display
            // user-friendly units without creating ambiguity in rate calculations.
            $table->unsignedInteger('weight_grams')->nullable();
            $table->unsignedInteger('length_mm')->nullable();
            $table->unsignedInteger('width_mm')->nullable();
            $table->unsignedInteger('height_mm')->nullable();
            $table->string('shipping_class', 120)->nullable();

            $table->boolean('taxable')->default(true);
            $table->string('tax_class', 120)->nullable();
            $table->boolean('is_featured')->default(false);

            $table->string('featured_image_url', 2048)->nullable();
            $table->string('featured_image_alt')->nullable();

            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['website_id', 'slug']);
            $table->unique(['website_id', 'sku']);
            $table->index(['website_id', 'status']);
            $table->index(['website_id', 'type']);
            $table->index(['website_id', 'stock_status']);
            $table->index(['website_id', 'is_featured']);
        });

        Schema::create('commerce_product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('commerce_products')->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_product_images');
        Schema::dropIfExists('commerce_products');
    }
};
