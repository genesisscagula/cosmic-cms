<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_inventory_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commerce_order_id')->constrained('commerce_orders')->cascadeOnDelete();
            $table->foreignId('commerce_order_item_id')->constrained('commerce_order_items')->cascadeOnDelete();
            $table->foreignId('commerce_product_id')->nullable()->constrained('commerce_products')->nullOnDelete();
            $table->foreignId('commerce_product_variant_id')->nullable()->constrained('commerce_product_variants')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status', 20)->default('active'); // active | consumed | released
            $table->timestamp('reserved_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->unique('commerce_order_item_id', 'commerce_inventory_reservations_item_unique');
            $table->index(['commerce_product_id', 'status', 'expires_at'], 'commerce_inventory_reservations_product_index');
            $table->index(['commerce_product_variant_id', 'status', 'expires_at'], 'commerce_inventory_reservations_variant_index');
            $table->index(['commerce_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_inventory_reservations');
    }
};
