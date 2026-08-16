<?php

namespace App\AI\Compatibility;

use App\AI\Registries\SparkPlannerRegistry;

class SparkCompatibilityChecker
{
    private const HEROES = [
        'hero_headline',
        'hero_floating_cards',
        'hero_video_background',
        'hero_video_style',
        'hero_background_image',
        'hero_slider_fade',
        'hero_parallax',
        'hero_editorial_overlay',
        'hero_split_image',
        'hero_split_editorial',
        'hero_floating_glass',
        'hero_saas_dashboard',
        'hero_luxury_fullscreen',
        'hero_video_premium',
        'hero_ai_conversation',
        'hero_agency_showcase',
        'hero_bento_premium',
        'hero_ken_burns_premium',
        'hero_crossfade_gallery_premium',
        'hero_cinematic_slider_premium',
        'hero_split_slider_premium',
        'hero_vertical_story_premium',
        'hero_parallax_layers_premium',
        'hero_mouse_parallax_premium',
        'hero_reveal_parallax_premium',
        'hero_zoom_scroll_premium',
        'hero_pinned_story_premium',
        'hero_video_cinematic_premium',
        'hero_video_split_premium',
        'hero_aurora_motion_premium',
        'hero_mesh_gradient_motion_premium',
        'hero_spotlight_cursor_premium',
        'hero_floating_cards_motion_premium',
        'hero_3d_tilt_product_premium',
        'hero_infinite_marquee_premium',
        'hero_rotating_words_premium',
        'hero_typewriter_premium',
        'hero_curtain_reveal_premium',
        'hero_image_mask_reveal_premium',
        'hero_stacked_cards_premium',
        'hero_perspective_carousel_premium',
        'hero_before_after_premium',
        'hero_scroll_morph_premium',
        'hero_glass_orb_premium',
        'hero_particle_constellation_premium',
        'hero_grid_pulse_tech_premium',
        'hero_light_trails_premium',
        'hero_device_showcase_premium',
        'hero_app_screens_carousel_premium',
        'hero_editorial_image_sequence_premium',
        'hero_interactive_bento_premium',
    ];

    private const CTA = ['hero_centered_cta', 'image_cta_banner', 'cta_glass_premium', 'cta_gradient_premium', 'cta_newsletter_premium', 'cta_book_demo_premium', 'cta_calendly_premium', 'cta_free_trial_premium', 'cta_countdown_premium', 'cta_limited_offer_premium'];

    private const TERMINAL = [
        'hero_centered_cta',
        'image_cta_banner',
        'cta_glass_premium',
        'cta_gradient_premium',
        'cta_newsletter_premium',
        'cta_book_demo_premium',
        'cta_calendly_premium',
        'cta_free_trial_premium',
        'cta_countdown_premium',
        'cta_limited_offer_premium',
        'contact_form_modern',
        'contact_details',
        'location_map',
        'contact_split_premium',
        'contact_map_premium',
        'contact_appointment_premium',
        'contact_support_center_premium',
        'contact_faq_premium',
        'contact_multistep_premium',
        'contact_live_chat_premium',
        'blog_magazine_premium',
        'blog_featured_article_premium',
        'blog_editors_pick_premium',
        'blog_sidebar_news_premium',
        'blog_newsletter_premium',
        'blog_trending_premium',
        'blog_categories_grid_premium',
        'blog_author_profile_premium',
        'team_cards_premium',
        'team_timeline_premium',
        'team_org_chart_premium',
        'team_leadership_premium',
        'team_culture_premium',
        'team_open_positions_premium',
    ];

    /**
     * Validate and safely repair an AI or fallback Spark plan.
     *
     * The AI remains responsible for creative selection. PHP owns hard rules,
     * supported slugs, required page-purpose blocks, and final ordering.
     */
    public function check(array $sections, string $prompt): array
    {
        $allowed = SparkPlannerRegistry::slugs();
        $intent = $this->detectPageIntent($prompt);
        $changes = [];

        $sections = $this->supportedUnique($sections, $allowed, $changes);
        $sections = $this->removeUnrequestedEffects($sections, $prompt, $changes);
        $sections = $this->limitExclusiveGroups($sections, $changes);
        $sections = $this->ensureIntentRequirements($sections, $intent, $changes);
        $sections = $this->ensureUsefulMinimum($sections, $intent, $changes);
        // Minimum/intent repair may add a member of an already selected group.
        // Re-run exclusivity so repair can never produce two heroes or CTAs.
        $sections = $this->limitExclusiveGroups($sections, $changes);
        $sections = $this->order($sections, $changes);

        if (count($sections) > 10) {
            $sections = array_slice($sections, 0, 10);
            $changes[] = 'trimmed_to_maximum_10';
            $sections = $this->order($sections, $changes, false);
        }

        return [
            'sections' => array_values($sections),
            'intent' => $intent,
            'changed' => $changes !== [],
            'changes' => array_values(array_unique($changes)),
        ];
    }

