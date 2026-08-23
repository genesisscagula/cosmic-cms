<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('website_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('page_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('trial_generation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 24)->index();
            $table->string('status', 24)->default('new')->index();
            $table->text('description');
            $table->string('reporter_name')->nullable();
            $table->string('reporter_email')->nullable();
            $table->text('source_url')->nullable();
            $table->json('context')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('screenshot_path')->nullable();
            $table->string('screenshot_original_name')->nullable();
            $table->string('screenshot_mime', 100)->nullable();
            $table->unsignedBigInteger('screenshot_size')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('viewed_at')->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_reports');
    }
};
