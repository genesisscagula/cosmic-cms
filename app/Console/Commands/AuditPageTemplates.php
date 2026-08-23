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
                'image_heavy' => $audit['image_heavy_section_count'], 'image_ratio' => $audit['image_heavy_ratio'],
                'image_run' => $audit['max_consecutive_image_heavy'], 'modes' => $audit['content_mode_count'],
                'composition_issues' => implode(' ', $audit['composition_issues']),
                'novelty_level' => $profile['composition_novelty_level'] ?? 'unknown',
                'exact_uses' => $profile['exact_composition_uses'] ?? 1,
                'auto_balanced' => (bool) ($template['auto_balanced'] ?? false),
                'balanced_replacements' => count($template['balanced_replacements'] ?? []),
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
            'image_rhythm_review' => $rows->filter(fn ($row) => $row['composition_issues'] !== '')->count(),
            'auto_balanced' => $rows->where('auto_balanced', true)->count(),
            'balanced_replacements' => $rows->sum('balanced_replacements'),
        ];
        if ($this->option('json')) { $this->line(json_encode(['summary'=>$summary,'diversity'=>$diversityReport,'templates'=>$rows->values()], JSON_PRETTY_PRINT)); return self::SUCCESS; }
        $this->info('Cosmic Template Audit'); $this->table(array_keys($summary), [array_values($summary)]);
        $issues = $rows->filter(fn ($r) => $r['status'] === 'invalid' || $r['invalid'] !== '');
        if ($issues->isNotEmpty()) {
            $this->table(
                ['key','score','status','sections','rhythm','media','invalid'],
                $issues->map(fn ($row) => collect($row)->only(['key','score','status','sections','rhythm','media','invalid'])->all())->values()->all()
            );
        }
        $rhythmIssues = $rows->filter(fn ($row) => $row['composition_issues'] !== '');
        if ($rhythmIssues->isNotEmpty()) {
            $this->warn('Templates needing image/content rhythm review:');
            $this->table(
                ['key','score','status','image_heavy','image_ratio','image_run','modes','composition_issues'],
                $rhythmIssues->map(fn ($row) => collect($row)->only(['key','score','status','image_heavy','image_ratio','image_run','modes','composition_issues'])->all())->values()->all()
            );
        }
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
