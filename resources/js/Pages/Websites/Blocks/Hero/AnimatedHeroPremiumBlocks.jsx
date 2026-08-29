import { useEffect, useMemo, useRef, useState } from "react";
import { usePage } from "@inertiajs/react";
import { EditableText } from "../Shared/EditableText";
import { EditableButton } from "../Shared/EditableButton";
import { EditableImage } from "../Shared/EditableImage";
import { EditableImageGallery } from "../Shared/EditableImageGallery";
import { getHeroThemeState, resolveHeroThemeRequest } from "../../../../theme/heroTheme";
import { resolveMediaOverlay, effectiveMediaOverlayOpacity } from "../../../../theme/mediaOverlay";
import { colorFamilies } from "../../../../theme/colorFamilies";

import { sparkTw, sparkTwPath } from "../Shared/sparkTailwindRuntime";
const baseDefaults = {
    eyebrow: "MOTION, WITH PURPOSE",
    heading: "Make the first screen move.",
    text: "Pair confident messaging with considered motion to create a hero that feels polished without getting in the way.",
    primary_label: "Start a project",
    primary_url: "/contact",
    secondary_label: "Explore more",
    secondary_url: "/about",
    image_url: "/storage/cms-images/background/background-1.avif",
    image_url_2: "/storage/cms-images/background/background-2.avif",
    image_url_3: "/storage/cms-images/background/background-3.avif",
    overlayOpacity: 58,
    interval: 5200,
};

function schema(type, title, purpose, tags, extraDefaults = {}, extraFields = []) {
    return {
        type,
        title,
        category: "Hero",
        purpose,
        description: `${title} is a premium animated opening section with accessible, performance-aware motion.`,
        access: "pro",
        isPremium: true,
        badge: "PRO",
        credits: 150,
        tags: ["hero", "premium", "animated", "motion", ...tags],
        aliases: [title.toLowerCase(), ...tags],
        defaults: { ...baseDefaults, ...extraDefaults },
        fields: [
            { type: "text", name: "eyebrow", label: "Eyebrow" },
            { type: "textarea", name: "heading", label: "Heading" },
            { type: "textarea", name: "text", label: "Description" },
            { type: "button", name: "primary", label: "Primary Button" },
            { type: "button", name: "secondary", label: "Secondary Button" },
            { type: "image", name: "image_url", label: "Primary Image" },
            { type: "image", name: "image_url_2", label: "Secondary Image" },
            { type: "image", name: "image_url_3", label: "Third Image" },
            { type: "range", name: "overlayOpacity", label: "Overlay Opacity", min: 20, max: 85, step: 5 },
            ...extraFields,
        ],
    };
}

export const HeroKenBurnsPremiumSchema = schema(
    "hero_ken_burns_premium",
    "Ken Burns Cinematic Hero",
    "Adds a slow cinematic pan-and-zoom treatment to a full-bleed hero image.",
    ["ken burns", "cinematic", "slow zoom", "image pan", "luxury"]
);
export const HeroCrossfadeGalleryPremiumSchema = schema(
    "hero_crossfade_gallery_premium",
    "Crossfade Gallery Hero",
    "Cycles through three full-bleed images using a soft crossfade.",
    ["crossfade", "gallery", "dissolve", "slideshow", "photography"],
    { interval: 4600 }
);
export const HeroCinematicSliderPremiumSchema = schema(
    "hero_cinematic_slider_premium",
    "Cinematic Slider Hero",
    "Creates a fullscreen cinematic slider with progress and directional motion.",
    ["cinematic slider", "fullscreen slider", "progress", "slides", "launch"],
    { interval: 5600 }
);
export const HeroSplitSliderPremiumSchema = schema(
    "hero_split_slider_premium",
    "Split Slider Hero",
    "Keeps conversion copy anchored while imagery changes in a premium split composition.",
    ["split slider", "split screen", "image slider", "saas", "agency"],
    { interval: 4800 }
);
export const HeroVerticalStoryPremiumSchema = schema(
    "hero_vertical_story_premium",
    "Vertical Story Hero",
    "Moves visual story panels vertically for a distinctive editorial opening.",
    ["vertical slider", "story", "editorial", "vertical transition", "portfolio"],
    { interval: 5000 }
);
export const HeroParallaxLayersPremiumSchema = schema(
    "hero_parallax_layers_premium",
    "Parallax Layers Hero",
    "Creates layered scroll depth using independently moving media planes.",
    ["parallax layers", "scroll depth", "layered", "immersive", "motion"],
    { parallaxStrength: 22 },
    [{ type: "range", name: "parallaxStrength", label: "Parallax Strength", min: 8, max: 36, step: 2 }]
);
export const HeroMouseParallaxPremiumSchema = schema(
    "hero_mouse_parallax_premium",
    "Mouse Parallax Hero",
    "Adds subtle pointer-responsive depth to layered hero imagery.",
    ["mouse parallax", "cursor", "interactive", "3d depth", "pointer"],
    { pointerStrength: 16 },
    [{ type: "range", name: "pointerStrength", label: "Pointer Strength", min: 6, max: 28, step: 2 }]
);

