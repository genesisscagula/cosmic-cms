import { useRef } from "react";
import { usePage } from "@inertiajs/react";
import { EditableButton } from "../Shared/EditableButton";
import { EditableImage } from "../Shared/EditableImage";
import { EditableText } from "../Shared/EditableText";
import { getEffectiveTheme } from "../../../../theme/Theme";
import { getHeroThemeState, resolveHeroThemeRequest } from "../../../../theme/heroTheme";
import { colorFamilies } from "../../../../theme/colorFamilies";
import { resolveMediaOverlay } from "../../../../theme/mediaOverlay";
import { sparkTw } from "../Shared/sparkTailwindRuntime";

export const HeroLuxuryFullscreenSchema = {
    type: "hero_luxury_fullscreen",
    title: "Luxury Fullscreen Hero",
    category: "Hero",
    purpose: "Open a luxury website with cinematic imagery, restrained copy, and oversized typography.",
    description: "A Pro-only full-screen image hero with minimal controls, premium spacing, and elegant proof details.",
    tags: ["hero", "premium", "luxury", "fullscreen", "minimal", "pro", "image"],
    defaults: {
        eyebrow: "THE ART OF ARRIVAL",
        heading: "Quiet confidence, made unforgettable.",
        text: "A refined opening statement for brands defined by craft, place, and exceptional attention to detail.",
        primary_label: "Discover the collection",
        primary_url: "#",
        secondary_label: "Our story",
        secondary_url: "#",
        location_label: "Crafted in exceptional detail",
        edition_label: "Private Edition 01",
        image_url: "/storage/cms-images/background/background-1.avif",
    },
};

