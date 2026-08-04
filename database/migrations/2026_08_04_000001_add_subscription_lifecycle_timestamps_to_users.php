<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('plan_status_changed_at')->nullable()->after('plan_cancelled_at');
            $table->timestamp('plan_past_due_at')->nullable()->after('plan_status_changed_at');
            $table->timestamp('plan_suspended_at')->nullable()->after('plan_past_due_at');
            $table->timestamp('plan_expired_at')->nullable()->after('plan_suspended_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'plan_status_changed_at',
            'plan_past_due_at',
            'plan_suspended_at',
            'plan_expired_at',
        ]));
    }
};
