import { usePage } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";

import { EditableButton } from "../Shared/EditableButton";
import { EditableImage } from "../Shared/EditableImage";
import { EditableText } from "../Shared/EditableText";
import { colorFamilies } from "../../../../theme/colorFamilies";

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
    const [offset, setOffset] = useState(0);
    const data = { ...HeroParallaxSchema.defaults, ...block };
    const { props } = usePage();
    const websiteId = props.page?.website_id || props.website?.id;
    const primaryTheme = colorFamilies[globalTheme.primary];

    useEffect(() => {
        let frame = null;

        const updateParallax = () => {
            frame = null;
            const section = sectionRef.current;
            if (!section) return;

            const rect = section.getBoundingClientRect();
            const viewport = window.innerHeight || 1;
            if (rect.bottom < 0 || rect.top > viewport) return;

            const progress = (viewport - rect.top) / (viewport + rect.height);
            const centered = progress - 0.5;
            setOffset(centered * Number(data.parallaxSpeed || 24));
        };

        const onScroll = () => {
            if (frame === null) frame = window.requestAnimationFrame(updateParallax);
        };

        updateParallax();
        window.addEventListener("scroll", onScroll, { passive: true });
        window.addEventListener("resize", onScroll);

        return () => {
            window.removeEventListener("scroll", onScroll);
            window.removeEventListener("resize", onScroll);
            if (frame !== null) window.cancelAnimationFrame(frame);
        };
    }, [data.parallaxSpeed]);

    const alignment = {
        left: "items-start text-left",
        center: "items-center text-center",
        right: "items-end text-right",
    };

    const contentWidth = data.contentAlign === "center" ? "max-w-4xl" : "max-w-3xl";
    const heroHeight = data.height === "large" ? "min-h-[720px]" : "min-h-[88svh] lg:min-h-screen";

    return (
        <section ref={sectionRef} className={`relative isolate flex overflow-hidden ${heroHeight}`}>
            <div
                className="absolute -inset-y-[12%] inset-x-0 z-0 will-change-transform"
                style={{ transform: `translate3d(0, ${offset}px, 0) scale(1.08)` }}
            >
                <EditableImage
                    ref={imageRef}
                    websiteId={websiteId}
                    blockIndex={blockIndex}
                    src={data.image_url}
                    showOverlay={false}
                    isBackground
                    className="absolute inset-0 h-full w-full overflow-hidden"
                    onSave={(value) => onUpdate({ image_url: value })}
                />
            </div>

            <div className="absolute inset-0 z-10 bg-slate-950" style={{ opacity: Number(data.overlayOpacity || 64) / 100 }} />
            <div className={`absolute inset-0 z-10 bg-gradient-to-t from-slate-950/85 via-slate-950/20 to-slate-950/25 ${data.contentAlign === "right" ? "bg-gradient-to-l" : data.contentAlign === "left" ? "bg-gradient-to-r" : ""}`} />

            <div className={`relative z-20 mx-auto flex w-full max-w-7xl flex-col justify-center px-6 py-24 sm:px-[8%] lg:py-32 ${alignment[data.contentAlign] || alignment.left}`}>
                <div className={contentWidth}>
                    <div className="inline-flex items-center gap-3 rounded-full border border-white/20 bg-white/10 px-4 py-2 backdrop-blur-md">
                        <span className={`h-2 w-2 rounded-full ${primaryTheme.bg}`} />
                        <EditableText
                            value={data.eyebrow}
                            className="text-xs font-bold uppercase tracking-[0.28em] text-white/85"
                            onSave={(value) => onUpdate({ eyebrow: value })}
                        />
                    </div>

                    <EditableText
                        value={data.heading}
                        className="mt-7 text-5xl font-semibold leading-[0.96] tracking-[-0.045em] text-white sm:text-6xl md:text-7xl lg:text-[6.5rem]"
                        onSave={(value) => onUpdate({ heading: value })}
                    />

                    <EditableText
                        value={data.text}
                        isTextArea
                        className={`mt-7 text-base leading-8 text-white/75 sm:text-lg ${data.contentAlign === "center" ? "mx-auto max-w-2xl" : "max-w-2xl"}`}
                        onSave={(value) => onUpdate({ text: value })}
                    />

                    <div className={`mt-10 flex w-full flex-col gap-3 sm:w-auto sm:flex-row ${data.contentAlign === "center" ? "justify-center" : data.contentAlign === "right" ? "justify-end" : "justify-start"}`}>
                        <EditableButton
                            label={data.primary_label}
                            url={data.primary_url}
                            className={`inline-flex min-h-[54px] items-center justify-center rounded-full px-8 font-bold transition hover:-translate-y-0.5 ${primaryTheme.bg} ${primaryTheme.text}`}
                            onSave={(label, url) => onUpdate({ primary_label: label, primary_url: url })}
                        />
                        <EditableButton
                            label={data.secondary_label}
                            url={data.secondary_url}
                            className="inline-flex min-h-[54px] items-center justify-center rounded-full border border-white/30 bg-white/10 px-8 font-bold text-white backdrop-blur-md transition hover:bg-white/20"
                            onSave={(label, url) => onUpdate({ secondary_label: label, secondary_url: url })}
                        />
                    </div>
                </div>
            </div>

            <div className="pointer-events-none absolute bottom-7 left-1/2 z-20 hidden -translate-x-1/2 flex-col items-center gap-3 text-white/65 sm:flex">
                <span className="text-[10px] font-bold uppercase tracking-[0.32em]">{data.scroll_label}</span>
                <span className="relative h-10 w-px overflow-hidden bg-white/25">
                    <span className="absolute left-0 top-0 h-4 w-px animate-bounce bg-white" />
                </span>
            </div>

            <button
                type="button"
                aria-label="Edit hero parallax background image"
                className="absolute inset-0 z-[15] cursor-pointer bg-transparent"
                onClick={() => imageRef.current?.openEditor()}
            />
        </section>
    );
}
