<?php

namespace App\Console\Commands;

use App\Cosmic\Pricing\BlockPricingRegistry;
use App\Services\SparkCatalog;
use Illuminate\Console\Command;

class AuditSparkIntelligenceMetadata extends Command
{
    protected $signature = 'cosmic:audit-spark-intelligence {--strict : Fail when any Spark has incomplete AI metadata}';

    protected $description = 'Audit the deterministic AI matching metadata for every registered Spark.';

    public function handle(): int
    {
        $registered = BlockPricingRegistry::all();
        $catalog = SparkCatalog::all();
        $errors = [];
        $warnings = [];
        $seen = [];

        if (count($registered) !== count($catalog)) {
            $errors[] = sprintf('Registry/catalog count mismatch: %d registered vs %d catalogued.', count($registered), count($catalog));
        }

        $requiredLists = [
            'aliases', 'traits', 'use_cases', 'layout', 'style', 'style_traits', 'visual_traits',
            'intent', 'industry_fit', 'position_fit', 'capabilities', 'search_terms',
        ];

        foreach ($catalog as $spark) {
            $key = (string) ($spark['key'] ?? '');
            if ($key === '') {
                $errors[] = 'Catalog contains a Spark with no key.';
                continue;
            }
            if (isset($seen[$key])) {
                $errors[] = "Duplicate Spark key [{$key}].";
            }
            $seen[$key] = true;

            if ((int) ($spark['ai_metadata_version'] ?? 0) < 2) {
                $errors[] = "Spark [{$key}] is not on AI metadata v2.";
            }
            if (trim((string) ($spark['semantic_type'] ?? '')) === '') {
                $errors[] = "Spark [{$key}] has no semantic_type.";
            }
            if (trim((string) ($spark['media'] ?? '')) === '') {
                $errors[] = "Spark [{$key}] has no media classification.";
            }

            foreach ($requiredLists as $field) {
                $value = $spark[$field] ?? null;
                if (! is_array($value) || $value === []) {
                    $errors[] = "Spark [{$key}] has empty or invalid {$field}.";
                    continue;
                }
                if (count($value) !== count(array_unique($value))) {
                    $errors[] = "Spark [{$key}] has duplicate {$field}.";
                }
            }

            $search = array_map('strtolower', (array) ($spark['search_terms'] ?? []));
            $semantic = strtolower((string) ($spark['semantic_type'] ?? ''));
            if ($semantic !== '' && ! in_array($semantic, $search, true)) {
                $errors[] = "Spark [{$key}] search_terms does not include semantic_type [{$semantic}].";
            }
            if ($semantic === 'general') {
                $warnings[] = "Spark [{$key}] remains semantically general; consider an explicit override if matching is weak.";
            }
        }

        $this->info(sprintf('Registered Sparks: %d', count($registered)));
        $this->info(sprintf('Catalogued Sparks: %d', count($catalog)));
        $this->info('AI metadata schema: v2');

        if ($warnings !== []) {
            $this->warn(sprintf('Warnings: %d', count($warnings)));
            foreach (array_slice($warnings, 0, 20) as $warning) $this->line('  - '.$warning);
            if (count($warnings) > 20) $this->line('  - ... '.(count($warnings) - 20).' more');
        }

        if ($errors !== []) {
            $this->error(sprintf('Errors: %d', count($errors)));
            foreach ($errors as $error) $this->line('  - '.$error);
            return self::FAILURE;
        }

        $this->info('Spark intelligence metadata audit PASS.');
        return self::SUCCESS;
    }
}
