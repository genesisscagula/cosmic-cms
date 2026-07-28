<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->string('industry')->nullable()->after('domain');
            $table->string('location')->nullable()->after('industry');
            $table->text('business_description')->nullable()->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropColumn(['industry', 'location', 'business_description']);
        });
    }
};
