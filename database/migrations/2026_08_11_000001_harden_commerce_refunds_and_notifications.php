<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commerce_order_refunds') && ! Schema::hasColumn('commerce_order_refunds', 'request_key')) {
            Schema::table('commerce_order_refunds', function (Blueprint $table) {
                $table->uuid('request_key')->nullable()->after('provider');
                $table->timestamp('provider_synced_at')->nullable()->after('refunded_at');
                $table->unique(['commerce_order_id', 'request_key'], 'commerce_refunds_order_request_unique');
            });
        }

        if (! Schema::hasColumn('commerce_orders', 'notification_attempts')) {
            Schema::table('commerce_orders', function (Blueprint $table) {
                $table->unsignedSmallInteger('notification_attempts')->default(0)->after('last_customer_notification_at');
                $table->timestamp('notification_last_attempt_at')->nullable()->after('notification_attempts');
                $table->timestamp('notification_next_attempt_at')->nullable()->after('notification_last_attempt_at');
                $table->text('notification_last_error')->nullable()->after('notification_next_attempt_at');
                $table->timestamp('notification_attention_required_at')->nullable()->after('notification_last_error');
                $table->index(['payment_status', 'notification_next_attempt_at'], 'commerce_orders_notification_due_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('commerce_orders', 'notification_attempts')) {
            Schema::table('commerce_orders', function (Blueprint $table) {
                $table->dropIndex('commerce_orders_notification_due_idx');
                $table->dropColumn(['notification_attempts','notification_last_attempt_at','notification_next_attempt_at','notification_last_error','notification_attention_required_at']);
            });
        }
        if (Schema::hasTable('commerce_order_refunds') && Schema::hasColumn('commerce_order_refunds', 'request_key')) {
            Schema::table('commerce_order_refunds', function (Blueprint $table) {
                $table->dropUnique('commerce_refunds_order_request_unique');
                $table->dropColumn(['request_key', 'provider_synced_at']);
            });
        }
    }
};
