<?php

namespace App\Console\Commands;

use App\Models\Website;
use App\Services\WebsiteHealthService;
use Illuminate\Console\Command;

final class WebsiteHealthCheck extends Command
{
    protected $signature = 'cosmic:website-health {website : Website ID} {--json : Print the full machine-readable scan}';
    protected $description = 'Run the read-only Cosmic Website Health scanner for one website.';

    public function handle(WebsiteHealthService $health): int
    {
        $website = Website::query()->find($this->argument('website'));
        if (! $website) {
            $this->error('Website not found.');
            return self::FAILURE;
        }

        $result = $health->scan($website);
        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $this->info("Website Health: {$result['score']}/100 — {$result['status']}");
        $this->line("Critical: {$result['summary']['critical']}  Warnings: {$result['summary']['warning']}  Passed: {$result['summary']['passed']}");

        foreach ($result['categories'] as $name => $category) {
            $this->newLine();
            $this->line('<comment>'.strtoupper($name).'</comment>');
            foreach ($category['findings'] as $finding) {
                $symbol = match ($finding['status']) {
                    'critical' => '✕',
                    'warning' => '!',
                    'passed' => '✓',
                    default => '•',
                };
                $this->line("  {$symbol} {$finding['title']}");
            }
        }

        return self::SUCCESS;
    }
}
