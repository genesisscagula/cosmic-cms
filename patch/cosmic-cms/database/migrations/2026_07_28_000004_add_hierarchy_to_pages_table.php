<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('website_id')->constrained('pages')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0)->after('parent_id');
            $table->index(['website_id', 'parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['website_id', 'parent_id', 'sort_order']);
            $table->dropColumn(['parent_id', 'sort_order']);
        });
    }
};
