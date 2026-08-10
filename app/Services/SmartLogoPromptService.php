<?php

namespace App\Services;

class SmartLogoPromptService
{
    public function build(
        string $company,
        string $industry,
        string $primary,
        string $themeKey,
        ?string $accent = null,
        ?string $headerBackground = '#FFFFFF',
        ?string $surface = null,
        ?string $brandPrompt = null,
        ?array $brandContext = null,
        ?string $latestUserPrompt = null,
    ): string {
        $brandPrompt = $this->clean($brandPrompt, 1200);
        $latestUserPrompt = $this->clean($latestUserPrompt, 700);
        $context = $this->contextSummary($brandContext ?? []);
        $primary = strtoupper($primary);
        $accent = $this->hex($accent) ?: $primary;
        $headerBackground = $this->hex($headerBackground) ?: '#FFFFFF';
        $surface = $this->hex($surface);

        $lines = [
            "Create an original, polished professional logo for {$company}.",
            "Industry: {$industry}.",
            "Active website theme: {$themeKey}.",
            "FINAL website primary color: {$primary}.",
            "FINAL website accent color: {$accent}.",
            "Website header background: {$headerBackground}.",
            $surface ? "Website theme surface color: {$surface}." : null,
        ];

        if ($brandPrompt !== '') {
            $lines[] = "Original website/brand brief: {$brandPrompt}";
        }
        if ($context !== '') {
            $lines[] = "Saved brand context: {$context}";
        }
        if ($latestUserPrompt !== '' && $latestUserPrompt !== $brandPrompt) {
            $lines[] = "Recent website direction (use only when relevant to brand identity): {$latestUserPrompt}";
        }

        $lines = array_values(array_filter($lines, fn ($line) => is_string($line) && $line !== ''));

        return implode("\n", array_merge($lines, [
            'ART DIRECTION: infer the visual personality from the brand brief and website context before choosing the logo style. Do not default to a generic flat SaaS icon plus plain text.',
            'Choose the most appropriate treatment for this brand: refined minimal, geometric, expressive gradient, dimensional/3D, metallic, layered, illustrative, futuristic, luxury, editorial, handcrafted, or another professional direction. The chosen treatment must feel intentional and specific to this business.',
            'Create a distinctive concept connected to the company name, industry, audience, product, or brand idea. Avoid generic swooshes, random abstract loops, stock-logo marks, clip-art, and obvious template-logo compositions unless the brief truly calls for them.',
            'Typography should feel custom-designed and coordinated with the symbol. You may use subtle letter customization, ligatures, cuts, weight contrast, or integrated iconography while keeping the company name clearly readable.',
            "COLOR MATCH IS MANDATORY: the FINAL website palette above is authoritative. Use {$primary} as the dominant visible brand color and {$accent} as the main supporting/accent color. The logo must immediately look like it belongs to this exact website theme.",
            'Do NOT invent an unrelated brand palette. Do not substitute generic SaaS blue, purple, gray, teal, or any other hue that is not derived from the provided final primary/accent colors. Harmonious tonal variants are allowed only when they remain recognizably inside the same color family.',
            "HEADER CONTRAST IS MANDATORY: the logo will be displayed on {$headerBackground}. Keep the wordmark, icon, tagline, and all meaningful visible artwork fully opaque and clearly legible against that exact header background. Do not use washed-out, faded, low-opacity, ghosted, semi-transparent, or low-contrast lettering.",
            'Visible logo artwork must use solid/full-opacity pixels except for intentional anti-aliasing at edges and truly transparent background pixels. Do not simulate opacity by using extremely pale versions of the brand colors when that reduces readability.',
            'You may use restrained highlights, gradients, depth, or material effects only if their dominant hues remain derived from the provided final primary/accent palette and they preserve strong header contrast.',

            'If dimensional or 3D styling suits the brand, keep it polished and logo-like rather than turning it into a scene or mockup. If minimal styling suits the brand, make the concept distinctive rather than generic.',
            'Prefer a strong horizontal symbol + wordmark composition suitable for a premium website header. The symbol may sit left of, partially integrate with, or intelligently interact with the wordmark.',
            'HEADER CROPPER TARGET IS MANDATORY: design the complete visible logo specifically to fit inside a 650 × 200 pixel website-header frame (3.25:1). Every visible element — icon, company name, tagline when explicitly requested, shadow, glow, highlight, and decoration — must be completely inside that frame.',
            'SAFE AREA: keep approximately 10–15% transparent safety padding on ALL sides of the complete visible artwork. Nothing may touch the top or bottom edges of the safe area, and no text, icon, tagline, or decoration may be clipped.',
            'PREFER LANDSCAPE: use a horizontal/landscape logo composition for website navigation. The symbol should normally sit beside or integrate with the wordmark. If the best concept is naturally taller or square, scale the ENTIRE composition down proportionally and add transparent padding around it instead of cropping or rearranging pieces outside the safe area.',
            'CONTAIN, NEVER COVER: preserve the entire logo composition. Do not zoom or scale artwork up merely to fill the canvas. Do not stretch, distort, crop, trim away required safety padding, or let any element extend beyond the 650 × 200 target.',
            'The generated PNG may include additional transparent canvas outside the target composition when needed. Preserving the complete logo is more important than filling the image. At the cropper default position and zoom, the full logo must already be visible inside the green 650 × 200 frame without user repositioning or zooming out.',
            'Do not make a poster, product mockup, wall sign, business card, or photographic scene. Nothing may be cropped or touch an image edge.',
            'Transparent background. No watermark. No decorative background plate. Do not add a slogan unless it is explicitly part of the company name or saved brand brief.',
            'Final result must look like a real commissioned brand identity: memorable at header size, balanced, cleanly rendered, and visually compatible with the website theme.',
        ]));
    }

    private function hex(?string $value): ?string
    {
        $value = strtoupper(trim((string) $value));
        return preg_match('/^#[0-9A-F]{6}$/', $value) ? $value : null;
    }

    private function contextSummary(array $context): string
    {
        $allowed = [
            'business_name', 'industry', 'location', 'business_description',
            'keywords', 'latest_keywords', 'audience', 'personality',
            'visual_direction', 'logo_direction', 'avoid',
        ];

        $parts = [];
        foreach ($allowed as $key) {
            if (! array_key_exists($key, $context)) {
                continue;
            }

            $value = $context[$key];
            if (is_array($value)) {
                $value = implode(', ', array_slice(array_values(array_filter(array_map('strval', $value))), 0, 12));
            }
            if (! is_scalar($value)) {
                continue;
            }

            $value = $this->clean((string) $value, 500);
            if ($value !== '') {
                $parts[] = str_replace('_', ' ', $key).': '.$value;
            }
        }

        return $this->clean(implode(' | ', $parts), 1800);
    }

    private function clean(?string $value, int $max): string
    {
        $value = preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '';
        return mb_substr($value, 0, $max);
    }
}
