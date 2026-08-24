<?php

namespace App\Services;

use App\AI\Schemas\SchemaManager;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Exceptions\TransporterException;
use RuntimeException;

final class LunaTemplatePlannerService
{
    public function __construct(
        private readonly LunaPageCompositionService $composition,
        private readonly LunaResponsiveIntelligenceService $responsive
    ) {}

    /** @return array{template_key:string,template_name:string,sections:array,theme:string,industry:string,design_direction:string,media_direction:string,planner:string,metadata_candidates:int} */
    public function plan(string $prompt, ?array $allowedTemplateKeys = null): array
    {
        $composition=$this->composition->plannerDirective($prompt);
        $responsiveContract=$this->responsive->plannerDirective();
        $templateIndex = PageTemplateCatalog::plannerIndex();
        if (is_array($allowedTemplateKeys) && $allowedTemplateKeys !== []) {
            $allowed = array_fill_keys(array_values(array_filter(array_map('strval', $allowedTemplateKeys))), true);
            $templateIndex = array_values(array_filter(
                $templateIndex,
                fn (array $template): bool => isset($allowed[(string) ($template['key'] ?? '')]),
            ));
        }
        $candidates = $this->shortlist($prompt, $templateIndex,$composition['context']);
        if ($candidates === []) {
            throw new RuntimeException(is_array($allowedTemplateKeys)
                ? 'The current site bundle has no compatible registered page templates.'
                : 'No Cosmic page templates are available to Luna.');
        }

        $catalogJson = json_encode($candidates, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $system = <<<'TXT'
You are Luna, the Cosmic CMS template art director.

Your job is ONLY to choose the best existing human-designed Cosmic page template for the website request. You receive compact TEMPLATE METADATA only. You do not receive content schemas and you must not invent sections, layouts, Spark IDs, or template IDs.

Return JSON only:
{"template_key":"exact-key","theme":"exact-theme","industry":"short industry","design_direction":"short visual direction","media_direction":"short media direction","reason":"short internal rationale"}

ALLOWED THEMES
midnight, emerald, coffee, rose, dark, ocean, indigo, amber, charcoal, violet, teal, ruby, forest, obsidian, navy, espresso, terracotta, asphalt

THEME RULES
- Choose the theme yourself from the allowed list using industry + requested mood + audience + selected template.
- Do not treat midnight, navy, dark, charcoal, asphalt, or obsidian as generic premium defaults.
- Choose midnight only when the request genuinely benefits from a night/midnight direction or explicitly asks for it.
- Automotive does not imply dark. Consider teal, emerald, indigo, ocean, amber and other compatible directions when they better fit the brief.
- The theme decision is authoritative for a fresh build and will be locked before content generation.
- If WEBSITE REQUEST includes an EXISTING WEBSITE BRAND CONSTRAINT, that constraint is authoritative: return exactly that current theme while still choosing the best compatible template/Spark composition. Do not reinterpret a different industry or design mood as permission to rebrand.
- If WEBSITE REQUEST includes EXISTING SITE DESIGN DNA, preserve its visual language: color family, typography direction, radius/component treatment, spacing rhythm, background treatment and media direction. Choose a page-appropriate composition without cloning the sibling page's section sequence.
- Page intent may change the layout and Spark variants; it does not by itself authorize a rebrand.

SELECTION RULES
- PAGE COMPOSITION CONTRACT is authoritative for page purpose and section rhythm. Select a template whose registered section sequence satisfies as many required roles as possible.
- Match industry and page intent first, then audience and style.
- Purpose first, layout second: choose sections because they serve the page's job, not merely because their visual effect matches a keyword.
- Do not add pricing, FAQ, statistics, galleries, or card grids simply to fill section count.
- Avoid more than two dense card/grid/comparison sections in a row when a mixed-media/story/proof alternative exists.
- Prefer a useful conversion/contact close and put credibility/proof before or near that close.
- RESPONSIVE DESIGN CONTRACT is authoritative for small-screen viability. Avoid choosing a composition whose value depends on desktop-only width, hover-only interaction, or fixed media sizing when a responsive-compatible alternative exists.
- Treat sliders, bento/grid, comparison, horizontal, timeline, and other wide/interactive templates as responsive-risk sections that require a clear tablet/mobile strategy.
- Prefer image-led/editorial/cinematic templates for hospitality, property, travel, food, beauty and visually driven brands.
- Prefer product-led/bento/dashboard/comparison templates for SaaS, AI and software.
- Prefer credibility/process/consultation templates for legal, finance, consulting and professional services.
- Avoid repetitive card/grid-heavy compositions when a more editorial or mixed-media template fits.
- Image-led does not mean image-only. Prefer roughly 30–55% image-led sections and cap genuinely visual hospitality/work pages at 60%.
- Never place image-heavy sections consecutively. Alternate them with a compatible story, process, values, statistics, text-led proof, FAQ, location, or contact Spark unless the user explicitly requests one continuous gallery sequence.
- Break image-heavy runs with a compatible story, process, values, statistics, text-led proof, FAQ, location, or contact Spark. The inserted Spark must still serve the page purpose and industry.
- Use composition_novelty_score and semantic_roles to distinguish otherwise equally relevant candidates. Prefer the more novel composition when industry, intent, quality and responsiveness are comparable.
- Do not select a familiar composition merely because its generic tags match more words. Relevance remains first, then page-purpose coverage, then composition novelty.
- Use media_mode, layout_style, text_density, visual_rhythm, visual_score and quality_score to create a premium balanced result.
- Prefer quality_status=excellent. Avoid review/invalid templates unless no stronger industry match exists.
- Prefer a clear visual rhythm: alternate dense information with editorial/media/proof moments; avoid card-on-card-on-card pacing.
- A template should normally contain one hero, useful proof, and a conversion close.
- If the prompt explicitly asks for video, slider, parallax, editorial, minimal, luxury, bento or another presentation, strongly honor that signal when industry-compatible.
- hero_media_mode describes the opening hero media contract. For image, slider, or video hero templates, respect overlay_header_recommended and overlay_header_default as part of the template design rather than treating header overlay as a separate manual choice.
- Never return a key that is not in the candidate metadata.
TXT;

        $response = $this->createChatCompletion([
            'model' => config('openai.planner_model', env('OPENAI_MODEL', 'gpt-5-mini')),
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => "WEBSITE REQUEST\n{$prompt}\n\n{$composition['directive']}\n\n{$responsiveContract}\n\nTEMPLATE METADATA\n{$catalogJson}"],
            ],
        ]);

