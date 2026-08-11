<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('order_number', 40)->unique();
            $table->string('status', 24)->default('pending')->index();
            $table->string('payment_status', 24)->default('pending')->index();
            $table->string('payment_provider', 24)->default('paypal');
            $table->string('external_checkout_id')->nullable()->index();
            $table->string('external_payment_id')->nullable()->index();
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('shipping_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->boolean('prices_include_tax')->default(false);
            $table->string('shipping_method')->nullable();
            $table->string('customer_email');
            $table->string('customer_first_name', 120);
            $table->string('customer_last_name', 120);
            $table->json('billing_address')->nullable();
            $table->json('shipping_address')->nullable();
            $table->json('tax_snapshot')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['website_id', 'created_at']);
        });

        Schema::create('commerce_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commerce_order_id')->constrained('commerce_orders')->cascadeOnDelete();
            $table->foreignId('commerce_product_id')->nullable()->constrained('commerce_products')->nullOnDelete();
            $table->foreignId('commerce_product_variant_id')->nullable()->constrained('commerce_product_variants')->nullOnDelete();
            $table->uuid('product_public_id');
            $table->uuid('variant_public_id')->nullable();
            $table->string('title');
            $table->string('sku')->nullable();
            $table->string('option_label')->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('line_subtotal_minor');
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('line_total_minor');
            $table->json('snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_order_items');
        Schema::dropIfExists('commerce_orders');
    }
};