    private function supportedUnique(array $sections, array $allowed, array &$changes): array
    {
        $clean = [];

        foreach ($sections as $section) {
            if (! is_string($section) || ! in_array($section, $allowed, true)) {
                $changes[] = 'removed_unsupported_spark';
                continue;
            }

            if (in_array($section, $clean, true)) {
                $changes[] = 'removed_duplicate_spark';
                continue;
            }

            $clean[] = $section;
        }

        return $clean;
    }

    private function removeUnrequestedEffects(array $sections, string $prompt, array &$changes): array
    {
        $wantsVideo = preg_match('/\b(video|cinematic|motion background)\b/i', $prompt) === 1;
        $wantsParallax = preg_match('/\bparallax\b/i', $prompt) === 1;

        return array_values(array_filter($sections, function (string $section) use ($wantsVideo, $wantsParallax, &$changes): bool {
            if (in_array($section, ['hero_video_background', 'hero_video_style', 'hero_video_premium'], true) && ! $wantsVideo) {
                $changes[] = 'removed_unrequested_video_hero';
                return false;
            }

            if (in_array($section, ['hero_parallax', 'hero_parallax_layers_premium'], true) && ! $wantsParallax) {
                $changes[] = 'removed_unrequested_parallax_hero';
                return false;
            }

            return true;
        }));
    }

    private function limitExclusiveGroups(array $sections, array &$changes): array
    {
        $groups = [
            'hero' => self::HEROES,
            'services' => ['services_cards', 'services_bento', 'services_bento_premium', 'services_pricing_comparison', 'services_feature_comparison', 'services_hover_cards', 'services_sticky_scroll', 'services_horizontal', 'services_interactive_tabs', 'services_mega_grid'],
            'cta' => self::CTA,
            'pricing' => ['pricing_cards', 'pricing_comparison_premium', 'pricing_toggle_premium', 'pricing_enterprise_premium', 'pricing_calculator_premium', 'pricing_credit_premium', 'pricing_agency_premium', 'pricing_feature_matrix_premium'],
            'team' => ['team_modern', 'team_cards_premium', 'team_timeline_premium', 'team_org_chart_premium', 'team_leadership_premium', 'team_culture_premium', 'team_open_positions_premium'],
            'statistics' => ['stats_modern', 'stats_animated_counters_premium', 'stats_revenue_dashboard_premium', 'stats_growth_charts_premium', 'stats_achievements_premium', 'stats_global_presence_premium', 'stats_timeline_metrics_premium'],
            'testimonials' => ['testimonials_carousel', 'testimonials_video_premium', 'testimonials_scrolling_marquee', 'testimonials_wall_of_love', 'testimonials_card_stack', 'testimonials_trust_dashboard', 'testimonials_review_grid', 'testimonials_review_carousel_pro'],
            'faq' => ['faq_accordion', 'faq_accordion_pro', 'faq_search_premium', 'faq_categories_premium', 'faq_support_portal_premium', 'faq_documentation_premium'],
            'contact_form' => ['contact_form_modern', 'contact_split_premium', 'contact_appointment_premium', 'contact_multistep_premium'],
            'location' => ['location_map', 'contact_map_premium'],
            'jobs' => ['jobs_list'],
            'portfolio' => ['case_studies_grid', 'portfolio_masonry', 'portfolio_pinterest', 'portfolio_hover_video', 'portfolio_case_study', 'portfolio_before_after', 'portfolio_filterable', 'portfolio_animated', 'portfolio_project_timeline'],
            'events' => ['events_grid'],
        ];

        $seen = [];
        $result = [];

        foreach ($sections as $section) {
            $groupName = null;
            foreach ($groups as $name => $members) {
                if (in_array($section, $members, true)) {
                    $groupName = $name;
                    break;
                }
            }

            if ($groupName !== null && isset($seen[$groupName])) {
                $changes[] = "removed_conflicting_{$groupName}_spark";
                continue;
            }

            if ($groupName !== null) {
                $seen[$groupName] = true;
            }

            $result[] = $section;
        }

        return $result;
    }

