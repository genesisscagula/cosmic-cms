<?php

namespace App\AI\Generators;
use App\AI\Schemas\SelectedSchemaLoader;
use App\AI\Validation\GeneratedContentValidator;
use App\Services\LunaPageCompositionService;
use App\Services\LunaContentIntelligenceService;
use App\Services\LunaResponsiveIntelligenceService;

use OpenAI\Laravel\Facades\OpenAI;

class ContentGenerator
{
    public function __construct(
        private readonly LunaPageCompositionService $composition,
        private readonly LunaContentIntelligenceService $contentIntelligence,
        private readonly LunaResponsiveIntelligenceService $responsive
    ) {}


    private const IMAGE_FOLDERS = [
        'construction',
        'deep-sea-exploration',
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

        $composition=$this->composition->context($prompt);
        $system = $this->buildSystemPrompt($schemaSelection,$prompt,$composition);
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

                $contentAudit=$this->contentIntelligence->audit($validation['blocks']);

                // Generic-copy findings are diagnostic rather than schema failures:
                // schema correctness remains the hard boundary, while the prompt contract
                // steers generation away from filler and unsupported facts.
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
                        'content_intelligence' => $contentAudit,
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
    private function buildSystemPrompt(array $schemaSelection,string $prompt,array $composition): string
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

            $system .= $this->schemaForSpark($section, $method);
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

        $system .= "

".$this->contentIntelligence->contract($prompt,$composition);
        $system .= "

".$this->responsive->plannerDirective(array_values($schemaSelection['selected']??[]));
        $system .= <<<'CONTENTRULES'

CONTENT QUALITY RULES
- Prefer concrete, industry-relevant language over generic marketing filler.
- Preserve every factual detail supplied by the user. Do not silently alter business names, locations, contact details, services, prices, credentials, or other supplied facts.
- If a fact was not supplied, write around the unknown rather than inventing it.
- Never create fake testimonial quotes or attribute praise to fictional customers.
- Never turn editable proof placeholders into factual claims. If a Spark schema requires proof/stat fields but the user supplied no verified metric, use neutral non-quantified starter wording that does not assert an achievement.
- Vary headings and supporting copy across the page; do not repeat the same promise in multiple sections.
- Match copy density to the field: short labels stay short, card copy stays scannable, and hero copy must not become a paragraph wall.
- CTA labels should describe a plausible next action for the page intent. Use "#" for destinations not supplied by the user.
- Do not use generic filler phrases listed in the CONTENT INTELLIGENCE CONTRACT.
- Write text lengths that remain usable when desktop layouts collapse on tablet/mobile.
- Do not create excessively long button labels, badges, card titles, stat labels, or navigation-like labels that are likely to overflow.
- For comparison/grid/card content, keep sibling item copy reasonably balanced so responsive stacking does not produce extreme height mismatch.
- For media-led Sparks, keep copy concise enough that text does not fight the media area at tablet/mobile widths.
CONTENTRULES;

        return $system;
    }

    /**
     * Render a schema for the exact selected Spark.
     *
     * Some premium Sparks intentionally share a schema method. The method may
     * describe a base Spark type, so normalize the declared type to the
     * selected Spark ID before it reaches Luna. This prevents valid generated
     * content from being discarded solely because a shared contract declared
     * its base type.
     */
    private function schemaForSpark(string $spark, string $method): string
    {
        $schema = (string) $this->{$method}();

        if ($schema === '') {
            throw new \LogicException("Schema method [{$method}] for Spark [{$spark}] returned an empty contract.");
        }

        $schema = preg_replace(
            '/(^|\n)(\s*-\s*type\s*=\s*)[a-zA-Z0-9_-]+/m',
            '$1$2' . $spark,
            $schema,
            1
        ) ?? $schema;

        $schema = preg_replace(
            '/(^|\n)(\s*type\s*=\s*)[a-zA-Z0-9_-]+/m',
            '$1$2' . $spark,
            $schema,
            1
        ) ?? $schema;

        return $schema;
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

    Icon requirements:
    - Use a recognizable, strongly colored emoji/icon that remains visible on light, surface, primary, and dark Spark backgrounds.
    - Avoid predominantly white, near-white, pale-gray, transparent-looking, or low-contrast icons.

    TXT;
    }

    private function servicesBentoPremiumSchema(): string
    {
        return <<<TXT

    services_bento_premium

    - type = services_bento_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - primary_label
    - primary_url
    - featured_number
    - featured_title
    - featured_text
    - featured_meta
    - service_two_number
    - service_two_title
    - service_two_text
    - service_three_number
    - service_three_title
    - service_three_text
    - service_four_number
    - service_four_title
    - service_four_text
    - service_five_number
    - service_five_title
    - service_five_text
    - service_six_number
    - service_six_title
    - service_six_text
    - service_seven_number
    - service_seven_title
    - service_seven_text
    - service_count
    - proof_value
    - proof_label

    Requirements:

    - Write seven distinct but complementary service options so the section can expand later. The application controls how many are visible with service_count; fresh builds default to 5.
    - Make the featured service the most strategic or commercially important offer.
    - Keep service numbers short and sequential from 01 through 07. service_count must be an integer from 1 to 7.
    - Keep featured_meta as three short capability labels separated by middle dots.
    - Treat proof_value and proof_label as editable starter content, not verified claims.
    - Do not invent awards, clients, guarantees, or performance results.
    - Do not use markdown or placeholder copy.

    TXT;
    }


    private function servicesEditorialPremiumSchema(): string
    {
        return <<<TXT

    services_editorial_premium

    - type = services_editorial_premium
    - theme = auto
    - eyebrow, heading, text
    - primary_label, primary_url
    - image_url = ""
    - featured_image_url = ""
    - service_two_image_url = ""
    - service_three_image_url = ""
    - service_four_image_url = ""
    - service_five_image_url = ""
    - service_six_image_url = ""
    - service_seven_image_url = ""
        - featured_number, featured_title, featured_text, featured_meta
    - service_two_number, service_two_title, service_two_text
    - service_three_number, service_three_title, service_three_text
    - service_four_number, service_four_title, service_four_text
    - service_five_number, service_five_title, service_five_text
    - service_six_number, service_six_title, service_six_text
    - service_seven_number, service_seven_title, service_seven_text
    - service_count
    - proof_value, proof_label

    Requirements:
    - Write seven distinct, relevant services so the section may expand later.
    - service_count must be 1 through 7 and fresh builds should normally use 5.
    - Keep numbers sequential 01 through 07.
    - Make the featured service the strongest strategic/commercial offer.
    - image_url must be empty; the application resolves imagery.
    - Do not invent awards, client names, guarantees, metrics, or unverifiable claims.
    - Preserve industry context and write concise, specific service copy.

    TXT;
    }

    private function servicesShowcasePremiumSchema(): string
    {
        return <<<TXT

    services_showcase_premium

    - type = services_showcase_premium
    - theme = auto
    - eyebrow, heading, text
    - primary_label, primary_url
    - image_url = ""
    - featured_image_url = ""
    - service_two_image_url = ""
    - service_three_image_url = ""
    - service_four_image_url = ""
    - service_five_image_url = ""
    - service_six_image_url = ""
    - service_seven_image_url = ""
        - featured_number, featured_title, featured_text, featured_meta
    - service_two_number, service_two_title, service_two_text
    - service_three_number, service_three_title, service_three_text
    - service_four_number, service_four_title, service_four_text
    - service_five_number, service_five_title, service_five_text
    - service_six_number, service_six_title, service_six_text
    - service_seven_number, service_seven_title, service_seven_text
    - service_count
    - proof_value, proof_label

    Requirements:
    - Write seven distinct, relevant services so the section may expand later.
    - service_count must be 1 through 7 and fresh builds should normally use 5.
    - Keep numbers sequential 01 through 07.
    - Make the featured service the strongest strategic/commercial offer.
    - image_url must be empty; the application resolves imagery.
    - Do not invent awards, client names, guarantees, metrics, or unverifiable claims.
    - Preserve industry context and write concise, specific service copy.

    TXT;
    }

    private function servicesMinimalLuxurySchema(): string
    {
        return <<<TXT

    services_minimal_luxury

    - type = services_minimal_luxury
    - theme = auto
    - eyebrow, heading, text
    - primary_label, primary_url
    - image_url = ""
    - featured_image_url = ""
    - service_two_image_url = ""
    - service_three_image_url = ""
    - service_four_image_url = ""
    - service_five_image_url = ""
    - service_six_image_url = ""
    - service_seven_image_url = ""
        - featured_number, featured_title, featured_text, featured_meta
    - service_two_number, service_two_title, service_two_text
    - service_three_number, service_three_title, service_three_text
    - service_four_number, service_four_title, service_four_text
    - service_five_number, service_five_title, service_five_text
    - service_six_number, service_six_title, service_six_text
    - service_seven_number, service_seven_title, service_seven_text
    - service_count
    - proof_value, proof_label

    Requirements:
    - Write seven distinct, relevant services so the section may expand later.
    - service_count must be 1 through 7 and fresh builds should normally use 5.
    - Keep numbers sequential 01 through 07.
    - Make the featured service the strongest strategic/commercial offer.
    - image_url must be empty; the application resolves imagery.
    - Do not invent awards, client names, guarantees, metrics, or unverifiable claims.
    - Preserve industry context and write concise, specific service copy.

    TXT;
    }

    private function servicesContrastPremiumSchema(): string
    {
        return <<<TXT

    services_contrast_premium

    - type = services_contrast_premium
    - theme = auto
    - eyebrow, heading, text
    - primary_label, primary_url
    - image_url = ""
    - featured_image_url = ""
    - service_two_image_url = ""
    - service_three_image_url = ""
    - service_four_image_url = ""
    - service_five_image_url = ""
    - service_six_image_url = ""
    - service_seven_image_url = ""
        - featured_number, featured_title, featured_text, featured_meta
    - service_two_number, service_two_title, service_two_text
    - service_three_number, service_three_title, service_three_text
    - service_four_number, service_four_title, service_four_text
    - service_five_number, service_five_title, service_five_text
    - service_six_number, service_six_title, service_six_text
    - service_seven_number, service_seven_title, service_seven_text
    - service_count
    - proof_value, proof_label

    Requirements:
    - Write seven distinct, relevant services so the section may expand later.
    - service_count must be 1 through 7 and fresh builds should normally use 5.
    - Keep numbers sequential 01 through 07.
    - Make the featured service the strongest strategic/commercial offer.
    - image_url must be empty; the application resolves imagery.
    - Do not invent awards, client names, guarantees, metrics, or unverifiable claims.
    - Preserve industry context and write concise, specific service copy.

    TXT;
    }

    private function servicesSplitPremiumSchema(): string
    {
        return <<<TXT

    services_split_premium

    - type = services_split_premium
    - theme = auto
    - eyebrow, heading, text
    - primary_label, primary_url
    - image_url = ""
    - featured_image_url = ""
    - service_two_image_url = ""
    - service_three_image_url = ""
    - service_four_image_url = ""
    - service_five_image_url = ""
    - service_six_image_url = ""
    - service_seven_image_url = ""
        - featured_number, featured_title, featured_text, featured_meta
    - service_two_number, service_two_title, service_two_text
    - service_three_number, service_three_title, service_three_text
    - service_four_number, service_four_title, service_four_text
    - service_five_number, service_five_title, service_five_text
    - service_six_number, service_six_title, service_six_text
    - service_seven_number, service_seven_title, service_seven_text
    - service_count
    - proof_value, proof_label

    Requirements:
    - Write seven distinct, relevant services so the section may expand later.
    - service_count must be 1 through 7 and fresh builds should normally use 5.
    - Keep numbers sequential 01 through 07.
    - Make the featured service the strongest strategic/commercial offer.
    - image_url must be empty; the application resolves imagery.
    - Do not invent awards, client names, guarantees, metrics, or unverifiable claims.
    - Preserve industry context and write concise, specific service copy.

    TXT;
    }

    private function servicesGridPremiumSchema(): string
    {
        return <<<TXT

    services_grid_premium

    - type = services_grid_premium
    - theme = auto
    - eyebrow, heading, text
    - primary_label, primary_url
    - image_url = ""
    - featured_image_url = ""
    - service_two_image_url = ""
    - service_three_image_url = ""
    - service_four_image_url = ""
    - service_five_image_url = ""
    - service_six_image_url = ""
    - service_seven_image_url = ""
        - featured_number, featured_title, featured_text, featured_meta
    - service_two_number, service_two_title, service_two_text
    - service_three_number, service_three_title, service_three_text
    - service_four_number, service_four_title, service_four_text
    - service_five_number, service_five_title, service_five_text
    - service_six_number, service_six_title, service_six_text
    - service_seven_number, service_seven_title, service_seven_text
    - service_count
    - proof_value, proof_label

    Requirements:
    - Write seven distinct, relevant services so the section may expand later.
    - service_count must be 1 through 7 and fresh builds should normally use 5.
    - Keep numbers sequential 01 through 07.
    - Make the featured service the strongest strategic/commercial offer.
    - image_url must be empty; the application resolves imagery.
    - Do not invent awards, client names, guarantees, metrics, or unverifiable claims.
    - Preserve industry context and write concise, specific service copy.

    TXT;
    }

