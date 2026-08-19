<?php
namespace App\Console\Commands;

use App\Services\PageTemplateCatalog;
use App\Services\TemplateMetadataService;
use App\Services\TemplateQualityAuditor;
use Illuminate\Console\Command;

class AuditPageTemplates extends Command
{
    protected $signature = 'cosmic:audit-templates {--json}';
    protected $description = 'Audit Cosmic page templates for schema integrity, composition quality and diversity.';

    public function handle(TemplateQualityAuditor $auditor, TemplateMetadataService $metadata): int
    {
        $templates = PageTemplateCatalog::all();
        $rows = collect($templates)->map(function ($template) use ($auditor, $metadata) {
            $audit = $auditor->audit($template);
            $meta = $metadata->enrich($template);
            return [
                'key' => $template['key'], 'score' => $audit['quality_score'], 'status' => $audit['quality_status'],
                'sections' => count($template['sections'] ?? []), 'rhythm' => $meta['visual_rhythm'],
                'media' => $meta['media_mode'], 'invalid' => implode(',', $audit['invalid_sections']),
            ];
        });
        $summary = [
            'templates' => $rows->count(), 'unique_keys' => $rows->pluck('key')->unique()->count(),
            'excellent' => $rows->where('status', 'excellent')->count(), 'good' => $rows->where('status', 'good')->count(),
            'review' => $rows->where('status', 'review')->count(), 'invalid' => $rows->where('status', 'invalid')->count(),
        ];
        if ($this->option('json')) { $this->line(json_encode(['summary'=>$summary,'templates'=>$rows->values()], JSON_PRETTY_PRINT)); return self::SUCCESS; }
        $this->info('Cosmic Template Audit'); $this->table(array_keys($summary), [array_values($summary)]);
        $issues = $rows->filter(fn ($r) => $r['status'] === 'invalid' || $r['invalid'] !== '');
        if ($issues->isNotEmpty()) $this->table(['key','score','status','sections','rhythm','media','invalid'], $issues->values()->all());
        return $issues->isEmpty() ? self::SUCCESS : self::FAILURE;
    }
}
