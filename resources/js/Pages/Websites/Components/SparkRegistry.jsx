import axios from "axios";
import { useState } from "react";
import { useRef } from "react";
import { showCosmicNotification } from "../../../Components/CosmicNotification";
import { ACTION_PRICING, getBlockPrice } from "../../../cosmic/pricing";
import CreditBalanceBadge from "../../../Components/CosmicCredits/CreditBalanceBadge";
import { useCreditBalance } from '@/Hooks/useCreditBalance';
import CreditPrice from "../../../Components/CosmicCredits/CreditPrice";

import BlockPreviewCard from "./BlockPreviewCard";

import HeroHeadlinePreview from "./Previews/HeroHeadlinePreview";
import ServicesGridPreview from "./Previews/ServicesGridPreview";
import FeatureLeftPreview from "./Previews/FeatureLeftPreview";
import FeatureRightPreview from "./Previews/FeatureRightPreview";
import HeroCenteredPreview from "./Previews/HeroCenteredPreview";
import ServicesBentoPreview from "./Previews/ServicesBentoPreview";
import ProcessTimelinePreview from "./Previews/ProcessTimelinePreview";
import StatsModernPreview from "./Previews/StatsModernPreview";
import TeamModernPreview from "./Previews/TeamModernPreview";
import TestimonialsCarouselPreview from "./Previews/TestimonialsCarouselPreview";
import PricingCardsPreview from "./Previews/PricingCardsPreview";
import HeroBackgroundImagePreview from "./Previews/HeroBackgroundImagePreview";
import HeroSliderFadePreview from "./Previews/HeroSliderFadePreview";
import HeroParallaxPreview from "./Previews/HeroParallaxPreview";
import HeroEditorialOverlayPreview from "./Previews/HeroEditorialOverlayPreview";
import HeroSplitImagePreview from "./Previews/HeroSplitImagePreview";
import HeroSplitEditorialPreview from "./Previews/HeroSplitEditorialPreview";
import HeroFloatingGlassPreview from "./Previews/HeroFloatingGlassPreview";
import HeroSaasDashboardPreview from "./Previews/HeroSaasDashboardPreview";
import HeroLuxuryFullscreenPreview from "./Previews/HeroLuxuryFullscreenPreview";
import HeroVideoPremiumPreview from "./Previews/HeroVideoPremiumPreview";
import HeroAiConversationPreview from "./Previews/HeroAiConversationPreview";
import HeroAgencyShowcasePreview from "./Previews/HeroAgencyShowcasePreview";
import HeroBentoPremiumPreview from "./Previews/HeroBentoPremiumPreview";
import ServicesBentoPremiumPreview from "./Previews/ServicesBentoPremiumPreview";
import ServicesPricingComparisonPreview from "./Previews/ServicesPricingComparisonPreview";
import ServicesFeatureComparisonPreview from "./Previews/ServicesFeatureComparisonPreview";
import ServicesHoverCardsPreview from "./Previews/ServicesHoverCardsPreview";
import ImageCtaBannerPreview from "./Previews/ImageCtaBannerPreview";
import HeroFloatingCardsPreview from "./Previews/HeroFloatingCardsPreview";
import HeroVideoStylePreview from "./Previews/HeroVideoStylePreview";
import HeroVideoBackgroundPreview from "./Previews/HeroVideoBackgroundPreview";
import ContactFormPreview from "./Previews/ContactFormPreview";
import FaqAccordionPreview from "./Previews/FaqAccordionPreview";
import ContactDetailsPreview from "./Previews/ContactDetailsPreview";
import LocationMapPreview from "./Previews/LocationMapPreview";
import CaseStudiesGridPreview from "./Previews/CaseStudiesGridPreview";
import JobsListPreview from "./Previews/JobsListPreview";
import EventsGridPreview from "./Previews/EventsGridPreview";


