<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_packs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('owner_type', 32)->default('trial');
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->foreignId('trial_generation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('website_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 32)->default('pending');
            $table->unsignedTinyInteger('target_image_count')->default(0);
            $table->json('keywords')->nullable();
            $table->json('manifest')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['owner_type', 'owner_id']);
            $table->index('status');
        });

        Schema::table('trial_generations', function (Blueprint $table) {
            $table->foreignId('media_pack_id')->nullable()->after('page_id')->constrained('media_packs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trial_generations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('media_pack_id');
        });

        Schema::dropIfExists('media_packs');
    }
};
