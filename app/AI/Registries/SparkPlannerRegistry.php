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
            'services_sticky_scroll' => ['category' => 'services', 'description' => 'Pro-only sticky editorial service sequence for detailed capabilities, agencies, consultancies, and specialist service firms.'],
            'services_horizontal' => ['category' => 'services', 'description' => 'Pro-only horizontally scrollable service rail for modern agencies, studios, technology, and premium service brands.'],
            'services_interactive_tabs' => ['category' => 'services', 'description' => 'Pro-only interactive service tabs for connected disciplines, consultancies, agencies, SaaS, and technology teams.'],
            'services_mega_grid' => ['category' => 'services', 'description' => 'Pro-only dense capability grid for businesses with a broad portfolio of connected specialist services.'],
            'about_timeline_story' => ['category' => 'about', 'description' => 'Pro-only editorial company timeline for brand history, milestones, launches, and growth stories.'],
            'about_founder_story' => ['category' => 'about', 'description' => 'Pro-only founder story with editorial biography, quote, principles, and portrait-led layout.'],
            'about_mission_grid' => ['category' => 'about', 'description' => 'Pro-only mission, vision, and values bento grid for purpose-led company storytelling.'],
            'about_interactive_stats' => ['category' => 'about', 'description' => 'Pro-only company proof section with four large interactive metric cards and supporting context.'],
            'about_brand_journey' => ['category' => 'about', 'description' => 'Pro-only editorial brand journey showing four stages of identity, positioning, or offer evolution.'],
            'about_awards_timeline' => ['category' => 'about', 'description' => 'Pro-only chronological recognition timeline for verified awards, shortlistings, certifications, and honours.'],
            'about_culture_section' => ['category' => 'about', 'description' => 'Pro-only culture section with four behaviour-led working principles and an editorial introduction.'],
            'about_office_gallery' => ['category' => 'about', 'description' => 'Pro-only asymmetrical workplace gallery for offices, studios, venues, clinics, showrooms, or team environments.'],
            'portfolio_masonry' => ['category' => 'portfolio', 'description' => 'Pro-only editorial masonry project gallery for design, agency, architecture, property, hospitality, and creative work.'],
            'portfolio_pinterest' => ['category' => 'portfolio', 'description' => 'Pro-only dense visual-board portfolio for image-first brands, studios, interiors, fashion, food, travel, and creative work.'],
            'portfolio_hover_video' => ['category' => 'portfolio', 'description' => 'Pro-only poster-first project grid with optional motion preview URLs and safe image fallback.'],
            'portfolio_case_study' => ['category' => 'portfolio', 'description' => 'Pro-only flagship case-study section with challenge, approach, outcome, and verified-result fields.'],
            'portfolio_before_after' => ['category' => 'portfolio', 'description' => 'Pro-only before-and-after comparison for redesigns, renovations, transformations, and measurable visual change.'],
            'portfolio_filterable' => ['category' => 'portfolio', 'description' => 'Pro-only project index with visitor-facing category filters for mixed bodies of work.'],
            'portfolio_animated' => ['category' => 'portfolio', 'description' => 'Pro-only motion-led project grid using lightweight CSS transitions and strong editorial imagery.'],
            'portfolio_project_timeline' => ['category' => 'portfolio', 'description' => 'Pro-only project story timeline showing four stages from discovery through launch.'],
            'process_timeline' => ['category' => 'process', 'description' => 'Step-by-step process or customer journey.'],
            'stats_modern' => ['category' => 'proof', 'description' => 'Metrics and numbers that build trust.'],
            'stats_animated_counters_premium' => ['category' => 'proof', 'description' => 'Pro-only premium counter layout for verified company metrics; never invent business statistics.'],
            'stats_revenue_dashboard_premium' => ['category' => 'proof', 'description' => 'Pro-only executive revenue/commercial dashboard using only supplied verified financial figures.'],
            'stats_growth_charts_premium' => ['category' => 'proof', 'description' => 'Pro-only lightweight growth chart for user-supplied trend data; never invent growth rates.'],
            'stats_achievements_premium' => ['category' => 'proof', 'description' => 'Pro-only milestone and achievements layout; only use real supplied recognitions, dates, and outcomes.'],
            'team_modern' => ['category' => 'team', 'description' => 'Team members and leadership profiles.'],
            'team_cards_premium' => ['category' => 'team', 'description' => 'Pro-only editorial portrait card grid for supplied or clearly placeholder team profiles.'],
            'team_timeline_premium' => ['category' => 'team', 'description' => 'Pro-only chronological people story using supplied team facts; never invent employment history.'],
            'team_org_chart_premium' => ['category' => 'team', 'description' => 'Pro-only responsive organization hierarchy using supplied people and roles; never invent reporting lines.'],
            'team_leadership_premium' => ['category' => 'team', 'description' => 'Pro-only leadership spotlight using supplied people, roles, and biographies.'],
            'team_culture_premium' => ['category' => 'team', 'description' => 'Pro-only team culture section with four editable principles; never invent certifications, employee sentiment, or workplace claims.'],
            'team_open_positions_premium' => ['category' => 'team', 'description' => 'Pro-only careers section for real user-supplied vacancies; never invent openings, salaries, benefits, locations, or hiring terms.'],
            'testimonials_carousel' => ['category' => 'proof', 'description' => 'Customer testimonials and social proof.'],
            'testimonials_video_premium' => ['category' => 'testimonials', 'description' => 'Pro-only editorial customer story layout with optional video and image fallback.'],
            'testimonials_scrolling_marquee' => ['category' => 'testimonials', 'description' => 'Pro-only horizontally flowing customer quote rail with lightweight native scrolling.'],
            'testimonials_wall_of_love' => ['category' => 'testimonials', 'description' => 'Pro-only dense multi-card social-proof wall for verified customer feedback.'],
            'testimonials_card_stack' => ['category' => 'testimonials', 'description' => 'Pro-only layered testimonial card stack with premium editorial hierarchy.'],
            'testimonials_trust_dashboard' => ['category' => 'testimonials', 'description' => 'Pro-only trust dashboard combining customer reviews with summary proof cards.'],
            'testimonials_review_grid' => ['category' => 'testimonials', 'description' => 'Pro-only balanced customer review grid for several verified voices at once.'],
            'testimonials_review_carousel_pro' => ['category' => 'testimonials', 'description' => 'Pro-only editorial review carousel with featured-card hierarchy and static fallback.'],
            'hero_centered_cta' => ['category' => 'cta', 'description' => 'Simple centered call-to-action section.'],
            'image_cta_banner' => ['category' => 'cta', 'description' => 'Visual image-backed call-to-action banner.'],
            'cta_glass_premium' => ['category' => 'cta', 'description' => 'Pro-only frosted glass call-to-action card with layered premium depth.'],
            'cta_gradient_premium' => ['category' => 'cta', 'description' => 'Pro-only bold gradient call-to-action with high-contrast conversion hierarchy.'],
            'cta_newsletter_premium' => ['category' => 'cta', 'description' => 'Pro-only newsletter signup call-to-action with compact trust/support copy.'],
            'cta_book_demo_premium' => ['category' => 'cta', 'description' => 'Pro-only demo-booking call-to-action with scheduling-oriented details and sales handoff.'],
            'cta_calendly_premium' => ['category' => 'cta', 'description' => 'Pro-only calendar-style scheduling CTA for a supplied Calendly or booking URL; never invent availability.'],
            'cta_free_trial_premium' => ['category' => 'cta', 'description' => 'Pro-only free-trial CTA with benefit chips and transparent user-supplied trial terms.'],
            'cta_countdown_premium' => ['category' => 'cta', 'description' => 'Pro-only countdown-style CTA for a real supplied deadline, launch, event, or campaign; never invent urgency.'],
            'cta_limited_offer_premium' => ['category' => 'cta', 'description' => 'Pro-only limited-offer CTA for genuine user-supplied promotion details, eligibility, and expiry terms.'],
            'pricing_cards' => ['category' => 'pricing', 'description' => 'Plans, packages, or service pricing.'],
            'pricing_comparison_premium' => ['category' => 'pricing', 'description' => 'Pro-only premium plan comparison table with three offers and six editable feature rows.'],
            'pricing_toggle_premium' => ['category' => 'pricing', 'description' => 'Pro-only pricing cards with monthly/yearly values and a lightweight billing toggle.'],
            'pricing_enterprise_premium' => ['category' => 'pricing', 'description' => 'Pro-only enterprise pricing section for custom scopes, consultative sales, and complex organisations.'],
            'pricing_calculator_premium' => ['category' => 'pricing', 'description' => 'Pro-only lightweight pricing estimator with editable unit rate, quantity bounds, and quote CTA.'],
            'pricing_credit_premium' => ['category' => 'pricing', 'description' => 'Pro-only prepaid credit-pack pricing for usage-based products, AI credits, tokens, or service units.'],
            'pricing_agency_premium' => ['category' => 'pricing', 'description' => 'Pro-only agency pricing built around client/site capacity, team collaboration, and scalable support.'],
            'pricing_feature_matrix_premium' => ['category' => 'pricing', 'description' => 'Pro-only grouped feature matrix for detailed plan capability comparison.'],
            'contact_form_modern' => ['category' => 'contact', 'description' => 'Lead-generation contact form.'],
            'contact_split_premium' => ['category' => 'contact', 'description' => 'Pro-only split contact layout pairing business details with an inquiry form.'],
            'contact_map_premium' => ['category' => 'contact', 'description' => 'Pro-only location-first contact layout with address and a real user-supplied directions URL.'],
            'contact_appointment_premium' => ['category' => 'contact', 'description' => 'Pro-only appointment booking contact layout using a supplied booking URL; never invent availability.'],
            'contact_support_center_premium' => ['category' => 'contact', 'description' => 'Pro-only support hub with clear support channels and help topics.'],
            'contact_faq_premium' => ['category' => 'contact', 'description' => 'Pro-only FAQ and contact combination for answering common questions before inquiry.'],
            'contact_multistep_premium' => ['category' => 'contact', 'description' => 'Pro-only staged inquiry experience with a lightweight multi-step builder UI and static export fallback.'],
            'contact_live_chat_premium' => ['category' => 'contact', 'description' => 'Pro-only chat-entry CTA using a supplied messaging URL; never invent online status or response time.'],
            'blog_magazine_premium' => ['category' => 'blog', 'description' => 'Pro-only magazine-style editorial blog section with a lead story and supporting articles.'],
            'blog_featured_article_premium' => ['category' => 'blog', 'description' => 'Pro-only featured article section with strong editorial hierarchy and one supplied story.'],
            'blog_editors_pick_premium' => ['category' => 'blog', 'description' => 'Pro-only curated editor pick section with a lead recommendation and short reading list.'],
            'blog_sidebar_news_premium' => ['category' => 'blog', 'description' => 'Pro-only news layout with a main feed and compact topic sidebar.'],
            'blog_newsletter_premium' => ['category' => 'blog', 'description' => 'Pro-only editorial newsletter signup section; never invent sending frequency or subscriber claims.'],
            'blog_trending_premium' => ['category' => 'blog', 'description' => 'Pro-only numbered featured-content list; only call content trending when the user provides evidence.'],
            'blog_categories_grid_premium' => ['category' => 'blog', 'description' => 'Pro-only topic category grid for editorial browsing.'],
            'blog_author_profile_premium' => ['category' => 'blog', 'description' => 'Pro-only author profile based only on supplied identity, role, biography, and specialties.'],
            'footer_mega_premium' => ['category' => 'footer', 'description' => 'Pro-only information-rich multi-column footer for sites with broader navigation.'],
            'footer_agency_premium' => ['category' => 'footer', 'description' => 'Pro-only bold agency footer with oversized project CTA and compact contact details.'],
            'footer_saas_premium' => ['category' => 'footer', 'description' => 'Pro-only structured SaaS footer with product, company, and resources navigation.'],
            'footer_luxury_premium' => ['category' => 'footer', 'description' => 'Pro-only refined minimal footer for luxury, hospitality, property, fashion, and premium brands.'],
            'footer_dark_premium' => ['category' => 'footer', 'description' => 'Pro-only high-contrast dark footer with a strong close and compact navigation.'],
            'footer_minimal_premium' => ['category' => 'footer', 'description' => 'Pro-only restrained minimal footer with generous whitespace and low visual noise.'],
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