    private function ensureIntentRequirements(array $sections, string $intent, array &$changes): array
    {
        $required = match ($intent) {
            'services' => ['services_cards', 'process_timeline'],
            'pricing' => ['pricing_cards', 'faq_accordion'],
            'team' => ['team_modern'],
            'contact' => ['contact_details', 'contact_form_modern'],
            'location' => ['location_map', 'contact_details'],
            'portfolio', 'case-studies', 'work' => ['case_studies_grid'],
            'careers', 'jobs' => ['jobs_list'],
            'events' => ['events_grid'],
            'about', 'story' => ['about_timeline_story', 'team_modern'],
            default => [],
        };

        foreach ($required as $spark) {
            if (! in_array($spark, $sections, true)) {
                $sections[] = $spark;
                $changes[] = "added_required_{$spark}";
            }
        }

        return $sections;
    }

    private function ensureUsefulMinimum(array $sections, string $intent, array &$changes): array
    {
        $minimum = in_array($intent, ['contact', 'location', 'careers', 'jobs', 'events'], true) ? 3 : 5;

        $defaults = match ($intent) {
            'contact' => ['hero_headline', 'contact_details', 'contact_form_modern'],
            'location' => ['hero_headline', 'location_map', 'contact_details'],
            'pricing' => ['hero_headline', 'pricing_cards', 'pricing_comparison_premium', 'pricing_toggle_premium', 'pricing_enterprise_premium', 'pricing_calculator_premium', 'pricing_credit_premium', 'pricing_agency_premium', 'pricing_feature_matrix_premium', 'feature_image_left', 'faq_accordion', 'faq_accordion_pro', 'faq_search_premium', 'faq_categories_premium', 'faq_support_portal_premium', 'faq_documentation_premium', 'lead_magnet_premium', 'lead_free_audit_premium', 'lead_website_audit_premium', 'lead_quote_form_premium', 'lead_roi_calculator_premium', 'lead_cost_calculator_premium', 'lead_consultation_booking_premium', 'sales_comparison_premium', 'sales_feature_matrix_premium', 'sales_competitor_comparison_premium', 'sales_roi_premium', 'sales_guarantee_premium', 'sales_trust_premium', 'sales_integrations_premium', 'agency_dashboard_preview_premium', 'agency_client_portal_premium', 'agency_white_label_showcase_premium', 'agency_website_management_premium', 'agency_maintenance_plans_premium', 'agency_support_plans_premium', 'agency_workflow_premium', 'agency_project_pipeline_premium', 'agency_client_reviews_premium', 'agency_website_reports_premium', 'ai_prompt_showcase_premium', 'ai_workflow_premium', 'ai_assistant_premium', 'ai_timeline_premium', 'ai_builder_premium', 'ai_automation_premium', 'ai_credits_dashboard_premium', 'ai_generation_process_premium', 'ai_statistics_premium', 'ai_prompt_examples_premium', 'hero_centered_cta'],
            'services' => ['hero_headline', 'services_cards', 'feature_image_left', 'process_timeline', 'hero_centered_cta'],
            'about', 'story' => ['hero_split_editorial', 'about_timeline_story', 'about_mission_grid', 'about_interactive_stats', 'about_brand_journey', 'about_awards_timeline', 'about_culture_section', 'about_office_gallery', 'team_modern'],
            'team' => ['hero_headline', 'feature_image_left', 'team_modern', 'team_cards_premium', 'team_timeline_premium', 'team_org_chart_premium', 'team_leadership_premium', 'team_culture_premium', 'team_open_positions_premium', 'testimonials_carousel', 'testimonials_video_premium', 'testimonials_scrolling_marquee', 'testimonials_wall_of_love', 'testimonials_card_stack', 'testimonials_trust_dashboard', 'testimonials_review_grid', 'testimonials_review_carousel_pro', 'hero_centered_cta'],
            'portfolio', 'case-studies', 'work' => ['hero_headline', 'case_studies_grid', 'stats_modern', 'testimonials_carousel', 'testimonials_video_premium', 'testimonials_scrolling_marquee', 'testimonials_wall_of_love', 'testimonials_card_stack', 'testimonials_trust_dashboard', 'testimonials_review_grid', 'testimonials_review_carousel_pro', 'hero_centered_cta'],
            'careers', 'jobs' => ['hero_headline', 'feature_image_left', 'jobs_list', 'hero_centered_cta'],
            'events' => ['hero_headline', 'events_grid', 'contact_form_modern'],
            default => ['hero_headline', 'services_cards', 'feature_image_left', 'stats_modern', 'testimonials_carousel', 'testimonials_video_premium', 'testimonials_scrolling_marquee', 'testimonials_wall_of_love', 'testimonials_card_stack', 'testimonials_trust_dashboard', 'testimonials_review_grid', 'testimonials_review_carousel_pro', 'hero_centered_cta'],
        };

        foreach ($defaults as $spark) {
            if (count($sections) >= $minimum) {
                break;
            }

            if (! in_array($spark, $sections, true) && ! $this->conflictsWithExisting($spark, $sections)) {
                $sections[] = $spark;
                $changes[] = "added_minimum_{$spark}";
            }
        }

        return $sections;
    }


