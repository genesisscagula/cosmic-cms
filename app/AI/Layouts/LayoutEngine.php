<?php

namespace App\AI\Layouts;

class LayoutEngine
{
    public static function random(string $folder, ?string $prompt = null): array
    {
        $layoutFiles = [
            'restaurant' => 'RestaurantLayouts.php',
            'coffee' => 'CoffeeLayouts.php',
            'bakery' => 'BakeryLayouts.php',
            'hotel' => 'HotelLayouts.php',
            'travel' => 'TravelLayouts.php',
            'automotive' => 'AutomotiveLayouts.php',
            'construction' => 'ConstructionLayouts.php',
            'electrician' => 'ElectricianLayouts.php',
            'plumbing' => 'PlumbingLayouts.php',
            'roofing' => 'RoofingLayouts.php',
            'dentist' => 'DentistLayouts.php',
            'medical' => 'MedicalLayouts.php',
            'fitness' => 'FitnessLayouts.php',
            'cleaning' => 'CleaningLayouts.php',
            'landscaping' => 'LandscapingLayouts.php',
            'lawyer' => 'LawyerLayouts.php',
            'finance' => 'FinanceLayouts.php',
            'real-estate' => 'RealEstateLayouts.php',
            'technology' => 'TechnologyLayouts.php',
            'education' => 'EducationLayouts.php',
            'salon' => 'SalonLayouts.php',
        ];

        $layoutFile = $layoutFiles[$folder] ?? 'DefaultLayouts.php';
        $layouts = self::normalizeLegacyBlockTypes(require __DIR__.'/'.$layoutFile);

        if (isset($layouts['focus'], $layouts['default']) && is_array($layouts['focus']) && is_array($layouts['default'])) {
            $layouts = self::layoutsForPrompt($layouts, $prompt);
        }

        // Video is an explicit visual request. Prefer only layout variations
        // that already include the dedicated video hero, so PHP keeps control
        // of the complete block order and does not inject a surprise section.
        if (self::wantsVideo($prompt)) {
            $videoLayouts = array_values(array_filter(
                $layouts,
                static fn (array $layout): bool => in_array('hero_video_background', $layout, true)
            ));

            if ($videoLayouts !== []) {
                return $videoLayouts[array_rand($videoLayouts)];
            }
        }

        return $layouts[array_rand($layouts)];
    }

    /**
     * Resolve a page-intent group before choosing a random visual variation.
     * Only layout files that opt into the `focus` / `default` shape use this;
     * existing industry layout files remain fully backward compatible.
     */
    private static function layoutsForPrompt(array $definition, ?string $prompt): array
    {
        if ($prompt === null || trim($prompt) === '') {
            return $definition['default'];
        }

        // The Builder always includes "Page: {title}" in its profile context.
        // That explicit page title wins over incidental words in old page copy.
        $pageTitle = '';
        if (preg_match('/^\s*page\s*:\s*([^\r\n]+)/mi', $prompt, $matches) === 1) {
            $pageTitle = trim($matches[1]);
        }

        foreach ([$pageTitle, $prompt] as $candidate) {
            $normalized = self::normalizeLayoutText($candidate);

            if ($normalized === '') {
                continue;
            }

            foreach ($definition['focus'] as $focus) {
                foreach ($focus['keywords'] ?? [] as $keyword) {
                    if (self::containsLayoutKeyword($normalized, $keyword)) {
                        return $focus['layouts'];
                    }
                }
            }
        }

        return $definition['default'];
    }

    /**
     * Normalize human page titles and URL-style slugs into the same form.
     * For example, "Our Story", "our-story", and "our_story" all match
     * a configured "story" focus keyword.
     */
    private static function normalizeLayoutText(string $value): string
    {
        $value = function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);

        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    private static function containsLayoutKeyword(string $normalizedCandidate, string $keyword): bool
    {
        $normalizedKeyword = self::normalizeLayoutText($keyword);

        if ($normalizedKeyword === '') {
            return false;
        }

        return str_contains(" {$normalizedCandidate} ", " {$normalizedKeyword} ");
    }

