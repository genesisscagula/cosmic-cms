<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('trial_generations', function (Blueprint $table) {
            $table->string('logo_url')->nullable()->after('preview_theme');
            $table->string('logo_company_name')->nullable()->after('logo_url');
            $table->string('logo_source', 24)->nullable()->after('logo_company_name');
            $table->timestamp('logo_updated_at')->nullable()->after('logo_source');
        });
    }

    public function down(): void
    {
        Schema::table('trial_generations', function (Blueprint $table) {
            $table->dropColumn(['logo_url', 'logo_company_name', 'logo_source', 'logo_updated_at']);
        });
    }
};
