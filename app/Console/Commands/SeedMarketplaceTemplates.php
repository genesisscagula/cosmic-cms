<?php

namespace App\Console\Commands;

use App\Models\MarketplaceTemplate;
use App\Services\MarketplaceTemplateService;
use Database\Seeders\MarketplaceWebsiteTemplateSeeder;
use Illuminate\Console\Command;

final class SeedMarketplaceTemplates extends Command
{
    protected $signature = 'cosmic:seed-marketplace-templates {--audit : Validate the seeded bundles after writing them}';
    protected $description = 'Create or refresh the built-in Cosmic Marketplace website template bundles.';

    public function handle(MarketplaceTemplateService $templates): int
    {
        $this->call('db:seed', [
            '--class' => MarketplaceWebsiteTemplateSeeder::class,
            '--force' => true,
        ]);

        $query = MarketplaceTemplate::query()->withCount(['pages', 'navigationItems'])->orderBy('sort_order');
        $rows = $query->get()->map(fn (MarketplaceTemplate $template) => [
            $template->name,
            ucfirst($template->plan),
            $template->pages_count,
            $template->navigation_items_count,
            '$'.number_format(((int) $template->monthly_price_cents) / 100, 0).'/mo',
            $template->status,
        ])->all();

        $this->table(['Template', 'Plan', 'Pages', 'Nav items', 'Price', 'Status'], $rows);

        if ($this->option('audit')) {
            $errors = $templates->validationErrors();
            if ($errors !== []) {
                foreach ($errors as $error) {
                    $this->error($error);
                }

                return self::FAILURE;
            }
        }

        $this->info('Marketplace website template inventory is ready.');
        return self::SUCCESS;
    }
}
