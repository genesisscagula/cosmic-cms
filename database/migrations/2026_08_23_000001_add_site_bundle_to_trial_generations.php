<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trial_generations', function (Blueprint $table) {
            $table->foreignId('website_id')
                ->nullable()
                ->after('page_id')
                ->constrained('websites')
                ->nullOnDelete();
            $table->json('bundle_manifest')->nullable()->after('menu_structure');
            $table->string('bundle_status', 32)->nullable()->after('bundle_manifest')->index();
            $table->text('bundle_error')->nullable()->after('bundle_status');
        });
    }

    public function down(): void
    {
        Schema::table('trial_generations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('website_id');
            $table->dropIndex(['bundle_status']);
            $table->dropColumn(['bundle_manifest', 'bundle_status', 'bundle_error']);
        });
    }
};
