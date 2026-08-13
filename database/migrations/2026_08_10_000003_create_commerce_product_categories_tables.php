<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL/MariaDB DDL is not transactional. If a previous migration run
        // was interrupted after creating the first table, Laravel can leave the
        // migration marked as pending while the table already exists. Guard each
        // table independently so a retry can safely finish the migration.
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

            // Category URLs are scoped to one website. Parentage is deliberately
            // independent from the URL slug so categories can be reorganized
            // without forcing a URL change.
            $table->unique(['website_id', 'slug']);
            $table->index(['website_id', 'is_visible', 'sort_order'], 'commerce_cat_visible_sort_idx');
            $table->index(['website_id', 'parent_id', 'sort_order'], 'commerce_cat_parent_sort_idx');
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
            });
        }

        // A failed MariaDB ALTER can leave the assignment table behind without
        // its indexes. Add them separately with explicit short names so retries
        // repair the partial table instead of silently skipping it. MariaDB/MySQL
        // limits identifiers to 64 characters; Laravel's generated names for
        // this long table name exceed that limit.
        if (! Schema::hasIndex('commerce_product_category_assignments', ['product_id', 'category_id'], 'unique')) {
            Schema::table('commerce_product_category_assignments', function (Blueprint $table): void {
                $table->unique(['product_id', 'category_id'], 'commerce_cat_assign_product_category_uq');
            });
        }

        if (! Schema::hasIndex('commerce_product_category_assignments', ['category_id', 'sort_order'])) {
            Schema::table('commerce_product_category_assignments', function (Blueprint $table): void {
                $table->index(['category_id', 'sort_order'], 'commerce_cat_assign_category_sort_idx');
            });
        }

        if (! Schema::hasIndex('commerce_product_category_assignments', ['product_id', 'is_primary'])) {
            Schema::table('commerce_product_category_assignments', function (Blueprint $table): void {
                $table->index(['product_id', 'is_primary'], 'commerce_cat_assign_primary_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_product_category_assignments');
        Schema::dropIfExists('commerce_product_categories');
    }
};
