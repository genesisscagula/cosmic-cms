<?php

namespace App\Services;

/**
 * Deterministic first-pass matcher for the registered Cosmic Spark library.
 *
 * This service never calls an AI model. It scans SparkCatalog metadata locally,
 * applies hard semantic/media mismatch penalties, and returns a compact ranked
 * shortlist that Luna can inspect in a later step.
 */
class SparkIntentMatcherService
{
    private const VERSION = 2;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function match(string $prompt, int $limit = 8, array $context = []): array
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            return [];
        }

        $limit = max(1, min($limit, 28));
        $analysis = $this->analyze($prompt, $context);
        $ranked = [];
        $allowedSparkKeys = array_key_exists('allowed_spark_keys', $context)
            ? array_flip(array_values(array_filter(array_map('strval', (array) $context['allowed_spark_keys']))))
            : null;

        foreach (SparkCatalog::all() as $spark) {
            if (! is_array($spark) || empty($spark['key'])) {
                continue;
            }
            if (is_array($allowedSparkKeys) && ! isset($allowedSparkKeys[(string) $spark['key']])) {
                continue;
            }

            $ranked[] = $this->scoreSpark($spark, $analysis);
        }

        usort($ranked, static function (array $a, array $b): int {
            return ($b['raw_score'] <=> $a['raw_score'])
                ?: ($b['score'] <=> $a['score'])
                ?: strcmp((string) $a['name'], (string) $b['name']);
        });

        return array_slice($ranked, 0, $limit);
    }

    /**
     * Compact catalog rows suitable for a later Luna ranking/planning call.
     * The complete 329 Spark schemas never need to be sent to the model.
     *
     * @return array<int, array<string, mixed>>
     */
    public function shortlistForAi(string $prompt, int $limit = 10, array $context = []): array
    {
        return array_map(static fn (array $row): array => [
            'id' => $row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'semantic_type' => $row['semantic_type'],
            'media' => $row['media'],
            'layout' => $row['layout'],
            'style_traits' => $row['style_traits'],
            'visual_traits' => $row['visual_traits'],
            'industry_fit' => $row['industry_fit'],
            'intent' => $row['intent'],
            'capabilities' => $row['capabilities'],
            'terms' => $row['terms'],
            'score' => $row['score'],
            'raw_score' => $row['raw_score'],
            'confidence' => $row['confidence'],
            'reason' => $row['reason'],
        ], $this->match($prompt, $limit, $context));
    }

    /**
     * Public analysis is useful to Batch 3 without spending another AI call.
     *
     * @return array<string, mixed>
     */
    public function analyze(string $prompt, array $context = []): array
    {
        $normalized = $this->normalize($prompt);
        $tokens = $this->tokens($normalized);

        $semantic = $this->detectGroups($normalized, [
            'hero' => ['hero','masthead','banner','above the fold','opening section','page intro'],
            'services' => ['service','services','offerings','solutions','what we do','treatments'],
            'features' => ['feature','features','benefits','capabilities'],
            'about' => ['about','our story','company story','brand story','mission'],
            'cta' => ['cta','call to action','conversion banner'],
            'pricing' => ['pricing','price plans','packages','plans','membership plans'],
            'testimonials' => ['testimonial','testimonials','reviews','customer stories','client quotes'],
            'team' => ['team','staff','leadership','our people'],
            'contact' => ['contact','get in touch','inquiry','enquiry','contact form'],
            'faq' => ['faq','frequently asked questions','questions accordion'],
            'lead' => ['lead capture','lead form','signup form','opt in'],
            'portfolio' => ['portfolio','projects','selected work','work showcase','property listing'],
            'gallery' => ['gallery','image gallery','photo gallery'],
            'case-studies' => ['case study','case studies','success stories'],
            'proof' => ['proof','stats','statistics','metrics','trust','results','numbers'],
            'process' => ['process','steps','workflow','how it works','timeline','itinerary'],
            'content' => ['blog','posts','articles','resources','newsletter','updates'],
            'commerce' => ['shop','store','products','catalog','commerce','cart','checkout'],
            'footer' => ['footer'],
        ]);

        $media = $this->detectGroups($normalized, [
            'slider' => ['slider','carousel','slideshow','rotating slides','slide show'],
            'video' => ['video','background video','video background','cinematic video'],
            'gallery' => ['gallery','photo gallery','image gallery','mosaic gallery'],
            'image' => ['image','photo','picture','background image','image background'],
            'map' => ['map','location map'],
        ]);

        $layout = $this->detectGroups($normalized, [
            'full-bleed' => ['full bleed','full width','fullscreen','full screen','edge to edge'],
            'split' => ['split','two column','two columns','2 column','2 columns','side by side'],
            'bento' => ['bento'],
            'grid' => ['grid','card grid'],
            'cards' => ['cards','card layout'],
            'centered' => ['centered','centre aligned','center aligned'],
            'editorial' => ['editorial'],
            'horizontal' => ['horizontal','rail'],
            'timeline' => ['timeline'],
            'accordion' => ['accordion'],
            'mosaic' => ['mosaic','masonry'],
            'gallery' => ['gallery','photo gallery','image gallery'],
        ]);

        $style = $this->detectGroups($normalized, [
            'premium' => ['premium','high end','high-end'],
            'luxury' => ['luxury','luxurious'],
            'minimal' => ['minimal','minimalist','clean'],
            'bold' => ['bold','strong'],
            'editorial' => ['editorial','magazine'],
            'glass' => ['glass','glassmorphism'],
            'modern' => ['modern','contemporary'],
            'cinematic' => ['cinematic','dramatic'],
        ]);

        $industry = $this->detectGroups($normalized, [
            'hospitality' => ['hotel','resort','hospitality','travel','destination','room booking'],
            'real-estate' => ['real estate','realestate','property','realtor','listing','apartment'],
            'construction' => ['construction','builder','contractor','architecture','architect'],
            'agency' => ['agency','creative studio','design studio','marketing agency'],
            'technology' => ['saas','software','technology','tech company','ai product'],
            'ecommerce' => ['ecommerce','e-commerce','online shop','online store'],
            'restaurant' => ['restaurant','cafe','coffee shop','food','dining','menu'],
            'dental' => ['dental','dentist','dental clinic'],
            'medical' => ['medical','healthcare','clinic','hospital'],
            'finance' => ['finance','financial','loan','mortgage','banking'],
            'legal' => ['lawyer','legal','attorney','law firm'],
        ]);

        foreach (['semantic_type' => 'semantic', 'media' => 'media', 'layout' => 'layout', 'style' => 'style', 'industry' => 'industry'] as $contextKey => $bucket) {
            if (! isset($context[$contextKey])) {
                continue;
            }
            $values = is_array($context[$contextKey]) ? $context[$contextKey] : [$context[$contextKey]];
            foreach ($values as $value) {
                $value = $this->normalize((string) $value);
                if ($value !== '' && ! in_array($value, ${$bucket}, true)) {
                    ${$bucket}[] = $value;
                }
            }
        }

        $intent = array_values(array_unique(array_filter([
            array_intersect($semantic, ['cta','lead','contact','pricing','commerce']) ? 'conversion' : null,
            array_intersect($semantic, ['testimonials','proof','case-studies']) ? 'proof' : null,
            array_intersect($semantic, ['about','team','content']) ? 'storytelling' : null,
            array_intersect($semantic, ['services','features','portfolio','gallery','commerce']) ? 'showcase' : null,
            in_array('hero', $semantic, true) ? 'first impression' : null,
            array_intersect($media, ['slider','gallery','video']) ? 'visual storytelling' : null,
        ])));

        return [
            'version' => self::VERSION,
            'prompt' => $prompt,
            'normalized' => $normalized,
            'tokens' => $tokens,
            'semantic' => array_values(array_unique($semantic)),
            'media' => array_values(array_unique($media)),
            'layout' => array_values(array_unique($layout)),
            'style' => array_values(array_unique($style)),
            'industry' => array_values(array_unique($industry)),
            'intent' => $intent,
        ];
    }

    /** @return array<string, mixed> */
    private function scoreSpark(array $spark, array $analysis): array
    {
        $semantic = $this->normalize((string) ($spark['semantic_type'] ?? 'general'));
        $media = $this->normalize((string) ($spark['media'] ?? 'mixed'));
        $layout = $this->normalizeList($spark['layout'] ?? []);
        $style = $this->normalizeList(array_merge((array) ($spark['style'] ?? []), (array) ($spark['style_traits'] ?? [])));
        $industry = $this->normalizeList($spark['industry_fit'] ?? []);
        $intent = $this->normalizeList($spark['intent'] ?? []);
        $capabilities = $this->normalizeList($spark['capabilities'] ?? []);
        $aliases = $this->normalizeList($spark['aliases'] ?? []);
        $searchTerms = $this->normalizeList($spark['search_terms'] ?? []);
        $visualTraits = $this->normalizeList($spark['visual_traits'] ?? []);
        $name = (string) ($spark['name'] ?? $spark['key']);
        $id = (string) $spark['key'];

        $raw = 0;
        $reasons = [];
        $penalties = [];

        if ($analysis['semantic'] !== []) {
            $semanticCompatible = in_array($semantic, $analysis['semantic'], true)
                || (in_array('gallery', $analysis['semantic'], true) && in_array('gallery', $layout, true))
                || (in_array('portfolio', $analysis['semantic'], true) && in_array('portfolio', $intent, true))
                || (in_array('services', $analysis['semantic'], true) && in_array('services', $intent, true));

            if (in_array($semantic, $analysis['semantic'], true)) {
                $raw += 44;
                $reasons[] = "{$semantic} section";
            } elseif ($semanticCompatible) {
                $raw += 30;
                $reasons[] = 'semantic-compatible layout';
            } else {
                $raw -= 34;
                $penalties[] = 'semantic mismatch';
            }
        }

        if ($analysis['media'] !== []) {
            $mediaMatch = in_array($media, $analysis['media'], true)
                || ($media === 'mixed' && array_intersect($analysis['media'], ['image']))
                || ($media === 'gallery' && in_array('image', $analysis['media'], true))
                || ($media === 'image' && in_array('gallery', $analysis['media'], true) && in_array('gallery', $layout, true));
            if ($mediaMatch) {
                $raw += 24;
                $reasons[] = "{$media} media";
            } else {
                $raw -= 18;
                $penalties[] = 'media mismatch';
            }
        }

        $layoutHits = array_values(array_intersect($layout, $analysis['layout']));
        if ($layoutHits !== []) {
            $raw += min(22, 12 + (count($layoutHits) - 1) * 5);
            $reasons[] = implode(', ', array_slice($layoutHits, 0, 2)).' layout';
        } elseif ($analysis['layout'] !== []) {
            $raw -= 7;
        }

        $styleHits = array_values(array_intersect($style, $analysis['style']));
        if ($styleHits !== []) {
            $raw += min(14, 8 + (count($styleHits) - 1) * 3);
            $reasons[] = implode(', ', array_slice($styleHits, 0, 2)).' style';
        }

        if ($analysis['industry'] !== []) {
            $industryHits = array_values(array_intersect($industry, $analysis['industry']));
            if ($industryHits !== []) {
                $raw += 22;
                $reasons[] = $industryHits[0].' fit';
            } elseif (in_array('universal', $industry, true)) {
                $raw += 2;
            } else {
                $raw -= 8;
                $penalties[] = 'industry mismatch';
            }
        }

        $intentHits = array_values(array_intersect($intent, $analysis['intent']));
        if ($intentHits !== []) {
            $raw += min(14, 8 + (count($intentHits) - 1) * 3);
        }

        $phrase = $analysis['normalized'];
        $aliasHits = 0;
        foreach ($aliases as $alias) {
            if (mb_strlen($alias) < 3) {
                continue;
            }
            if ($this->containsPhrase($phrase, $alias)) {
                $aliasHits++;
                $raw += $aliasHits <= 2 ? 13 : 4;
                if ($aliasHits <= 2) {
                    $reasons[] = "alias: {$alias}";
                }
            }
        }

        $tokenHaystack = $this->normalize(implode(' ', array_merge(
            [$id, $name, (string) ($spark['description'] ?? ''), $semantic, $media],
            $aliases,
            $searchTerms,
            $layout,
            $style,
            $visualTraits,
            $industry,
            $intent
        )));
        $tokenHits = 0;
        foreach ($analysis['tokens'] as $token) {
            if ($this->containsPhrase($tokenHaystack, $token)) {
                $tokenHits++;
            }
        }
        if ($tokenHits > 0) {
            $raw += min(20, $tokenHits * 3);
        }

        // Small deterministic bonuses for requested mechanics already declared safe.
        if (in_array('slider', $analysis['media'], true) && in_array('supports-slider', $capabilities, true)) {
            $raw += 6;
        }
        if (in_array('video', $analysis['media'], true) && in_array('supports-video', $capabilities, true)) {
            $raw += 6;
        }
        if (in_array('hero', $analysis['semantic'], true) && in_array('top', $this->normalizeList($spark['position_fit'] ?? []), true)) {
            $raw += 5;
        }

        $score = $this->normalizedScore($raw);
        $confidence = match (true) {
            $score >= 82 => 'high',
            $score >= 64 => 'medium',
            $score >= 45 => 'low',
            default => 'weak',
        };

        $reasons = array_values(array_unique($reasons));
        $reason = $reasons !== []
            ? 'Matched '.implode(' + ', array_slice($reasons, 0, 3)).'.'
            : 'Closest deterministic catalog match.';
        if ($penalties !== [] && $confidence === 'weak') {
            $reason .= ' '.implode(', ', array_unique($penalties)).'.';
        }

        return [
            'id' => $id,
            'name' => $name,
            'description' => (string) ($spark['description'] ?? ''),
            'score' => $score,
            'raw_score' => $raw,
            'confidence' => $confidence,
            'strong_match' => in_array($confidence, ['high','medium'], true),
            'reason' => $reason,
            'semantic_type' => $semantic,
            'media' => $media,
            'layout' => $layout,
            'style_traits' => $this->normalizeList($spark['style_traits'] ?? []),
            'visual_traits' => $visualTraits,
            'industry_fit' => $industry,
            'intent' => $intent,
            'capabilities' => $capabilities,
            'terms' => array_slice($searchTerms, 0, 32),
        ];
    }

    private function normalizedScore(int $raw): int
    {
        // 120 raw points is a near-perfect multi-signal match. Negative matches
        // deliberately collapse toward zero so wrong semantic types do not leak up.
        return max(0, min(100, (int) round(($raw / 120) * 100)));
    }

    /** @return array<int, string> */
    private function detectGroups(string $normalized, array $groups): array
    {
        $hits = [];
        foreach ($groups as $key => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->containsPhrase($normalized, $this->normalize($phrase))) {
                    $hits[] = $key;
                    break;
                }
            }
        }
        return $hits;
    }

    private function containsPhrase(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return false;
        }

        return str_contains(' '.$haystack.' ', ' '.$needle.' ')
            || str_contains($haystack, $needle);
    }

    /** @return array<int, string> */
    private function tokens(string $value): array
    {
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? '';
        $stop = ['the','a','an','and','or','for','to','of','with','in','on','my','me','i','we','our','website','site','page','template','spark','section','need','want','make','build','create','please','put','add','show'];
        $tokens = preg_split('/\s+/u', trim($value)) ?: [];

        return array_values(array_unique(array_filter($tokens, static fn (string $token): bool => mb_strlen($token) >= 2 && ! in_array($token, $stop, true))));
    }

    /** @return array<int, string> */
    private function normalizeList(mixed $values): array
    {
        $values = is_array($values) ? $values : [$values];
        $out = [];
        foreach ($values as $value) {
            if (! is_scalar($value)) {
                continue;
            }
            $value = $this->normalize((string) $value);
            if ($value !== '' && ! in_array($value, $out, true)) {
                $out[] = $value;
            }
        }
        return $out;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['_', '-'], ' ', $value);
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}
