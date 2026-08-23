<?php
namespace App\Console\Commands;

use App\Services\PageTemplateCatalog;
use App\Services\TemplateMetadataService;
use App\Services\TemplateQualityAuditor;
use App\Services\TemplateDiversityService;
use App\AI\Schemas\SchemaManager;
use Illuminate\Console\Command;

class AuditPageTemplates extends Command
{
    protected $signature = 'cosmic:audit-templates {--json}';
    protected $description = 'Audit Cosmic page templates for schema integrity, composition quality and diversity.';

    public function handle(TemplateQualityAuditor $auditor, TemplateMetadataService $metadata, TemplateDiversityService $diversity): int
    {
        $templates = PageTemplateCatalog::all();
        $profiles = $diversity->profiles($templates);
        $rows = collect($templates)->map(function ($template) use ($auditor, $metadata, $profiles) {
            $audit = $auditor->audit($template);
            $meta = $metadata->enrich($template);
            $profile = $profiles[(string) ($template['key'] ?? '')] ?? [];
            return [
                'key' => $template['key'], 'score' => $audit['quality_score'], 'status' => $audit['quality_status'],
                'sections' => count($template['sections'] ?? []), 'rhythm' => $meta['visual_rhythm'],
                'media' => $meta['media_mode'], 'novelty' => $profile['composition_novelty_score'] ?? 0,
                'novelty_level' => $profile['composition_novelty_level'] ?? 'unknown',
                'exact_uses' => $profile['exact_composition_uses'] ?? 1,
                'invalid' => implode(',', $audit['invalid_sections']),
            ];
        });
        $diversityReport = $diversity->report($templates, array_keys(SchemaManager::map()));
        $summary = [
            'templates' => $rows->count(), 'unique_keys' => $rows->pluck('key')->unique()->count(),
            'excellent' => $rows->where('status', 'excellent')->count(), 'good' => $rows->where('status', 'good')->count(),
            'review' => $rows->where('status', 'review')->count(), 'invalid' => $rows->where('status', 'invalid')->count(),
            'unique_sparks_used' => $diversityReport['unique_sparks_used'],
            'unique_compositions' => $diversityReport['unique_exact_compositions'],
            'duplicate_groups' => $diversityReport['exact_duplicate_groups'],
            'average_novelty' => $diversityReport['average_novelty_score'],
            'familiar' => $diversityReport['familiar_templates'],
        ];
        if ($this->option('json')) { $this->line(json_encode(['summary'=>$summary,'diversity'=>$diversityReport,'templates'=>$rows->values()], JSON_PRETTY_PRINT)); return self::SUCCESS; }
        $this->info('Cosmic Template Audit'); $this->table(array_keys($summary), [array_values($summary)]);
        $issues = $rows->filter(fn ($r) => $r['status'] === 'invalid' || $r['invalid'] !== '');
        if ($issues->isNotEmpty()) $this->table(['key','score','status','sections','rhythm','media','invalid'], $issues->values()->all());
        $this->line(sprintf(
            'Diversity: %d/%d registered Sparks used; %d unique compositions; %d exact duplicate groups; average novelty %.1f.',
            $diversityReport['unique_sparks_used'],
            $diversityReport['registered_sparks'],
            $diversityReport['unique_exact_compositions'],
            $diversityReport['exact_duplicate_groups'],
            $diversityReport['average_novelty_score']
        ));
        return $issues->isEmpty() ? self::SUCCESS : self::FAILURE;
    }
}