    private function conflictsWithExisting(string $spark, array $sections): bool
    {
        $groups = [
            self::HEROES,
            ['services_cards', 'services_bento', 'services_bento_premium'],
            self::CTA,
        ];

        foreach ($groups as $group) {
            if (! in_array($spark, $group, true)) {
                continue;
            }

            foreach ($sections as $existing) {
                if (in_array($existing, $group, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function order(array $sections, array &$changes, bool $record = true): array
    {
        $original = $sections;
        $heroes = array_values(array_filter($sections, fn (string $spark): bool => in_array($spark, self::HEROES, true)));
        $body = array_values(array_filter($sections, fn (string $spark): bool => ! in_array($spark, self::HEROES, true) && ! in_array($spark, self::TERMINAL, true)));
        $terminal = array_values(array_filter($sections, fn (string $spark): bool => in_array($spark, self::TERMINAL, true)));

        // CTA should lead into contact details/form rather than appear after them.
        usort($terminal, static function (string $a, string $b): int {
            $priority = [
                'hero_centered_cta' => 10,
                'image_cta_banner' => 10,
                'contact_details' => 20,
                'location_map' => 30,
                'contact_form_modern' => 40,
            ];

            return ($priority[$a] ?? 20) <=> ($priority[$b] ?? 20);
        });

        $ordered = array_merge($heroes, $body, $terminal);

        if ($record && $ordered !== $original) {
            $changes[] = 'reordered_for_page_flow';
        }

        return $ordered;
    }

    private function detectPageIntent(string $prompt): string
    {
        $candidates = [];

        if (preg_match('/^\s*page\s*:\s*([^\r\n]+)/mi', $prompt, $matches) === 1) {
            $candidates[] = $matches[1];
        }

        $candidates[] = $prompt;

        $intentKeywords = [
            'case-studies' => ['case studies', 'case study'],
            'portfolio' => ['portfolio', 'projects'],
            'careers' => ['careers', 'career'],
            'jobs' => ['jobs', 'job openings', 'vacancies'],
            'location' => ['location', 'find us', 'directions'],
            'contact' => ['contact', 'get in touch'],
            'pricing' => ['pricing', 'plans', 'packages'],
            'services' => ['services', 'what we do'],
            'team' => ['team', 'people', 'leadership'],
            'events' => ['events', 'workshops'],
            'about' => ['about'],
            'story' => ['our story', 'story'],
            'work' => ['our work', 'work'],
            'home' => ['home', 'homepage', 'landing page'],
        ];

        foreach ($candidates as $candidate) {
            $normalized = $this->normalize((string) $candidate);

            foreach ($intentKeywords as $intent => $keywords) {
                foreach ($keywords as $keyword) {
                    if (str_contains(" {$normalized} ", ' ' . $this->normalize($keyword) . ' ')) {
                        return $intent;
                    }
                }
            }
        }

        return 'home';
    }

    private function normalize(string $value): string
    {
        $value = function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);

        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}
