import axios from "axios";
import { useState } from "react";
import { useRef } from "react";
import { showCosmicNotification } from "../../../Components/CosmicNotification";
import { ACTION_PRICING, getBlockPrice } from "../../../cosmic/pricing";
import CreditBalanceBadge from "../../../Components/CosmicCredits/CreditBalanceBadge";
import { useCreditBalance } from "../../../Components/CosmicCredits/CreditBalanceContext";
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
];