    private function servicesFeaturePremiumSchema(): string
    {
        return <<<TXT

    services_feature_premium

    - type = services_feature_premium
    - theme = auto
    - eyebrow, heading, text
    - primary_label, primary_url
    - image_url = ""
    - featured_image_url = ""
    - service_two_image_url = ""
    - service_three_image_url = ""
    - service_four_image_url = ""
    - service_five_image_url = ""
    - service_six_image_url = ""
    - service_seven_image_url = ""
        - featured_number, featured_title, featured_text, featured_meta
    - service_two_number, service_two_title, service_two_text
    - service_three_number, service_three_title, service_three_text
    - service_four_number, service_four_title, service_four_text
    - service_five_number, service_five_title, service_five_text
    - service_six_number, service_six_title, service_six_text
    - service_seven_number, service_seven_title, service_seven_text
    - service_count
    - proof_value, proof_label

    Requirements:
    - Write seven distinct, relevant services so the section may expand later.
    - service_count must be 1 through 7 and fresh builds should normally use 5.
    - Keep numbers sequential 01 through 07.
    - Make the featured service the strongest strategic/commercial offer.
    - image_url must be empty; the application resolves imagery.
    - Do not invent awards, client names, guarantees, metrics, or unverifiable claims.
    - Preserve industry context and write concise, specific service copy.

    TXT;
    }

