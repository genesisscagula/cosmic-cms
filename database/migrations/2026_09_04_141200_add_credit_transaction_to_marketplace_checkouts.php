<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_checkouts') || Schema::hasColumn('marketplace_checkouts', 'credit_transaction_id')) {
            return;
        }

        Schema::table('marketplace_checkouts', function (Blueprint $table) {
            $table->foreignId('credit_transaction_id')
                ->nullable()
                ->after('payment_order_id')
                ->constrained('credit_transactions')
                ->nullOnDelete();
            $table->unique('credit_transaction_id', 'marketplace_checkout_credit_transaction_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('marketplace_checkouts') || ! Schema::hasColumn('marketplace_checkouts', 'credit_transaction_id')) {
            return;
        }

        Schema::table('marketplace_checkouts', function (Blueprint $table) {
            $table->dropUnique('marketplace_checkout_credit_transaction_unique');
            $table->dropConstrainedForeignId('credit_transaction_id');
        });
    }
};
