<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_provisionings', function (Blueprint $table) {
            $table->string('bound_plan_key')->nullable()->after('trial_page_id')->index();
            $table->string('bound_subscription_id')->nullable()->after('bound_plan_key')->index();
            $table->unsignedInteger('monthly_credits')->nullable()->after('bound_subscription_id');
            $table->unsignedInteger('credit_balance_at_binding')->nullable()->after('monthly_credits');
            $table->timestamp('subscription_bound_at')->nullable()->after('credit_balance_at_binding');
        });
    }

    public function down(): void
    {
        Schema::table('workspace_provisionings', function (Blueprint $table) {
            $table->dropIndex(['bound_plan_key']);
            $table->dropIndex(['bound_subscription_id']);
            $table->dropColumn([
                'bound_plan_key',
                'bound_subscription_id',
                'monthly_credits',
                'credit_balance_at_binding',
                'subscription_bound_at',
            ]);
        });
    }
};
