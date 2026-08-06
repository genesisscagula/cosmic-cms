<?php

namespace App\AI\Registries;

use App\AI\Schemas\SchemaManager;

class SparkPlannerRegistry
{
    /**
     * Lightweight metadata sent to the planning model.
     * Full content schemas are intentionally excluded from the planner call.
     */
    public static function all(): array
    {
        $metadata = [
            'hero_headline' => ['category' => 'hero', 'description' => 'Text-first hero with two calls to action.'],
            'hero_floating_cards' => ['category' => 'hero', 'description' => 'Modern visual hero with floating supporting cards.'],
            'hero_video_background' => ['category' => 'hero', 'description' => 'Immersive hero for an explicit video request.'],
            'hero_video_style' => ['category' => 'hero', 'description' => 'Cinematic media-led hero section.'],
            'hero_background_image' => ['category' => 'hero', 'description' => 'Full-width photo background hero.'],
            'hero_slider_fade' => ['category' => 'hero', 'description' => 'Multi-message fading hero slider.'],
            'hero_parallax' => ['category' => 'hero', 'description' => 'High-impact parallax image hero.'],
            'hero_editorial_overlay' => ['category' => 'hero', 'description' => 'Premium editorial hero with overlaid copy.'],
            'hero_split_image' => ['category' => 'hero', 'description' => 'Balanced split layout with copy and image.'],
            'hero_split_editorial' => ['category' => 'hero', 'description' => 'Pro-only Apple-inspired editorial hero for premium, luxury, creative, architecture, design, technology, and agency brands.'],
            'hero_floating_glass' => ['category' => 'hero', 'description' => 'Pro-only immersive glassmorphism hero for SaaS, AI, technology, agencies, finance, consulting, and premium service brands.'],
            'hero_saas_dashboard' => ['category' => 'hero', 'description' => 'Pro-only SaaS product hero with a dashboard mockup, metrics, chart, and customer logo proof for software, AI, fintech, productivity, and technology brands.'],
            'hero_luxury_fullscreen' => ['category' => 'hero', 'description' => 'Pro-only cinematic full-screen luxury hero for hospitality, property, fashion, beauty, architecture, automotive, and premium service brands.'],
            'hero_video_premium' => ['category' => 'hero', 'description' => 'Pro-only cinematic background-video hero with premium overlays and a scroll cue for hospitality, property, automotive, fitness, travel, events, agencies, and launch campaigns.'],
            'hero_ai_conversation' => ['category' => 'hero', 'description' => 'Pro-only conversational AI hero with assistant messages, prompt composer, proof chips, and clear calls to action for AI, SaaS, automation, software, and technology brands.'],
            'hero_agency_showcase' => ['category' => 'hero', 'description' => 'Pro-only agency showcase hero with before-and-after project visuals, transformation metrics, and client-logo proof for creative, branding, web, digital, marketing, and performance agencies.'],
            'hero_bento_premium' => ['category' => 'hero', 'description' => 'Pro-only asymmetrical bento hero with editorial copy, image, metric, proof, and supporting feature cards for modern SaaS, technology, design, creative, agency, and premium service brands.'],
            'feature_image_left' => ['category' => 'feature', 'description' => 'Story or feature with image on the left.'],
            'feature_image_right' => ['category' => 'feature', 'description' => 'Story or feature with image on the right.'],
            'services_cards' => ['category' => 'services', 'description' => 'Scannable service cards for clear offerings.'],
            'services_bento' => ['category' => 'services', 'description' => 'Modern bento layout for richer service presentation.'],
            'services_bento_premium' => ['category' => 'services', 'description' => 'Pro-only asymmetrical premium services grid with one featured discipline, four supporting offers, proof content, and a conversion CTA for agencies, consultancies, SaaS, technology, finance, property, and premium service brands.'],
            'services_pricing_comparison' => ['category' => 'services', 'description' => 'Pro-only premium service pricing comparison with three packages, six editable comparison rows, and conversion actions for agencies, consultancies, retainers, and productised services.'],
            'services_feature_comparison' => ['category' => 'services', 'description' => 'Pro-only feature comparison with three service approaches, eight editable capability rows, and a conversion CTA for agencies, consultancies, SaaS, technology, and premium service teams.'],
            'services_hover_cards' => ['category' => 'services', 'description' => 'Pro-only interactive service grid with six hover-reveal cards, concise summaries, detailed service copy, and a conversion CTA for agencies, consultancies, technology, design, and premium service brands.'],
            'process_timeline' => ['category' => 'process', 'description' => 'Step-by-step process or customer journey.'],
            'stats_modern' => ['category' => 'proof', 'description' => 'Metrics and numbers that build trust.'],
            'team_modern' => ['category' => 'team', 'description' => 'Team members and leadership profiles.'],
            'testimonials_carousel' => ['category' => 'proof', 'description' => 'Customer testimonials and social proof.'],
            'hero_centered_cta' => ['category' => 'cta', 'description' => 'Simple centered call-to-action section.'],
            'image_cta_banner' => ['category' => 'cta', 'description' => 'Visual image-backed call-to-action banner.'],
            'pricing_cards' => ['category' => 'pricing', 'description' => 'Plans, packages, or service pricing.'],
            'contact_form_modern' => ['category' => 'contact', 'description' => 'Lead-generation contact form.'],
            'faq_accordion' => ['category' => 'faq', 'description' => 'Frequently asked questions in an accordion.'],
            'contact_details' => ['category' => 'contact', 'description' => 'Address, phone, email, and business details.'],
            'location_map' => ['category' => 'location', 'description' => 'Map and physical location information.'],
            'case_studies_grid' => ['category' => 'portfolio', 'description' => 'Projects, work samples, or case studies.'],
            'jobs_list' => ['category' => 'careers', 'description' => 'Open roles and recruitment information.'],
            'events_grid' => ['category' => 'events', 'description' => 'Upcoming events, sessions, or activities.'],
        ];

        $supported = array_keys(SchemaManager::map());

        return array_values(array_map(
            static fn (string $slug): array => [
                'slug' => $slug,
                'category' => $metadata[$slug]['category'] ?? 'content',
                'description' => $metadata[$slug]['description'] ?? 'Reusable website section.',
            ],
            $supported
        ));
    }

    public static function slugs(): array
    {
        return array_column(self::all(), 'slug');
    }
}
