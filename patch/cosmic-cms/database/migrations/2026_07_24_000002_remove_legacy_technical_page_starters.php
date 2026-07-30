<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('pages')
            ->select(['id', 'blocks'])
            ->orderBy('id')
            ->chunkById(100, function ($pages): void {
                foreach ($pages as $page) {
                    $blocks = json_decode($page->blocks ?? '[]', true);

                    if (! $this->isLegacyTechnicalStarter($blocks)) {
                        continue;
                    }

                    // New pages now open as a clean Builder canvas. Only this
                    // known old demo payload is removed; customer content stays intact.
                    DB::table('pages')->where('id', $page->id)->update([
                        'blocks' => json_encode([]),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Demo content must not be recreated on rollback.
    }

    private function isLegacyTechnicalStarter(mixed $blocks): bool
    {
        if (! is_array($blocks) || count($blocks) !== 2) {
            return false;
        }

        $hero = $blocks[0] ?? [];
        $content = $blocks[1] ?? [];

        return ($hero['type'] ?? null) === 'hero'
            && str_starts_with((string) ($hero['subheading'] ?? ''), 'Custom crafted solutions via Cosmic CMS for ')
            && ($content['type'] ?? null) === 'content'
            && str_contains((string) ($content['text'] ?? ''), 'Headless CMS database');
    }
};
