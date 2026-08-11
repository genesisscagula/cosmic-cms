<?php

namespace App\Console\Commands;

use App\Models\Website;
use App\Services\CommerceInstallerService;
use Illuminate\Console\Command;

class InstallCommerceDemo extends Command
{
    protected $signature = 'cosmic:install-commerce-demo
                            {--website= : Website ID, preview slug, or website name}
                            {--refresh-images : Re-download the bundled demo image set from Unsplash}';
    protected $description = 'Install/repair Cosmic commerce, download local Unsplash demo images, and seed the idempotent demo catalog.';

    public function handle(CommerceInstallerService $installer): int
    {
        $key = trim((string) $this->option('website'));
        if ($key === '') { $this->error('Pass --website=<id|preview-slug|name>.'); return self::FAILURE; }
        $website = Website::query()->whereKey(ctype_digit($key) ? (int) $key : -1)
            ->orWhere('preview_slug', $key)->orWhere('name', $key)->first();
        if (! $website) { $this->error("Website not found: {$key}"); return self::FAILURE; }

        $this->info('Preparing local demo images from Unsplash...');
        $images = $installer->downloadDemoImages((bool) $this->option('refresh-images'));
        if (! $images['enabled']) {
            $this->warn('UNSPLASH_ACCESS_KEY is not configured. Demo records will still install, but local demo images cannot be downloaded yet.');
        } else {
            $this->line("Images: {$images['downloaded']} downloaded, {$images['skipped']} already local, {$images['failed']} unavailable.");
        }

        $result = $installer->install($website, true);
        $this->info("Commerce ready for {$website->name}. Added {$result['demo']['products']} demo products and {$result['demo']['categories']} demo categories.");
        $this->line('Shop is a normal Builder page preloaded with Shop Categories + Product Grid Sparks. Cart, Checkout, Account and Order remain protected dynamic commerce routes.');
        $this->line('Re-running this command repairs the setup and does not duplicate demo slugs.');
        return self::SUCCESS;
    }
}
