import axios from "axios";
import { useState } from "react";
import { useRef } from "react";

import BlockPreviewCard from "./BlockPreviewCard";

import HeroHeadlinePreview from "./Previews/HeroHeadlinePreview";
import ServicesGridPreview from "./Previews/ServicesGridPreview";
import FeatureLeftPreview from "./Previews/FeatureLeftPreview";
import FeatureRightPreview from "./Previews/FeatureRightPreview";
import HeroCenteredPreview from "./Previews/HeroCenteredPreview";
import ServicesBentoPreview from "./Previews/ServicesBentoPreview";
import ProcessTimelinePreview from "./Previews/ProcessTimelinePreview";
import TestimonialsCarouselPreview from "./Previews/TestimonialsCarouselPreview";
import PricingCardsPreview from "./Previews/PricingCardsPreview";
import HeroBackgroundImagePreview from "./Previews/HeroBackgroundImagePreview";


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
            text: "Focus sa logic, biya-i ang manual coding. Ang imong website, automated na sa atong custom CMS logic.",
            btn1_label: "Get Started",
            btn1_url: "#",
            btn2_label: "View Docs",
            btn2_url: "#"
        }
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
            backgroundImage: "",
            overlayOpacity: 50,
            textAlign: "center",
            height: "screen"
        }
    },
];

