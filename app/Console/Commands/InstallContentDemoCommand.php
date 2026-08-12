<?php

namespace App\Console\Commands;

use App\Models\Website;
use App\Services\ContentInstallerService;
use Illuminate\Console\Command;

class InstallContentDemoCommand extends Command
{
    protected $signature = 'cosmic:install-content-demo {--website= : Website ID, name, domain, or preview slug} {--plain : Install pages without demo entries}';
    protected $description = 'Install Blog, Events and Projects pages with optional demo structured content.';

    public function handle(ContentInstallerService $installer): int
    {
        $key = trim((string)$this->option('website'));
        if ($key === '') { $this->error('Pass --website=<id|name|domain|preview-slug>.'); return self::FAILURE; }
        $website = Website::query()->whereKey(ctype_digit($key) ? (int)$key : -1)
            ->orWhere('name',$key)->orWhere('domain',$key)->orWhere('preview_slug',$key)->first();
        if (! $website) { $this->error('Website not found.'); return self::FAILURE; }
        $result = $installer->install($website, ! $this->option('plain'), true);
        $this->info("Content ready for {$website->name}. {$result['pages']} pages; {$result['demo']} demo entries added.");
        return self::SUCCESS;
    }
}
