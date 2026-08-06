<?php

namespace App\AI\Generators;
use App\AI\Schemas\SelectedSchemaLoader;
use App\AI\Validation\GeneratedContentValidator;

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
        $schemaSelection = (new SelectedSchemaLoader())->resolve($sections);
        $selectedSections = $schemaSelection['selected'];

        if ($selectedSections === []) {
            throw new \InvalidArgumentException('No supported Spark schemas were selected.');
        }

        $system = $this->buildSystemPrompt($schemaSelection);
        $user = $this->buildUserPrompt($prompt, $selectedSections);
        $attemptLimit = max(1, (int) config('openai.content_json_attempts', 2));
        $lastContent = '';
        $lastJsonError = null;

        for ($attempt = 1; $attempt <= $attemptLimit; $attempt++) {
            $messages = [
                [
                    'role' => 'system',
                    'content' => $system,
                ],
                [
                    'role' => 'user',
                    'content' => $attempt === 1
                        ? $user
                        : $this->buildRepairPrompt($user, $lastContent, $lastJsonError),
                ],
            ];

            $response = OpenAI::chat()->create([
                'model' => config('openai.content_model', env('OPENAI_MODEL', 'gpt-5-mini')),
                'response_format' => ['type' => 'json_object'],
                'messages' => $messages,
            ]);

            $lastContent = trim((string) ($response->choices[0]->message->content ?? ''));

            try {
                $data = $this->decodeEnvelope($lastContent);
                $validation = (new GeneratedContentValidator())->validate(
                    $data['blocks'],
                    $selectedSections
                );

                return [
                    'image_folder' => $data['image_folder'],
                    'blocks' => $validation['blocks'],
                    'schema_diagnostics' => [
                        'selected' => $selectedSections,
                        'loaded_count' => count($selectedSections),
                        'skipped' => $schemaSelection['skipped'],
                        'duplicates_removed' => $schemaSelection['duplicate_count'],
                        'content_model' => config('openai.content_model'),
                        'json_attempts_used' => $attempt,
                        'json_validation' => $validation['diagnostics'],
                    ],
                ];
            } catch (\UnexpectedValueException $exception) {
                $lastJsonError = $exception->getMessage();
            }
        }

        throw new \UnexpectedValueException(
            "AI content generator failed to return the required JSON envelope after {$attemptLimit} attempt(s). " .
            ($lastJsonError ?: 'Unknown JSON response error.')
        );
    }

    /**
     * Build the complete schema boundary for the selected Sparks only.
     */
    private function buildSystemPrompt(array $schemaSelection): string
    {
        $system = <<<PROMPT
You are Cosmic's senior website content generator.

Return one JSON object only, with this exact top-level envelope:

{
  "image_folder": "",
  "blocks": []
}

The image_folder value MUST be exactly one of:
PROMPT;

        $system .= "\n- " . implode("\n- ", self::IMAGE_FOLDERS) . "\n";
        $system .= "\nSELECTED SPARK SCHEMAS\n";

        foreach ($schemaSelection['methods'] as $section => $method) {
            if (! method_exists($this, $method)) {
                throw new \LogicException("Schema method [{$method}] for Spark [{$section}] does not exist.");
            }

            $system .= $this->{$method}();
        }

        $system .= <<<'RULES'

GLOBAL CONTENT CONTRACT

- Output valid JSON only. Never wrap the response in markdown fences.
- Generate exactly one block for every selected Spark.
- Keep blocks in the exact same order as the selected Spark list.
- Every block.type must exactly match its selected Spark ID.
- Do not add, replace, rename, or omit a selected Spark.
- Every block must contain every field required by its selected schema.
- Every block theme must be "auto".
- Use the supplied business and page context consistently across all blocks.
- Write production-ready content, not notes, explanations, TODOs, or placeholders.
- Do not invent awards, certifications, client names, dates, prices, statistics, addresses, phone numbers, or performance claims not supplied by the user.
- Keep URLs editable and safe. Use "#" when no real destination was supplied.
- Image URL fields defined by a schema must remain empty strings; the application assigns images after generation.
RULES;

        return $system;
    }

    private function buildUserPrompt(string $prompt, array $selectedSections): string
    {
        $ordered = [];
        foreach ($selectedSections as $index => $section) {
            $ordered[] = ($index + 1) . '. ' . $section;
        }

        $orderedList = implode("\n", $ordered);
        $blockCount = count($selectedSections);

        return <<<PROMPT
WEBSITE REQUEST

{$prompt}

ORDERED SELECTED SPARKS

{$orderedList}

Generate the complete content payload now.
The blocks array must contain exactly {$blockCount} blocks in the order shown above.
PROMPT;
    }

    private function buildRepairPrompt(string $originalPrompt, string $invalidContent, ?string $error): string
    {
        $invalidContent = function_exists('mb_substr')
            ? mb_substr($invalidContent, 0, 12000)
            : substr($invalidContent, 0, 12000);

        return <<<PROMPT
{$originalPrompt}

Your previous response did not satisfy the JSON envelope.
Error: {$error}

Previous response:
{$invalidContent}

Return a corrected JSON object only. Preserve the exact selected Spark order and include one block per selected Spark.
PROMPT;
    }

    /**
     * @return array{image_folder:string, blocks:array<int, array<string, mixed>>}
     */
    private function decodeEnvelope(string $content): array
    {
        $content = preg_replace('/^```json\s*/i', '', $content) ?? $content;
        $content = preg_replace('/^```\s*/i', '', $content) ?? $content;
        $content = preg_replace('/```\s*$/i', '', $content) ?? $content;
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $content = trim($content);

        if ($content === '') {
            throw new \UnexpectedValueException('The AI returned an empty response.');
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \UnexpectedValueException('Invalid JSON: ' . $exception->getMessage(), 0, $exception);
        }

        if (! is_array($data)) {
            throw new \UnexpectedValueException('The AI response must be a JSON object.');
        }

        if (! isset($data['blocks']) || ! is_array($data['blocks'])) {
            throw new \UnexpectedValueException('The AI response is missing a blocks array.');
        }

        if (! isset($data['image_folder']) || ! is_string($data['image_folder'])) {
            throw new \UnexpectedValueException('The AI response is missing a string image_folder.');
        }

        if (! in_array($data['image_folder'], self::IMAGE_FOLDERS, true)) {
            throw new \UnexpectedValueException('The AI returned an unsupported image_folder.');
        }

        return [
            'image_folder' => $data['image_folder'],
            'blocks' => array_values($data['blocks']),
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

    private function teamModernSchema(): string
    {
        return <<<TXT

    team_modern

    - type = team_modern
    - theme = auto
    - eyebrow
    - heading
    - text
    - members (array of exactly 4 items)

    Each member contains:

    - name
    - role
    - bio
    - image_url

    Requirements:

    - Generate exactly 4 team members.
    - Use realistic placeholder names and role titles appropriate to the requested business.
    - Bios must be concise, professional, and avoid unverifiable personal history or credentials.
    - Set image_url to these existing avatar paths in order:
      /storage/cms-images/avatars/avatar-1.jpg
      /storage/cms-images/avatars/avatar-2.jpg
      /storage/cms-images/avatars/avatar-3.jpg
      /storage/cms-images/avatars/avatar-4.jpg
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


    private function heroParallaxSchema(): string
    {
        return <<<TXT

    hero_parallax

    - type = hero_parallax
    - theme = auto
    - category
    - eyebrow
    - heading
    - text
    - primary_label
    - primary_url
    - secondary_label
    - secondary_url
    - image_url = ""
    - overlayOpacity = 64
    - parallaxSpeed = 24
    - contentAlign = left
    - height = screen
    - scroll_label = Scroll to explore

    Requirements:
    - Keep the heading short and cinematic.
    - Do not invent awards, certifications, or unverifiable claims.
    - Use neutral image search intent without brand names or logos.

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

    private function heroSliderFadeSchema(): string
    {
        return <<<TXT

    hero_slider_fade

    - type = hero_slider_fade
    - theme = auto
    - category
    - autoplay = true
    - interval = 5000
    - pause_on_hover = true
    - show_dots = true
    - show_arrows = true
    - slides = array of exactly 3 items

    Each slide must contain:
    - image_url = ""
    - eyebrow
    - heading
    - description
    - button_1_text
    - button_1_url
    - button_2_text
    - button_2_url
    - button_3_text
    - button_3_url
    - button_4_text
    - button_4_url

    Generate exactly 3 distinct slides in the submitted business and page context.
    Keep eyebrow text concise, headings clear, descriptions brief, and all four calls to action specific.
    Use an empty image_url so the existing image pipeline can supply an appropriate image.
    Do not omit, rename, duplicate, or add slide keys. Do not include markdown.

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

    private function heroSplitEditorialSchema(): string
    {
        return <<<TXT

    hero_split_editorial

    - type = hero_split_editorial
    - theme = auto
    - eyebrow
    - editorial_index = "01"
    - heading
    - text
    - primary_label
    - primary_url
    - secondary_label
    - secondary_url
    - proof_value
    - proof_label
    - image_caption
    - image_url = ""

    Requirements:

    - Write refined, concise editorial copy suitable for a premium agency-designed homepage.
    - Heading should feel confident and sophisticated, not promotional or generic.
    - proof_value and proof_label must be broadly truthful; avoid invented awards or precise claims when unavailable.
    - Keep both button labels short and actionable.
    - Keep image_caption to one short sentence.
    - Do not use markdown or placeholder copy.
    - image_url must remain an empty string so the existing image-selection flow can provide the image.

    TXT;
    }

    private function heroFloatingGlassSchema(): string
    {
        return <<<TXT

    hero_floating_glass

    - type = hero_floating_glass
    - theme = auto
    - eyebrow
    - heading
    - text
    - primary_label
    - primary_url
    - secondary_label
    - secondary_url
    - glass_title
    - glass_text
    - metric_value
    - metric_label
    - badge_one
    - badge_two
    - image_url = ""

    Requirements:

    - Write polished, concise copy for a premium glassmorphism hero.
    - Use a confident heading and a clear primary action.
    - glass_title and glass_text should summarize a useful business benefit.
    - metric_value and metric_label must be broadly truthful; never invent precise performance claims when unavailable.
    - Keep badges to two or three words each.
    - Do not use markdown or placeholder copy.
    - image_url must remain an empty string so the existing image-selection flow can provide the image.

    TXT;
    }

    private function heroVideoPremiumSchema(): string
    {
        return <<<TXT

    hero_video_premium

    - type = hero_video_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - primary_label
    - primary_url
    - secondary_label
    - secondary_url
    - media_badge
    - scroll_label
    - video_url = "/storage/cms-videos/hero-placeholder.mp4"
    - poster_image_url = ""

    Requirements:

    - Write cinematic but concise copy for a premium motion-led homepage hero.
    - Heading should be memorable and confident without unsupported superlatives.
    - Keep media_badge and scroll_label short.
    - Keep both calls to action clear and useful.
    - Do not use markdown or placeholder copy.
    - Keep video_url on the provided placeholder unless an existing approved asset is supplied.
    - poster_image_url must remain empty so the existing image-selection flow can provide the poster image.

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

    private function contactFormModernSchema(): string
    {
        return <<<TXT

    contact_form_modern

    - type = contact_form_modern
    - theme = auto
    - eyebrow
    - heading
    - text
    - email
    - phone
    - address
    - submit_label
    - fields (array of 3 to 7 items)

    Each field contains:

    - id
    - name
    - type
    - label
    - placeholder
    - required (boolean)
    - options (array; only for select and radio fields)

    Requirements:

    - Write inviting, concise contact-section copy that matches the requested business.
    - Use hello@example.com when the request does not provide a real email address.
    - Use a generic, safe phone number when the request does not provide one.
    - Do not invent an exact street address; use a general appointment or service-area phrase instead.
    - submit_label must be a short action such as Send inquiry, Request a quote, or Get in touch.
    - Always include these usable inquiry fields: name (text), email (email), and message (textarea).
    - You may add one to four useful business-specific fields such as phone, preferred_service, budget_range, appointment_date, or consent.
    - type must be exactly one of: text, email, tel, textarea, select, radio, checkbox, date.
    - name must be a unique lowercase snake_case key using only letters, numbers, and underscores.
    - checkbox can be a single acknowledgement/consent or a short multi-choice list.
    - select, radio, and multi-choice checkbox fields must contain 2 to 6 short, useful options.
    - Keep labels and placeholders concise and customer-friendly.
    - Do not request passwords, payment data, government IDs, medical history, or other sensitive personal information.
    - Do not use markdown or placeholder copy.

    TXT;
    }

    private function faqAccordionSchema(): string
    {
        return <<<TXT

    faq_accordion

    - type = faq_accordion
    - theme = auto
    - eyebrow
    - heading
    - text
    - faqs (array of exactly 4 items)

    Each faq contains:

    - question
    - answer

    Requirements:

    - Write four concise, useful questions a prospective customer would genuinely ask.
    - Answers must be one or two clear sentences, without markdown.
    - Keep the questions relevant to the requested business, page, and service.
    - Do not invent awards, certifications, guarantees, regulated claims, or exact business facts that were not supplied.
    - Do not use placeholders such as FAQ 1, Click to add text, or Lorem ipsum.

    TXT;
    }

    private function contactDetailsSchema(): string
    {
        return <<<TXT

    contact_details

    - type = contact_details
    - theme = auto
    - eyebrow
    - heading
    - text
    - email
    - phone
    - address
    - hours

    Requirements:

    - Write concise, trustworthy contact copy that matches the requested business.
    - Use hello@example.com if no email address is supplied.
    - Use a generic safe phone number if no real phone number is supplied.
    - Do not invent a precise street address; use a service-area or appointment phrase when unavailable.
    - Hours must be a simple availability range, not a verified claim.
    - Do not use markdown or placeholder copy.

    TXT;
    }

    private function locationMapSchema(): string
    {
        return <<<TXT

    location_map

    - type = location_map
    - theme = auto
    - eyebrow
    - heading
    - text
    - location_name
    - address
    - service_area
    - directions_label

    Requirements:

    - Write concise location and visit guidance matching the requested business.
    - Do not invent a precise street address, map URL, landmark, or travel time when none was supplied.
    - Use a general service-area or appointment phrase when exact location details are unavailable.
    - directions_label must be a short action such as Get directions or Plan your visit.
    - Do not use markdown or placeholder copy.

    TXT;
    }

    private function caseStudiesGridSchema(): string
    {
        return <<<TXT

    case_studies_grid

    - type = case_studies_grid
    - theme = auto
    - eyebrow
    - heading
    - text
    - studies (array of exactly 3 items)

    Each study contains:

    - category
    - title
    - summary
    - result
    - image_url
    - link_label

    Requirements:

    - Write three credible examples of work relevant to the requested business and page.
    - Keep titles, summaries, and outcomes concise and useful.
    - Do not invent client names, revenue, rankings, exact performance results, awards, or other unverifiable claims.
    - result should describe a practical outcome without presenting an unverified metric as fact.
    - image_url must be an empty string so the existing image-selection flow can assign an image.
    - link_label must be a short action such as View case study or Read the story.
    - Do not use markdown or placeholder copy.

    TXT;
    }

    private function jobsListSchema(): string
    {
        return <<<TXT

    jobs_list

    - type = jobs_list
    - theme = auto
    - eyebrow
    - heading
    - text
    - jobs (array of exactly 4 items)

    Each job contains:

    - title
    - type
    - location
    - description
    - button_label

    Requirements:

    - Write four realistic, concise role summaries appropriate to the requested business.
    - Do not imply that roles are currently open unless the owner confirms it; use broadly editable starter roles.
    - type and location must remain concise and should use flexible wording such as Full-time, Hybrid, Remote, or By arrangement when details are unavailable.
    - button_label must be a short action such as View role or Learn more.
    - Do not use markdown or placeholder copy.

    TXT;
    }

    private function heroSaasDashboardSchema(): string
    {
        return <<<TXT

    hero_saas_dashboard

    - type = hero_saas_dashboard
    - theme = auto
    - eyebrow
    - heading
    - text
    - primary_label
    - primary_url
    - secondary_label
    - secondary_url
    - dashboard_title
    - dashboard_subtitle
    - metric_one_value
    - metric_one_label
    - metric_two_value
    - metric_two_label
    - metric_three_value
    - metric_three_label
    - chart_label
    - logo_one
    - logo_two
    - logo_three
    - logo_four

    Requirements:

    - Write concise product-led copy for a premium SaaS, AI, fintech, productivity, or technology homepage.
    - Make the heading outcome-focused and credible, not hype-heavy.
    - Dashboard labels should describe useful product activity without inventing customer data.
    - Metrics must be broadly editable starter values; avoid claims presented as verified facts.
    - Logo names must be fictional neutral placeholders, never real customer claims.
    - Keep CTA labels short and product-oriented.
    - Do not use markdown or placeholder copy.

    TXT;
    }

    private function eventsGridSchema(): string
    {
        return <<<TXT

    events_grid

    - type = events_grid
    - theme = auto
    - eyebrow
    - heading
    - text
    - events (array of exactly 3 items)

    Each event contains:

    - month
    - day
    - title
    - date
    - location
    - description
    - button_label

    Requirements:

    - Write three concise, editable event ideas that fit the requested business.
    - month must be a three-letter uppercase month abbreviation and day must be a one- or two-digit day.
    - Do not invent confirmed event dates, venues, speakers, or attendance claims. Use clearly editable, general event details when no specifics are provided.
    - button_label must be a short action such as Reserve a place, Save your seat, or Learn more.
    - Do not use markdown or placeholder copy.

    TXT;
    }

}
