<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_onboardings', function (Blueprint $table) {
            $table->foreignId('workspace_id')->nullable()->after('trial_generation_id')->constrained()->nullOnDelete();
            $table->foreignId('website_id')->nullable()->after('workspace_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pending_onboardings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('website_id');
            $table->dropConstrainedForeignId('workspace_id');
        });
    }
};
