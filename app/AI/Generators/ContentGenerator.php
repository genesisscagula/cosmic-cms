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

    private function statsModernSchema(): string
    {
        return <<<TXT

    stats_modern

    - type = stats_modern
    - theme = auto
    - eyebrow
    - heading
    - text
    - metrics (array of exactly 4 items)

    Each metric contains:

    - value
    - label
    - description

    Requirements:

    - Generate exactly 4 metrics.
    - Values must be concise, such as 15+, 250+, 98%, 24/7, or 10k+.
    - Labels must be short.
    - Descriptions must be one concise sentence.
    - Match the requested business niche.
    - Do not present regulated, medical, financial, legal, safety, or performance claims as verified facts unless the request provides them.
    - Do not include markdown.

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
    - height = screen

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

    Each feature contains:

    - text

    Requirements:

    - Generate exactly 3 plans.
    - Generate exactly 5 meaningful, niche-specific features for each plan.
    - Each feature must be an object with a text key; do not return feature strings.
    - Do not use placeholders such as "Click to add text", "Feature 1", or "Lorem ipsum".
    - featured must be a boolean, not a string.
    - button_label must be concise and button_url must be a valid URL or #.

    TXT;
    }

    private function heroEditorialOverlaySchema(): string
    {
        return <<<TXT

    hero_editorial_overlay

    - type = hero_editorial_overlay
    - theme = auto
    - category
    - tagline
    - heading
    - text
    - primary_label
    - primary_url
    - secondary_label
    - secondary_url
    - image_url = ""
    - overlayOpacity = 72
    - height = screen

    Requirements:

    - Write a concise editorial eyebrow, a confident headline, and supporting text for the business prompt.
    - Use a clear primary action and a useful secondary action.
    - Keep button labels short and actionable.
    - Do not use markdown or placeholder copy.
    - image_url must remain an empty string so the existing image-selection flow can provide the background image.

    TXT;
    }

    private function heroSplitImageSchema(): string
    {
        return <<<TXT

    hero_split_image

    - type = hero_split_image
    - theme = auto
    - tagline
    - heading
    - text
    - primary_label
    - primary_url
    - secondary_label
    - secondary_url
    - trust_line
    - image_badge
    - image_url = ""

    Requirements:

    - Write a concise eyebrow, confident heading, and helpful supporting text.
    - Use a clear primary action and a useful secondary action.
    - Keep trust_line factual and broadly applicable when exact customer proof is unavailable.
    - Keep image_badge short and location or service relevant.
    - Do not use markdown or placeholder copy.
    - image_url must remain an empty string so the existing image-selection flow can provide the image.

    TXT;
    }

    private function imageCtaBannerSchema(): string
    {
        return <<<TXT

    image_cta_banner

    - type = image_cta_banner
    - theme = auto
    - eyebrow
    - heading
    - text
    - primary_label
    - primary_url
    - secondary_label
    - secondary_url
    - image_url = ""
    - overlayOpacity = 76

    Requirements:

    - Keep the headline concise because this is a compact mid-page call to action.
    - Use a direct primary action and an optional helpful secondary action.
    - Do not use markdown or placeholder copy.
    - image_url must remain an empty string so the existing image-selection flow can provide the image.

    TXT;
    }


    private function heroFloatingCardsSchema(): string
    {
        return <<<TXT

        hero_floating_cards

        - type = hero_floating_cards
        - theme = auto
        - tagline
        - heading
        - text
        - primary_label
        - primary_url
        - secondary_label
        - secondary_url
        - image_url = ""
        - image_badge
        - card_one_value
        - card_one_label
        - card_two_title
        - card_two_text

        Requirements:

        - Write a concise uppercase-style tagline suitable for the requested business.
        - Write a confident hero heading and helpful supporting text.
        - Use a clear primary action and a useful secondary action.
        - Keep button labels concise and actionable.
        - image_badge must be a short trust, service, or location-related phrase.
        - card_one_value must be a concise value such as 15+, 250+, 98%, 24/7, or 10k+.
        - card_one_label must briefly explain the value.
        - card_two_title must be a short business benefit or trust statement.
        - card_two_text must be one concise supporting sentence.
        - Do not present medical, legal, financial, safety, customer, performance, or business statistics as verified facts unless they are supplied in the website request.
        - When no verified statistic is provided, use a broadly applicable non-regulated value or phrase.
        - Do not use markdown.
        - Do not return placeholder copy.
        - Button URLs must be valid URLs or #.
        - image_url must remain an empty string so the existing image-selection flow can assign the image.

        TXT;
    }


    private function heroVideoStyleSchema(): string
    {
        return <<<TXT

        hero_video_style

        - type = hero_video_style
        - theme = auto
        - tagline
        - heading
        - text
        - primary_label
        - primary_url
        - video_label
        - video_url
        - play_label
        - image_badge
        - image_url = ""

        Requirements:

        - Write a concise, business-relevant tagline.
        - Write a confident hero heading and helpful supporting text.
        - Use a clear primary action.
        - video_label must be a short action such as Watch our story, See how it works, or View the experience.
        - video_url must be # unless a specific public video URL is included in the website request.
        - play_label must clearly describe the video action.
        - image_badge must be a short supporting phrase related to the business, service, story, project, or experience.
        - Keep all labels concise and actionable.
        - Do not make unverified customer, medical, financial, legal, safety, or performance claims.
        - Do not use markdown.
        - Do not return placeholder copy.
        - Button URLs must be valid URLs or #.
        - image_url must remain an empty string so the existing image-selection flow can assign the image.

        TXT;
    }


    private function heroVideoBackgroundSchema(): string
    {
        return <<<TXT

        hero_video_background

        - type = hero_video_background
        - theme = auto
        - tagline
        - heading
        - text
        - primary_label
        - primary_url
        - secondary_label
        - secondary_url
        - video_url = "/storage/cms-videos/hero-placeholder.mp4"
        - poster_image_url = ""
        - video_badge
        - scroll_label

        Requirements:

        - Write a concise, cinematic tagline relevant to the requested business.
        - Write a strong hero heading suitable for display over a full-width background video.
        - Keep the heading concise enough to remain readable over moving footage.
        - Write one concise supporting paragraph.
        - Use a clear primary action and a useful secondary action.
        - Keep both button labels short and actionable.
        - video_badge must be a short supporting phrase about the business, experience, location, service, or project.
        - scroll_label must be one or two short words such as Explore, Discover, View more, or Learn more.
        - video_url must always be exactly /storage/cms-videos/hero-placeholder.mp4.
        - Do not invent an external video URL.
        - Do not make unverified customer, medical, legal, financial, safety, or performance claims.
        - Do not use markdown.
        - Do not return placeholder copy.
        - Button URLs must be valid URLs or #.
        - poster_image_url must remain an empty string so the existing image-selection flow can assign the poster image.

        TXT;
    }

}
