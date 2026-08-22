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
            'hero_ken_burns_premium' => ['category' => 'hero', 'description' => 'Pro-only cinematic image hero with slow Ken Burns pan-and-zoom motion for luxury, hospitality, property, photography, and editorial brands.'],
            'hero_crossfade_gallery_premium' => ['category' => 'hero', 'description' => 'Pro-only gallery hero that softly crossfades three images for portfolios, photography, hospitality, travel, and visual brands.'],
            'hero_cinematic_slider_premium' => ['category' => 'hero', 'description' => 'Pro-only fullscreen cinematic image slider with progress cues for launches, campaigns, property, travel, automotive, and premium brands.'],
            'hero_split_slider_premium' => ['category' => 'hero', 'description' => 'Pro-only split-screen hero with anchored conversion copy and rotating imagery for SaaS, agencies, consulting, products, and services.'],
            'hero_vertical_story_premium' => ['category' => 'hero', 'description' => 'Pro-only editorial hero with vertical image transitions for portfolios, fashion, architecture, agencies, and storytelling brands.'],
            'hero_parallax_layers_premium' => ['category' => 'hero', 'description' => 'Pro-only layered parallax hero with multiple visual depth planes that respond to scrolling.'],
            'hero_mouse_parallax_premium' => ['category' => 'hero', 'description' => 'Pro-only interactive layered hero with restrained cursor-responsive parallax depth.'],
            'hero_reveal_parallax_premium' => ['category' => 'hero', 'description' => 'Pro-only cinematic scroll-reveal hero for luxury, property, hospitality, editorial, and portfolio storytelling.'],
            'hero_zoom_scroll_premium' => ['category' => 'hero', 'description' => 'Pro-only immersive image hero with restrained scroll-driven zoom motion.'],
            'hero_pinned_story_premium' => ['category' => 'hero', 'description' => 'Pro-only sticky scrollytelling hero with three visual chapters for launches and premium brand narratives.'],
            'hero_video_cinematic_premium' => ['category' => 'hero', 'description' => 'Pro-only fullscreen muted background-video hero for premium brand films, hospitality, launches, and agencies.'],
            'hero_video_split_premium' => ['category' => 'hero', 'description' => 'Pro-only split-screen video hero for demos, product stories, showreels, and conversion-led campaigns.'],
            'hero_aurora_motion_premium' => ['category' => 'hero', 'description' => 'Pro-only lightweight animated aurora-gradient hero for AI, SaaS, fintech, and future-facing brands.'],
            'hero_mesh_gradient_motion_premium' => ['category' => 'hero', 'description' => 'Pro-only animated mesh-gradient hero for modern SaaS, startup, product, and technology launches.'],
            'hero_spotlight_cursor_premium' => ['category' => 'hero', 'description' => 'Pro-only cursor spotlight hero for SaaS, AI, design, and premium digital products.'],
            'hero_floating_cards_motion_premium' => ['category' => 'hero', 'description' => 'Pro-only floating card hero for SaaS dashboards, product proof, metrics, and app launches.'],
            'hero_3d_tilt_product_premium' => ['category' => 'hero', 'description' => 'Pro-only pointer-responsive 3D product showcase for SaaS, apps, software, and digital tools.'],
            'hero_infinite_marquee_premium' => ['category' => 'hero', 'description' => 'Pro-only moving typography hero for agencies, creative studios, events, campaigns, and editorial brands.'],
            'hero_rotating_words_premium' => ['category' => 'hero', 'description' => 'Pro-only rotating keyword headline hero for SaaS, services, startups, and multi-benefit offers.'],
            'hero_typewriter_premium' => ['category' => 'hero', 'description' => 'Pro-only typewriter headline hero for AI, developer tools, software, creators, and product launches.'],
            'hero_curtain_reveal_premium' => ['category' => 'hero', 'description' => 'Pro-only cinematic curtain reveal hero for luxury, property, hospitality, automotive, fashion, and campaigns.'],
            'hero_image_mask_reveal_premium' => ['category' => 'hero', 'description' => 'Pro-only image mask reveal hero for fashion, hospitality, architecture, editorial, and premium visual brands.'],
            'hero_stacked_cards_premium' => ['category' => 'hero', 'description' => 'Pro-only layered card stack hero for portfolios, products, services, and visual proof.'],
            'hero_perspective_carousel_premium' => ['category' => 'hero', 'description' => 'Pro-only perspective carousel hero for portfolios, products, property, and campaigns.'],
            'hero_before_after_premium' => ['category' => 'hero', 'description' => 'Pro-only draggable before-and-after comparison hero for transformation-led services and visual results.'],
            'hero_scroll_morph_premium' => ['category' => 'hero', 'description' => 'Pro-only scroll morph hero that expands framed artwork into a cinematic visual stage.'],
            'hero_glass_orb_premium' => ['category' => 'hero', 'description' => 'Pro-only lightweight glass-orb hero for AI, fintech, beauty, luxury technology, and premium services.'],
            'hero_particle_constellation_premium' => ['category' => 'hero', 'description' => 'Pro-only lightweight particle constellation hero for AI, science, cybersecurity, data, and technology brands.'],
            'hero_grid_pulse_tech_premium' => ['category' => 'hero', 'description' => 'Pro-only animated technical grid hero for AI, cyber, data, and developer brands.'],
            'hero_light_trails_premium' => ['category' => 'hero', 'description' => 'Pro-only luminous light-trail hero for technology, premium launches, and campaigns.'],
            'hero_device_showcase_premium' => ['category' => 'hero', 'description' => 'Pro-only floating device showcase for SaaS, apps, software, and digital products.'],
            'hero_app_screens_carousel_premium' => ['category' => 'hero', 'description' => 'Pro-only rotating app-screen showcase for mobile apps and product-led websites.'],
            'hero_editorial_image_sequence_premium' => ['category' => 'hero', 'description' => 'Pro-only editorial image sequence for fashion, hospitality, architecture, and creative brands.'],
            'hero_interactive_bento_premium' => ['category' => 'hero', 'description' => 'Pro-only interactive bento hero for agencies, products, portfolios, and visual services.'],

            'about_founder_visual_premium' => ['category' => 'about', 'description' => 'Pro-only premium about section using the about founder visual visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'about_image_manifesto_premium' => ['category' => 'about', 'description' => 'Pro-only premium about section using the about image manifesto visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'about_journey_gallery_premium' => ['category' => 'about', 'description' => 'Pro-only premium about section using the about journey gallery visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'about_story_collage_premium' => ['category' => 'about', 'description' => 'Pro-only premium about section using the about story collage visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'brand_value_cards_premium' => ['category' => 'about', 'description' => 'Pro-only premium about section using the brand value cards visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'brand_visual_principles_premium' => ['category' => 'about', 'description' => 'Pro-only premium about section using the brand visual principles visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'construction_capability_split_premium' => ['category' => 'services', 'description' => 'Pro-only premium services section using the construction capability split visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'construction_project_cards_premium' => ['category' => 'services', 'description' => 'Pro-only premium services section using the construction project cards visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'construction_service_photos_premium' => ['category' => 'services', 'description' => 'Pro-only premium services section using the construction service photos visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'construction_site_progress_premium' => ['category' => 'services', 'description' => 'Pro-only premium services section using the construction site progress visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'contact_editorial_split_premium' => ['category' => 'contact', 'description' => 'Pro-only premium contact section using the contact editorial split visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'contact_image_form_premium' => ['category' => 'contact', 'description' => 'Pro-only premium contact section using the contact image form visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'contact_office_cards_premium' => ['category' => 'contact', 'description' => 'Pro-only premium contact section using the contact office cards visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'contact_visual_inquiry_premium' => ['category' => 'contact', 'description' => 'Pro-only premium contact section using the contact visual inquiry visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'content_asymmetric_story_premium' => ['category' => 'content', 'description' => 'Pro-only premium content section using the content asymmetric story visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'content_editorial_image_stack_premium' => ['category' => 'content', 'description' => 'Pro-only premium content section using the content editorial image stack visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'content_media_manifesto_premium' => ['category' => 'content', 'description' => 'Pro-only premium content section using the content media manifesto visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'content_visual_quote_premium' => ['category' => 'content', 'description' => 'Pro-only premium content section using the content visual quote visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'cta_background_media_premium' => ['category' => 'cta', 'description' => 'Pro-only premium cta section using the cta background media visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'cta_editorial_banner_premium' => ['category' => 'cta', 'description' => 'Pro-only premium cta section using the cta editorial banner visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'cta_floating_panel_premium' => ['category' => 'cta', 'description' => 'Pro-only premium cta section using the cta floating panel visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'cta_image_split_premium' => ['category' => 'cta', 'description' => 'Pro-only premium cta section using the cta image split visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'cta_media_cards_premium' => ['category' => 'cta', 'description' => 'Pro-only premium cta section using the cta media cards visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'dental_clinic_story_premium' => ['category' => 'services', 'description' => 'Pro-only premium services section using the dental clinic story visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'dental_treatment_cards_premium' => ['category' => 'services', 'description' => 'Pro-only premium services section using the dental treatment cards visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'features_asymmetric_media_premium' => ['category' => 'feature', 'description' => 'Pro-only premium feature section using the features asymmetric media visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'features_editorial_mosaic_premium' => ['category' => 'feature', 'description' => 'Pro-only premium feature section using the features editorial mosaic visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'features_fullbleed_panels_premium' => ['category' => 'feature', 'description' => 'Pro-only premium feature section using the features fullbleed panels visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'features_image_index_premium' => ['category' => 'feature', 'description' => 'Pro-only premium feature section using the features image index visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'features_image_stack_premium' => ['category' => 'feature', 'description' => 'Pro-only premium feature section using the features image stack visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'features_media_steps_premium' => ['category' => 'feature', 'description' => 'Pro-only premium feature section using the features media steps visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'features_overlap_cards_premium' => ['category' => 'feature', 'description' => 'Pro-only premium feature section using the features overlap cards visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'features_spotlight_cards_premium' => ['category' => 'feature', 'description' => 'Pro-only premium feature section using the features spotlight cards visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'features_visual_split_premium' => ['category' => 'feature', 'description' => 'Pro-only premium feature section using the features visual split visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'gallery_asymmetric_premium' => ['category' => 'portfolio', 'description' => 'Pro-only premium portfolio section using the gallery asymmetric visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'gallery_editorial_grid_premium' => ['category' => 'portfolio', 'description' => 'Pro-only premium portfolio section using the gallery editorial grid visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'gallery_image_rail_premium' => ['category' => 'portfolio', 'description' => 'Pro-only premium portfolio section using the gallery image rail visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'gallery_story_tiles_premium' => ['category' => 'portfolio', 'description' => 'Pro-only premium portfolio section using the gallery story tiles visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'hotel_experience_cards_premium' => ['category' => 'services', 'description' => 'Pro-only premium services section using the hotel experience cards visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'hotel_room_collection_premium' => ['category' => 'services', 'description' => 'Pro-only premium services section using the hotel room collection visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'location_city_spotlight_premium' => ['category' => 'contact', 'description' => 'Pro-only premium contact section using the location city spotlight visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'location_multi_office_premium' => ['category' => 'contact', 'description' => 'Pro-only premium contact section using the location multi office visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'location_photo_cards_premium' => ['category' => 'contact', 'description' => 'Pro-only premium contact section using the location photo cards visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'location_visual_directory_premium' => ['category' => 'contact', 'description' => 'Pro-only premium contact section using the location visual directory visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'medical_care_pathways_premium' => ['category' => 'services', 'description' => 'Pro-only premium services section using the medical care pathways visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'medical_facility_showcase_premium' => ['category' => 'services', 'description' => 'Pro-only premium services section using the medical facility showcase visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'portfolio_cinematic_grid_premium' => ['category' => 'portfolio', 'description' => 'Pro-only premium portfolio section using the portfolio cinematic grid visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'portfolio_editorial_cards_premium' => ['category' => 'portfolio', 'description' => 'Pro-only premium portfolio section using the portfolio editorial cards visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'portfolio_fullbleed_projects_premium' => ['category' => 'portfolio', 'description' => 'Pro-only premium portfolio section using the portfolio fullbleed projects visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'portfolio_image_index_premium' => ['category' => 'portfolio', 'description' => 'Pro-only premium portfolio section using the portfolio image index visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'portfolio_media_ledger_premium' => ['category' => 'portfolio', 'description' => 'Pro-only premium portfolio section using the portfolio media ledger visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'portfolio_project_panels_premium' => ['category' => 'portfolio', 'description' => 'Pro-only premium portfolio section using the portfolio project panels visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'portfolio_split_showcase_premium' => ['category' => 'portfolio', 'description' => 'Pro-only premium portfolio section using the portfolio split showcase visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'portfolio_staggered_gallery_premium' => ['category' => 'portfolio', 'description' => 'Pro-only premium portfolio section using the portfolio staggered gallery visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'portfolio_story_index_premium' => ['category' => 'portfolio', 'description' => 'Pro-only premium portfolio section using the portfolio story index visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'process_editorial_journey_premium' => ['category' => 'process', 'description' => 'Pro-only premium process section using the process editorial journey visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'process_image_timeline_premium' => ['category' => 'process', 'description' => 'Pro-only premium process section using the process image timeline visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'process_media_roadmap_premium' => ['category' => 'process', 'description' => 'Pro-only premium process section using the process media roadmap visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'process_numbered_panels_premium' => ['category' => 'process', 'description' => 'Pro-only premium process section using the process numbered panels visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'process_visual_steps_premium' => ['category' => 'process', 'description' => 'Pro-only premium process section using the process visual steps visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'proof_case_story_premium' => ['category' => 'stats', 'description' => 'Pro-only premium stats section using the proof case story visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'proof_metric_gallery_premium' => ['category' => 'stats', 'description' => 'Pro-only premium stats section using the proof metric gallery visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'realestate_agent_story_premium' => ['category' => 'services', 'description' => 'Pro-only premium services section using the realestate agent story visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'realestate_featured_listing_premium' => ['category' => 'services', 'description' => 'Pro-only premium services section using the realestate featured listing visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'realestate_neighborhood_cards_premium' => ['category' => 'services', 'description' => 'Pro-only premium services section using the realestate neighborhood cards visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'realestate_property_grid_premium' => ['category' => 'services', 'description' => 'Pro-only premium services section using the realestate property grid visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'restaurant_atmosphere_gallery_premium' => ['category' => 'services', 'description' => 'Pro-only premium services section using the restaurant atmosphere gallery visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'restaurant_reservation_cta_premium' => ['category' => 'services', 'description' => 'Pro-only premium services section using the restaurant reservation cta visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'restaurant_signature_dishes_premium' => ['category' => 'services', 'description' => 'Pro-only premium services section using the restaurant signature dishes visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'restaurant_story_menu_premium' => ['category' => 'services', 'description' => 'Pro-only premium services section using the restaurant story menu visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'services_editorial_rows_premium' => ['category' => 'services', 'description' => 'Pro-only premium services section using the services editorial rows visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'services_fullbleed_overlay_premium' => ['category' => 'services', 'description' => 'Pro-only premium services section using the services fullbleed overlay visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'services_horizontal_media_premium' => ['category' => 'services', 'description' => 'Pro-only premium services section using the services horizontal media visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'services_image_accordion_premium' => ['category' => 'services', 'description' => 'Pro-only premium services section using the services image accordion visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'services_image_trio_premium' => ['category' => 'services', 'description' => 'Pro-only premium services section using the services image trio visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'services_mosaic_media_premium' => ['category' => 'services', 'description' => 'Pro-only premium services section using the services mosaic media visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'services_numbered_images_premium' => ['category' => 'services', 'description' => 'Pro-only premium services section using the services numbered images visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'services_overlay_grid_premium' => ['category' => 'services', 'description' => 'Pro-only premium services section using the services overlay grid visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'services_staggered_media_premium' => ['category' => 'services', 'description' => 'Pro-only premium services section using the services staggered media visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'services_visual_directory_premium' => ['category' => 'services', 'description' => 'Pro-only premium services section using the services visual directory visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'stats_editorial_numbers_premium' => ['category' => 'stats', 'description' => 'Pro-only premium stats section using the stats editorial numbers visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'stats_image_panels_premium' => ['category' => 'stats', 'description' => 'Pro-only premium stats section using the stats image panels visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'stats_photo_metrics_premium' => ['category' => 'stats', 'description' => 'Pro-only premium stats section using the stats photo metrics visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'stats_visual_mosaic_premium' => ['category' => 'stats', 'description' => 'Pro-only premium stats section using the stats visual mosaic visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'team_image_grid_premium' => ['category' => 'team', 'description' => 'Pro-only premium team section using the team image grid visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'team_leadership_split_premium' => ['category' => 'team', 'description' => 'Pro-only premium team section using the team leadership split visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'team_people_mosaic_premium' => ['category' => 'team', 'description' => 'Pro-only premium team section using the team people mosaic visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'team_portrait_editorial_premium' => ['category' => 'team', 'description' => 'Pro-only premium team section using the team portrait editorial visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'team_profile_overlay_premium' => ['category' => 'team', 'description' => 'Pro-only premium team section using the team profile overlay visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'testimonials_client_spotlight_premium' => ['category' => 'testimonials', 'description' => 'Pro-only premium testimonials section using the testimonials client spotlight visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'testimonials_editorial_quotes_premium' => ['category' => 'testimonials', 'description' => 'Pro-only premium testimonials section using the testimonials editorial quotes visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'testimonials_featured_story_premium' => ['category' => 'testimonials', 'description' => 'Pro-only premium testimonials section using the testimonials featured story visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'testimonials_image_wall_premium' => ['category' => 'testimonials', 'description' => 'Pro-only premium testimonials section using the testimonials image wall visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'testimonials_portrait_cards_premium' => ['category' => 'testimonials', 'description' => 'Pro-only premium testimonials section using the testimonials portrait cards visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'travel_destination_story_premium' => ['category' => 'services', 'description' => 'Pro-only premium services section using the travel destination story visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'travel_itinerary_visual_premium' => ['category' => 'services', 'description' => 'Pro-only premium services section using the travel itinerary visual visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'trust_certification_cards_premium' => ['category' => 'stats', 'description' => 'Pro-only premium stats section using the trust certification cards visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'trust_image_proof_premium' => ['category' => 'stats', 'description' => 'Pro-only premium stats section using the trust image proof visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'trust_logo_story_premium' => ['category' => 'stats', 'description' => 'Pro-only premium stats section using the trust logo story visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],
            'trust_partner_showcase_premium' => ['category' => 'stats', 'description' => 'Pro-only premium stats section using the trust partner showcase visual composition. Image-rich, theme-aware, and suitable for Luna redesign selection.'],

            'feature_image_left' => ['category' => 'feature', 'description' => 'Story or feature with image on the left.'],
            'feature_image_right' => ['category' => 'feature', 'description' => 'Story or feature with image on the right.'],
            'services_cards' => ['category' => 'services', 'description' => 'Scannable service cards for clear offerings.'],
            'services_bento' => ['category' => 'services', 'description' => 'Modern bento layout for richer service presentation.'],
            'services_bento_premium' => ['category' => 'services', 'description' => 'Pro-only asymmetrical premium services grid with one featured discipline, four supporting offers, proof content, and a conversion CTA for agencies, consultancies, SaaS, technology, finance, property, and premium service brands.'],
            'services_editorial_premium' => ['category' => 'services', 'description' => 'Pro-only Editorial asymmetric magazine-style services. Compatible alternative to other premium Services layouts for relative redesign requests.'],
            'services_showcase_premium' => ['category' => 'services', 'description' => 'Pro-only Large imagery with supporting premium service cards. Compatible alternative to other premium Services layouts for relative redesign requests.'],
            'services_minimal_luxury' => ['category' => 'services', 'description' => 'Pro-only Whitespace-heavy, restrained luxury service presentation. Compatible alternative to other premium Services layouts for relative redesign requests.'],
            'services_contrast_premium' => ['category' => 'services', 'description' => 'Pro-only higher-contrast premium services treatment that remains fully theme-aware. Compatible alternative to other premium Services layouts for relative redesign requests.'],
            'services_split_premium' => ['category' => 'services', 'description' => 'Pro-only Alternating split service rows with strong rhythm. Compatible alternative to other premium Services layouts for relative redesign requests.'],
            'services_grid_premium' => ['category' => 'services', 'description' => 'Pro-only Refined three-column premium service grid. Compatible alternative to other premium Services layouts for relative redesign requests.'],
            'services_feature_premium' => ['category' => 'services', 'description' => 'Pro-only One featured service with supporting service cards. Compatible alternative to other premium Services layouts for relative redesign requests.'],
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
            'stats_global_presence_premium' => ['category' => 'proof', 'description' => 'Pro-only global presence layout using only supplied offices, markets, regions, service areas, and geographic counts.'],
            'stats_timeline_metrics_premium' => ['category' => 'proof', 'description' => 'Pro-only chronological metrics layout using supplied historical dates and figures; never invent progress data.'],
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
            'faq_accordion' => ['category' => 'faq', 'description' => 'Frequently asked questions in an accordion.'],
            'faq_accordion_pro' => ['category' => 'faq', 'description' => 'Pro-only editorial FAQ accordion with strong hierarchy and accessible disclosure controls.'],
            'faq_search_premium' => ['category' => 'faq', 'description' => 'Pro-only searchable FAQ for larger question sets; generate factual business-specific answers only.'],
            'faq_categories_premium' => ['category' => 'faq', 'description' => 'Pro-only FAQ grouped into useful topic categories for easier scanning.'],
            'faq_support_portal_premium' => ['category' => 'faq', 'description' => 'Pro-only self-service support portal combining topic cards, featured FAQs, and a real supplied escalation path.'],
            'faq_documentation_premium' => ['category' => 'faq', 'description' => 'Pro-only documentation-style help section with topics, featured guide, and real supplied navigation path.'],
            'lead_magnet_premium' => ['category' => 'lead_generation', 'description' => 'Pro-only lead magnet for a real supplied guide, checklist, template, report, or resource.'],
            'lead_free_audit_premium' => ['category' => 'lead_generation', 'description' => 'Pro-only audit request section; use free wording only when a real free audit is offered.'],
            'lead_website_audit_premium' => ['category' => 'lead_generation', 'description' => 'Pro-only website audit CTA with supplied audit areas and no fabricated scan scores or findings.'],
            'lead_quote_form_premium' => ['category' => 'lead_generation', 'description' => 'Pro-only quote request section that collects real project details without promising instant or guaranteed pricing.'],
            'lead_roi_calculator_premium' => ['category' => 'lead_generation', 'description' => 'Pro-only illustrative ROI calculator using visitor-entered assumptions with estimate-only language.'],
            'lead_cost_calculator_premium' => ['category' => 'lead_generation', 'description' => 'Pro-only cost estimator using visitor-entered quantities and rates without presenting a final quote.'],
            'lead_consultation_booking_premium' => ['category' => 'lead_generation', 'description' => 'Pro-only consultation request section with no fabricated availability, confirmation, or response-time claims.'],
            'sales_comparison_premium' => ['category' => 'sales', 'description' => 'Pro-only sales comparison table using factual supplied options and criteria.'],
            'sales_feature_matrix_premium' => ['category' => 'sales', 'description' => 'Pro-only sales feature matrix using real supplied capabilities and terms.'],
            'sales_competitor_comparison_premium' => ['category' => 'sales', 'description' => 'Pro-only competitor comparison requiring factual, supportable claims.'],
            'sales_roi_premium' => ['category' => 'sales', 'description' => 'Pro-only ROI/business-case section using transparent supplied assumptions without guarantees.'],
            'sales_guarantee_premium' => ['category' => 'sales', 'description' => 'Pro-only guarantee or assurance section that requires real supplied terms and avoids invented promises.'],
            'sales_trust_premium' => ['category' => 'sales', 'description' => 'Pro-only trust section using real supplied credentials, safeguards, proof, policies, or service commitments.'],
            'sales_integrations_premium' => ['category' => 'sales', 'description' => 'Pro-only integrations section showing real supplied supported or planned tools and platforms.'],
            'agency_dashboard_preview_premium' => ['category' => 'agency', 'description' => 'Pro-only agency dashboard preview using only supplied metrics, workflows, and management capabilities.'],
            'agency_client_portal_premium' => ['category' => 'agency', 'description' => 'Pro-only client portal showcase using only real supplied client-facing features and workflows.'],
            'agency_white_label_showcase_premium' => ['category' => 'agency', 'description' => 'Pro-only white-label showcase using only real supplied branding and client-facing capabilities.'],
            'agency_website_management_premium' => ['category' => 'agency', 'description' => 'Pro-only website management showcase using only real supplied multi-site workflows and controls.'],
            'agency_maintenance_plans_premium' => ['category' => 'agency', 'description' => 'Pro-only maintenance plans section using only real supplied care scope, cadence, inclusions, and terms.'],
            'agency_support_plans_premium' => ['category' => 'agency', 'description' => 'Pro-only support plans section using only real supplied support channels, coverage, and plan differences.'],
            'agency_workflow_premium' => ['category' => 'agency', 'description' => 'Pro-only agency workflow section using only the supplied delivery process and no fabricated timing.'],
            'agency_project_pipeline_premium' => ['category' => 'agency', 'description' => 'Pro-only project pipeline section using real supplied stages without fabricated client status or deadlines.'],
            'agency_client_reviews_premium' => ['category' => 'agency', 'description' => 'Pro-only client reviews section using only verified supplied feedback or clearly editable placeholders.'],
            'agency_website_reports_premium' => ['category' => 'agency', 'description' => 'Pro-only website reports section using only real supplied reporting capabilities, metrics, and recommendations.'],
            'ai_prompt_showcase_premium' => ['category' => 'ai', 'description' => 'Pro-only AI prompt showcase using safe example prompts or supplied real use cases without unsupported capability claims.'],
            'ai_workflow_premium' => ['category' => 'ai', 'description' => 'Pro-only AI workflow section explaining real supported AI-assisted stages without fake automation or timing claims.'],
            'ai_assistant_premium' => ['category' => 'ai', 'description' => 'Pro-only AI assistant conversation showcase using example dialogue rather than fabricated customer interactions.'],
            'ai_timeline_premium' => ['category' => 'ai', 'description' => 'Pro-only AI generation timeline explaining supported stages without invented processing times or guarantees.'],
            'ai_builder_premium' => ['category' => 'ai', 'description' => 'Pro-only AI builder showcase presenting real supported prompt-to-builder actions and user controls.'],
            'ai_automation_premium' => ['category' => 'ai', 'description' => 'Pro-only automation showcase describing only supported or clearly labelled planned workflows.'],
            'ai_credits_dashboard_premium' => ['category' => 'ai', 'description' => 'Pro-only credits dashboard using supplied balances, usage rules, or editable sample values without fake account data.'],
            'ai_generation_process_premium' => ['category' => 'ai', 'description' => 'Pro-only generation process explaining supported stages without fake progress, timing, or background-work claims.'],
            'ai_statistics_premium' => ['category' => 'ai', 'description' => 'Pro-only AI statistics section using supplied usage, adoption, generation, or efficiency metrics without fabricated performance claims.'],
            'ai_prompt_examples_premium' => ['category' => 'ai', 'description' => 'Pro-only practical prompt examples clearly presented as illustrative unless supplied as real prompts.'],
            'contact_details' => ['category' => 'contact', 'description' => 'Address, phone, email, and business details.'],
            'location_map' => ['category' => 'location', 'description' => 'Map and physical location information.'],
            'case_studies_grid' => ['category' => 'portfolio', 'description' => 'Projects, work samples, or case studies.'],
            'jobs_list' => ['category' => 'careers', 'description' => 'Open roles and recruitment information.'],
            'events_grid' => ['category' => 'events', 'description' => 'Upcoming events, sessions, or activities.'],
        ];

        $supported = array_keys(SchemaManager::map());

        return array_values(array_map(
            static function (string $slug) use ($metadata): array {
                $description = $metadata[$slug]['description'] ?? 'Reusable website section.';
                $profile = self::plannerProfile($slug, $description);

                return [
                    'slug' => $slug,
                    'category' => $metadata[$slug]['category'] ?? 'content',
                    'description' => $description,
                    'selection_tier' => $profile['selection_tier'],
                    'media_mode' => $profile['media_mode'],
                    'visual_score' => $profile['visual_score'],
                    'text_density' => $profile['text_density'],
                    'motion_level' => $profile['motion_level'],
                    'planner_priority' => $profile['planner_priority'],
                    'best_for' => $profile['best_for'],
                ];
            },
            $supported
        ));
    }

    /**
     * Planner-only art-direction metadata. This is deliberately derived from
     * stable Spark slugs so adding a new Spark never requires a DB migration.
     * The planner can distinguish a real image-led section from a card-heavy
     * section instead of guessing from marketing descriptions alone.
     *
     * @return array{selection_tier:string,media_mode:string,visual_score:int,text_density:string,motion_level:string,planner_priority:int,best_for:string}
     */
    private static function plannerProfile(string $slug, string $description): array
    {
        $classic = in_array($slug, [
            'hero_headline', 'hero_floating_cards', 'hero_video_background',
            'hero_video_style', 'hero_background_image', 'hero_slider_fade',
            'hero_parallax', 'hero_editorial_overlay', 'hero_split_image',
            'feature_image_left', 'feature_image_right', 'services_cards',
            'services_bento', 'process_timeline', 'testimonials_carousel',
            'hero_centered_cta', 'image_cta_banner', 'pricing_cards',
            'stats_modern', 'team_modern', 'faq_accordion',
            'contact_form_modern', 'contact_details', 'location_map',
            'case_studies_grid', 'jobs_list', 'events_grid',
        ], true);

        $premium = str_contains($slug, 'premium')
            || str_ends_with($slug, '_pro')
            || str_starts_with($description, 'Pro-only');

        $selectionTier = $classic ? 'classic' : ($premium ? 'premium_new' : 'modern');

        $imageLedTokens = [
            'image', 'gallery', 'masonry', 'pinterest', 'portfolio', 'office',
            'founder', 'team_cards', 'leadership', 'culture', 'before_after',
            'case_study', 'map', 'featured_article', 'magazine', 'editors_pick',
            'agency_showcase', 'device_showcase', 'app_screens', 'cinematic',
            'ken_burns', 'split_slider', 'vertical_story', 'curtain_reveal',
            'mask_reveal', 'perspective_carousel', 'scroll_morph',
        ];
        $motionTokens = [
            'motion', 'animated', 'hover', 'carousel', 'slider', 'parallax',
            'scroll', 'marquee', 'typewriter', 'rotating', 'spotlight', '3d_tilt',
            'particle', 'grid_pulse', 'light_trails', 'interactive', 'video',
        ];
        $dataTokens = [
            'stats_', 'dashboard', 'charts', 'calculator', 'matrix', 'comparison',
            'pricing_', 'credits_', 'pipeline', 'org_chart',
        ];
        $textHeavyTokens = [
            'faq_', 'process_timeline', 'services_cards', 'services_mega_grid',
            'about_mission_grid', 'about_timeline_story', 'about_awards_timeline',
            'jobs_list', 'contact_details', 'documentation', 'support_portal',
            'sales_guarantee', 'sales_trust', 'workflow', 'timeline_metrics',
        ];

        $containsAny = static fn (array $tokens): bool => collect($tokens)->contains(
            static fn (string $token): bool => str_contains($slug, $token)
        );

        $imageLed = $containsAny($imageLedTokens)
            || in_array($slug, ['feature_image_left', 'feature_image_right', 'image_cta_banner', 'hero_background_image', 'hero_editorial_overlay', 'hero_split_image', 'about_office_gallery'], true);
        $motion = $containsAny($motionTokens);
        $data = $containsAny($dataTokens);
        $textHeavy = $containsAny($textHeavyTokens);

        $mediaMode = match (true) {
            $imageLed && $motion => 'image_motion',
            $imageLed => 'image_led',
            $motion => 'motion_visual',
            $data => 'data_visual',
            $textHeavy => 'text_structured',
            default => 'mixed_content',
        };

        $visualScore = match ($mediaMode) {
            'image_motion' => 5,
            'image_led' => 5,
            'motion_visual' => 4,
            'data_visual' => 3,
            'mixed_content' => 3,
            default => 2,
        };

        // Keep functional sections available, but make truly visual premium
        // Sparks easier for the model to notice and rank.
        $plannerPriority = 50;
        $plannerPriority += $selectionTier === 'premium_new' ? 24 : ($selectionTier === 'modern' ? 12 : 0);
        $plannerPriority += ($visualScore - 2) * 7;
        $plannerPriority -= $textHeavy ? 6 : 0;

        $bestFor = match ($mediaMode) {
            'image_motion' => 'hero moments, portfolios, visual storytelling, high-impact transitions',
            'image_led' => 'photography, people, places, projects, products, editorial storytelling',
            'motion_visual' => 'digital products, modern brands, interaction, visual pacing',
            'data_visual' => 'verified metrics, comparisons, structured proof, product information',
            'text_structured' => 'FAQ, process, detailed explanations, policies, structured information',
            default => 'balanced supporting content',
        };

        return [
            'selection_tier' => $selectionTier,
            'media_mode' => $mediaMode,
            'visual_score' => $visualScore,
            'text_density' => $textHeavy ? 'high' : ($imageLed ? 'low' : 'medium'),
            'motion_level' => $motion ? 'enhanced' : 'static',
            'planner_priority' => max(1, min(100, $plannerPriority)),
            'best_for' => $bestFor,
        ];
    }

    public static function slugs(): array
    {
        return array_column(self::all(), 'slug');
    }
}
