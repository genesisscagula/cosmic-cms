<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commerce_shipping_zones')) {
            Schema::create('commerce_shipping_zones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('website_id')->constrained()->cascadeOnDelete();
                $table->string('name', 120);
                $table->json('countries')->nullable();
                $table->boolean('is_rest_of_world')->default(false);
                $table->boolean('is_enabled')->default(true);
                $table->unsignedInteger('priority')->default(100);
                $table->timestamps();
                $table->index(['website_id', 'is_enabled', 'priority']);
            });
        }

        if (! Schema::hasTable('commerce_shipping_rates')) {
            Schema::create('commerce_shipping_rates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shipping_zone_id')->constrained('commerce_shipping_zones')->cascadeOnDelete();
                $table->string('name', 120);
                $table->unsignedBigInteger('rate_minor')->default(0);
                $table->unsignedBigInteger('free_above_minor')->nullable();
                $table->boolean('is_enabled')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['shipping_zone_id', 'is_enabled', 'sort_order'], 'commerce_ship_rate_sort_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_shipping_rates');
        Schema::dropIfExists('commerce_shipping_zones');
    }
};
