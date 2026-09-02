<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_templates')) {
            Schema::create('marketplace_templates', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 160)->unique();
                $table->string('name', 140);
                $table->string('industry_slug', 100)->index();
                $table->string('industry_label', 120);
                $table->string('style_slug', 100)->default('premium')->index();
                $table->string('plan', 24)->default('starter')->index();
                $table->string('status', 24)->default('draft')->index();
                $table->unsignedInteger('monthly_price_cents')->default(0);
                $table->string('currency', 3)->default('USD');
                $table->unsignedSmallInteger('page_count')->default(5);
                $table->string('summary', 500)->nullable();
                $table->text('description')->nullable();
                $table->string('thumbnail_url', 2048)->nullable();
                $table->string('preview_url', 2048)->nullable();
                $table->string('theme_key', 80)->nullable();
                $table->json('theme_settings')->nullable();
                $table->json('global_header')->nullable();
                $table->json('global_footer')->nullable();
                $table->json('features')->nullable();
                $table->json('tags')->nullable();
                $table->json('seo')->nullable();
                $table->json('onboarding_schema')->nullable();
                $table->string('source_bundle_key', 160)->nullable()->index();
                $table->boolean('is_featured')->default(false)->index();
                $table->boolean('is_customizable')->default(true);
                $table->boolean('ai_personalization_enabled')->default(true);
                $table->boolean('website_care_included')->default(true);
                $table->integer('sort_order')->default(0)->index();
                $table->unsignedInteger('version')->default(1);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'industry_slug', 'plan', 'sort_order'], 'marketplace_templates_catalog_idx');
                $table->index(['status', 'is_featured', 'sort_order'], 'marketplace_templates_featured_idx');
            });
        }

        if (! Schema::hasTable('marketplace_template_pages')) {
            Schema::create('marketplace_template_pages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('marketplace_template_id')->constrained('marketplace_templates')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('marketplace_template_pages')->nullOnDelete();
                $table->string('name', 140);
                $table->string('slug', 160);
                $table->string('page_intent', 80)->default('general')->index();
                $table->string('page_style', 80)->default('premium');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_home')->default(false);
                $table->json('seo')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['marketplace_template_id', 'slug'], 'marketplace_template_pages_slug_unique');
                $table->index(['marketplace_template_id', 'sort_order'], 'marketplace_template_pages_order_idx');
            });
        }

        if (! Schema::hasTable('marketplace_template_blocks')) {
            Schema::create('marketplace_template_blocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('marketplace_template_page_id')->constrained('marketplace_template_pages')->cascadeOnDelete();
                $table->string('spark_key', 180)->index();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->json('content');
                $table->json('settings')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['marketplace_template_page_id', 'sort_order'], 'marketplace_template_blocks_order_unique');
            });
        }

        if (! Schema::hasTable('marketplace_template_navigation_items')) {
            Schema::create('marketplace_template_navigation_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('marketplace_template_id')->constrained('marketplace_templates')->cascadeOnDelete();
                $table->foreignId('marketplace_template_page_id')->nullable()->constrained('marketplace_template_pages')->nullOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('marketplace_template_navigation_items')->cascadeOnDelete();
                $table->string('label', 120);
                $table->string('url', 500)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_cta')->default(false);
                $table->string('target', 20)->default('_self');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['marketplace_template_id', 'parent_id', 'sort_order'], 'marketplace_template_navigation_order_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_template_navigation_items');
        Schema::dropIfExists('marketplace_template_blocks');
        Schema::dropIfExists('marketplace_template_pages');
        Schema::dropIfExists('marketplace_templates');
    }
};
