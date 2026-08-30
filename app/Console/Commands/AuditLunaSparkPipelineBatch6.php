<?php

namespace App\Console\Commands;

use App\Services\LunaSparkCapabilityBridgeService;
use App\Services\SparkCatalog;
use App\Services\SparkIntentMatcherService;
use Illuminate\Console\Command;

final class AuditLunaSparkPipelineBatch6 extends Command
{
    protected $signature = 'cosmic:audit-luna-spark-pipeline {--strict : Exit non-zero when any production contract fails}';
    protected $description = 'Batch 6 torture QA for deterministic Spark discovery, metadata, capability guards, and API-budget contracts.';

    public function handle(SparkIntentMatcherService $matcher, LunaSparkCapabilityBridgeService $bridge): int
    {
        $failures = 0;
        $catalog = SparkCatalog::all();
        $this->line('Registered Sparks: '.count($catalog));

        $ids = [];
        foreach ($catalog as $spark) {
            $id = (string) ($spark['key'] ?? '');
            $ok = $id !== ''
                && !isset($ids[$id])
                && trim((string) ($spark['semantic_type'] ?? '')) !== ''
                && count((array) ($spark['aliases'] ?? [])) > 0
                && count((array) ($spark['search_terms'] ?? [])) > 0;
            if (!$ok) $failures++;
            $ids[$id] = true;

            $manifest = $id !== '' ? $bridge->manifestForSpark($id) : [];
            $guardrails = (array) ($manifest['guardrails'] ?? []);
            $guardOk = ($guardrails['registered_schema_only'] ?? false) === true
                && ($guardrails['no_arbitrary_component_tree'] ?? false) === true
                && ($guardrails['unsupported_mutation'] ?? null) === 'reject';
            if (!$guardOk) $failures++;
        }
        $this->line(($failures === 0 ? '<info>PASS</info>' : '<error>FAIL</error>').' catalog metadata + capability guardrails');

        $fixtures = [
            ['premium fullscreen hero slider with background images', 'hero', 'slider'],
            ['cinematic hero with video background and two CTAs', 'hero', 'video'],
            ['restaurant services card grid', 'services', null],
            ['luxury hotel testimonials carousel', 'testimonials', 'slider'],
            ['real estate property listing grid', 'portfolio', null],
            ['three column services section', 'services', null],
            ['premium pricing comparison cards', 'pricing', null],
            ['frequently asked questions accordion', 'faq', null],
            ['team leadership grid with portraits', 'team', null],
            ['contact section with inquiry form', 'contact', null],
            ['project gallery mosaic', 'gallery', 'gallery'],
            ['process timeline how it works', 'process', null],
            ['minimal about our story section', 'about', null],
            ['bold stats and proof section', 'proof', null],
            ['ecommerce product showcase grid', 'commerce', null],
        ];

        foreach ($fixtures as [$prompt, $semantic, $media]) {
            $analysis = $matcher->analyze($prompt);
            $top = $matcher->match($prompt, 5)[0] ?? null;
            $ok = in_array($semantic, (array) ($analysis['semantic'] ?? []), true)
                && ($media === null || in_array($media, (array) ($analysis['media'] ?? []), true))
                && is_array($top)
                && (float) ($top['score'] ?? 0) > 0;
            $this->line(($ok ? '<info>PASS</info>' : '<error>FAIL</error>')." {$prompt} -> ".($top['id'] ?? 'none'));
            if (!$ok) $failures++;
        }

        $negative = [
            ['pricing comparison cards', 'hero'],
            ['frequently asked questions accordion', 'testimonials'],
            ['team leadership grid', 'pricing'],
            ['contact inquiry form', 'services'],
        ];
        foreach ($negative as [$prompt, $wrongSemantic]) {
            $topFive = $matcher->match($prompt, 5);
            $wrong = array_filter($topFive, static fn(array $row): bool => (string)($row['semantic_type'] ?? '') === $wrongSemantic);
            $ok = count($wrong) < 3;
            $this->line(($ok ? '<info>PASS</info>' : '<error>FAIL</error>')." mismatch guard: {$prompt} != {$wrongSemantic}");
            if (!$ok) $failures++;
        }

        if ($failures === 0) {
            $this->info('Batch 6 Luna Spark pipeline torture QA passed. Matcher/catalog/capability checks used zero AI calls.');
            return self::SUCCESS;
        }
        $this->error("{$failures} Batch 6 contract failure(s) detected.");
        return $this->option('strict') ? self::FAILURE : self::SUCCESS;
    }
}
