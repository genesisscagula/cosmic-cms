import axios from "axios";
import { useEffect, useMemo, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { Link } from "@inertiajs/react";
import { showCosmicNotification } from "../../../Components/CosmicNotification";
import { useCreditBalance } from '@/Hooks/useCreditBalance';
import useInfiniteReveal from '../../../Hooks/useInfiniteReveal';
import BlockPreviewCard from "./BlockPreviewCard";
import { BlockRegistry } from "./SparkRegistry";
import { BlockRegistry as BuilderBlockRegistry } from "../BlockRegistry";
import { createSparkTailwindRuntime } from "../Blocks/Shared/sparkTailwindRuntime";
import { colorFamilies, installCustomBrandTheme } from "../../../theme/colorFamilies";
import { useAppearance } from "../../../Appearance/AppearanceContext";

const SECTION_TYPE_META = {
    "Hero": { tone: "violet", icon: "sparkles", description: "Headers, banners & hero layouts" },
    "About / Content": { tone: "cyan", icon: "document", description: "Story, content & split sections" },
    "Services": { tone: "blue", icon: "grid", description: "Service cards, grids & showcases" },
    "Features": { tone: "indigo", icon: "diamond", description: "Feature grids & product benefits" },
    "Testimonials": { tone: "fuchsia", icon: "quote", description: "Reviews, quotes & social proof" },
    "Pricing": { tone: "orange", icon: "tag", description: "Plans, packages & comparisons" },
    "Process": { tone: "emerald", icon: "route", description: "Steps, timelines & workflows" },
    "Stats": { tone: "lime", icon: "chart", description: "Metrics, counters & trust numbers" },
    "Team": { tone: "purple", icon: "users", description: "People, profiles & leadership" },
    "FAQ": { tone: "teal", icon: "question", description: "Questions & accordion content" },
    "Contact": { tone: "sky", icon: "mail", description: "Forms, locations & contact details" },
    "Gallery": { tone: "rose", icon: "image", description: "Image grids & visual showcases" },
    "CTA": { tone: "amber", icon: "star", description: "Calls to action & conversion bands" },
    "Case Studies": { tone: "amber", icon: "folder", description: "Projects, work & case studies" },
    "Posts / Updates": { tone: "rose", icon: "document", description: "Blog, resources & updates" },
    "Events": { tone: "cyan", icon: "calendar", description: "Events, schedules & listings" },
    "Careers": { tone: "lime", icon: "briefcase", description: "Jobs, roles & hiring content" },
    "Ecommerce": { tone: "orange", icon: "bag", description: "Products, collections & commerce" },
    "Other": { tone: "slate", icon: "grid", description: "More section layouts" },
};

function SectionTypeIcon({ icon = "sparkles" }) {
    const common = {
        viewBox: "0 0 24 24",
        fill: "none",
        stroke: "currentColor",
        strokeWidth: 1.9,
        strokeLinecap: "round",
        strokeLinejoin: "round",
        "aria-hidden": "true",
    };

    if (icon === "grid") return <svg {...common}><rect x="4" y="4" width="6" height="6" rx="1.5"/><rect x="14" y="4" width="6" height="6" rx="1.5"/><rect x="4" y="14" width="6" height="6" rx="1.5"/><rect x="14" y="14" width="6" height="6" rx="1.5"/></svg>;
    if (icon === "diamond") return <svg {...common}><path d="m12 3 8 9-8 9-8-9 8-9Z"/><path d="m12 8 3.5 4-3.5 4-3.5-4 3.5-4Z"/></svg>;
    if (icon === "shield") return <svg {...common}><path d="M12 3 19 6v5c0 4.6-2.8 8.1-7 10-4.2-1.9-7-5.4-7-10V6l7-3Z"/><path d="m9 12 2 2 4-4"/></svg>;
    if (icon === "quote") return <svg {...common}><path d="M9.5 11H5.8A3.8 3.8 0 0 0 2 14.8V18h7.5v-7Z"/><path d="M22 11h-3.7a3.8 3.8 0 0 0-3.8 3.8V18H22v-7Z"/><path d="M5.8 11c0-2.5 1-4.4 3-5.8"/><path d="M18.3 11c0-2.5 1-4.4 3-5.8"/></svg>;
    if (icon === "tag") return <svg {...common}><path d="M20 13 13 20l-9-9V4h7l9 9Z"/><circle cx="8.5" cy="8.5" r="1.25"/></svg>;
    if (icon === "users") return <svg {...common}><path d="M16 20v-1.7c0-2-1.8-3.8-4-3.8H7c-2.2 0-4 1.8-4 3.8V20"/><circle cx="9.5" cy="7.5" r="3.5"/><path d="M17 11a3 3 0 1 0-2.4-4.8"/><path d="M17.5 14.8c2 .3 3.5 1.8 3.5 3.5V20"/></svg>;
    if (icon === "question") return <svg {...common}><circle cx="12" cy="12" r="9"/><path d="M9.8 9a2.4 2.4 0 1 1 3.7 2c-1 .6-1.5 1-1.5 2"/><path d="M12 17h.01"/></svg>;
    if (icon === "mail") return <svg {...common}><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m4.5 7 7.5 6 7.5-6"/></svg>;
    if (icon === "folder") return <svg {...common}><path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H10l2 2h6.5A2.5 2.5 0 0 1 21 9.5v7A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5v-9Z"/></svg>;
    if (icon === "document") return <svg {...common}><path d="M6 3h8l4 4v14H6V3Z"/><path d="M14 3v5h4"/><path d="M9 12h6M9 16h6"/></svg>;
    if (icon === "calendar") return <svg {...common}><rect x="3" y="5" width="18" height="16" rx="2.5"/><path d="M8 3v4M16 3v4M3 10h18"/><path d="M8 14h2M14 14h2M8 17h2"/></svg>;
    if (icon === "briefcase") return <svg {...common}><rect x="3" y="7" width="18" height="13" rx="2.5"/><path d="M9 7V5h6v2M3 12h18M10 12v2h4v-2"/></svg>;
    if (icon === "star") return <svg {...common}><path d="m12 3 2.3 5.3L20 10.5l-5.7 2.2L12 18l-2.3-5.3L4 10.5l5.7-2.2L12 3Z"/></svg>;
    if (icon === "route") return <svg {...common}><circle cx="6" cy="6" r="2"/><circle cx="18" cy="18" r="2"/><path d="M8 6h4a3 3 0 0 1 3 3v6a3 3 0 0 0 3 3"/></svg>;
    if (icon === "chart") return <svg {...common}><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>;
    if (icon === "image") return <svg {...common}><rect x="3" y="4" width="18" height="16" rx="2.5"/><circle cx="8.5" cy="9" r="1.5"/><path d="m5 17 4.5-4.5 3.5 3 2.5-2.5L20 17"/></svg>;
    if (icon === "bag") return <svg {...common}><path d="M5 8h14l-1 12H6L5 8Z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/></svg>;
    return <svg {...common}><path d="m12 3 2 5 5 2-5 2-2 5-2-5-5-2 5-2 2-5Z"/><path d="m18.5 15 .9 2.1 2.1.9-2.1.9-.9 2.1-.9-2.1-2.1-.9 2.1-.9.9-2.1Z"/></svg>;
}

// Batch 3: Add Section is a production surface, not a raw catalog dump.
// Only expose Sparks that can be rendered by the same Builder registry used
// after Apply. A Spark may opt out explicitly while it is being repaired.
// Spark Audit Batch 3: every registered Builder Spark is eligible for Add Section
// even when it does not have a hand-authored marketplace preview entry yet.
// The Builder registry schema/defaults are the source of truth; curated previews
// still win when present. This removes the old 229/329 visibility ceiling without
// creating a second rendering contract.
const createBuilderRegistryFallback = (spark) => {
    const builderEntry = BuilderBlockRegistry[spark?.key];
    if (!spark?.key || !builderEntry?.component || !builderEntry?.schema || typeof builderEntry.schema !== "object") return null;

    return {
        type: spark.key,
        title: spark.name || spark.key,
        buttonLabel: `Add ${spark.name || "Section"}`,
        buttonClass: "bg-violet-600 hover:bg-violet-500",
        payload: structuredClone(builderEntry.schema.defaults || {}),
        builderReady: true,
        addSectionReady: true,
        source: "builder_registry_fallback",
    };
};

const addSectionSparkEligibility = (spark) => {
    if (!spark?.key || !spark?.registry) return { ready: false, reason: "missing_catalog_entry" };
    if (spark.registry.addSectionReady === false || spark.registry.builderReady === false || spark.add_section_ready === false || spark.builder_ready === false) {
        return { ready: false, reason: "explicitly_disabled" };
    }

    const builderEntry = BuilderBlockRegistry[spark.key];
    if (!builderEntry?.component) return { ready: false, reason: "missing_builder_component" };
    if (!builderEntry?.schema || typeof builderEntry.schema !== "object") return { ready: false, reason: "missing_builder_schema" };
    if (spark.registry.payload != null && (typeof spark.registry.payload !== "object" || Array.isArray(spark.registry.payload))) {
        return { ready: false, reason: "invalid_preview_payload" };
    }

    return { ready: true, reason: "builder_parity_ready" };
};

const categoryFor = (type, rawCategory = "") => {
    const key = String(type || "").toLowerCase();
    const raw = String(rawCategory || "").toLowerCase();
    const haystack = `${key} ${raw}`;
    if (key.startsWith("mini_hero_") || key.startsWith("hero_") || key === "image_cta_banner" || raw.includes("hero")) return "Hero";
    if (key.startsWith("services_") || haystack.includes("service")) return "Services";
    if (key.startsWith("feature_") || haystack.includes("feature")) return "Features";
    if (haystack.includes("testimonial") || haystack.includes("review")) return "Testimonials";
    if (haystack.includes("pricing") || haystack.includes("plan")) return "Pricing";
    if (haystack.includes("process") || haystack.includes("timeline") || haystack.includes("steps")) return "Process";
    if (haystack.includes("stats") || haystack.includes("metric") || haystack.includes("counter")) return "Stats";
    if (haystack.includes("team") || haystack.includes("people")) return "Team";
    if (haystack.includes("faq") || haystack.includes("question")) return "FAQ";
    if (haystack.includes("contact") || haystack.includes("location") || haystack.includes("map")) return "Contact";
    if (haystack.includes("gallery") || haystack.includes("portfolio") || haystack.includes("masonry")) return "Gallery";
    if (haystack.includes("case_stud") || haystack.includes("case stud") || haystack.includes("project")) return "Case Studies";
    if (haystack.includes("product") || haystack.includes("commerce") || haystack.includes("shop") || haystack.includes("collection")) return "Ecommerce";
    if (haystack.includes("job") || haystack.includes("career")) return "Careers";
    if (haystack.includes("event")) return "Events";
    if (haystack.includes("blog") || haystack.includes("newsletter") || haystack.includes("resource") || key.startsWith("content_")) return "Posts / Updates";
    if (haystack.includes("cta") || haystack.includes("call to action")) return "CTA";
    if (haystack.includes("about") || haystack.includes("content") || haystack.includes("story") || haystack.includes("split")) return "About / Content";
    return "Other";
};

const marketplaceCategoryForSectionType = (sectionType = "") => ({
    "About / Content": "About",
    "Stats": "Proof",
    "Gallery": "Portfolio",
    "Ecommerce": "Commerce",
}[sectionType] || sectionType);


function SparkVisual({ spark, previewVariant = "primary", websiteTheme = "midnight", payloadOverride = null }) {
    const Preview = spark.registry.preview;
    return <div className="cosmic-preview-isolation cosmic-spark-layout-host w-full" data-cosmic-preview-isolation="true" data-cosmic-site-preview="true"><Preview {...spark.registry.payload} {...(payloadOverride || {})} previewVariant={previewVariant} websiteTheme={websiteTheme} /></div>;
}


function previewPalette(websiteTheme, previewVariant) {
    const normalized = typeof websiteTheme === "string"
        ? { primary: websiteTheme }
        : (websiteTheme || {});

    if (normalized.primary === "my-brand" && normalized.custom_brand_theme) {
        installCustomBrandTheme(normalized.custom_brand_theme);
    }

    const primaryKey = normalized.primary || "midnight";
    const primaryFamily = colorFamilies[primaryKey] || colorFamilies.midnight;
    const whiteFamily = colorFamilies.white;
    const surfaceFamily = colorFamilies.stone;

    const family = previewVariant === "white"
        ? whiteFamily
        : previewVariant === "surface"
            ? surfaceFamily
            : primaryFamily;

    const palette = family?.palette || {};
    return {
        family,
        background: palette.background || "#243447",
        surface: palette.surface || palette.background || "#30475E",
        text: palette.text || "#F8FAFC",
        accent: palette.accent || "#60A5FA",
    };
}

function hexLuminance(hex) {
    const normalized = String(hex || "").replace("#", "");
    if (!/^[0-9a-fA-F]{6}$/.test(normalized)) return 0;

    const channels = [0, 2, 4].map((offset) => {
        const value = parseInt(normalized.slice(offset, offset + 2), 16) / 255;
        return value <= 0.03928 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4);
    });

    return (0.2126 * channels[0]) + (0.7152 * channels[1]) + (0.0722 * channels[2]);
}

