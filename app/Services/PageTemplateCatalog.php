<?php

namespace App\Services;

class PageTemplateCatalog
{
    public const PURCHASE_CREDITS = 100;
    public const PERSONALIZE_CREDITS = 50;

    public static function all(): array
    {
        return [
            [
                'key' => 'parallax-authority', 'name' => 'Parallax Authority',
                'description' => 'A high-impact business landing page with parallax depth, proof, services and a strong close.',
                'tags' => ['Parallax', 'Business', 'Premium'], 'featured' => true,
                'sections' => ['hero_parallax', 'stats_modern', 'services_bento_premium', 'process_timeline', 'testimonials_carousel', 'image_cta_banner'],
            ],
            [
                'key' => 'slider-showcase', 'name' => 'Slider Showcase',
                'description' => 'A cinematic slider-led page for visual brands, property, hospitality and premium services.',
                'tags' => ['Slider', 'Luxury', 'Showcase'], 'featured' => true,
                'sections' => ['hero_slider_fade', 'feature_image_left', 'services_hover_cards', 'case_studies_grid', 'testimonials_carousel', 'contact_form_modern'],
            ],
            [
                'key' => 'split-conversion', 'name' => 'Split Conversion',
                'description' => 'A focused split-hero composition built for lead generation and professional services.',
                'tags' => ['Split', 'Business', 'Conversion'], 'featured' => false,
                'sections' => ['hero_split_image', 'services_feature_comparison', 'feature_image_right', 'stats_modern', 'faq_accordion', 'contact_form_modern'],
            ],
            [
                'key' => 'bento-launch', 'name' => 'Bento Launch',
                'description' => 'A modern product and startup page combining a premium bento hero with modular proof.',
                'tags' => ['Bento', 'SaaS', 'Modern'], 'featured' => true,
                'sections' => ['hero_bento_premium', 'services_bento_premium', 'feature_image_left', 'stats_modern', 'pricing_cards', 'faq_accordion'],
            ],
            [
                'key' => 'editorial-luxe', 'name' => 'Editorial Luxe',
                'description' => 'An editorial, image-forward page for luxury, creative and lifestyle brands.',
                'tags' => ['Editorial', 'Luxury', 'Creative'], 'featured' => false,
                'sections' => ['hero_editorial_overlay', 'feature_image_right', 'services_bento', 'case_studies_grid', 'testimonials_carousel', 'image_cta_banner'],
            ],
            [
                'key' => 'cinematic-agency', 'name' => 'Cinematic Agency',
                'description' => 'A motion-led creative agency page with a cinematic hero, service proof, work highlights and a confident conversion path.',
                'tags' => ['Video', 'Agency', 'Creative'], 'featured' => true,
                'sections' => ['hero_video_premium', 'services_hover_cards', 'case_studies_grid', 'stats_modern', 'testimonials_carousel', 'contact_form_modern'],
            ],
            [
                'key' => 'luxury-signature', 'name' => 'Luxury Signature',
                'description' => 'A spacious fullscreen luxury composition for premium brands, hospitality, property and high-end professional services.',
                'tags' => ['Luxury', 'Fullscreen', 'Premium'], 'featured' => true,
                'sections' => ['hero_luxury_fullscreen', 'feature_image_left', 'services_bento_premium', 'case_studies_grid', 'testimonials_carousel', 'image_cta_banner'],
            ],
            [
                'key' => 'glass-studio', 'name' => 'Glass Studio',
                'description' => 'A polished floating-glass page for studios and modern service brands with layered visuals, proof and compact calls to action.',
                'tags' => ['Glass', 'Modern', 'Creative'], 'featured' => false,
                'sections' => ['hero_floating_glass', 'services_cards', 'feature_image_right', 'stats_modern', 'process_timeline', 'contact_form_modern'],
            ],
            [
                'key' => 'saas-command', 'name' => 'SaaS Command',
                'description' => 'A dashboard-first SaaS page designed for product clarity, feature comparison, pricing and conversion-focused FAQs.',
                'tags' => ['SaaS', 'Dashboard', 'Technology'], 'featured' => true,
                'sections' => ['hero_saas_dashboard', 'services_feature_comparison', 'services_bento_premium', 'stats_modern', 'pricing_cards', 'faq_accordion'],
            ],
            [
                'key' => 'split-editorial-pro', 'name' => 'Split Editorial Pro',
                'description' => 'An editorial split-layout template balancing story, services and trust for consultants, design firms and premium businesses.',
                'tags' => ['Split', 'Editorial', 'Business'], 'featured' => false,
                'sections' => ['hero_split_editorial', 'feature_image_left', 'services_pricing_comparison', 'process_timeline', 'testimonials_carousel', 'image_cta_banner'],
            ],
            [
                'key' => 'ai-product-launch', 'name' => 'AI Product Launch',
                'description' => 'A conversational AI product page that moves from an interactive-feeling hero into product benefits, proof, pricing and conversion FAQs.',
                'tags' => ['AI', 'SaaS', 'Technology'], 'featured' => true,
                'sections' => ['hero_ai_conversation', 'services_bento_premium', 'feature_image_right', 'stats_modern', 'pricing_cards', 'faq_accordion'],
            ],
            [
                'key' => 'agency-transformation', 'name' => 'Agency Transformation',
                'description' => 'A portfolio-first agency composition built around transformation proof, premium services, case studies and a direct lead-generation close.',
                'tags' => ['Agency', 'Portfolio', 'Creative'], 'featured' => true,
                'sections' => ['hero_agency_showcase', 'services_hover_cards', 'case_studies_grid', 'process_timeline', 'testimonials_carousel', 'contact_form_modern'],
            ],
            [
                'key' => 'tech-bento-growth', 'name' => 'Tech Bento Growth',
                'description' => 'A modular technology and startup page with bento storytelling, comparison-led features, metrics, pricing and a compact conversion path.',
                'tags' => ['Bento', 'Startup', 'Technology'], 'featured' => false,
                'sections' => ['hero_bento_premium', 'services_feature_comparison', 'stats_modern', 'feature_image_left', 'pricing_cards', 'image_cta_banner'],
            ],
            [
                'key' => 'corporate-clarity', 'name' => 'Corporate Clarity',
                'description' => 'A polished corporate page with a familiar image-led opening, structured capabilities, measurable proof and clear contact conversion.',
                'tags' => ['Corporate', 'Business', 'Professional'], 'featured' => false,
                'sections' => ['hero_background_image', 'services_cards', 'feature_image_right', 'stats_modern', 'testimonials_carousel', 'contact_form_modern'],
            ],
            [
                'key' => 'consulting-forward', 'name' => 'Consulting Forward',
                'description' => 'A professional-services layout combining floating-card storytelling with service comparison, a clear process, trust proof and a strong final CTA.',
                'tags' => ['Consulting', 'Professional', 'Business'], 'featured' => false,
                'sections' => ['hero_floating_cards', 'services_feature_comparison', 'process_timeline', 'feature_image_left', 'testimonials_carousel', 'image_cta_banner'],
            ],
            [
                'key' => 'restaurant-signature', 'name' => 'Restaurant Signature',
                'description' => 'An atmospheric restaurant page with immersive storytelling, menu and service highlights, social proof and a reservation-ready close.',
                'tags' => ['Restaurant', 'Hospitality', 'Parallax'], 'featured' => true,
                'sections' => ['hero_parallax', 'feature_image_left', 'services_hover_cards', 'case_studies_grid', 'testimonials_carousel', 'contact_form_modern'],
            ],
            [
                'key' => 'coffee-craft', 'name' => 'Coffee Craft',
                'description' => 'A warm editorial composition for coffee shops and bakeries, pairing product storytelling with offerings, process and an inviting final call to action.',
                'tags' => ['Coffee', 'Bakery', 'Editorial'], 'featured' => false,
                'sections' => ['hero_editorial_overlay', 'feature_image_right', 'services_cards', 'process_timeline', 'case_studies_grid', 'image_cta_banner'],
            ],
            [
                'key' => 'hotel-escape', 'name' => 'Hotel Escape',
                'description' => 'A cinematic hospitality and travel template with a visual slider, experience highlights, destination imagery, guest proof and direct enquiry conversion.',
                'tags' => ['Hotel', 'Travel', 'Slider'], 'featured' => true,
                'sections' => ['hero_slider_fade', 'services_bento_premium', 'feature_image_left', 'case_studies_grid', 'testimonials_carousel', 'contact_form_modern'],
            ],
            [
                'key' => 'property-vision', 'name' => 'Property Vision',
                'description' => 'A premium real-estate showcase combining a fullscreen opening with property features, visual proof, key figures and a strong lead-capture finish.',
                'tags' => ['Real Estate', 'Luxury', 'Showcase'], 'featured' => true,
                'sections' => ['hero_luxury_fullscreen', 'feature_image_right', 'services_bento', 'case_studies_grid', 'stats_modern', 'contact_form_modern'],
            ],
            [
                'key' => 'fitness-momentum', 'name' => 'Fitness Momentum',
                'description' => 'An energetic fitness and wellness page with bold positioning, programs, transformation proof, a clear journey and conversion-focused pricing.',
                'tags' => ['Fitness', 'Wellness', 'Bold'], 'featured' => false,
                'sections' => ['hero_split_image', 'services_hover_cards', 'stats_modern', 'process_timeline', 'testimonials_carousel', 'pricing_cards'],
            ],
            [
                'key' => 'builder-pro', 'name' => 'Builder Pro',
                'description' => 'A strong construction and trades page built around visual authority, service capability, project proof, process clarity and direct enquiries.',
                'tags' => ['Construction', 'Trades', 'Business'], 'featured' => true,
                'sections' => ['hero_background_image', 'services_bento_premium', 'case_studies_grid', 'process_timeline', 'stats_modern', 'contact_form_modern'],
            ],
            [
                'key' => 'auto-performance', 'name' => 'Auto Performance',
                'description' => 'A bold automotive template for workshops, detailing and performance brands with visual impact, services, proof and conversion-focused contact.',
                'tags' => ['Automotive', 'Bold', 'Showcase'], 'featured' => false,
                'sections' => ['hero_video_style', 'services_hover_cards', 'feature_image_right', 'case_studies_grid', 'testimonials_carousel', 'contact_form_modern'],
            ],
            [
                'key' => 'health-trust', 'name' => 'Health Trust',
                'description' => 'A calm professional healthcare composition for medical and dental practices with clear services, practitioner trust, patient proof and easy enquiries.',
                'tags' => ['Medical', 'Dental', 'Professional'], 'featured' => true,
                'sections' => ['hero_split_editorial', 'services_cards', 'feature_image_left', 'team_modern', 'testimonials_carousel', 'contact_form_modern'],
            ],
            [
                'key' => 'advisory-prestige', 'name' => 'Advisory Prestige',
                'description' => 'A polished legal and finance page emphasizing expertise, service comparison, measurable credibility, client confidence and a premium consultation close.',
                'tags' => ['Legal', 'Finance', 'Professional'], 'featured' => false,
                'sections' => ['hero_luxury_fullscreen', 'services_feature_comparison', 'stats_modern', 'process_timeline', 'testimonials_carousel', 'image_cta_banner'],
            ],
            [
                'key' => 'learning-forward', 'name' => 'Learning Forward',
                'description' => 'A modern education template for schools, academies and training providers with programs, outcomes, learning journey, social proof and clear next steps.',
                'tags' => ['Education', 'Modern', 'Community'], 'featured' => false,
                'sections' => ['hero_floating_cards', 'services_bento', 'stats_modern', 'process_timeline', 'testimonials_carousel', 'contact_form_modern'],
            ],

            [
                'key' => 'portfolio-canvas', 'name' => 'Portfolio Canvas',
                'description' => 'A portfolio-first creative page with an editorial opening, selected work, capabilities, proof and a clean enquiry close.',
                'tags' => ['Portfolio', 'Creative', 'Editorial'], 'featured' => true,
                'sections' => ['hero_agency_showcase', 'case_studies_grid', 'services_hover_cards', 'feature_image_left', 'testimonials_carousel', 'contact_form_modern'],
            ],
            [
                'key' => 'editorial-journal', 'name' => 'Editorial Journal',
                'description' => 'A refined editorial composition for studios, publications and premium brands with story-led imagery and understated conversion.',
                'tags' => ['Editorial', 'Minimal', 'Premium'], 'featured' => false,
                'sections' => ['hero_editorial_overlay', 'feature_image_left', 'feature_image_right', 'services_cards', 'case_studies_grid', 'image_cta_banner'],
            ],
            [
                'key' => 'cinematic-story', 'name' => 'Cinematic Story',
                'description' => 'An immersive motion-led showcase combining cinematic video, visual storytelling, proof and a decisive final call to action.',
                'tags' => ['Cinematic', 'Video', 'Showcase'], 'featured' => true,
                'sections' => ['hero_video_premium', 'feature_image_right', 'case_studies_grid', 'stats_modern', 'testimonials_carousel', 'image_cta_banner'],
            ],
            [
                'key' => 'dark-prestige', 'name' => 'Dark Prestige',
                'description' => 'A high-contrast premium page for ambitious brands, pairing a fullscreen hero with structured services, metrics and trust.',
                'tags' => ['Dark', 'Luxury', 'Premium'], 'featured' => true,
                'sections' => ['hero_luxury_fullscreen', 'services_bento_premium', 'stats_modern', 'feature_image_left', 'testimonials_carousel', 'contact_form_modern'],
            ],
            [
                'key' => 'modern-showcase', 'name' => 'Modern Showcase',
                'description' => 'A versatile modern composition with floating visual depth, modular services, project proof, process and conversion.',
                'tags' => ['Modern', 'Showcase', 'Business'], 'featured' => false,
                'sections' => ['hero_floating_glass', 'services_bento', 'case_studies_grid', 'process_timeline', 'testimonials_carousel', 'contact_form_modern'],
            ],
        ];
    }

    public static function find(string $key): ?array
    {
        return collect(self::all())->firstWhere('key', $key);
    }
}
