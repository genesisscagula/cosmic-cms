<?php
namespace App\Services;

use App\AI\Registries\SparkPlannerRegistry;
use App\AI\Schemas\SchemaManager;
use Illuminate\Support\Str;

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
        $imageProfile = $this->imageProfile($sections);
        $modeProfile = $this->modeProfile($sections);

        $visualFirst = Str::contains(Str::lower(implode(' ', array_merge(
            (array) ($template['style'] ?? []),
            (array) ($template['industry'] ?? []),
            (array) ($template['features'] ?? [])
        ))), ['cinematic', 'visual', 'image-led', 'portfolio', 'photography', 'hospitality', 'restaurant', 'hotel', 'resort', 'travel', 'property', 'fashion']);
        // Even visual-first pages need breathing room. A maximum 60% visual
        // density plus strict alternation keeps hospitality/editorial templates
        // immersive without turning every section into another photo grid.
        $imageRatioLimit = $visualFirst ? 0.60 : 0.55;
        $imageRunLimit = 1;
        $issues = [];
        if ($imageProfile['ratio'] > $imageRatioLimit) {
            $issues[] = sprintf('Image-heavy sections occupy %.0f%%; target at most %.0f%% for this template.', $imageProfile['ratio'] * 100, $imageRatioLimit * 100);
        }
        if ($imageProfile['max_consecutive'] > $imageRunLimit) {
            $issues[] = "{$imageProfile['max_consecutive']} image-heavy sections run consecutively; target at most {$imageRunLimit}.";
        }
        if ($modeProfile['count'] < 3 && count($sections) >= 6) {
            $issues[] = 'The composition uses fewer than three distinct content modes.';
        }

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
        $score -= $imageProfile['ratio'] > $imageRatioLimit ? min(18, (int) ceil(($imageProfile['ratio'] - $imageRatioLimit) * 50)) : 0;
        $score -= max(0, $imageProfile['max_consecutive'] - $imageRunLimit) * 5;
        $score -= $modeProfile['count'] < 3 && count($sections) >= 6 ? 7 : 0;

        return [
            'quality_score' => max(0, $score),
            'invalid_sections' => $invalid,
            'has_duplicate_sections' => $duplicateSections,
            'hero_count' => $heroCount,
            'has_conversion_close' => $hasClose,
            'proof_count' => $proofCount,
            'dense_section_count' => $denseCount,
            'media_section_count' => $mediaCount,
            'image_heavy_section_count' => $imageProfile['count'],
            'image_heavy_ratio' => $imageProfile['ratio'],
            'max_consecutive_image_heavy' => $imageProfile['max_consecutive'],
            'content_modes' => $modeProfile['modes'],
            'content_mode_count' => $modeProfile['count'],
            'composition_issues' => $issues,
            'quality_status' => $score >= 92 ? 'excellent' : ($score >= 82 ? 'good' : ($score >= 70 ? 'review' : 'invalid')),
        ];
    }

    /** @return array{count:int,ratio:float,max_consecutive:int,sections:array<int,string>} */
    public function imageProfile(array $sections): array
    {
        $sections = array_values(array_filter($sections, 'is_string'));
        $imageHeavy = [];
        $run = 0;
        $maxRun = 0;

        foreach ($sections as $section) {
            if ($this->isImageHeavySection($section)) {
                $imageHeavy[] = $section;
                $run++;
                $maxRun = max($maxRun, $run);
            } else {
                $run = 0;
            }
        }

        return [
            'count' => count($imageHeavy),
            'ratio' => $sections === [] ? 0.0 : round(count($imageHeavy) / count($sections), 3),
            'max_consecutive' => $maxRun,
            'sections' => $imageHeavy,
        ];
    }

    public function isImageHeavySection(string $section): bool
    {
        static $mediaModes = null;
        $mediaModes ??= collect(SparkPlannerRegistry::all())
            ->mapWithKeys(fn (array $spark): array => [
                (string) ($spark['slug'] ?? '') => (string) ($spark['media_mode'] ?? 'mixed_content'),
            ])
            ->all();

        if (in_array($mediaModes[$section] ?? null, ['image_led', 'image_motion'], true)) {
            return true;
        }

        return Str::contains(Str::lower($section), [
            'image', 'gallery', 'portfolio', 'video', 'slider', 'parallax', 'ken_burns',
            'crossfade', 'photo', 'media_', 'visual_', 'cinematic', 'fullscreen',
            'before_after', 'featured_story', 'signature_dishes', 'story_menu',
            'atmosphere', 'room_collection', 'experience_cards', 'destination_story',
            'location_city', 'location_photo', 'showcase',
        ]);
    }

    /** @return array{count:int,modes:array<int,string>} */
    private function modeProfile(array $sections): array
    {
        $modes = [];
        foreach ($sections as $section) {
            $section = Str::lower((string) $section);
            $modes[] = match (true) {
                str_starts_with($section, 'hero_') => 'hero',
                Str::contains($section, ['testimonial', 'review', 'proof_', 'stats_', 'trust_']) => 'proof',
                Str::contains($section, ['contact_', 'lead_', 'cta_', 'reservation_cta', 'booking']) => 'conversion',
                Str::contains($section, ['service', 'feature', 'menu', 'dish', 'room', 'package', 'pricing']) => 'offering',
                Str::contains($section, ['process', 'timeline', 'workflow', 'step']) => 'process',
                Str::contains($section, ['about', 'story', 'mission', 'value', 'content_']) => 'story',
                $this->isImageHeavySection($section) => 'media',
                default => 'information',
            };
        }
        $modes = array_values(array_unique($modes));
        return ['count' => count($modes), 'modes' => $modes];
    }
}
