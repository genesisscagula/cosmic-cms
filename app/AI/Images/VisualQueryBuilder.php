<?php

namespace App\AI\Images;

use Illuminate\Support\Str;

final class VisualQueryBuilder
{
    /**
     * Convert a long website brief into a concise stock-photo search query.
     *
     * The builder is deterministic on purpose: it does not add another AI call,
     * so image generation stays fast and inexpensive.
     */
    public function build(string $prompt, array $block = [], string $type = '', string $fallbackFolder = 'default'): string
    {
        $normalized = $this->normalize($prompt);
        $type = Str::lower(trim($type));
        $folder = Str::slug($fallbackFolder) ?: 'default';

        $subject = $this->subjectOverride($normalized)
            ?? $this->industrySubject($normalized, $folder)
            ?? $this->extractSubject($prompt);

        $location = $this->extractLocation($prompt, $normalized);
        $style = $this->styleCue($normalized);
        $intent = $this->blockIntent($type, $block, $normalized);

        $parts = array_filter([
            $style,
            $subject,
            $location,
            $intent,
        ]);

        return $this->compact(implode(' ', $parts));
    }

    private function subjectOverride(string $prompt): ?string
    {
        return match (true) {
            $this->containsAny($prompt, ['yacht', 'yachting', 'superyacht', 'private charter', 'yacht charter'])
                => 'luxury yacht Mediterranean sea',
            $this->containsAny($prompt, ['cruise ship', 'ocean cruise', 'mediterranean cruise', 'cruises'])
                => 'luxury Mediterranean cruise ship sea',
            $this->containsAny($prompt, ['sailboat', 'sailing charter', 'catamaran'])
                => 'luxury sailboat Mediterranean sea',
            $this->containsAny($prompt, ['sushi', 'omakase', 'japanese restaurant'])
                => 'Japanese sushi fine dining',
            $this->containsAny($prompt, ['coffee shop', 'specialty coffee', 'coffee roastery'])
                => 'specialty coffee cafe',
            $this->containsAny($prompt, ['dental clinic', 'dentist', 'orthodontist'])
                => 'modern dental clinic patient care',
            $this->containsAny($prompt, ['private medical clinic', 'medical clinic', 'family medicine'])
                => 'modern medical clinic patient care',
            $this->containsAny($prompt, ['luxury condominium', 'luxury apartment', 'property development'])
                => 'luxury residential architecture',
            $this->containsAny($prompt, ['ai website builder', 'website builder', 'saas platform', 'software platform'])
                => 'modern software product workspace',
            $this->containsAny($prompt, ['commercial construction', 'industrial construction', 'construction company'])
                => 'commercial construction site architecture',
            default => null,
        };
    }

    private function industrySubject(string $prompt, string $folder): ?string
    {
        $subjects = [
            'automotive' => 'professional automotive workshop vehicle',
            'bakery' => 'artisan bakery fresh pastries',
            'cleaning' => 'professional cleaning service interior',
            'coffee' => 'specialty coffee cafe',
            'construction' => 'commercial construction site architecture',
            'dentist' => 'modern dental clinic patient care',
            'education' => 'modern education classroom students',
            'electrician' => 'professional electrician at work',
            'finance' => 'financial advisor client meeting',
            'fitness' => 'premium fitness gym training',
            'hotel' => 'luxury hotel resort hospitality',
            'landscaping' => 'professional landscape garden design',
            'lawyer' => 'professional lawyer client meeting',
            'medical' => 'modern medical clinic patient care',
            'plumbing' => 'professional plumber at work',
            'real-estate' => 'premium real estate architecture',
            'restaurant' => 'premium restaurant dining experience',
            'roofing' => 'professional roofing contractor at work',
            'salon' => 'premium beauty salon client service',
            'technology' => 'modern software team product workspace',
            'travel' => 'premium travel destination experience',
        ];

        if (isset($subjects[$folder])) {
            return $subjects[$folder];
        }

        foreach ($subjects as $industry => $subject) {
            if (str_contains($prompt, str_replace('-', ' ', $industry))) {
                return $subject;
            }
        }

        return null;
    }

