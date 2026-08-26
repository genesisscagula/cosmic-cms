<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class AuditLunaNestedRouter extends Command
{
    protected $signature = 'cosmic:audit-luna-nested-router {--strict : Exit non-zero when a required contract is missing}';
    protected $description = 'Audit Luna nested routing and full Spark schema editing contracts.';

    public function handle(): int
    {
        $checks = [
            'API 1 chat/action router' => [
                app_path('Services/LunaIntentGateway.php'),
                ['{"intent":"action"}', 'aiRoute(', "['chat','action']"],
            ],
            'API 2 action-source router' => [
                app_path('Services/LunaIntentGateway.php'),
                ['aiActionSource(', 'standard', 'reference', 'action_source'],
            ],
            'API 3 CMS scope router' => [
                app_path('Services/LunaIntentGateway.php'),
                ['aiActionScope(', "'sparks'", "'navigation'", "'theme'", "'publish'"],
            ],
            'API 4 Spark action router' => [
                app_path('Services/LunaIntentGateway.php'),
                ['aiSparkAction(', 'edit_spark', 'change_spark', 'add_spark', 'remove_spark', 'custom_spark', 'reference_spark', 'reorder_spark'],
            ],
            'Reference Spark sub-router' => [
                app_path('Services/LunaIntentGateway.php'),
                ['reference_spark', 'aiReferenceScope(', 'whole_page', 'single_spark', 'aiReferenceMode(', 'layout_only', 'layout_and_theme'],
            ],
            'Reference Spark placement UX/executor' => [
                app_path('Http/Controllers/CustomSparkController.php'),
                ['reference_target_index', 'reference_placement', 'reference_place', 'ai_flex_insert_before', 'ai_flex_insert_after', 'reference_vision_placement_v1'],
            ],
            'Whole-page reference composer' => [
                app_path('Services/LunaAiFlexSparkService.php'),
                ['generatePageFromReference(', 'sol_reference_page_v1', 'registered_catalog', 'page_plan'],
            ],
            'API 4 current-page Spark selector' => [
                app_path('Services/LunaIntentGateway.php'),
                ['aiSparkTarget(', 'AVAILABLE PAGE SPARKS', 'SPARK ACTION', 'spark_target'],
            ],
            'API 5 full Spark schema editor' => [
                app_path('Services/LunaSparkSchemaEditorService.php'),
                ['FULL EDITABLE SPARK SCHEMA', 'FULL CURRENT TAILWIND SCHEMA', 'Return the COMPLETE editable object'],
            ],
            'API 5 deterministic diff guards' => [
                app_path('Services/LunaSparkSchemaEditorService.php'),
                ['protected_tailwind_removed', "'diff'=>[", 'before_fingerprint', 'after_fingerprint'],
            ],
            'Dynamic-class-safe patch persistence' => [
                app_path('Services/LunaSparkSchemaEditorService.php'),
                ["'add'=>array_values", "'remove'=>array_values", 'original renderer classes'],
            ],
            'Spark route fail-closed executor' => [
                app_path('Http/Controllers/CustomSparkController.php'),
                ['spark_full_schema_edit', 'fail-closed', "routing.spark_action')==='edit_spark"],
            ],
            'Batch 2 structural Spark branch executor' => [
                app_path('Http/Controllers/CustomSparkController.php'),
                ['lunaSparkStructuralBranchPlan(', "'change_spark'", "'add_spark'", "'remove_spark'", "'reorder_spark'", 'spark_structural_v1'],
            ],
            'Batch 2 registered Spark content preservation' => [
                app_path('Http/Controllers/CustomSparkController.php'),
                ['lunaRegisteredSparkCandidate(', 'lunaPreserveCompatibleSparkContent(', "'preserve_content'=>true"],
            ],
            'Spark route bypasses typography shortcut' => [
                app_path('Http/Controllers/CustomSparkController.php'),
                ["routing.menu_scope')==='sparks'", '? null', 'lunaTypographyAction('],
            ],
            'Builder Tailwind runtime' => [
                resource_path('js/Pages/Websites/Blocks/Shared/sparkTailwindRuntime.js'),
                ['luna_tailwind_schema', 'add', 'remove'],
            ],
            'Export/live Tailwind resolver' => [
                app_path('Helpers/CmsHtmlCompiler.php'),
                ['sparkTw(', 'SparkTailwindSchemaContract'],
            ],
        ];

        $failed = [];
        foreach ($checks as $label => [$path, $needles]) {
            $content = is_file($path) ? (string) file_get_contents($path) : '';
            $missing = [];
            foreach ($needles as $needle) {
                if ($content === '' || ! str_contains($content, $needle)) $missing[] = $needle;
            }
            if ($missing === []) {
                $this->components->info($label);
            } else {
                $failed[$label] = $missing;
                $this->components->error($label.' — missing: '.implode(', ', $missing));
            }
        }

        $this->newLine();
        if ($failed === []) {
            $this->info('Nested Luna router audit passed.');
            return self::SUCCESS;
        }

        $this->warn(count($failed).' nested-router contract check(s) failed.');
        return $this->option('strict') ? self::FAILURE : self::SUCCESS;
    }
}
