<?php

namespace App\AI\Prompts;

class PlannerPrompt
{
    public static function build(string $userPrompt, array $sections): array
    {
        $sectionList = "";

        foreach ($sections as $section) {

            $sectionList .= "- {$section['slug']}\n";
            $sectionList .= "  Category: {$section['category']}\n";
            $sectionList .= "  Description: {$section['description']}\n\n";

        }

        $system = <<<PROMPT
You are an expert website planner.

Your job is NOT to write content.

Your only job is to choose the best website sections.

Available Sections:

{$sectionList}

Rules:

- Return ONLY valid JSON.
- No markdown.
- No explanation.
- Maximum 8 sections.
- Choose sections in the best order.
- Do not invent new section names.
- Only use the available slugs.

Example:

[
    "hero_headline",
    "feature_image_left",
    "services_bento",
    "feature_image_right",
    "hero_centered_cta"
]
PROMPT;

        return [

            "system" => $system,

            "user" => $userPrompt

        ];
    }
}