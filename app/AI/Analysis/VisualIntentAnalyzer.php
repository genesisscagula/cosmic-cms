<?php

namespace App\AI\Analysis;

use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;

class VisualIntentAnalyzer
{
    private const FALLBACK_INDUSTRIES = [
        'default', 'construction', 'restaurant', 'coffee', 'bakery', 'dentist',
        'medical', 'lawyer', 'fitness', 'real-estate', 'hotel', 'travel',
        'technology', 'education', 'finance', 'electrician', 'plumbing',
        'cleaning', 'landscaping', 'automotive', 'salon',
    ];

    public function analyze(string $prompt): array
    {
        $system = <<<'PROMPT'
You are Cosmic's fast visual-intent analyzer.

Your only job is to understand the business request well enough to start image retrieval immediately.
Do not choose website sections. Do not write website copy.

Return only JSON with exactly this shape:
{
  "business_type":"short specific business category",
  "fallback_industry":"one supported fallback folder",
  "image_keywords":["keyword phrase"],
  "visual_style":"short visual direction",
  "overlay_header_on_banner":true
}

Rules:
- image_keywords must contain 3 to 6 concise stock-photo search phrases.
- Make the phrases specific to the user's business, services, subject matter, and likely website visuals.
- Avoid generic phrases such as "professional business" unless no better description exists.
- fallback_industry must be one of: default, construction, restaurant, coffee, bakery, dentist, medical, lawyer, fitness, real-estate, hotel, travel, technology, education, finance, electrician, plumbing, cleaning, landscaping, automotive, salon.
- visual_style should be short, for example "technical editorial", "luxury cinematic", or "warm artisanal".
- overlay_header_on_banner must be a JSON boolean. Set it true when a transparent navigation layered over the first hero/banner would materially improve the requested design, especially cinematic, image-led, video, luxury, travel, hospitality, real-estate, event, or immersive visual directions.
- Set overlay_header_on_banner false for minimal, light, editorial, documentation, dashboard-like, dense utility, or whitespace-led designs where the header should remain visually separate from the first section.
- Do not force overlay simply because a hero exists; choose it as a deliberate visual-design decision.
PROMPT;

        $response = OpenAI::chat()->create([
            'model' => config('openai.visual_model', config('openai.planner_model', env('OPENAI_MODEL', 'gpt-5-mini'))),
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => "WEBSITE REQUEST\n\n{$prompt}"],
            ],
        ]);

        $content = trim((string) ($response->choices[0]->message->content ?? ''));
        if ($content === '') {
            throw new RuntimeException('Visual intent analyzer returned an empty response.');
        }

        $data = json_decode($content, true);
        if (! is_array($data)) {
            throw new RuntimeException('Visual intent analyzer returned invalid JSON.');
        }

        $businessType = trim((string) ($data['business_type'] ?? ''));
        $visualStyle = trim((string) ($data['visual_style'] ?? ''));
        $fallbackIndustry = trim((string) ($data['fallback_industry'] ?? 'default'));
        $keywords = collect($data['image_keywords'] ?? [])
            ->filter(fn ($keyword) => is_string($keyword) && trim($keyword) !== '')
            ->map(fn ($keyword) => trim($keyword))
            ->unique()
            ->take(6)
            ->values()
            ->all();

        if ($businessType === '' || count($keywords) < 1) {
            throw new RuntimeException('Visual intent analyzer response is incomplete.');
        }

        if (! in_array($fallbackIndustry, self::FALLBACK_INDUSTRIES, true)) {
            $fallbackIndustry = 'default';
        }

        return [
            'business_type' => $businessType,
            'fallback_industry' => $fallbackIndustry,
            'image_keywords' => $keywords,
            'visual_style' => $visualStyle !== '' ? $visualStyle : 'professional editorial',
            'overlay_header_on_banner' => filter_var(
                $data['overlay_header_on_banner'] ?? false,
                FILTER_VALIDATE_BOOL
            ),
        ];
    }
}
