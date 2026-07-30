<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trial_generations', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->string('email');
            $table->string('business_name');
            $table->string('industry', 120);
            $table->string('location', 255);
            $table->text('business_description');
            $table->text('prompt')->nullable();
            $table->json('sections')->nullable();
            $table->json('generated_blocks')->nullable();
            $table->string('status', 20)->default('queued');
            $table->text('error_message')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['email', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_generations');
    }
};
