<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('commerce_orders', 'payment_recovery_last_attempt_at')) {
            Schema::table('commerce_orders', function (Blueprint $table) {
                $table->timestamp('payment_recovery_last_attempt_at')->nullable()->after('payment_recovery_attempts');
                $table->timestamp('payment_recovery_next_attempt_at')->nullable()->after('payment_recovery_last_attempt_at');
                $table->timestamp('payment_attention_required_at')->nullable()->after('payment_recovered_at');
                $table->index(['payment_status', 'payment_recovery_next_attempt_at'], 'commerce_orders_recovery_due_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('commerce_orders', 'payment_recovery_last_attempt_at')) {
            Schema::table('commerce_orders', function (Blueprint $table) {
                $table->dropIndex('commerce_orders_recovery_due_idx');
                $table->dropColumn(['payment_recovery_last_attempt_at','payment_recovery_next_attempt_at','payment_attention_required_at']);
            });
        }
    }
};
