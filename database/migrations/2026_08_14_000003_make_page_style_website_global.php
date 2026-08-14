<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table): void {
            if (! Schema::hasColumn('websites', 'page_style')) {
                $table->string('page_style', 40)->nullable()->after('theme_settings');
            }
            if (! Schema::hasColumn('websites', 'published_page_style')) {
                $table->string('published_page_style', 40)->nullable()->after('page_style');
            }
        });

        DB::table('websites')->orderBy('id')->get(['id'])->each(function ($website): void {
            $pages = DB::table('pages')->where('website_id', $website->id)->orderBy('id')->get(['slug', 'page_style', 'published_page_style']);
            $home = $pages->first(fn ($page) => in_array((string) $page->slug, ['', 'home'], true));
            $draftStyle = trim((string) (($home?->page_style) ?: optional($pages->first(fn ($page) => filled($page->page_style) && $page->page_style !== 'auto'))->page_style ?: 'auto'));
            $publishedStyle = trim((string) (($home?->published_page_style) ?: optional($pages->first(fn ($page) => filled($page->published_page_style) && $page->published_page_style !== 'auto'))->published_page_style ?: $draftStyle));

            DB::table('websites')->where('id', $website->id)->update([
                'page_style' => $draftStyle !== '' ? $draftStyle : 'auto',
                'published_page_style' => $publishedStyle !== '' ? $publishedStyle : ($draftStyle !== '' ? $draftStyle : 'auto'),
            ]);

            // Keep legacy per-page columns synchronized for older snapshots/routes.
            DB::table('pages')->where('website_id', $website->id)->update([
                'page_style' => $draftStyle !== '' ? $draftStyle : 'auto',
                'published_page_style' => $publishedStyle !== '' ? $publishedStyle : ($draftStyle !== '' ? $draftStyle : 'auto'),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table): void {
            $columns = array_values(array_filter(['page_style', 'published_page_style'], fn (string $column) => Schema::hasColumn('websites', $column)));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
