<?php

return [
    'version' => 2,

    // New per-instance storage. Existing Sparks continue to render their
    // hard-coded classes until later migration batches populate this field.
    'storage_key' => 'luna_tailwind_schema',

    // Every Spark may declare only the slots it actually renders. These are
    // canonical names Luna can reason about across otherwise unrelated Sparks.
    'canonical_slots' => [
        'section', 'container', 'wrapper', 'inner', 'grid', 'stack',
        'eyebrow', 'heading', 'subheading', 'body', 'text', 'label',
        'media', 'image', 'video', 'overlay',
        'card', 'card_media', 'card_body', 'card_heading', 'card_text',
        'button', 'primary_button', 'secondary_button', 'icon',
        'collection', 'item', 'divider', 'badge', 'form', 'field', 'input',
    ],

    // A slot can also be Spark-specific (for example "pricing_feature_icon").
    // Keep identifiers deterministic so Luna can address them safely.
    'slot_pattern' => '/^[a-z][a-z0-9_]{0,63}$/',

    // Tailwind variants accepted by the schema parser. Chained variants such
    // as md:hover: are supported as long as every prefix is listed here.
    'variants' => [
        'sm', 'md', 'lg', 'xl', '2xl',
        'hover', 'focus', 'focus-visible', 'focus-within', 'active', 'visited',
        'disabled', 'checked', 'required', 'invalid', 'placeholder-shown',
        'first', 'last', 'odd', 'even', 'empty',
        'group-hover', 'group-focus', 'peer-checked', 'peer-focus',
        'dark', 'motion-safe', 'motion-reduce', 'print',
        'portrait', 'landscape',
    ],

    // Utilities that are globally protected by the existing mutation engine.
    // Keep this list intentionally small: layout utilities can still be changed
    // by explicit structural operations in later batches.
    'protected_utility_prefixes' => [
        'sr-only', 'not-sr-only', 'contents',
    ],

    // V2 structural classifier. Migration batches can copy matching utilities
    // into a style definition's `base`/`protected` bucket so ordinary visual
    // edits cannot accidentally remove renderer invariants (for example an
    // overlay's absolute positioning). Classification alone does NOT globally
    // lock these utilities.
    'structural_utility_prefixes' => [
        'static', 'fixed', 'absolute', 'relative', 'sticky',
        'inset', 'inset-x', 'inset-y', 'top', 'right', 'bottom', 'left',
        'z', 'isolate', 'isolation',
        'block', 'inline-block', 'inline', 'flex', 'inline-flex', 'grid', 'inline-grid', 'hidden',
        'overflow', 'overflow-x', 'overflow-y',
        'pointer-events',
    ],

    // Disallow class payloads that could escape into markup or arbitrary CSS.
    // Tailwind arbitrary values remain supported, but unsafe characters and
    // executable/protocol-like payloads are rejected by the validator.
    'forbidden_fragments' => [
        '<', '"', "'", '`', ';', '{', '}', '\\',
        'javascript:', 'data:text/html', 'expression(', '@import', '</',
    ],

    // Batch 3: primary Hero family is now addressable through per-instance
    // Tailwind slots in the React renderer. Animated experimental hero packs
    // remain on the compatibility bridge and are migrated in the final hero pass.
    'migration_families' => [
        'hero_core' => [
            'state' => 'schema_backed',
            'types' => [
                'hero_headline', 'hero_background_image', 'hero_split_image',
                'hero_floating_cards', 'hero_floating_glass', 'hero_luxury_fullscreen',
                'hero_agency_showcase', 'hero_saas_dashboard', 'hero_parallax',
                'hero_editorial_overlay', 'hero_video_premium', 'hero_bento_premium',
                'hero_slider_fade', 'hero_centered_cta', 'hero_split_editorial',
                'hero_video_style', 'hero_video_background', 'hero_ai_conversation',
                'image_cta_banner',
            ],
        ],
        'services_features_about_general' => [
            'state' => 'schema_backed',
            'types' => [
                'services_bento', 'services_bento_premium', 'services_cards',
                'services_feature_comparison', 'services_hover_cards', 'services_pricing_comparison',
                'services_editorial_premium', 'services_showcase_premium', 'services_minimal_luxury',
                'services_contrast_premium', 'services_split_premium', 'services_grid_premium',
                'services_feature_premium', 'services_sticky_scroll', 'services_horizontal',
                'services_interactive_tabs', 'services_mega_grid',
                'feature_image_left', 'feature_image_right',
                'about_timeline_story', 'about_founder_story', 'about_mission_grid',
                'about_interactive_stats', 'about_brand_journey', 'about_awards_timeline',
                'about_culture_section', 'about_office_gallery',
                'mini_hero_minimal', 'mini_hero_split', 'mini_hero_promo',
                'luna_custom_section',
            ],
        ],

        'pricing_stats_process_cta_lead' => [
            'state' => 'schema_backed',
            'types' => [
                'pricing_cards', 'pricing_comparison_premium', 'pricing_toggle_premium',
                'pricing_enterprise_premium', 'pricing_calculator_premium', 'pricing_credit_premium',
                'pricing_agency_premium', 'pricing_feature_matrix_premium',
                'process_timeline', 'stats_modern', 'stats_animated_counters_premium',
                'stats_revenue_dashboard_premium', 'stats_growth_charts_premium',
                'stats_achievements_premium', 'stats_global_presence_premium',
                'stats_timeline_metrics_premium',
                'cta_glass_premium', 'cta_gradient_premium', 'cta_newsletter_premium',
                'cta_book_demo_premium', 'cta_calendly_premium', 'cta_free_trial_premium',
                'cta_countdown_premium', 'cta_limited_offer_premium',
                'faq_documentation_premium', 'lead_magnet_premium', 'lead_free_audit_premium',
                'lead_website_audit_premium', 'lead_quote_form_premium',
                'lead_roi_calculator_premium', 'lead_cost_calculator_premium',
                'lead_consultation_booking_premium',
                'sales_comparison_premium', 'sales_feature_matrix_premium',
                'sales_competitor_comparison_premium', 'sales_roi_premium',
                'sales_guarantee_premium', 'sales_trust_premium', 'sales_integrations_premium',
            ],
        ],

        'testimonials_team_portfolio_faq_contact' => [
            'state' => 'schema_backed',
            'types' => [
                'testimonials_carousel', 'testimonials_video_premium', 'testimonials_scrolling_marquee',
                'testimonials_wall_of_love', 'testimonials_card_stack', 'testimonials_trust_dashboard',
                'testimonials_review_grid', 'testimonials_review_carousel_pro',
                'team_modern', 'team_cards_premium', 'team_timeline_premium', 'team_org_chart_premium',
                'team_leadership_premium', 'team_culture_premium', 'team_open_positions_premium',
                'portfolio_masonry', 'portfolio_pinterest', 'portfolio_hover_video', 'portfolio_case_study',
                'portfolio_before_after', 'portfolio_filterable', 'portfolio_animated', 'portfolio_project_timeline',
                'faq_accordion', 'faq_accordion_pro', 'faq_search_premium', 'faq_categories_premium',
                'faq_support_portal_premium',
                'contact_details', 'contact_form_modern', 'location_map', 'contact_split_premium',
                'contact_map_premium', 'contact_appointment_premium', 'contact_support_center_premium',
                'contact_faq_premium', 'contact_multistep_premium', 'contact_live_chat_premium',
            ],
        ],

        // Batch 7: remaining complex customer-facing families. BlogHub's
        // builder/composer chrome is intentionally excluded and remains on the
        // legacy bridge until the final parity audit separates editor UI slots.
        'batch10_final_cleanup' => [
            'state' => 'schema_backed',
            'types' => [
                'jobs_list', 'events_grid', 'case_studies_grid', 'blog_hub',
                'footer_mega_premium', 'footer_agency_premium', 'footer_saas_premium',
                'footer_luxury_premium', 'footer_dark_premium',
            ],
        ],

        'blog_content_commerce_agency_ai_expansion_animated' => [
            'state' => 'schema_backed',
            'types' => [
                'about_chapter_index_premium',                 'about_founder_visual_premium',                 'about_image_manifesto_premium',                 'about_journey_gallery_premium',
                'about_story_collage_premium',                 'agency_client_portal_premium',                 'agency_client_reviews_premium',                 'agency_dashboard_preview_premium',
                'agency_maintenance_plans_premium',                 'agency_project_pipeline_premium',                 'agency_support_plans_premium',                 'agency_website_management_premium',
                'agency_website_reports_premium',                 'agency_white_label_showcase_premium',                 'agency_workflow_premium',                 'ai_assistant_premium',
                'ai_automation_premium',                 'ai_builder_premium',                 'ai_credits_dashboard_premium',                 'ai_generation_process_premium',
                'ai_prompt_examples_premium',                 'ai_prompt_showcase_premium',                 'ai_statistics_premium',                 'ai_timeline_premium',
                'ai_workflow_premium',                 'blog_author_profile_premium',                 'blog_categories_grid_premium',                 'blog_editors_pick_premium',
                'blog_featured_article_premium',                 'blog_magazine_premium',                 'blog_mini_hero',                 'blog_newsletter_premium',
                'blog_sidebar_news_premium',                 'blog_trending_premium',                 'brand_value_cards_premium',                 'brand_visual_principles_premium',
                'commerce_benefits_strip',                 'commerce_cart_classic',                 'commerce_cart_compact',                 'commerce_cart_split',
                'commerce_catalog_compact',                 'commerce_catalog_editorial',                 'commerce_catalog_grid',                 'commerce_categories',
                'commerce_checkout_classic',                 'commerce_checkout_express',                 'commerce_checkout_split',                 'commerce_featured_collection',
                'commerce_featured_products',                 'commerce_mini_cart',                 'commerce_price',                 'commerce_product_gallery',
                'commerce_product_grid',                 'commerce_promo_split',                 'commerce_related_products',                 'commerce_variation_selector',
                'construction_capability_split_premium',                 'construction_project_cards_premium',                 'construction_service_photos_premium',                 'construction_site_progress_premium',
                'contact_availability_board_premium',                 'contact_editorial_split_premium',                 'contact_image_form_premium',                 'contact_office_cards_premium',
                'contact_visual_inquiry_premium',                 'content_asymmetric_story_premium',                 'content_editorial_image_stack_premium',                 'content_events_grid',
                'content_featured_entry',                 'content_grid_classic',                 'content_grid_compact',                 'content_grid_editorial',
                'content_latest_entries',                 'content_media_manifesto_premium',                 'content_visual_quote_premium',                 'cta_background_media_premium',
                'cta_editorial_banner_premium',                 'cta_floating_panel_premium',                 'cta_image_split_premium',                 'cta_media_cards_premium',
                'cta_ticket_premium',                 'dental_clinic_story_premium',                 'dental_treatment_cards_premium',                 'faq_decision_tree_premium',
                'features_asymmetric_media_premium',                 'features_editorial_mosaic_premium',                 'features_fullbleed_panels_premium',                 'features_image_index_premium',
                'features_image_stack_premium',                 'features_media_steps_premium',                 'features_overlap_cards_premium',                 'features_spotlight_cards_premium',
                'features_visual_split_premium',                 'gallery_asymmetric_premium',                 'gallery_editorial_grid_premium',                 'gallery_image_rail_premium',
                'gallery_story_tiles_premium',                 'hero_3d_tilt_product_premium',                 'hero_app_screens_carousel_premium',                 'hero_aurora_motion_premium',
                'hero_before_after_premium',                 'hero_cinematic_slider_premium',                 'hero_crossfade_gallery_premium',                 'hero_curtain_reveal_premium',
                'hero_device_showcase_premium',                 'hero_editorial_image_sequence_premium',                 'hero_floating_cards_motion_premium',                 'hero_glass_orb_premium',
                'hero_grid_pulse_tech_premium',                 'hero_image_mask_reveal_premium',                 'hero_infinite_marquee_premium',                 'hero_interactive_bento_premium',
                'hero_ken_burns_premium',                 'hero_light_trails_premium',                 'hero_mesh_gradient_motion_premium',                 'hero_mouse_parallax_premium',
                'hero_parallax_layers_premium',                 'hero_particle_constellation_premium',                 'hero_perspective_carousel_premium',                 'hero_pinned_story_premium',
                'hero_reveal_parallax_premium',                 'hero_rotating_words_premium',                 'hero_scroll_morph_premium',                 'hero_split_slider_premium',
                'hero_spotlight_cursor_premium',                 'hero_stacked_cards_premium',                 'hero_typewriter_premium',                 'hero_vertical_story_premium',
                'hero_video_cinematic_premium',                 'hero_video_split_premium',                 'hero_zoom_scroll_premium',                 'hotel_experience_cards_premium',
                'hotel_room_collection_premium',                 'latest_resources',                 'location_city_spotlight_premium',                 'location_multi_office_premium',
                'location_photo_cards_premium',                 'location_visual_directory_premium',                 'medical_care_pathways_premium',                 'medical_facility_showcase_premium',
                'newsletter_cta',                 'portfolio_cinematic_grid_premium',                 'portfolio_editorial_cards_premium',                 'portfolio_fullbleed_projects_premium',
                'portfolio_image_index_premium',                 'portfolio_media_ledger_premium',                 'portfolio_project_panels_premium',                 'portfolio_split_showcase_premium',
                'portfolio_staggered_gallery_premium',                 'portfolio_story_index_premium',                 'process_constellation_premium',                 'process_editorial_journey_premium',
                'process_image_timeline_premium',                 'process_media_roadmap_premium',                 'process_numbered_panels_premium',                 'process_visual_steps_premium',
                'proof_case_story_premium',                 'proof_metric_gallery_premium',                 'proof_metric_staircase_premium',                 'realestate_agent_story_premium',
                'realestate_featured_listing_premium',                 'realestate_neighborhood_cards_premium',                 'realestate_property_grid_premium',                 'restaurant_atmosphere_gallery_premium',
                'restaurant_reservation_cta_premium',                 'restaurant_signature_dishes_premium',                 'restaurant_story_menu_premium',                 'services_editorial_rows_premium',
                'services_fullbleed_overlay_premium',                 'services_horizontal_media_premium',                 'services_image_accordion_premium',                 'services_image_trio_premium',
                'services_mosaic_media_premium',                 'services_numbered_images_premium',                 'services_orbit_map_premium',                 'services_overlay_grid_premium',
                'services_staggered_media_premium',                 'services_visual_directory_premium',                 'stats_editorial_numbers_premium',                 'stats_image_panels_premium',
                'stats_photo_metrics_premium',                 'stats_visual_mosaic_premium',                 'team_image_grid_premium',                 'team_leadership_split_premium',
                'team_people_mosaic_premium',                 'team_portrait_editorial_premium',                 'team_profile_overlay_premium',                 'testimonials_client_spotlight_premium',
                'testimonials_editorial_quotes_premium',                 'testimonials_featured_story_premium',                 'testimonials_image_wall_premium',                 'testimonials_portrait_cards_premium',
                'travel_destination_story_premium',                 'travel_itinerary_visual_premium',                 'trust_certification_cards_premium',                 'trust_evidence_ledger_premium',
                'trust_image_proof_premium',                 'trust_logo_story_premium',                 'trust_partner_showcase_premium',
            ],
        ],

    ],

    'limits' => [
        // Legacy flat slots remain supported during migration.
        'max_slots' => 160,

        // V2 shared styles + per-item/nested-item override guards.
        'max_styles_per_scope' => 160,
        'max_collections_per_scope' => 24,
        'max_items_per_collection' => 64,
        'max_collection_depth' => 4,

        'max_classes_per_slot' => 160,
        'max_class_length' => 180,
    ],
];
