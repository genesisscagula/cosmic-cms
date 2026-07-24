<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('slug');
            $table->string('status')->default('draft');
            $table->json('blocks')->nullable();
            $table->json('published_blocks')->nullable();
            $table->longText('published_html')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('last_published_at')->nullable();
            $table->text('publish_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
