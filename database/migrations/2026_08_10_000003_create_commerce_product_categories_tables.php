<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commerce_product_categories')) {
            Schema::create('commerce_product_categories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('website_id')->constrained()->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('commerce_product_categories')->nullOnDelete();
                $table->uuid('public_id')->unique();
                $table->string('name');
                $table->string('slug');
                $table->text('description')->nullable();
                $table->string('image_url', 2048)->nullable();
                $table->string('image_alt')->nullable();
                $table->string('banner_image_url', 2048)->nullable();
                $table->string('banner_image_alt')->nullable();
                $table->string('seo_title')->nullable();
                $table->text('seo_description')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_visible')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['website_id', 'slug'], 'commerce_cat_website_slug_unique');
                $table->index(['website_id', 'is_visible', 'sort_order'], 'commerce_cat_visibility_idx');
                $table->index(['website_id', 'parent_id', 'sort_order'], 'commerce_cat_parent_idx');
            });
        }

        if (! Schema::hasTable('commerce_product_category_assignments')) {
            Schema::create('commerce_product_category_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('product_id')->constrained('commerce_products')->cascadeOnDelete();
                $table->foreignId('category_id')->constrained('commerce_product_categories')->cascadeOnDelete();
                $table->boolean('is_primary')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                // Keep names below MariaDB/MySQL's 64-character identifier limit.
                $table->unique(['product_id', 'category_id'], 'commerce_prod_cat_unique');
                $table->index(['category_id', 'sort_order'], 'commerce_prod_cat_sort_idx');
                $table->index(['product_id', 'is_primary'], 'commerce_prod_primary_idx');
            });
        } else {
            // Repair a table left behind by an interrupted/older migration.
            Schema::table('commerce_product_category_assignments', function (Blueprint $table): void {
                if (! Schema::hasIndex('commerce_product_category_assignments', 'commerce_prod_cat_unique')) {
                    $table->unique(['product_id', 'category_id'], 'commerce_prod_cat_unique');
                }

                if (! Schema::hasIndex('commerce_product_category_assignments', 'commerce_prod_cat_sort_idx')) {
                    $table->index(['category_id', 'sort_order'], 'commerce_prod_cat_sort_idx');
                }

                if (! Schema::hasIndex('commerce_product_category_assignments', 'commerce_prod_primary_idx')) {
                    $table->index(['product_id', 'is_primary'], 'commerce_prod_primary_idx');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_product_category_assignments');
        Schema::dropIfExists('commerce_product_categories');
    }
};
