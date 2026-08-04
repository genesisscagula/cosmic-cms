<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_provisionings', function (Blueprint $table) {
            $table->foreignId('website_id')
                ->nullable()
                ->after('workspace_id')
                ->constrained()
                ->nullOnDelete();

            $table->unique('website_id');
        });
    }

    public function down(): void
    {
        Schema::table('workspace_provisionings', function (Blueprint $table) {
            $table->dropUnique(['website_id']);
            $table->dropConstrainedForeignId('website_id');
        });
    }
};
