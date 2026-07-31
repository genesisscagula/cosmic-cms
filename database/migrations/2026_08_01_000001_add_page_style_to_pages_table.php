<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('page_style', 40)->nullable()->after('page_type');
            $table->string('published_page_style', 40)->nullable()->after('page_style');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['page_style', 'published_page_style']);
        });
    }
};
