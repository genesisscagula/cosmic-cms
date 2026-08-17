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
        $plannerCatalog = array_map(static function (array $spark): array {
            $slug = (string) ($spark['slug'] ?? '');
            $description = (string) ($spark['description'] ?? '');
            $legacy = in_array($slug, [
                'hero_headline',
                'hero_floating_cards',
                'hero_video_background',
                'hero_video_style',
                'hero_background_image',
                'hero_slider_fade',
                'hero_parallax',
                'hero_editorial_overlay',
                'hero_split_image',
                'feature_image_left',
                'feature_image_right',
                'services_cards',
                'services_bento',
                'process_timeline',
                'testimonials_carousel',
                'hero_centered_cta',
                'image_cta_banner',
                'pricing_cards',
                'stats_modern',
                'team_modern',
                'faq_accordion',
            ], true);

            $spark['selection_tier'] = $legacy
                ? 'classic'
                : ((str_contains($slug, 'premium') || str_ends_with($slug, '_pro') || str_starts_with($description, 'Pro-only')) ? 'premium_new' : 'modern');

            return $spark;
        }, $available);

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
- Prefer Sparks marked selection_tier=premium_new when they are a strong fit for the business, page intent, and requested visual direction.
- Treat selection_tier=classic Sparks as safe fallbacks, not the default creative choice. Do not repeatedly fall back to the same classic hero or classic body sections when suitable premium_new or modern options exist.
- The preference for newer Sparks applies to the entire page, not only the hero. After the hero, deliberately use newer/specialized services, about, portfolio, stats, testimonials, team, pricing, FAQ, contact, sales, agency, AI, lead-generation, blog, and CTA Sparks when relevant.
- Build visual variety across the page: avoid choosing a sequence dominated by generic legacy cards/image/text sections when richer compatible Sparks exist.
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
