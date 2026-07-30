<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trial_generations', function (Blueprint $table) {
            if (! Schema::hasColumn('trial_generations', 'preview_theme')) {
                $table->json('preview_theme')->nullable()->after('menu_structure');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trial_generations', function (Blueprint $table) {
            if (Schema::hasColumn('trial_generations', 'preview_theme')) {
                $table->dropColumn('preview_theme');
            }
        });
    }
};
