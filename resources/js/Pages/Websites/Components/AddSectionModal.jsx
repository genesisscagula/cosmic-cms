import axios from "axios";
import { useEffect, useMemo, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { Link } from "@inertiajs/react";
import { showCosmicNotification } from "../../../Components/CosmicNotification";
import { useCreditBalance } from '@/Hooks/useCreditBalance';
import BlockPreviewCard from "./BlockPreviewCard";
import { BlockRegistry } from "./SparkRegistry";
import { BlockRegistry as BuilderBlockRegistry } from "../BlockRegistry";
import { colorFamilies, installCustomBrandTheme } from "../../../theme/colorFamilies";

const categoryFor = (type) => {
    if (type.startsWith("mini_hero_")) return "Mini Heroes";
    if (type.startsWith("hero_") || type === "image_cta_banner") return "Hero";
    if (type.startsWith("services_")) return "Services";
    if (type.startsWith("feature_")) return "Features";
    if (type.includes("pricing")) return "Pricing";
    if (type.includes("testimonial")) return "Testimonials";
    if (type.includes("team")) return "Team";
    if (type.includes("faq")) return "FAQ";
    if (type.includes("contact") || type.includes("location")) return "Contact";
    if (type.includes("case_stud")) return "Case Studies";
    if (type.includes("job")) return "Careers";
    if (type.startsWith("content_")) return "Posts / Updates";
    if (type.includes("event")) return "Events";
    if (type.includes("blog") || type.includes("newsletter") || type.includes("resource")) return "Posts / Updates";
    if (type.includes("stats") || type.includes("process")) return "Proof";
    return "Other";
};

function SparkVisual({ spark, previewVariant = "primary", websiteTheme = "midnight", payloadOverride = null }) {
    const Preview = spark.registry.preview;
    return <div className="cosmic-preview-isolation w-full" data-cosmic-site-preview="true"><Preview {...spark.registry.payload} {...(payloadOverride || {})} previewVariant={previewVariant} websiteTheme={websiteTheme} /></div>;
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
                className="cosmic-preview-isolation cosmic-spark-preview-content w-full"
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
            className="cosmic-preview-isolation cosmic-spark-preview-content w-full"
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
    onReplace = null,
    websiteContext = "",
    websiteId = null,
    headerOverlayEnabled = false,
    trialMode = false,
    trialToken = null,
    ownedOnly = false,
    contextLabel = null,
    onOwnershipChanged = null,
    websiteTheme = null,
    commerce = null,
    contentWorkspace = { types: [] },
    preloadedCatalog = [],
    preloadedCatalogLoading = false,
    preloadedCatalogLoaded = false,
    preparedVisibleCount = 0,
}) {
    const { setBalance } = useCreditBalance();
    const [tab, setTab] = useState("marketplace");
    const [query, setQuery] = useState("");
    const [category, setCategory] = useState("All");
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
    const [previewVariantIndex, setPreviewVariantIndex] = useState(0);
    const [personalizingSpark, setPersonalizingSpark] = useState(false);
    const [personalizeProgress, setPersonalizeProgress] = useState(0);
    const [personalizeStage, setPersonalizeStage] = useState("Understanding your Spark...");
    const marketplaceScrollRef = useRef(null);


    const previewVariants = ["primary", "white", "surface"];
    const previewVariant = previewVariants[previewVariantIndex];

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
        setPreviewSpark(null);
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
    const items = useMemo(() => catalog.map((spark) => ({ ...spark, registry: registry.get(spark.key) })).filter((spark) => spark.registry), [catalog, registry]);
    const categories = useMemo(() => ["All", ...new Set(items.map((item) => item.category || categoryFor(item.key)))], [items]);
    const counts = useMemo(() => ({
        owned: items.filter((item) => item.owned).length,
        favorites: items.filter((item) => item.favorited).length,
        marketplace: items.length,
    }), [items]);

    const aiResultMap = useMemo(() => new Map((aiResults || []).map((result, index) => [result.id, { ...result, rank: index + 1 }])), [aiResults]);

    const filteredItems = useMemo(() => items.filter((item) => {
        if (tab === "owned" && !item.owned) return false;
        if (tab === "favorites" && !item.favorited) return false;
        if (category !== "All" && item.category !== category) return false;
        if (aiResults && !aiResultMap.has(item.key)) return false;
        if (aiResults) return true;
        const haystack = `${item.name} ${item.description} ${item.category} ${item.collection || ""}`.toLowerCase();
        return haystack.includes(query.trim().toLowerCase());
    }).sort((a, b) => aiResults ? ((aiResultMap.get(a.key)?.rank || 999) - (aiResultMap.get(b.key)?.rank || 999)) : 0), [items, tab, category, query, aiResults, aiResultMap]);

    const effectivePreparedCount = Math.max(0, Number(preparedVisibleCount || 0));
    const visible = useMemo(
        () => filteredItems.slice(0, Math.max(Math.min(effectivePreparedCount, filteredItems.length), filteredItems.length > 0 && !loading ? Math.min(50, filteredItems.length) : 0)),
        [filteredItems, effectivePreparedCount, loading]
    );

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
                onAdd({
                    ...structuredClone(schemaDefaults),
                    ...structuredClone(selected.registry.payload || {}),
                    type: selected.key,
                });
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
                onAdd(block);
            }
            showCosmicNotification({ title: "Spark added", message: `${selected.name} was added to this page.`, tone: "success" });
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

    return <div className="fixed inset-0 z-[900] flex items-center justify-center p-4 sm:p-6">
        <button type="button" onClick={onClose} className="absolute inset-0 bg-black/75 backdrop-blur-sm" aria-label="Close Add Spark" />
        <section role="dialog" aria-modal="true" className="cosmic-add-spark-modal relative z-10 flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-white/10 bg-[#111116] text-white shadow-2xl shadow-black/70">
            <header className="cosmic-add-spark-header border-b border-white/10 px-5 py-5 sm:px-7">
                <div className="flex items-start justify-between gap-4">
                    <div><p className="text-[10px] font-bold uppercase tracking-[0.22em] text-violet-300">Cosmic Builder</p><h2 className="mt-1 text-2xl font-semibold">✨ {contextLabel || 'Add Spark'}</h2><p className="mt-1 text-sm text-slate-400">{trialMode ? '10 Sparks are ready to use in your trial. Keep scrolling to preview the full library; sign up to unlock the rest.' : contextLabel?.startsWith('Insert Spark') ? 'Choose any Spark from the full library. Owned Sparks can be added now; marketplace Sparks can be purchased here.' : 'Reuse your owned layouts, or discover a new one in the Marketplace.'}</p></div>
                    <button onClick={onClose} className="rounded-xl border border-white/10 px-3 py-2 text-slate-400 hover:bg-white/5 hover:text-white">✕</button>
                </div>
                <div className="mt-5 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex max-w-full gap-1 overflow-x-auto rounded-xl border border-white/10 bg-black/20 p-1">
                        {trialMode ? <span className="whitespace-nowrap rounded-lg bg-white px-3 py-2 text-sm font-semibold text-slate-950">Trial Sparks ({counts.marketplace})</span> : <>
                            <button onClick={() => setTab("marketplace")} className={`whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold ${tab === "marketplace" ? "bg-white text-slate-950" : "text-slate-400 hover:text-white"}`}>All Sparks ({counts.marketplace})</button>
                            <button onClick={() => setTab("owned")} className={`whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold ${tab === "owned" ? "bg-white text-slate-950" : "text-slate-400 hover:text-white"}`}>Owned ({counts.owned})</button>
                            <button onClick={() => setTab("favorites")} className={`whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold ${tab === "favorites" ? "bg-white text-slate-950" : "text-slate-400 hover:text-white"}`}>Favorites ({counts.favorites})</button>
                        </>}
                    </div>
                    <div className="flex w-full items-center gap-2 lg:w-auto">
                        <input value={query} onChange={(event) => { setQuery(event.target.value); if (aiResults) clearAiSearch(); }} onKeyDown={(event) => { if (event.key === 'Enter' && !trialMode) { event.preventDefault(); runAiSearch(); } }} placeholder="Search, or describe the Spark you need..." className="h-11 min-w-0 flex-1 rounded-xl border border-white/10 bg-white/[0.04] px-4 text-sm placeholder:text-slate-600 focus:border-violet-400 focus:outline-none lg:w-80" />
                        {!trialMode && <button type="button" disabled={aiSearchBusy || query.trim().length < 2} onClick={runAiSearch} className="inline-flex h-11 shrink-0 items-center gap-1.5 rounded-xl bg-violet-400 px-3 text-xs font-bold text-slate-950 disabled:cursor-not-allowed disabled:opacity-50" title="Let Luna rank the best matching Sparks"><span aria-hidden="true">✦</span>{aiSearchBusy ? 'Searching…' : 'Ask Luna'}</button>}
                    </div>
                </div>
                {trialMode && <div className="mt-4 flex flex-col gap-2 rounded-2xl border border-violet-400/20 bg-violet-400/[0.07] px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between"><div><b className="text-violet-200">Trial access: 10 Sparks available</b><p className="mt-0.5 text-xs text-slate-400">Preview everything. Create a free account to unlock the full Spark library.</p></div><Link href={trialSignupUrl} className="shrink-0 rounded-xl bg-violet-400 px-4 py-2 text-center text-xs font-bold text-slate-950">Sign up to unlock</Link></div>}
                {aiResults && <div className="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-400"><span><b className="text-violet-200">✦ Luna results</b> for “{aiPrompt}” · {visible.length} match{visible.length === 1 ? '' : 'es'}</span><button type="button" onClick={clearAiSearch} className="rounded-full border border-white/10 px-2.5 py-1 text-[11px] font-semibold text-slate-300 hover:text-white">Clear AI results</button></div>}
                <div className="mt-4 flex gap-2 overflow-x-auto pb-1">{categories.map((item) => <button key={item} onClick={() => setCategory(item)} className={`whitespace-nowrap rounded-full px-3 py-1.5 text-xs font-semibold ${category === item ? "bg-violet-400 text-slate-950" : "border border-white/10 text-slate-400 hover:text-white"}`}>{item}</button>)}</div>
            </header>

            <div ref={marketplaceScrollRef} className="min-h-0 flex-1 overflow-y-auto overscroll-contain p-5 sm:p-7">
                {loading && catalog.length === 0 ? <div className="flex min-h-[420px] flex-col items-center justify-center rounded-2xl border border-dashed border-white/10 bg-white/[0.02] px-6 text-center"><div className="h-10 w-10 animate-spin rounded-full border-4 border-white/10 border-t-violet-400" /><h3 className="mt-4 text-base font-semibold text-white">Loading Sparks…</h3><p className="mt-1 text-sm text-slate-400">Preparing Sparks in the background.</p><div className="mt-6 grid w-full max-w-3xl gap-3 sm:grid-cols-3">{Array.from({ length: 6 }).map((_, index) => <div key={index} className="h-28 animate-pulse rounded-xl bg-white/[0.04]" />)}</div></div> : visible.length ? <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{visible.map((spark, sparkIndex) => <article key={spark.key} style={{ contentVisibility: "auto", containIntrinsicSize: "420px" }} className="cosmic-spark-card relative overflow-hidden rounded-xl border border-white/10 bg-white/[0.025]">
                    <div className={`h-40 overflow-hidden p-3 ${spark.trial_locked ? "blur-[3px] saturate-50 opacity-55" : ""}`}><div className="pointer-events-none h-full w-full"><SparkVisual spark={spark} previewVariant={["primary", "white", "surface", "white", "primary"][sparkIndex % 5]} websiteTheme={websiteTheme || "midnight"} /></div></div>
                    <div className="p-4"><div className="flex items-start justify-between gap-3"><div><p className="text-[10px] font-bold uppercase tracking-wider text-violet-300">{spark.category}</p><h3 className="mt-1 text-base font-semibold">{spark.name}</h3></div><div className="flex items-center gap-2">{!trialMode && <button type="button" disabled={busyKey === `favorite-${spark.key}`} onClick={() => toggleFavorite(spark)} className={`cosmic-flat-icon flex h-8 w-8 items-center justify-center rounded-lg border text-sm ${spark.favorited ? "border-rose-300/30 bg-rose-400/10 text-rose-200" : "border-white/10 text-slate-400 hover:text-white"}`}>{spark.favorited ? "♥" : "♡"}</button>}{spark.trial_locked ? <span className="rounded-full border border-white/15 bg-white/[0.06] px-2.5 py-1 text-[10px] font-bold text-slate-200">🔒 Locked</span> : spark.owned ? <span className="cosmic-owned-badge rounded-full border border-emerald-400/20 bg-emerald-400/10 px-2.5 py-1 text-[10px] font-bold text-emerald-200">{trialMode ? "Trial" : "✓ Owned"}</span> : Number(spark.credits || 0) === 0 ? <span className="rounded-full bg-cyan-300/10 px-2.5 py-1 text-[10px] font-bold text-cyan-100">Built-in · Free</span> : <span className="rounded-full bg-amber-300/10 px-2.5 py-1 text-[10px] font-bold text-amber-100">⚡ {spark.credits}</span>}</div></div><p className={`mt-2 line-clamp-2 text-xs leading-5 text-slate-400 ${spark.trial_locked ? "blur-[2px] select-none opacity-55" : ""}`}>{spark.description}</p>{aiResults && aiResultMap.get(spark.key)?.reason && <p className="mt-2 rounded-lg border border-violet-300/10 bg-violet-400/[0.06] px-2.5 py-2 text-[11px] leading-4 text-violet-100"><b>✦ Luna:</b> {aiResultMap.get(spark.key).reason}</p>}<div className="mt-4 flex gap-2"><button type="button" onClick={() => spark.can_preview === false ? showCosmicNotification({ title: "Preview locked", message: spark.preview_access?.message || "Upgrade your plan to preview this Spark.", tone: "warning" }) : setPreviewSpark(spark)} className="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-bold text-slate-200 transition hover:border-violet-400/40 hover:bg-white/5">Preview</button>{spark.trial_locked ? <Link href={trialSignupUrl} className="flex-1 rounded-xl bg-violet-400 px-4 py-2.5 text-center text-sm font-bold text-slate-950">Sign up to unlock</Link> : spark.owned ? <button onClick={() => setSelected(spark)} className="flex-1 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-950 hover:bg-violet-100">Add to Page</button> : spark.can_install === false && spark.usage_state?.upgrade_url ? <Link href={spark.usage_state.upgrade_url} className="flex-1 rounded-xl bg-amber-200 px-4 py-2.5 text-center text-sm font-bold text-slate-950">{spark.usage_state.actionLabel || "Upgrade to add"}</Link> : spark.can_install === false && spark.usage_state?.action === "buy_credits" ? <Link href="/credits" className="flex-1 rounded-xl bg-amber-200 px-4 py-2.5 text-center text-sm font-bold text-slate-950">Add credits</Link> : <button disabled={busyKey === spark.key || spark.can_install === false} onClick={() => unlock(spark)} className="flex-1 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-bold hover:bg-violet-500 disabled:opacity-50">{busyKey === spark.key ? "Buying..." : spark.usage_state?.actionLabel || (Number(spark.credits || 0) === 0 ? "Add Free Spark" : `Buy · ⚡ ${spark.credits}`)}</button>}</div></div>
                </article>)}</div> : <div className="rounded-2xl border border-dashed border-white/10 p-12 text-center"><div className="text-3xl">✨</div><h3 className="mt-3 font-semibold">{tab === "owned" ? "No owned Sparks found" : tab === "favorites" ? "No favorite Sparks yet" : "No Sparks match your search"}</h3><p className="mt-1 text-sm text-slate-500">{tab === "owned" ? "Open the Marketplace tab and add your first reusable Spark." : tab === "favorites" ? "Use the heart button to save Sparks here for quick access." : "Try another category or search phrase."}</p>{tab === "owned" && <button onClick={() => setTab("marketplace")} className="mt-5 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-950">Browse Marketplace</button>}</div>}
            </div>
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
                            <button type="button" onClick={() => { setSelected(previewSpark); setPreviewSpark(null); }} className="rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-slate-950">Add to Page</button>
                        ) : (
                            <button type="button" disabled={busyKey === previewSpark.key || previewSpark.can_install === false} onClick={() => unlock(previewSpark)} className="rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-bold text-white disabled:opacity-50">
                                {busyKey === previewSpark.key ? "Buying..." : previewSpark.slot_blocked ? "Owned Spark slots full" : previewSpark.can_install === false ? "Upgrade to add" : Number(previewSpark.credits || 0) === 0 ? "Add Free Spark" : `Buy Spark · ⚡ ${previewSpark.credits}`}
                            </button>
                        )}
                    </div>
                }
            />
        )}

        {personalizingSpark && <div className="cosmic-spark-progress-overlay fixed inset-0 z-[990] grid place-items-center bg-black/80 px-4 backdrop-blur-md" role="status" aria-live="polite">
            <section className="cosmic-spark-progress-modal w-full max-w-xl rounded-3xl border border-white/10 bg-[#151519]/98 px-5 py-7 text-center shadow-2xl shadow-black/70 sm:px-8 sm:py-8">
                <div className="relative mx-auto h-16 w-16" aria-hidden="true">
                    <div className="cosmic-loading-spinner absolute inset-0 rounded-full" />
                    <div className="absolute inset-[3px] grid place-items-center rounded-full bg-[#17171d] text-xl text-cyan-300 shadow-lg shadow-violet-950/50">✦</div>
                </div>
                <p className="mt-5 text-xs font-semibold uppercase tracking-[0.2em] text-violet-300">Cosmic AI</p>
                <h3 className="mt-2 text-2xl font-semibold tracking-tight text-white">Personalizing your Spark</h3>
                <p className="mt-3 text-sm text-slate-300">{personalizeStage}</p>
                <div className="mt-7 grid grid-cols-2 gap-2 sm:grid-cols-4">
                    {[
                        { label: "Understand Spark", threshold: 18 },
                        { label: "Personalize content", threshold: 46 },
                        { label: "Apply style", threshold: 74 },
                        { label: "Finish section", threshold: 96 },
                    ].map((step, index, steps) => {
                        const isComplete = personalizeProgress >= step.threshold;
                        const previousThreshold = index === 0 ? 0 : steps[index - 1].threshold;
                        const isCurrent = !isComplete && personalizeProgress >= previousThreshold;
                        return <div key={step.label} className={`flex items-center gap-2 rounded-lg border px-2.5 py-2 text-left text-[10px] font-medium sm:text-xs ${isComplete ? "border-emerald-400/30 bg-emerald-400/10 text-emerald-200" : isCurrent ? "border-violet-400/45 bg-violet-400/10 text-violet-100" : "border-white/10 bg-white/[0.02] text-slate-500"}`}>
                            <span className={`grid h-4 w-4 shrink-0 place-items-center rounded-full text-[9px] ${isComplete ? "bg-emerald-400 text-emerald-950" : isCurrent ? "bg-violet-400 text-white" : "bg-white/10 text-slate-400"}`}>{isComplete ? "✓" : index + 1}</span>
                            <span className="leading-4">{step.label}</span>
                        </div>;
                    })}
                </div>
                <div className="mt-6 h-2 overflow-hidden rounded-full bg-white/10">
                    <div className="h-full rounded-full bg-gradient-to-r from-violet-500 via-cyan-400 to-emerald-400 transition-[width] duration-200" style={{ width: `${personalizeProgress}%` }} />
                </div>
                <div className="mt-3 flex items-center justify-between text-xs text-slate-500"><span>Personalizing...</span><span>{personalizeProgress}%</span></div>
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
                    <button type="button" disabled={busyKey === selected.key} onClick={addSpark} className="rounded-xl bg-white px-5 py-2 text-sm font-bold text-slate-950 disabled:opacity-50">{busyKey === selected.key ? "Adding..." : mode === "ai" ? "Personalize & Add · ⚡20" : "Add to Page · FREE"}</button>
                </div>
            </section>
        </div>}
    </div>;
}
