<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table): void {
            $table->string('preview_slug')->nullable()->unique()->after('domain');
            $table->timestamp('last_preview_deployed_at')->nullable()->after('last_deployed_at');
            $table->text('preview_deployment_error')->nullable()->after('deployment_error');
        });
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table): void {
            $table->dropUnique(['preview_slug']);
            $table->dropColumn(['preview_slug', 'last_preview_deployed_at', 'preview_deployment_error']);
        });
    }
};