    private function aboutFounderVisualPremiumSchema(): string
    {
        return <<<TXT

    about_founder_visual_premium

    - type = about_founder_visual_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a about section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function aboutImageManifestoPremiumSchema(): string
    {
        return <<<TXT

    about_image_manifesto_premium

    - type = about_image_manifesto_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a about section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function aboutJourneyGalleryPremiumSchema(): string
    {
        return <<<TXT

    about_journey_gallery_premium

    - type = about_journey_gallery_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a about section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function aboutStoryCollagePremiumSchema(): string
    {
        return <<<TXT

    about_story_collage_premium

    - type = about_story_collage_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a about section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function brandValueCardsPremiumSchema(): string
    {
        return <<<TXT

    brand_value_cards_premium

    - type = brand_value_cards_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a about section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function brandVisualPrinciplesPremiumSchema(): string
    {
        return <<<TXT

    brand_visual_principles_premium

    - type = brand_visual_principles_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a about section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function constructionCapabilitySplitPremiumSchema(): string
    {
        return <<<TXT

    construction_capability_split_premium

    - type = construction_capability_split_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a services section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function constructionProjectCardsPremiumSchema(): string
    {
        return <<<TXT

    construction_project_cards_premium

    - type = construction_project_cards_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a services section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function constructionServicePhotosPremiumSchema(): string
    {
        return <<<TXT

    construction_service_photos_premium

    - type = construction_service_photos_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a services section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function constructionSiteProgressPremiumSchema(): string
    {
        return <<<TXT

    construction_site_progress_premium

    - type = construction_site_progress_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a services section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function contactEditorialSplitPremiumSchema(): string
    {
        return <<<TXT

    contact_editorial_split_premium

    - type = contact_editorial_split_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a contact section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function contactImageFormPremiumSchema(): string
    {
        return <<<TXT

    contact_image_form_premium

    - type = contact_image_form_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a contact section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function contactOfficeCardsPremiumSchema(): string
    {
        return <<<TXT

    contact_office_cards_premium

    - type = contact_office_cards_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a contact section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function contactVisualInquiryPremiumSchema(): string
    {
        return <<<TXT

    contact_visual_inquiry_premium

    - type = contact_visual_inquiry_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a contact section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function contentAsymmetricStoryPremiumSchema(): string
    {
        return <<<TXT

    content_asymmetric_story_premium

    - type = content_asymmetric_story_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a content section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function contentEditorialImageStackPremiumSchema(): string
    {
        return <<<TXT

    content_editorial_image_stack_premium

    - type = content_editorial_image_stack_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a content section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function contentMediaManifestoPremiumSchema(): string
    {
        return <<<TXT

    content_media_manifesto_premium

    - type = content_media_manifesto_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a content section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function contentVisualQuotePremiumSchema(): string
    {
        return <<<TXT

    content_visual_quote_premium

    - type = content_visual_quote_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a content section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function ctaBackgroundMediaPremiumSchema(): string
    {
        return <<<TXT

    cta_background_media_premium

    - type = cta_background_media_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a cta section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function ctaEditorialBannerPremiumSchema(): string
    {
        return <<<TXT

    cta_editorial_banner_premium

    - type = cta_editorial_banner_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a cta section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function ctaFloatingPanelPremiumSchema(): string
    {
        return <<<TXT

    cta_floating_panel_premium

    - type = cta_floating_panel_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a cta section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function ctaImageSplitPremiumSchema(): string
    {
        return <<<TXT

    cta_image_split_premium

    - type = cta_image_split_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a cta section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function ctaMediaCardsPremiumSchema(): string
    {
        return <<<TXT

    cta_media_cards_premium

    - type = cta_media_cards_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a cta section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function dentalClinicStoryPremiumSchema(): string
    {
        return <<<TXT

    dental_clinic_story_premium

    - type = dental_clinic_story_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a services section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function dentalTreatmentCardsPremiumSchema(): string
    {
        return <<<TXT

    dental_treatment_cards_premium

    - type = dental_treatment_cards_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a services section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function featuresAsymmetricMediaPremiumSchema(): string
    {
        return <<<TXT

    features_asymmetric_media_premium

    - type = features_asymmetric_media_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a feature section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function featuresEditorialMosaicPremiumSchema(): string
    {
        return <<<TXT

    features_editorial_mosaic_premium

    - type = features_editorial_mosaic_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a feature section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function featuresFullbleedPanelsPremiumSchema(): string
    {
        return <<<TXT

    features_fullbleed_panels_premium

    - type = features_fullbleed_panels_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a feature section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function featuresImageIndexPremiumSchema(): string
    {
        return <<<TXT

    features_image_index_premium

    - type = features_image_index_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a feature section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function featuresImageStackPremiumSchema(): string
    {
        return <<<TXT

    features_image_stack_premium

    - type = features_image_stack_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a feature section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function featuresMediaStepsPremiumSchema(): string
    {
        return <<<TXT

    features_media_steps_premium

    - type = features_media_steps_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a feature section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function featuresOverlapCardsPremiumSchema(): string
    {
        return <<<TXT

    features_overlap_cards_premium

    - type = features_overlap_cards_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a feature section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function featuresSpotlightCardsPremiumSchema(): string
    {
        return <<<TXT

    features_spotlight_cards_premium

    - type = features_spotlight_cards_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a feature section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function featuresVisualSplitPremiumSchema(): string
    {
        return <<<TXT

    features_visual_split_premium

    - type = features_visual_split_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a feature section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function galleryAsymmetricPremiumSchema(): string
    {
        return <<<TXT

    gallery_asymmetric_premium

    - type = gallery_asymmetric_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a portfolio section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function galleryEditorialGridPremiumSchema(): string
    {
        return <<<TXT

    gallery_editorial_grid_premium

    - type = gallery_editorial_grid_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a portfolio section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function galleryImageRailPremiumSchema(): string
    {
        return <<<TXT

    gallery_image_rail_premium

    - type = gallery_image_rail_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a portfolio section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function galleryStoryTilesPremiumSchema(): string
    {
        return <<<TXT

    gallery_story_tiles_premium

    - type = gallery_story_tiles_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a portfolio section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function hotelExperienceCardsPremiumSchema(): string
    {
        return <<<TXT

    hotel_experience_cards_premium

    - type = hotel_experience_cards_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a services section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function hotelRoomCollectionPremiumSchema(): string
    {
        return <<<TXT

    hotel_room_collection_premium

    - type = hotel_room_collection_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a services section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function locationCitySpotlightPremiumSchema(): string
    {
        return <<<TXT

    location_city_spotlight_premium

    - type = location_city_spotlight_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a contact section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function locationMultiOfficePremiumSchema(): string
    {
        return <<<TXT

    location_multi_office_premium

    - type = location_multi_office_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a contact section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function locationPhotoCardsPremiumSchema(): string
    {
        return <<<TXT

    location_photo_cards_premium

    - type = location_photo_cards_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a contact section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function locationVisualDirectoryPremiumSchema(): string
    {
        return <<<TXT

    location_visual_directory_premium

    - type = location_visual_directory_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a contact section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function medicalCarePathwaysPremiumSchema(): string
    {
        return <<<TXT

    medical_care_pathways_premium

    - type = medical_care_pathways_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a services section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function medicalFacilityShowcasePremiumSchema(): string
    {
        return <<<TXT

    medical_facility_showcase_premium

    - type = medical_facility_showcase_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a services section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function portfolioCinematicGridPremiumSchema(): string
    {
        return <<<TXT

    portfolio_cinematic_grid_premium

    - type = portfolio_cinematic_grid_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a portfolio section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function portfolioEditorialCardsPremiumSchema(): string
    {
        return <<<TXT

    portfolio_editorial_cards_premium

    - type = portfolio_editorial_cards_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a portfolio section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function portfolioFullbleedProjectsPremiumSchema(): string
    {
        return <<<TXT

    portfolio_fullbleed_projects_premium

    - type = portfolio_fullbleed_projects_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a portfolio section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function portfolioImageIndexPremiumSchema(): string
    {
        return <<<TXT

    portfolio_image_index_premium

    - type = portfolio_image_index_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a portfolio section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function portfolioMediaLedgerPremiumSchema(): string
    {
        return <<<TXT

    portfolio_media_ledger_premium

    - type = portfolio_media_ledger_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a portfolio section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function portfolioProjectPanelsPremiumSchema(): string
    {
        return <<<TXT

    portfolio_project_panels_premium

    - type = portfolio_project_panels_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a portfolio section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function portfolioSplitShowcasePremiumSchema(): string
    {
        return <<<TXT

    portfolio_split_showcase_premium

    - type = portfolio_split_showcase_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a portfolio section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function portfolioStaggeredGalleryPremiumSchema(): string
    {
        return <<<TXT

    portfolio_staggered_gallery_premium

    - type = portfolio_staggered_gallery_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a portfolio section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function portfolioStoryIndexPremiumSchema(): string
    {
        return <<<TXT

    portfolio_story_index_premium

    - type = portfolio_story_index_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a portfolio section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function processEditorialJourneyPremiumSchema(): string
    {
        return <<<TXT

    process_editorial_journey_premium

    - type = process_editorial_journey_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a process section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function processImageTimelinePremiumSchema(): string
    {
        return <<<TXT

    process_image_timeline_premium

    - type = process_image_timeline_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a process section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function processMediaRoadmapPremiumSchema(): string
    {
        return <<<TXT

    process_media_roadmap_premium

    - type = process_media_roadmap_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a process section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function processNumberedPanelsPremiumSchema(): string
    {
        return <<<TXT

    process_numbered_panels_premium

    - type = process_numbered_panels_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a process section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function processVisualStepsPremiumSchema(): string
    {
        return <<<TXT

    process_visual_steps_premium

    - type = process_visual_steps_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a process section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function proofCaseStoryPremiumSchema(): string
    {
        return <<<TXT

    proof_case_story_premium

    - type = proof_case_story_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a stats section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function proofMetricGalleryPremiumSchema(): string
    {
        return <<<TXT

    proof_metric_gallery_premium

    - type = proof_metric_gallery_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a stats section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function realestateAgentStoryPremiumSchema(): string
    {
        return <<<TXT

    realestate_agent_story_premium

    - type = realestate_agent_story_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a services section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function realestateFeaturedListingPremiumSchema(): string
    {
        return <<<TXT

    realestate_featured_listing_premium

    - type = realestate_featured_listing_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a services section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function realestateNeighborhoodCardsPremiumSchema(): string
    {
        return <<<TXT

    realestate_neighborhood_cards_premium

    - type = realestate_neighborhood_cards_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a services section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function realestatePropertyGridPremiumSchema(): string
    {
        return <<<TXT

    realestate_property_grid_premium

    - type = realestate_property_grid_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a services section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function restaurantAtmosphereGalleryPremiumSchema(): string
    {
        return <<<TXT

    restaurant_atmosphere_gallery_premium

    - type = restaurant_atmosphere_gallery_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a services section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function restaurantReservationCtaPremiumSchema(): string
    {
        return <<<TXT

    restaurant_reservation_cta_premium

    - type = restaurant_reservation_cta_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a services section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function restaurantSignatureDishesPremiumSchema(): string
    {
        return <<<TXT

    restaurant_signature_dishes_premium

    - type = restaurant_signature_dishes_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a services section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function restaurantStoryMenuPremiumSchema(): string
    {
        return <<<TXT

    restaurant_story_menu_premium

    - type = restaurant_story_menu_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a services section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function servicesEditorialRowsPremiumSchema(): string
    {
        return <<<TXT

    services_editorial_rows_premium

    - type = services_editorial_rows_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a services section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function servicesFullbleedOverlayPremiumSchema(): string
    {
        return <<<TXT

    services_fullbleed_overlay_premium

    - type = services_fullbleed_overlay_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a services section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function servicesHorizontalMediaPremiumSchema(): string
    {
        return <<<TXT

    services_horizontal_media_premium

    - type = services_horizontal_media_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a services section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function servicesImageAccordionPremiumSchema(): string
    {
        return <<<TXT

    services_image_accordion_premium

    - type = services_image_accordion_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a services section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function servicesImageTrioPremiumSchema(): string
    {
        return <<<TXT

    services_image_trio_premium

    - type = services_image_trio_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a services section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function servicesMosaicMediaPremiumSchema(): string
    {
        return <<<TXT

    services_mosaic_media_premium

    - type = services_mosaic_media_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a services section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function servicesNumberedImagesPremiumSchema(): string
    {
        return <<<TXT

    services_numbered_images_premium

    - type = services_numbered_images_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a services section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function servicesOverlayGridPremiumSchema(): string
    {
        return <<<TXT

    services_overlay_grid_premium

    - type = services_overlay_grid_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a services section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function servicesStaggeredMediaPremiumSchema(): string
    {
        return <<<TXT

    services_staggered_media_premium

    - type = services_staggered_media_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a services section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function servicesVisualDirectoryPremiumSchema(): string
    {
        return <<<TXT

    services_visual_directory_premium

    - type = services_visual_directory_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a services section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function statsEditorialNumbersPremiumSchema(): string
    {
        return <<<TXT

    stats_editorial_numbers_premium

    - type = stats_editorial_numbers_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a stats section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function statsImagePanelsPremiumSchema(): string
    {
        return <<<TXT

    stats_image_panels_premium

    - type = stats_image_panels_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a stats section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function statsPhotoMetricsPremiumSchema(): string
    {
        return <<<TXT

    stats_photo_metrics_premium

    - type = stats_photo_metrics_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a stats section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function statsVisualMosaicPremiumSchema(): string
    {
        return <<<TXT

    stats_visual_mosaic_premium

    - type = stats_visual_mosaic_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a stats section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function teamImageGridPremiumSchema(): string
    {
        return <<<TXT

    team_image_grid_premium

    - type = team_image_grid_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a team section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function teamLeadershipSplitPremiumSchema(): string
    {
        return <<<TXT

    team_leadership_split_premium

    - type = team_leadership_split_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a team section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function teamPeopleMosaicPremiumSchema(): string
    {
        return <<<TXT

    team_people_mosaic_premium

    - type = team_people_mosaic_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a team section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function teamPortraitEditorialPremiumSchema(): string
    {
        return <<<TXT

    team_portrait_editorial_premium

    - type = team_portrait_editorial_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a team section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function teamProfileOverlayPremiumSchema(): string
    {
        return <<<TXT

    team_profile_overlay_premium

    - type = team_profile_overlay_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a team section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function testimonialsClientSpotlightPremiumSchema(): string
    {
        return <<<TXT

    testimonials_client_spotlight_premium

    - type = testimonials_client_spotlight_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a testimonials section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function testimonialsEditorialQuotesPremiumSchema(): string
    {
        return <<<TXT

    testimonials_editorial_quotes_premium

    - type = testimonials_editorial_quotes_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a testimonials section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function testimonialsFeaturedStoryPremiumSchema(): string
    {
        return <<<TXT

    testimonials_featured_story_premium

    - type = testimonials_featured_story_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a testimonials section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function testimonialsImageWallPremiumSchema(): string
    {
        return <<<TXT

    testimonials_image_wall_premium

    - type = testimonials_image_wall_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a testimonials section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function testimonialsPortraitCardsPremiumSchema(): string
    {
        return <<<TXT

    testimonials_portrait_cards_premium

    - type = testimonials_portrait_cards_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a testimonials section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function travelDestinationStoryPremiumSchema(): string
    {
        return <<<TXT

    travel_destination_story_premium

    - type = travel_destination_story_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a services section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function travelItineraryVisualPremiumSchema(): string
    {
        return <<<TXT

    travel_itinerary_visual_premium

    - type = travel_itinerary_visual_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a services section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function trustCertificationCardsPremiumSchema(): string
    {
        return <<<TXT

    trust_certification_cards_premium

    - type = trust_certification_cards_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a stats section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function trustImageProofPremiumSchema(): string
    {
        return <<<TXT

    trust_image_proof_premium

    - type = trust_image_proof_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a stats section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function trustLogoStoryPremiumSchema(): string
    {
        return <<<TXT

    trust_logo_story_premium

    - type = trust_logo_story_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a stats section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }

    private function trustPartnerShowcasePremiumSchema(): string
    {
        return <<<TXT

    trust_partner_showcase_premium

    - type = trust_partner_showcase_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - secondary_label
    - secondary_url
    - items (array of 4 to 6 relevant items)

    Each item contains:
    - label
    - title
    - text
    - image_url = ""

    Requirements:
    - Preserve the requested business/industry context.
    - Use concise, specific copy appropriate to a stats section.
    - Keep image_url empty; Cosmic assigns relevant provider imagery after generation.
    - Use 4 items by default unless the requested layout clearly benefits from another count between 4 and 6.
    - Do not invent awards, client names, certifications, guarantees, numeric performance claims, addresses, or people.
    - button_url and secondary_url must be # when no destination is supplied.
    - Do not use markdown or placeholder lorem ipsum.

    TXT;
    }


    private function blogMiniHeroSchema(): string
    {
        return <<<TXT

    blog_mini_hero

    - type = blog_mini_hero
    - theme = auto
    - layout_variant = mini-header-01
    - eyebrow
    - heading
    - text

    Requirements:
    - Use this only as a compact introduction for Blog, Posts, Updates, Resources, News, or editorial archives.
    - Keep the heading concise and the supporting text useful.
    - layout_variant may be mini-header-01, mini-header-02, or mini-header-03.
    - Preserve the active website theme.
    - Do not invent article counts, authors, publication statistics, or claims.

    TXT;
    }

    private function commerceCartClassicSchema(): string
    {
        return <<<TXT

    commerce_cart_classic

    - type = commerce_cart_classic
    - theme = auto
    - heading
    - text
    - checkout_label
    - continue_label

    Requirements:
    - This is a commerce runtime cart layout. Luna may edit presentation copy only.
    - Never invent cart items, prices, discounts, shipping totals, taxes, stock, or order data.
    - Preserve live commerce runtime behavior and the active website theme.

    TXT;
    }

    private function commerceCartCompactSchema(): string
    {
        return <<<TXT

    commerce_cart_compact

    - type = commerce_cart_compact
    - theme = auto
    - heading
    - text
    - checkout_label
    - continue_label

    Requirements:
    - This is a compact commerce runtime cart layout. Luna may edit presentation copy only.
    - Never invent cart items, prices, discounts, shipping totals, taxes, stock, or order data.
    - Preserve live commerce runtime behavior and the active website theme.

    TXT;
    }

    private function commerceCartSplitSchema(): string
    {
        return <<<TXT

    commerce_cart_split

    - type = commerce_cart_split
    - theme = auto
    - heading
    - text
    - checkout_label
    - continue_label

    Requirements:
    - This is a split commerce runtime cart layout. Luna may edit presentation copy only.
    - Never invent cart items, prices, discounts, shipping totals, taxes, stock, or order data.
    - Preserve live commerce runtime behavior and the active website theme.

    TXT;
    }

    private function commerceCatalogCompactSchema(): string
    {
        return <<<TXT

    commerce_catalog_compact

    - type = commerce_catalog_compact
    - theme = auto
    - heading
    - text
    - button_label
    - button_url

    Requirements:
    - This is a live commerce catalog/product presentation layout.
    - Luna may edit section copy and choose this registered layout, but product names, prices, inventory, variants, categories, and product images are runtime/catalog-owned.
    - Never fabricate product data.
    - Preserve commerce runtime bindings and the active website theme.

    TXT;
    }

    private function commerceCatalogEditorialSchema(): string
    {
        return <<<TXT

    commerce_catalog_editorial

    - type = commerce_catalog_editorial
    - theme = auto
    - heading
    - text
    - button_label
    - button_url

    Requirements:
    - This is a live commerce catalog/product presentation layout.
    - Luna may edit section copy and choose this registered layout, but product names, prices, inventory, variants, categories, and product images are runtime/catalog-owned.
    - Never fabricate product data.
    - Preserve commerce runtime bindings and the active website theme.

    TXT;
    }

    private function commerceCatalogGridSchema(): string
    {
        return <<<TXT

    commerce_catalog_grid

    - type = commerce_catalog_grid
    - theme = auto
    - heading
    - text
    - button_label
    - button_url

    Requirements:
    - This is a live commerce catalog/product presentation layout.
    - Luna may edit section copy and choose this registered layout, but product names, prices, inventory, variants, categories, and product images are runtime/catalog-owned.
    - Never fabricate product data.
    - Preserve commerce runtime bindings and the active website theme.

    TXT;
    }

    private function commerceCategoriesSchema(): string
    {
        return <<<TXT

    commerce_categories

    - type = commerce_categories
    - theme = auto
    - heading
    - text
    - button_label
    - button_url

    Requirements:
    - This is a live commerce catalog/product presentation layout.
    - Luna may edit section copy and choose this registered layout, but product names, prices, inventory, variants, categories, and product images are runtime/catalog-owned.
    - Never fabricate product data.
    - Preserve commerce runtime bindings and the active website theme.

    TXT;
    }

    private function commerceCheckoutClassicSchema(): string
    {
        return <<<TXT

    commerce_checkout_classic

    - type = commerce_checkout_classic
    - theme = auto
    - heading
    - text
    - payment_label
    - help_text

    Requirements:
    - This is a live commerce checkout layout. Luna may edit presentation copy/layout only.
    - Never invent customer details, cart items, prices, tax, shipping, discounts, payment state, or order totals.
    - Preserve commerce runtime bindings and the active website theme.

    TXT;
    }

    private function commerceCheckoutExpressSchema(): string
    {
        return <<<TXT

    commerce_checkout_express

    - type = commerce_checkout_express
    - theme = auto
    - heading
    - text
    - payment_label
    - help_text

    Requirements:
    - This is a live commerce checkout layout. Luna may edit presentation copy/layout only.
    - Never invent customer details, cart items, prices, tax, shipping, discounts, payment state, or order totals.
    - Preserve commerce runtime bindings and the active website theme.

    TXT;
    }

    private function commerceCheckoutSplitSchema(): string
    {
        return <<<TXT

    commerce_checkout_split

    - type = commerce_checkout_split
    - theme = auto
    - heading
    - text
    - payment_label
    - help_text

    Requirements:
    - This is a live commerce checkout layout. Luna may edit presentation copy/layout only.
    - Never invent customer details, cart items, prices, tax, shipping, discounts, payment state, or order totals.
    - Preserve commerce runtime bindings and the active website theme.

    TXT;
    }

    private function commerceFeaturedCollectionSchema(): string
    {
        return <<<TXT

    commerce_featured_collection

    - type = commerce_featured_collection
    - theme = auto
    - heading
    - text
    - button_label
    - button_url

    Requirements:
    - This is a live commerce catalog/product presentation layout.
    - Luna may edit section copy and choose this registered layout, but product names, prices, inventory, variants, categories, and product images are runtime/catalog-owned.
    - Never fabricate product data.
    - Preserve commerce runtime bindings and the active website theme.

    TXT;
    }

    private function commerceFeaturedProductsSchema(): string
    {
        return <<<TXT

    commerce_featured_products

    - type = commerce_featured_products
    - theme = auto
    - heading
    - text
    - button_label
    - button_url

    Requirements:
    - This is a live commerce catalog/product presentation layout.
    - Luna may edit section copy and choose this registered layout, but product names, prices, inventory, variants, categories, and product images are runtime/catalog-owned.
    - Never fabricate product data.
    - Preserve commerce runtime bindings and the active website theme.

    TXT;
    }

    private function commerceMiniCartSchema(): string
    {
        return <<<TXT

    commerce_mini_cart

    - type = commerce_mini_cart
    - theme = auto
    - heading
    - text

    Requirements:
    - This is a commerce runtime component. Luna may edit presentation copy/layout only.
    - Never invent product, price, variant, stock, cart, or order data.
    - Preserve runtime bindings and the active website theme.

    TXT;
    }

    private function commercePriceSchema(): string
    {
        return <<<TXT

    commerce_price

    - type = commerce_price
    - theme = auto
    - heading
    - text

    Requirements:
    - This is a commerce runtime component. Luna may edit presentation copy/layout only.
    - Never invent product, price, variant, stock, cart, or order data.
    - Preserve runtime bindings and the active website theme.

    TXT;
    }

    private function commerceProductGallerySchema(): string
    {
        return <<<TXT

    commerce_product_gallery

    - type = commerce_product_gallery
    - theme = auto
    - heading
    - text
    - button_label
    - button_url

    Requirements:
    - This is a live commerce catalog/product presentation layout.
    - Luna may edit section copy and choose this registered layout, but product names, prices, inventory, variants, categories, and product images are runtime/catalog-owned.
    - Never fabricate product data.
    - Preserve commerce runtime bindings and the active website theme.

    TXT;
    }

    private function commercePromoSplitSchema(): string
    {
        return <<<TXT

    commerce_promo_split

    - type = commerce_promo_split
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - image_url = "" 

    Requirements:
    - Use image_url only as presentation media; Cosmic may resolve it from the media provider.
    - Do not fabricate discounts, coupon values, expiry dates, stock, or product claims.
    - Preserve the active website theme.

    TXT;
    }

    private function commerceRelatedProductsSchema(): string
    {
        return <<<TXT

    commerce_related_products

    - type = commerce_related_products
    - theme = auto
    - heading
    - text
    - button_label
    - button_url

    Requirements:
    - This is a live commerce catalog/product presentation layout.
    - Luna may edit section copy and choose this registered layout, but product names, prices, inventory, variants, categories, and product images are runtime/catalog-owned.
    - Never fabricate product data.
    - Preserve commerce runtime bindings and the active website theme.

    TXT;
    }

    private function commerceVariationSelectorSchema(): string
    {
        return <<<TXT

    commerce_variation_selector

    - type = commerce_variation_selector
    - theme = auto
    - heading
    - text

    Requirements:
    - This is a commerce runtime component. Luna may edit presentation copy/layout only.
    - Never invent product, price, variant, stock, cart, or order data.
    - Preserve runtime bindings and the active website theme.

    TXT;
    }

    private function contentEventsGridSchema(): string
    {
        return <<<TXT

    content_events_grid

    - type = content_events_grid
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url

    Requirements:
    - This is a dynamic content/posts layout. Luna may edit section framing copy and select the layout.
    - Entries, event records, dates, authors, excerpts, taxonomies, and resource data are runtime/content-store owned.
    - Never invent live content records.
    - Preserve the active website theme.

    TXT;
    }

    private function contentFeaturedEntrySchema(): string
    {
        return <<<TXT

    content_featured_entry

    - type = content_featured_entry
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url

    Requirements:
    - This is a dynamic content/posts layout. Luna may edit section framing copy and select the layout.
    - Entries, event records, dates, authors, excerpts, taxonomies, and resource data are runtime/content-store owned.
    - Never invent live content records.
    - Preserve the active website theme.

    TXT;
    }

    private function contentGridClassicSchema(): string
    {
        return <<<TXT

    content_grid_classic

    - type = content_grid_classic
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url

    Requirements:
    - This is a dynamic content/posts layout. Luna may edit section framing copy and select the layout.
    - Entries, event records, dates, authors, excerpts, taxonomies, and resource data are runtime/content-store owned.
    - Never invent live content records.
    - Preserve the active website theme.

    TXT;
    }

    private function contentGridCompactSchema(): string
    {
        return <<<TXT

    content_grid_compact

    - type = content_grid_compact
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url

    Requirements:
    - This is a dynamic content/posts layout. Luna may edit section framing copy and select the layout.
    - Entries, event records, dates, authors, excerpts, taxonomies, and resource data are runtime/content-store owned.
    - Never invent live content records.
    - Preserve the active website theme.

    TXT;
    }

    private function contentGridEditorialSchema(): string
    {
        return <<<TXT

    content_grid_editorial

    - type = content_grid_editorial
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url

    Requirements:
    - This is a dynamic content/posts layout. Luna may edit section framing copy and select the layout.
    - Entries, event records, dates, authors, excerpts, taxonomies, and resource data are runtime/content-store owned.
    - Never invent live content records.
    - Preserve the active website theme.

    TXT;
    }

    private function contentLatestEntriesSchema(): string
    {
        return <<<TXT

    content_latest_entries

    - type = content_latest_entries
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url

    Requirements:
    - This is a dynamic content/posts layout. Luna may edit section framing copy and select the layout.
    - Entries, event records, dates, authors, excerpts, taxonomies, and resource data are runtime/content-store owned.
    - Never invent live content records.
    - Preserve the active website theme.

    TXT;
    }


    private function miniHeroMinimalSchema(): string
    {
        return <<<TXT

    mini_hero_minimal

    - type = mini_hero_minimal
    - theme = auto
    - eyebrow
    - heading
    - text

    Requirements:
    - Use as a compact internal-page hero with restrained visual weight.
    - Keep copy concise and preserve the active website theme.
    - Do not invent statistics, awards, client names, or unverifiable claims.

    TXT;
    }

    private function miniHeroPromoSchema(): string
    {
        return <<<TXT

    mini_hero_promo

    - type = mini_hero_promo
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label
    - button_url
    - image_url = ""

    Requirements:
    - Use as a compact promotional internal-page hero.
    - image_url must remain empty for Cosmic media resolution.
    - Keep the CTA factual; do not invent discounts, expiry dates, or guarantees.
    - Preserve the active website theme.

    TXT;
    }

    private function miniHeroSplitSchema(): string
    {
        return <<<TXT

    mini_hero_split

    - type = mini_hero_split
    - theme = auto
    - eyebrow
    - heading
    - text
    - image_url = ""

    Requirements:
    - Use as a compact split internal-page hero with relevant imagery.
    - image_url must remain empty for Cosmic media resolution.
    - Preserve industry context and the active website theme.
    - Do not invent awards, client names, or unverifiable claims.

    TXT;
    }

    private function newsletterCtaSchema(): string
    {
        return <<<TXT

    newsletter_cta

    - type = newsletter_cta
    - theme = auto
    - eyebrow
    - heading
    - text
    - button_label

    Requirements:
    - Use for newsletter/email signup framing only.
    - Do not invent subscriber counts, send frequency, incentives, or privacy claims.
    - Form submission behavior is runtime-owned; Luna edits presentation copy only.
    - Preserve the active website theme.

    TXT;
    }

    private function aboutTimelineStorySchema(): string
    {
        return <<<TXT

    about_timeline_story
    - type = about_timeline_story
    - theme = auto
    - eyebrow, heading, text, primary_label, primary_url
    - year_one, title_one, text_one
    - year_two, title_two, text_two
    - year_three, title_three, text_three
    - year_four, title_four, text_four

    Requirements:
    - Tell a concise four-stage company or brand story in chronological order.
    - Only use real years supplied by the user; otherwise use neutral labels such as "The beginning", "Next chapter", or "Today" in year fields.
    - Keep each milestone factual and avoid invented achievements, clients, awards, or statistics.
    - Keep primary_url as # when no destination was supplied.

    TXT;
    }

    private function aboutFounderStorySchema(): string
    {
        return <<<TXT

    about_founder_story
    - type = about_founder_story
    - theme = auto
    - eyebrow, heading, text, quote
    - founder_name, founder_role
    - principle_one, principle_two, principle_three
    - image_url
    - primary_label, primary_url

    Requirements:
    - Write a human, editorial founder narrative grounded only in supplied facts.
    - Never invent a founder name, biography detail, credential, quote, or title. Use neutral editable starter wording if absent.
    - image_url must be an empty string; the application assigns the image.
    - Keep primary_url as # when no destination was supplied.

    TXT;
    }

    private function aboutMissionGridSchema(): string
    {
        return <<<TXT

    about_mission_grid
    - type = about_mission_grid
    - theme = auto
    - eyebrow, heading, text
    - mission_label, mission_title, mission_text
    - vision_label, vision_title, vision_text
    - value_one_title, value_one_text
    - value_two_title, value_two_text
    - value_three_title, value_three_text

    Requirements:
    - Express one clear mission, one aspirational vision, and exactly three distinct values.
    - Keep language specific to the supplied business rather than generic corporate slogans.
    - Do not invent certifications, social impact claims, awards, or measurable outcomes.

    TXT;
    }

    private function aboutInteractiveStatsSchema(): string
    {
        return <<<TXT

    about_interactive_stats
    - type = about_interactive_stats
    - theme = auto
    - eyebrow, heading, text
    - stat_one_value, stat_one_label, stat_one_text
    - stat_two_value, stat_two_label, stat_two_text
    - stat_three_value, stat_three_label, stat_three_text
    - stat_four_value, stat_four_label, stat_four_text
    - footnote

    Requirements:
    - Use real metrics only when explicitly provided by the user.
    - If verified numeric company metrics were not supplied, use non-factual editable starter labels instead of fabricated numbers.
    - Keep every description concise and explain why each metric matters.
    - Never invent customer counts, revenue, growth, awards, locations, years, or performance claims.

    TXT;
    }


    private function aboutBrandJourneySchema(): string
    {
        return <<<TXT

    about_brand_journey
    - type = about_brand_journey
    - theme = auto
    - eyebrow, heading, text
    - chapter_one_label, chapter_one_title, chapter_one_text
    - chapter_two_label, chapter_two_title, chapter_two_text
    - chapter_three_label, chapter_three_title, chapter_three_text
    - chapter_four_label, chapter_four_title, chapter_four_text
    - primary_label, primary_url

    Requirements:
    - Describe four distinct stages in the evolution of the brand, offer, positioning, or customer experience.
    - Ground every chapter in supplied business facts. Do not invent dates, launches, acquisitions, clients, awards, or measurable results.
    - Keep labels short and editorial rather than numeric unless real dates were supplied.
    - Keep primary_url as # when no destination was supplied.

    TXT;
    }

    private function aboutAwardsTimelineSchema(): string
    {
        return <<<TXT

    about_awards_timeline
    - type = about_awards_timeline
    - theme = auto
    - eyebrow, heading, text
    - award_one_year, award_one_title, award_one_org
    - award_two_year, award_two_title, award_two_org
    - award_three_year, award_three_title, award_three_org
    - award_four_year, award_four_title, award_four_org
    - footnote

    Requirements:
    - Only include awards, honours, certifications, shortlistings, or recognition explicitly supplied by the user.
    - Never fabricate award names, organisations, years, rankings, or certifications.
    - If fewer than four verified recognitions exist, use clearly editable neutral starter wording rather than false claims.
    - Keep the footnote reminding the user to verify recognition before publishing when starter content remains.

    TXT;
    }

    private function aboutCultureSectionSchema(): string
    {
        return <<<TXT

    about_culture_section
    - type = about_culture_section
    - theme = auto
    - eyebrow, heading, text
    - pillar_one_title, pillar_one_text
    - pillar_two_title, pillar_two_text
    - pillar_three_title, pillar_three_text
    - pillar_four_title, pillar_four_text
    - closing_line

    Requirements:
    - Write exactly four distinct behaviour-led culture principles.
    - Prefer observable ways of working over generic corporate values.
    - Keep each pillar concise, specific, and appropriate to the supplied business context.
    - Do not invent benefits, employee statistics, certifications, awards, or workplace claims.

    TXT;
    }

    private function aboutOfficeGallerySchema(): string
    {
        return <<<TXT

    about_office_gallery
    - type = about_office_gallery
    - theme = auto
    - eyebrow, heading, text
    - image_one_url, image_one_caption
    - image_two_url, image_two_caption
    - image_three_url, image_three_caption

    Requirements:
    - Use this Spark for a real office, studio, venue, clinic, showroom, workshop, or team environment.
    - image_one_url, image_two_url, and image_three_url must be empty strings; the application assigns images.
    - Captions must stay generic unless the user supplied specific locations or room names.
    - Do not claim facilities, locations, capacity, or amenities that were not supplied.

    TXT;
    }

    private function portfolioMasonrySchema(): string
    {
        return <<<TXT

    portfolio_masonry
    - type = portfolio_masonry
    - theme = auto
    - eyebrow, heading, text, primary_label, primary_url
    - project_one_title, project_one_meta, project_one_image_url through project_six_title, project_six_meta, project_six_image_url

    Requirements:
    - Write exactly six distinct project entries using only work or capabilities supported by the supplied business context.
    - project_*_image_url must be empty strings; the application assigns images.
    - Keep project_meta short and factual; do not invent clients, awards, dates, or results.
    - Keep primary_url as # when no destination was supplied.

    TXT;
    }

    private function portfolioPinterestSchema(): string
    {
        return <<<TXT

    portfolio_pinterest
    - type = portfolio_pinterest
    - theme = auto
    - eyebrow, heading, text, primary_label, primary_url
    - project_one_title, project_one_meta, project_one_image_url through project_six_title, project_six_meta, project_six_image_url

    Requirements:
    - Write exactly six concise visual-project entries suited to an image-first portfolio.
    - project_*_image_url must be empty strings; the application assigns images.
    - Do not invent client names, locations, awards, or performance claims.
    - Keep primary_url as # when no destination was supplied.

    TXT;
    }

    private function portfolioHoverVideoSchema(): string
    {
        return <<<TXT

    portfolio_hover_video
    - type = portfolio_hover_video
    - theme = auto
    - eyebrow, heading, text, primary_label, primary_url
    - project_one_title, project_one_meta, project_one_image_url, project_one_video_url through project_six_title, project_six_meta, project_six_image_url, project_six_video_url

    Requirements:
    - Write exactly six project entries.
    - All image and video URL fields must be empty strings; the application or user supplies media.
    - Content must remain meaningful when motion is unavailable; do not describe unsupported video footage.
    - Do not invent clients, awards, dates, or results.

    TXT;
    }

    private function portfolioCaseStudySchema(): string
    {
        return <<<TXT

    portfolio_case_study
    - type = portfolio_case_study
    - theme = auto
    - eyebrow
    - heading
    - text
    - project_title
    - project_meta
    - image_url = ""
    - challenge_label
    - challenge_text
    - approach_label
    - approach_text
    - outcome_label
    - outcome_text
    - metric_value
    - metric_label
    - primary_label
    - primary_url
    - footnote

    Requirements:
    - image_url must be an empty string; the application assigns an image.
    - Never invent a client name, revenue figure, percentage, award, testimonial, or performance result.
    - If no verified metric was supplied, metric_value must be "—" and metric_label should invite the user to add a verified result.
    - Keep challenge, approach, and outcome useful but conservative and grounded in supplied context.
    - Keep primary_url as # when no destination was supplied.

    TXT;
    }

    private function portfolioBeforeAfterSchema(): string
    {
        return <<<TXT

    portfolio_before_after
    - type = portfolio_before_after
    - theme = auto
    - eyebrow
    - heading
    - text
    - project_title
    - project_meta
    - before_label
    - after_label
    - before_image_url = ""
    - after_image_url = ""
    - outcome_label
    - outcome_text
    - primary_label
    - primary_url

    Requirements:
    - Describe a transformation only from supplied facts. Never invent measurable outcomes.
    - before_image_url and after_image_url must be empty strings; the application assigns images.
    - Keep primary_url as # when no destination was supplied.

    TXT;
    }

    private function portfolioFilterableSchema(): string
    {
        return <<<TXT

    portfolio_filterable
    - type = portfolio_filterable
    - theme = auto
    - eyebrow
    - heading
    - text
    - primary_label
    - primary_url
    - project_one_title, project_one_meta, project_one_category, project_one_image_url
      through project_six_title, project_six_meta, project_six_category, project_six_image_url

    Requirements:
    - Generate exactly six concise project entries with useful category labels.
    - All project image URL fields must be empty strings; the application assigns images.
    - Never invent clients, awards, dates, locations, or performance results.
    - Keep primary_url as # when no destination was supplied.

    TXT;
    }

    private function portfolioAnimatedSchema(): string
    {
        return <<<TXT

    portfolio_animated
    - type = portfolio_animated
    - theme = auto
    - eyebrow
    - heading
    - text
    - primary_label
    - primary_url
    - project_one_title, project_one_meta, project_one_image_url
      through project_four_title, project_four_meta, project_four_image_url

    Requirements:
    - Generate exactly four image-led project entries.
    - All project image URL fields must be empty strings; the application assigns images.
    - Motion is a visual treatment only; never invent outcomes or client claims.
    - Keep primary_url as # when no destination was supplied.

    TXT;
    }

    private function portfolioProjectTimelineSchema(): string
    {
        return <<<TXT

    portfolio_project_timeline
    - type = portfolio_project_timeline
    - theme = auto
    - eyebrow
    - heading
    - text
    - project_title
    - project_meta
    - primary_label
    - primary_url
    - step_one_number, step_one_title, step_one_text, step_one_image_url
      through step_four_number, step_four_title, step_four_text, step_four_image_url

    Requirements:
    - Generate exactly four concise stages grounded in the supplied project/process context.
    - All step image URL fields must be empty strings; the application assigns images.
    - Do not present invented client facts, dates, awards, or results as real.
    - Keep primary_url as # when no destination was supplied.

    TXT;
    }

    private function servicesStickyScrollSchema(): string
    {
        return <<<TXT

    services_sticky_scroll
    - type = services_sticky_scroll
    - theme = auto
    - eyebrow, heading, text, primary_label, primary_url
    - service_one_number, service_one_title, service_one_text through service_six_number, service_six_title, service_six_text

    Requirements:
    - Write exactly six distinct services in a logical sequence.
    - Keep titles concise and service text focused on practical customer outcomes.
    - Use short sequential service numbers such as 01 through 06.
    - Do not invent clients, awards, guarantees, certifications, or performance results.
    - Keep primary_url as # when no destination was supplied.

    TXT;
    }

    private function servicesHorizontalSchema(): string
    {
        return <<<TXT

    services_horizontal
    - type = services_horizontal
    - theme = auto
    - eyebrow, heading, text, primary_label, primary_url
    - service_one_number, service_one_title, service_one_text through service_six_number, service_six_title, service_six_text

    Requirements:
    - Write exactly six complementary services suitable for a horizontal browsing rail.
    - Keep each service concise enough to scan quickly.
    - Use short sequential service numbers such as 01 through 06.
    - Do not invent unsupported claims or credentials.
    - Keep primary_url as # when no destination was supplied.

    TXT;
    }

    private function servicesInteractiveTabsSchema(): string
    {
        return <<<TXT

    services_interactive_tabs
    - type = services_interactive_tabs
    - theme = auto
    - eyebrow, heading, text, primary_label, primary_url
    - tab_one_label, tab_one_title, tab_one_text through tab_four_label, tab_four_title, tab_four_text

    Requirements:
    - Write exactly four distinct service disciplines.
    - Tab labels must be short; titles and text should explain the value of each discipline.
    - Make the four tabs complementary rather than repetitive.
    - Do not invent unsupported claims, clients, awards, or results.
    - Keep primary_url as # when no destination was supplied.

    TXT;
    }

    private function servicesMegaGridSchema(): string
    {
        return <<<TXT

    services_mega_grid
    - type = services_mega_grid
    - theme = auto
    - eyebrow, heading, text, primary_label, primary_url
    - item_one_title, item_one_text through item_eight_title, item_eight_text

    Requirements:
    - Write exactly eight concise, distinct capabilities.
    - Use this only when the business has a broad service portfolio or the user asks for a large capability grid.
    - Keep descriptions compact and specific.
    - Do not invent unsupported services or business claims; stay within supplied context.
    - Keep primary_url as # when no destination was supplied.

    TXT;
    }

    private function servicesHoverCardsSchema(): string
    {
        return <<<TXT

    services_hover_cards

    - type = services_hover_cards
    - theme = auto
    - eyebrow, heading, text
    - primary_label, primary_url
    - card_one_number through card_six_number
    - card_one_title through card_six_title
    - card_one_summary through card_six_summary
    - card_one_text through card_six_text
    - card_one_link through card_six_link

    Requirements:
    - Present exactly six distinct but complementary services.
    - Keep each summary short enough to scan before hover.
    - Use the longer card text to explain the outcome and practical value of each service.
    - Keep link labels concise and action-oriented.
    - Do not invent clients, awards, guarantees, certifications, or performance results.
    - Keep primary_url as # when no destination was supplied.
    - Do not use markdown or placeholder copy.

    TXT;
    }


    private function servicesFeatureComparisonSchema(): string
    {
        return <<<TXT

    services_feature_comparison

    - type = services_feature_comparison
    - theme = auto
    - eyebrow, heading, text
    - option_one_name, option_one_kicker, option_one_text
    - option_two_name, option_two_kicker, option_two_text, option_two_badge
    - option_three_name, option_three_kicker, option_three_text
    - feature_one through feature_eight
    - option_one_one through option_one_eight
    - option_two_one through option_two_eight
    - option_three_one through option_three_eight
    - primary_label, primary_url, footnote

    Requirements:
    - Compare exactly three clearly differentiated service approaches or capability levels.
    - Keep eight capability rows concise, concrete, and easy to scan.
    - Highlight option two as the recommended or most versatile approach.
    - Do not include pricing unless the user explicitly supplied pricing.
    - Do not invent clients, awards, guarantees, certifications, or performance results.
    - Keep primary_url as # when no destination was supplied.
    - Do not use markdown or placeholder copy.

    TXT;
    }

    private function servicesPricingComparisonSchema(): string
    {
        return <<<TXT

    services_pricing_comparison

    - type = services_pricing_comparison
    - theme = auto
    - eyebrow, heading, text
    - starter_name, starter_price, starter_period, starter_description, starter_button_label, starter_button_url
    - growth_name, growth_price, growth_period, growth_description, growth_button_label, growth_button_url, growth_badge
    - pro_name, pro_price, pro_period, pro_description, pro_button_label, pro_button_url
    - feature_one through feature_six
    - starter_one through starter_six
    - growth_one through growth_six
    - pro_one through pro_six
    - footnote

    Requirements:
    - Present exactly three clearly differentiated service packages.
    - Keep six comparison rows concise, specific, and useful.
    - Use editable starter prices only when the user supplied pricing; otherwise use "Custom" or neutral package language.
    - Do not invent guarantees, clients, awards, or performance results.
    - Keep button URLs as # when no destination was supplied.
    - Do not use markdown or placeholder copy.
    - ICON CONTRAST RULE: icons must remain clearly visible on any Spark theme slot: primary, white, surface, dark, or light.
    - Never choose a plain white, near-white, pale-gray, transparent, or washed-out icon for a white/light card.
    - White/light icons are allowed only when the actual icon container/background is dark enough for strong contrast.
    - On white/light/surface cards prefer a saturated, dark, or primary-compatible emoji/icon with a clearly visible silhouette.
    - Avoid icons whose dominant color visually disappears into their container. Readability is more important than decorative palette matching.
    - Icon identity/content should remain usable after theme switching; do not depend on a specific page background to make the icon visible.

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
    - Choose an emoji with a strong visible silhouette and enough intrinsic color contrast for both light and dark theme contexts.
    - Avoid predominantly white or near-white emoji/icon choices that can disappear on white cards.
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

    private function statsAnimatedCountersPremiumSchema(): string { return "\n    stats_animated_counters_premium\n\n    - type = stats_animated_counters_premium\n    - theme = auto\n    - eyebrow\n    - heading\n    - text\n    - metrics (array of exactly 4 items: value, label, description)\n\n    Requirements:\n    - Generate exactly 4 concise metrics.\n    - Never invent company statistics, percentages, customer counts, years, revenue, certifications, or performance claims.\n    - If verified values are not supplied, use clearly editable placeholders such as — instead of presenting sample numbers as facts.\n    - Do not include markdown.\n"; }
    private function statsRevenueDashboardPremiumSchema(): string { return "\n    stats_revenue_dashboard_premium\n\n    - type = stats_revenue_dashboard_premium\n    - theme = auto\n    - eyebrow\n    - heading\n    - text\n    - period_label\n    - metrics (array of exactly 4 items: value, label, description)\n\n    Requirements:\n    - Financial/revenue figures must come directly from supplied user data.\n    - Never invent revenue, MRR/ARR, order value, margins, growth, customer counts, forecasts, or commercial performance.\n    - When figures are unavailable use — and editable neutral labels.\n    - Do not include markdown.\n"; }
    private function statsGrowthChartsPremiumSchema(): string { return "\n    stats_growth_charts_premium\n\n    - type = stats_growth_charts_premium\n    - theme = auto\n    - eyebrow\n    - heading\n    - text\n    - chart_label\n    - series (array of exactly 6 items: label, value)\n    - metrics (array of exactly 3 items: value, label, description)\n\n    Requirements:\n    - Use chart values only when trend data is supplied by the user.\n    - value must be a numeric 0-100 display scale, not an invented business percentage.\n    - Never invent growth rates, retention, customer counts, financial results, or forecasts.\n    - If no verified data is supplied, use neutral editable placeholders for metrics and a non-factual sample display series.\n    - Do not include markdown.\n"; }
    private function statsAchievementsPremiumSchema(): string { return "\n    stats_achievements_premium\n\n    - type = stats_achievements_premium\n    - theme = auto\n    - eyebrow\n    - heading\n    - text\n    - achievements (array of exactly 4 items: year, badge, title, text)\n\n    Requirements:\n    - Only present awards, recognitions, dates, certifications, growth milestones, rankings, or outcomes that the user explicitly supplies.\n    - Otherwise use clearly editable placeholder milestones such as 20XX and generic titles.\n    - Never fabricate achievements.\n    - Do not include markdown.\n"; }
    private function statsGlobalPresencePremiumSchema(): string { return "\n    stats_global_presence_premium\n\n    - type = stats_global_presence_premium\n    - theme = auto\n    - eyebrow\n    - heading\n    - text\n    - locations (array of exactly 4 items: region, value, label, description)\n\n    Requirements:\n    - Use only offices, markets, countries, regions, service areas, location counts, or geographic reach explicitly supplied by the user.\n    - Never invent global reach, office locations, market counts, countries served, or geographic performance claims.\n    - If verified geographic facts are unavailable, use neutral placeholders such as Region 01 and —.\n    - Do not include markdown.\n"; }
    private function statsTimelineMetricsPremiumSchema(): string { return "\n    stats_timeline_metrics_premium\n\n    - type = stats_timeline_metrics_premium\n    - theme = auto\n    - eyebrow\n    - heading\n    - text\n    - periods (array of exactly 4 items: period, value, label, description)\n\n    Requirements:\n    - Use only dates, periods, metrics, milestones, and measured results explicitly supplied by the user.\n    - Never invent historical figures, growth percentages, revenue, customer counts, achievements, or dates.\n    - If verified timeline data is unavailable, use clearly editable placeholders such as 20XX and —.\n    - Do not include markdown.\n"; }

    private function teamCardsPremiumSchema(): string { return $this->premiumTeamSchema('team_cards_premium'); }
    private function teamTimelinePremiumSchema(): string { return $this->premiumTeamSchema('team_timeline_premium'); }
    private function teamOrgChartPremiumSchema(): string { return $this->premiumTeamSchema('team_org_chart_premium'); }
    private function teamLeadershipPremiumSchema(): string { return $this->premiumTeamSchema('team_leadership_premium'); }
    private function teamCulturePremiumSchema(): string { return "\n    team_culture_premium\n\n    - type = team_culture_premium\n    - theme = auto\n    - eyebrow\n    - heading\n    - text\n    - culture_label\n    - values (array of exactly 4 items: title, text)\n\n    Requirements:\n    - Generate exactly 4 concise culture principles.\n    - Do not invent employee satisfaction scores, awards, certifications, benefits, or workplace claims.\n    - Keep principles practical and editable when no company-specific culture facts are supplied.\n    - Do not include markdown.\n"; }
    private function teamOpenPositionsPremiumSchema(): string { return "\n    team_open_positions_premium\n\n    - type = team_open_positions_premium\n    - theme = auto\n    - eyebrow\n    - heading\n    - text\n    - positions_label\n    - primary_label\n    - primary_url\n    - positions (array of exactly 4 items: title, meta, location, summary)\n\n    Requirements:\n    - Use real openings only when the user supplies them.\n    - Otherwise use clearly editable placeholder roles rather than presenting them as active vacancies.\n    - Never invent salaries, benefits, hiring deadlines, employment terms, locations, visa support, or application guarantees.\n    - Do not include markdown.\n"; }
    private function premiumTeamSchema(string $type): string
    {
        $extra = match ($type) {
            'team_timeline_premium' => "\n    - timeline_label",
            'team_org_chart_premium' => "\n    - chart_label",
            'team_leadership_premium' => "\n    - lead_label",
            default => '',
        };
        return "\n    {$type}\n\n    - type = {$type}\n    - theme = auto\n    - eyebrow\n    - heading\n    - text{$extra}\n    - members (array of exactly 4 items)\n\n    Each member contains:\n    - name\n    - role\n    - bio\n    - image_url\n    - level\n\n    Requirements:\n    - Generate exactly 4 members.\n    - If real team information is not supplied, use clearly generic placeholder identities suitable for editing.\n    - Never invent credentials, awards, employment history, reporting relationships, tenure, qualifications, or personal achievements.\n    - For organization charts, use only hierarchy or reporting relationships explicitly supplied by the user; otherwise keep generic level labels only.\n    - Use existing avatar paths /storage/cms-images/avatars/avatar-1.jpg through avatar-4.jpg in order.\n    - Keep bios concise and professional.\n    - Do not include markdown.\n";
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


    private function ctaGlassPremiumSchema(): string { return $this->premiumCtaSchema('cta_glass_premium'); }
    private function ctaGradientPremiumSchema(): string { return $this->premiumCtaSchema('cta_gradient_premium'); }
    private function ctaNewsletterPremiumSchema(): string { return $this->premiumCtaSchema('cta_newsletter_premium', true); }
    private function ctaBookDemoPremiumSchema(): string { return $this->premiumCtaSchema('cta_book_demo_premium'); }
    private function ctaCalendlyPremiumSchema(): string { return $this->premiumCtaSchema('cta_calendly_premium'); }
    private function ctaFreeTrialPremiumSchema(): string { return $this->premiumCtaSchema('cta_free_trial_premium'); }
    private function ctaCountdownPremiumSchema(): string { return $this->premiumCtaSchema('cta_countdown_premium'); }
    private function ctaLimitedOfferPremiumSchema(): string { return $this->premiumCtaSchema('cta_limited_offer_premium'); }
    private function premiumCtaSchema(string $type, bool $newsletter = false): string
    {
        $extra = match ($type) {
            'cta_newsletter_premium' => "\n    - input_placeholder\n    - privacy_note",
            'cta_calendly_premium' => "\n    - booking_url\n    - availability_note\n    - duration_label",
            'cta_free_trial_premium' => "\n    - trial_note\n    - benefit_one\n    - benefit_two\n    - benefit_three",
            'cta_countdown_premium' => "\n    - countdown_deadline\n    - countdown_days\n    - countdown_hours\n    - countdown_minutes\n    - countdown_seconds\n    - deadline_note",
            'cta_limited_offer_premium' => "\n    - offer_badge\n    - offer_detail\n    - terms_note",
            default => "\n    - secondary_label\n    - secondary_url",
        };
        $primaryUrlField = $type === 'cta_calendly_premium' ? '' : "\n    - primary_url";
        return "
    {$type}
    - type = {$type}
    - theme = auto
    - eyebrow
    - heading
    - text
    - primary_label{$primaryUrlField}{$extra}

    Requirements:
    - Write concise conversion-focused CTA copy appropriate to the requested business.
    - Do not invent discounts, availability, response times, guarantees, booking slots, subscriber counts, or other claims not supplied by the user.
    - For newsletter CTAs, keep privacy copy generic and do not claim a sending frequency unless supplied.
    - For demo or Calendly CTAs, use a factual booking invitation and a placeholder URL when no scheduling URL is supplied; never invent available dates or times.
    - For free-trial CTAs, do not invent trial length, card requirements, cancellation terms, included credits, or billing behavior.
    - For countdown CTAs, only use a deadline supplied by the user. Return countdown_deadline as an ISO-8601 date/time when supplied. If none is supplied, use countdown_deadline = demo:+7d and clearly identify it as a demo placeholder; never manufacture urgency.
    - For limited-offer CTAs, do not invent discounts, savings, expiry dates, scarcity, quantities, eligibility, or guarantees.
    - Do not include markdown.
";
    }

    private function pricingComparisonPremiumSchema(): string { return $this->premiumPricingSchema('pricing_comparison_premium'); }
    private function pricingTogglePremiumSchema(): string { return $this->premiumPricingSchema('pricing_toggle_premium'); }
    private function pricingEnterprisePremiumSchema(): string { return $this->premiumPricingSchema('pricing_enterprise_premium'); }
    private function pricingCalculatorPremiumSchema(): string { return $this->premiumPricingSchema('pricing_calculator_premium'); }
    private function pricingCreditPremiumSchema(): string { return $this->premiumPricingSchema('pricing_credit_premium'); }
    private function pricingAgencyPremiumSchema(): string { return $this->premiumPricingSchema('pricing_agency_premium'); }
    private function pricingFeatureMatrixPremiumSchema(): string { return $this->premiumPricingSchema('pricing_feature_matrix_premium'); }
    private function premiumPricingSchema(string $type): string
    {
        return "
    {$type}
    - type = {$type}
    - theme = auto
    - eyebrow
    - heading
    - text

    Requirements:
    - Generate concise premium pricing copy appropriate to the requested business.
    - Never invent discounts, guarantees, SLAs, savings percentages, compliance claims, or exact prices unless supplied by the user.
    - If pricing is unknown, use clearly editable placeholder values or Custom pricing.
    - Keep calls to action factual and concise.
    - Do not include markdown.
";
    }

    private function testimonialsVideoPremiumSchema(): string { return $this->premiumTestimonialsSchema('testimonials_video_premium', true); }
    private function testimonialsScrollingMarqueeSchema(): string { return $this->premiumTestimonialsSchema('testimonials_scrolling_marquee'); }
    private function testimonialsWallOfLoveSchema(): string { return $this->premiumTestimonialsSchema('testimonials_wall_of_love'); }
    private function testimonialsCardStackSchema(): string { return $this->premiumTestimonialsSchema('testimonials_card_stack'); }
    private function testimonialsTrustDashboardSchema(): string { return $this->premiumTestimonialsSchema('testimonials_trust_dashboard'); }
    private function testimonialsReviewGridSchema(): string { return $this->premiumTestimonialsSchema('testimonials_review_grid'); }
    private function testimonialsReviewCarouselProSchema(): string { return $this->premiumTestimonialsSchema('testimonials_review_carousel_pro'); }
    private function premiumTestimonialsSchema(string $type, bool $video = false): string
    {
        $videoFields = $video ? "\n    - video_url (optional; only use a URL supplied by the user)\n    - video_label" : '';
        return "\n    {$type}\n    - type = {$type}\n    - theme = auto\n    - eyebrow\n    - heading\n    - text{$videoFields}\n    - testimonials (array of exactly 4 items)\n\n    Each testimonial contains: avatar, name, company, quote.\n    Requirements:\n    - Use the four existing avatar paths /storage/cms-images/avatars/avatar-1.jpg through avatar-4.jpg.\n    - Never invent a real customer endorsement. If no verified testimonial copy is supplied in the prompt, clearly write Sample review placeholder copy that the website owner must replace before publishing.\n    - Do not invent company names as if they are verified customers; placeholder examples must remain obviously generic.\n    - Keep quotes concise and natural.\n";
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

    private function heroLuxuryFullscreenSchema(): string
    {
        return <<<TXT

    hero_luxury_fullscreen

    - type = hero_luxury_fullscreen
    - theme = auto
    - eyebrow
    - heading
    - text
    - primary_label
    - primary_url
    - secondary_label
    - secondary_url
    - location_label
    - edition_label
    - image_url

    Requirements:

    - Write a cinematic, premium full-screen hero for the supplied business and page context.
    - Keep eyebrow, location_label, and edition_label concise and refined.
    - heading must be distinctive and suitable for a luxury or premium brand.
    - text should be one short supporting paragraph.
    - primary_label and secondary_label must be clear calls to action.
    - primary_url and secondary_url must be valid URLs or #.
    - image_url must be an empty string so the application image pipeline can assign the image.
    - Do not invent awards, rankings, dates, addresses, or unsupported claims.

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

    private function heroAiConversationSchema(): string
    {
        return <<<TXT

    hero_ai_conversation

    - type = hero_ai_conversation
    - theme = auto
    - eyebrow
    - heading
    - text
    - primary_label
    - primary_url
    - secondary_label
    - secondary_url
    - assistant_label
    - assistant_status
    - user_message
    - assistant_message
    - prompt_placeholder
    - chip_one
    - chip_two
    - chip_three

    Requirements:

    - Write confident, concise copy for a conversational AI or automation product.
    - The user_message should sound like a realistic customer request.
    - The assistant_message should explain a useful next step without promising unsupported capabilities.
    - Keep assistant_status and proof chips short.
    - Do not invent customer counts, awards, or performance claims.
    - Do not use markdown or placeholder copy.

    TXT;
    }


    private function heroBentoPremiumSchema(): string
    {
        return <<<TXT

    hero_bento_premium

    - type = hero_bento_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - primary_label
    - primary_url
    - secondary_label
    - secondary_url
    - image_url = ""
    - image_label
    - metric_value
    - metric_label
    - proof_title
    - proof_text
    - card_one_label
    - card_two_label
    - card_three_label

    Requirements:

    - Write concise, premium copy for an asymmetrical bento-style hero.
    - Keep the three card labels short and complementary.
    - The metric must be clearly editable starter content, not presented as a verified claim.
    - Do not invent awards, clients, or guaranteed outcomes.
    - Leave image_url empty so the image-selection flow can populate it.
    - Do not use markdown or placeholder copy.

    TXT;
    }


    private function heroAgencyShowcaseSchema(): string
    {
        return <<<TXT

    hero_agency_showcase

    - type = hero_agency_showcase
    - theme = auto
    - eyebrow
    - heading
    - text
    - primary_label
    - primary_url
    - secondary_label
    - secondary_url
    - before_label
    - before_caption
    - after_label
    - after_caption
    - metric_one_value
    - metric_one_label
    - metric_two_value
    - metric_two_label
    - metric_three_value
    - metric_three_label
    - logo_one
    - logo_two
    - logo_three
    - logo_four
    - before_image_url = ""
    - after_image_url = ""

    Requirements:

    - Write polished, concise copy for a creative, digital, branding, web, or performance agency.
    - Frame the before/after captions as a transformation in clarity or experience, not an unsupported factual claim.
    - Metrics must remain editable starter examples unless the user supplied verified figures.
    - Client logo labels must be neutral placeholders unless real client names were provided.
    - Keep both calls to action practical and specific.
    - Do not invent awards, named clients, revenue, or guaranteed results.
    - Leave image URLs empty so the approved image-selection flow can fill both visuals.
    - Do not use markdown or placeholder copy.

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


    private function contactSplitPremiumSchema(): string { return $this->premiumContactSchema('contact_split_premium'); }
    private function contactMapPremiumSchema(): string { return $this->premiumContactSchema('contact_map_premium'); }
    private function contactAppointmentPremiumSchema(): string { return $this->premiumContactSchema('contact_appointment_premium'); }
    private function contactSupportCenterPremiumSchema(): string { return $this->premiumContactSchema('contact_support_center_premium'); }
    private function contactFaqPremiumSchema(): string { return $this->premiumContactSchema('contact_faq_premium'); }
    private function contactMultiStepPremiumSchema(): string { return $this->premiumContactSchema('contact_multistep_premium'); }
    private function contactLiveChatPremiumSchema(): string { return $this->premiumContactSchema('contact_live_chat_premium'); }
    private function blogMagazinePremiumSchema(): string { return $this->premiumBlogSchema('blog_magazine_premium'); }
    private function blogFeaturedArticlePremiumSchema(): string { return $this->premiumBlogSchema('blog_featured_article_premium'); }
    private function blogEditorsPickPremiumSchema(): string { return $this->premiumBlogSchema('blog_editors_pick_premium'); }
    private function blogSidebarNewsPremiumSchema(): string { return $this->premiumBlogSchema('blog_sidebar_news_premium'); }
    private function blogNewsletterPremiumSchema(): string { return $this->premiumBlogSchema('blog_newsletter_premium'); }
    private function blogTrendingPremiumSchema(): string { return $this->premiumBlogSchema('blog_trending_premium'); }
    private function blogCategoriesGridPremiumSchema(): string { return $this->premiumBlogSchema('blog_categories_grid_premium'); }
    private function blogAuthorProfilePremiumSchema(): string { return $this->premiumBlogSchema('blog_author_profile_premium'); }
    private function premiumFooterSchema(string $type): string
    {
        $extra = match ($type) {
            default => "\n    - eyebrow\n    - heading\n    - location\n    - social_one\n    - social_two",
        };
        return "\n    {$type}\n\n    - type = {$type}\n    - theme = auto\n    - brand_name\n    - tagline\n    - primary_label\n    - primary_url\n    - link_one_label\n    - link_one_url\n    - link_two_label\n    - link_two_url\n    - link_three_label\n    - link_three_url{$extra}\n\n    Requirements:\n    - Use only business names, contact details, locations, social channels, legal wording, and navigation destinations supplied by the user or existing site context.\n    - Never invent addresses, email addresses, social handles, legal/company registration details, awards, availability, or guarantees.\n    - Keep footer copy concise and suitable for a site-wide closing section.\n    - Do not use markdown.\n";
    }
    private function premiumBlogSchema(string $type): string
    {
        $fields = match ($type) {
            'blog_magazine_premium' => "\n    - feature_title\n    - feature_excerpt\n    - feature_image_url = \"\"\n    - story_one_title\n    - story_one_meta\n    - story_two_title\n    - story_two_meta\n    - story_three_title\n    - story_three_meta",
            'blog_featured_article_premium' => "\n    - feature_category\n    - feature_title\n    - feature_excerpt\n    - feature_image_url = \"\"\n    - author_line",
            'blog_editors_pick_premium' => "\n    - pick_title\n    - pick_excerpt\n    - pick_image_url = \"\"\n    - item_one\n    - item_two\n    - item_three",
            'blog_newsletter_premium' => "\n    - newsletter_note\n    - topic_one\n    - topic_two\n    - topic_three",
            'blog_trending_premium' => "\n    - item_one_title\n    - item_one_meta\n    - item_two_title\n    - item_two_meta\n    - item_three_title\n    - item_three_meta\n    - item_four_title\n    - item_four_meta",
            'blog_categories_grid_premium' => "\n    - category_one\n    - category_one_text\n    - category_two\n    - category_two_text\n    - category_three\n    - category_three_text\n    - category_four\n    - category_four_text",
            'blog_author_profile_premium' => "\n    - author_name\n    - author_role\n    - author_bio\n    - author_image_url = \"\"\n    - specialty_one\n    - specialty_two\n    - specialty_three",
            default => "\n    - lead_title\n    - lead_excerpt\n    - lead_image_url = \"\"\n    - news_one_title\n    - news_one_meta\n    - news_two_title\n    - news_two_meta\n    - topic_one\n    - topic_two\n    - topic_three",
        };
        return "\n    {$type}\n\n    - type = {$type}\n    - theme = auto\n    - eyebrow\n    - heading\n    - text\n    - primary_label\n    - primary_url{$fields}\n\n    Requirements:\n    - Write concise editorial copy based only on supplied business context.\n    - Do not invent publication dates, authors, trending status, readership, subscriber counts, sending frequency, awards, or performance claims.
    - For Author Profile, use identity/role/biography details only when supplied; otherwise keep neutral editable placeholders.
    - For Trending, treat items as curated/featured unless genuine popularity data is supplied.\n    - Leave image URL fields empty so the image-selection flow can populate them.\n    - Do not use markdown.\n";
    }
    private function premiumContactSchema(string $type): string
    {
        $extra = match ($type) {
            'contact_map_premium' => "\n    - map_label\n    - directions_label\n    - directions_url",
            'contact_appointment_premium' => "\n    - booking_url\n    - appointment_note\n    - duration_label",
            'contact_support_center_premium' => "\n    - support_one_title\n    - support_one_text\n    - support_two_title\n    - support_two_text\n    - support_three_title\n    - support_three_text",
            'contact_faq_premium' => "\n    - faq_one_question\n    - faq_one_answer\n    - faq_two_question\n    - faq_two_answer\n    - faq_three_question\n    - faq_three_answer",
            'contact_multistep_premium' => "\n    - step_one_title\n    - step_one_text\n    - step_two_title\n    - step_two_text\n    - step_three_title\n    - step_three_text\n    - submit_label",
            'contact_live_chat_premium' => "\n    - chat_url\n    - chat_note\n    - secondary_label\n    - secondary_url",
            default => '',
        };
        return "
    {$type}
    - type = {$type}
    - theme = auto
    - eyebrow
    - heading
    - text
    - email
    - phone
    - address
    - primary_label
    - primary_url{$extra}
    - for contact_split_premium, contact_faq_premium, and contact_multistep_premium: fields (array of 3 to 7 structured contact fields using id, name, type, label, placeholder, required, options)

    Requirements:
    - Use factual, concise contact copy appropriate to the requested business.
    - Never invent physical addresses, phone numbers, office hours, response times, support SLAs, booking availability, appointment duration, map coordinates, or staff availability.
    - Use clearly editable placeholder contact details when the user has not supplied real details.
    - For appointment booking, use https://calendly.com/ as the editable demo URL when none is supplied and explicitly avoid fabricated dates or times.
    - For map contact, do not invent coordinates or map embeds; use a supplied directions URL or https://www.google.com/maps/search/?api=1&query=Your+Business+Location as the editable demo placeholder.
    - For FAQ + Contact, answer only generic process questions unless the user supplied business-specific facts; do not invent policies, guarantees, pricing, or turnaround times.
    - For Multi-step Contact, keep step labels concise and do not imply the form is submitted anywhere beyond the configured contact endpoint.
    - For Live Chat CTA, never claim staff are online, available now, or will reply within a time window unless the user explicitly supplied that fact. Use https://www.messenger.com/ as the editable demo chat URL when none is supplied.
    - Do not include markdown.
";
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

    private function faqAccordionProSchema(): string
    {
        return $this->faqBasePremiumSchema('faq_accordion_pro', 'Generate exactly 6 useful FAQs. Use concise, factual answers and a premium editorial tone.');
    }

    private function faqSearchPremiumSchema(): string
    {
        return $this->faqBasePremiumSchema('faq_search_premium', 'Generate exactly 8 useful FAQs plus search_placeholder. Questions should cover distinct visitor intents so search is genuinely useful.', true);
    }

    private function faqCategoriesPremiumSchema(): string
    {
        return <<<TXT

    faq_categories_premium

    - type = faq_categories_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - categories (array of exactly 3 items)

    Each category contains:
    - title
    - description
    - faqs (array of exactly 2 items, each with question and answer)

    Requirements:
    - Build three genuinely distinct, useful topic groups for the requested business.
    - Answers must be concise, factual, and based only on supplied business information or safe general process wording.
    - Never invent prices, guarantees, certifications, response times, availability, policies, legal terms, or regulated claims.
    - Do not use markdown or fake business facts.

    TXT;
    }

    private function faqSupportPortalPremiumSchema(): string
    {
        return <<<TXT

    faq_support_portal_premium

    - type = faq_support_portal_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - primary_label
    - primary_url
    - topics (array of exactly 4 items: title, description, count_label)
    - faqs (array of exactly 4 items: question, answer)

    Requirements:
    - Create four useful self-service support topics and four featured questions.
    - primary_url must use a real supplied support/contact URL; if none is supplied use # rather than inventing one.
    - Never invent support availability, response times, SLAs, ticket counts, documentation counts, guarantees, policies, or account features.
    - Keep count_label qualitative (for example Guide, How-to, Account, Policy) unless a real count is supplied.
    - Do not use markdown.

    TXT;
    }


    private function faqDocumentationPremiumSchema(): string
    {
        return <<<TXT

    faq_documentation_premium

    - type = faq_documentation_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - primary_label
    - primary_url
    - topics (array of exactly 4 items: title, text)
    - featured_title
    - featured_text
    - steps (array of exactly 3 items: title, text)

    Requirements:
    - Build documentation only from supplied product, service, onboarding, process, policy, or help information.
    - primary_url must use a real supplied URL or #.
    - Never invent API features, setup steps, policies, support guarantees, availability, response times, account capabilities, or technical requirements.
    - Keep topics and steps clear, useful, and factual.
    - Do not use markdown.

    TXT;
    }

    private function leadMagnetPremiumSchema(): string
    {
        return <<<TXT

    lead_magnet_premium

    - type = lead_magnet_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - resource_label
    - resource_title
    - resource_text
    - primary_label
    - primary_url
    - benefits (array of exactly 3 items: title, text)

    Requirements:
    - Use a real supplied resource, guide, checklist, template, report, download, or offer. If none exists, keep the content clearly placeholder-like instead of inventing an asset.
    - Never invent download counts, results, guarantees, scarcity, testimonials, proprietary research, or claims about what the resource contains.
    - primary_url must be supplied or #.
    - Do not use markdown.

    TXT;
    }

    private function leadFreeAuditPremiumSchema(): string
    {
        return <<<TXT

    lead_free_audit_premium

    - type = lead_free_audit_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - offer_label
    - primary_label
    - primary_url
    - items (array of exactly 3 items: title, text)
    - note

    Requirements:
    - Call the audit free only when the supplied business information explicitly says a free audit is offered.
    - Describe only supplied or safely generic audit areas; do not invent eligibility, turnaround times, deliverables, scores, guarantees, savings, or findings.
    - primary_url must be supplied or #.
    - Do not use fake urgency or markdown.

    TXT;
    }

    private function leadWebsiteAuditPremiumSchema(): string
    {
        return <<<TXT

    lead_website_audit_premium

    - type = lead_website_audit_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - primary_label
    - primary_url
    - audit_items (array of exactly 4 items: title, text)
    - report_label
    - report_title
    - report_text

    Requirements:
    - Present the website audit as an offer or methodology unless an actual audit result was supplied.
    - Never fabricate performance scores, SEO rankings, accessibility results, conversion rates, vulnerabilities, scan results, revenue impact, or before/after claims.
    - primary_url must be supplied or #.
    - Do not use markdown.

    TXT;
    }

    private function leadQuoteFormPremiumSchema(): string
    {
        return <<<TXT

    lead_quote_form_premium

    - type = lead_quote_form_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - primary_label
    - primary_url
    - items (array of exactly 3 items: title, text)
    - form_note

    Requirements:
    - Present this as a quote request, not an instant or guaranteed final price unless the supplied business information explicitly supports that workflow.
    - Ask only for useful project, scope, quantity, timing, or service details.
    - Never invent pricing, discounts, turnaround times, availability, minimum spend, deposits, or guarantees.
    - primary_url must be supplied or #.
    - Do not use markdown.

    TXT;
    }

    private function leadRoiCalculatorPremiumSchema(): string
    {
        return <<<TXT

    lead_roi_calculator_premium

    - type = lead_roi_calculator_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - input_one_label
    - input_two_label
    - input_three_label
    - result_label
    - note

    Requirements:
    - Treat all ROI output as illustrative estimates based only on visitor-entered assumptions.
    - Never invent revenue, savings, growth percentages, conversion lifts, benchmarks, guarantees, or expected returns.
    - Use neutral labels that fit the requested business model.
    - note must clearly state that actual results can vary.
    - Do not use markdown.

    TXT;
    }

    private function leadCostCalculatorPremiumSchema(): string
    {
        return <<<TXT

    lead_cost_calculator_premium

    - type = lead_cost_calculator_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - quantity_label
    - rate_label
    - result_label
    - note

    Requirements:
    - Present calculator output as a rough estimate, not a confirmed quote.
    - Never invent unit prices, taxes, shipping, labour costs, fees, discounts, minimums, or final totals unless explicitly supplied.
    - Keep labels generic enough for visitor-entered quantity and rate values.
    - note must explain that final pricing depends on real scope and terms.
    - Do not use markdown.

    TXT;
    }

    private function leadConsultationBookingPremiumSchema(): string
    {
        return <<<TXT

    lead_consultation_booking_premium

    - type = lead_consultation_booking_premium
    - theme = auto
    - eyebrow
    - heading
    - text
    - primary_label
    - primary_url
    - items (array of exactly 3 items: title, text)
    - note

    Requirements:
    - Present this as a consultation request unless a real connected scheduler and confirmed booking flow are supplied.
    - Never invent available slots, dates, duration, fees, response times, staff availability, or automatic confirmation.
    - primary_url must be supplied or #.
    - note should clarify that a request is not confirmed until the business or connected scheduler confirms it.
    - Do not use markdown.

    TXT;
    }

    private function salesComparisonPremiumSchema(): string { return $this->salesBasePremiumSchema('sales_comparison_premium', 'Compare only supplied options, features, terms, or criteria. Never invent pricing, guarantees, limits, or superiority claims.'); }

    private function salesFeatureMatrixPremiumSchema(): string { return $this->salesBasePremiumSchema('sales_feature_matrix_premium', 'Use only real supplied capabilities, plan inclusions, support details, or terms. Never fabricate checkmarks, limits, or availability.'); }

    private function salesCompetitorComparisonPremiumSchema(): string { return $this->salesBasePremiumSchema('sales_competitor_comparison_premium', 'Competitor claims must be factual, neutral, supportable, and supplied or verifiable. Never invent weaknesses, prices, market share, ratings, legal claims, or superiority.'); }

    private function salesRoiPremiumSchema(): string { return $this->salesBasePremiumSchema('sales_roi_premium', 'Use transparent supplied assumptions only. Never invent ROI percentages, revenue, savings, payback periods, conversion lifts, benchmarks, forecasts, or guarantees.'); }

    private function salesGuaranteePremiumSchema(): string { return $this->salesBasePremiumSchema('sales_guarantee_premium', 'Use only a real supplied guarantee, warranty, refund policy, assurance, or commitment. Never invent coverage, duration, remedies, exclusions, refunds, certifications, legal protections, or risk-free claims.'); }

    private function salesTrustPremiumSchema(): string { return $this->salesBasePremiumSchema('sales_trust_premium', 'Use only supplied or verified trust signals. Never invent certifications, compliance status, customer counts, review ratings, client logos, security claims, awards, insurance, guarantees, uptime, or response times.'); }

    private function salesIntegrationsPremiumSchema(): string { return $this->salesBasePremiumSchema('sales_integrations_premium', 'List only integrations, platforms, APIs, tools, or planned connections supplied by the business. Never imply native support, partnership, certification, or availability that was not supplied.'); }

    private function agencyDashboardPreviewPremiumSchema(): string { return $this->agencyBasePremiumSchema('agency_dashboard_preview_premium', 'Use only supplied agency metrics, website counts, workflow states, reporting capabilities, or management features. Never invent client counts, revenue, uptime, conversions, growth, or status data.'); }
    private function agencyClientPortalPremiumSchema(): string { return $this->agencyBasePremiumSchema('agency_client_portal_premium', 'Use only real supplied client portal capabilities. Never invent approvals, file sharing, billing, messaging, reporting, permissions, integrations, or access features.'); }
    private function agencyWhiteLabelShowcasePremiumSchema(): string { return $this->agencyBasePremiumSchema('agency_white_label_showcase_premium', 'Use only real supplied white-label capabilities. Never invent custom domains, branding controls, reseller rights, removal of vendor branding, client ownership, or plan availability.'); }
    private function agencyWebsiteManagementPremiumSchema(): string { return $this->agencyBasePremiumSchema('agency_website_management_premium', 'Use only real supplied multi-site management workflows and controls. Never invent monitoring, backups, security, analytics, publishing, permissions, maintenance, or reporting capabilities.'); }
    private function agencyMaintenancePlansPremiumSchema(): string { return $this->agencyBasePremiumSchema('agency_maintenance_plans_premium', 'Use only real supplied maintenance scope, cadence, inclusions, exclusions, and terms. Never invent pricing, SLA, uptime guarantees, included hours, response times, backups, security coverage, or update frequency.'); }
    private function agencySupportPlansPremiumSchema(): string { return $this->agencyBasePremiumSchema('agency_support_plans_premium', 'Use only real supplied support channels, coverage, escalation paths, and plan differences. Never invent support hours, response times, SLAs, priority levels, included hours, pricing, or guarantees.'); }
    private function agencyWorkflowPremiumSchema(): string { return $this->agencyBasePremiumSchema('agency_workflow_premium', 'Use only the real supplied agency process or clearly editable generic stages. Never invent delivery times, approval guarantees, revision counts, team roles, or client commitments.'); }
    private function agencyProjectPipelinePremiumSchema(): string { return $this->agencyBasePremiumSchema('agency_project_pipeline_premium', 'Use only supplied project stages or generic editable stage labels. Never invent client names, project counts, completion percentages, deadlines, budgets, current status, or launch dates.'); }
    private function agencyClientReviewsPremiumSchema(): string { return $this->agencyBasePremiumSchema('agency_client_reviews_premium', 'Use only genuine supplied client feedback. Never invent names, companies, quotes, star ratings, testimonials, outcomes, awards, review counts, or endorsements. If no verified review is supplied, use clearly editable placeholder wording rather than presenting it as a real review.'); }
    private function agencyWebsiteReportsPremiumSchema(): string { return $this->agencyBasePremiumSchema('agency_website_reports_premium', 'Use only real supplied reporting capabilities, metrics, data sources, maintenance activity, recommendations, and report cadence. Never invent traffic, conversions, uptime, rankings, revenue, security findings, client results, report frequency, or guaranteed improvements.'); }
    private function aiPromptShowcasePremiumSchema(): string { return $this->aiBasePremiumSchema('ai_prompt_showcase_premium', 'Use only safe example prompts or supplied real prompts. Never imply unsupported model abilities, autonomous actions, integrations, results, processing times, accuracy, or guarantees.'); }
    private function aiWorkflowPremiumSchema(): string { return $this->aiBasePremiumSchema('ai_workflow_premium', 'Describe only supported AI-assisted workflow stages. Never invent autonomous publishing, approvals, integrations, human review, processing time, accuracy, or guarantees.'); }
    private function aiAssistantPremiumSchema(): string { return $this->aiBasePremiumSchema('ai_assistant_premium', 'Use clearly illustrative dialogue or supplied real conversation content. Never fabricate customer conversations, personal data, actions performed, integrations used, availability, or guaranteed outcomes.'); }
    private function aiTimelinePremiumSchema(): string { return $this->aiBasePremiumSchema('ai_timeline_premium', 'Describe only supported generation stages. Never invent processing times, completion percentages, background work, autonomous actions, accuracy, performance, or guarantees.'); }
    private function aiBuilderPremiumSchema(): string { return $this->aiBasePremiumSchema('ai_builder_premium', 'Describe only real supported builder actions and controls. Never invent autonomous publishing, unavailable editing modes, integrations, generated assets, processing times, accuracy, or guarantees.'); }
    private function aiAutomationPremiumSchema(): string { return $this->aiBasePremiumSchema('ai_automation_premium', 'Use only real supported automations or clearly label planned/example workflows. Never invent triggers, external integrations, background jobs, notifications, approvals, autonomous actions, timing, or guarantees.'); }
    private function aiCreditsDashboardPremiumSchema(): string { return $this->aiBasePremiumSchema('ai_credits_dashboard_premium', 'Use only supplied real credit balances, costs, plan rules, purchase paths, or clearly editable sample values. Never invent account balances, reset cadence, discounts, billing terms, consumption rates, or entitlement claims.'); }
    private function aiGenerationProcessPremiumSchema(): string { return $this->aiBasePremiumSchema('ai_generation_process_premium', 'Describe only supported generation stages. Never invent live progress percentages, completion times, queued/background work, autonomous publishing, hidden agents, model accuracy, or guaranteed outcomes.'); }
    private function aiStatisticsPremiumSchema(): string { return $this->aiBasePremiumSchema('ai_statistics_premium', 'Use only supplied or verified AI usage, adoption, generation, efficiency, quality, or business metrics. Never invent totals, percentages, time saved, accuracy, success rates, cost savings, customer counts, benchmarks, forecasts, or performance improvements. If no real metric is supplied, keep values clearly editable or non-factual.'); }
    private function aiPromptExamplesPremiumSchema(): string { return $this->aiBasePremiumSchema('ai_prompt_examples_premium', 'Use clearly illustrative prompt examples unless the user supplied real prompts. Keep examples limited to supported capabilities. Never imply unsupported actions, integrations, autonomous publishing, guaranteed outputs, hidden data access, or capabilities not supplied.'); }


    private function aiBasePremiumSchema(string $type, string $rule): string
    {
        return "\n    {$type}\n\n    - type = {$type}\n    - theme = auto\n    - eyebrow\n    - heading\n    - text\n    - items (array of exactly 3 items: title, text)\n    - note\n\n    Requirements:\n    - {$rule}\n    - Keep AI language concrete, understandable, and tied to capabilities actually supplied.\n    - Example prompts/dialogue must remain clearly illustrative unless explicitly supplied as real.\n    - Do not use markdown or fake product facts.\n";
    }

    private function agencyBasePremiumSchema(string $type, string $rule): string
    {
        return "\n    {$type}\n\n    - type = {$type}\n    - theme = auto\n    - eyebrow\n    - heading\n    - text\n    - items (array of exactly 3 items: title, text)\n    - note\n\n    Requirements:\n    - {$rule}\n    - Keep wording suitable for an agency-facing website section.\n    - Do not use markdown or fake business facts.\n";
    }

    private function salesBasePremiumSchema(string $type, string $rule): string
    {
        return "\n    {$type}\n\n    - type = {$type}\n    - theme = auto\n    - eyebrow\n    - heading\n    - text\n    - items (array of exactly 3 items: title, text)\n    - note\n\n    Requirements:\n    - {$rule}\n    - Keep language clear and decision-oriented without fake urgency.\n    - Do not use markdown.\n";
    }

    private function faqBasePremiumSchema(string $type, string $extra, bool $search = false): string
    {
        $searchField = $search ? "\n    - search_placeholder" : '';
        return "\n    {$type}\n\n    - type = {$type}\n    - theme = auto\n    - eyebrow\n    - heading\n    - text{$searchField}\n    - faqs (array of question and answer items)\n\n    Requirements:\n    - {$extra}\n    - Keep questions relevant to the requested business, page, product, or service.\n    - Never invent awards, certifications, guarantees, exact policies, response times, pricing, availability, or regulated claims.\n    - Do not use markdown or fake business facts.\n";
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
    - directions_url

    Requirements:

    - Write concise location and visit guidance matching the requested business.
    - Do not invent a precise street address, landmark, or travel time when none was supplied.
    - Use a general service-area or appointment phrase when exact location details are unavailable.
    - If no real directions URL is supplied, use https://www.google.com/maps/search/?api=1&query=Your+Business+Location as the editable demo placeholder.
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

    private function dynamicBlogHubSchema(): string
    {
        return <<<TXT

    blog_hub
    - type = blog_hub
    - theme = auto
    - eyebrow
    - heading
    - text

    Requirements:
    - Write an editorial, business-relevant introduction.
    - Do not invent article counts, authors, dates, or published resources.

    TXT;
    }

    private function dynamicLatestResourcesSchema(): string
    {
        return <<<TXT

    latest_resources
    - type = latest_resources
    - theme = auto
    - eyebrow
    - heading
    - text

    Requirements:
    - Write a concise introduction to the resource area.
    - Do not invent specific downloadable assets or publication claims.

    TXT;
    }

    private function dynamicCommerceProductGridSchema(): string
    {
        return <<<TXT

    commerce_product_grid
    - type = commerce_product_grid
    - theme = auto
    - heading
    - text
    - limit = 8

    Requirements:
    - Introduce the product collection without inventing products, prices, stock, discounts, or guarantees.
    - Keep limit at 8.

    TXT;
    }

    private function dynamicCommerceBenefitsStripSchema(): string
    {
        return <<<TXT

    commerce_benefits_strip
    - type = commerce_benefits_strip
    - theme = auto
    - heading
    - text

    Requirements:
    - Use neutral shopping reassurance.
    - Never invent shipping times, returns policies, warranties, payment terms, or guarantees.

    TXT;
    }


}
