<?php
namespace App\Services;

use App\AI\Schemas\SchemaManager;

final class TemplateQualityAuditor
{
    public function audit(array $template): array
    {
        $sections = array_values(array_filter($template['sections'] ?? [], 'is_string'));
        // SchemaManager builds the full Spark schema registry. A planner pass
        // audits 500+ templates, so rebuilding it per template made a cold
        // shortlist take tens of seconds.
        static $registered = null;
        $registered ??= array_keys(SchemaManager::map());
        $invalid = array_values(array_diff($sections, $registered));
        $duplicateSections = count($sections) !== count(array_unique($sections));
        $heroCount = count(array_filter($sections, fn ($s) => str_starts_with($s, 'hero_')));
        $hasClose = collect($sections)->contains(fn ($s) => str_starts_with($s, 'cta_') || str_starts_with($s, 'contact_') || str_starts_with($s, 'lead_') || $s === 'image_cta_banner');
        $proofCount = collect($sections)->filter(fn ($s) => str_starts_with($s, 'testimonials_') || str_starts_with($s, 'stats_') || str_starts_with($s, 'portfolio_') || str_starts_with($s, 'case_stud'))->count();
        $denseCount = collect($sections)->filter(fn ($s) => str_contains($s, 'cards') || str_contains($s, 'grid') || str_contains($s, 'comparison') || str_contains($s, 'pricing'))->count();
        $mediaCount = collect($sections)->filter(fn ($s) => str_contains($s, 'image') || str_contains($s, 'portfolio') || str_contains($s, 'video') || str_contains($s, 'gallery') || str_contains($s, 'slider'))->count();

        $score = 100;
        $score -= count($invalid) * 40;
        $score -= $duplicateSections ? 20 : 0;
        $score -= $heroCount !== 1 ? 25 : 0;
        $score -= count($sections) < 6 ? 12 : 0;
        $score -= count($sections) > 8 ? 8 : 0;
        $score -= ! $hasClose ? 8 : 0;
        $score -= $proofCount === 0 ? 8 : 0;
        $score -= $denseCount >= 4 ? 8 : 0;
        $score -= $mediaCount === 0 ? 6 : 0;

        return [
            'quality_score' => max(0, $score),
            'invalid_sections' => $invalid,
            'has_duplicate_sections' => $duplicateSections,
            'hero_count' => $heroCount,
            'has_conversion_close' => $hasClose,
            'proof_count' => $proofCount,
            'dense_section_count' => $denseCount,
            'media_section_count' => $mediaCount,
            'quality_status' => $score >= 92 ? 'excellent' : ($score >= 82 ? 'good' : ($score >= 70 ? 'review' : 'invalid')),
        ];
    }
}
