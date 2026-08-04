<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('plan_last_synced_at')->nullable()->after('plan_expired_at');
            $table->timestamp('plan_recovery_attempted_at')->nullable()->after('plan_last_synced_at');
            $table->text('plan_recovery_error')->nullable()->after('plan_recovery_attempted_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['plan_last_synced_at', 'plan_recovery_attempted_at', 'plan_recovery_error']);
        });
    }
};
