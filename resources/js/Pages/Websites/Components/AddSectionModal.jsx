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
import TestimonialsCarouselPreview from "./Previews/TestimonialsCarouselPreview";
import PricingCardsPreview from "./Previews/PricingCardsPreview";
import HeroBackgroundImagePreview from "./Previews/HeroBackgroundImagePreview";
import HeroEditorialOverlayPreview from "./Previews/HeroEditorialOverlayPreview";
import HeroSplitImagePreview from "./Previews/HeroSplitImagePreview";
import ImageCtaBannerPreview from "./Previews/ImageCtaBannerPreview";
import HeroFloatingCardsPreview from "./Previews/HeroFloatingCardsPreview";
import HeroVideoStylePreview from "./Previews/HeroVideoStylePreview";
import HeroVideoBackgroundPreview from "./Previews/HeroVideoBackgroundPreview";


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
];

const generationProgressSteps = [
    { label: "Understand brief", threshold: 10 },
    { label: "Plan sections", threshold: 30 },
    { label: "Create content", threshold: 90 },
    { label: "Build page", threshold: 100 },
];

export default function AddSectionModal({
    open,
    onClose,
    onAdd,
    onReplace,
    hasBlocks = false,
}) {

    const [prompt, setPrompt] = useState("");
    const [showConfirm, setShowConfirm] = useState(false);
    const [isBlockLibraryOpen, setIsBlockLibraryOpen] = useState(false);

    const aiResult = {};

    const [isGenerating, setIsGenerating] = useState(false);
    const [aiStage, setAiStage] = useState("Planning your page...");
    const [progress, setProgress] = useState(0);

    const generateWithAI = () => {

        if (!prompt.trim()) {
            showCosmicNotification({ title: "Prompt required", message: "Describe the website or section you want to generate first.", tone: "info" });
            return;
        }

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

        if (!prompt.trim()) {
            showCosmicNotification({ title: "Prompt required", message: "Describe the website or section you want to generate first.", tone: "info" });
            return;
        }

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

            const sectionResponse = await axios.post(
                "/ai/select-sections",
                {
                    prompt
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
                    prompt,
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

                    placeholder="Describe the page you want, such as: a modern dental clinic About page with services, testimonials, and a booking CTA."

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

                    className="hidden"

                >

                    ✨ Generate Page

                </button>

            </div>

            <div className="mt-6 border-t border-white/10 pt-5">
                <button
                    type="button"
                    onClick={() => setIsBlockLibraryOpen(!isBlockLibraryOpen)}
                    aria-expanded={isBlockLibraryOpen}
                    className="flex w-full items-center justify-between rounded-xl border border-white/10 bg-white/[0.03] px-4 py-3 text-left transition hover:bg-white/[0.06] focus:outline-none focus:ring-2 focus:ring-violet-400"
                >
                    <span>
                        <span className="block text-sm font-semibold text-white">Browse Blocks</span>
                        <span className="mt-0.5 block text-xs text-slate-500">Add a professional section manually.</span>
                    </span>
                    <span className="text-lg text-slate-400" aria-hidden="true">{isBlockLibraryOpen ? '−' : '+'}</span>
                </button>
            </div>

            {isBlockLibraryOpen && (
                <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
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

        <div className="shrink-0 border-t border-white/10 bg-[#151519] px-4 py-3 sm:px-6">
            <button
                type="button"
                onClick={generateWithAI}
                disabled={isGenerating || !prompt.trim()}
                className="flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-violet-950/30 transition hover:from-violet-500 hover:to-indigo-500 focus:outline-none focus:ring-2 focus:ring-violet-300 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {isGenerating ? 'Preparing generation...' : 'Generate Page'}
            </button>
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

                        Building your page

                    </h2>

                    <p className="mt-3 text-center text-sm text-slate-300" aria-live="polite">

                        {aiStage || "Generating your page..."}

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

