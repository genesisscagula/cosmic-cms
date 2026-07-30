<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trial_generations', function (Blueprint $table) {
            $table->json('menu_structure')->nullable()->after('generated_blocks');
        });
    }

    public function down(): void
    {
        Schema::table('trial_generations', function (Blueprint $table) {
            $table->dropColumn('menu_structure');
        });
    }
};