export default function AddSectionModal({
    open,
    onClose,
    onAdd,
    onReplace
}) {

    const [prompt, setPrompt] = useState("");
    const [showConfirm, setShowConfirm] = useState(false);

    const aiResult = {};

    const [isGenerating, setIsGenerating] = useState(false);
    const [aiStage, setAiStage] = useState("Planning your page...");
    const [progress, setProgress] = useState(0);

    const generateWithAI = () => {

        if (!prompt.trim()) {
            alert("Please enter a prompt first.");
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
            alert("Please enter a prompt first.");
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

            const sections = sectionResponse.data.sections;

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
                    sections
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

                alert("AI did not return any blocks.");

            }

        } catch (error) {

            console.log(error);

            setIsGenerating(false);

            alert("Check browser console.");

        }

    };



    if (!open) return null;

    return (
        <div className="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center z-50 p-4">

            <div
            className="
                bg-slate-900
                border border-slate-700
                rounded-3xl
                w-full
                max-w-6xl
                shadow-2xl
                text-slate-100
                max-h-[92vh]
                overflow-y-auto

                [&::-webkit-scrollbar]:w-2
                [&::-webkit-scrollbar-track]:bg-transparent

                [&::-webkit-scrollbar-thumb]:rounded-full
                [&::-webkit-scrollbar-thumb]:bg-slate-700
                hover:[&::-webkit-scrollbar-thumb]:bg-violet-500/70

                [&::-webkit-scrollbar-corner]:bg-transparent
            "
        >

        {/* Header */}

        <div className="sticky top-0 bg-slate-900/95 backdrop-blur border-b border-slate-800 px-8 py-6 z-20">

            <div className="flex items-start justify-between">

                <div>

                    <h2 className="text-3xl font-black text-white flex items-center gap-3">

                        ✨ AI Page Generator

                    </h2>

                    <p className="text-slate-400 mt-2 max-w-2xl leading-7">

                        Describe the page you want to build and let Cosmic AI
                        generate a complete layout using your professional block
                        library.

                    </p>

                </div>

                <button
                    onClick={onClose}
                    className="text-slate-500 hover:text-white text-3xl transition"
                >
                    ✕

                </button>

            </div>

        </div>

        <div className="p-8">

            {/* Prompt */}

            <div className="space-y-5">

                <textarea

                    value={prompt}

                    onChange={(e) => setPrompt(e.target.value)}

                    rows={7}

                    placeholder={`Example:

                Create an About Us page for a Dental Clinic

                Modern SaaS landing page with pricing and testimonials

                Construction company homepage with hero, services and contact CTA.`}

                    className="w-full resize-none rounded-2xl bg-slate-950 border border-slate-700 p-5 text-white placeholder-slate-500 leading-7 focus:outline-none focus:border-violet-500 transition"

                    onKeyDown={(e) => {

                        if (e.key === "Enter" && !e.shiftKey) {

                            e.preventDefault();

                            generateWithAI();

                        }

                    }}

                />

                {/* Quick Prompts */}

                <div>

                    <p className="text-xs uppercase tracking-[0.3em] text-slate-500 mb-3">

                        Quick Ideas

                    </p>

                    <div className="flex flex-wrap gap-3">

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

                                className="px-4 py-2 rounded-full bg-slate-800 hover:bg-slate-700 border border-slate-700 text-sm transition"

                            >

                                {item}

                            </button>

                        ))}

                    </div>

                </div>

                {/* AI Options */}

                <div className="flex flex-wrap gap-6 text-sm text-slate-400">

                    <label className="flex items-center gap-2">

                        <input
                            type="checkbox"
                            checked
                            readOnly
                            className="accent-violet-500"
                        />

                        Generate Layout

                    </label>

                    <label className="flex items-center gap-2">

                        <input
                            type="checkbox"
                            checked
                            readOnly
                            className="accent-violet-500"
                        />

                        Generate Content

                    </label>

                    <label className="flex items-center gap-2">

                        <input
                            type="checkbox"
                            checked
                            readOnly
                            className="accent-violet-500"
                        />

                        Match Website Theme

                    </label>

                </div>

                {/* Generate */}

                <button

                    onClick={generateWithAI}

                    className="w-full py-4 rounded-2xl font-bold text-lg bg-gradient-to-r from-violet-600 via-indigo-600 to-blue-600 hover:opacity-95 transition shadow-xl"

                >

                    ✨ Generate Page

                </button>

            </div>

            {/* Divider */}

            <div className="flex items-center gap-6 my-12">

                <div className="flex-1 border-t border-slate-800" />

                <span className="text-xs uppercase tracking-[0.35em] text-slate-500">

                    Or Browse Professional Blocks

                </span>

                <div className="flex-1 border-t border-slate-800" />

            </div>

            {/* Registry */}

            <div>

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

            <div className="fixed inset-0 bg-black/70 flex items-center justify-center z-[999]">

                <div className="bg-slate-900 rounded-2xl border border-slate-700 p-8 w-full max-w-md">

                    <h3 className="text-2xl font-bold text-white mb-4">
                        Replace Current Page?
                    </h3>

                    <p className="text-slate-300 leading-7">
                        Generating a new AI page will replace all existing blocks.
                        <br /><br />
                        Your Header, Footer and Theme settings will remain unchanged.
                    </p>

                    <div className="flex justify-end gap-3 mt-8">

                        <button
                            onClick={() => setShowConfirm(false)}
                            className="px-5 py-3 rounded-xl bg-slate-700 hover:bg-slate-600"
                        >
                            Cancel
                        </button>

                        <button
                            onClick={() => {
                                setShowConfirm(false);
                                executeGenerate();
                            }}
                            className="px-5 py-3 rounded-xl bg-violet-600 hover:bg-violet-500 font-bold"
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

            <div className="fixed inset-0 z-[99999] bg-slate-950/90 backdrop-blur-md flex items-center justify-center">

                <div className="w-full max-w-lg px-10">

                    <div className="flex justify-center">

                        <div className="relative flex justify-center">

                            <div className="absolute w-44 h-44 rounded-full bg-violet-500/20 blur-3xl animate-pulse" />

                            <div className="w-28 h-28 rounded-full border-[5px] border-violet-500 border-t-cyan-400 border-r-indigo-400 animate-spin" />

                            <div className="absolute inset-0 flex items-center justify-center">

                                <svg
                                    className="w-10 h-10 text-cyan-300 animate-pulse"
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

                    <h2 className="mt-8 text-center text-4xl font-black text-white">

                        Cosmic AI

                    </h2>

                    <p className="mt-4 text-center text-slate-300">

                        {aiStage}

                    </p>

                    <div className="mt-10 h-3 rounded-full bg-slate-800 overflow-hidden">

                        <div

                            className="h-full bg-gradient-to-r from-violet-500 via-indigo-500 to-cyan-500 transition-all duration-700"

                            style={{

                                width: `${progress}%`

                            }}

                        />

                    </div>

                    <div className="mt-4 flex justify-between text-sm text-slate-400">

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

