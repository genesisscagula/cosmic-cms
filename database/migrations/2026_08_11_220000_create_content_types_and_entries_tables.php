<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_types')) {
            Schema::create('content_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('website_id')->constrained()->cascadeOnDelete();
                $table->string('name', 100);
                $table->string('singular_name', 100);
                $table->string('slug', 100);
                $table->string('icon', 40)->nullable();
                $table->text('description')->nullable();
                $table->json('schema')->nullable();
                $table->boolean('is_system')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['website_id', 'slug']);
            });
        }

        if (! Schema::hasTable('content_entries')) {
            Schema::create('content_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('website_id')->constrained()->cascadeOnDelete();
                $table->foreignId('content_type_id')->constrained('content_types')->cascadeOnDelete();
                $table->string('title');
                $table->string('slug');
                $table->text('excerpt')->nullable();
                $table->longText('content')->nullable();
                $table->string('status', 20)->default('draft');
                $table->string('category', 100)->nullable();
                $table->json('tags')->nullable();
                $table->string('featured_image_url')->nullable();
                $table->json('gallery')->nullable();
                $table->json('custom_fields')->nullable();
                $table->string('seo_title')->nullable();
                $table->text('seo_description')->nullable();
                $table->string('og_image_url')->nullable();
                $table->boolean('is_featured')->default(false);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->unique(['website_id', 'content_type_id', 'slug'], 'content_entries_site_type_slug_unique');
                $table->index(['website_id', 'content_type_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_entries');
        Schema::dropIfExists('content_types');
    }
};
