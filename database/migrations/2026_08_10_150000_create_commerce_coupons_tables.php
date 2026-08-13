<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('commerce_coupons')) {
            Schema::create('commerce_coupons', function (Blueprint $table) {
                $table->id();
                $table->foreignId('website_id')->constrained()->cascadeOnDelete();
                $table->string('code', 80);
                $table->string('name', 160)->nullable();
                $table->enum('discount_type', ['percent', 'fixed'])->default('percent');
                $table->unsignedInteger('percent_basis_points')->nullable();
                $table->unsignedBigInteger('fixed_amount_minor')->nullable();
                $table->unsignedBigInteger('minimum_spend_minor')->nullable();
                $table->unsignedInteger('usage_limit')->nullable();
                $table->unsignedInteger('usage_limit_per_email')->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->boolean('is_enabled')->default(true);
                $table->json('product_ids')->nullable();
                $table->json('category_ids')->nullable();
                $table->timestamps();
                $table->unique(['website_id', 'code']);
                $table->index(['website_id', 'is_enabled']);
            });
        }

        if (! Schema::hasTable('commerce_coupon_usages')) {
            Schema::create('commerce_coupon_usages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('commerce_coupon_id')->constrained('commerce_coupons')->cascadeOnDelete();
                $table->foreignId('commerce_order_id')->constrained('commerce_orders')->cascadeOnDelete();
                $table->foreignId('website_id')->constrained()->cascadeOnDelete();
                $table->string('customer_email');
                $table->unsignedBigInteger('discount_minor');
                $table->timestamp('used_at');
                $table->timestamps();
                $table->unique('commerce_order_id');
                $table->index(['commerce_coupon_id', 'customer_email'], 'commerce_coupon_email_idx');
            });
        }

        if (! Schema::hasColumn('commerce_orders', 'commerce_coupon_id')) {
            Schema::table('commerce_orders', function (Blueprint $table) {
                $table->foreignId('commerce_coupon_id')->nullable()->after('website_id')->constrained('commerce_coupons')->nullOnDelete();
                $table->string('coupon_code', 80)->nullable()->after('commerce_coupon_id');
                $table->unsignedBigInteger('discount_minor')->default(0)->after('subtotal_minor');
            });
        }
    }

    public function down(): void
    {
        Schema::table('commerce_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('commerce_coupon_id');
            $table->dropColumn(['coupon_code', 'discount_minor']);
        });
        Schema::dropIfExists('commerce_coupon_usages');
        Schema::dropIfExists('commerce_coupons');
    }
};
