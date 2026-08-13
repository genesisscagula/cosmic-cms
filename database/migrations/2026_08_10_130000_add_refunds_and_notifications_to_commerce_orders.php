<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('commerce_orders', 'refunded_minor')) {
            Schema::table('commerce_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('refunded_minor')->default(0)->after('total_minor');
                $table->string('refund_status', 32)->default('none')->after('payment_status');
                $table->timestamp('order_confirmation_sent_at')->nullable()->after('paid_at');
                $table->timestamp('merchant_notification_sent_at')->nullable()->after('order_confirmation_sent_at');
                $table->timestamp('last_customer_notification_at')->nullable()->after('merchant_notification_sent_at');
            });
        }

        if (! Schema::hasTable('commerce_order_refunds')) {
            Schema::create('commerce_order_refunds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('commerce_order_id')->constrained('commerce_orders')->cascadeOnDelete();
                $table->string('provider', 40)->default('paypal');
                $table->string('external_refund_id')->nullable()->unique();
                $table->unsignedBigInteger('amount_minor');
                $table->string('currency', 3);
                $table->string('status', 32)->default('completed');
                $table->text('reason')->nullable();
                $table->json('provider_payload')->nullable();
                $table->timestamp('refunded_at')->nullable();
                $table->timestamps();
                $table->index(['commerce_order_id', 'status'], 'commerce_refund_order_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_order_refunds');
        if (Schema::hasColumn('commerce_orders', 'refunded_minor')) {
            Schema::table('commerce_orders', function (Blueprint $table) {
                $table->dropColumn(['refunded_minor','refund_status','order_confirmation_sent_at','merchant_notification_sent_at','last_customer_notification_at']);
            });
        }
    }
};
