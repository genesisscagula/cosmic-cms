<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('saved_page_templates')) return;

        Schema::create('saved_page_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 140);
            $table->string('slug', 160);
            $table->string('description', 500)->nullable();
            $table->string('thumbnail_url', 2048)->nullable();
            $table->string('source', 40)->default('saved');
            $table->string('template_type', 40)->default('page');
            $table->string('status', 40)->default('active');
            $table->json('blocks');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
            $table->index(['user_id', 'source', 'template_type', 'status'], 'saved_templates_library_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_page_templates');
    }
};
