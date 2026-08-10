<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('trial_generations', function (Blueprint $table) {
            if (! Schema::hasColumn('trial_generations', 'owned_sparks')) $table->json('owned_sparks')->nullable();
            if (! Schema::hasColumn('trial_generations', 'favorite_sparks')) $table->json('favorite_sparks')->nullable();
            if (! Schema::hasColumn('trial_generations', 'owned_templates')) $table->json('owned_templates')->nullable();
            if (! Schema::hasColumn('trial_generations', 'favorite_templates')) $table->json('favorite_templates')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trial_generations', function (Blueprint $table) {
            foreach (['owned_sparks','favorite_sparks','owned_templates','favorite_templates'] as $column) {
                if (Schema::hasColumn('trial_generations', $column)) $table->dropColumn($column);
            }
        });
    }
};
