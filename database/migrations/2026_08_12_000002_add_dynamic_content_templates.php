<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_page_templates', function (Blueprint $table) {
            $table->foreignId('content_type_id')->nullable()->after('website_id')->constrained('content_types')->nullOnDelete();
            $table->longText('markup')->nullable()->after('blocks');
            $table->index(['content_type_id', 'template_type', 'status'], 'saved_templates_content_type_index');
        });

        Schema::table('content_types', function (Blueprint $table) {
            $table->foreignId('single_template_id')->nullable()->after('sort_order')->constrained('saved_page_templates')->nullOnDelete();
            $table->foreignId('archive_template_id')->nullable()->after('single_template_id')->constrained('saved_page_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('content_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('archive_template_id');
            $table->dropConstrainedForeignId('single_template_id');
        });

        Schema::table('saved_page_templates', function (Blueprint $table) {
            $table->dropIndex('saved_templates_content_type_index');
            $table->dropConstrainedForeignId('content_type_id');
            $table->dropColumn('markup');
        });
    }
};
