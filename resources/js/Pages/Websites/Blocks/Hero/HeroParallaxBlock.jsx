import { usePage } from "@inertiajs/react";
import { useEffect, useRef } from "react";
import { resolveMediaOverlay, effectiveMediaOverlayOpacity } from "../../../../theme/mediaOverlay";
import { getHeroThemeState, resolveHeroThemeRequest } from "../../../../theme/heroTheme";

import { EditableButton } from "../Shared/EditableButton";
import { EditableImage } from "../Shared/EditableImage";
import { EditableText } from "../Shared/EditableText";
import { colorFamilies } from "../../../../theme/colorFamilies";
import { sparkTw } from "../Shared/sparkTailwindRuntime";

export const HeroParallaxSchema = {
    type: "hero_parallax",
    title: "Hero Parallax",
    category: "Hero",
    purpose: "Creates an immersive full-screen hero with a smooth scroll-driven parallax background.",
    description: "A premium motion hero for bold launches, portfolios, hotels, agencies, and high-impact business websites.",
    access: "pro",
    isPremium: true,
    badge: "PRO",
    credits: 100,
    tags: ["hero", "parallax", "motion", "premium", "immersive", "background", "cta"],
    defaults: {
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
        scroll_label: "Scroll to explore",
    },
    fields: [
        { type: "text", name: "eyebrow", label: "Eyebrow" },
        { type: "textarea", name: "heading", label: "Heading" },
        { type: "textarea", name: "text", label: "Description" },
        { type: "button", name: "primary", label: "Primary Button" },
        { type: "button", name: "secondary", label: "Secondary Button" },
        { type: "image", name: "image_url", label: "Background Image" },
        { type: "range", name: "overlayOpacity", label: "Overlay Opacity", min: 20, max: 90, step: 5 },
        { type: "range", name: "parallaxSpeed", label: "Parallax Strength", min: 8, max: 40, step: 2 },
        { type: "select", name: "contentAlign", label: "Content Alignment", options: ["left", "center", "right"] },
        { type: "select", name: "height", label: "Hero Height", options: ["large", "screen"] },
        { type: "text", name: "scroll_label", label: "Scroll Label" },
    ],
};

