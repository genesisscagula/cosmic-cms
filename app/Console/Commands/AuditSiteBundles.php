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
        if (collect($bundles)->pluck('candidate_signature')->unique()->count() !== count($bundles)) {
            $errors[] = 'Two or more bundles reuse the same 18-template candidate composition.';
        }
        if (collect($bundles)->pluck('composition_signature')->unique()->count() !== count($bundles)) {
            $errors[] = 'Two or more bundles reuse the same complete page composition.';
        }

        $expectedArchetypes = count($catalog->archetypes());
        foreach (array_keys($catalog->industries()) as $industry) {
            $industryBundles = collect($bundles)->where('industry', $industry);
            if ($industryBundles->count() !== $expectedArchetypes) {
                $errors[] = "Industry [{$industry}] must contain {$expectedArchetypes} bundle directions.";
            }
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
            if (count(array_unique(array_column($pages, 'slug'))) !== count($pages)) {
                $errors[] = "Bundle [{$key}] contains duplicate page slugs.";
            }

            $signatureKeys = $templates;
            sort($signatureKeys, SORT_STRING);
            if (($bundle['candidate_signature'] ?? '') !== sha1(implode('|', $signatureKeys))) {
                $errors[] = "Bundle [{$key}] has a stale candidate signature.";
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
                $pageCandidates = array_values(array_unique($page['candidate_template_keys'] ?? []));
                if (count($pageCandidates) !== 5) {
                    $errors[] = "Bundle [{$key}] page [".($page['slug'] ?? 'unknown').'] must expose five unique choices.';
                }
                if (array_diff($pageCandidates, $templates) !== []) {
                    $errors[] = "Bundle [{$key}] page [".($page['slug'] ?? 'unknown').'] references a choice outside its bundle.';
                }
            }
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Bundles', count($bundles)],
                ['Industries', collect($bundles)->pluck('industry')->unique()->count()],
                ['Archetypes', collect($bundles)->pluck('archetype')->unique()->count()],
                ['Unique candidate sets', collect($bundles)->pluck('candidate_signature')->unique()->count()],
                ['Unique compositions', collect($bundles)->pluck('composition_signature')->unique()->count()],
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