export function HeroLuxuryFullscreenBlock({ block, blockIndex, onUpdate, globalTheme }) {
    const { requestedTheme, theme } = getHeroThemeState(block, globalTheme);
    const normalizedGlobalTheme = typeof globalTheme === 'string' ? { primary: globalTheme } : (globalTheme || {});
    const primaryTheme = colorFamilies[normalizedGlobalTheme.primary] || colorFamilies.midnight;
    const data = { ...HeroLuxuryFullscreenSchema.defaults, ...block };
    const { props } = usePage();
    const websiteId = props.page?.website_id || props.website?.id;
    const imageRef = useRef(null);
    const isPrimary = requestedTheme === "primary";
    const mediaOverlay = resolveMediaOverlay(globalTheme, resolveHeroThemeRequest(block, globalTheme));
    const isLightMediaTheme = mediaOverlay.isLight;
    const overlayColor = mediaOverlay.overlayColor;
    const primaryButton = isLightMediaTheme
        ? `${primaryTheme.bg} !text-white`
        : (isPrimary ? "bg-white !text-slate-950" : `${primaryTheme.bg} ${primaryTheme.text}`);
    const copy = isLightMediaTheme
        ? {
            border: "border-slate-900/15",
            eyebrow: "!text-slate-700",
            edition: "!text-slate-600",
            heading: "!text-slate-950",
            body: "!text-slate-700",
            secondary: "border-slate-900/20 bg-white/72 !text-slate-950 backdrop-blur-sm",
            location: "!text-slate-700",
            divider: "bg-slate-900/25",
        }
        : {
            border: "border-white/25",
            eyebrow: "!text-white/80",
            edition: "!text-white/70",
            heading: "!text-white",
            body: "!text-white/75",
            secondary: "border-white/45 bg-white/5 !text-white backdrop-blur-sm",
            location: "!text-white/75",
            divider: "bg-white/35",
        };

    const handleImageEdit = (event) => {
        if (event.target.closest("button, a, input, textarea, select, label, [contenteditable=\'true\'], [role=\'button\'], [data-cosmic-edit-control]")) return;
        imageRef.current?.openEditor();
    };

    return (
        <section onClick={handleImageEdit} data-cosmic-media-banner="true" className={sparkTw(block, "section", `relative overflow-hidden ${theme.bg}`)} style={{minHeight:"var(--cosmic-hero-fold-height, calc(100svh - 80px))"}}>
            <EditableImage ref={imageRef} websiteId={websiteId} blockIndex={blockIndex} src={data.image_url} isBackground className={sparkTw(block, "image", "absolute inset-0 h-full w-full object-cover")} onSave={(image_url)=>onUpdate({image_url})} />
            <div
                className={sparkTw(block, "wrapper", "pointer-events-none absolute inset-0")}
                style={{ backgroundColor: overlayColor, opacity: isLightMediaTheme ? 0.96 : 0.52 }}
            />
            <div className={sparkTw(block, "wrapper_2", `pointer-events-none absolute inset-0 ${isLightMediaTheme ? "bg-gradient-to-r from-white/72 via-white/48 to-white/24" : "bg-gradient-to-r from-slate-950/30 via-transparent to-transparent"}`)} />
            <div className={sparkTw(block, "wrapper_3", `pointer-events-none absolute inset-0 ${isLightMediaTheme ? "bg-gradient-to-t from-white/56 via-white/20 to-white/24" : "bg-gradient-to-t from-slate-950/34 via-transparent to-slate-950/8"}`)} />
            <div className={sparkTw(block, "wrapper_4", "relative mx-auto flex max-w-7xl flex-col justify-between px-6 py-0 sm:px-10 lg:px-14")} style={{minHeight:"var(--cosmic-hero-fold-height, calc(100svh - 80px))"}}>
                <div className={sparkTw(block, "wrapper_5", `flex items-center justify-between border-b pb-5 ${copy.border}`)}>
                    <EditableText value={data.eyebrow} className={sparkTw(block, "text", `text-[11px] font-bold uppercase tracking-[.34em] ${copy.eyebrow}`)} onSave={(eyebrow)=>onUpdate({eyebrow})}/>
                    <EditableText value={data.edition_label} className={sparkTw(block, "text_2", `text-xs font-medium ${copy.edition}`)} onSave={(edition_label)=>onUpdate({edition_label})}/>
                </div>
                <div className={sparkTw(block, "wrapper_6", "max-w-5xl py-0")}>
                    <EditableText value={data.heading} cosmicType="h1" className={sparkTw(block, "text_3", `block max-w-5xl text-4xl font-medium leading-[.96] tracking-[-.05em] sm:text-5xl lg:text-6xl xl:text-7xl ${copy.heading}`)} onSave={(heading)=>onUpdate({heading})}/>
                    <EditableText value={data.text} isTextArea className={sparkTw(block, "text_4", `mt-7 block max-w-xl text-base leading-7 sm:text-lg sm:leading-8 ${copy.body}`)} onSave={(text)=>onUpdate({text})}/>
                    <div className={sparkTw(block, "wrapper_7", "mt-9 flex flex-col gap-3 sm:flex-row")}>
                        <EditableButton label={data.primary_label} url={data.primary_url} className={sparkTw(block, "button", `inline-flex min-h-[52px] items-center justify-center rounded-full px-7 font-bold ${primaryButton}`)} onSave={(primary_label,primary_url)=>onUpdate({primary_label,primary_url})}/>
                        <EditableButton label={data.secondary_label} url={data.secondary_url} className={sparkTw(block, "button_2", `inline-flex min-h-[52px] items-center justify-center rounded-full border px-7 font-bold ${copy.secondary}`)} onSave={(secondary_label,secondary_url)=>onUpdate({secondary_label,secondary_url})}/>
                    </div>
                </div>
                <div className={sparkTw(block, "wrapper_8", `flex items-end justify-between border-t pt-5 ${copy.border}`)}>
                    <EditableText value={data.location_label} className={sparkTw(block, "text_5", `text-xs font-semibold uppercase tracking-[.2em] ${copy.location}`)} onSave={(location_label)=>onUpdate({location_label})}/>
                    <span className={sparkTw(block, "label", `h-10 w-px ${copy.divider}`)} />
                </div>
            </div>
        </section>
    );
}