        $data = $this->decode((string) ($response->choices[0]->message->content ?? ''));
        $key = trim((string) ($data['template_key'] ?? ''));
        $allowedThemes = ['midnight','emerald','coffee','rose','dark','ocean','indigo','amber','charcoal','violet','teal','ruby','forest','obsidian','navy','espresso','terracotta','asphalt'];
        $theme = Str::lower(trim((string) ($data['theme'] ?? '')));
        if (! in_array($theme, $allowedThemes, true)) {
            $theme = 'emerald';
        }
        $allowed = collect($candidates)->keyBy('key');
        if ($key === '' || ! $allowed->has($key)) {
            $key = (string) $candidates[0]['key'];
        }

        $template = PageTemplateCatalog::find($key);
        if (! $template) {
            throw new RuntimeException('Luna selected an unavailable Cosmic template.');
        }

        $selectedAudit=$this->composition->audit($template['sections']??[],$composition['context']);
        if(!$selectedAudit['pass']){
            foreach($candidates as $candidate){
                $candidateTemplate=PageTemplateCatalog::find((string)($candidate['key']??''));
                if(!$candidateTemplate)continue;
                $candidateAudit=$this->composition->audit($candidateTemplate['sections']??[],$composition['context']);
                if($candidateAudit['pass']){
                    $key=(string)$candidate['key'];
                    $template=$candidateTemplate;
                    $selectedAudit=$candidateAudit;
                    break;
                }
            }
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
            'theme' => $theme,
            'industry' => trim((string) ($data['industry'] ?? 'general')),
            'design_direction' => trim((string) ($data['design_direction'] ?? 'premium, coherent and practical')),
            'media_direction' => trim((string) ($data['media_direction'] ?? 'industry-relevant imagery only')),
            'reason' => trim((string) ($data['reason'] ?? '')),
            'hero_media_mode' => (string) ($metadata['hero_media_mode'] ?? 'none'),
            'overlay_header_recommended' => (bool) ($metadata['overlay_header_recommended'] ?? false),
            'overlay_header_default' => (bool) ($metadata['overlay_header_default'] ?? false),
            'planner' => 'luna_template_metadata',
            'metadata_candidates' => count($candidates),
            'page_intent' => (string)($composition['context']['page_intent']??'home'),
            'composition_industry' => (string)($composition['context']['industry']??'general'),
            'composition_roles' => $selectedAudit['roles']??[],
            'composition_issues' => $selectedAudit['issues']??[],
            'composition_pass' => (bool)($selectedAudit['pass']??false),
            'responsive_risks' => $this->responsive->auditSections($sections)['risks']??[],
            'responsive_contract' => $this->responsive->contract($sections),
        ];
    }

    /**
     * Retry only transient transport failures. A reset connection should not
     * discard a valid public trial before its staged Website can be persisted.
     */
    private function createChatCompletion(array $payload): mixed
    {
        $attemptLimit = max(1, (int) config('openai.pipeline_stage_attempts', 2));
        $delayMs = max(100, (int) config('openai.pipeline_retry_delay_ms', 350));
        $lastException = null;

        for ($attempt = 1; $attempt <= $attemptLimit; $attempt++) {
            try {
                return OpenAI::chat()->create($payload);
            } catch (TransporterException $exception) {
                $lastException = $exception;
                logger()->warning('[LunaTemplatePlanner] OpenAI transport attempt failed.', [
                    'attempt' => $attempt,
                    'attempt_limit' => $attemptLimit,
                    'message' => $exception->getMessage(),
                ]);

                if ($attempt < $attemptLimit) {
                    usleep($delayMs * 1000);
                }
            }
        }

        throw $lastException;
    }

    private function shortlist(string $prompt, array $templates, array $composition=[]): array
    {
        $terms = collect(preg_split('/[^a-z0-9]+/i', Str::lower($prompt)) ?: [])
            ->filter(fn ($term) => strlen($term) >= 3)
            ->unique()
            ->values();

        $scored = collect($templates)->map(function (array $template) use ($terms,$composition) {
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
            $score += ((int) ($template['composition_novelty_score'] ?? 50)) / 18;
            $score -= max(0, ((int) ($template['exact_composition_uses'] ?? 1)) - 1) * 2.5;
            $imageRatio=(float)($template['image_heavy_ratio']??0);
            $imageRun=(int)($template['max_consecutive_image_heavy']??0);
            $score -= max(0,$imageRatio-0.60)*42;
            $score -= max(0,$imageRun-1)*10;
            $score += min(5,(int)($template['content_mode_count']??0))*1.2;

            $audit=$this->composition->audit($template['sections']??[],$composition);
            $requiredCount=max(1,count($composition['required_roles']??[]));
            $satisfied=$requiredCount-count(array_filter($audit['issues']??[],fn($issue)=>str_starts_with($issue,'Missing page-purpose role:')));
            $score += max(0,$satisfied)*4;
            $score += ($audit['pass']??false)?14:0;
            $score -= count($audit['issues']??[])*2.5;

            $industryPriority=implode(' ',array_map('strtolower',$composition['industry_priorities']??[]));
            $templateFeatures=Str::lower(implode(' ',array_merge($template['features']??[],$template['page_intents']??[])));
            foreach($composition['industry_priorities']??[] as $priority){
                if(Str::contains($templateFeatures,Str::lower((string)$priority)))$score+=3;
            }

            return array_merge($template, ['_match_score' => round($score, 2)]);
        })->sortByDesc('_match_score')->values();

        $top = collect($this->diverseCandidates($scored->all(), 48));
        if ($top->where('_match_score', '>', 4)->count() < 12) {
            $top = $top->concat($scored->filter(fn ($template) => ($template['premium_level'] ?? '') !== 'standard')->take(18));
        }

        return $top->unique('key')->take(60)->map(function ($template) {
            unset($template['_match_score']);
            return $template;
        })->values()->all();
    }

    /**
     * Maximal-marginal-relevance shortlist: preserve prompt relevance while
     * preventing a near-identical Spark set from crowding out alternatives.
     */
    private function diverseCandidates(array $ranked, int $limit): array
    {
        $selected = [];
        $remaining = array_values($ranked);
        while ($remaining !== [] && count($selected) < $limit) {
            $bestIndex = 0;
            $bestScore = -INF;
            foreach ($remaining as $index => $candidate) {
                $overlap = 0.0;
                foreach ($selected as $chosen) {
                    $overlap = max($overlap, $this->sectionSimilarity(
                        (array) ($candidate['sections'] ?? []),
                        (array) ($chosen['sections'] ?? [])
                    ));
                }
                $score = (float) ($candidate['_match_score'] ?? 0)
                    - ($overlap * 5.5)
                    + (((int) ($candidate['composition_novelty_score'] ?? 50)) / 35);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestIndex = $index;
                }
            }
            $selected[] = $remaining[$bestIndex];
            array_splice($remaining, $bestIndex, 1);
        }

        return $selected;
    }

    private function sectionSimilarity(array $left, array $right): float
    {
        $left = array_values(array_unique(array_filter($left, 'is_string')));
        $right = array_values(array_unique(array_filter($right, 'is_string')));
        $union = array_unique(array_merge($left, $right));
        if ($union === []) return 1.0;

        return count(array_intersect($left, $right)) / count($union);
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
