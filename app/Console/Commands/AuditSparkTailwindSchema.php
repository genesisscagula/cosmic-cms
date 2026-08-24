<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class AuditSparkTailwindSchema extends Command
{
    protected $signature = 'cosmic:audit-spark-tailwind {--strict : Fail when any unapproved className bypass remains}';
    protected $description = 'Audit Spark Tailwind schema coverage and Builder/Export parity contracts.';

    public function handle(): int
    {
        $root = resource_path('js/Pages/Websites/Blocks');
        $files = [];
        $direct = [];
        $bridged = 0;

        $approvedBypassFiles = [
            'Blog/BlogHubBlock.jsx' => 'mixed blog composer / builder chrome',
            'Content/StructuredContentBlocks.jsx' => 'builder source/filter controls',
            'Contact/ContactFormModernBlock.jsx' => 'form-field editor modal chrome',
            'Shared/EditableImage.jsx' => 'shared editor primitive',
            'Shared/EditableText.jsx' => 'shared editor primitive',
            'Shared/EditableButton.jsx' => 'shared editor primitive',
            'General/LunaCustomSectionBlock.jsx' => 'builder-only Ask Luna control',
            'Hero/HeroSliderFadeBlock.jsx' => 'builder-only slide editor controls',
        ];

        if (! is_dir($root)) {
            $this->error("Spark block directory not found: {$root}");
            return self::FAILURE;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if (! $file->isFile() || ! Str::endsWith($file->getFilename(), ['.jsx', '.js'])) continue;
            $path = $file->getPathname();
            $relative = ltrim(str_replace('\\', '/', Str::after($path, str_replace('\\', '/', $root))), '/');
            $source = file_get_contents($path) ?: '';
            $files[] = $relative;
            $bridged += substr_count($source, 'sparkTw(');

            foreach (preg_split('/\R/', $source) ?: [] as $index => $line) {
                if (! str_contains($line, 'className=')) continue;
                if (str_contains($line, 'sparkTw(')) continue;
                $direct[] = [
                    'file' => $relative,
                    'line' => $index + 1,
                    'approved' => array_key_exists($relative, $approvedBypassFiles),
                    'reason' => $approvedBypassFiles[$relative] ?? 'unapproved customer-facing bypass',
                ];
            }
        }

        $families = (array) config('spark-tailwind-schema.migration_families', []);
        $types = [];
        foreach ($families as $family) {
            foreach ((array) ($family['types'] ?? []) as $type) $types[(string) $type] = true;
        }

        $unapproved = array_values(array_filter($direct, fn (array $row) => ! $row['approved']));
        $approved = array_values(array_filter($direct, fn (array $row) => $row['approved']));

        $compiler = file_get_contents(app_path('Helpers/CmsHtmlCompiler.php')) ?: '';
        $previewDeployment = file_get_contents(app_path('Services/PreviewDeploymentService.php')) ?: '';
        $liveDeployment = file_get_contents(app_path('Services/DeploymentConnectorArchive.php')) ?: '';
        $builder = file_get_contents(resource_path('js/Pages/Websites/Builder.jsx')) ?: '';
        $parity = [
            'builder_schema_marker' => str_contains($builder, 'data-cosmic-tailwind-schema'),
            'builder_slot_marker' => str_contains($builder, 'cosmic-tw-slot--') || $bridged > 0,
            'export_schema_resolver' => str_contains($compiler, 'sparkTw(') && str_contains($compiler, 'SparkTailwindSchemaContract'),
            'preview_dynamic_tailwind_guard' => str_contains($previewDeployment, 'hasDynamicTailwindSchema') && str_contains($previewDeployment, 'cdn.tailwindcss.com'),
            'live_dynamic_tailwind_asset' => str_contains($liveDeployment, 'cdn.tailwindcss.com'),
        ];

        $this->info('Spark Tailwind schema audit');
        $this->line('Block source files: '.count($files));
        $this->line('Schema bridge bindings: '.$bridged);
        $this->line('Registered schema-backed Spark types: '.count($types));
        $this->line('Approved editor-only/shared bypass lines: '.count($approved));
        $this->line('Unapproved customer-facing bypass lines: '.count($unapproved));

        foreach ($parity as $name => $ok) {
            $this->line(($ok ? '<info>PASS</info>' : '<error>FAIL</error>').' '.$name);
        }

        if ($unapproved !== []) {
            $this->newLine();
            $this->error('Unapproved Tailwind className bypasses:');
            foreach (array_slice($unapproved, 0, 40) as $row) {
                $this->line("- {$row['file']}:{$row['line']}");
            }
        }

        $parityOk = ! in_array(false, $parity, true);
        if (($this->option('strict') && $unapproved !== []) || ! $parityOk) return self::FAILURE;

        return self::SUCCESS;
    }
}