const SCHEMAS = {
    hero_ken_burns_premium: HeroKenBurnsPremiumSchema,
    hero_crossfade_gallery_premium: HeroCrossfadeGalleryPremiumSchema,
    hero_cinematic_slider_premium: HeroCinematicSliderPremiumSchema,
    hero_split_slider_premium: HeroSplitSliderPremiumSchema,
    hero_vertical_story_premium: HeroVerticalStoryPremiumSchema,
    hero_parallax_layers_premium: HeroParallaxLayersPremiumSchema,
    hero_mouse_parallax_premium: HeroMouseParallaxPremiumSchema,
};

function AnimatedHeroPremiumBlock({ block, blockIndex, onUpdate, globalTheme }) {
    const schemaDef = SCHEMAS[block.type] || HeroKenBurnsPremiumSchema;
    const heroState = getHeroThemeState(block, globalTheme);
    const { theme, isPrimary, isLight } = heroState;
    const mediaOverlay = resolveMediaOverlay(globalTheme, resolveHeroThemeRequest(block, globalTheme));
    const primaryKey = typeof globalTheme === "string" ? globalTheme : (globalTheme?.primary || "midnight");
    const primaryTheme = colorFamilies[primaryKey] || colorFamilies.midnight;
    const data = { ...schemaDef.defaults, ...block };
    const { props } = usePage();
    const websiteId = props.page?.website_id || props.website?.id;
    const rootRef = useRef(null);
    const singleImageRef = useRef(null);
    const galleryRef = useRef(null);
    const [active, setActive] = useState(0);
    const [isVisible, setIsVisible] = useState(true);
    const images = useMemo(() => [data.image_url, data.image_url_2, data.image_url_3].filter(Boolean), [data.image_url, data.image_url_2, data.image_url_3]);
    const isTimed = ["hero_crossfade_gallery_premium", "hero_cinematic_slider_premium", "hero_split_slider_premium", "hero_vertical_story_premium"].includes(block.type);
    const usesGallery = block.type !== "hero_ken_burns_premium" && images.length > 1;
    const updateGalleryImages = (next) => onUpdate({ image_url: next[0] || "", image_url_2: next[1] || "", image_url_3: next[2] || "" });
    const handleMediaEdit = (event) => {
        if (event.target.closest("button, a, input, textarea, select, label, [contenteditable=\'true\'], [role=\'button\'], [data-cosmic-edit-control]")) return;
        if (usesGallery) galleryRef.current?.openEditor();
        else singleImageRef.current?.openEditor();
    };

    useEffect(() => {
        const root = rootRef.current;
        if (!root || typeof IntersectionObserver === "undefined") return undefined;
        const observer = new IntersectionObserver(([entry]) => setIsVisible(entry.isIntersecting), { rootMargin: "160px 0px" });
        observer.observe(root);
        return () => observer.disconnect();
    }, []);

    useEffect(() => {
        if (!isTimed || !isVisible || images.length < 2) return undefined;
        const media = window.matchMedia("(prefers-reduced-motion: reduce)");
        if (media.matches) return undefined;
        const id = window.setInterval(() => setActive((value) => (value + 1) % images.length), Math.max(3200, Number(data.interval) || 5000));
        return () => window.clearInterval(id);
    }, [isTimed, isVisible, images.length, data.interval]);

    useEffect(() => {
        const root = rootRef.current;
        if (!root || !["hero_parallax_layers_premium", "hero_mouse_parallax_premium"].includes(block.type)) return undefined;
        const reduced = window.matchMedia("(prefers-reduced-motion: reduce)");
        let raf = null;

        const apply = (x = 0, y = 0) => {
            const layers = root.querySelectorAll("[data-motion-layer]");
            layers.forEach((layer, index) => {
                const depth = (index + 1) * 0.38;
                layer.style.transform = `translate3d(${x * depth}px, ${y * depth}px, 0) scale(${1.04 + index * 0.025})`;
            });
        };

        const onPointer = (event) => {
            if (block.type !== "hero_mouse_parallax_premium" || reduced.matches) return;
            const rect = root.getBoundingClientRect();
            const strength = Number(data.pointerStrength || 16);
            const x = ((event.clientX - rect.left) / Math.max(1, rect.width) - 0.5) * strength;
            const y = ((event.clientY - rect.top) / Math.max(1, rect.height) - 0.5) * strength;
            if (raf) cancelAnimationFrame(raf);
            raf = requestAnimationFrame(() => apply(x, y));
        };

        const onScroll = () => {
            if (block.type !== "hero_parallax_layers_premium" || reduced.matches) return;
            const rect = root.getBoundingClientRect();
            const viewport = window.innerHeight || 1;
            if (rect.bottom < 0 || rect.top > viewport) return;
            const strength = Number(data.parallaxStrength || 22);
            const progress = ((viewport - rect.top) / (viewport + rect.height) - 0.5) * strength;
            if (raf) cancelAnimationFrame(raf);
            raf = requestAnimationFrame(() => apply(0, progress));
        };

        const onPointerLeave = () => apply(0, 0);
        root.addEventListener("pointermove", onPointer);
        root.addEventListener("pointerleave", onPointerLeave);
        document.addEventListener("scroll", onScroll, true);
        onScroll();
        return () => {
            root.removeEventListener("pointermove", onPointer);
            root.removeEventListener("pointerleave", onPointerLeave);
            document.removeEventListener("scroll", onScroll, true);
            if (raf) cancelAnimationFrame(raf);
        };
    }, [block.type, data.pointerStrength, data.parallaxStrength]);

    const split = block.type === "hero_split_slider_premium";
    const vertical = block.type === "hero_vertical_story_premium";
    const layered = ["hero_parallax_layers_premium", "hero_mouse_parallax_premium"].includes(block.type);
    const overlay = effectiveMediaOverlayOpacity(data.overlayOpacity, { isLight, lightMinimum: 90 }) / 100;
    const primaryButton = isPrimary ? "bg-white text-slate-950 hover:bg-white/90" : `${primaryTheme.bg} ${primaryTheme.text}`;
    const secondaryButton = isLight ? `border-slate-300 bg-white/70 ${theme.text} hover:bg-white` : "border-white/30 bg-white/10 text-white hover:bg-white/20";
    const copyText = isLight ? theme.text : "text-white";
    const copySub = isLight ? theme.sub : "text-white/72";

    const media = (
        <div
            data-cosmic-editable-hero-media="true"
            title={usesGallery ? "Click to edit hero images" : "Click to edit hero image"}
            className={sparkTw(block, "media", `absolute inset-0 cursor-pointer overflow-hidden ${split ? "lg:relative lg:min-h-[640px]" : ""}`)}
        >
            {images.map((src, index) => (
                <div
                    key={`${src}-${index}`}
                    data-motion-layer={layered ? String(index + 1) : undefined}
                    className={`${sparkTwPath(block, ["images", index], "layer", "absolute inset-0 transition-all duration-1000 ease-out")} ${
                        layered ? (index === 0 ? "opacity-100" : index === 1 ? "opacity-40 mix-blend-screen" : "opacity-20 mix-blend-overlay") :
                        vertical ? (index === active ? "translate-y-0 opacity-100" : index < active ? "-translate-y-full opacity-0" : "translate-y-full opacity-0") :
                        index === active ? "opacity-100 scale-100" : "opacity-0 scale-[1.025]"
                    } ${block.type === "hero_ken_burns_premium" && index === 0 ? "cosmic-hero-kenburns" : ""}`.trim()}
                >
                    {index === 0 ? (
                        <EditableImage
                            ref={singleImageRef}
                            websiteId={websiteId}
                            blockIndex={blockIndex}
                            src={src}
                            showOverlay={false}
                            isBackground
                            blockType={block.type}
                            className={sparkTwPath(block, ["images", index], "image", "absolute inset-0 h-full w-full")}
                            onSave={(value) => onUpdate({ image_url: value })}
                        />
                    ) : (
                        <img src={src} alt="" className={sparkTwPath(block, ["images", index], "image", "h-full w-full object-cover")} loading="lazy" decoding="async" />
                    )}
                </div>
            ))}
            <div className={sparkTw(block, "overlay_color", "absolute inset-0")} style={{ backgroundColor: mediaOverlay.overlayColor, opacity: overlay }} />
            <div className={sparkTw(block, "overlay", `absolute inset-0 ${isLight ? "bg-gradient-to-r from-white/75 via-white/30 to-white/10" : "bg-gradient-to-r from-slate-950/70 via-slate-950/20 to-transparent"}`)} />
        </div>
    );

    return (
        <section ref={rootRef} data-cosmic-media-banner="true" onClick={handleMediaEdit} data-cosmic-hero-theme={heroState.requestedTheme} style={{minHeight: split ? "640px" : "var(--cosmic-hero-fold-height, calc(100svh - 80px))"}} className={sparkTw(block, "section", `relative isolate overflow-hidden ${isLight ? `${theme.bg} ${theme.text}` : "bg-slate-950 text-white"} ${split ? "lg:grid lg:grid-cols-[0.9fr_1.1fr]" : ""}`)}>
            {split ? null : media}
            <div className={sparkTw(block, "wrapper", `relative z-20 mx-auto flex w-full max-w-7xl items-center px-6 py-0 sm:px-10 lg:px-14 ${split ? "lg:min-h-[640px]" : ""}`)} style={{minHeight: split ? "640px" : "var(--cosmic-hero-fold-height, calc(100svh - 80px))"}}>
                <div className={sparkTw(block, "content", "max-w-3xl")}>
                    <EditableText value={data.eyebrow} className={sparkTw(block, "eyebrow", `text-xs font-bold uppercase tracking-[0.32em] ${isLight ? theme.sub : "text-white/70"}`)} onSave={(value) => onUpdate({ eyebrow: value })} />
                    <EditableText value={data.heading} cosmicType="h1" className={sparkTw(block, "heading", `mt-6 block text-4xl font-semibold leading-[0.98] tracking-[-0.045em] sm:text-5xl lg:text-6xl xl:text-7xl ${copyText}`)} onSave={(value) => onUpdate({ heading: value })} />
                    <EditableText value={data.text} className={sparkTw(block, "description", `mt-7 block max-w-2xl text-base leading-8 sm:text-lg ${copySub}`)} onSave={(value) => onUpdate({ text: value })} />
                    <div className={sparkTw(block, "actions", "mt-9 flex flex-wrap gap-3")}>
                        <EditableButton label={data.primary_label} url={data.primary_url} onSave={(label, url) => onUpdate({ primary_label: label, primary_url: url })} className={sparkTw(block, "primary_button", `rounded-full px-6 py-3.5 text-sm font-bold transition ${primaryButton}`)} />
                        <EditableButton label={data.secondary_label} url={data.secondary_url} onSave={(label, url) => onUpdate({ secondary_label: label, secondary_url: url })} className={sparkTw(block, "secondary_button", `rounded-full border px-6 py-3.5 text-sm font-bold backdrop-blur transition ${secondaryButton}`)} />
                    </div>
                    {isTimed && images.length > 1 && (
                        <div className={sparkTw(block, "progress", "mt-10 flex items-center gap-2")} aria-label="Slide progress">
                            {images.map((_, index) => <span key={index} className={`${sparkTwPath(block, ["images", index], "dot", "h-1 rounded-full transition-all duration-500")} ${index === active ? (isLight ? "w-12 bg-slate-700" : "w-12 bg-white") : (isLight ? "w-5 bg-slate-400/50" : "w-5 bg-white/30")}`.trim()} />)}
                        </div>
                    )}
                </div>
            </div>
            {split && <div className={sparkTw(block, "split_media", "relative min-h-[560px] lg:min-h-[720px]")}>{media}</div>}
            {usesGallery ? <EditableImageGallery ref={galleryRef} websiteId={websiteId} images={images} maxItems={3} title={`${schemaDef.title} images`} onSave={updateGalleryImages} /> : null}
        </section>
    );
}

export const HeroKenBurnsPremiumBlock = AnimatedHeroPremiumBlock;
export const HeroCrossfadeGalleryPremiumBlock = AnimatedHeroPremiumBlock;
export const HeroCinematicSliderPremiumBlock = AnimatedHeroPremiumBlock;
export const HeroSplitSliderPremiumBlock = AnimatedHeroPremiumBlock;
export const HeroVerticalStoryPremiumBlock = AnimatedHeroPremiumBlock;
export const HeroParallaxLayersPremiumBlock = AnimatedHeroPremiumBlock;
export const HeroMouseParallaxPremiumBlock = AnimatedHeroPremiumBlock;
