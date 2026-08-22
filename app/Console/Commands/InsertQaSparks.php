<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Support\NewSparkQaCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class InsertQaSparks extends Command
{
    protected $signature = 'cosmic:sparks
        {page : Page ID to update}
        {--count=10 : Number of Sparks to insert}
        {--offset=0 : Zero-based catalog offset. 0=1-10, 10=11-20, 20=21-30}
        {--source=new : Spark source. Currently: new}
        {--random : Pick random Sparks instead of catalog order}
        {--all-new : Insert every remaining new expansion Spark}
        {--replace : Replace the page blocks instead of appending}
        {--allow-duplicates : Allow Spark types already present on the page}
        {--dry-run : Show what would be inserted without saving}';

    protected $description = 'Insert QA Sparks into a page for Builder / Preview / Export Live testing.';

    public function handle(): int
    {
        $pageId = (int) $this->argument('page');
        $source = strtolower(trim((string) $this->option('source')));

        if ($pageId < 1) {
            $this->error('Page ID must be a positive integer.');
            return self::FAILURE;
        }

        if ($source !== 'new') {
            $this->error("Unsupported --source={$source}. Currently supported: new");
            return self::FAILURE;
        }

        /** @var Page|null $page */
        $page = Page::query()->with('website')->find($pageId);

        if (! $page) {
            $this->error("Page {$pageId} was not found.");
            return self::FAILURE;
        }

        $catalog = collect(NewSparkQaCatalog::all());

        $offset = max(0, min(99, (int) $this->option('offset')));
        if (! $this->option('all-new')) {
            $catalog = $catalog->slice($offset)->values();
        }

        $existingBlocks = is_array($page->blocks) ? array_values($page->blocks) : [];
        $existingTypes = collect($existingBlocks)
            ->pluck('type')
            ->filter()
            ->map(fn ($type) => (string) $type)
            ->values();

        if (! $this->option('allow-duplicates')) {
            $catalog = $catalog->reject(
                fn (array $spark) => $existingTypes->contains((string) ($spark['type'] ?? ''))
            )->values();
        }

        if ($catalog->isEmpty()) {
            $this->warn('No eligible new Sparks remain for this page.');
            $this->line('Use --allow-duplicates if you intentionally want duplicate Spark types.');
            return self::SUCCESS;
        }

        if ($this->option('random')) {
            $catalog = $catalog->shuffle()->values();
        }

        $requestedCount = max(1, min(100, (int) $this->option('count')));
        $take = $this->option('all-new') ? $catalog->count() : min($requestedCount, $catalog->count());

        $selected = $catalog->take($take)->values();

        $newBlocks = $selected->map(function (array $spark): array {
            $defaults = is_array($spark['defaults'] ?? null) ? $spark['defaults'] : [];
            $type = (string) ($spark['type'] ?? Arr::get($defaults, 'type', ''));

            return array_replace(
                ['type' => $type, 'theme' => 'auto'],
                $defaults,
                [
                    'type' => $type,
                    '_qa_seeded' => true,
                    '_qa_seed_source' => 'cosmic:sparks',
                ]
            );
        })->all();

        $finalBlocks = $this->option('replace')
            ? $newBlocks
            : array_values(array_merge($existingBlocks, $newBlocks));

        $this->newLine();
        $this->info('Cosmic Spark QA seed');
        $this->line('Page: '.$page->id.' · '.$page->title);
        if ($page->website) {
            $this->line('Website: '.$page->website->id.' · '.$page->website->name);
        }
        $this->line('Mode: '.($this->option('replace') ? 'replace' : 'append'));
        if (! $this->option('all-new')) {
            $this->line('Catalog offset: '.$offset.' (starts at Spark '.($offset + 1).')');
        }
        $this->line('Selected: '.count($newBlocks).' Spark(s)');
        $this->newLine();

        $this->table(
            ['#', 'Spark type'],
            collect($newBlocks)->values()->map(
                fn (array $block, int $index) => [$index + 1, (string) ($block['type'] ?? '')]
            )->all()
        );

        if ($this->option('dry-run')) {
            $this->warn('Dry run only — page was not changed.');
            return self::SUCCESS;
        }

        $page->blocks = $finalBlocks;
        $page->status = 'draft';
        $page->publish_error = null;
        $page->save();

        $this->newLine();
        $this->info('Saved successfully.');
        $this->line('Page now has '.count($finalBlocks).' block(s).');

        if (! $this->option('allow-duplicates') && ! $this->option('replace')) {
            $remaining = max(0, 100 - collect($finalBlocks)->pluck('type')->intersect(NewSparkQaCatalog::types())->unique()->count());
            $this->line("New expansion Sparks remaining for this page: {$remaining}");
        }

        return self::SUCCESS;
    }
}