    /**
     * Keep the first-generation industry layout files compatible with the
     * current Builder registry. These aliases were used before the reusable
     * Team, Contact, Map, and Blog blocks were finalized. Normalizing here
     * keeps PHP layout selection deterministic while preventing an unknown
     * section type from reaching the AI schema or React renderer.
     */
    private static function normalizeLegacyBlockTypes(array $definition): array
    {
        $aliases = [
            'team_grid' => 'team_modern',
            'contact_form' => 'contact_form_modern',
            'map_embed' => 'location_map',
            'posts_grid' => 'blog_hub',
            'featured_posts' => 'blog_hub',
            'newsletter_signup' => 'newsletter_cta',
        ];

        $normalizeLayout = static function (array $layout) use ($aliases): array {
            $types = array_map(
                static fn ($type) => is_string($type) ? ($aliases[$type] ?? $type) : $type,
                $layout
            );

            // The old featured-posts + posts-grid pairing is now one cohesive
            // Blog Hub block. Retain order while avoiding duplicate instances.
            return array_values(array_unique($types, SORT_REGULAR));
        };

        if (isset($definition['focus'], $definition['default'])) {
            foreach ($definition['focus'] as $key => $focus) {
                if (isset($focus['layouts']) && is_array($focus['layouts'])) {
                    $definition['focus'][$key]['layouts'] = array_map($normalizeLayout, $focus['layouts']);
                }
            }

            if (is_array($definition['default'])) {
                $definition['default'] = array_map($normalizeLayout, $definition['default']);
            }

            return $definition;
        }

        return array_map($normalizeLayout, $definition);
    }

    /**
     * Video is an explicit visual request. Keep the detection intentionally
     * narrow so ordinary mentions of images or media do not override the
     * normal randomized hero selection.
     */
    private static function wantsEditorial(?string $prompt): bool
    {
        return $prompt !== null
            && preg_match('/\b(editorial|apple[- ]style|luxury|minimal premium|high[- ]end|architecture|creative agency)\b/i', $prompt) === 1;
    }

    private static function wantsVideo(?string $prompt): bool
    {
        return $prompt !== null
            && preg_match('/\bvideo\b/i', $prompt) === 1;
    }

    /**
     * Pick a supported visual variation for one user-selected section purpose.
     * Structure remains deterministic in PHP; AI only fills the chosen schema.
     */
    public static function randomSection(string $category, ?string $prompt = null): string
    {
        // A video request is an explicit visual requirement, not a random variation.
        // Keep the rest of the selection deterministic and lightweight in PHP.
        if ($category === 'hero' && $prompt !== null && preg_match('/\b(premium video|cinematic video|video hero premium|luxury video|background motion|motion hero)\b/i', $prompt) === 1) {
            return 'hero_video_premium';
        }

        if ($category === 'hero' && self::wantsVideo($prompt)) {
            return 'hero_video_background';
        }

        if ($category === 'hero' && $prompt !== null && preg_match('/\b(luxury|luxurious|high[- ]end|exclusive|boutique|resort|hotel|fashion|jewelry|architecture|automotive|private edition)\b/i', $prompt) === 1) {
            return 'hero_luxury_fullscreen';
        }

        if ($category === 'hero' && self::wantsEditorial($prompt)) {
            return 'hero_split_editorial';
        }

        if ($category === 'hero' && $prompt !== null && preg_match('/\b(glass|glassmorphism|floating card|frosted|translucent)\b/i', $prompt) === 1) {
            return 'hero_floating_glass';
        }

        if ($category === 'hero' && $prompt !== null && preg_match('/\b(ai assistant|ai conversation|chatbot|conversational ai|prompt interface|copilot|automation assistant)\b/i', $prompt) === 1) {
            return 'hero_ai_conversation';
        }

        if ($category === 'hero' && $prompt !== null && preg_match('/\b(agency hero|creative agency|digital agency|branding agency|web agency|marketing agency|performance agency|before[- ]after|portfolio metrics|client logos)\b/i', $prompt) === 1) {
            return 'hero_agency_showcase';
        }

        if ($category === 'hero' && $prompt !== null && preg_match('/\b(bento|bento grid|asymmetrical|asymmetric cards|modular hero|multi-card hero)\b/i', $prompt) === 1) {
            return 'hero_bento_premium';
        }

        if ($category === 'hero' && $prompt !== null && preg_match('/\b(saas|software|dashboard|platform|app|product-led|fintech|productivity)\b/i', $prompt) === 1) {
            return 'hero_saas_dashboard';
        }

        if ($category === 'portfolio' && $prompt !== null && preg_match('/\b(masonry|editorial portfolio)\b/i', $prompt) === 1) { return 'portfolio_masonry'; }
        if ($category === 'portfolio' && $prompt !== null && preg_match('/\b(pinterest|inspiration board|visual archive)\b/i', $prompt) === 1) { return 'portfolio_pinterest'; }
        if ($category === 'portfolio' && $prompt !== null && preg_match('/\b(hover video|motion preview|video portfolio)\b/i', $prompt) === 1) { return 'portfolio_hover_video'; }
        if ($category === 'portfolio' && $prompt !== null && preg_match('/\b(case study|featured project|project story)\b/i', $prompt) === 1) { return 'portfolio_case_study'; }
        if ($category === 'portfolio' && $prompt !== null && preg_match('/\b(before after|before \/ after|transformation|redesign comparison|renovation comparison)\b/i', $prompt) === 1) { return 'portfolio_before_after'; }
        if ($category === 'portfolio' && $prompt !== null && preg_match('/\b(filter|filterable|categories|project index|sort projects)\b/i', $prompt) === 1) { return 'portfolio_filterable'; }
        if ($category === 'portfolio' && $prompt !== null && preg_match('/\b(animated|motion|interactive portfolio|moving portfolio)\b/i', $prompt) === 1) { return 'portfolio_animated'; }
        if ($category === 'portfolio' && $prompt !== null && preg_match('/\b(project timeline|project process|from brief to launch|project journey)\b/i', $prompt) === 1) { return 'portfolio_project_timeline'; }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(hover cards|interactive service cards|service hover|hover services|animated service cards)\b/i', $prompt) === 1) {
            return 'services_hover_cards';
        }

