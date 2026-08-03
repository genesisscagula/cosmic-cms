<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('plan_cancel_at_period_end')->default(false)->after('plan_renews_at');
            $table->timestamp('plan_cancelled_at')->nullable()->after('plan_cancel_at_period_end');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'plan_cancel_at_period_end',
            'plan_cancelled_at',
        ]));
    }
};
