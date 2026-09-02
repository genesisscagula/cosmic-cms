<?php

namespace App\Console\Commands;

use App\Models\MarketplaceTemplate;
use App\Services\MarketplaceTemplateService;
use Illuminate\Console\Command;

final class AuditMarketplaceTemplates extends Command
{
    protected $signature = 'cosmic:audit-marketplace-templates';
    protected $description = 'Validate database-driven Marketplace website bundles, pages, navigation, and Spark plan compatibility.';

    public function handle(MarketplaceTemplateService $templates): int
    {
        $all = MarketplaceTemplate::query()->withCount(['pages', 'navigationItems'])->orderBy('sort_order')->get();
        $errors = $templates->validationErrors();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Templates', $all->count()],
                ['Published', $all->where('status', 'published')->count()],
                ['Starter', $all->where('plan', 'starter')->count()],
                ['Growth', $all->where('plan', 'growth')->count()],
                ['Pro', $all->where('plan', 'pro')->count()],
                ['Pages', $all->sum('pages_count')],
                ['Navigation items', $all->sum('navigation_items_count')],
            ],
        );

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $this->info('PASS: Marketplace templates are valid and ready for catalog/provisioning integration.');
        return self::SUCCESS;
    }
}