function safePreviewText(background, preferredText) {
    const backgroundLum = hexLuminance(background);
    const preferredLum = hexLuminance(preferredText);
    const contrast = (Math.max(backgroundLum, preferredLum) + 0.05) / (Math.min(backgroundLum, preferredLum) + 0.05);

    if (contrast >= 4.5) return preferredText;
    return backgroundLum > 0.45 ? "#0F172A" : "#F8FAFC";
}

export function ActualSparkPreview({ spark, previewVariant = "white", websiteTheme, payloadOverride = null, blockIndex = 0, commerce = null, contentWorkspace = { types: [] } }) {
    const registryItem = BuilderBlockRegistry[spark.key];
    const Component = registryItem?.component;

    if (!Component) {
        return (
            <div
                className="cosmic-preview-isolation cosmic-spark-preview-content cosmic-spark-layout-host w-full"
                data-cosmic-preview-isolation="true"
                data-cosmic-site-preview="true"
                data-cosmic-add-spark-preview="true"
                data-cosmic-spark-type={spark.key}
            >
                <SparkVisual spark={spark} previewVariant={previewVariant} websiteTheme={websiteTheme} payloadOverride={payloadOverride} />
            </div>
        );
    }

    const defaults = registryItem.schema?.defaults || {};
    const block = {
        ...defaults,
        ...structuredClone(spark.registry.payload || {}),
        ...structuredClone(payloadOverride || {}),
        type: spark.key,
        theme: previewVariant,
        resolvedTheme: previewVariant,
    };

    // Preview parity rule: render the registry block exactly like Builder does.
    // The shell must never recolor, resize, or otherwise "repair" the section.
    // `previewVariant` only supplies the same resolvedTheme value Builder passes
    // after resolveBlockTheme(); the block component owns all visual decisions.
    const normalizedTheme = typeof websiteTheme === "string"
        ? { primary: websiteTheme, secondary: "white", tertiary: "surface", auto: true }
        : {
            ...(websiteTheme || {}),
            primary: websiteTheme?.primary || "midnight",
            secondary: websiteTheme?.secondary || "white",
            tertiary: websiteTheme?.tertiary || "surface",
            auto: websiteTheme?.auto ?? true,
        };
    const palette = previewPalette(normalizedTheme, previewVariant);

    return (
        <div
            className="cosmic-preview-isolation cosmic-spark-preview-content cosmic-spark-layout-host w-full"
            data-cosmic-preview-isolation="true"
            data-cosmic-site-preview="true"
            data-cosmic-add-spark-preview="true"
            data-cosmic-spark-type={spark.key}
            data-preview-variant={previewVariant}
            data-preview-family={previewVariant === "primary" ? normalizedTheme.primary : previewVariant}
            style={{
                "--cosmic-preview-bg": palette.background,
                "--cosmic-preview-surface": palette.surface,
                "--cosmic-preview-text": palette.text,
                "--cosmic-preview-accent": palette.accent,
            }}
        >
            <Component
                block={block}
                blockIndex={blockIndex}
                globalTheme={normalizedTheme}
                tailwind={createSparkTailwindRuntime(block)}
                onUpdate={() => {}}
                blogPosts={[]}
                blogWebsiteId={null}
                blogPageId={null}
                onBlogPostCreated={() => {}}
                onBlogPostUpdated={() => {}}
                onBlogPostDeleted={() => {}}
                commerce={commerce}
                contentWorkspace={contentWorkspace}
                builderMode={false}
            />
        </div>
    );
}


