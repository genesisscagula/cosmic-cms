<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trial_generations', function (Blueprint $table) {
            $table->foreignId('page_id')
                ->nullable()
                ->after('id')
                ->constrained('pages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trial_generations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('page_id');
        });
    }
};
