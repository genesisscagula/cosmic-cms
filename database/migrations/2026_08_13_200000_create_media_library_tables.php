<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('media_folders')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['website_id', 'parent_id', 'sort_order'], 'media_folders_tree_idx');
            $table->index(['website_id', 'name'], 'media_folders_name_idx');
        });

        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('media_folders')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 32)->default('upload');
            $table->string('kind', 64)->nullable();
            $table->string('disk', 32)->default('public');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('filename', 255);
            $table->string('mime_type', 120)->nullable();
            $table->string('extension', 16)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('checksum_sha256', 64)->nullable();
            $table->string('alt_text', 255)->nullable();
            $table->text('caption')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['website_id', 'folder_id', 'created_at'], 'media_assets_folder_idx');
            $table->index(['website_id', 'source', 'created_at'], 'media_assets_source_idx');
            $table->index(['website_id', 'mime_type'], 'media_assets_mime_idx');
            $table->index(['website_id', 'original_name'], 'media_assets_name_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('media_folders');
    }
};
