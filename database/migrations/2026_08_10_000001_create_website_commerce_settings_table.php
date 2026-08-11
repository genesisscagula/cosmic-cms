<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_commerce_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->unique()->constrained()->cascadeOnDelete();
            $table->uuid('public_key')->unique();
            $table->boolean('enabled')->default(false);
            $table->char('currency', 3)->default('USD');
            $table->boolean('tax_enabled')->default(false);
            $table->boolean('prices_include_tax')->default(false);
            $table->string('tax_strategy', 32)->default('manual');
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_commerce_settings');
    }
};
