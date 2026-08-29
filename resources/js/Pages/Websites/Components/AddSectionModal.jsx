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
    lunaSparksCount = 0,
    onOpenLunaSparks = null,
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
    const items = useMemo(() => catalog.map((spark) => ({ ...spark, registry: registry.get(spark.key) })).filter((spark) => spark.registry), [catalog, registry]);
    const categories = useMemo(() => ["All", ...new Set(items.map((item) => item.category || categoryFor(item.key)))], [items]);
    const categoryCards = useMemo(() => {
        const countsByCategory = new Map();
        items.forEach((item) => {
            const name = item.category || categoryFor(item.key);
            if (!name || name === "All") return;
            countsByCategory.set(name, (countsByCategory.get(name) || 0) + 1);
        });
        const preferred = ["Hero", "Services", "Features", "Proof", "Testimonials", "Pricing", "Team", "FAQ", "Contact", "Case Studies", "Posts / Updates", "Events", "Careers", "Mini Heroes", "Other"];
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

    const filteredItems = useMemo(() => items.filter((item) => {
        if (tab === "owned" && !item.owned) return false;
        if (tab === "favorites" && !item.favorited) return false;
        if (category !== "All" && item.category !== category) return false;
        if (aiResults && !aiResultMap.has(item.key)) return false;
        if (aiResults) return true;
        const haystack = `${item.name} ${item.description} ${item.category} ${item.collection || ""}`.toLowerCase();
        return haystack.includes(query.trim().toLowerCase());
    }).sort((a, b) => aiResults ? ((aiResultMap.get(a.key)?.rank || 999) - (aiResultMap.get(b.key)?.rank || 999)) : 0), [items, tab, category, query, aiResults, aiResultMap]);

    const categorySparkItems = useMemo(() => {
        if (category === "All") {
            if (!aiResults) return [];
            return items
                .filter((item) => aiResultMap.has(item.key))
                .sort((a, b) => (aiResultMap.get(a.key)?.rank || 999) - (aiResultMap.get(b.key)?.rank || 999));
        }
        return items.filter((item) => (item.category || categoryFor(item.key)) === category);
    }, [items, category, aiResults, aiResultMap]);

    const pickerSpark = useMemo(() => {
        if (!pickerSparkKey) return categorySparkItems[0] || null;
        return items.find((item) => item.key === pickerSparkKey) || categorySparkItems[0] || null;
    }, [items, categorySparkItems, pickerSparkKey]);

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
        const matches = items.filter((item) => (item.category || categoryFor(item.key)) === name);
        const initial = matches.find((item) => item.owned && !item.trial_locked) || matches.find((item) => !item.trial_locked) || matches[0] || null;
        setCategory(name);
        setPickerSparkKey(initial?.key || null);
        setPreviewVariantIndex(0);

        // Hotfix: when Add Section is connected to Builder's reusable Section
        // Editor, choosing a category should enter that editor immediately. The
        // editor already exposes every compatible Spark layout plus contextual
        // Luna, so this removes the redundant category-specific preview screen.
        // The new block is still only a popup draft; Builder commits it later
        // when the user presses Add Section in the Section Editor.
        if (onCustomize && initial?.owned && !initial?.trial_locked) {
            const block = blockForSpark(initial);
            if (block) {
                onCustomize(block, { spark: initial, insertionContext, category: name });
                onClose();
                return;
            }
        }

        // Locked/unowned categories keep the marketplace step so the user can
        // unlock a Spark before opening the Section Editor.
        setPickerStage("sparks");
        window.requestAnimationFrame(() => marketplaceScrollRef.current?.scrollTo({ top: 0, behavior: "auto" }));
    };

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
            onCustomize(block, { spark, insertionContext });
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
            className={`cosmic-add-spark-modal cosmic-native-text-layer relative z-10 flex max-h-[92vh] w-full max-w-[1500px] flex-col overflow-hidden rounded-2xl border shadow-2xl ${appDark ? 'border-white/10 bg-[#111116] text-white shadow-black/70' : 'border-slate-200 bg-white text-slate-950 shadow-slate-950/20'} ${popupActive ? 'is-active' : ''}`}
        >
            <button type="button" onClick={onClose} className={`absolute right-4 top-4 z-30 grid h-10 w-10 place-items-center rounded-xl border text-sm shadow-sm transition ${appDark ? 'border-white/10 bg-[#18181d] text-slate-300 hover:bg-white/10 hover:text-white' : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50 hover:text-slate-900'}`} aria-label="Close Add Section">✕</button>
            {pickerStage === "categories" ? (
                <div className={`cosmic-add-section-library grid min-h-[620px] flex-1 grid-cols-1 overflow-hidden lg:grid-cols-[minmax(0,1fr)_320px] ${appDark ? 'bg-[#111116] text-white' : 'bg-white text-slate-950'}`}>
                    <div className="min-w-0 overflow-y-auto px-6 py-6 sm:px-8 sm:py-8">
                        <div className="flex items-start justify-between gap-4 pr-12">
                            <div>
                                <p className="text-[10px] font-bold uppercase tracking-[0.22em] text-violet-600">{modalContextLabel}</p>
                                <h2 className="mt-1 text-2xl font-semibold tracking-tight">Choose a section type</h2>
                                <p className={`mt-1.5 max-w-2xl text-sm leading-6 ${appDark ? 'text-slate-400' : 'text-slate-600'}`}>Pick a section type, or start blank with Luna for a brand-new AI Flex section. Blank Luna sections skip the preset picker and open directly in the Section Editor.</p>
                                {insertionHint ? <p className={`mt-2 text-xs font-semibold ${appDark ? 'text-violet-300' : 'text-violet-700'}`}>{insertionHint}</p> : null}
                            </div>
                        </div>

                        {loading && catalog.length === 0 ? (
                            <div className={`mt-8 grid min-h-[420px] place-items-center rounded-2xl border border-dashed ${appDark ? 'border-white/10 bg-white/[0.02]' : 'border-slate-200 bg-slate-50'}`}>
                                <div className="text-center"><div className="mx-auto h-10 w-10 animate-spin rounded-full border-4 border-violet-200 border-t-violet-600" /><p className="mt-4 text-sm font-semibold">Preparing section types…</p></div>
                            </div>
                        ) : (
                            <div className="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                <button
                                    type="button"
                                    onClick={startBlankWithLuna}
                                    className={`group min-h-40 rounded-2xl border p-5 text-left transition hover:-translate-y-0.5 hover:shadow-lg ${appDark ? 'border-violet-400/35 bg-gradient-to-br from-violet-500/10 to-indigo-500/5 hover:border-violet-300' : 'border-violet-200 bg-gradient-to-br from-violet-50 to-indigo-50 shadow-sm hover:border-violet-400'}`}
                                >
                                    <div className={`grid h-10 w-10 place-items-center rounded-xl text-lg font-bold ${appDark ? 'bg-violet-400/15 text-violet-200' : 'bg-violet-600 text-white'}`}>✦</div>
                                    <div className="mt-8 flex items-end justify-between gap-3">
                                        <div>
                                            <div className="text-base font-semibold">Start Blank with Luna</div>
                                            <div className={`mt-1 text-xs leading-5 ${appDark ? 'text-violet-200/70' : 'text-violet-700'}`}>Build a new AI Flex section from scratch</div>
                                        </div>
                                        <span className="text-lg text-violet-500 transition group-hover:translate-x-1" aria-hidden="true">→</span>
                                    </div>
                                </button>
                                <button
                                    type="button"
                                    onClick={() => onOpenLunaSparks?.()}
                                    disabled={!onOpenLunaSparks}
                                    className={`group min-h-40 rounded-2xl border p-5 text-left transition hover:-translate-y-0.5 hover:shadow-lg disabled:cursor-not-allowed disabled:opacity-50 ${appDark ? 'border-fuchsia-400/25 bg-white/[0.035] hover:border-fuchsia-300' : 'border-fuchsia-200 bg-white shadow-sm hover:border-fuchsia-400'}`}
                                >
                                    <div className={`grid h-10 w-10 place-items-center rounded-xl text-lg font-bold ${appDark ? 'bg-fuchsia-400/10 text-fuchsia-300' : 'bg-fuchsia-50 text-fuchsia-700'}`}>▣</div>
                                    <div className="mt-8 flex items-end justify-between gap-3">
                                        <div>
                                            <div className="text-base font-semibold">Luna Sparks ✦</div>
                                            <div className={`mt-1 text-xs ${appDark ? 'text-slate-500' : 'text-slate-500'}`}>{Number(lunaSparksCount || 0)} saved Luna Spark{Number(lunaSparksCount || 0) === 1 ? '' : 's'}</div>
                                        </div>
                                        <span className="text-lg text-fuchsia-500 transition group-hover:translate-x-1" aria-hidden="true">→</span>
                                    </div>
                                </button>
                                {categoryCards.map((item) => (
                                    <button
                                        key={item.name}
                                        type="button"
                                        onClick={() => chooseCategory(item.name)}
                                        className={`group min-h-40 rounded-2xl border p-5 text-left transition hover:-translate-y-0.5 hover:border-violet-400 hover:shadow-lg ${appDark ? 'border-white/10 bg-white/[0.035]' : 'border-slate-200 bg-white shadow-sm'}`}
                                    >
                                        <div className={`grid h-10 w-10 place-items-center rounded-xl text-lg font-bold ${appDark ? 'bg-violet-400/10 text-violet-300' : 'bg-violet-50 text-violet-700'}`}>✦</div>
                                        <div className="mt-8 flex items-end justify-between gap-3">
                                            <div><div className="text-base font-semibold">{item.name}</div><div className={`mt-1 text-xs ${appDark ? 'text-slate-500' : 'text-slate-500'}`}>{item.count} Spark{item.count === 1 ? '' : 's'}</div></div>
                                            <span className="text-lg text-violet-500 transition group-hover:translate-x-1" aria-hidden="true">→</span>
                                        </div>
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>

                    <aside className={`flex min-h-0 flex-col border-t p-5 lg:border-l lg:border-t-0 ${appDark ? 'border-white/10 bg-[#0d0d12]' : 'border-slate-200 bg-slate-50'}`}>
                        <div>
                            <p className="text-[10px] font-black uppercase tracking-[0.18em] text-violet-500">✦ Luna · {isContextualInsert ? modalContextLabel : 'Add Section'}</p>
                            <h3 className="mt-2 text-base font-semibold">Need help choosing?</h3>
                            <p className={`mt-1 text-xs leading-5 ${appDark ? 'text-slate-400' : 'text-slate-600'}`}>Describe the section you want. Luna will rank matching Sparks without changing the Builder.</p>
                        </div>
                        <div className="mt-auto pt-6">
                            <textarea
                                value={query}
                                onChange={(event) => { setQuery(event.target.value); if (aiResults) clearAiSearch(); }}
                                rows={5}
                                placeholder="e.g. Add a premium services section with four cards"
                                className={`w-full resize-none rounded-xl border px-3.5 py-3 text-sm outline-none transition focus:border-violet-400 ${appDark ? 'border-white/10 bg-black/20 text-white placeholder:text-slate-600' : 'border-slate-300 bg-white text-slate-900 placeholder:text-slate-400'}`}
                            />
                            <button type="button" disabled={trialMode || aiSearchBusy || query.trim().length < 2} onClick={runAiSearch} className="mt-2 inline-flex w-full items-center justify-center gap-1.5 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-violet-500 disabled:cursor-not-allowed disabled:opacity-40"><span aria-hidden="true">✦</span>{aiSearchBusy ? 'Searching…' : 'Ask Luna'}</button>
                            <div className={`mt-4 rounded-xl border px-3 py-3 text-xs leading-5 ${appDark ? 'border-white/10 bg-white/[0.03] text-slate-400' : 'border-slate-200 bg-white text-slate-600'}`}><b className={appDark ? 'text-slate-200' : 'text-slate-800'}>Whole page?</b><br/>Use the whole-page Luna chat when you want Luna to plan multiple sections together.</div>
                        </div>
                    </aside>
                </div>
            ) : (
                <>
                    <header className={`shrink-0 border-b px-5 py-4 pr-16 sm:px-7 sm:pr-20 ${appDark ? 'border-white/10 bg-[#111116]' : 'border-slate-200 bg-white'}`}>
                        <div className="flex items-start justify-between gap-4">
                            <div className="min-w-0">
                                <button type="button" onClick={() => { setPickerStage('categories'); setCategory('All'); setPickerSparkKey(null); clearAiSearch(); }} className={`mb-2 inline-flex items-center gap-1 text-xs font-semibold ${appDark ? 'text-slate-400 hover:text-white' : 'text-slate-500 hover:text-slate-900'}`}>← Section types</button>
                                <p className="text-[10px] font-bold uppercase tracking-[0.2em] text-violet-500">{modalContextLabel} · {category}</p>
                                <h2 className="mt-1 truncate text-xl font-semibold">{pickerSpark?.name || `Choose a ${category} Spark`}</h2>
                            </div>
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
                            ) : <div className="grid min-h-[420px] place-items-center text-sm text-slate-500">No Sparks found in this category.</div>}
                        </div>
                        <aside className={`flex min-h-0 flex-col border-t p-4 lg:border-l lg:border-t-0 ${appDark ? 'border-white/10 bg-[#0d0d12]' : 'border-slate-200 bg-slate-50'}`}>
                            <p className="text-[10px] font-black uppercase tracking-[0.18em] text-violet-500">✦ Luna · {category}</p>
                            <h3 className="mt-2 text-sm font-semibold">{pickerSpark?.name || category}</h3>
                            <p className={`mt-1 text-xs leading-5 ${appDark ? 'text-slate-400' : 'text-slate-600'}`}>Click the Spark buttons to compare layouts. Nothing is added until you confirm below.</p>
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
                            {pickerSpark?.trial_locked ? <Link href={trialSignupUrl} className="rounded-xl bg-violet-600 px-5 py-2 text-sm font-bold text-white">Sign up to unlock</Link> : pickerSpark?.owned ? <button type="button" onClick={() => addPickerSparkQuick(pickerSpark)} className="rounded-xl bg-slate-950 px-5 py-2 text-sm font-bold text-white hover:bg-slate-800">Customize Section</button> : pickerSpark ? <button type="button" disabled={busyKey === pickerSpark.key || pickerSpark.can_install === false} onClick={() => unlock(pickerSpark)} className="rounded-xl bg-violet-600 px-5 py-2 text-sm font-bold text-white hover:bg-violet-500 disabled:opacity-40">{busyKey === pickerSpark.key ? 'Adding…' : Number(pickerSpark.credits || 0) === 0 ? 'Add Free Spark' : `Unlock · ⚡ ${pickerSpark.credits}`}</button> : null}
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
                    <button type="button" disabled={busyKey === selected.key} onClick={addSpark} className="rounded-xl bg-white px-5 py-2 text-sm font-bold text-slate-950 disabled:opacity-50">{busyKey === selected.key ? "Preparing..." : mode === "ai" ? "Personalize & Customize · ⚡20" : "Customize Section"}</button>
                </div>
            </section>
        </div>}
    </div>;
}