export function HeroParallaxBlock({ block, blockIndex, onUpdate, globalTheme }) {
    const sectionRef = useRef(null);
    const imageRef = useRef(null);
    const contentRef = useRef(null);
    const data = { ...HeroParallaxSchema.defaults, ...block };
    const { props } = usePage();
    const websiteId = props.page?.website_id || props.website?.id;
    const normalizedGlobalTheme = typeof globalTheme === 'string' ? { primary: globalTheme } : (globalTheme || {});
    const primaryTheme = colorFamilies[normalizedGlobalTheme.primary] || colorFamilies.midnight;
    const mediaOverlay = resolveMediaOverlay(globalTheme, resolveHeroThemeRequest(block, globalTheme));
    const isLightMediaTheme = mediaOverlay.isLight;
    const overlayColor = mediaOverlay.overlayColor;
    const configuredOverlayOpacity = Math.max(20, Math.min(90, Number(data.overlayOpacity) || 64));
    const effectiveOverlayOpacity = effectiveMediaOverlayOpacity(configuredOverlayOpacity, {
        isLight: isLightMediaTheme,
        lightMinimum: 96,
    });
    const mediaStyle = isLightMediaTheme
        ? {
            overlay: "bg-white",
            gradient: "from-white/100 via-white/97 to-white/92",
            badge: "border-slate-900/15 bg-white/72",
            eyebrow: "!text-slate-700",
            heading: "!text-slate-950",
            body: "!text-slate-700",
            secondary: "border-slate-900/20 bg-white/72 !text-slate-950 hover:bg-white/90",
            scroll: "!text-slate-700",
            scrollLine: "bg-slate-900/25",
            scrollDot: "bg-slate-900",
        }
        : {
            overlay: "bg-slate-950",
            gradient: "from-slate-950/55 via-slate-950/12 to-slate-950/16",
            badge: "border-white/20 bg-white/10",
            eyebrow: "!text-white/85",
            heading: "!text-white",
            body: "!text-white/75",
            secondary: "border-white/30 bg-white/10 !text-white hover:bg-white/20",
            scroll: "!text-white/65",
            scrollLine: "bg-white/25",
            scrollDot: "bg-white",
        };

    useEffect(() => {
        const section = sectionRef.current;
        const media = imageRef.current?.closest?.(".cosmic-parallax-media");
        const content = contentRef.current;
        if (!section || !media) return undefined;

        const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
        let frame = null;

        const updateParallax = () => {
            frame = null;

            if (reducedMotion.matches) {
                media.style.transform = "translate3d(0, 0, 0) scale(1.14)";
                if (content) {
                    content.style.transform = "translate3d(0, 0, 0)";
                    content.style.opacity = "1";
                }
                return;
            }

            const rect = section.getBoundingClientRect();
            const viewport = window.innerHeight || 1;
            if (rect.bottom <= 0 || rect.top >= viewport) return;

            // 0 at entry, 0.5 near viewport centre, 1 at exit.
            const progress = Math.max(0, Math.min(1, (viewport - rect.top) / (viewport + rect.height)));
            const centered = progress - 0.5;
            const strength = Number(data.parallaxSpeed || 24);
            const mediaOffset = centered * strength * 7;
            const contentOffset = centered * strength * -1.7;
            const contentOpacity = Math.max(0.35, 1 - Math.abs(centered) * 0.75);

            media.style.transform = `translate3d(0, ${mediaOffset}px, 0) scale(1.14)`;
            if (content) {
                content.style.transform = `translate3d(0, ${contentOffset}px, 0)`;
                content.style.opacity = String(contentOpacity);
            }
        };

        const requestUpdate = () => {
            if (frame === null) frame = window.requestAnimationFrame(updateParallax);
        };

        updateParallax();
        // Capture scroll events so the effect also works inside the Builder's scrollable canvas.
        document.addEventListener("scroll", requestUpdate, true);
        window.addEventListener("resize", requestUpdate);
        reducedMotion.addEventListener?.("change", requestUpdate);

        return () => {
            document.removeEventListener("scroll", requestUpdate, true);
            window.removeEventListener("resize", requestUpdate);
            reducedMotion.removeEventListener?.("change", requestUpdate);
            if (frame !== null) window.cancelAnimationFrame(frame);
        };
    }, [data.parallaxSpeed]);

    const alignment = {
        left: "items-start text-left",
        center: "items-center text-center",
        right: "items-end text-right",
    };

    const contentWidth = data.contentAlign === "center" ? "max-w-4xl" : "max-w-3xl";
    const heroHeight = data.height === "large" ? "min-h-[720px]" : "";

    const handleSectionImageEdit = (event) => {
        // Keep text, buttons, and other Builder controls independently editable.
        // Any click on the remaining hero canvas opens the background media editor.
        if (event.target.closest("button, a, input, textarea, select, label, [contenteditable='true'], [role='button'], [data-cosmic-edit-control]")) {
            return;
        }

        imageRef.current?.openEditor();
    };

    return (
        <section
            data-cosmic-media-banner="true"
            ref={sectionRef}
            className={sparkTw(block, "section", `relative isolate flex cursor-pointer overflow-hidden py-0 ${heroHeight}`)}
            onClick={handleSectionImageEdit}
            style={data.height === "screen" ? {minHeight:"var(--cosmic-hero-fold-height, calc(100svh - 80px))"} : undefined}
        >
            <div
                className={sparkTw(block, "wrapper", "cosmic-parallax-media absolute -inset-y-[18%] inset-x-0 z-0 will-change-transform")}
                style={{ transform: "translate3d(0, 0, 0) scale(1.14)" }}
            >
                <EditableImage
                    ref={imageRef}
                    websiteId={websiteId}
                    blockIndex={blockIndex}
                    src={data.image_url}
                    showOverlay={false}
                    isBackground
                    className={sparkTw(block, "image", "absolute inset-0 h-full w-full overflow-hidden")}
                    onSave={(value) => onUpdate({ image_url: value })}
                />
            </div>

            <div className={sparkTw(block, "wrapper_2", "absolute inset-0 z-10")} style={{ backgroundColor: overlayColor, opacity: effectiveOverlayOpacity / 100 }} />
            <div className={sparkTw(block, "wrapper_3", `absolute inset-0 z-10 bg-gradient-to-t ${mediaStyle.gradient} ${data.contentAlign === "right" ? "bg-gradient-to-l" : data.contentAlign === "left" ? "bg-gradient-to-r" : ""}`)} />

            <div
                ref={contentRef}
                className={sparkTw(block, "wrapper_4", `relative z-20 mx-auto flex w-full max-w-7xl flex-col justify-center px-4 transition-opacity duration-150 sm:px-6 lg:px-8 ${alignment[data.contentAlign] || alignment.left}`)}
                style={{ transform: "translate3d(0, 0, 0)", willChange: "transform, opacity" }}
            >
                <div className={sparkTw(block, "b10_1", contentWidth)}>
                    <div className={sparkTw(block, "wrapper_5", `inline-flex items-center gap-3 rounded-full border px-4 py-2 backdrop-blur-md ${mediaStyle.badge}`)}>
                        <span className={sparkTw(block, "label", `h-2 w-2 rounded-full ${primaryTheme.bg}`)} />
                        <EditableText
                            value={data.eyebrow}
                            className={sparkTw(block, "text", `text-xs font-bold uppercase tracking-[0.28em] ${mediaStyle.eyebrow}`)}
                            onSave={(value) => onUpdate({ eyebrow: value })}
                        />
                    </div>

                    <EditableText
                        value={data.heading} cosmicType="h1"
                        className={sparkTw(block, "text_2", `mt-7 block w-full text-4xl font-semibold leading-[.98] tracking-[-.045em] sm:text-5xl lg:text-6xl xl:text-7xl ${mediaStyle.heading}`)}
                        onSave={(value) => onUpdate({ heading: value })}
                    />

                    <EditableText
                        value={data.text}
                        isTextArea
                        className={sparkTw(block, "text_3", `mt-7 block w-full text-base leading-8 sm:text-lg ${mediaStyle.body} ${data.contentAlign === "center" ? "mx-auto max-w-2xl" : "max-w-2xl"}`)}
                        onSave={(value) => onUpdate({ text: value })}
                    />

                    <div className={sparkTw(block, "wrapper_6", `mt-10 flex w-full flex-col gap-3 sm:w-auto sm:flex-row ${data.contentAlign === "center" ? "justify-center" : data.contentAlign === "right" ? "justify-end" : "justify-start"}`)}>
                        <EditableButton
                            label={data.primary_label}
                            url={data.primary_url}
                            className={sparkTw(block, "button", `inline-flex min-h-[54px] items-center justify-center rounded-full px-8 font-bold transition hover:-translate-y-0.5 ${primaryTheme.bg} !text-white`)}
                            onSave={(label, url) => onUpdate({ primary_label: label, primary_url: url })}
                        />
                        <EditableButton
                            label={data.secondary_label}
                            url={data.secondary_url}
                            className={sparkTw(block, "button_2", `inline-flex min-h-[54px] items-center justify-center rounded-full border px-8 font-bold backdrop-blur-md transition ${mediaStyle.secondary}`)}
                            onSave={(label, url) => onUpdate({ secondary_label: label, secondary_url: url })}
                        />
                    </div>
                </div>
            </div>

            <div className={sparkTw(block, "wrapper_7", `pointer-events-none absolute bottom-7 left-1/2 z-20 hidden -translate-x-1/2 flex-col items-center gap-3 sm:flex ${mediaStyle.scroll}`)}>
                <span className={sparkTw(block, "label_2", "text-[10px] font-bold uppercase tracking-[0.32em]")}>{data.scroll_label}</span>
                <span className={sparkTw(block, "label_3", `relative h-10 w-px overflow-hidden ${mediaStyle.scrollLine}`)}>
                    <span className={sparkTw(block, "label_4", `absolute left-0 top-0 h-4 w-px animate-bounce ${mediaStyle.scrollDot}`)} />
                </span>
            </div>

        </section>
    );
}
