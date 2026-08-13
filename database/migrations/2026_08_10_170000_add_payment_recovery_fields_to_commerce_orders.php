<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('commerce_orders', 'payment_attempted_at')) {
            Schema::table('commerce_orders', function (Blueprint $table) {
                $table->timestamp('payment_attempted_at')->nullable()->after('checkout_fingerprint');
                $table->timestamp('checkout_expires_at')->nullable()->after('payment_attempted_at');
                $table->unsignedSmallInteger('payment_recovery_attempts')->default(0)->after('checkout_expires_at');
                $table->timestamp('payment_recovered_at')->nullable()->after('payment_recovery_attempts');
                $table->text('payment_recovery_last_error')->nullable()->after('payment_recovered_at');
                $table->index(['payment_status', 'checkout_expires_at'], 'commerce_orders_recovery_expiry_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('commerce_orders', 'payment_attempted_at')) {
            Schema::table('commerce_orders', function (Blueprint $table) {
                $table->dropIndex('commerce_orders_recovery_expiry_idx');
                $table->dropColumn(['payment_attempted_at','checkout_expires_at','payment_recovery_attempts','payment_recovered_at','payment_recovery_last_error']);
            });
        }
    }
};
