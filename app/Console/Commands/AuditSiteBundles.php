<?php

namespace App\Console\Commands;

use App\Services\PageTemplateCatalog;
use App\Services\SiteBundleCatalog;
use Illuminate\Console\Command;

final class AuditSiteBundles extends Command
{
    protected $signature = 'cosmic:audit-site-bundles';
    protected $description = 'Validate the centralized Luna whole-site bundle catalog.';

    public function handle(SiteBundleCatalog $catalog): int
    {
        $bundles = $catalog->all();
        $knownTemplates = collect(PageTemplateCatalog::all())->pluck('key')->flip();
        $errors = [];

        if (count($bundles) !== SiteBundleCatalog::EXPECTED_BUNDLE_COUNT) {
            $errors[] = 'Expected '.SiteBundleCatalog::EXPECTED_BUNDLE_COUNT.' bundles; found '.count($bundles).'.';
        }
        if (collect($bundles)->pluck('key')->unique()->count() !== count($bundles)) {
            $errors[] = 'Bundle keys are not unique.';
        }

        foreach ($bundles as $bundle) {
            $key = (string) ($bundle['key'] ?? 'unknown');
            $templates = array_values(array_unique($bundle['template_candidates'] ?? []));
            $pages = $bundle['pages'] ?? [];

            if (count($templates) < 15 || count($templates) > 20) {
                $errors[] = "Bundle [{$key}] must contain 15–20 unique template candidates.";
            }
            if (count($pages) < 4 || count($pages) > 7) {
                $errors[] = "Bundle [{$key}] must contain 4–7 pages.";
            }
            foreach ($templates as $templateKey) {
                if (! $knownTemplates->has($templateKey)) {
                    $errors[] = "Bundle [{$key}] references missing template [{$templateKey}].";
                }
            }
            foreach ($pages as $page) {
                if (empty($page['title']) || empty($page['slug']) || empty($page['candidate_template_keys'])) {
                    $errors[] = "Bundle [{$key}] contains an incomplete page recipe.";
                }
            }
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Bundles', count($bundles)],
                ['Industries', collect($bundles)->pluck('industry')->unique()->count()],
                ['Archetypes', collect($bundles)->pluck('archetype')->unique()->count()],
                ['Templates per bundle', collect($bundles)->pluck('template_count')->min().'–'.collect($bundles)->pluck('template_count')->max()],
                ['Pages per bundle', collect($bundles)->pluck('page_count')->min().'–'.collect($bundles)->pluck('page_count')->max()],
                ['Referenced templates', collect($bundles)->flatMap(fn (array $bundle) => $bundle['template_candidates'])->unique()->count()],
            ],
        );

        if ($errors !== []) {
            foreach (array_values(array_unique($errors)) as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        $this->info('PASS: Luna site bundles are centralized, valid and ready for selection.');
        return self::SUCCESS;
    }
}