        if ($category === 'services' && $prompt !== null && preg_match('/\b(feature comparison|compare features|capability comparison|compare capabilities|service capabilities|approach comparison|compare approaches)\b/i', $prompt) === 1) {
            return 'services_feature_comparison';
        }

        if ($category === 'services' && $prompt !== null && preg_match('/\b(pricing comparison|compare packages|service packages|service pricing|retainer plans|package comparison)\b/i', $prompt) === 1) {
            return 'services_pricing_comparison';
        }

        if ($category === 'services' && $prompt !== null && preg_match('/\b(premium services|bento services|service bento|asymmetrical services|integrated services|agency services|consulting services|specialist team)\b/i', $prompt) === 1) {
            return 'services_bento_premium';
        }


        if ($category === 'cta' && $prompt !== null && preg_match('/\b(glass cta|frosted cta|glass call to action)\b/i', $prompt) === 1) { return 'cta_glass_premium'; }
        if ($category === 'cta' && $prompt !== null && preg_match('/\b(gradient cta|gradient call to action|bold cta)\b/i', $prompt) === 1) { return 'cta_gradient_premium'; }
        if ($category === 'cta' && $prompt !== null && preg_match('/\b(newsletter|email signup|subscribe|mailing list)\b/i', $prompt) === 1) { return 'cta_newsletter_premium'; }
        if ($category === 'cta' && $prompt !== null && preg_match('/\b(book demo|book a demo|schedule demo|request demo|demo call)\b/i', $prompt) === 1) { return 'cta_book_demo_premium'; }
        if ($category === 'cta' && $prompt !== null && preg_match('/\b(calendly|calendar booking|schedule a call|book a call|appointment link)\b/i', $prompt) === 1) { return 'cta_calendly_premium'; }
        if ($category === 'cta' && $prompt !== null && preg_match('/\b(free trial|start trial|try free|trial signup)\b/i', $prompt) === 1) { return 'cta_free_trial_premium'; }
        if ($category === 'cta' && $prompt !== null && preg_match('/\b(countdown|launch timer|event countdown|deadline timer)\b/i', $prompt) === 1) { return 'cta_countdown_premium'; }
        if ($category === 'cta' && $prompt !== null && preg_match('/\b(limited offer|special offer|promotion|promo offer)\b/i', $prompt) === 1) { return 'cta_limited_offer_premium'; }
        if ($category === 'contact' && $prompt !== null && preg_match('/\b(split contact|contact form|inquiry form|contact us)\b/i', $prompt) === 1) { return 'contact_split_premium'; }
        if ($category === 'contact' && $prompt !== null && preg_match('/\b(map contact|location contact|directions|find us|visit us)\b/i', $prompt) === 1) { return 'contact_map_premium'; }
        if ($category === 'contact' && $prompt !== null && preg_match('/\b(appointment booking|book appointment|schedule appointment|booking contact)\b/i', $prompt) === 1) { return 'contact_appointment_premium'; }
        if ($category === 'contact' && $prompt !== null && preg_match('/\b(support center|help center|technical support|billing support|customer support)\b/i', $prompt) === 1) { return 'contact_support_center_premium'; }
        if ($category === 'contact' && $prompt !== null && preg_match('/\b(faq contact|faq \+ contact|questions and contact|contact faq|common questions)\b/i', $prompt) === 1) { return 'contact_faq_premium'; }
        if ($category === 'contact' && $prompt !== null && preg_match('/\b(multi[- ]?step contact|multi[- ]?step form|guided inquiry|staged inquiry|step form)\b/i', $prompt) === 1) { return 'contact_multistep_premium'; }
        if ($category === 'contact' && $prompt !== null && preg_match('/\b(live chat|chat with us|chat support|messenger|whatsapp chat|start chat)\b/i', $prompt) === 1) { return 'contact_live_chat_premium'; }
        if ($category === 'faq' && $prompt !== null && preg_match('/\b(accordion pro|premium accordion|editorial faq)\b/i', $prompt) === 1) { return 'faq_accordion_pro'; }
        if ($category === 'faq' && $prompt !== null && preg_match('/\b(search faq|searchable faq|faq search|knowledge search)\b/i', $prompt) === 1) { return 'faq_search_premium'; }
        if ($category === 'faq' && $prompt !== null && preg_match('/\b(faq categories|categorized faq|browse by topic|faq topics)\b/i', $prompt) === 1) { return 'faq_categories_premium'; }
        if ($category === 'faq' && $prompt !== null && preg_match('/\b(support portal|help portal|support hub|knowledge portal)\b/i', $prompt) === 1) { return 'faq_support_portal_premium'; }
        if ($category === 'faq' && $prompt !== null && preg_match('/\b(documentation|docs|documentation section|help docs|product docs)\b/i', $prompt) === 1) { return 'faq_documentation_premium'; }
        if ($prompt !== null && preg_match('/\b(lead magnet|downloadable guide|free guide|checklist|template download|resource download)\b/i', $prompt) === 1) { return 'lead_magnet_premium'; }
        if ($prompt !== null && preg_match('/\b(free audit|free review|complimentary audit)\b/i', $prompt) === 1) { return 'lead_free_audit_premium'; }
        if ($prompt !== null && preg_match('/\b(website audit|site audit|website review|seo audit|conversion audit)\b/i', $prompt) === 1) { return 'lead_website_audit_premium'; }
        if ($prompt !== null && preg_match('/\b(quote form|request a quote|request quote|quote request|estimate form)\b/i', $prompt) === 1) { return 'lead_quote_form_premium'; }
        if ($prompt !== null && preg_match('/\b(roi calculator|return on investment calculator|roi estimate)\b/i', $prompt) === 1) { return 'lead_roi_calculator_premium'; }
        if ($prompt !== null && preg_match('/\b(cost calculator|cost estimator|price estimator|estimate calculator)\b/i', $prompt) === 1) { return 'lead_cost_calculator_premium'; }
        if ($prompt !== null && preg_match('/\b(consultation booking|book consultation|request consultation|schedule consultation)\b/i', $prompt) === 1) { return 'lead_consultation_booking_premium'; }
        if ($prompt !== null && preg_match('/\b(competitor comparison|compare competitors|versus competitors|vs competitors)\b/i', $prompt) === 1) { return 'sales_competitor_comparison_premium'; }
        if ($prompt !== null && preg_match('/\b(sales feature matrix|feature matrix for sales)\b/i', $prompt) === 1) { return 'sales_feature_matrix_premium'; }
        if ($prompt !== null && preg_match('/\b(sales comparison|comparison table for sales|compare options)\b/i', $prompt) === 1) { return 'sales_comparison_premium'; }
        if ($prompt !== null && preg_match('/\b(sales roi|business case|roi section)\b/i', $prompt) === 1) { return 'sales_roi_premium'; }
        if ($prompt !== null && preg_match('/\b(guarantee|warranty|assurance|money back|refund promise)\b/i', $prompt) === 1) { return 'sales_guarantee_premium'; }
        if ($prompt !== null && preg_match('/\b(trust section|trust signals|credentials|certifications|why trust us)\b/i', $prompt) === 1) { return 'sales_trust_premium'; }
        if ($prompt !== null && preg_match('/\b(integrations|integrates with|works with|connected tools|tech stack)\b/i', $prompt) === 1) { return 'sales_integrations_premium'; }
        if ($prompt !== null && preg_match('/\b(agency dashboard|agency dashboard preview|agency overview|agency metrics)\b/i', $prompt) === 1) { return 'agency_dashboard_preview_premium'; }
        if ($prompt !== null && preg_match('/\b(client portal|customer portal|client workspace)\b/i', $prompt) === 1) { return 'agency_client_portal_premium'; }
        if ($prompt !== null && preg_match('/\b(white label showcase|white label|white-label|agency branding)\b/i', $prompt) === 1) { return 'agency_white_label_showcase_premium'; }
        if ($prompt !== null && preg_match('/\b(website management|manage websites|multi-site management|site portfolio)\b/i', $prompt) === 1) { return 'agency_website_management_premium'; }
        if ($prompt !== null && preg_match('/\b(maintenance plans?|website care plans?|care plan|ongoing maintenance)\b/i', $prompt) === 1) { return 'agency_maintenance_plans_premium'; }
        if ($prompt !== null && preg_match('/\b(support plans?|support packages?|support options?|support tiers?)\b/i', $prompt) === 1) { return 'agency_support_plans_premium'; }
        if ($prompt !== null && preg_match('/\b(agency workflow|our process|delivery workflow|project workflow)\b/i', $prompt) === 1) { return 'agency_workflow_premium'; }
        if ($prompt !== null && preg_match('/\b(project pipeline|delivery pipeline|project stages|work pipeline)\b/i', $prompt) === 1) { return 'agency_project_pipeline_premium'; }
        if ($prompt !== null && preg_match('/\b(client reviews?|client testimonials?|agency reviews?|customer feedback|client feedback)\b/i', $prompt) === 1) { return 'agency_client_reviews_premium'; }
        if ($prompt !== null && preg_match('/\b(website reports?|client reports?|agency reports?|website reporting|client reporting)\b/i', $prompt) === 1) { return 'agency_website_reports_premium'; }
        if ($prompt !== null && preg_match('/\b(ai prompt showcase|prompt showcase|prompt examples?|example prompts?)\b/i', $prompt) === 1) { return 'ai_prompt_showcase_premium'; }
        if ($prompt !== null && preg_match('/\b(ai workflow|ai process|generation workflow|ai generation process)\b/i', $prompt) === 1) { return 'ai_workflow_premium'; }
        if ($prompt !== null && preg_match('/\b(ai assistant|assistant conversation|ai chat showcase|assistant showcase)\b/i', $prompt) === 1) { return 'ai_assistant_premium'; }
        if ($prompt !== null && preg_match('/\b(ai timeline|generation timeline|ai journey|generation journey)\b/i', $prompt) === 1) { return 'ai_timeline_premium'; }
        if ($prompt !== null && preg_match('/\b(ai builder|ai website builder|prompt to builder|builder showcase)\b/i', $prompt) === 1) { return 'ai_builder_premium'; }
        if ($prompt !== null && preg_match('/\b(ai automation|automation workflow|automated workflow|workflow automation)\b/i', $prompt) === 1) { return 'ai_automation_premium'; }
        if ($prompt !== null && preg_match('/\b(ai credits?|credits dashboard|credit usage|ai usage dashboard)\b/i', $prompt) === 1) { return 'ai_credits_dashboard_premium'; }
        if ($prompt !== null && preg_match('/\b(generation process|ai generation steps|generation stages|how ai generates)\b/i', $prompt) === 1) { return 'ai_generation_process_premium'; }
        if ($prompt !== null && preg_match('/\b(ai statistics|ai stats|ai metrics|ai usage metrics|generation metrics)\b/i', $prompt) === 1) { return 'ai_statistics_premium'; }
        if ($prompt !== null && preg_match('/\b(prompt examples?|sample prompts?|ai prompt ideas|prompt library)\b/i', $prompt) === 1) { return 'ai_prompt_examples_premium'; }
        if ($category === 'blog' && $prompt !== null && preg_match('/\b(magazine|editorial magazine|journal layout)\b/i', $prompt) === 1) { return 'blog_magazine_premium'; }
        if ($category === 'blog' && $prompt !== null && preg_match('/\b(featured article|feature story|lead article)\b/i', $prompt) === 1) { return 'blog_featured_article_premium'; }
        if ($category === 'blog' && $prompt !== null && preg_match('/\b(editor.?s pick|editors pick|curated reads)\b/i', $prompt) === 1) { return 'blog_editors_pick_premium'; }
        if ($category === 'blog' && $prompt !== null && preg_match('/\b(sidebar news|news sidebar|news layout|latest news)\b/i', $prompt) === 1) { return 'blog_sidebar_news_premium'; }
        if ($category === 'blog' && $prompt !== null && preg_match('/\b(newsletter|subscribe|email updates)\b/i', $prompt) === 1) { return 'blog_newsletter_premium'; }
        if ($category === 'blog' && $prompt !== null && preg_match('/\b(trending|popular posts|featured reads)\b/i', $prompt) === 1) { return 'blog_trending_premium'; }
        if ($category === 'blog' && $prompt !== null && preg_match('/\b(categories grid|browse categories|topics grid|blog categories)\b/i', $prompt) === 1) { return 'blog_categories_grid_premium'; }
        if ($category === 'blog' && $prompt !== null && preg_match('/\b(author profile|about the author|writer profile|editor profile)\b/i', $prompt) === 1) { return 'blog_author_profile_premium'; }
        if ($category === 'team' && $prompt !== null && preg_match('/\b(open positions|open roles|careers|jobs|hiring|vacancies)\b/i', $prompt) === 1) { return 'team_open_positions_premium'; }
        if ($category === 'team' && $prompt !== null && preg_match('/\b(team culture|work culture|company culture|culture values)\b/i', $prompt) === 1) { return 'team_culture_premium'; }
        if ($category === 'team' && $prompt !== null && preg_match('/\b(organization chart|org chart|team hierarchy)\b/i', $prompt) === 1) { return 'team_org_chart_premium'; }
        if ($category === 'team' && $prompt !== null && preg_match('/\b(leadership|leadership team|executive team)\b/i', $prompt) === 1) { return 'team_leadership_premium'; }
        if ($category === 'team' && $prompt !== null && preg_match('/\b(team timeline|people timeline|team journey)\b/i', $prompt) === 1) { return 'team_timeline_premium'; }
        if ($category === 'team' && $prompt !== null && preg_match('/\b(team cards|team grid|people cards)\b/i', $prompt) === 1) { return 'team_cards_premium'; }
        if ($category === 'pricing' && $prompt !== null && preg_match('/\b(comparison table|compare plans|pricing matrix|feature matrix)\b/i', $prompt) === 1) { return 'pricing_comparison_premium'; }
        if ($category === 'pricing' && $prompt !== null && preg_match('/\b(monthly yearly|monthly \/ yearly|billing toggle|annual billing|yearly pricing)\b/i', $prompt) === 1) { return 'pricing_toggle_premium'; }
        if ($category === 'pricing' && $prompt !== null && preg_match('/\b(enterprise pricing|custom pricing|talk to sales|enterprise plan)\b/i', $prompt) === 1) { return 'pricing_enterprise_premium'; }
        if ($category === 'pricing' && $prompt !== null && preg_match('/\b(pricing calculator|price calculator|cost estimator|pricing estimator|estimate price)\b/i', $prompt) === 1) { return 'pricing_calculator_premium'; }
        if ($category === 'pricing' && $prompt !== null && preg_match('/\b(credit pricing|credit packs|credits pack|prepaid credits|usage credits|token packs)\b/i', $prompt) === 1) { return 'pricing_credit_premium'; }
        if ($category === 'pricing' && $prompt !== null && preg_match('/\b(agency pricing|agency plans|client sites|client capacity|agency package)\b/i', $prompt) === 1) { return 'pricing_agency_premium'; }
        if ($category === 'pricing' && $prompt !== null && preg_match('/\b(feature matrix|capability matrix|detailed features|plan features)\b/i', $prompt) === 1) { return 'pricing_feature_matrix_premium'; }
        if ($category === 'testimonials' && $prompt !== null && preg_match('/\b(video testimonial|customer video|video review)\b/i', $prompt) === 1) { return 'testimonials_video_premium'; }
        if ($category === 'testimonials' && $prompt !== null && preg_match('/\b(marquee|scrolling testimonials|testimonial rail)\b/i', $prompt) === 1) { return 'testimonials_scrolling_marquee'; }
        if ($category === 'testimonials' && $prompt !== null && preg_match('/\b(wall of love|testimonial wall|review wall)\b/i', $prompt) === 1) { return 'testimonials_wall_of_love'; }
        if ($category === 'testimonials' && $prompt !== null && preg_match('/\b(card stack|stacked testimonials|stacked reviews)\b/i', $prompt) === 1) { return 'testimonials_card_stack'; }
        if ($category === 'testimonials' && $prompt !== null && preg_match('/\b(trust dashboard|review dashboard|social proof dashboard)\b/i', $prompt) === 1) { return 'testimonials_trust_dashboard'; }
        if ($category === 'testimonials' && $prompt !== null && preg_match('/\b(review grid|testimonial grid|reviews grid)\b/i', $prompt) === 1) { return 'testimonials_review_grid'; }
        if ($category === 'testimonials' && $prompt !== null && preg_match('/\b(review carousel pro|premium review carousel|featured reviews carousel)\b/i', $prompt) === 1) { return 'testimonials_review_carousel_pro'; }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(sticky|scroll story|detailed services|service chapters)\b/i', $prompt) === 1) { return 'services_sticky_scroll'; }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(horizontal|sideways|scrolling rail|service rail)\b/i', $prompt) === 1) { return 'services_horizontal'; }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(tabs|tabbed|interactive tabs|service tabs)\b/i', $prompt) === 1) { return 'services_interactive_tabs'; }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(mega grid|many services|full capability|capability grid)\b/i', $prompt) === 1) { return 'services_mega_grid'; }
        if ($category === 'about' && $prompt !== null && preg_match('/\b(founder|founder story|our founder|origin story)\b/i', $prompt) === 1) {
            return 'about_founder_story';
        }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(sticky|scroll story|detailed services|service chapters)\b/i', $prompt) === 1) { return 'services_sticky_scroll'; }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(horizontal|sideways|scrolling rail|service rail)\b/i', $prompt) === 1) { return 'services_horizontal'; }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(tabs|tabbed|interactive tabs|service tabs)\b/i', $prompt) === 1) { return 'services_interactive_tabs'; }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(mega grid|many services|full capability|capability grid)\b/i', $prompt) === 1) { return 'services_mega_grid'; }
        if ($category === 'about' && $prompt !== null && preg_match('/\b(mission|vision|values|purpose)\b/i', $prompt) === 1) {
            return 'about_mission_grid';
        }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(sticky|scroll story|detailed services|service chapters)\b/i', $prompt) === 1) { return 'services_sticky_scroll'; }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(horizontal|sideways|scrolling rail|service rail)\b/i', $prompt) === 1) { return 'services_horizontal'; }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(tabs|tabbed|interactive tabs|service tabs)\b/i', $prompt) === 1) { return 'services_interactive_tabs'; }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(mega grid|many services|full capability|capability grid)\b/i', $prompt) === 1) { return 'services_mega_grid'; }
        if ($category === 'about' && $prompt !== null && preg_match('/\b(company stats|company statistics|numbers|metrics|proof|milestones in numbers)\b/i', $prompt) === 1) {
            return 'about_interactive_stats';
        }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(sticky|scroll story|detailed services|service chapters)\b/i', $prompt) === 1) { return 'services_sticky_scroll'; }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(horizontal|sideways|scrolling rail|service rail)\b/i', $prompt) === 1) { return 'services_horizontal'; }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(tabs|tabbed|interactive tabs|service tabs)\b/i', $prompt) === 1) { return 'services_interactive_tabs'; }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(mega grid|many services|full capability|capability grid)\b/i', $prompt) === 1) { return 'services_mega_grid'; }
        if ($category === 'about' && $prompt !== null && preg_match('/\b(office|studio|workspace|workplace|gallery|inside the studio|our space|showroom|clinic interior)\b/i', $prompt) === 1) {
            return 'about_office_gallery';
        }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(sticky|scroll story|detailed services|service chapters)\b/i', $prompt) === 1) { return 'services_sticky_scroll'; }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(horizontal|sideways|scrolling rail|service rail)\b/i', $prompt) === 1) { return 'services_horizontal'; }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(tabs|tabbed|interactive tabs|service tabs)\b/i', $prompt) === 1) { return 'services_interactive_tabs'; }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(mega grid|many services|full capability|capability grid)\b/i', $prompt) === 1) { return 'services_mega_grid'; }
        if ($category === 'about' && $prompt !== null && preg_match('/\b(award|awards|recognition|recognitions|honours|honors|shortlisted|certification)\b/i', $prompt) === 1) {
            return 'about_awards_timeline';
        }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(sticky|scroll story|detailed services|service chapters)\b/i', $prompt) === 1) { return 'services_sticky_scroll'; }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(horizontal|sideways|scrolling rail|service rail)\b/i', $prompt) === 1) { return 'services_horizontal'; }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(tabs|tabbed|interactive tabs|service tabs)\b/i', $prompt) === 1) { return 'services_interactive_tabs'; }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(mega grid|many services|full capability|capability grid)\b/i', $prompt) === 1) { return 'services_mega_grid'; }
        if ($category === 'about' && $prompt !== null && preg_match('/\b(culture|how we work|working principles|team values|behaviours|behaviors)\b/i', $prompt) === 1) {
            return 'about_culture_section';
        }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(sticky|scroll story|detailed services|service chapters)\b/i', $prompt) === 1) { return 'services_sticky_scroll'; }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(horizontal|sideways|scrolling rail|service rail)\b/i', $prompt) === 1) { return 'services_horizontal'; }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(tabs|tabbed|interactive tabs|service tabs)\b/i', $prompt) === 1) { return 'services_interactive_tabs'; }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(mega grid|many services|full capability|capability grid)\b/i', $prompt) === 1) { return 'services_mega_grid'; }
        if ($category === 'about' && $prompt !== null && preg_match('/\b(brand journey|brand evolution|identity evolution|brand story|rebrand|positioning evolution)\b/i', $prompt) === 1) {
            return 'about_brand_journey';
        }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(sticky|scroll story|detailed services|service chapters)\b/i', $prompt) === 1) { return 'services_sticky_scroll'; }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(horizontal|sideways|scrolling rail|service rail)\b/i', $prompt) === 1) { return 'services_horizontal'; }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(tabs|tabbed|interactive tabs|service tabs)\b/i', $prompt) === 1) { return 'services_interactive_tabs'; }
        if ($category === 'services' && $prompt !== null && preg_match('/\b(mega grid|many services|full capability|capability grid)\b/i', $prompt) === 1) { return 'services_mega_grid'; }
        if ($category === 'about' && $prompt !== null && preg_match('/\b(timeline|history|journey|our story|milestones)\b/i', $prompt) === 1) {
            return 'about_timeline_story';
        }

        if ($category === 'stats' && $prompt !== null && preg_match('/\b(revenue|mrr|arr|sales dashboard|commercial dashboard|financial dashboard)\b/i', $prompt) === 1) { return 'stats_revenue_dashboard_premium'; }
        if ($category === 'stats' && $prompt !== null && preg_match('/\b(growth chart|growth charts|trend|trends|chart|trajectory)\b/i', $prompt) === 1) { return 'stats_growth_charts_premium'; }
        if ($category === 'stats' && $prompt !== null && preg_match('/\b(global presence|global reach|markets served|regions served|countries served|office locations|locations worldwide)\b/i', $prompt) === 1) { return 'stats_global_presence_premium'; }
        if ($category === 'stats' && $prompt !== null && preg_match('/\b(timeline metrics|metrics timeline|historical metrics|progress over time|year over year|period by period)\b/i', $prompt) === 1) { return 'stats_timeline_metrics_premium'; }
        if ($category === 'stats' && $prompt !== null && preg_match('/\b(achievement|achievements|milestone|milestones|recognition|recognitions)\b/i', $prompt) === 1) { return 'stats_achievements_premium'; }
        if ($category === 'stats' && $prompt !== null && preg_match('/\b(animated counter|animated counters|counter|counters|numbers|metrics)\b/i', $prompt) === 1) { return 'stats_animated_counters_premium'; }

        $sections = [
            'hero' => [
                'hero_headline',
                'hero_background_image',
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
                'hero_video_background',
                'hero_floating_cards',
            ],
            'services' => ['services_cards', 'services_bento', 'services_bento_premium', 'services_pricing_comparison', 'services_feature_comparison', 'services_hover_cards', 'services_sticky_scroll', 'services_horizontal', 'services_interactive_tabs', 'services_mega_grid'],
            'about' => ['about_timeline_story', 'about_founder_story', 'about_mission_grid', 'about_interactive_stats', 'about_brand_journey', 'about_awards_timeline', 'about_culture_section', 'about_office_gallery'],
            'portfolio' => ['case_studies_grid', 'portfolio_masonry', 'portfolio_pinterest', 'portfolio_hover_video', 'portfolio_case_study', 'portfolio_before_after', 'portfolio_filterable', 'portfolio_animated', 'portfolio_project_timeline'],
            'feature' => ['feature_image_left', 'feature_image_right'],
            'pricing' => ['pricing_cards', 'pricing_comparison_premium', 'pricing_toggle_premium', 'pricing_enterprise_premium', 'pricing_calculator_premium', 'pricing_credit_premium', 'pricing_agency_premium', 'pricing_feature_matrix_premium'],
            'testimonials' => ['testimonials_carousel', 'testimonials_video_premium', 'testimonials_scrolling_marquee', 'testimonials_wall_of_love', 'testimonials_card_stack', 'testimonials_trust_dashboard', 'testimonials_review_grid', 'testimonials_review_carousel_pro'],
            'process' => ['process_timeline'],
            'stats' => ['stats_modern', 'stats_animated_counters_premium', 'stats_revenue_dashboard_premium', 'stats_growth_charts_premium', 'stats_achievements_premium', 'stats_global_presence_premium', 'stats_timeline_metrics_premium'],
            'team' => ['team_modern', 'team_cards_premium', 'team_timeline_premium', 'team_org_chart_premium', 'team_leadership_premium', 'team_culture_premium', 'team_open_positions_premium'],
            'cta' => ['hero_centered_cta', 'image_cta_banner', 'cta_glass_premium', 'cta_gradient_premium', 'cta_newsletter_premium', 'cta_book_demo_premium', 'cta_calendly_premium', 'cta_free_trial_premium', 'cta_countdown_premium', 'cta_limited_offer_premium'],
            'faq' => ['faq_accordion', 'faq_accordion_pro', 'faq_search_premium', 'faq_categories_premium', 'faq_support_portal_premium', 'faq_documentation_premium'],
            'contact' => ['contact_form_modern', 'contact_details', 'location_map', 'contact_split_premium', 'contact_map_premium', 'contact_appointment_premium', 'contact_support_center_premium', 'contact_faq_premium', 'contact_multistep_premium', 'contact_live_chat_premium'],
            'location' => ['location_map'],
            'case_studies' => ['case_studies_grid'],
            'careers' => ['jobs_list'],
            'jobs' => ['jobs_list'],
            'events' => ['events_grid'],
            'blog' => ['blog_hub', 'blog_magazine_premium', 'blog_featured_article_premium', 'blog_editors_pick_premium', 'blog_sidebar_news_premium', 'blog_newsletter_premium', 'blog_trending_premium', 'blog_categories_grid_premium', 'blog_author_profile_premium'],
        ];

        $candidates = $sections[$category] ?? [];

        if ($candidates === []) {
            throw new \InvalidArgumentException('Unsupported section category.');
        }

        return $candidates[array_rand($candidates)];
    }
}
