<?php

namespace App\AI\Schemas;

class SchemaManager
{
    public static function map(): array
    {
        return [

            'hero_headline' => 'heroHeadlineSchema',

            'hero_floating_cards' => 'heroFloatingCardsSchema',

            'hero_video_background' => 'heroVideoBackgroundSchema',

            'hero_video_style' => 'heroVideoStyleSchema',

            'hero_background_image' => 'heroBackgroundImageSchema',

            'hero_slider_fade' => 'heroSliderFadeSchema',

            'hero_parallax' => 'heroParallaxSchema',

            'hero_editorial_overlay' => 'heroEditorialOverlaySchema',

            'hero_split_image' => 'heroSplitImageSchema',

            'hero_split_editorial' => 'heroSplitEditorialSchema',
            'hero_floating_glass' => 'heroFloatingGlassSchema',
            'hero_saas_dashboard' => 'heroSaasDashboardSchema',
            'hero_luxury_fullscreen' => 'heroLuxuryFullscreenSchema',
            'hero_video_premium' => 'heroVideoPremiumSchema',
            'hero_ai_conversation' => 'heroAiConversationSchema',
            'hero_agency_showcase' => 'heroAgencyShowcaseSchema',
            'hero_bento_premium' => 'heroBentoPremiumSchema',

            'image_cta_banner' => 'imageCtaBannerSchema',

            'feature_image_left' => 'featureImageLeftSchema',

            'feature_image_right' => 'featureImageRightSchema',

            'services_cards' => 'servicesCardsSchema',

            'services_bento' => 'servicesBentoSchema',
            'services_bento_premium' => 'servicesBentoPremiumSchema',
            'services_pricing_comparison' => 'servicesPricingComparisonSchema',
            'services_feature_comparison' => 'servicesFeatureComparisonSchema',
            'services_hover_cards' => 'servicesHoverCardsSchema',
            'services_sticky_scroll' => 'servicesStickyScrollSchema',
            'services_horizontal' => 'servicesHorizontalSchema',
            'services_interactive_tabs' => 'servicesInteractiveTabsSchema',
            'services_mega_grid' => 'servicesMegaGridSchema',
            'about_timeline_story' => 'aboutTimelineStorySchema',
            'about_founder_story' => 'aboutFounderStorySchema',
            'about_mission_grid' => 'aboutMissionGridSchema',
            'about_interactive_stats' => 'aboutInteractiveStatsSchema',
            'about_brand_journey' => 'aboutBrandJourneySchema',
            'about_awards_timeline' => 'aboutAwardsTimelineSchema',
            'about_culture_section' => 'aboutCultureSectionSchema',
            'about_office_gallery' => 'aboutOfficeGallerySchema',
            'portfolio_masonry' => 'portfolioMasonrySchema',
            'portfolio_pinterest' => 'portfolioPinterestSchema',
            'portfolio_hover_video' => 'portfolioHoverVideoSchema',
            'portfolio_case_study' => 'portfolioCaseStudySchema',
            'portfolio_before_after' => 'portfolioBeforeAfterSchema',
            'portfolio_filterable' => 'portfolioFilterableSchema',
            'portfolio_animated' => 'portfolioAnimatedSchema',
            'portfolio_project_timeline' => 'portfolioProjectTimelineSchema',

            'process_timeline' => 'processTimelineSchema',

            'stats_modern' => 'statsModernSchema',
            'stats_animated_counters_premium' => 'statsAnimatedCountersPremiumSchema',
            'stats_revenue_dashboard_premium' => 'statsRevenueDashboardPremiumSchema',
            'stats_growth_charts_premium' => 'statsGrowthChartsPremiumSchema',
            'stats_achievements_premium' => 'statsAchievementsPremiumSchema',

            'team_modern' => 'teamModernSchema',
            'team_cards_premium' => 'teamCardsPremiumSchema',
            'team_timeline_premium' => 'teamTimelinePremiumSchema',
            'team_org_chart_premium' => 'teamOrgChartPremiumSchema',
            'team_leadership_premium' => 'teamLeadershipPremiumSchema',
            'team_culture_premium' => 'teamCulturePremiumSchema',
            'team_open_positions_premium' => 'teamOpenPositionsPremiumSchema',

            'testimonials_carousel' => 'testimonialsSchema',
            'testimonials_video_premium' => 'testimonialsVideoPremiumSchema',
            'testimonials_scrolling_marquee' => 'testimonialsScrollingMarqueeSchema',
            'testimonials_wall_of_love' => 'testimonialsWallOfLoveSchema',
            'testimonials_card_stack' => 'testimonialsCardStackSchema',
            'testimonials_trust_dashboard' => 'testimonialsTrustDashboardSchema',
            'testimonials_review_grid' => 'testimonialsReviewGridSchema',
            'testimonials_review_carousel_pro' => 'testimonialsReviewCarouselProSchema',

            'hero_centered_cta' => 'heroCtaSchema',
            'cta_glass_premium' => 'ctaGlassPremiumSchema',
            'cta_gradient_premium' => 'ctaGradientPremiumSchema',
            'cta_newsletter_premium' => 'ctaNewsletterPremiumSchema',
            'cta_book_demo_premium' => 'ctaBookDemoPremiumSchema',
            'cta_calendly_premium' => 'ctaCalendlyPremiumSchema',
            'cta_free_trial_premium' => 'ctaFreeTrialPremiumSchema',
            'cta_countdown_premium' => 'ctaCountdownPremiumSchema',
            'cta_limited_offer_premium' => 'ctaLimitedOfferPremiumSchema',

            'pricing_cards' => 'pricingCardsSchema',
            'pricing_comparison_premium' => 'pricingComparisonPremiumSchema',
            'pricing_toggle_premium' => 'pricingTogglePremiumSchema',
            'pricing_enterprise_premium' => 'pricingEnterprisePremiumSchema',
            'pricing_calculator_premium' => 'pricingCalculatorPremiumSchema',
            'pricing_credit_premium' => 'pricingCreditPremiumSchema',
            'pricing_agency_premium' => 'pricingAgencyPremiumSchema',
            'pricing_feature_matrix_premium' => 'pricingFeatureMatrixPremiumSchema',

            'contact_form_modern' => 'contactFormModernSchema',
            'contact_split_premium' => 'contactSplitPremiumSchema',
            'contact_map_premium' => 'contactMapPremiumSchema',
            'contact_appointment_premium' => 'contactAppointmentPremiumSchema',
            'contact_support_center_premium' => 'contactSupportCenterPremiumSchema',
            'contact_faq_premium' => 'contactFaqPremiumSchema',
            'contact_multistep_premium' => 'contactMultiStepPremiumSchema',
            'contact_live_chat_premium' => 'contactLiveChatPremiumSchema',
            'blog_magazine_premium' => 'blogMagazinePremiumSchema',
            'blog_featured_article_premium' => 'blogFeaturedArticlePremiumSchema',
            'blog_editors_pick_premium' => 'blogEditorsPickPremiumSchema',
            'blog_sidebar_news_premium' => 'blogSidebarNewsPremiumSchema',
            'blog_newsletter_premium' => 'blogNewsletterPremiumSchema',
            'blog_trending_premium' => 'blogTrendingPremiumSchema',
            'blog_categories_grid_premium' => 'blogCategoriesGridPremiumSchema',
            'blog_author_profile_premium' => 'blogAuthorProfilePremiumSchema',
            'footer_mega_premium' => 'footerMegaPremiumSchema',
            'footer_agency_premium' => 'footerAgencyPremiumSchema',
            'footer_saas_premium' => 'footerSaasPremiumSchema',
            'footer_luxury_premium' => 'footerLuxuryPremiumSchema',
            'footer_dark_premium' => 'footerDarkPremiumSchema',
            'footer_minimal_premium' => 'footerMinimalPremiumSchema',

            'faq_accordion' => 'faqAccordionSchema',

            'contact_details' => 'contactDetailsSchema',

            'location_map' => 'locationMapSchema',

            'case_studies_grid' => 'caseStudiesGridSchema',

            'jobs_list' => 'jobsListSchema',

            'events_grid' => 'eventsGridSchema',

        ];
    }
}
