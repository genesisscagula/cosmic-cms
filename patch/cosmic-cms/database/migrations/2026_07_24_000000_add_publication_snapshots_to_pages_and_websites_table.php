<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            if (! Schema::hasColumn('pages', 'published_blocks')) {
                $table->json('published_blocks')->nullable()->after('blocks');
            }

            if (! Schema::hasColumn('pages', 'published_html')) {
                $table->longText('published_html')->nullable()->after('published_blocks');
            }

            if (! Schema::hasColumn('pages', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('published_html');
            }

            if (! Schema::hasColumn('pages', 'last_published_at')) {
                $table->timestamp('last_published_at')->nullable()->after('published_at');
            }

            if (! Schema::hasColumn('pages', 'publish_error')) {
                $table->text('publish_error')->nullable()->after('last_published_at');
            }
        });

        Schema::table('websites', function (Blueprint $table) {
            if (! Schema::hasColumn('websites', 'published_theme_settings')) {
                $table->json('published_theme_settings')->nullable()->after('theme_settings');
            }

            if (! Schema::hasColumn('websites', 'published_global_header')) {
                $table->json('published_global_header')->nullable()->after('global_header');
            }

            if (! Schema::hasColumn('websites', 'published_global_footer')) {
                $table->json('published_global_footer')->nullable()->after('global_footer');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $columns = ['published_blocks', 'published_html', 'published_at', 'last_published_at', 'publish_error'];
            $existing = array_filter($columns, fn (string $column) => Schema::hasColumn('pages', $column));

            if ($existing) {
                $table->dropColumn($existing);
            }
        });

        Schema::table('websites', function (Blueprint $table) {
            $columns = ['published_theme_settings', 'published_global_header', 'published_global_footer'];
            $existing = array_filter($columns, fn (string $column) => Schema::hasColumn('websites', $column));

            if ($existing) {
                $table->dropColumn($existing);
            }
        });
    }
};