export function UnifiedSparkPreviewEngine({
    spark,
    previewVariant = "white",
    websiteTheme,
    payloadOverride = null,
    blockIndex = 0,
    commerce = null,
    contentWorkspace = { types: [] },
    interactive = false,
}) {
    if (!spark) return null;

    return (
        <div className="cosmic-unified-preview-engine w-full" data-cosmic-unified-preview="true">
            <div className="cosmic-unified-preview-frame mx-auto w-full">
                <div className={interactive ? "w-full min-w-0" : "pointer-events-none w-full min-w-0"}>
                    <ActualSparkPreview
                        spark={spark}
                        previewVariant={previewVariant}
                        websiteTheme={websiteTheme}
                        payloadOverride={payloadOverride}
                        blockIndex={blockIndex}
                        commerce={commerce}
                        contentWorkspace={contentWorkspace}
                    />
                </div>
            </div>
        </div>
    );
}


export function GlobalSparkPreviewModal({
    spark,
    previewVariant = "white",
    websiteTheme,
    commerce = null,
    contentWorkspace = { types: [] },
    eyebrow = null,
    title = null,
    description = null,
    onClose,
    footerLeft = null,
    footerRight = null,
}) {
    useEffect(() => {
        if (!spark || typeof document === "undefined") return undefined;

        const body = document.body;
        const html = document.documentElement;
        const previousBodyOverflow = body.style.overflow;
        const previousBodyPaddingRight = body.style.paddingRight;
        const previousHtmlOverscroll = html.style.overscrollBehavior;
        const scrollbarWidth = Math.max(0, window.innerWidth - html.clientWidth);

        body.style.overflow = "hidden";
        if (scrollbarWidth > 0) body.style.paddingRight = `${scrollbarWidth}px`;
        html.style.overscrollBehavior = "none";

        const handleKeyDown = (event) => {
            if (event.key === "Escape") onClose?.();
        };
        window.addEventListener("keydown", handleKeyDown);

        return () => {
            window.removeEventListener("keydown", handleKeyDown);
            body.style.overflow = previousBodyOverflow;
            body.style.paddingRight = previousBodyPaddingRight;
            html.style.overscrollBehavior = previousHtmlOverscroll;
        };
    }, [spark, onClose]);

    if (!spark || typeof document === "undefined") return null;

    return createPortal(
        <div id="cosmic-global-spark-preview" className="fixed inset-0 z-[2147483000] flex items-stretch justify-stretch">
            <button
                type="button"
                onClick={onClose}
                className="absolute inset-0 bg-black/85 backdrop-blur-sm"
                aria-label="Close Spark preview"
            />
            <section
                role="dialog"
                aria-modal="true"
                className="cosmic-spark-preview-modal relative z-10 flex h-[100dvh] w-screen max-w-none flex-col overflow-hidden rounded-none border-0 bg-[#101014] text-white shadow-2xl"
            >
                <header className="shrink-0 flex items-start justify-between gap-4 border-b border-white/10 px-5 py-4 sm:px-7">
                    <div className="min-w-0">
                        <p className="text-[10px] font-bold uppercase tracking-[0.2em] text-violet-300">
                            {eyebrow || `Spark Preview · ${spark.category || "Spark"}`}
                        </p>
                        <h3 className="mt-1 truncate text-xl font-semibold">{title || spark.name}</h3>
                        {(description || spark.description) && (
                            <p className="mt-1 max-w-3xl text-sm text-slate-400">{description || spark.description}</p>
                        )}
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="shrink-0 rounded-xl border border-white/10 px-3 py-2 text-slate-400 hover:bg-white/5 hover:text-white"
                        aria-label="Close preview"
                    >
                        ✕
                    </button>
                </header>

                <div className="cosmic-global-spark-preview-body min-h-0 flex-1 overflow-auto overscroll-contain bg-[#e5e7eb]">
                    <UnifiedSparkPreviewEngine
                        spark={spark}
                        previewVariant={previewVariant}
                        websiteTheme={websiteTheme}
                        commerce={commerce}
                        contentWorkspace={contentWorkspace}
                    />
                </div>

                {(footerLeft || footerRight) && (
                    <footer className="shrink-0 flex flex-col gap-3 border-t border-white/10 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                        <div className="min-w-0">{footerLeft}</div>
                        <div className="shrink-0">{footerRight}</div>
                    </footer>
                )}
            </section>
        </div>,
        document.body
    );
}

