<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('seo_title')->nullable()->after('slug');
            $table->text('meta_description')->nullable()->after('seo_title');
            $table->text('og_image_url')->nullable()->after('meta_description');
            $table->text('canonical_url')->nullable()->after('og_image_url');
            $table->boolean('is_indexable')->default(true)->after('canonical_url');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['seo_title', 'meta_description', 'og_image_url', 'canonical_url', 'is_indexable']);
        });
    }
};
