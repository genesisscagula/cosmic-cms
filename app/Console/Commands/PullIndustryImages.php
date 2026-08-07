<?php

namespace App\Console\Commands;

use App\Services\IndustryResolver;
use App\Services\SmartImageService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class PullIndustryImages extends Command
{
    protected $signature = 'cosmic:industries:pull
                            {industry : Curated industry key from cosmic:industries:list}
                            {--count=10 : Target total number of local images (1-30)}
                            {--query= : Optional custom provider search phrase}';

    protected $description = 'Fill a curated industry folder from Unsplash/Pexels up to the requested image count';

    public function handle(IndustryResolver $industries, SmartImageService $images): int
    {
        $industry = Str::slug((string) $this->argument('industry'));
        $target = max(1, min(30, (int) $this->option('count')));

        if (! $industries->isSupported($industry) || $industry === 'default') {
            $this->error("Unknown industry key: {$industry}");
            $this->line('Run: php artisan cosmic:industries:list');

            return self::FAILURE;
        }

        $query = trim((string) $this->option('query')) ?: $industries->searchQuery($industry);
        $before = $images->industryImageCount($industry);

        $this->info('Industry: '.$industry);
        $this->line('Search: '.$query);
        $this->line("Current images: {$before}");
        $this->line("Target images: {$target}");

        if ($before >= $target) {
            $this->info('Folder already has enough images. Nothing downloaded.');
            return self::SUCCESS;
        }

        $missing = $target - $before;
        $this->line("Downloading up to {$missing} missing image(s) via configured providers...");

        $queries = array_fill(0, $target, [
            'query' => $query,
            'role' => 'general',
        ]);

        $images->learnIndustry($industry, $queries);
        $after = $images->industryImageCount($industry);

        $this->newLine();
        $this->info("Finished. {$industry} now has {$after} image(s).");
        $this->line('Folder: '.storage_path('app/public/cms-images/'.$industry));

        return $after > $before ? self::SUCCESS : self::FAILURE;
    }
}
