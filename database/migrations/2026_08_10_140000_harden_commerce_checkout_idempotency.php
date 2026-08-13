<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $needsHardening = ! Schema::hasColumn('commerce_orders', 'checkout_idempotency_key');

        if ($needsHardening) {
            Schema::table('commerce_orders', function (Blueprint $table) {
                $table->uuid('checkout_idempotency_key')->nullable()->after('payment_provider');
                $table->char('checkout_fingerprint', 64)->nullable()->after('checkout_idempotency_key');
                $table->unique(['website_id', 'checkout_idempotency_key'], 'commerce_orders_checkout_idem_unique');
            });

            Schema::table('commerce_orders', function (Blueprint $table) {
                $table->dropIndex('commerce_orders_external_checkout_id_index');
                $table->dropIndex('commerce_orders_external_payment_id_index');
                $table->unique('external_checkout_id', 'commerce_orders_ext_checkout_unique');
                $table->unique('external_payment_id', 'commerce_orders_ext_payment_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('commerce_orders', 'checkout_idempotency_key')) {
            Schema::table('commerce_orders', function (Blueprint $table) {
                $table->dropUnique('commerce_orders_checkout_idem_unique');
                $table->dropColumn(['checkout_idempotency_key', 'checkout_fingerprint']);
            });
        }
    }
};
