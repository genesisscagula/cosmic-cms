<?php

namespace App\AI\Generators;
use App\AI\Schemas\SchemaManager;

use OpenAI\Laravel\Facades\OpenAI;

class ContentGenerator
{

    private const IMAGE_FOLDERS = [
        'construction',
        'restaurant',
        'coffee',
        'bakery',
        'dentist',
        'medical',
        'lawyer',
        'fitness',
        'real-estate',
        'hotel',
        'travel',
        'technology',
        'education',
        'finance',
        'electrician',
        'plumbing',
        'cleaning',
        'landscaping',
        'automotive',
        'salon',
    ];


    public function generate(string $prompt, array $sections): array
    {


        $system = <<<PROMPT
        You are a senior website copywriter.

        Generate professional marketing copy.

        Format:

        {
            "image_folder":"",
            "blocks":[]
        }

        image_folder MUST be exactly one of:

        PROMPT;

        $system .= implode("\n", self::IMAGE_FOLDERS);


        $schemaMap = SchemaManager::map();

        foreach ($sections as $section) {

            if (!isset($schemaMap[$section])) {
                continue;
            }

            $method = $schemaMap[$section];

            $system .= $this->$method();

        }

        $system .= <<<RULES

        - Return ONLY valid JSON.
        - Theme must always be "auto".
        - Every block must contain all required fields.

        RULES;


        $user = <<<PROMPT
        Website Request

        {$prompt}

        Selected Sections

        PROMPT;

        foreach ($sections as $section) {
            $user .= "\n- {$section}";
        }


        $user .= <<<PROMPT

        Generate professional content for each selected section.

        Return ONLY this JSON structure:

        {
            "image_folder":"",
            "blocks":[]
        }

        One block per selected section.

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

    private function heroHeadlineSchema(): string
    {
        return <<<TXT

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

    TXT;
    }

    private function featureImageLeftSchema(): string
    {
        return <<<TXT

    feature_image_left

    - type = feature_image_left
    - theme = auto
    - category
    - heading
    - text
    - button_label
    - button_url
    - image_url = ""

    TXT;
    }

    private function featureImageRightSchema(): string
    {
        return <<<TXT

    feature_image_right

    - type = feature_image_right
    - theme = auto
    - category
    - heading
    - text
    - button_label
    - button_url
    - image_url = ""

    TXT;
    }

    private function servicesBentoSchema(): string
    {
        return <<<TXT

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

    TXT;
    }

    private function servicesCardsSchema(): string
    {
        return <<<TXT

    services_cards

    - type = services_cards
    - theme = auto
    - tagline
    - heading
    - description
    - cards (array of exactly 3 items)

    Each card contains:

    - icon (emoji)
    - title
    - desc

    Requirements:

    - Generate exactly 3 cards.
    - Choose an emoji that best represents the service.
    - Do not repeat the same emoji.

    TXT;
    }

    private function heroCtaSchema(): string
    {
        return <<<TXT

    hero_centered_cta

    - type = hero_centered_cta
    - theme = auto
    - tagline
    - heading
    - subheading

    TXT;
    }

    private function processTimelineSchema(): string
    {
        return <<<TXT

    process_timeline

    - type = process_timeline
    - theme = auto
    - category
    - heading
    - text
    - steps (array of exactly 4 items)

    Each step contains:

    - number
    - title
    - text

    TXT;
    }

    private function testimonialsSchema(): string
    {
        return <<<TXT

    testimonials_carousel

    - type = testimonials_carousel
    - theme = auto
    - tagline
    - heading
    - text
    - testimonials (array of exactly 3 items)

    Each testimonial contains:

    - avatar
    - name
    - company
    - quote
    - rating

    Requirements:

    - Generate exactly 3 testimonials.
    - Rating must always be 5.
    - Avatar must use:
      /storage/cms-images/avatars/avatar-1.jpg
      /storage/cms-images/avatars/avatar-2.jpg
      /storage/cms-images/avatars/avatar-3.jpg
    - Quotes should be realistic and industry-specific.
    - Company should match the business niche.
    - Name should be a realistic full name.

    TXT;
    }


    private function heroBackgroundImageSchema(): string
    {
        return <<<TXT

    hero_background_image

    - type = hero_background_image
    - theme = auto
    - category
    - tagline
    - heading
    - text
    - button_label
    - button_url
    - image_url = ""
    - overlayOpacity = 50
    - textAlign = center
    - height = xl

    TXT;
    }

    private function pricingCardsSchema(): string
    {
        return <<<TXT

    pricing_cards

    - type = pricing_cards
    - theme = auto
    - category
    - tagline
    - heading
    - text
    - plans (array of exactly 3 items)

    Each plan contains:

    - badge
    - featured
    - title
    - price
    - period
    - description
    - button_label
    - button_url
    - features (array of exactly 5 items)

    TXT;
    }

}