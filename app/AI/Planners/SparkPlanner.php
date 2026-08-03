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
- Include contact or CTA near the end when appropriate.
- Respect explicit page intent such as Home, About, Services, Pricing, Team, Contact, Blog, Careers, Events, or Location.
- Choose video or parallax Sparks only when the user explicitly requests that treatment.
PROMPT;

        $catalog = json_encode($available, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
