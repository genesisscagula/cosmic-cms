<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commerce_product_id')->constrained('commerce_products')->cascadeOnDelete();
            $table->foreignId('commerce_product_variant_id')->nullable()->constrained('commerce_product_variants', 'id', 'commerce_inv_adj_variant_fk')->cascadeOnDelete();
            $table->foreignId('commerce_order_id')->nullable()->constrained('commerce_orders')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 40); // manual | sale | restock | correction | import
            $table->integer('quantity_delta');
            $table->unsignedInteger('quantity_before')->nullable();
            $table->unsignedInteger('quantity_after')->nullable();
            $table->string('note', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['website_id', 'created_at']);
            $table->index(['commerce_product_id', 'commerce_product_variant_id'], 'commerce_inv_adj_product_variant_idx');
            $table->index(['commerce_order_id', 'reason']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_inventory_adjustments');
    }
};