export const BlockRegistry = [
    {
        type: "hero_headline",
        theme:"auto",
        title: "Hero",
        buttonLabel: "Install",
        buttonClass: "bg-rose-600 hover:bg-rose-500",
        preview: HeroHeadlinePreview,
        payload: {
            type: "hero_headline",
            subtitle: "WELCOME TO THE FUTURE",
            heading: "Build Better Digital Reality.",
            text: "Build a polished website faster with reusable sections and complete editorial control.",
            btn1_label: "Get Started",
            btn1_url: "#",
            btn2_label: "View Docs",
            btn2_url: "#"
        }
    },

    {
        type: "hero_video_background",
        theme: "auto",
        title: "Hero Video Background",
        buttonLabel: "Add Video Background",
        buttonClass: "bg-violet-600 hover:bg-violet-500",
        preview: HeroVideoBackgroundPreview,
        payload: {
            type: "hero_video_background",
            theme: "auto",
            tagline: "STEP INTO THE EXPERIENCE",
            heading: "Make every first impression unforgettable.",
            text: "Introduce your business through motion, strong storytelling, and a clear next step for every visitor.",
            primary_label: "Get started",
            primary_url: "#",
            secondary_label: "Explore more",
            secondary_url: "#",
            video_url: "/storage/cms-videos/hero-placeholder.mp4",
            poster_image_url: "/storage/cms-images/background/background-1.avif",
            video_badge: "Discover what makes us different",
            scroll_label: "Explore",
        },
    },

    {
    type: "hero_video_style",
        theme: "auto",
        title: "Hero Video Style",
        buttonLabel: "Add Video Hero",
        buttonClass: "bg-violet-600 hover:bg-violet-500",
        preview: HeroVideoStylePreview,
        payload: {
            type: "hero_video_style",
            theme: "auto",
            tagline: "SEE WHAT SETS US APART",
            heading: "A clear vision for what comes next.",
            text: "Introduce your business with a strong message, a compelling visual, and a simple path for visitors to learn more.",
            primary_label: "Get started",
            primary_url: "#",
            video_label: "Watch our story",
            video_url: "#",
            play_label: "Play video",
            image_badge: "Discover our approach",
            image_url: "/storage/cms-images/background/background-1.avif",
        },
    },

    {
    type: "hero_floating_cards",
        theme: "auto",
        title: "Hero Floating Cards",
        buttonLabel: "Add Floating Hero",
        buttonClass: "bg-violet-600 hover:bg-violet-500",
        preview: HeroFloatingCardsPreview,
        payload: {
            type: "hero_floating_cards",
            theme: "auto",
            tagline: "BUILT AROUND YOUR NEXT STEP",
            heading: "A better way to move your business forward.",
            text: "Present your strongest message, highlight what makes your business different, and help visitors take action with confidence.",
            primary_label: "Get started",
            primary_url: "#",
            secondary_label: "Explore services",
            secondary_url: "#",
            image_url: "/storage/cms-images/background/background-1.avif",
            image_badge: "Professional service you can rely on",
            card_one_value: "15+",
            card_one_label: "Years of experience",
            card_two_title: "Trusted expertise",
            card_two_text: "Thoughtful service, clear communication, and dependable results.",
        },
    },

    {
        type: "services_cards",
        theme:"auto",
        title: "Services Grid",
        buttonLabel: "Install Grid",
        buttonClass: "bg-blue-600 hover:bg-blue-500",
        preview: ServicesGridPreview,
        payload: {
            type: "services_cards",
            heading: "Our Services",
            tagline: "WHAT WE OFFER"
        }
    },

    {
        type: "feature_image_left",
        theme:"auto",
        title: "Feature Image Left",
        buttonLabel: "Install Feature Block",
        buttonClass: "bg-blue-600 hover:bg-blue-500",
        preview: FeatureLeftPreview,
        payload: {
            type: "feature_image_left",
            category: "CATEGORY",
            heading: "Lorem ipsum dolor sit amet",
            text: "Consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.",
            button_label: "Read more →",
            button_url: "#",
            image_url: "https://picsum.photos/800/400"
        }
    },

    {
        type: "feature_image_right",
        theme:"auto",
        title: "Feature Image Right",
        buttonLabel: "Install Reverse Block",
        buttonClass: "bg-emerald-600 hover:bg-emerald-500",
        preview: FeatureRightPreview,
        payload: {
            type: "feature_image_right",
            category: "CATEGORY",
            heading: "Lorem ipsum dolor sit amet",
            text: "Consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.",
            button_label: "Read more →",
            button_url: "#",
            image_url: "https://picsum.photos/800/400"
        }
    },

    {
        type: "hero_centered_cta",
        theme:"auto",
        title: "Hero: Accent Focus",
        buttonLabel: "Install Accent Focus",
        buttonClass: "bg-emerald-600 hover:bg-emerald-500",
        preview: HeroCenteredPreview,
        payload: {
            type: "hero_centered_cta",
            heading: "Build Modern Websites Fast",
            tagline: "GET STARTED"
        }
    },

    {
        type: "services_bento",
        theme:"auto",
        title: "Services Bento",
        buttonLabel: "Install Bento",
        buttonClass: "bg-violet-600 hover:bg-violet-500",
        preview: ServicesBentoPreview,
        payload: {
            type: "services_bento",
            heading: "Solutions Built Around Your Business",
            tagline: "OUR SERVICES"
        }
    },
    {
        type: "process_timeline",
        theme: "auto",
        title: "Process Timeline",
        buttonLabel: "Install Timeline",
        buttonClass: "bg-cyan-600 hover:bg-cyan-500",
        preview: ProcessTimelinePreview,
        payload: {
            type: "process_timeline",
            category: "HOW IT WORKS",
            heading: "Our Simple Process",
            text: "We follow a proven workflow to deliver quality results from consultation to completion."
        }
    },
    {
        type: "stats_modern",
        theme: "auto",
        title: "Modern Stats",
        buttonLabel: "Add Stats",
        buttonClass: "bg-violet-600 hover:bg-violet-500",
        preview: StatsModernPreview,
        payload: {
            type: "stats_modern",
            eyebrow: "Why choose us",
            heading: "Experience you can count on",
            text: "Clear results, dependable service, and a team committed to every project.",
            metrics: [
                { value: "15+", label: "Years of experience", description: "Serving customers with proven expertise." },
                { value: "250+", label: "Projects completed", description: "Delivered across a wide range of needs." },
                { value: "98%", label: "Client satisfaction", description: "Built through reliable service and support." },
                { value: "24/7", label: "Responsive support", description: "Help is available whenever it matters." }
            ]
        }
    },
    {
        type: "team_modern",
        theme: "auto",
        title: "Team Modern",
        buttonLabel: "Choose team layout",
        buttonClass: "bg-violet-600 hover:bg-violet-500",
        preview: TeamModernPreview,
        payload: {
            type: "team_modern",
            eyebrow: "Meet the team",
            heading: "The people behind the work",
            text: "A dedicated team focused on thoughtful service and dependable results.",
            members: [
                { name: "Alex Morgan", role: "Founder & Director", bio: "Guides the team with a client-first approach.", image_url: "/storage/cms-images/avatars/avatar-1.jpg" },
                { name: "Jordan Lee", role: "Client Experience Lead", bio: "Keeps every project organized and responsive.", image_url: "/storage/cms-images/avatars/avatar-2.jpg" },
                { name: "Taylor Brooks", role: "Creative Lead", bio: "Turns clear ideas into polished experiences.", image_url: "/storage/cms-images/avatars/avatar-3.jpg" },
                { name: "Casey Rivera", role: "Operations Manager", bio: "Keeps quality consistent from start to finish.", image_url: "/storage/cms-images/avatars/avatar-4.jpg" }
            ]
        }
    },
    {
        type: "testimonials_carousel",
        theme: "auto",
        title: "Testimonials",
        buttonLabel: "Install Testimonials",
        buttonClass: "bg-amber-600 hover:bg-amber-500",
        preview: TestimonialsCarouselPreview,
        payload: {
            type: "testimonials_carousel",
            tagline: "CLIENT TESTIMONIALS",
            heading: "Trusted By Businesses Around The World",
            text: "See what our satisfied clients say about working with our team."
        }
    },
    {
        type: "pricing_cards",
        theme: "auto",
        title: "Pricing Cards",
        buttonLabel: "Install Pricing Cards",
        buttonClass: "bg-emerald-600 hover:bg-emerald-500",
        preview: PricingCardsPreview,
        payload: {
            type: "pricing_cards",
            tagline: "SIMPLE PRICING",
            heading: "Choose The Perfect Plan",
            text: "Flexible pricing options designed for individuals, growing businesses, and enterprise teams."
        }
    },

    {
        type: "hero_parallax",
        theme: "auto",
        title: "Hero Parallax",
        buttonLabel: "Unlock Hero Parallax",
        buttonClass: "bg-violet-600 hover:bg-violet-500",
        preview: HeroParallaxPreview,
        badge: "PRO",
        payload: {
            type: "hero_parallax",
            eyebrow: "INTRODUCING A NEW PERSPECTIVE",
            heading: "Move beyond the ordinary.",
            text: "Create a memorable first impression with cinematic depth, confident typography, and a clear next step.",
            primary_label: "Start a project",
            primary_url: "#",
            secondary_label: "Explore our work",
            secondary_url: "#",
            image_url: "/storage/cms-images/background/background-1.avif",
            overlayOpacity: 64,
            parallaxSpeed: 24,
            contentAlign: "left",
            height: "screen",
            scroll_label: "Scroll to explore"
        }
    },

    {
    type: "hero_background_image",
        theme: "auto",
        title: "Hero Background Image",
        buttonLabel: "Install Hero Background",
        buttonClass: "bg-emerald-600 hover:bg-emerald-500",
        preview: HeroBackgroundImagePreview,
        payload: {
            type: "hero_background_image",
            tagline: "WELCOME TO OUR COMPANY",
            heading: "Build Beautiful Websites With Confidence",
            text: "Create modern, responsive websites using reusable blocks, AI-generated content, and powerful customization tools.",
            button_label: "Get Started",
            button_url: "#",
            image_url: "/storage/cms-images/background/background-1.avif",
            overlayOpacity: 50,
            textAlign: "center",
            height: "screen"
        }
    },

    {
        type: "hero_slider_fade",
        theme: "auto",
        title: "Hero Slider Fade",
        buttonLabel: "Add Fade Slider",
        buttonClass: "bg-violet-600 hover:bg-violet-500",
        preview: HeroSliderFadePreview,
        payload: {
            type: "hero_slider_fade",
            theme: "auto",
            autoplay: true,
            interval: 5000,
            pause_on_hover: true,
            show_dots: true,
            show_arrows: true,
            slides: [
                {
                    image_url: "/storage/cms-images/background/background-1.avif",
                    eyebrow: "BUILT FOR WHAT'S NEXT",
                    heading: "A stronger first impression",
                    description: "Introduce your business with a clear message and a confident next step.",
                    button_1_text: "Get Started",
                    button_1_url: "#",
                    button_2_text: "Explore Services",
                    button_2_url: "#",
                    button_3_text: "View Our Work",
                    button_3_url: "#",
                    button_4_text: "Learn More",
                    button_4_url: "#"
                },
                {
                    image_url: "/storage/cms-images/background/background-2.avif",
                    eyebrow: "DESIGNED AROUND YOU",
                    heading: "Show what makes you different",
                    description: "Highlight your services, experience, and the value customers can expect.",
                    button_1_text: "Explore Services",
                    button_1_url: "#",
                    button_2_text: "Our Process",
                    button_2_url: "#",
                    button_3_text: "Case Studies",
                    button_3_url: "#",
                    button_4_text: "See Details",
                    button_4_url: "#"
                },
                {
                    image_url: "/storage/cms-images/background/background-3.avif",
                    eyebrow: "READY WHEN YOU ARE",
                    heading: "Turn interest into action",
                    description: "Give visitors a simple, direct path to contact, book, or learn more.",
                    button_1_text: "Contact Us",
                    button_1_url: "#",
                    button_2_text: "Book a Call",
                    button_2_url: "#",
                    button_3_text: "View Pricing",
                    button_3_url: "#",
                    button_4_text: "Get Started",
                    button_4_url: "#"
                }
            ]
        }
    },

    {
        type: "hero_editorial_overlay",
        theme: "auto",
        title: "Hero Editorial Overlay",
        buttonLabel: "Add Hero",
        buttonClass: "bg-violet-600 hover:bg-violet-500",
        preview: HeroEditorialOverlayPreview,
        payload: {
            type: "hero_editorial_overlay",
            tagline: "BUILT FOR WHAT COMES NEXT",
            heading: "A stronger first impression starts here.",
            text: "Bring your story, services, and next step into focus with a confident, image-led introduction.",
            primary_label: "Start a project",
            primary_url: "#",
            secondary_label: "Explore services",
            secondary_url: "#",
            image_url: "/storage/cms-images/background/background-1.avif",
            overlayOpacity: 72,
            height: "screen",
        },
    },

    {
        type: "hero_split_editorial",
        theme: "auto",
        title: "Hero Split Editorial",
        buttonLabel: "Add Editorial Hero",
        buttonClass: "bg-emerald-600 hover:bg-emerald-500",
        preview: HeroSplitEditorialPreview,
        payload: {
            type: "hero_split_editorial",
            theme: "auto",
            eyebrow: "A NEW STANDARD",
            editorial_index: "01",
            heading: "Designed to make the right first impression.",
            text: "A considered digital experience that brings your story, expertise, and next step into one confident opening statement.",
            primary_label: "Start a conversation",
            primary_url: "#",
            secondary_label: "Explore our work",
            secondary_url: "#",
            proof_value: "15+",
            proof_label: "Years of considered craft",
            image_caption: "Built with clarity, confidence, and care.",
            image_url: "/storage/cms-images/background/background-1.avif",
        },
    },

    {
        type: "hero_floating_glass",
        theme: "auto",
        title: "Floating Glass Hero",
        buttonLabel: "Add Glass Hero",
        buttonClass: "bg-emerald-600 hover:bg-emerald-500",
        preview: HeroFloatingGlassPreview,
        payload: {
            type: "hero_floating_glass", theme: "auto", eyebrow: "BUILT FOR MOMENTUM",
            heading: "A clearer way to move your business forward.",
            text: "Bring your offer, proof, and next step together in one immersive opening experience.",
            primary_label: "Start a project", primary_url: "#", secondary_label: "See how it works", secondary_url: "#",
            glass_title: "Made for decisive teams", glass_text: "A focused digital experience designed to turn attention into action.",
            metric_value: "3.2x", metric_label: "Faster path to launch", badge_one: "Strategy-led", badge_two: "Conversion-ready",
            image_url: "/storage/cms-images/background/background-1.avif",
        },
    },

    {
        type: "hero_saas_dashboard",
        theme: "auto",
        title: "SaaS Dashboard Hero",
        buttonLabel: "Add SaaS Hero",
        buttonClass: "bg-emerald-600 hover:bg-emerald-500",
        preview: HeroSaasDashboardPreview,
        payload: {
            type: "hero_saas_dashboard", theme: "auto", eyebrow: "THE OPERATING SYSTEM FOR GROWTH",
            heading: "Turn your workflow into a clear, measurable advantage.",
            text: "Bring projects, performance, and customer momentum into one focused workspace built for modern teams.",
            primary_label: "Start building", primary_url: "#", secondary_label: "View product tour", secondary_url: "#",
            dashboard_title: "Workspace overview", dashboard_subtitle: "Live performance across your team",
            metric_one_value: "42%", metric_one_label: "Faster delivery", metric_two_value: "18.4k", metric_two_label: "Monthly actions",
            metric_three_value: "99.9%", metric_three_label: "Platform uptime", chart_label: "Growth this quarter",
            logo_one: "NORTHSTAR", logo_two: "ARC LABS", logo_three: "SCALEWORKS", logo_four: "FOUNDRY",
        },
    },

    {
        type: "hero_luxury_fullscreen", theme: "auto", title: "Luxury Fullscreen Hero", buttonLabel: "Add Luxury Hero", buttonClass: "bg-emerald-600 hover:bg-emerald-500", preview: HeroLuxuryFullscreenPreview,
        payload: { type: "hero_luxury_fullscreen", theme: "auto", eyebrow: "THE ART OF ARRIVAL", heading: "Quiet confidence, made unforgettable.", text: "A refined opening statement for brands defined by craft, place, and exceptional attention to detail.", primary_label: "Discover the collection", primary_url: "#", secondary_label: "Our story", secondary_url: "#", location_label: "Crafted in exceptional detail", edition_label: "Private Edition 01", image_url: "/storage/cms-images/background/background-1.avif" },
    },

    {
        type: "hero_video_premium", theme: "auto", title: "Video Hero Premium", buttonLabel: "Add Premium Video Hero", buttonClass: "bg-emerald-600 hover:bg-emerald-500", preview: HeroVideoPremiumPreview,
        payload: { type: "hero_video_premium", theme: "auto", eyebrow: "A STORY IN MOTION", heading: "Make the first few seconds impossible to forget.", text: "Use cinematic movement, focused copy, and one clear next step to introduce your brand with confidence.", primary_label: "Start the experience", primary_url: "#", secondary_label: "Watch the story", secondary_url: "#", media_badge: "Cinematic brand experience", scroll_label: "Scroll to explore", video_url: "/storage/cms-videos/hero-placeholder.mp4", poster_image_url: "/storage/cms-images/background/background-1.avif" },
    },

    {
        type: "hero_ai_conversation", theme: "auto", title: "AI Conversation Hero", buttonLabel: "Add AI Conversation Hero", buttonClass: "bg-emerald-600 hover:bg-emerald-500", preview: HeroAiConversationPreview,
        payload: { type: "hero_ai_conversation", theme: "auto", eyebrow: "AI THAT WORKS WITH YOU", heading: "Turn a simple prompt into meaningful progress.", text: "Show visitors how your AI listens, responds, and helps them move from idea to action in one focused experience.", primary_label: "Start building", primary_url: "#", secondary_label: "See how it works", secondary_url: "#", assistant_label: "Cosmic AI", assistant_status: "Ready to help", user_message: "Create a polished campaign page for our next launch.", assistant_message: "I’ll shape the structure, write the first draft, and prepare a responsive page you can refine.", prompt_placeholder: "Ask AI to create, improve, or explain...", chip_one: "Strategy-aware", chip_two: "Editable output", chip_three: "Built to publish" },
    },

    {
        type: "hero_agency_showcase", theme: "auto", title: "Agency Showcase Hero", buttonLabel: "Add Agency Hero", buttonClass: "bg-emerald-600 hover:bg-emerald-500", preview: HeroAgencyShowcasePreview,
        payload: { type: "hero_agency_showcase", theme: "auto", eyebrow: "DESIGN THAT MOVES BUSINESS FORWARD", heading: "From overlooked to unforgettable.", text: "Pair strategic thinking with polished execution, then show visitors the difference your agency creates at a glance.", primary_label: "Start a project", primary_url: "#", secondary_label: "View case studies", secondary_url: "#", before_label: "Before", before_caption: "A fragmented digital experience", after_label: "After", after_caption: "A focused brand built to convert", metric_one_value: "48%", metric_one_label: "More qualified enquiries", metric_two_value: "2.4x", metric_two_label: "Higher conversion rate", metric_three_value: "6 weeks", metric_three_label: "From strategy to launch", logo_one: "NORTHSTAR", logo_two: "MORROW & CO", logo_three: "FOUNDRY", logo_four: "KINSHIP", before_image_url: "/storage/cms-images/background/background-2.avif", after_image_url: "/storage/cms-images/background/background-1.avif" },
    },


    {
        type: "hero_bento_premium", theme: "auto", title: "Bento Hero", buttonLabel: "Add Bento Hero", buttonClass: "bg-emerald-600 hover:bg-emerald-500", preview: HeroBentoPremiumPreview,
        payload: { type: "hero_bento_premium", theme: "auto", eyebrow: "BUILT TO STAND APART", heading: "One clear idea, expressed from every angle.", text: "Bring your message, proof, imagery, and next step together in a flexible bento composition designed for modern brands.", primary_label: "Start a project", primary_url: "#", secondary_label: "Explore the work", secondary_url: "#", image_url: "/storage/cms-images/background/background-1.avif", image_label: "Featured perspective", metric_value: "3.4x", metric_label: "More engaged visitors", proof_title: "Built around clarity", proof_text: "A modular opening experience with strong hierarchy and deliberate rhythm.", card_one_label: "Strategy-led", card_two_label: "Responsive by design", card_three_label: "Ready to publish" },
    },


    {
        type: "services_bento_premium", theme: "auto", title: "Bento Services Premium", buttonLabel: "Add Premium Services", buttonClass: "bg-emerald-600 hover:bg-emerald-500", preview: ServicesBentoPremiumPreview,
        payload: { type: "services_bento_premium", theme: "auto", eyebrow: "SERVICES DESIGNED AROUND MOMENTUM", heading: "Specialist thinking, connected into one clear growth system.", text: "Combine strategy, design, technology, and optimisation in a flexible service model built around the way your business actually works.", primary_label: "Explore our services", primary_url: "#", featured_number: "01", featured_title: "Digital strategy", featured_text: "Clarify the opportunity, align the priorities, and turn ambitious goals into an actionable roadmap.", featured_meta: "Research · Positioning · Roadmaps", service_two_number: "02", service_two_title: "Experience design", service_two_text: "Shape intuitive journeys and interfaces that make every interaction feel considered.", service_three_number: "03", service_three_title: "Web platforms", service_three_text: "Build fast, scalable digital foundations designed to evolve with your team.", service_four_number: "04", service_four_title: "Growth systems", service_four_text: "Connect content, campaigns, and measurement into a repeatable growth engine.", service_five_number: "05", service_five_title: "Ongoing optimisation", service_five_text: "Improve performance continuously through testing, insight, and focused iteration.", proof_value: "5 disciplines", proof_label: "One integrated senior team" },
    },

    {
        type: "services_pricing_comparison", theme: "auto", title: "Pricing Comparison Premium", buttonLabel: "Add Pricing Comparison", buttonClass: "bg-emerald-600 hover:bg-emerald-500", preview: ServicesPricingComparisonPreview,
        payload: { type: "services_pricing_comparison", theme: "auto", eyebrow: "CHOOSE THE RIGHT LEVEL OF SUPPORT", heading: "Clear packages. No hidden complexity.", text: "Compare the level of strategy, delivery, and ongoing support included in each engagement.", starter_name: "Essential", starter_price: "$2,500", starter_period: "from", starter_description: "A focused foundation for one clear business priority.", starter_button_label: "Choose Essential", starter_button_url: "#", growth_name: "Growth", growth_price: "$6,500", growth_period: "from", growth_description: "A complete growth engagement for ambitious teams.", growth_button_label: "Choose Growth", growth_button_url: "#", growth_badge: "MOST POPULAR", pro_name: "Partner", pro_price: "Custom", pro_period: "", pro_description: "Embedded senior support for complex, ongoing work.", pro_button_label: "Talk to our team", pro_button_url: "#", feature_one: "Strategic discovery", starter_one: "Included", growth_one: "Extended", pro_one: "Ongoing", feature_two: "Design direction", starter_two: "1 concept", growth_two: "3 concepts", pro_two: "Unlimited scope", feature_three: "Delivery support", starter_three: "Launch", growth_three: "Launch + optimise", pro_three: "Embedded team", feature_four: "Reporting", starter_four: "Summary", growth_four: "Monthly", pro_four: "Custom dashboard", feature_five: "Response time", starter_five: "3 business days", growth_five: "1 business day", pro_five: "Priority", feature_six: "Best for", starter_six: "Focused projects", growth_six: "Growing teams", pro_six: "Complex programmes", footnote: "Every engagement is tailored before work begins. Prices shown are editable starting points." },
    },


    {
        type: "services_feature_comparison", theme: "auto", title: "Feature Comparison Premium", buttonLabel: "Add Feature Comparison", buttonClass: "bg-emerald-600 hover:bg-emerald-500", preview: ServicesFeatureComparisonPreview,
        payload: { type: "services_feature_comparison", theme: "auto", eyebrow: "COMPARE THE APPROACH", heading: "Choose the level of capability your next stage needs.", text: "See how each service model differs across strategy, delivery, collaboration, and ongoing support.", option_one_name: "Foundation", option_one_kicker: "Focused project", option_one_text: "A clear, senior-led engagement for one defined priority.", option_two_name: "Growth System", option_two_kicker: "Most versatile", option_two_text: "Connected strategy and delivery for teams building momentum.", option_two_badge: "RECOMMENDED", option_three_name: "Embedded Partner", option_three_kicker: "Ongoing capability", option_three_text: "Flexible senior support across complex, evolving priorities.", feature_one: "Strategic direction", option_one_one: "Focused", option_two_one: "Integrated", option_three_one: "Embedded", feature_two: "Research depth", option_one_two: "Essentials", option_two_two: "Extended", option_three_two: "Continuous", feature_three: "Design systems", option_one_three: "Core", option_two_three: "Scalable", option_three_three: "Multi-brand", feature_four: "Delivery support", option_one_four: "Launch", option_two_four: "Launch + optimise", option_three_four: "Ongoing", feature_five: "Team access", option_one_five: "Lead specialist", option_two_five: "Cross-functional", option_three_five: "Dedicated pod", feature_six: "Reporting", option_one_six: "Wrap-up", option_two_six: "Monthly", option_three_six: "Custom cadence", feature_seven: "Best suited to", option_one_seven: "One clear priority", option_two_seven: "Growing teams", option_three_seven: "Complex programmes", feature_eight: "Engagement style", option_one_eight: "Fixed scope", option_two_eight: "Phased roadmap", option_three_eight: "Flexible retainer", primary_label: "Discuss the right approach", primary_url: "#", footnote: "Every engagement is shaped around your goals, team, and delivery requirements." },
    },


    {
        type: "services_hover_cards", theme: "auto", title: "Hover Cards Premium", buttonLabel: "Add Hover Cards", buttonClass: "bg-emerald-600 hover:bg-emerald-500", preview: ServicesHoverCardsPreview,
        payload: { type: "services_hover_cards", theme: "auto", eyebrow: "EXPLORE OUR CAPABILITIES", heading: "Specialist services, designed to work better together.", text: "Move from first idea to measurable improvement with senior support across strategy, design, technology, and growth.", primary_label: "Discuss your project", primary_url: "#", card_one_number: "01", card_one_title: "Digital strategy", card_one_summary: "Set the direction.", card_one_text: "Clarify the opportunity, align priorities, and turn ambition into a focused roadmap.", card_one_link: "Explore strategy", card_two_number: "02", card_two_title: "Brand systems", card_two_summary: "Build recognition.", card_two_text: "Create a flexible visual and verbal system that keeps every touchpoint consistent.", card_two_link: "Explore branding", card_three_number: "03", card_three_title: "Experience design", card_three_summary: "Make journeys intuitive.", card_three_text: "Shape clear user flows and polished interfaces around the needs of real customers.", card_three_link: "Explore experience", card_four_number: "04", card_four_title: "Web platforms", card_four_summary: "Create a stronger foundation.", card_four_text: "Build fast, responsive websites and platforms designed to evolve with your team.", card_four_link: "Explore platforms", card_five_number: "05", card_five_title: "Growth systems", card_five_summary: "Connect the funnel.", card_five_text: "Bring campaigns, content, conversion, and measurement into one repeatable system.", card_five_link: "Explore growth", card_six_number: "06", card_six_title: "Optimisation", card_six_summary: "Keep improving.", card_six_text: "Use focused testing and insight to improve performance after launch.", card_six_link: "Explore optimisation" },
    },

    {
        type: "hero_split_image",
        theme: "auto",
        title: "Hero Split Image",
        buttonLabel: "Add Split Hero",
        buttonClass: "bg-violet-600 hover:bg-violet-500",
        preview: HeroSplitImagePreview,
        payload: {
            type: "hero_split_image",
            tagline: "BUILT FOR WHAT'S NEXT",
            heading: "Make a stronger first impression.",
            text: "Tell your story clearly, show what makes your business different, and guide visitors toward the next step.",
            primary_label: "Get started",
            primary_url: "#",
            secondary_label: "Learn more",
            secondary_url: "#",
            trust_line: "Trusted by customers who value quality work.",
            image_badge: "Serving your community",
            image_url: "/storage/cms-images/background/background-1.avif",
        },
    },

    {
        type: "image_cta_banner",
        theme: "auto",
        title: "Image CTA Banner",
        buttonLabel: "Add CTA Banner",
        buttonClass: "bg-emerald-600 hover:bg-emerald-500",
        preview: ImageCtaBannerPreview,
        payload: {
            type: "image_cta_banner",
            eyebrow: "READY WHEN YOU ARE",
            heading: "Let’s make your next step simple.",
            text: "Talk with our team and get a clear plan for moving forward.",
            primary_label: "Get started",
            primary_url: "#",
            secondary_label: "Learn more",
            secondary_url: "#",
            image_url: "/storage/cms-images/background/background-1.avif",
            overlayOpacity: 76,
        },
    },

    {
        type: "contact_form_modern",
        theme: "auto",
        title: "Contact Form",
        buttonLabel: "Choose contact form",
        buttonClass: "bg-violet-600 hover:bg-violet-500",
        preview: ContactFormPreview,
        payload: {
            type: "contact_form_modern",
            theme: "auto",
            eyebrow: "START A CONVERSATION",
            heading: "Let’s talk about what’s next.",
            text: "Tell us a little about your goals and our team will help you find the right next step.",
            email: "hello@example.com",
            phone: "+1 (555) 010-0200",
            address: "Available by appointment",
            submit_label: "Send inquiry",
            fields: [
                { id: "name", name: "name", type: "text", label: "Name", placeholder: "Your name", required: true },
                { id: "email", name: "email", type: "email", label: "Email", placeholder: "you@example.com", required: true },
                { id: "phone", name: "phone", type: "tel", label: "Phone", placeholder: "Your phone number", required: false },
                { id: "message", name: "message", type: "textarea", label: "How can we help?", placeholder: "Tell us a little about your project", required: true },
            ],
        },
    },
    {
        type: "faq_accordion",
        theme: "auto",
        title: "FAQ Accordion",
        buttonLabel: "Choose FAQ layout",
        buttonClass: "bg-violet-600 hover:bg-violet-500",
        preview: FaqAccordionPreview,
        payload: {
            type: "faq_accordion",
            theme: "auto",
            eyebrow: "HELPFUL ANSWERS",
            heading: "Questions, answered clearly.",
            text: "Everything visitors need to know before taking the next step.",
            faqs: [
                { question: "What services do you offer?", answer: "We provide clear, practical support tailored to your needs." },
                { question: "How do I get started?", answer: "Reach out with a short note and we will help you choose the right next step." },
                { question: "Can I request a consultation?", answer: "Yes. Use the contact details on this page to arrange a conversation." },
                { question: "What should I prepare?", answer: "Share your goals, timeline, and any questions you would like us to cover." },
            ],
        },
    },
    {
        type: "contact_details",
        theme: "auto",
        title: "Contact Details",
        buttonLabel: "Choose contact details",
        buttonClass: "bg-violet-600 hover:bg-violet-500",
        preview: ContactDetailsPreview,
        payload: {
            type: "contact_details",
            theme: "auto",
            eyebrow: "GET IN TOUCH",
            heading: "Let’s start a conversation.",
            text: "Reach out when you are ready to discuss your next project or question.",
            email: "hello@example.com",
            phone: "+1 (555) 010-0200",
            address: "Available by appointment",
            hours: "Monday to Friday, 9:00 AM to 5:00 PM",
        },
    },
    {
        type: "location_map",
        theme: "auto",
        title: "Location & Directions",
        buttonLabel: "Choose location layout",
        buttonClass: "bg-violet-600 hover:bg-violet-500",
        preview: LocationMapPreview,
        payload: {
            type: "location_map",
            theme: "auto",
            eyebrow: "FIND US",
            heading: "Visit us when it works for you.",
            text: "Plan your visit with clear location details and directions.",
            location_name: "Our studio",
            address: "Available by appointment",
            directions_label: "Get directions",
            directions_url: "#",
        },
    },
    {
        type: "case_studies_grid", theme: "auto", title: "Case Studies Grid", buttonLabel: "Choose case studies layout", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: CaseStudiesGridPreview,
        payload: { type: "case_studies_grid", theme: "auto", eyebrow: "SELECTED WORK", heading: "Results that make the difference.", text: "A closer look at focused work shaped around clear goals and practical outcomes.", studies: [
            { category: "Strategy", title: "A clearer digital path", summary: "A focused engagement that turned a complex challenge into a practical next step.", result: "Built for measurable progress", image_url: "/storage/cms-images/background/background-1.avif", link_label: "View case study" },
            { category: "Design", title: "An experience made simpler", summary: "A thoughtful redesign that made important information easier to find and act on.", result: "Clarity at every step", image_url: "/storage/cms-images/background/background-2.avif", link_label: "View case study" },
            { category: "Growth", title: "A stronger launch foundation", summary: "A collaborative project built around the real customer journey.", result: "Ready to grow", image_url: "/storage/cms-images/background/background-3.avif", link_label: "View case study" },
        ] },
    },
    {
        type: "jobs_list", theme: "auto", title: "Jobs List", buttonLabel: "Choose careers layout", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: JobsListPreview,
        payload: { type: "jobs_list", theme: "auto", eyebrow: "JOIN OUR TEAM", heading: "Do work that moves things forward.", text: "We are looking for thoughtful people who care about good work and shared progress.", jobs: [
            { title: "Senior designer", type: "Full-time", location: "New York, NY", description: "Help shape thoughtful digital experiences for ambitious teams and their customers.", button_label: "View role" },
            { title: "Project manager", type: "Full-time", location: "Remote", description: "Keep client work organized, moving clearly, and grounded in practical next steps.", button_label: "View role" },
            { title: "Growth strategist", type: "Flexible", location: "Hybrid", description: "Turn research and collaboration into clear opportunities for clients.", button_label: "View role" },
        ] },
    },
    {
        type: "events_grid", theme: "auto", title: "Events Grid", buttonLabel: "Choose events layout", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: EventsGridPreview,
        payload: { type: "events_grid", theme: "auto", eyebrow: "UPCOMING EVENTS", heading: "Useful conversations, coming up.", text: "Join practical sessions, thoughtful gatherings, and opportunities to connect with our team.", events: [
            { month: "OCT", day: "12", title: "A practical session for your next move", date: "October 12 - 10:00 AM", location: "Online", description: "Useful ideas you can put into action right away.", button_label: "Reserve a place" },
            { month: "NOV", day: "04", title: "Meet the people behind the work", date: "November 4 - 6:00 PM", location: "Our studio", description: "An informal evening to connect and exchange ideas.", button_label: "Save your seat" },
            { month: "DEC", day: "08", title: "Plan a stronger year ahead", date: "December 8 - 1:00 PM", location: "Online", description: "A guided planning session for teams setting clearer priorities.", button_label: "Join the session" },
        ] },
    },

    { type: "mini_hero_minimal", theme: "auto", title: "Mini Hero Minimal", buttonLabel: "Add Mini Hero", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: HeroCenteredPreview, payload: { type:"mini_hero_minimal", theme:"auto", eyebrow:"Explore more", heading:"A focused page for what matters next", text:"Use a compact hero to introduce this page without taking over the whole screen.", button_label:"Explore", button_url:"#" } },
    { type: "mini_hero_split", theme: "auto", title: "Mini Hero Split Image", buttonLabel: "Add Split Mini Hero", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: HeroSplitImagePreview, payload: { type:"mini_hero_split", theme:"auto", eyebrow:"Discover the collection", heading:"Designed for everyday essentials", text:"A concise introduction with a strong supporting visual.", button_label:"Explore", button_url:"#", image_url:"/storage/cms-images/background/background-2.avif", image_alt:"Featured page image" } },
    { type: "mini_hero_promo", theme: "auto", title: "Mini Hero Promo", buttonLabel: "Add Promo Mini Hero", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: ImageCtaBannerPreview, payload: { type:"mini_hero_promo", theme:"auto", eyebrow:"Featured now", heading:"Something worth discovering", text:"Highlight a collection, announcement, or important next step without using a full-height hero.", button_label:"Shop now", button_url:"#", image_url:"/storage/cms-images/background/background-3.avif", image_alt:"Promotional page image" } },

    { type: "commerce_product_grid", theme: "auto", title: "Product Grid", buttonLabel: "Add Product Grid", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: ServicesGridPreview, payload: { type:"commerce_product_grid", theme:"auto", heading:"Featured products", text:"Discover customer favorites and new arrivals.", limit:8, featured_only:false } },
    { type: "commerce_catalog_grid", theme: "auto", title: "Shop Catalog Grid", buttonLabel: "Add Catalog Grid", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: ServicesGridPreview, payload: { type:"commerce_catalog_grid", theme:"auto", heading:"Shop the catalog", text:"Browse products, filter collections, and find what fits.", limit:12, show_toolbar:true } },
    { type: "commerce_catalog_editorial", theme: "auto", title: "Shop Catalog Editorial", buttonLabel: "Add Editorial Catalog", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: FeatureLeftPreview, payload: { type:"commerce_catalog_editorial", theme:"auto", heading:"Curated for you", text:"Explore an image-first collection with a more editorial rhythm.", limit:10, show_toolbar:true } },
    { type: "commerce_catalog_compact", theme: "auto", title: "Shop Catalog Compact", buttonLabel: "Add Compact Catalog", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: ServicesFeatureComparisonPreview, payload: { type:"commerce_catalog_compact", theme:"auto", heading:"Browse all products", text:"A compact catalog built for quick comparison.", limit:16, show_toolbar:true } },
    { type: "commerce_categories", theme: "auto", title: "Shop Categories", buttonLabel: "Add Categories", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: ServicesBentoPreview, payload: { type:"commerce_categories", theme:"auto", heading:"Shop by category", text:"Find the collection that fits what you need." } },
    { type: "commerce_featured_products", theme: "auto", title: "Featured Products", buttonLabel: "Add Featured Products", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: ServicesGridPreview, payload: { type:"commerce_featured_products", theme:"auto", heading:"Featured picks", text:"A curated selection worth a closer look.", limit:4 } },
    { type: "commerce_featured_collection", theme: "auto", title: "Featured Collection", buttonLabel: "Add Featured Collection", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: ServicesGridPreview, payload: { type:"commerce_featured_collection", theme:"auto", heading:"Featured collection", text:"Explore a focused edit from one collection.", category_id:null, limit:4, button_label:"View collection" } },
    { type: "commerce_promo_split", theme: "auto", title: "Promo Split Banner", buttonLabel: "Add Promo Banner", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: FeatureLeftPreview, payload: { type:"commerce_promo_split", theme:"auto", eyebrow:"Limited collection", heading:"A standout offer for the season", text:"Pair a strong message with a product-led visual and a clear next step.", button_label:"Shop the collection", button_url:"/shop", image_url:"/storage/cms-images/background/background-3.avif", image_alt:"Featured collection" } },
    { type: "commerce_benefits_strip", theme: "auto", title: "Benefits / Trust Strip", buttonLabel: "Add Benefits Strip", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: ServicesFeatureComparisonPreview, payload: { type:"commerce_benefits_strip", theme:"auto", heading:"Shop with confidence", benefit_1_title:"Secure checkout", benefit_1_text:"Protected payment flow", benefit_2_title:"Fast delivery", benefit_2_text:"Clear shipping options", benefit_3_title:"Easy returns", benefit_3_text:"Straightforward support", benefit_4_title:"Here to help", benefit_4_text:"Customer care when needed" } },
    { type: "commerce_product_gallery", theme: "auto", title: "Product Gallery", buttonLabel: "Add Product Gallery", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: FeatureLeftPreview, payload: { type:"commerce_product_gallery", theme:"auto", heading:"Product gallery", product_id:null } },
    { type: "commerce_price", theme: "auto", title: "Product Price", buttonLabel: "Add Price", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: PricingCardsPreview, payload: { type:"commerce_price", theme:"auto", product_id:null, label:"Price" } },
    { type: "commerce_variation_selector", theme: "auto", title: "Variation Selector", buttonLabel: "Add Variations", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: ServicesFeatureComparisonPreview, payload: { type:"commerce_variation_selector", theme:"auto", product_id:null, heading:"Choose your options" } },
    { type: "commerce_related_products", theme: "auto", title: "Related Products", buttonLabel: "Add Related Products", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: ServicesGridPreview, payload: { type:"commerce_related_products", theme:"auto", product_id:null, heading:"You may also like", limit:4 } },
    { type: "commerce_mini_cart", theme: "auto", title: "Mini Cart Shell", buttonLabel: "Add Mini Cart", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: PricingCardsPreview, payload: { type:"commerce_mini_cart", theme:"auto", heading:"Your cart", empty_text:"Your cart is ready for products.", button_label:"View cart" } },
    { type: "commerce_cart_classic", theme: "auto", title: "Cart Classic", buttonLabel: "Add Cart Classic", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: PricingCardsPreview, payload: { type:"commerce_cart_classic", theme:"auto", heading:"Your cart", text:"Review your items before checkout.", checkout_label:"Proceed to checkout", continue_label:"Continue shopping" } },
    { type: "commerce_cart_split", theme: "auto", title: "Cart Split Summary", buttonLabel: "Add Split Cart", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: ServicesFeatureComparisonPreview, payload: { type:"commerce_cart_split", theme:"auto", heading:"Review your bag", text:"Everything looks good? Continue securely to checkout.", checkout_label:"Secure checkout", continue_label:"Keep shopping" } },
    { type: "commerce_cart_compact", theme: "auto", title: "Cart Compact", buttonLabel: "Add Compact Cart", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: ServicesGridPreview, payload: { type:"commerce_cart_compact", theme:"auto", heading:"Cart summary", text:"Quickly review quantities and totals.", checkout_label:"Checkout", continue_label:"Back to shop" } },
    { type: "commerce_checkout_classic", theme: "auto", title: "Checkout Classic", buttonLabel: "Add Checkout Classic", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: PricingCardsPreview, payload: { type:"commerce_checkout_classic", theme:"auto", heading:"Checkout", text:"Complete your details and review the order securely.", payment_label:"Continue to payment", help_text:"Secure checkout powered by the commerce runtime." } },
    { type: "commerce_checkout_split", theme: "auto", title: "Checkout Split", buttonLabel: "Add Split Checkout", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: ServicesFeatureComparisonPreview, payload: { type:"commerce_checkout_split", theme:"auto", heading:"Secure checkout", text:"Delivery details on the left, live order summary on the right.", payment_label:"Pay securely", help_text:"Shipping, tax, coupons and payment stay runtime-controlled." } },
    { type: "commerce_checkout_express", theme: "auto", title: "Checkout Express", buttonLabel: "Add Express Checkout", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: ContactFormPreview, payload: { type:"commerce_checkout_express", theme:"auto", heading:"Express checkout", text:"A streamlined path from customer details to payment.", payment_label:"Complete purchase", help_text:"Fast, focused and secure." } },
    { type:"content_grid_classic", theme:"auto", title:"Content Loop · Grid", buttonLabel:"Add Content Loop", buttonClass:"bg-violet-600 hover:bg-violet-500", preview: CaseStudiesGridPreview, payload:{type:"content_grid_classic",theme:"auto",eyebrow:"LATEST STORIES",heading:"Fresh from our updates",text:"Explore the latest articles, events, projects, and updates.",content_type_slug:"blog",category:"",tag:"",sort:"newest",limit:6}},
    { type:"content_grid_editorial", theme:"auto", title:"Content Loop · Editorial", buttonLabel:"Add Editorial Loop", buttonClass:"bg-violet-600 hover:bg-violet-500", preview: FeatureLeftPreview, payload:{type:"content_grid_editorial",theme:"auto",eyebrow:"EDITORIAL",heading:"Stories worth exploring",text:"A more visual way to present your latest content.",content_type_slug:"blog",category:"",tag:"",sort:"newest",limit:5}},
    { type:"content_grid_compact", theme:"auto", title:"Content Loop · List", buttonLabel:"Add Content List", buttonClass:"bg-violet-600 hover:bg-violet-500", preview: CaseStudiesGridPreview, payload:{type:"content_grid_compact",theme:"auto",eyebrow:"UPDATES",heading:"Latest updates",text:"A compact archive for quick scanning.",content_type_slug:"blog",category:"",tag:"",sort:"newest",limit:8}},
    { type:"content_featured_entry", theme:"auto", title:"Featured Post", buttonLabel:"Add Featured Post", buttonClass:"bg-violet-600 hover:bg-violet-500", preview: FeatureLeftPreview, payload:{type:"content_featured_entry",theme:"auto",eyebrow:"FEATURED",heading:"Featured story",text:"Highlight one important entry.",content_type_slug:"blog",featured_only:true,sort:"newest",limit:1}},
    { type:"content_latest_entries", theme:"auto", title:"Latest Posts", buttonLabel:"Add Latest Posts", buttonClass:"bg-violet-600 hover:bg-violet-500", preview: CaseStudiesGridPreview, payload:{type:"content_latest_entries",theme:"auto",eyebrow:"LATEST",heading:"Latest posts",text:"Keep your newest content close at hand.",content_type_slug:"blog",sort:"newest",limit:4}},
    { type:"content_events_grid", theme:"auto", title:"Upcoming Events", buttonLabel:"Add Events Grid", buttonClass:"bg-violet-600 hover:bg-violet-500", preview: EventsGridPreview, payload:{type:"content_events_grid",theme:"auto",eyebrow:"UPCOMING EVENTS",heading:"What’s coming up",text:"Upcoming events, workshops, and gatherings.",content_type_slug:"events",sort:"event_date",upcoming_only:true,limit:6}},

];
