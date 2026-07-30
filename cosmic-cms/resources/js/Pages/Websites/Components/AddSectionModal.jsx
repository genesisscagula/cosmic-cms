import axios from "axios";
import { useState } from "react";
import { useRef } from "react";
import { showCosmicNotification } from "../../../Components/CosmicNotification";

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
import HeroEditorialOverlayPreview from "./Previews/HeroEditorialOverlayPreview";
import HeroSplitImagePreview from "./Previews/HeroSplitImagePreview";
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

const generationProgressSteps = [
    { label: "Understand brief", threshold: 10 },
    { label: "Plan sections", threshold: 30 },
    { label: "Create content", threshold: 90 },
    { label: "Build page", threshold: 100 },
];

const sectionCategories = [
    { id: "hero", icon: "✦", title: "Hero", description: "Create a strong first impression." },
    { id: "services", icon: "▦", title: "Services", description: "Show what your business offers." },
    { id: "feature", icon: "◆", title: "Features", description: "Explain what makes you different." },
    { id: "pricing", icon: "₱", title: "Pricing", description: "Display packages and plans." },
    { id: "testimonials", icon: "★", title: "Testimonials", description: "Build trust with social proof." },
    { id: "process", icon: "→", title: "Process", description: "Show customers what happens next." },
    { id: "stats", icon: "#", title: "Stats", description: "Highlight measurable proof." },
    { id: "faq", icon: "?", title: "FAQ", description: "Answer common visitor questions." },
    { id: "team", icon: "â˜…", title: "Team", description: "Introduce the people behind your business." },
    { id: "case_studies", icon: "▣", title: "Case studies", description: "Show selected work and meaningful outcomes." },
    { id: "careers", icon: "◫", title: "Careers", description: "Share current opportunities with your team." },
    { id: "events", icon: "◷", title: "Events", description: "Promote upcoming sessions and gatherings." },
    { id: "cta", icon: "↗", title: "Call to action", description: "Guide visitors to take the next step." },
    { id: "contact", icon: "✉", title: "Contact", description: "Give visitors a clear way to reach you." },
];

