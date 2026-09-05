<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_templates')) {
            return;
        }

        if (! Schema::hasColumn('marketplace_templates', 'credit_price')) {
            Schema::table('marketplace_templates', function (Blueprint $table) {
                $table->unsignedInteger('credit_price')->default(0)->after('monthly_price_cents');
            });
        }

        // Safe deterministic backfill for existing Marketplace inventory.
        // The legacy monthly_price_cents column is left intact temporarily so
        // shared/legacy payment code can be disconnected in later batches
        // without destructive schema changes in this pricing migration.
        DB::table('marketplace_templates')->where('plan', 'starter')->update(['credit_price' => 500]);
        DB::table('marketplace_templates')->where('plan', 'growth')->update(['credit_price' => 1000]);
        DB::table('marketplace_templates')->where('plan', 'pro')->update(['credit_price' => 2000]);
    }

    public function down(): void
    {
        if (Schema::hasTable('marketplace_templates') && Schema::hasColumn('marketplace_templates', 'credit_price')) {
            Schema::table('marketplace_templates', function (Blueprint $table) {
                $table->dropColumn('credit_price');
            });
        }
    }
};
