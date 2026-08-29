import { useState } from "react";
import { usePage } from "@inertiajs/react";
import { EditableButton } from "../Shared/EditableButton";
import { EditableImage } from "../Shared/EditableImage";
import { EditableText } from "../Shared/EditableText";
import { EditableVideoSource, getVideoEmbedUrl } from "../Shared/EditableVideoSource";
import { getEffectiveTheme } from "../../../../theme/Theme";
import { getHeroThemeState, resolveHeroThemeRequest } from "../../../../theme/heroTheme";
import { colorFamilies } from "../../../../theme/colorFamilies";
import { resolveMediaOverlay, effectiveMediaOverlayOpacity } from "../../../../theme/mediaOverlay";
import { sparkTw } from "../Shared/sparkTailwindRuntime";

export const HeroVideoPremiumSchema = {
    type: "hero_video_premium",
    title: "Video Hero Premium",
    category: "Hero",
    purpose: "Create a cinematic first impression with background motion, premium overlays, and a clear scroll cue.",
    description: "A Pro-only full-screen video hero with editable background video, poster fallback, gradient overlays, CTA actions, a media badge, and scroll indicator.",
    tags: ["hero", "premium", "video", "cinematic", "fullscreen", "pro", "motion"],
    defaults: {
        eyebrow: "A STORY IN MOTION",
        heading: "Make the first few seconds impossible to forget.",
        text: "Use cinematic movement, focused copy, and one clear next step to introduce your brand with confidence.",
        primary_label: "Start the experience",
        primary_url: "#",
        secondary_label: "Watch the story",
        secondary_url: "#",
        media_badge: "Cinematic brand experience",
        scroll_label: "Scroll to explore",
        video_url: "/storage/cms-videos/hero-placeholder.mp4",
        poster_image_url: "/storage/cms-images/background/background-1.avif",
    },
};

