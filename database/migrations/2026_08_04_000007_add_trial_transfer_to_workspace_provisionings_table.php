<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_provisionings', function (Blueprint $table) {
            $table->foreignId('trial_page_id')
                ->nullable()
                ->after('website_id')
                ->constrained('pages')
                ->nullOnDelete();

            $table->unique('trial_page_id');
        });
    }

    public function down(): void
    {
        Schema::table('workspace_provisionings', function (Blueprint $table) {
            $table->dropUnique(['trial_page_id']);
            $table->dropConstrainedForeignId('trial_page_id');
        });
    }
};
