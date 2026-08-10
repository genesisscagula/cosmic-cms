<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('trial_generations', function (Blueprint $table) {
            if (! Schema::hasColumn('trial_generations', 'brand_prompt')) {
                $table->text('brand_prompt')->nullable()->after('prompt');
            }
            if (! Schema::hasColumn('trial_generations', 'latest_user_prompt')) {
                $table->text('latest_user_prompt')->nullable()->after('brand_prompt');
            }
            if (! Schema::hasColumn('trial_generations', 'prompt_history')) {
                $table->json('prompt_history')->nullable()->after('latest_user_prompt');
            }
            if (! Schema::hasColumn('trial_generations', 'brand_context')) {
                $table->json('brand_context')->nullable()->after('prompt_history');
            }
        });

        // Existing trials already stored their latest prompt in `prompt`; preserve it as
        // the initial brand seed so L2/L3 can work with old trial links too.
        DB::table('trial_generations')
            ->whereNull('brand_prompt')
            ->whereNotNull('prompt')
            ->update([
                'brand_prompt' => DB::raw('prompt'),
                'latest_user_prompt' => DB::raw('prompt'),
            ]);
    }

    public function down(): void
    {
        Schema::table('trial_generations', function (Blueprint $table) {
            foreach (['brand_context', 'prompt_history', 'latest_user_prompt', 'brand_prompt'] as $column) {
                if (Schema::hasColumn('trial_generations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
