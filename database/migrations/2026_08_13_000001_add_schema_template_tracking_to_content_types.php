<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_types')) return;

        Schema::table('content_types', function (Blueprint $table) {
            if (! Schema::hasColumn('content_types', 'preset_key')) $table->string('preset_key', 60)->nullable()->after('schema');
            if (! Schema::hasColumn('content_types', 'schema_source')) $table->string('schema_source', 24)->default('preset')->after('preset_key');
            if (! Schema::hasColumn('content_types', 'schema_signature')) $table->string('schema_signature', 64)->nullable()->after('schema_source');
            if (! Schema::hasColumn('content_types', 'single_template_schema_signature')) $table->string('single_template_schema_signature', 64)->nullable()->after('schema_signature');
            if (! Schema::hasColumn('content_types', 'archive_template_schema_signature')) $table->string('archive_template_schema_signature', 64)->nullable()->after('single_template_schema_signature');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('content_types')) return;
        Schema::table('content_types', function (Blueprint $table) {
            foreach (['archive_template_schema_signature','single_template_schema_signature','schema_signature','schema_source','preset_key'] as $column) {
                if (Schema::hasColumn('content_types', $column)) $table->dropColumn($column);
            }
        });
    }
};