    private function blockIntent(string $type, array $block, string $prompt): string
    {
        $heading = $this->normalize((string) ($block['heading'] ?? $block['title'] ?? ''));
        $context = trim($type . ' ' . $heading);

        return match (true) {
            str_contains($type, 'hero') => 'wide cinematic exterior',
            str_contains($context, 'team') || str_contains($context, 'leadership') => 'authentic people portrait',
            str_contains($context, 'service') => 'service in action',
            str_contains($context, 'feature') => 'editorial lifestyle detail',
            str_contains($context, 'gallery') || str_contains($context, 'portfolio') => 'editorial portfolio photography',
            str_contains($context, 'case_stud') || str_contains($context, 'project') => 'completed project photography',
            str_contains($context, 'testimonial') => 'happy customer lifestyle',
            str_contains($context, 'process') => 'behind the scenes experience',
            str_contains($context, 'pricing') => 'premium customer experience',
            str_contains($context, 'contact') || str_contains($context, 'cta') => 'welcoming customer experience',
            $this->containsAny($prompt, ['yacht', 'cruise', 'sailing']) => 'cinematic ocean lifestyle',
            default => 'editorial photography',
        };
    }

    private function styleCue(string $prompt): ?string
    {
        return match (true) {
            $this->containsAny($prompt, ['luxury', 'exclusive', 'premium', 'high-end', 'high end']) => 'luxury',
            $this->containsAny($prompt, ['minimal', 'minimalist', 'clean']) => 'minimal',
            $this->containsAny($prompt, ['modern', 'contemporary']) => 'modern',
            $this->containsAny($prompt, ['rustic', 'artisan', 'handcrafted']) => 'artisan',
            $this->containsAny($prompt, ['energetic', 'bold', 'dynamic']) => 'dynamic',
            default => null,
        };
    }

    private function extractLocation(string $originalPrompt, string $normalized): ?string
    {
        if (preg_match('/^location:\s*([^\r\n]+)/mi', $originalPrompt, $matches) === 1) {
            return $this->cleanLocation($matches[1]);
        }

        $knownLocations = [
            'monaco', 'mediterranean', 'bali', 'sydney', 'melbourne', 'brisbane',
            'perth', 'london', 'new york', 'dubai', 'singapore', 'tokyo', 'paris',
        ];

        foreach ($knownLocations as $location) {
            if (str_contains($normalized, $location)) {
                return Str::title($location);
            }
        }

        return null;
    }

    private function cleanLocation(string $location): ?string
    {
        $location = trim(preg_replace('/[^\pL\pN\s,-]+/u', ' ', $location) ?? '');

        return $location !== '' ? Str::limit($location, 40, '') : null;
    }

    private function extractSubject(string $prompt): string
    {
        $context = $this->extractPromptContext($prompt);
        $context = preg_replace('/[^\pL\pN\s-]+/u', ' ', $context) ?? '';
        $words = preg_split('/\s+/', Str::lower(trim($context))) ?: [];

        $stopWords = [
            'a', 'an', 'and', 'are', 'as', 'at', 'based', 'be', 'business', 'company',
            'create', 'exclusive', 'for', 'from', 'in', 'is', 'landing', 'luxury', 'modern',
            'of', 'offering', 'page', 'premium', 'private', 'professional', 'the', 'their',
            'to', 'website', 'with', 'your', 'showcase', 'include', 'focused', 'focus',
        ];

        $important = [];
        foreach ($words as $word) {
            $word = trim($word, '-');
            if ($word === '' || strlen($word) < 3 || in_array($word, $stopWords, true)) {
                continue;
            }
            if (! in_array($word, $important, true)) {
                $important[] = $word;
            }
            if (count($important) >= 5) {
                break;
            }
        }

        return $important !== [] ? implode(' ', $important) : 'modern business';
    }

    private function extractPromptContext(string $prompt): string
    {
        $parts = [];

        foreach (['Business name', 'Industry', 'Location', 'Page'] as $label) {
            if (preg_match('/^' . preg_quote($label, '/') . ':\s*([^\r\n]+)/mi', $prompt, $matches) === 1) {
                $parts[] = trim($matches[1]);
            }
        }

        if ($parts === []) {
            $clean = preg_replace('/\s+/', ' ', strip_tags($prompt)) ?? '';
            $parts[] = Str::limit(trim($clean), 160, '');
        }

        return implode(' ', array_unique(array_filter($parts)));
    }

    private function compact(string $query): string
    {
        $tokens = preg_split('/\s+/', trim($query)) ?: [];
        $unique = [];

        foreach ($tokens as $token) {
            $key = Str::lower(trim($token));
            if ($key === '' || in_array($key, $unique, true)) {
                continue;
            }
            $unique[] = $key;
            if (count($unique) >= 10) {
                break;
            }
        }

        return implode(' ', $unique);
    }

    private function normalize(string $value): string
    {
        $value = preg_replace('/\s+/', ' ', strip_tags($value)) ?? '';

        return Str::lower(trim($value));
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, Str::lower($needle))) {
                return true;
            }
        }

        return false;
    }
}