export function HeroVideoPremiumBlock({ block, blockIndex, onUpdate, globalTheme }) {
    const { requestedTheme, theme, isPrimary } = getHeroThemeState(block, globalTheme);
    const primaryTheme = colorFamilies[globalTheme?.primary] || colorFamilies.emerald;
    const data = { ...HeroVideoPremiumSchema.defaults, ...block };
    const { props } = usePage();
    const websiteId = props.page?.website_id || props.website?.id;
    const [isVideoEditorOpen, setIsVideoEditorOpen] = useState(false);
    const embeddedVideoUrl = getVideoEmbedUrl(data.video_url);
    const resolvedTheme = requestedTheme || "surface";
    const mediaOverlay = resolveMediaOverlay(globalTheme, resolvedTheme);
    const isLightMediaTheme = mediaOverlay.isLight;
    const primaryButton = isPrimary
        ? "bg-white !text-slate-950"
        : `${primaryTheme.bg} !text-white`;
    const mediaStyle = isLightMediaTheme
        ? {
            overlayBase: "bg-white/90",
            overlayX: "bg-gradient-to-r from-white/100 via-white/96 to-white/82",
            overlayY: "bg-gradient-to-t from-white/94 via-white/36 to-white/78",
            topBorder: "border-slate-900/15",
            eyebrow: "!text-slate-700",
            badge: "border-slate-900/15 bg-white/65 !text-slate-900",
            heading: "!text-slate-950",
            body: "!text-slate-700",
            secondary: "border-slate-900/20 bg-white/78 !text-slate-950 hover:bg-white/95",
            footerBorder: "border-slate-900/15",
            scroll: "!text-slate-700",
            edit: "border-slate-900/15 bg-white/60 !text-slate-800 hover:bg-white/85 hover:!text-slate-950",
            posterCard: "border-slate-900/15 bg-white/55",
        }
        : {
            overlayBase: "",
            overlayX: "bg-gradient-to-r from-slate-950/62 via-slate-950/34 to-slate-950/10",
            overlayY: "bg-gradient-to-t from-slate-950/52 via-transparent to-slate-950/16",
            topBorder: "border-white/25",
            eyebrow: "!text-white/80",
            badge: "border-white/30 bg-white/10 !text-white",
            heading: "!text-white",
            body: "!text-white/75",
            secondary: "border-white/45 bg-white/5 !text-white hover:bg-white/12",
            footerBorder: "border-white/25",
            scroll: "!text-white/75",
            edit: "border-white/25 bg-slate-950/35 !text-white/85 hover:bg-slate-950/55 hover:!text-white",
            posterCard: "border-white/20 bg-slate-950/35",
        };

    const openVideoEditor = (event) => {
        event.preventDefault();
        event.stopPropagation();
        setIsVideoEditorOpen(true);
    };

    return (
        <>
            <section data-cosmic-media-banner="true" className={sparkTw(block, "section", `relative isolate cursor-pointer overflow-hidden ${theme.bg}`)} style={{minHeight:"var(--cosmic-hero-fold-height, calc(100svh - 80px))"}}>
                <div className={sparkTw(block, "wrapper", "absolute inset-0")}>
                    <img src={data.poster_image_url} alt="" aria-hidden="true" className={sparkTw(block, "image", "absolute inset-0 h-full w-full object-cover sm:hidden")} />
                    {embeddedVideoUrl ? (
                        <iframe
                            src={embeddedVideoUrl}
                            title="Premium background video"
                            allow="autoplay; fullscreen; picture-in-picture"
                            className={sparkTw(block, "wrapper_2", "pointer-events-none absolute left-1/2 top-1/2 hidden h-[56.25vw] min-h-full w-[177.78vh] min-w-full -translate-x-1/2 -translate-y-1/2 border-0 sm:block")}
                        />
                    ) : (
                        <video className={sparkTw(block, "video", "hidden h-full w-full object-cover sm:block")} autoPlay muted loop playsInline preload="metadata" poster={data.poster_image_url}>
                            <source src={data.video_url} type="video/mp4" />
                        </video>
                    )}
                    <div
                        className={sparkTw(block, "wrapper_3", `pointer-events-none absolute inset-0 ${mediaStyle.overlayBase}`)}
                        style={isLightMediaTheme ? undefined : { backgroundColor: mediaOverlay.overlayColor, opacity: 0.48 }}
                    />
                    <div className={sparkTw(block, "wrapper_4", `pointer-events-none absolute inset-0 ${mediaStyle.overlayX}`)} />
                    <div className={sparkTw(block, "wrapper_5", `pointer-events-none absolute inset-0 ${mediaStyle.overlayY}`)} />
                </div>

                <button type="button" aria-label="Edit background video" onPointerDown={openVideoEditor} className={sparkTw(block, "button", "absolute inset-0 z-[5] cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-violet-300")} />

                <div className={sparkTw(block, "wrapper_6", "pointer-events-none relative z-10 mx-auto flex max-w-7xl flex-col justify-between px-6 py-8 sm:px-10 sm:py-10 lg:px-14 lg:py-12")} style={{minHeight:"var(--cosmic-hero-fold-height, calc(100svh - 80px))"}}>
                    <div className={sparkTw(block, "wrapper_7", `pointer-events-auto flex items-center justify-between border-b pb-5 ${mediaStyle.topBorder}`)}>
                        <EditableText value={data.eyebrow} className={sparkTw(block, "text", `text-[11px] font-bold uppercase tracking-[.34em] ${mediaStyle.eyebrow}`)} onSave={(eyebrow) => onUpdate({ eyebrow })} />
                        <EditableText value={data.media_badge} cosmicType="badge" className={sparkTw(block, "text_2", `rounded-full border px-4 py-2 text-[11px] font-semibold backdrop-blur-md ${mediaStyle.badge}`)} onSave={(media_badge) => onUpdate({ media_badge })} />
                    </div>

                    <div className={sparkTw(block, "wrapper_8", "pointer-events-auto max-w-4xl py-0")}>
                        <EditableText value={data.heading} cosmicType="h1" className={sparkTw(block, "text_3", `block max-w-4xl text-4xl font-semibold leading-[.98] tracking-[-.045em] sm:text-5xl lg:text-6xl xl:text-7xl ${mediaStyle.heading}`)} onSave={(heading) => onUpdate({ heading })} />
                        <EditableText value={data.text} isTextArea className={sparkTw(block, "text_4", `mt-7 block max-w-2xl text-base leading-7 sm:text-lg sm:leading-8 ${mediaStyle.body}`)} onSave={(text) => onUpdate({ text })} />
                        <div className={sparkTw(block, "wrapper_9", "mt-9 flex flex-col gap-3 sm:flex-row")}>
                            <EditableButton label={data.primary_label} url={data.primary_url} className={sparkTw(block, "button_2", `inline-flex min-h-[52px] items-center justify-center rounded-full px-7 font-bold ${primaryButton}`)} onSave={(primary_label, primary_url) => onUpdate({ primary_label, primary_url })} />
                            <EditableButton label={data.secondary_label} url={data.secondary_url} className={sparkTw(block, "button_3", `inline-flex min-h-[52px] items-center justify-center rounded-full border px-7 font-bold backdrop-blur-sm transition ${mediaStyle.secondary}`)} onSave={(secondary_label, secondary_url) => onUpdate({ secondary_label, secondary_url })} />
                        </div>
                    </div>

                    <div className={sparkTw(block, "wrapper_10", `pointer-events-auto flex items-end justify-between gap-5 border-t pt-5 ${mediaStyle.footerBorder}`)}>
                        <div className={sparkTw(block, "wrapper_11", "flex items-center gap-4")}>
                            <EditableText value={data.scroll_label} className={sparkTw(block, "text_5", `text-xs font-semibold uppercase tracking-[.2em] ${mediaStyle.scroll}`)} onSave={(scroll_label) => onUpdate({ scroll_label })} />
                        </div>
                        <div data-editable-media data-cosmic-no-luna-hover="true" className={sparkTw(block, "wrapper_12", `hidden w-40 overflow-hidden rounded-xl border shadow-xl sm:block ${mediaStyle.posterCard}`)}>
                            <EditableImage websiteId={websiteId} blockIndex={blockIndex} src={data.poster_image_url} className={sparkTw(block, "image_2", "aspect-video w-full object-cover opacity-85")} onSave={(poster_image_url) => onUpdate({ poster_image_url })} />
                        </div>
                    </div>
                </div>
            </section>

            <EditableVideoSource value={data.video_url} posterImageUrl={data.poster_image_url} title="Edit premium background video" isOpen={isVideoEditorOpen} onClose={() => setIsVideoEditorOpen(false)} onSave={(video_url) => onUpdate({ video_url })} />
        </>
    );
}
