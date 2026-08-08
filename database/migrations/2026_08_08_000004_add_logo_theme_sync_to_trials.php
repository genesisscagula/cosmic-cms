<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('trial_generations', function (Blueprint $table) {
            $table->string('logo_theme_sync_state', 32)->nullable()->after('logo_source');
            $table->string('logo_theme_sync_source', 32)->nullable()->after('logo_theme_sync_state');
            $table->string('logo_theme_synced_theme', 60)->nullable()->after('logo_theme_sync_source');
        });
    }

    public function down(): void
    {
        Schema::table('trial_generations', function (Blueprint $table) {
            $table->dropColumn(['logo_theme_sync_state', 'logo_theme_sync_source', 'logo_theme_synced_theme']);
        });
    }
};
