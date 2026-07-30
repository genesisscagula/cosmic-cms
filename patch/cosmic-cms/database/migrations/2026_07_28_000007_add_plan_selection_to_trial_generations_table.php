<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trial_generations', function (Blueprint $table) {
            $table->string('selected_plan', 32)->nullable()->after('claimed_at');
            $table->timestamp('plan_selected_at')->nullable()->after('selected_plan');
        });
    }

    public function down(): void
    {
        Schema::table('trial_generations', function (Blueprint $table) {
            $table->dropColumn(['selected_plan', 'plan_selected_at']);
        });
    }
};
