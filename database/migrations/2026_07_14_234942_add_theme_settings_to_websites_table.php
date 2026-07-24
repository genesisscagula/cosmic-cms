<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        if (Schema::hasColumn('websites', 'theme_settings')) {
            return;
        }

        Schema::table('websites', function (Blueprint $table) {
            // I-save nato ang object { primary, secondary, etc. }
            $table->json('theme_settings')->nullable()->after('domain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            //
        });
    }
};
