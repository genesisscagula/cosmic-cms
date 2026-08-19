<?php

namespace App\AI\Planners;

use App\AI\Registries\SparkPlannerRegistry;
use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;

class SparkPlanner
{
    public function plan(string $prompt): array
    {
        $available = SparkPlannerRegistry::all();
        $allowedSlugs = array_column($available, 'slug');
        // SparkPlannerRegistry already supplies audited planner metadata.
        // Sorting within the catalog gives high-value visual Sparks a fairer
        // chance to be noticed without removing functional or classic options.
        $plannerCatalog = collect($available)
            ->sortByDesc(static fn (array $spark): int => (int) ($spark['planner_priority'] ?? 50))
            ->values()
            ->all();

        $system = <<<'PROMPT'
You are the Cosmic Spark Planner.

Your only job is to choose and order the best website Sparks for the requested page.
Do not write website copy and do not invent Spark names.

Rules:
- Return only valid JSON with exactly this shape: {"sections":["spark_slug"]}
- Use only slugs from AVAILABLE SPARKS.
- Select 5 to 10 Sparks unless the page intent clearly needs fewer.
- Preserve a logical storytelling order.
- Use no more than one hero, and place it first when a hero is appropriate.
- Avoid duplicate Sparks.
- CUSTOM GENERATION MODE: treat AVAILABLE SPARKS as audited responsive/export-safe building primitives, not a fixed template recipe.
- Every generation should feel art-directed for this specific business. Vary composition, visual pacing, media density, asymmetry, section sequencing, and transitions between dense and spacious sections.
- If the request includes Previous generated Spark types, strongly prefer different compatible types and a different ordering. Reuse a previous type only when it is clearly the best semantic fit.
- A design seed is a diversity signal: different seeds should not intentionally converge on the same Spark sequence when equally suitable alternatives exist.
- Use planner metadata as real ranking signals: planner_priority is an editorial preference score, visual_score rates visual richness, media_mode identifies the actual composition type, and text_density helps prevent card/text monotony.
- Prefer Sparks marked selection_tier=premium_new when they are a strong fit for the business, page intent, and requested visual direction.
- When two Sparks satisfy the same semantic purpose, prefer the one with the higher planner_priority and visual_score unless its best_for/media_mode conflicts with the request.
- Treat selection_tier=classic Sparks as safe fallbacks, not the default creative choice. Do not repeatedly fall back to the same classic hero or classic body sections when suitable premium_new or modern options exist.
- The preference for newer Sparks applies to the entire page, not only the hero. After the hero, deliberately use newer/specialized services, about, portfolio, stats, testimonials, team, pricing, FAQ, contact, sales, agency, AI, lead-generation, blog, and CTA Sparks when relevant.
- HERO ART DIRECTION: Decide the hero experience based on brand and industry. Use cinematic video, parallax, slider, or fullscreen treatments only when appropriate; do not default every site to the same hero style.
- UNIFIED COLOR SYSTEM: Use one primary solid brand color family and derive secondary/tertiary lighter surfaces from it. Avoid unrelated color worlds across sections.
- Build visual variety across the page: avoid choosing a sequence dominated by generic legacy cards/image/text sections when richer compatible Sparks exist.
- Do not infer visual richness from the word premium alone. A premium text/table Spark can still be text-heavy; use media_mode + visual_score to distinguish it from a genuinely image-led or motion-led Spark.
- VISUAL-FIRST COMPOSITION: when the business/page naturally supports photography, product visuals, portfolio work, places, people, projects, interiors, food, property, or other meaningful media, intentionally choose image-led or mixed-media Sparks throughout the body instead of producing a mostly text-and-card page.
- For an ordinary 6-10 section visual marketing page, target roughly 40-60% of applicable body sections as image-led, mixed-media, gallery/showcase, or strongly visual Sparks when suitable options exist. This is a composition target, not a quota: never add irrelevant imagery.
- Do not place more than 2 primarily text/card/grid sections consecutively when a relevant visual alternative exists. Break long runs of text/cards with an editorial image, split image/content, showcase, gallery, image-backed CTA, location visual, portfolio, or other media-led Spark.
- Prefer a premium visual or mixed-media Spark over a generic text/card Spark when both satisfy the same content purpose. Prefer meaningful business imagery over decorative filler.
- Think about the WHOLE PAGE before finalizing. The result should have visual pacing: alternate dense information with visual breathing room and avoid repeated white card grids, repeated icon grids, or repeated heading-plus-cards compositions.
- Industry-aware imagery matters. Examples: law/consulting can use professional consultation, team, office/architecture, city/location, or editorial imagery; construction can use projects/materials/sites; restaurants can use food/interiors/chefs; agencies can use portfolio/device/campaign visuals; real estate can use properties/interiors/neighborhoods; SaaS/AI can use product UI/dashboard/device/abstract product visuals.
- Do not weaken page intent just to satisfy visual variety. FAQ, pricing, legal details, process, and other information-heavy sections may remain text-led when that is the clearest treatment.
- For an ordinary 5-10 section marketing page, aim for a majority of the selected body sections to be premium_new or modern when suitable. Never force a premium Spark when its purpose or factual requirements do not fit the request.
- Include contact or CTA near the end when appropriate.
- Respect explicit page intent such as Home, About, Services, Pricing, Team, Contact, Blog, Careers, Events, or Location.
- Choose video or parallax Sparks only when the user explicitly requests that treatment.
PROMPT;

        $catalog = json_encode($plannerCatalog, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($catalog === false) {
            throw new RuntimeException('Unable to encode the Spark planner catalog.');
        }

        $user = "WEBSITE REQUEST\n\n{$prompt}\n\nAVAILABLE SPARKS\n{$catalog}";

        $response = OpenAI::chat()->create([
            'model' => config('openai.planner_model', env('OPENAI_MODEL', 'gpt-5-mini')),
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
        ]);

        $content = trim((string) ($response->choices[0]->message->content ?? ''));
        $content = preg_replace('/^```json\s*/i', '', $content) ?? $content;
        $content = preg_replace('/^```\s*/i', '', $content) ?? $content;
        $content = preg_replace('/```\s*$/i', '', $content) ?? $content;
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;

        $data = json_decode(trim($content), true);
        if (! is_array($data) || ! is_array($data['sections'] ?? null)) {
            throw new RuntimeException('Spark Planner returned an invalid response.');
        }

        $sections = [];
        foreach ($data['sections'] as $section) {
            if (! is_string($section) || ! in_array($section, $allowedSlugs, true)) {
                continue;
            }

            if (! in_array($section, $sections, true)) {
                $sections[] = $section;
            }
        }

        if ($sections === []) {
            throw new RuntimeException('Spark Planner did not select any supported Sparks.');
        }

        return $sections;
    }
}