export default function AddSectionModal({
    open,
    onClose,
    onAdd,
    onCustomize = null,
    onReplace = null,
    websiteContext = "",
    websiteId = null,
    headerOverlayEnabled = false,
    trialMode = false,
    trialToken = null,
    ownedOnly = false,
    contextLabel = null,
    insertionContext = null,
    onOwnershipChanged = null,
    websiteTheme = null,
    commerce = null,
    contentWorkspace = { types: [] },
    preloadedCatalog = [],
    preloadedCatalogLoading = false,
    preloadedCatalogLoaded = false,
    preparedVisibleCount = 0,
    overlayClassName = "z-[900]",
}) {
    const { setBalance } = useCreditBalance();
    const { resolvedTheme: appAppearanceTheme } = useAppearance();
    const appDark = appAppearanceTheme === "dark";
    const [tab, setTab] = useState("marketplace");
    const [query, setQuery] = useState("");
    const [category, setCategory] = useState("All");
    const [pickerStage, setPickerStage] = useState("categories");
    const [aiResults, setAiResults] = useState(null);
    const [aiPrompt, setAiPrompt] = useState("");
    const [aiSearchBusy, setAiSearchBusy] = useState(false);
    const [catalog, setCatalog] = useState(() => Array.isArray(preloadedCatalog) ? preloadedCatalog : []);
    const [loading, setLoading] = useState(Boolean(preloadedCatalogLoading));
    const [busyKey, setBusyKey] = useState(null);
    const trialSignupUrl = trialToken ? `/register?trial=${encodeURIComponent(trialToken)}` : "/register";
    const [selected, setSelected] = useState(null);
    const [mode, setMode] = useState("quick");
    const [instruction, setInstruction] = useState("");
    const [previewSpark, setPreviewSpark] = useState(null);
    const [pickerSparkKey, setPickerSparkKey] = useState(null);
    const [previewVariantIndex, setPreviewVariantIndex] = useState(0);
    const [personalizingSpark, setPersonalizingSpark] = useState(false);
    const [personalizeProgress, setPersonalizeProgress] = useState(0);
    const [personalizeStage, setPersonalizeStage] = useState("Understanding your Spark...");
    const marketplaceScrollRef = useRef(null);
    const [popupActive, setPopupActive] = useState(false);


    const previewVariants = ["primary", "white", "surface"];
    const previewVariant = previewVariants[previewVariantIndex];

    // Every Add/Insert session starts clean. This prevents the previous category,
    // Spark, search, or preview variant from leaking into a later insertion flow.
    useEffect(() => {
        if (!open) return;
        setTab('marketplace');
        setQuery('');
        setCategory('All');
        setPickerStage('categories');
        setAiResults(null);
        setAiPrompt('');
        setSelected(null);
        setMode('quick');
        setInstruction('');
        setPreviewSpark(null);
        setPickerSparkKey(null);
        setPreviewVariantIndex(0);
        setBusyKey(null);
        setPopupActive(false);
        marketplaceScrollRef.current?.scrollTo?.({ top: 0, behavior: 'auto' });
    }, [open, contextLabel]);

    useEffect(() => {
        if (previewSpark) setPreviewVariantIndex(0);
    }, [previewSpark]);


    useEffect(() => {
        if (!personalizingSpark) {
            setPersonalizeProgress(0);
            setPersonalizeStage("Understanding your Spark...");
            return undefined;
        }

        const stages = [
            { at: 8, text: "Understanding your Spark..." },
            { at: 30, text: "Personalizing the content..." },
            { at: 58, text: "Applying your website style..." },
            { at: 82, text: "Finishing your section..." },
        ];

        let progress = 4;
        setPersonalizeProgress(progress);
        const timer = window.setInterval(() => {
            const increment = progress < 35 ? 3 : progress < 70 ? 2 : 1;
            progress = Math.min(91, progress + increment);
            setPersonalizeProgress(progress);
            const current = [...stages].reverse().find((stage) => progress >= stage.at);
            if (current) setPersonalizeStage(current.text);
        }, 180);

        return () => window.clearInterval(timer);
    }, [personalizingSpark]);

    useEffect(() => {
        if (Array.isArray(preloadedCatalog) && preloadedCatalog.length) {
            setCatalog(preloadedCatalog);
        }
        setLoading(Boolean(preloadedCatalogLoading) && !(preloadedCatalog?.length > 0));
    }, [preloadedCatalog, preloadedCatalogLoading]);

    useEffect(() => {
        if (!open) return;

        // One global Spark picker serves Add, Insert Above, and Insert Below.
        // Placement is handled by Builder; the library itself always stays complete.
        setTab("marketplace");
        setCategory("All");
        setPickerStage("categories");
        setPreviewSpark(null);
        setPickerSparkKey(null);
        setSelected(null);
        setMode("quick");
        setInstruction("");
        setAiResults(null);
        setAiPrompt("");
        setAiSearchBusy(false);

        window.requestAnimationFrame(() => {
            marketplaceScrollRef.current?.scrollTo({ top: 0, behavior: "auto" });
        });

        // Builder normally preloads the catalog on landing. This is only a
        // resilience fallback for a failed/aborted preload, never a per-open fetch.
        if (preloadedCatalogLoaded || preloadedCatalogLoading || catalog.length) return;

        let cancelled = false;
        const endpoint = trialMode && trialToken
            ? `/trial-assets/${trialToken}/sparks`
            : "/sparks/catalog";

        setLoading(true);
        axios.get(endpoint)
            .then(({ data }) => {
                if (!cancelled) setCatalog(data.sparks || []);
            })
            .catch(() => {
                if (!cancelled) {
                    showCosmicNotification({ title: "Could not load Sparks", message: "Please refresh and try again.", tone: "error" });
                }
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => {
            cancelled = true;
        };
    }, [open, trialMode, trialToken, preloadedCatalogLoaded, preloadedCatalogLoading, catalog.length]);

    const registry = useMemo(() => new Map(BlockRegistry.map((item) => [item.type, item])), []);
    const catalogItems = useMemo(() => catalog
        .map((spark) => ({
            ...spark,
            registry: registry.get(spark.key) || createBuilderRegistryFallback(spark),
        }))
        .filter((spark) => spark.registry), [catalog, registry]);
    const sparkReadiness = useMemo(() => new Map(catalogItems.map((spark) => [spark.key, addSectionSparkEligibility(spark)])), [catalogItems]);
    const items = useMemo(() => catalogItems.filter((spark) => sparkReadiness.get(spark.key)?.ready), [catalogItems, sparkReadiness]);
    // Builder Add/Insert flows intentionally expose only the user's installed/shared
    // Spark library. The full catalog lives in the dedicated Marketplace.
    const displayItems = useMemo(() => ownedOnly ? items.filter((item) => item.owned) : items, [items, ownedOnly]);
    const hiddenUnsafeSparkCount = catalogItems.length - items.length;
    const categories = useMemo(() => ["All", ...new Set(items.map((item) => categoryFor(item.key, item.category)))], [items]);
    const categoryCards = useMemo(() => {
        const countsByCategory = new Map();
        items.forEach((item) => {
            const name = categoryFor(item.key, item.category);
            if (!name || name === "All") return;
            countsByCategory.set(name, (countsByCategory.get(name) || 0) + 1);
        });
        const preferred = ["Hero", "About / Content", "Services", "Features", "Testimonials", "Pricing", "Process", "Stats", "Team", "FAQ", "Contact", "Gallery", "CTA", "Case Studies", "Posts / Updates", "Events", "Careers", "Ecommerce", "Other"];
        const ordered = [
            ...preferred.filter((name) => countsByCategory.has(name)),
            ...Array.from(countsByCategory.keys()).filter((name) => !preferred.includes(name)).sort((a, b) => a.localeCompare(b)),
        ];
        return ordered.map((name) => ({ name, count: countsByCategory.get(name) || 0 }));
    }, [items]);
    const counts = useMemo(() => ({
        owned: items.filter((item) => item.owned).length,
        favorites: items.filter((item) => item.favorited).length,
        marketplace: items.length,
    }), [items]);

    const aiResultMap = useMemo(() => new Map((aiResults || []).map((result, index) => [result.id, { ...result, rank: index + 1 }])), [aiResults]);

    const filteredItems = useMemo(() => displayItems.filter((item) => {
        if (tab === "owned" && !item.owned) return false;
        if (tab === "favorites" && !item.favorited) return false;
        if (category !== "All" && categoryFor(item.key, item.category) !== category) return false;
        if (aiResults && !aiResultMap.has(item.key)) return false;
        if (aiResults) return true;
        const haystack = `${item.name} ${item.description} ${item.category} ${item.collection || ""}`.toLowerCase();
        return haystack.includes(query.trim().toLowerCase());
    }).sort((a, b) => aiResults ? ((aiResultMap.get(a.key)?.rank || 999) - (aiResultMap.get(b.key)?.rank || 999)) : 0), [displayItems, tab, category, query, aiResults, aiResultMap]);

    const categorySparkItems = useMemo(() => {
        if (category === "All") {
            if (!aiResults) return [];
            return displayItems
                .filter((item) => aiResultMap.has(item.key))
                .sort((a, b) => (aiResultMap.get(a.key)?.rank || 999) - (aiResultMap.get(b.key)?.rank || 999));
        }
        return displayItems.filter((item) => (categoryFor(item.key, item.category)) === category);
    }, [displayItems, category, aiResults, aiResultMap]);

    const pickerSpark = useMemo(() => {
        if (!pickerSparkKey) return categorySparkItems[0] || null;
        return displayItems.find((item) => item.key === pickerSparkKey) || categorySparkItems[0] || null;
    }, [displayItems, categorySparkItems, pickerSparkKey]);

    const blockForSpark = (spark) => {
        if (!spark?.registry) return null;
        const schemaDefaults = BuilderBlockRegistry[spark.key]?.schema?.defaults || {};
        return {
            ...structuredClone(schemaDefaults),
            ...structuredClone(spark.registry.payload || {}),
            type: spark.key,
        };
    };

    const blankLunaFlexBlock = () => ({
        type: 'luna_custom_section',
        custom_spark_key: `luna-blank-${Date.now()}`,
        custom_spark_saved: false,
        semantic_type: 'custom',
        source_type: 'blank_luna',
        category: 'content',
        layout: 'editorial',
        alignment: 'left',
        media_position: 'none',
        density: 'balanced',
        accent_shape: 'none',
        section_mood: 'auto',
        eyebrow: '',
        heading: 'New Luna Section',
        heading_accent_text: '',
        text: '',
        primary_label: '',
        primary_url: '#',
        secondary_label: '',
        secondary_url: '#',
        image_url: '',
        items: [],
        elements: [],
        theme: 'auto',
        ai_flex: {
            source: 'blank',
            mode: 'ai_flex',
            skip_spark_match: true,
            generated: false,
        },
    });

    const startBlankWithLuna = () => {
        if (!onCustomize) return;
        const block = blankLunaFlexBlock();
        onCustomize(block, {
            spark: { name: 'Start Blank with Luna', key: block.custom_spark_key, category: 'Luna' },
            insertionContext,
            category: 'Luna',
            creationSource: 'blank_luna',
            aiFlexMode: true,
            skipSparkMatch: true,
        });
        onClose();
    };

    const chooseCategory = (name) => {
        // Category cards always remain visible so users can discover section types,
        // but the next screen is their owned/shared collection only.
        const matches = displayItems.filter((item) => (categoryFor(item.key, item.category)) === name);
        const initial = matches.find((item) => !item.trial_locked) || matches[0] || null;
        setCategory(name);
        setPickerSparkKey(initial?.key || null);
        setPreviewVariantIndex(0);
        setPickerStage("sparks");
        window.requestAnimationFrame(() => marketplaceScrollRef.current?.scrollTo({ top: 0, behavior: "auto" }));
    };

    const browseMarketplace = (sectionType = category) => {
        if (trialMode) return;
        const marketplaceCategory = marketplaceCategoryForSectionType(sectionType);
        const baseUrl = route('sparks.index');
        const separator = baseUrl.includes('?') ? '&' : '?';
        const marketplaceUrl = marketplaceCategory && marketplaceCategory !== 'All'
            ? `${baseUrl}${separator}category=${encodeURIComponent(marketplaceCategory)}`
            : baseUrl;
        const marketplaceWindow = window.open(marketplaceUrl, '_blank');
        if (marketplaceWindow) {
            try { marketplaceWindow.opener = null; } catch (_) {}
            return;
        }
        showCosmicNotification({
            title: 'Marketplace blocked by your browser',
            message: 'Allow popups for Cosmic CMS, then choose Browse Sparks again.',
            tone: 'warning',
        });
    };

    useEffect(() => {
        if (typeof window === 'undefined') return undefined;
        const handleOwnershipSync = (event) => {
            if (event.key !== 'cosmic:spark-ownership-changed' || !event.newValue) return;
            try {
                const payload = JSON.parse(event.newValue);
                const sparkKey = String(payload?.key || '');
                if (!sparkKey || payload?.owned === false) return;
                setCatalog((current) => current.map((item) => item.key === sparkKey ? { ...item, owned: true } : item));
                onOwnershipChanged?.(sparkKey);
            } catch (_) {}
        };
        window.addEventListener('storage', handleOwnershipSync);
        return () => window.removeEventListener('storage', handleOwnershipSync);
    }, [onOwnershipChanged]);

    const sparkRevealKey = `${tab}|${category}|${query.trim().toLowerCase()}|${aiResults ? 'ai' : 'browse'}|${filteredItems.length}`;
    const {
        visibleItems: visible,
        sentinelRef: sparkSentinelRef,
        hasMore: hasMoreSparks,
        isRevealing: isRevealingSparks,
    } = useInfiniteReveal(filteredItems, {
        batchSize: 50,
        resetKey: sparkRevealKey,
        root: marketplaceScrollRef,
        rootMargin: '420px 0px',
        disabled: !open || !popupActive || loading,
    });

    const clearAiSearch = () => {
        setAiResults(null);
        setAiPrompt("");
    };

    const runAiSearch = async () => {
        const prompt = query.trim();
        if (trialMode || prompt.length < 2 || aiSearchBusy) return;
        setAiSearchBusy(true);
        try {
            const { data } = await axios.post('/ai/library-search', { type: 'sparks', prompt, limit: 10 });
            setAiResults(Array.isArray(data.results) ? data.results : []);
            setAiPrompt(prompt);
            setCategory('All');
            setTab('marketplace');
            setPickerStage('sparks');
            window.requestAnimationFrame(() => marketplaceScrollRef.current?.scrollTo({ top: 0, behavior: 'auto' }));
        } catch (error) {
            showCosmicNotification({ title: 'Luna search unavailable', message: error.response?.data?.message || 'Normal Spark search is still available.', tone: 'error' });
        } finally {
            setAiSearchBusy(false);
        }
    };

    if (!open) return null;

    const unlock = async (spark) => {
        setBusyKey(spark.key);
        try {
            const { data } = await axios.post(trialMode && trialToken ? `/trial-assets/${trialToken}/sparks/${spark.key}/unlock` : `/sparks/${spark.key}/unlock`);
            setCatalog((current) => current.map((item) => item.key === spark.key ? { ...item, owned: true } : item));
            onOwnershipChanged?.(spark.key);
            setBalance(data.credit_balance);
            showCosmicNotification({ title: "Added to My Sparks", message: data.message, tone: "success" });
        } catch (error) {
            showCosmicNotification({ title: "Could not unlock Spark", message: error.response?.data?.message || "Please try again.", tone: "error" });
        } finally {
            setBusyKey(null);
        }
    };

    const toggleFavorite = async (spark) => {
        setBusyKey(`favorite-${spark.key}`);
        try {
            const { data } = await axios.post(trialMode && trialToken ? `/trial-assets/${trialToken}/sparks/${spark.key}/favorite` : `/sparks/${spark.key}/favorite`);
            setCatalog((current) => current.map((item) => item.key === spark.key ? { ...item, favorited: data.favorited } : item));
            setPreviewSpark((current) => current?.key === spark.key ? { ...current, favorited: data.favorited } : current);
            showCosmicNotification({ title: data.favorited ? "Added to Favorites" : "Removed from Favorites", message: data.message, tone: "success" });
        } catch (error) {
            showCosmicNotification({ title: "Could not update Favorite", message: error.response?.data?.message || "Please try again.", tone: "error" });
        } finally {
            setBusyKey(null);
        }
    };

    const addPickerSparkQuick = (spark) => {
        if (!spark || !spark.owned || spark.trial_locked) return;
        const block = blockForSpark(spark);
        if (!block) return;
        if (onCustomize) {
            onCustomize(block, { spark, insertionContext, creationSource: 'registered_spark' });
            onClose();
            return;
        }
        onAdd(block);
        showCosmicNotification({ title: isContextualInsert ? 'Section inserted' : 'Section added', message: isContextualInsert && insertionAnchorLabel ? `${spark.name} was inserted ${insertionPosition === 'above' ? 'above' : 'below'} ${insertionAnchorLabel}.` : `${spark.name} was added to this page.`, tone: 'success' });
        onClose();
    };

    const addSpark = async () => {
        if (!selected) return;
        setBusyKey(selected.key);
        try {
            if (mode === "quick") {
                // Persist the same defaults Builder uses for preview/rendering. This prevents
                // untouched repeater Sparks from looking complete in Builder but exporting
                // with missing arrays until the user edits them. Registry payload remains the
                // final override so intentionally customized marketplace copy still wins.
                const schemaDefaults = BuilderBlockRegistry[selected.key]?.schema?.defaults || {};
                const block = {
                    ...structuredClone(schemaDefaults),
                    ...structuredClone(selected.registry.payload || {}),
                    type: selected.key,
                };
                if (onCustomize) onCustomize(block, { spark: selected, insertionContext });
                else onAdd(block);
            } else {
                if (trialMode) throw new Error("AI Spark personalization is available after sign up. Quick Add remains available in trial.");
                setPersonalizingSpark(true);
                const prompt = [
                    websiteContext || "Create professional website content.",
                    instruction.trim() || `Personalize this ${selected.name} section for the business.`,
                ].join("\n\n");
                const { data } = await axios.post("/ai/generate-content", {
                    prompt,
                    sections: [selected.key],
                    generation_type: "section",
                    website_id: websiteId,
                    header_overlay_enabled: Boolean(headerOverlayEnabled),
                });
                const block = data.blocks?.[0];
                if (!block) throw new Error("Cosmic AI did not return a section.");
                setPersonalizeStage("Your Spark is ready.");
                setPersonalizeProgress(100);
                await new Promise((resolve) => window.setTimeout(resolve, 400));
                setBalance(data.credit_balance);
                if (onCustomize) onCustomize(block, { spark: selected, insertionContext });
                else onAdd(block);
            }
            if (!onCustomize) showCosmicNotification({ title: isContextualInsert ? 'Section inserted' : 'Spark added', message: isContextualInsert && insertionAnchorLabel ? `${selected.name} was inserted ${insertionPosition === 'above' ? 'above' : 'below'} ${insertionAnchorLabel}.` : `${selected.name} was added to this page.`, tone: 'success' });
            setSelected(null);
            setInstruction("");
            setMode("quick");
            onClose();
        } catch (error) {
            showCosmicNotification({ title: "Could not add Spark", message: error.response?.data?.message || error.message || "Please try again.", tone: "error" });
        } finally {
            setPersonalizingSpark(false);
            setBusyKey(null);
        }
    };

    const modalContextLabel = String(contextLabel || "Add Section");
    const isContextualInsert = /insert section/i.test(modalContextLabel);
    const insertionPosition = String(insertionContext?.position || '').toLowerCase();
    const insertionAnchorLabel = String(insertionContext?.anchorLabel || '').trim();
    const insertionHint = isContextualInsert && insertionAnchorLabel
        ? `This new section will be inserted ${insertionPosition === 'above' ? 'above' : 'below'} ${insertionAnchorLabel}.`
        : '';

    return <div className={`fixed inset-0 ${overlayClassName} flex items-center justify-center p-4 sm:p-6`}>
        <button type="button" onClick={onClose} style={{ backgroundColor: 'rgba(2, 6, 23, 0.66)', backdropFilter: 'blur(8px)' }} className="absolute inset-0 bg-black/75 backdrop-blur-sm" aria-label="Close Add Spark" />
        <section
            id="cosmic-add-section-modal"
            role="dialog"
            aria-modal="true"
            data-cosmic-app-modal="add-section"
            data-appearance={appDark ? 'dark' : 'light'}
            onPointerEnter={() => setPopupActive(true)}
            onPointerLeave={() => setPopupActive(false)}
            className={`cosmic-add-spark-modal cosmic-native-text-layer cosmic-add-section-premium-shell relative z-10 flex max-h-[92vh] w-full max-w-[1560px] flex-col overflow-hidden rounded-[26px] border shadow-2xl ${appDark ? 'border-white/10 bg-[#111116] text-white shadow-black/70' : 'border-slate-200 bg-white text-slate-950 shadow-slate-950/20'} ${popupActive ? 'is-active' : ''}`}
        >
            <button type="button" onClick={onClose} className={`absolute right-4 top-4 z-30 grid h-10 w-10 place-items-center rounded-xl border text-sm shadow-sm transition ${appDark ? 'border-white/10 bg-[#18181d] text-slate-300 hover:bg-white/10 hover:text-white' : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50 hover:text-slate-900'}`} aria-label="Close Add Section">✕</button>
            {pickerStage === "categories" ? (
                <div className={`cosmic-add-section-library grid min-h-[640px] flex-1 grid-cols-1 overflow-hidden lg:grid-cols-[minmax(0,1fr)_340px] ${appDark ? 'bg-[#111116] text-white' : 'bg-white text-slate-950'}`}>
                    <div className="cosmic-add-section-main min-w-0 overflow-y-auto px-6 py-6 sm:px-8 sm:py-8 lg:px-9 lg:py-9">
                        <div className="cosmic-add-section-heading flex items-start justify-between gap-4 pr-12">
                            <div className="min-w-0">
                                <p className="cosmic-add-section-eyebrow text-[10px] font-bold uppercase tracking-[0.22em] text-violet-600">{modalContextLabel}</p>
                                <h2 className="mt-2 text-[clamp(1.65rem,2vw,2.15rem)] font-semibold tracking-[-0.035em]">Choose a section type</h2>
                                <p className={`cosmic-add-section-intro mt-2 max-w-3xl text-sm leading-6 ${appDark ? 'text-slate-400' : 'text-slate-600'}`}>Pick a section type to use Sparks from your collection. Need another layout? Browse the Marketplace and add more Sparks anytime.</p>
                                {insertionHint ? <p className={`cosmic-add-section-insertion-hint mt-2 text-xs font-semibold ${appDark ? 'text-violet-300' : 'text-violet-700'}`}>{insertionHint}</p> : null}
                            </div>
                            <div className="cosmic-add-section-heading-art" aria-hidden="true"><span /><span /><span>✦</span></div>
                        </div>

                        {loading && catalog.length === 0 ? (
                            <div className={`mt-8 grid min-h-[420px] place-items-center rounded-2xl border border-dashed ${appDark ? 'border-white/10 bg-white/[0.02]' : 'border-slate-200 bg-white/70'}`}>
                                <div className="text-center"><div className="mx-auto h-10 w-10 animate-spin rounded-full border-4 border-violet-200 border-t-violet-600" /><p className="mt-4 text-sm font-semibold">Preparing section types…</p></div>
                            </div>
                        ) : (
                            <div className="cosmic-section-type-grid mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                <button
                                    type="button"
                                    onClick={startBlankWithLuna}
                                    className="group cosmic-section-type-card cosmic-section-type-card--luna min-h-40 text-left"
                                    data-card-kind="luna-blank"
                                    data-tone="violet"
                                >
                                    <div className="cosmic-section-type-icon"><SectionTypeIcon icon="sparkles" /></div>
                                    <div className="cosmic-section-type-card__footer">
                                        <div className="min-w-0">
                                            <div className="cosmic-section-type-title">Start Blank with Luna</div>
                                            <div className="cosmic-section-type-meta cosmic-section-type-meta--accent">Describe it — Luna picks the closest Spark</div>
                                        </div>
                                        <span className="cosmic-section-type-arrow" aria-hidden="true">→</span>
                                    </div>
                                </button>
                                {categoryCards.map((item) => {
                                    const meta = SECTION_TYPE_META[item.name] || SECTION_TYPE_META.Other;
                                    return (
                                        <button
                                            key={item.name}
                                            type="button"
                                            onClick={() => chooseCategory(item.name)}
                                            className="group cosmic-section-type-card min-h-40 text-left"
                                            data-card-kind="category"
                                            data-tone={meta.tone}
                                        >
                                            <div className="cosmic-section-type-icon"><SectionTypeIcon icon={meta.icon} /></div>
                                            <div className="cosmic-section-type-card__footer">
                                                <div className="min-w-0">
                                                    <div className="cosmic-section-type-title">{item.name}</div>
                                                    <div className="cosmic-section-type-meta">{meta.description}</div>
                                                </div>
                                                <span className="cosmic-section-type-arrow" aria-hidden="true">→</span>
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        )}
                    </div>

                    <aside className="cosmic-add-section-luna-panel cosmic-add-section-luna-premium flex min-h-0 flex-col border-t lg:border-l lg:border-t-0" data-appearance={appDark ? 'dark' : 'light'}>
                        <div className="cosmic-add-section-luna-premium__header">
                            <div className="min-w-0">
                                <p className="cosmic-add-section-luna-premium__eyebrow">✦ Luna · {isContextualInsert ? modalContextLabel : 'Add Section'}</p>
                                <h3 className="font-semibold cosmic-add-section-luna-premium__title">Find the right section</h3>
                            </div>
                            <div className="cosmic-add-section-luna-premium__active"><span aria-hidden="true"/>AI Active</div>
                        </div>
                        <div className="cosmic-add-section-luna-premium__context">
                            <span aria-hidden="true"/>
                            Context · {isContextualInsert ? 'Insert Section' : 'Section Library'}
                        </div>
                        <div className="cosmic-add-section-luna-premium__chat">
                            <div className="cosmic-add-section-luna-premium__message-row is-assistant">
                                <div className="cosmic-add-section-luna-premium__bubble is-assistant">
                                    Describe the section you need. I’ll rank the closest Sparks already in your collection, then you can customize the selected layout before it is added.
                                </div>
                            </div>
                            {aiSearchBusy ? (
                                <div className="cosmic-add-section-luna-premium__message-row is-assistant">
                                    <div className={`cosmic-luna-process-card ${appDark ? 'cosmic-luna-process-card--dark' : ''}`} role="status" aria-live="polite">
                                        <p className="cosmic-luna-process-card__intro">Luna is finding the best section match.</p>
                                        <div className="cosmic-luna-process-card__steps">
                                            <div className="cosmic-luna-process-card__step cosmic-luna-process-card__step--complete"><span className="cosmic-luna-process-card__marker" aria-hidden="true">✓</span><span>Understanding the section request</span></div>
                                            <div className="cosmic-luna-process-card__step cosmic-luna-process-card__step--active"><span className="cosmic-luna-process-card__marker" aria-hidden="true"/><span>Ranking matching Sparks</span></div>
                                            <div className="cosmic-luna-process-card__step cosmic-luna-process-card__step--pending"><span className="cosmic-luna-process-card__marker" aria-hidden="true">•</span><span>Checking Builder compatibility</span></div>
                                            <div className="cosmic-luna-process-card__step cosmic-luna-process-card__step--pending"><span className="cosmic-luna-process-card__marker" aria-hidden="true">•</span><span>Preparing the results</span></div>
                                        </div>
                                        <p className="cosmic-luna-process-card__status">Searching the Spark library…</p>
                                    </div>
                                </div>
                            ) : null}
                        </div>
                        <div className="cosmic-add-section-luna-premium__composer">
                            <textarea
                                value={query}
                                onChange={(event) => { setQuery(event.target.value); if (aiResults) clearAiSearch(); }}
                                rows={5}
                                placeholder="e.g. Add a premium services section with four cards"
                                className="cosmic-add-section-luna-premium__textarea"
                            />
                            <div className="cosmic-add-section-luna-premium__composer-footer">
                                <span className="cosmic-add-section-luna-premium__builder-label"><span aria-hidden="true">✦</span>Section Finder</span>
                                <button type="button" disabled={trialMode || aiSearchBusy || query.trim().length < 2} onClick={runAiSearch} className="cosmic-add-section-luna-premium__send">{aiSearchBusy ? 'Working…' : 'Ask Luna'}</button>
                            </div>
                            {trialMode ? <p className="cosmic-add-section-luna-premium__note">Luna section search is available after sign up. You can still use the curated Sparks included with your trial.</p> : null}
                        </div>
                        <div className="cosmic-add-section-luna-premium__whole-page">
                            <b>Building several sections?</b>
                            <span>Use the whole-page Luna chat when you want Luna to plan the page as one composition.</span>
                        </div>
                    </aside>
                </div>
            ) : (
                <>
                    <header className={`shrink-0 border-b px-5 py-4 pr-16 sm:px-7 sm:pr-20 ${appDark ? 'border-white/10 bg-[#111116]' : 'border-slate-200 bg-white'}`}>
                        <div className="flex items-start justify-between gap-4">
                            <div className="min-w-0">
                                <button
                                    type="button"
                                    onClick={() => { setPickerStage('categories'); setCategory('All'); setPickerSparkKey(null); clearAiSearch(); }}
                                    aria-label="Back to all section types"
                                    className={`cosmic-add-section-all-types mb-3 inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-xs font-bold shadow-sm transition ${appDark ? 'border-white/10 bg-white/[0.04] text-slate-200 hover:border-violet-400/40 hover:bg-white/[0.08] hover:text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-violet-300 hover:bg-violet-50 hover:text-violet-800'}`}
                                >
                                    <span aria-hidden="true">←</span>
                                    <span>All section types</span>
                                </button>
                                <p className="text-[10px] font-bold uppercase tracking-[0.2em] text-violet-500">{modalContextLabel} · {category}</p>
                                <h2 className="mt-1 truncate text-xl font-semibold">{pickerSpark?.name || `My ${category} Sparks`}</h2>
                                <p className={`mt-1 text-xs ${appDark ? 'text-slate-400' : 'text-slate-500'}`}>{categorySparkItems.length ? `${categorySparkItems.length} Spark${categorySparkItems.length === 1 ? '' : 's'} in your collection` : 'No Sparks from this section type are in your collection yet.'}</p>
                            </div>
                            {trialMode ? (
                                <Link href={trialSignupUrl} className={`mt-12 shrink-0 rounded-xl border px-4 py-2 text-xs font-bold transition ${appDark ? 'border-violet-300/20 bg-violet-300/10 text-violet-100 hover:bg-violet-300/15' : 'border-violet-200 bg-violet-50 text-violet-800 hover:bg-violet-100'}`}>Sign up for more Sparks</Link>
                            ) : (
                                <button type="button" onClick={() => browseMarketplace(category)} className={`mt-12 shrink-0 rounded-xl border px-4 py-2 text-xs font-bold transition ${appDark ? 'border-violet-300/20 bg-violet-300/10 text-violet-100 hover:bg-violet-300/15' : 'border-violet-200 bg-violet-50 text-violet-800 hover:bg-violet-100'}`}>Browse More Sparks ↗</button>
                            )}
                        </div>
                        <div className="mt-4 flex max-w-full gap-2 overflow-x-auto pb-1">
                            {categorySparkItems.map((spark) => {
                                const active = pickerSpark?.key === spark.key;
                                return <button key={spark.key} type="button" onClick={() => { setPickerSparkKey(spark.key); setPreviewVariantIndex(0); }} aria-pressed={active} data-active={active ? 'true' : 'false'} className={`cosmic-add-section-spark-tab shrink-0 rounded-lg border px-3 py-2 text-xs font-semibold transition ${active ? 'border-violet-600 bg-violet-100 text-violet-900 ring-1 ring-violet-200' : appDark ? 'border-white/10 bg-white/[0.03] text-slate-300 hover:border-violet-400/50 hover:text-white' : 'border-slate-300 bg-white text-slate-700 hover:border-violet-400 hover:bg-violet-50'}`}>{spark.name}{!spark.owned && !spark.trial_locked ? <span className="ml-1.5 text-[10px] text-amber-500">⚡{Number(spark.credits || 0)}</span> : null}</button>;
                            })}
                        </div>
                    </header>

                    <div className={`cosmic-add-section-stage grid min-h-0 flex-1 overflow-hidden lg:grid-cols-[minmax(0,1fr)_300px] ${appDark ? 'bg-[#111116]' : 'bg-white'}`}>
                        <div ref={marketplaceScrollRef} className="min-h-0 overflow-y-auto p-4 sm:p-5">
                            {pickerSpark ? (
                                <div className={`overflow-hidden rounded-2xl border ${appDark ? 'border-white/10 bg-black/20' : 'border-slate-200 bg-slate-50'}`}>
                                    <UnifiedSparkPreviewEngine spark={pickerSpark} previewVariant={previewVariant} websiteTheme={websiteTheme} commerce={commerce} contentWorkspace={contentWorkspace} interactive={true} />
                                </div>
                            ) : <div className="grid min-h-[420px] place-items-center px-6 text-center">
                                <div className="max-w-md">
                                    <div className={`mx-auto grid h-12 w-12 place-items-center rounded-2xl border text-lg ${appDark ? 'border-white/10 bg-white/[0.04] text-violet-200' : 'border-violet-100 bg-violet-50 text-violet-700'}`}>✦</div>
                                    <h3 className="mt-4 text-base font-semibold">No {category} Sparks in your collection yet</h3>
                                    <p className={`mt-2 text-sm leading-6 ${appDark ? 'text-slate-400' : 'text-slate-600'}`}>Add a Spark from the Marketplace, then it will appear here automatically and stay reusable across your pages.</p>
                                    {trialMode ? (
                                        <Link href={trialSignupUrl} className="mt-5 inline-flex rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-violet-500">Sign up to browse Sparks</Link>
                                    ) : (
                                        <button type="button" onClick={() => browseMarketplace(category)} className="mt-5 rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-violet-500">Browse {category} Sparks ↗</button>
                                    )}
                                </div>
                            </div>}
                        </div>
                        <aside className={`flex min-h-0 flex-col border-t p-4 lg:border-l lg:border-t-0 ${appDark ? 'border-white/10 bg-[#0d0d12]' : 'border-slate-200 bg-slate-50'}`}>
                            <p className="text-[10px] font-bold uppercase tracking-[0.18em] text-violet-500">✦ Luna · {category}</p>
                            <h3 className="mt-2 text-sm font-semibold">{pickerSpark?.name || category}</h3>
                            <p className={`mt-1 text-xs leading-5 ${appDark ? 'text-slate-400' : 'text-slate-600'}`}>Compare the Sparks you already own. Nothing is added until you confirm below.</p>
                            {insertionHint ? <p className={`mt-2 rounded-lg border px-2.5 py-2 text-[11px] font-semibold leading-4 ${appDark ? 'border-violet-400/20 bg-violet-400/[0.06] text-violet-200' : 'border-violet-200 bg-violet-50 text-violet-800'}`}>{insertionHint}</p> : null}
                            {pickerSpark?.description ? <p className={`mt-4 rounded-xl border p-3 text-xs leading-5 ${appDark ? 'border-white/10 bg-white/[0.03] text-slate-400' : 'border-slate-200 bg-white text-slate-600'}`}>{pickerSpark.description}</p> : null}
                        </aside>
                    </div>

                    <footer className={`flex shrink-0 flex-wrap items-center justify-between gap-3 border-t px-5 py-3 sm:px-7 ${appDark ? 'border-white/10 bg-[#111116]' : 'border-slate-200 bg-white'}`}>
                        <div className="flex items-center gap-2">
                            {previewVariants.map((variant, index) => <button key={variant} type="button" onClick={() => setPreviewVariantIndex(index)} aria-pressed={previewVariantIndex === index} className={`rounded-lg px-3 py-1.5 text-xs font-semibold capitalize ${previewVariantIndex === index ? 'bg-violet-600 text-white' : appDark ? 'text-slate-400 hover:bg-white/5 hover:text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'}`}>{variant}</button>)}
                        </div>
                        <div className="flex items-center gap-2">
                            <button type="button" onClick={onClose} className={`rounded-xl border px-4 py-2 text-sm font-semibold ${appDark ? 'border-white/10 text-slate-300' : 'border-slate-300 text-slate-700'}`}>Cancel</button>
                            {pickerSpark?.trial_locked ? <Link href={trialSignupUrl} className="rounded-xl bg-violet-600 px-5 py-2 text-sm font-bold text-white">Sign up to unlock</Link> : pickerSpark?.owned ? <button type="button" onClick={() => addPickerSparkQuick(pickerSpark)} className="rounded-xl bg-slate-950 px-5 py-2 text-sm font-bold text-white hover:bg-slate-800">Customize Section</button> : (!ownedOnly && pickerSpark) ? <button type="button" disabled={busyKey === pickerSpark.key || pickerSpark.can_install === false} onClick={() => unlock(pickerSpark)} className="rounded-xl bg-violet-600 px-5 py-2 text-sm font-bold text-white hover:bg-violet-500 disabled:opacity-40">{busyKey === pickerSpark.key ? 'Adding…' : Number(pickerSpark.credits || 0) === 0 ? 'Add Free Spark' : `Unlock · ⚡ ${pickerSpark.credits}`}</button> : null}
                        </div>
                    </footer>
                </>
            )}
        </section>

        {previewSpark && (
            <GlobalSparkPreviewModal
                spark={previewSpark}
                previewVariant={previewVariant}
                websiteTheme={websiteTheme}
                commerce={commerce}
                contentWorkspace={contentWorkspace}
                onClose={() => setPreviewSpark(null)}
                footerLeft={
                    <div className="flex flex-wrap items-center gap-3 text-xs text-slate-400">
                        <span className="rounded-full bg-violet-400/10 px-2.5 py-1 font-bold text-violet-200">AI Ready</span>
                        <span>Responsive Spark preview</span>
                        <div className="flex items-center gap-1.5">
                            {previewVariants.map((variant, index) => (
                                <button
                                    key={variant}
                                    type="button"
                                    aria-pressed={previewVariantIndex === index}
                                    data-active={previewVariantIndex === index ? "true" : "false"}
                                    onClick={() => setPreviewVariantIndex(index)}
                                    className="cosmic-spark-variant-toggle rounded-full px-2.5 py-1 font-semibold capitalize transition"
                                >
                                    {variant}
                                </button>
                            ))}
                        </div>
                    </div>
                }
                footerRight={
                    <div className="flex gap-2">
                        {!trialMode && (
                            <button
                                type="button"
                                disabled={busyKey === `favorite-${previewSpark.key}`}
                                onClick={() => toggleFavorite(previewSpark)}
                                className={`rounded-xl border px-4 py-2.5 text-sm font-semibold ${previewSpark.favorited ? "border-rose-300/30 bg-rose-400/10 text-rose-200" : "border-white/10 text-slate-300"}`}
                            >
                                {previewSpark.favorited ? "♥ Favorite" : "♡ Favorite"}
                            </button>
                        )}
                        <button type="button" onClick={() => setPreviewSpark(null)} className="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-slate-300">Close</button>
                        {previewSpark.trial_locked ? (
                            <Link href={trialSignupUrl} className="rounded-xl bg-violet-400 px-5 py-2.5 text-sm font-bold text-slate-950">Sign up to unlock</Link>
                        ) : previewSpark.owned ? (
                            <button type="button" onClick={() => { setPreviewSpark(null); addPickerSparkQuick(previewSpark); }} className="rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-slate-950">Customize Section</button>
                        ) : (
                            <button type="button" disabled={busyKey === previewSpark.key || previewSpark.can_install === false} onClick={() => unlock(previewSpark)} className="rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-bold text-white disabled:opacity-50">
                                {busyKey === previewSpark.key ? "Buying..." : previewSpark.slot_blocked ? "Owned Spark slots full" : previewSpark.can_install === false ? "Upgrade to add" : Number(previewSpark.credits || 0) === 0 ? "Add Free Spark" : `Buy Spark · ⚡ ${previewSpark.credits}`}
                            </button>
                        )}
                    </div>
                }
            />
        )}

        {personalizingSpark && <div className="cosmic-spark-progress-overlay cosmic-luna-action-overlay fixed inset-0 z-[990] grid place-items-center px-4" role="status" aria-live="polite">
            <section className="cosmic-luna-action-modal w-full max-w-md">
                <div className="cosmic-luna-action-modal__header">
                    <div>
                        <p className="cosmic-luna-action-modal__eyebrow">✦ Luna · Section</p>
                        <h3 className="font-semibold cosmic-luna-action-modal__title">Personalizing your Spark</h3>
                    </div>
                    <span className="cosmic-luna-action-modal__percent">{personalizeProgress}%</span>
                </div>
                <div className="cosmic-luna-process-card cosmic-luna-process-card--dark !w-full !max-w-none">
                    <p className="cosmic-luna-process-card__intro">I’m tailoring this section to your website.</p>
                    <div className="cosmic-luna-process-card__steps">
                        {[
                            { label: "Understanding the Spark", threshold: 18 },
                            { label: "Personalizing the content", threshold: 46 },
                            { label: "Applying your website style", threshold: 74 },
                            { label: "Verifying the section", threshold: 96 },
                        ].map((step, index, steps) => {
                            const isComplete = personalizeProgress >= step.threshold;
                            const previousThreshold = index === 0 ? 0 : steps[index - 1].threshold;
                            const isCurrent = !isComplete && personalizeProgress >= previousThreshold;
                            const state = isComplete ? 'complete' : isCurrent ? 'active' : 'pending';
                            return <div key={step.label} className={`cosmic-luna-process-card__step cosmic-luna-process-card__step--${state}`}>
                                <span className="cosmic-luna-process-card__marker" aria-hidden="true">{isComplete ? '✓' : state === 'pending' ? '•' : ''}</span>
                                <span>{step.label}</span>
                            </div>;
                        })}
                    </div>
                    <p className="cosmic-luna-process-card__status">{personalizeStage}</p>
                </div>
                <div className="cosmic-luna-action-modal__progress"><span style={{ width: `${personalizeProgress}%` }} /></div>
            </section>
        </div>}

        {selected && <div className="cosmic-add-owned-spark-overlay fixed inset-0 z-[950] flex items-center justify-center p-4">
            <button onClick={() => setSelected(null)} className="absolute inset-0 bg-black/80" aria-label="Close Add Spark dialog" />
            <section className="cosmic-add-owned-spark-modal relative z-10 w-full max-w-lg rounded-2xl border border-violet-400/20 bg-[#18181b] p-6 shadow-2xl">
                <p className="text-[10px] font-bold uppercase tracking-[0.2em] text-violet-300">Add owned Spark</p>
                <h3 className="mt-1 text-xl font-semibold text-white">{selected.name}</h3>
                <p className="mt-2 text-sm leading-6 text-slate-400">Choose instant generic content for free, or let Cosmic AI personalize this section for 20 Credits.</p>

                <div className="mt-5 grid gap-3 sm:grid-cols-2">
                    <button type="button" onClick={() => setMode("quick")} aria-pressed={mode === "quick"} className={`cosmic-spark-mode-card relative rounded-xl border p-4 text-left text-white transition ${mode === "quick" ? "is-selected is-quick border-emerald-400 bg-emerald-400/10 shadow-[0_0_0_1px_rgba(52,211,153,0.12)]" : "border-white/10 bg-white/[0.02] hover:border-white/20"}`}>
                        <span className="cosmic-spark-mode-check" aria-hidden="true">✓</span>
                        <span className="cosmic-spark-mode-selected-label">Selected</span>
                        <span className="block text-sm font-bold">Quick Content</span>
                        <span className="mt-1 block text-xs text-emerald-300">FREE · instant</span>
                    </button>
                    <button type="button" onClick={() => setMode("ai")} aria-pressed={mode === "ai"} className={`cosmic-spark-mode-card relative rounded-xl border p-4 text-left text-white transition ${mode === "ai" ? "is-selected is-ai border-violet-400 bg-violet-400/10 shadow-[0_0_0_1px_rgba(167,139,250,0.12)]" : "border-white/10 bg-white/[0.02] hover:border-white/20"}`}>
                        <span className="cosmic-spark-mode-check" aria-hidden="true">✓</span>
                        <span className="cosmic-spark-mode-selected-label">Selected</span>
                        <span className="block text-sm font-bold">AI Personalize</span>
                        <span className="mt-1 block text-xs text-violet-300">⚡ 20 Credits</span>
                    </button>
                </div>

                <div className="mt-5 border-t border-white/10 pt-5">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <p className="text-xs font-bold uppercase tracking-[0.16em] text-violet-300">✨ AI Instructions <span className="text-slate-500">(Optional)</span></p>
                            <p className="mt-1 text-xs text-slate-500">Used only when AI Personalize is selected.</p>
                        </div>
                        <span className="text-[10px] font-semibold text-slate-600">{instruction.length}/500</span>
                    </div>
                    <textarea value={instruction} onChange={(event) => setInstruction(event.target.value.slice(0, 500))} rows={4} disabled={mode !== "ai"} placeholder="Describe how you want Cosmic AI to personalize this Spark..." className="mt-3 w-full resize-none rounded-xl border border-white/10 bg-black/25 px-4 py-3 text-sm text-white placeholder:text-slate-600 focus:border-violet-400 focus:outline-none disabled:cursor-not-allowed disabled:opacity-40" />
                </div>

                <div className="cosmic-add-owned-spark-actions mt-5 flex justify-end gap-2">
                    <button type="button" onClick={() => setSelected(null)} className="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-300">Cancel</button>
                    <button type="button" disabled={busyKey === selected.key} onClick={addSpark} className="rounded-xl bg-white px-5 py-2 text-sm font-bold text-slate-950 disabled:opacity-50">{busyKey === selected.key ? "Preparing..." : mode === "ai" ? "Personalize & Customize · ⚡20" : "Customize Section"}</button>
                </div>
            </section>
        </div>}
    </div>;
}
