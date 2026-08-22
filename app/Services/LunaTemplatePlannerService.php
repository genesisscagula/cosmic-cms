<?php

namespace App\Services;

use App\AI\Schemas\SchemaManager;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;

final class LunaTemplatePlannerService
{
    /** @return array{template_key:string,template_name:string,sections:array,planner:string,metadata_candidates:int} */
    public function plan(string $prompt): array
    {
        $candidates = $this->shortlist($prompt, PageTemplateCatalog::plannerIndex());
        if ($candidates === []) {
            throw new RuntimeException('No Cosmic page templates are available to Luna.');
        }

        $catalogJson = json_encode($candidates, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $system = <<<'TXT'
You are Luna, the Cosmic CMS template art director.

Your job is ONLY to choose the best existing human-designed Cosmic page template for the website request. You receive compact TEMPLATE METADATA only. You do not receive content schemas and you must not invent sections, layouts, Spark IDs, or template IDs.

Return JSON only:
{"template_key":"exact-key","reason":"short internal rationale"}

SELECTION RULES
- Match industry and page intent first, then audience and style.
- Prefer image-led/editorial/cinematic templates for hospitality, property, travel, food, beauty and visually driven brands.
- Prefer product-led/bento/dashboard/comparison templates for SaaS, AI and software.
- Prefer credibility/process/consultation templates for legal, finance, consulting and professional services.
- Avoid repetitive card/grid-heavy compositions when a more editorial or mixed-media template fits.
- Use media_mode, layout_style, text_density, visual_rhythm, visual_score and quality_score to create a premium balanced result.
- Prefer quality_status=excellent. Avoid review/invalid templates unless no stronger industry match exists.
- Prefer a clear visual rhythm: alternate dense information with editorial/media/proof moments; avoid card-on-card-on-card pacing.
- A template should normally contain one hero, useful proof, and a conversion close.
- If the prompt explicitly asks for video, slider, parallax, editorial, minimal, luxury, bento or another presentation, strongly honor that signal when industry-compatible.
- hero_media_mode describes the opening hero media contract. For image, slider, or video hero templates, respect overlay_header_recommended and overlay_header_default as part of the template design rather than treating header overlay as a separate manual choice.
- Never return a key that is not in the candidate metadata.
TXT;

        $response = OpenAI::chat()->create([
            'model' => config('openai.planner_model', env('OPENAI_MODEL', 'gpt-5-mini')),
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => "WEBSITE REQUEST\n{$prompt}\n\nTEMPLATE METADATA\n{$catalogJson}"],
            ],
        ]);

        $data = $this->decode((string) ($response->choices[0]->message->content ?? ''));
        $key = trim((string) ($data['template_key'] ?? ''));
        $allowed = collect($candidates)->keyBy('key');
        if ($key === '' || ! $allowed->has($key)) {
            $key = (string) $candidates[0]['key'];
        }

        $template = PageTemplateCatalog::find($key);
        if (! $template) {
            throw new RuntimeException('Luna selected an unavailable Cosmic template.');
        }

        $schemaMap = SchemaManager::map();
        $sections = collect($template['sections'] ?? [])
            ->filter(fn ($section) => is_string($section) && isset($schemaMap[$section]))
            ->unique()
            ->values()
            ->all();

        if ($sections === []) {
            throw new RuntimeException("Template [{$key}] has no registered Spark schemas.");
        }

        $metadata = app(TemplateMetadataService::class)->enrich($template);

        return [
            'template_key' => $key,
            'template_name' => (string) ($template['name'] ?? $key),
            'sections' => $sections,
            'hero_media_mode' => (string) ($metadata['hero_media_mode'] ?? 'none'),
            'overlay_header_recommended' => (bool) ($metadata['overlay_header_recommended'] ?? false),
            'overlay_header_default' => (bool) ($metadata['overlay_header_default'] ?? false),
            'planner' => 'luna_template_metadata',
            'metadata_candidates' => count($candidates),
        ];
    }

    private function shortlist(string $prompt, array $templates): array
    {
        $terms = collect(preg_split('/[^a-z0-9]+/i', Str::lower($prompt)) ?: [])
            ->filter(fn ($term) => strlen($term) >= 3)
            ->unique()
            ->values();

        $scored = collect($templates)->map(function (array $template) use ($terms) {
            $industry = Str::lower(implode(' ', $template['industries'] ?? []));
            $aliases = Str::lower(implode(' ', $template['aliases'] ?? []));
            $style = Str::lower(implode(' ', $template['style'] ?? []));
            $intent = Str::lower(implode(' ', $template['page_intents'] ?? []));
            $description = Str::lower((string) ($template['description'] ?? ''));
            $haystack = implode(' ', [$industry, $aliases, $style, $intent, $description]);

            $score = $terms->sum(function ($term) use ($industry, $aliases, $style, $intent, $haystack) {
                if (str_contains($industry, $term)) return 12;
                if (str_contains($aliases, $term)) return 8;
                if (str_contains($intent, $term)) return 7;
                if (str_contains($style, $term)) return 5;
                return str_contains($haystack, $term) ? 2 : 0;
            });
            $score += ((int) ($template['visual_score'] ?? 0)) / 25;
            $score += ((int) ($template['quality_score'] ?? 0)) / 18;
            $score += ($template['quality_status'] ?? '') === 'excellent' ? 3 : 0;
            $score += ($template['visual_rhythm'] ?? '') === 'editorial' ? 1.5 : 0;
            $score -= ($template['quality_status'] ?? '') === 'review' ? 4 : 0;
            $score -= ($template['quality_status'] ?? '') === 'invalid' ? 50 : 0;
            $score += ($template['premium_level'] ?? '') === 'signature' ? 2 : 0;

            return array_merge($template, ['_match_score' => round($score, 2)]);
        })->sortByDesc('_match_score')->values();

        $top = $scored->take(48);
        if ($top->where('_match_score', '>', 4)->count() < 12) {
            $top = $top->concat($scored->filter(fn ($template) => ($template['premium_level'] ?? '') !== 'standard')->take(18));
        }

        return $top->unique('key')->take(60)->map(function ($template) {
            unset($template['_match_score']);
            return $template;
        })->values()->all();
    }

    private function decode(string $content): array
    {
        $content = preg_replace('/^```json\s*/i', '', trim($content)) ?? $content;
        $content = preg_replace('/^```\s*/i', '', $content) ?? $content;
        $content = preg_replace('/```\s*$/i', '', $content) ?? $content;
        $data = json_decode(trim($content), true);
        return is_array($data) ? $data : [];
    }
}
