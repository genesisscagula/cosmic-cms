<?php

namespace App\AI\Generators;

use OpenAI\Laravel\Facades\OpenAI;

class ContentGenerator
{
    public function generate(string $prompt, array $sections): array
    {
        $system = <<<PROMPT
        You are a senior website copywriter.

        Return ONLY valid JSON.

        Format:

        {
            "image_folder":"",
            "blocks":[]
        }

        image_folder MUST be exactly one of:

        construction
        restaurant
        coffee
        bakery
        dentist
        medical
        lawyer
        fitness
        real-estate
        hotel
        travel
        technology
        education
        finance
        electrician
        plumbing
        cleaning
        landscaping
        automotive
        salon

        Use ONLY these block types:

        - hero_headline
        - feature_image_left
        - feature_image_right
        - services_bento
        - hero_centered_cta

        Rules:

        - Determine the BEST image_folder based on the website request.
        - image_folder MUST be one of the allowed folder names above.
        - theme = auto
        - image_url = ""
        - Follow the selected block types.
        - No markdown.
        - No explanations.
        - Professional marketing copy.
        - Keep paragraphs under 25 words.

        PROMPT;

        foreach ($sections as $section) {
            $system .= "\n- {$section}";
        }

        $system .= <<<SCHEMA

        Required fields for each block.

        hero_headline

        - type = hero_headline
        - theme = auto
        - subtitle
        - heading
        - text
        - btn1_label
        - btn1_url
        - btn2_label
        - btn2_url

        feature_image_left

        - type = feature_image_left
        - theme = auto
        - category
        - heading
        - text
        - button_label
        - button_url
        - image_url = ""

        feature_image_right

        - type = feature_image_right
        - theme = auto
        - category
        - heading
        - text
        - button_label
        - button_url
        - image_url = ""

        services_bento

        - type = services_bento
        - theme = auto
        - tagline
        - heading
        - description
        - services (array of exactly 3 items)

        Each service contains:
        - icon (emoji)
        - title
        - desc

        hero_centered_cta

        - type = hero_centered_cta
        - theme = auto
        - tagline
        - heading
        - subheading

        Rules:

        - Return ONLY valid JSON.
        - Follow the exact response structure requested.
        - Do NOT invent new block types.
        - Include ONLY the selected sections.
        - Every block must contain all required fields.
        - Theme must always be "auto".
        - Use concise, professional marketing copy.
        - Keep paragraphs under 35 words.
        - CTA labels should be short and action-oriented.
        - Use https://picsum.photos/900/600 for all placeholder images.

        SCHEMA;

        $user = <<<PROMPT
        Website Request

        {$prompt}

        Selected Sections

        PROMPT;

        foreach ($sections as $section) {
            $user .= "\n- {$section}";
        }

        $user .= <<<PROMPT

        Generate content for every section above.

        Return this exact structure:

        {
            "image_folder":"",
            "blocks":[]
        }

        The image_folder MUST exactly match one of the allowed folder names.

        Never return markdown.
        Never return explanations.
        Never return code fences.

        PROMPT;

        $response = OpenAI::chat()->create([

            "model" => env("OPENAI_MODEL", "gpt-5-mini"),

            "messages" => [

                [
                    "role" => "system",
                    "content" => $system
                ],

                [
                    "role" => "user",
                    "content" => $user
                ]

            ]

        ]);

        $content = trim(
            $response->choices[0]->message->content
        );

        // Remove markdown fences
        $content = preg_replace('/^```json\s*/i', '', $content);
        $content = preg_replace('/^```\s*/i', '', $content);
        $content = preg_replace('/```\s*$/i', '', $content);

        // Remove UTF-8 BOM if present
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $content = trim($content);

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {

            throw new \Exception(
                "AI returned invalid JSON:\n\n".$content
            );

        }

        if (!isset($data["blocks"])) {

            throw new \Exception(
                "AI did not return a blocks array.\n\n".$content
            );

        }

        if (!isset($data["image_folder"])) {

            throw new \Exception(
                "AI did not return an image_folder.\n\n".$content
            );

        }

        return [
            'image_folder' => $data['image_folder'],
            'blocks' => $data['blocks'],
        ];

    }
}