export default function AddSectionModal({
    open,
    onClose,
    onAdd,
    onReplace,
    hasBlocks = false,
    hasWebsiteContent = false,
    websiteContext = "",
}) {

    const [prompt, setPrompt] = useState("");
    const [showConfirm, setShowConfirm] = useState(false);
    const [isBlockLibraryOpen, setIsBlockLibraryOpen] = useState(false);
    const [selectedSectionCategory, setSelectedSectionCategory] = useState(null);
    const [sectionInstruction, setSectionInstruction] = useState("");
    const [selectedSpecificBlock, setSelectedSpecificBlock] = useState(null);
    const [specificLayoutInstruction, setSpecificLayoutInstruction] = useState("");
    const [generationTarget, setGenerationTarget] = useState("page");

    const aiResult = {};

    const [isGenerating, setIsGenerating] = useState(false);
    const [aiStage, setAiStage] = useState("Planning your page...");
    const [progress, setProgress] = useState(0);

    const generateWithAI = () => {

        if (!hasBlocks) {
            executeGenerate();
            return;
        }

        setShowConfirm(true);

    };

    const progressRef = useRef(0);

    const random = (min, max) =>
        Math.floor(Math.random() * (max - min + 1)) + min;

    const sleep = (ms) =>
        new Promise(resolve => setTimeout(resolve, ms));

    const animateProgress = async (target) => {

        while (progressRef.current < target) {

            progressRef.current += random(1, 3);

            if (progressRef.current > target) {
                progressRef.current = target;
            }

            setProgress(progressRef.current);

            await sleep(random(40, 80));

        }

    };

    const nextStage = async (
        stage,
        target,
        minimumTime = 500
    ) => {

        setAiStage(stage);

        await Promise.all([
            animateProgress(target),
            sleep(minimumTime)
        ]);

    };

    const executeGenerate = async () => {

        setIsGenerating(true);

        progressRef.current = 0;

        setProgress(0);

        try {

            // =========================================
            // STEP 1
            // =========================================

            await nextStage(
                "🧠 Understanding your request...",
                random(5,10),
                700
            );

            const generationPrompt = [
                websiteContext || "Create professional website content.",
                prompt.trim() ? `Page request: ${prompt.trim()}` : "Create content appropriate for this page and business.",
            ].join("\n\n");

            const sectionResponse = await axios.post(
                "/ai/select-sections",
                {
                    prompt: generationPrompt
                }
            );

            const { sections, image_folder: imageFolder } = sectionResponse.data;

            // =========================================
            // STEP 2
            // =========================================

            await nextStage(
                "📐 Choosing the best layout...",
                random(18,30),
                700
            );

            // =========================================
            // STEP 3
            // =========================================

            await nextStage(
                "🎨 Selecting the best design blocks...",
                random(38,48),
                600
            );

            // =========================================
            // STEP 4
            // =========================================

            setAiStage("✍ Writing professional content...");

            await animateProgress(90);

            const contentResponse = await axios.post(
                "/ai/generate-content",
                {
                    prompt: generationPrompt,
                    sections,
                    image_folder: imageFolder
                }
            );


            console.log(contentResponse.data.blocks);


            // =========================================
            // STEP 5
            // =========================================

            await nextStage(
                "🖼 Matching industry images...",
                random(92,96),
                500
            );

            await nextStage(
                "🚀 Building your page...",
                99,
                500
            );

            if (contentResponse.data?.blocks?.length) {

                onReplace(contentResponse.data.blocks);

                progressRef.current = 100;

                setProgress(100);

                setAiStage("✅ Done!");

                await sleep(700);

                setPrompt("");

                setIsGenerating(false);

                onClose();

            } else {

                setIsGenerating(false);

                showCosmicNotification({ title: "No sections generated", message: "Cosmic AI did not return any usable sections. Please try a more specific prompt.", tone: "error" });

            }

        } catch (error) {

            console.log(error);

            setIsGenerating(false);

            showCosmicNotification({
                title: "Generation failed",
                message: error.response?.data?.message || "Cosmic AI could not generate the page. Please try again.",
                tone: "error",
            });

        }

    };



    const generateSectionWithAI = async () => {
        const category = sectionCategories.find((item) => item.id === selectedSectionCategory);

        if (!category) {
            showCosmicNotification({ title: "Choose a section", message: "Select the kind of section you want to add first.", tone: "info" });
            return;
        }

        const sectionPrompt = [
            websiteContext || "Create professional website content.",
            sectionInstruction.trim()
                ? `Section request: ${sectionInstruction.trim()}`
                : `Create a ${category.title.toLowerCase()} section that fits the existing website.`,
        ].join("\n\n");

        setGenerationTarget("section");
        setIsGenerating(true);
        progressRef.current = 0;
        setProgress(0);

        try {
            await nextStage("Understanding this section...", random(12, 22), 450);

            const selectionResponse = await axios.post("/ai/select-section", {
                category: category.id,
                prompt: sectionPrompt,
            });

            const { section, image_folder: imageFolder } = selectionResponse.data;

            await nextStage("Choosing a compatible layout...", random(34, 48), 450);
            await nextStage("Writing content for your website...", 82, 600);

            const contentResponse = await axios.post("/ai/generate-content", {
                prompt: sectionPrompt,
                sections: [section],
                image_folder: imageFolder,
            });

            const generatedBlock = contentResponse.data?.blocks?.[0];

            if (!generatedBlock) {
                throw new Error("No usable section was returned.");
            }

            await nextStage("Adding your new section...", 100, 350);
            onAdd(generatedBlock);
            setSectionInstruction("");
            setSelectedSectionCategory(null);
            showCosmicNotification({ title: "Section added", message: `${category.title} was created and added to your page.`, tone: "success" });
        } catch (error) {
            console.log(error);
            showCosmicNotification({
                title: "Section generation failed",
                message: error.response?.data?.message || error.message || "Cosmic AI could not create this section. Please try again.",
                tone: "error",
            });
        } finally {
            setIsGenerating(false);
            setGenerationTarget("page");
        }
    };

    const generateSpecificLayoutWithAI = async () => {
        if (!selectedSpecificBlock) {
            return;
        }

        const contentPrompt = [
            websiteContext || "Create professional website content.",
            specificLayoutInstruction.trim()
                ? `Section request: ${specificLayoutInstruction.trim()}`
                : `Create content for a ${selectedSpecificBlock.title} section that fits the existing website.`,
        ].join("\n\n");

        setGenerationTarget("specific-layout");
        setIsGenerating(true);
        progressRef.current = 0;
        setProgress(0);

        try {
            await nextStage("Using your selected layout...", random(18, 32), 400);
            setAiStage("Getting content from AI...");
            await animateProgress(82);

            const contentResponse = await axios.post("/ai/generate-content", {
                prompt: contentPrompt,
                sections: [selectedSpecificBlock.type],
            });

            const generatedBlock = contentResponse.data?.blocks?.[0];

            if (!generatedBlock) {
                throw new Error("No usable section was returned.");
            }

            await nextStage("Adding your selected layout...", 100, 350);
            onAdd(generatedBlock);
            setSpecificLayoutInstruction("");
            setSelectedSpecificBlock(null);
            showCosmicNotification({ title: "Section added", message: `${selectedSpecificBlock.title} was filled with AI-generated content.`, tone: "success" });
        } catch (error) {
            console.log(error);
            showCosmicNotification({
                title: "Content generation failed",
                message: error.response?.data?.message || error.message || "Cosmic AI could not create content for this layout. Please try again.",
                tone: "error",
            });
        } finally {
            setIsGenerating(false);
            setGenerationTarget("page");
        }
    };

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/75 p-3 backdrop-blur-sm sm:p-6">

            <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="add-section-modal-title"
            className="
                flex flex-col
                bg-[#111114]
                border border-white/10
                rounded-2xl
                w-full
                max-w-5xl
                h-[min(88dvh,820px)]
                max-h-[calc(100dvh-1.5rem)]
                shadow-2xl shadow-black/50
                text-slate-100
                overflow-hidden
            "
        >

        {/* Header */}

        <div className="shrink-0 border-b border-white/10 bg-[#151519] px-4 py-4 sm:px-6">

            <div className="flex items-start justify-between">

                <div>

                    <h2 id="add-section-modal-title" className="flex items-center gap-2 text-xl font-bold text-white sm:text-2xl">

                        ✨ AI Page Generator

                    </h2>

                    <p className="text-slate-400 mt-2 max-w-2xl leading-7">

                        Describe your business and goals. Cosmic AI plans a tailored
                        page, selects the right sections, and writes content that
                        matches your brand and website theme.

                    </p>

                </div>

                <button
                    onClick={onClose}
                    type="button"
                    aria-label="Close Add Section"
                    className="flex h-9 w-9 items-center justify-center rounded-lg text-xl text-slate-400 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400"
                >
                    ✕

                </button>

            </div>

        </div>

        <div className="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6
            [&::-webkit-scrollbar]:w-2
            [&::-webkit-scrollbar-track]:bg-transparent
            [&::-webkit-scrollbar-thumb]:rounded-full
            [&::-webkit-scrollbar-thumb]:bg-slate-700
            hover:[&::-webkit-scrollbar-thumb]:bg-violet-500/70">

            {/* Prompt */}

            <div className="space-y-5">

                <textarea

                    value={prompt}

                    onChange={(e) => setPrompt(e.target.value)}

                    rows={4}

                    placeholder="Optional: describe the page you want, such as an About page focused on services, testimonials, and booking."

                    aria-label="Describe the page to generate"
                    className="w-full resize-none rounded-xl border border-white/10 bg-black/30 p-4 text-sm leading-6 text-white placeholder:text-slate-500 transition focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-400/20"

                    onKeyDown={(e) => {

                        if (e.key === "Enter" && !e.shiftKey) {

                            e.preventDefault();

                            generateWithAI();

                        }

                    }}

                />

                {/* Quick Prompts */}

                <div>

                    <p className="mb-2 text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500">

                        Quick Ideas

                    </p>

                    <div className="flex flex-wrap gap-2">

                        {[
                            "🏥 Dental Clinic",
                            "🍽 Restaurant",
                            "🏗 Construction",
                            "💻 SaaS Startup",
                            "🏡 Real Estate",
                            "🏋 Fitness Gym",
                            "⚖ Law Firm",
                            "☕ Coffee Shop"
                        ].map((item) => (

                            <button

                                key={item}
                                type="button"
                                onClick={() => setPrompt(item)}

                                className="rounded-full border border-white/10 bg-white/[0.04] px-3 py-1.5 text-xs text-slate-300 transition hover:border-violet-400/40 hover:bg-violet-400/10 hover:text-violet-100 focus:outline-none focus:ring-2 focus:ring-violet-400"

                            >

                                {item}

                            </button>

                        ))}

                    </div>

                </div>

                {/* AI Options */}

                <div className="grid grid-cols-1 gap-2 text-sm sm:grid-cols-3">

                    <label className="flex items-center gap-2 rounded-lg border border-white/10 bg-white/[0.03] px-3 py-2 text-xs font-medium text-slate-300">

                        <input
                            type="checkbox"
                            checked
                            readOnly
                            aria-label="Generate Layout enabled"
                            className="h-3.5 w-3.5 accent-violet-500"
                        />

                        Generate Layout

                    </label>

                    <label className="flex items-center gap-2 rounded-lg border border-white/10 bg-white/[0.03] px-3 py-2 text-xs font-medium text-slate-300">

                        <input
                            type="checkbox"
                            checked
                            readOnly
                            aria-label="Generate Content enabled"
                            className="h-3.5 w-3.5 accent-violet-500"
                        />

                        Generate Content

                    </label>

                    <label className="flex items-center gap-2 rounded-lg border border-white/10 bg-white/[0.03] px-3 py-2 text-xs font-medium text-slate-300">

                        <input
                            type="checkbox"
                            checked
                            readOnly
                            aria-label="Match Website Theme enabled"
                            className="h-3.5 w-3.5 accent-violet-500"
                        />

                        Match Website Theme

                    </label>

                </div>

                {/* Generate */}

                <button

                    onClick={generateWithAI}

                    type="button"
                    disabled={isGenerating}
                    className="flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-violet-950/30 transition hover:from-violet-500 hover:to-indigo-500 focus:outline-none focus:ring-2 focus:ring-violet-300 disabled:cursor-not-allowed disabled:opacity-50"

                >

                    ✨ Generate Page

                </button>

            </div>

            <div className="mt-6 border-t border-white/10 pt-5">
                <div className="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-[10px] font-semibold uppercase tracking-[0.18em] text-violet-300">Add a section with AI</p>
                        <h3 className="mt-1 text-base font-semibold text-white">Choose what this page needs</h3>
                        <p className="mt-1 text-xs leading-5 text-slate-400">Cosmic chooses a compatible layout variation, then writes content for it.</p>
                    </div>
                    <span className="text-xs text-slate-500">PHP chooses layout · AI writes content</span>
                </div>

                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    {sectionCategories.map((category) => {
                        const selected = selectedSectionCategory === category.id;

                        return (
                            <button
                                key={category.id}
                                type="button"
                                onClick={() => setSelectedSectionCategory(category.id)}
                                aria-pressed={selected}
                                className={`rounded-xl border p-3 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400 ${selected ? "border-violet-400/70 bg-violet-400/[0.10] shadow-[0_0_0_1px_rgba(167,139,250,0.15)]" : "border-white/10 bg-white/[0.025] hover:border-white/25 hover:bg-white/[0.05]"}`}
                            >
                                <span className={`flex h-7 w-7 items-center justify-center rounded-lg text-sm ${selected ? "bg-violet-400/15 text-violet-200" : "bg-white/[0.06] text-slate-300"}`} aria-hidden="true">{category.icon}</span>
                                <span className="mt-3 block text-sm font-semibold text-white">{category.title}</span>
                                <span className="mt-1 block text-xs leading-4 text-slate-400">{category.description}</span>
                            </button>
                        );
                    })}
                </div>

                {selectedSectionCategory && (
                    <div className="mt-3 rounded-xl border border-violet-400/20 bg-violet-400/[0.045] p-3 sm:p-4">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <label className="min-w-0 flex-1">
                                <span className="text-xs font-semibold text-white">What should this section communicate? <span className="font-normal text-slate-500">(optional)</span></span>
                                <textarea
                                    value={sectionInstruction}
                                    onChange={(event) => setSectionInstruction(event.target.value)}
                                    rows={2}
                                    placeholder="Optional: e.g. Highlight family rooms, pools, and airport access."
                                    className="mt-2 w-full resize-none rounded-lg border border-white/10 bg-black/25 px-3 py-2.5 text-sm leading-5 text-white placeholder:text-slate-500 focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-400/20"
                                />
                            </label>
                            <button
                                type="button"
                                onClick={generateSectionWithAI}
                                disabled={isGenerating}
                                className="inline-flex h-10 shrink-0 items-center justify-center rounded-lg bg-violet-600 px-4 text-sm font-semibold text-white transition hover:bg-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-300 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                Generate section
                            </button>
                        </div>
                    </div>
                )}
            </div>

            <div className="mt-5">
                <button
                    type="button"
                    onClick={() => setIsBlockLibraryOpen(!isBlockLibraryOpen)}
                    aria-expanded={isBlockLibraryOpen}
                    className="flex w-full items-center justify-between rounded-xl border border-white/10 bg-white/[0.03] px-4 py-3 text-left transition hover:bg-white/[0.06] focus:outline-none focus:ring-2 focus:ring-violet-400"
                >
                    <span>
                        <span className="block text-sm font-semibold text-white">Choose a specific layout</span>
                        <span className="mt-0.5 block text-xs text-slate-500">Use this only when you want a particular section design.</span>
                    </span>
                    <span className="text-lg text-slate-400" aria-hidden="true">{isBlockLibraryOpen ? '−' : '+'}</span>
                </button>
            </div>

            {isBlockLibraryOpen && (
                <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    {BlockRegistry.map((block) => (
                        <BlockPreviewCard
                            key={block.type}
                            onAdd={() => {
                                setSelectedSpecificBlock(block);
                                setSpecificLayoutInstruction("");
                            }}
                            title={block.title}
                            buttonLabel="Choose this layout"
                            buttonClass="bg-violet-600 hover:bg-violet-500"
                            preview={block.preview}
                            payload={block.payload}
                        />
                    ))}
                </div>
            )}

            {selectedSpecificBlock && (
                <div className="fixed inset-0 z-[1000] flex items-center justify-center p-4 sm:p-6">
                    <button
                        type="button"
                        onClick={() => setSelectedSpecificBlock(null)}
                        className="absolute inset-0 bg-black/75 backdrop-blur-sm"
                        aria-label="Close selected layout dialog"
                    />
                    <div role="dialog" aria-modal="true" aria-labelledby="selected-layout-title" className="relative z-10 w-full max-w-lg rounded-2xl border border-violet-400/25 bg-[#18181b] p-5 shadow-2xl shadow-black/60 sm:p-6">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-violet-300">Selected layout</p>
                            <h4 id="selected-layout-title" className="mt-1 text-lg font-semibold text-white">{selectedSpecificBlock.title}</h4>
                            <p className="mt-1.5 text-sm leading-6 text-slate-400">Cosmic AI will keep this layout and generate content tailored to your website.</p>
                        </div>
                        <button
                            type="button"
                            onClick={() => setSelectedSpecificBlock(null)}
                            className="rounded-lg p-1 text-slate-400 transition hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400"
                            aria-label="Clear selected layout"
                        >
                            ×
                        </button>
                    </div>

                    <label className="mt-5 block">
                        <span className="text-sm font-semibold text-white">What should this section communicate? <span className="font-normal text-slate-500">(optional)</span></span>
                        <textarea
                            value={specificLayoutInstruction}
                            onChange={(event) => setSpecificLayoutInstruction(event.target.value)}
                            rows={4}
                            autoFocus
                            placeholder="Optional: e.g. Focus on ocean-view rooms and family amenities."
                            className="mt-2 w-full resize-none rounded-xl border border-white/10 bg-black/25 px-3.5 py-3 text-sm leading-6 text-white placeholder:text-slate-500 focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-400/20"
                        />
                    </label>

                    <div className="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            onClick={() => setSelectedSpecificBlock(null)}
                            disabled={isGenerating}
                            className="inline-flex h-10 items-center justify-center rounded-lg px-4 text-sm font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-300 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            onClick={generateSpecificLayoutWithAI}
                            disabled={isGenerating}
                            className="inline-flex h-10 items-center justify-center rounded-lg bg-violet-600 px-4 text-sm font-semibold text-white transition hover:bg-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-300 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {isGenerating ? "Getting content from AI..." : "Generate with this layout"}
                        </button>
                    </div>
                </div>
                </div>
            )}

            {/* Divider */}

            <div className="hidden flex items-center gap-6 my-12">

                <div className="flex-1 border-t border-slate-800" />

                <span className="text-xs uppercase tracking-[0.35em] text-slate-500">

                    Or Browse Professional Blocks

                </span>

                <div className="flex-1 border-t border-slate-800" />

            </div>

            {/* Registry */}

            <div className="hidden">

                <div className="grid grid-cols-1 md:grid-cols-2 gap-8">

                    {BlockRegistry.map((block) => (

                        <BlockPreviewCard
                            key={block.type}

                            onAdd={onAdd}

                            title={block.title}

                            buttonLabel={block.buttonLabel}

                            buttonClass={block.buttonClass}

                            preview={block.preview}

                            payload={block.payload}
                        />

                    ))}

                </div>

            </div>

        </div>

    </div>
    {
        showConfirm && (

            <div className="fixed inset-0 z-[999] flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm">

                <div role="alertdialog" aria-modal="true" aria-labelledby="replace-page-title" className="w-full max-w-md rounded-2xl border border-white/10 bg-[#151519] p-5 shadow-2xl shadow-black/50 sm:p-6">

                    <div className="mb-4 flex h-10 w-10 items-center justify-center rounded-xl border border-amber-400/20 bg-amber-400/10 text-lg text-amber-200">!</div>
                    <h3 id="replace-page-title" className="text-xl font-bold text-white">
                        Replace Current Page?
                    </h3>

                    <p className="mt-2 text-sm leading-6 text-slate-400">This action replaces the page's current content with the generated layout.</p>

                    <div className="mt-5 rounded-xl border border-red-400/15 bg-red-400/[0.06] p-3">
                        <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-red-200">Will be replaced</p>
                        <p className="mt-1 text-sm font-medium text-slate-200">All current page blocks and their content</p>
                    </div>

                    <div className="mt-3 rounded-xl border border-emerald-400/15 bg-emerald-400/[0.05] p-3">
                        <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-emerald-200">Will be preserved</p>
                        <ul className="mt-2 space-y-1.5 text-sm text-slate-300">
                            <li className="flex items-center gap-2"><span className="text-emerald-300" aria-hidden="true">✓</span> Global header</li>
                            <li className="flex items-center gap-2"><span className="text-emerald-300" aria-hidden="true">✓</span> Global footer</li>
                            <li className="flex items-center gap-2"><span className="text-emerald-300" aria-hidden="true">✓</span> Website theme settings</li>
                        </ul>
                    </div>

                    <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">

                        <button
                            type="button"
                            onClick={() => setShowConfirm(false)}
                            className="rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400"
                        >
                            Cancel
                        </button>

                        <button
                            type="button"
                            onClick={() => {
                                setShowConfirm(false);
                                executeGenerate();
                            }}
                            className="rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-300"
                        >
                            ✨ Replace & Generate
                        </button>

                    </div>

                </div>

            </div>

        )
    }
    {
        isGenerating && (

            <div className="fixed inset-0 z-[99999] flex items-center justify-center bg-black/80 p-4 backdrop-blur-md">

                <div className="w-full max-w-xl rounded-2xl border border-white/10 bg-[#151519]/95 px-5 py-7 shadow-2xl shadow-black/60 sm:px-8 sm:py-8">

                    <div className="flex justify-center">

                        <div className="relative flex justify-center">

                            <div className="absolute h-36 w-36 rounded-full bg-violet-500/20 blur-3xl animate-pulse" />

                            <div className="h-20 w-20 rounded-full border-4 border-violet-500 border-t-cyan-400 border-r-indigo-400 animate-spin" />

                            <div className="absolute inset-0 flex items-center justify-center">

                                <svg
                                    className="h-8 w-8 animate-pulse text-cyan-300"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="2"
                                    viewBox="0 0 24 24"
                                >
                                    <path d="M12 2v4M12 18v4M2 12h4M18 12h4M5 5l3 3M16 16l3 3M19 5l-3 3M8 16l-3 3"/>
                                </svg>

                            </div>

                        </div>

                    </div>

                    <p className="mt-6 text-center text-[10px] font-semibold uppercase tracking-[0.22em] text-violet-300">Cosmic AI</p>
                    <h2 className="mt-2 text-center text-2xl font-bold text-white sm:text-3xl">

                        {generationTarget === "page" ? "Building your page" : "Creating your section"}

                    </h2>

                    <p className="mt-3 text-center text-sm text-slate-300" aria-live="polite">

                        {aiStage || (generationTarget === "page" ? "Generating your page..." : "Getting content from AI...")}

                    </p>

                    <div className="mt-6 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        {generationProgressSteps.map((step, index) => {
                            const previousThreshold = index === 0 ? 0 : generationProgressSteps[index - 1].threshold;
                            const isComplete = progress >= step.threshold;
                            const isCurrent = !isComplete && progress >= previousThreshold;

                            return (
                                <div key={step.label} className={`rounded-lg border px-2.5 py-2 ${isComplete ? 'border-emerald-400/25 bg-emerald-400/[0.08]' : isCurrent ? 'border-violet-400/35 bg-violet-400/[0.1]' : 'border-white/10 bg-white/[0.02]'}`}>
                                    <div className={`flex items-center gap-1.5 text-[10px] font-semibold ${isComplete ? 'text-emerald-200' : isCurrent ? 'text-violet-200' : 'text-slate-500'}`}>
                                        <span className={`flex h-4 w-4 items-center justify-center rounded-full text-[9px] ${isComplete ? 'bg-emerald-400 text-slate-950' : isCurrent ? 'bg-violet-400 text-white' : 'bg-white/10 text-slate-500'}`}>{isComplete ? '✓' : index + 1}</span>
                                        <span className="truncate">{step.label}</span>
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    <div className="mt-6 h-2 overflow-hidden rounded-full bg-white/10">

                        <div

                            className="h-full bg-gradient-to-r from-violet-500 via-indigo-500 to-cyan-500 transition-all duration-700"

                            style={{

                                width: `${progress}%`

                            }}

                        />

                    </div>

                    <div className="mt-3 flex justify-between text-xs text-slate-400">

                        <span>Generating...</span>

                        <span>{progress}%</span>

                    </div>

                </div>

            </div>

        )
    }

</div>
    );
}

