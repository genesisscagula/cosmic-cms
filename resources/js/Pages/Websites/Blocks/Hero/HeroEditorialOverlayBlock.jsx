import { usePage } from "@inertiajs/react";
import { useRef } from "react";
import { EditableButton } from "../Shared/EditableButton";
import { EditableImage } from "../Shared/EditableImage";
import { EditableText } from "../Shared/EditableText";
import { getEffectiveTheme } from "../../../../theme/Theme";
import { colorFamilies } from "../../../../theme/colorFamilies";
import { resolveMediaOverlay, effectiveMediaOverlayOpacity } from "../../../../theme/mediaOverlay";

export const HeroEditorialOverlaySchema = {
    type: "hero_editorial_overlay",
    title: "Hero Editorial Overlay",
    category: "Hero",
    purpose: "Create an image-led, editorial hero with two clear calls to action.",
    description: "A full-width background-image hero with left-aligned copy, a subtle dark overlay, and two editable actions.",
    tags: ["hero", "background image", "editorial", "cta", "landing"],
    defaults: {
        tagline: "Built for what comes next",
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
};

export function HeroEditorialOverlayBlock({ block, blockIndex, onUpdate, globalTheme }) {
    const normalizedGlobalTheme = typeof globalTheme === 'string' ? { primary: globalTheme } : (globalTheme || {});
    const primaryTheme = colorFamilies[normalizedGlobalTheme.primary] || colorFamilies.midnight;
    const imageRef = useRef(null);
    const theme = getEffectiveTheme(block.resolvedTheme, globalTheme);
    const mediaOverlay = resolveMediaOverlay(globalTheme, block.resolvedTheme);
    const isLightMediaTheme = mediaOverlay.isLight;
    const overlayColor = mediaOverlay.overlayColor;
    const mediaStyle = isLightMediaTheme
        ? {
            overlay: "bg-white",
            gradient: "from-white/99 via-white/92 to-white/76",
            tagline: "!text-slate-700",
            heading: "!text-slate-950",
            body: "!text-slate-700",
            secondary: "border-slate-900/20 bg-white/72 !text-slate-950 hover:bg-white/90",
        }
        : {
            overlay: "bg-slate-950",
            gradient: "from-slate-950/55 via-slate-950/22 to-transparent",
            tagline: "!text-white/75",
            heading: "!text-white",
            body: "!text-white/80",
            secondary: "border-white/40 bg-white/5 !text-white hover:bg-white/10",
        };
    const { props } = usePage();
    const websiteId = props.page?.website_id || props.website?.id;
    const data = { ...HeroEditorialOverlaySchema.defaults, ...block };
    const configuredOverlayOpacity = Math.max(0, Math.min(100, Number(data.overlayOpacity) || 72));
    const effectiveOverlayOpacity = effectiveMediaOverlayOpacity(configuredOverlayOpacity, {
        isLight: isLightMediaTheme,
        lightMinimum: 96,
    });
    const height = {
        medium: "min-h-[520px]",
        large: "min-h-[650px]",
        screen: "min-h-[72svh] sm:min-h-[80vh] md:min-h-[85vh] lg:min-h-[90vh]",
    }[data.height] || "min-h-[650px]";

    const handleSectionImageEdit = (event) => {
        if (event.target.closest("button, a, input, textarea, select, label, [contenteditable='true'], [role='button'], [data-cosmic-edit-control]")) {
            return;
        }

        imageRef.current?.openEditor();
    };

    return (
        <section data-cosmic-media-banner="true" className={`relative flex cursor-pointer overflow-hidden ${height}`} onClick={handleSectionImageEdit}>
            <EditableImage
                ref={imageRef}
                websiteId={websiteId}
                blockIndex={blockIndex}
                src={data.image_url}
                showOverlay={false}
                isBackground
                className="absolute inset-0 z-0 h-full w-full"
                onSave={(image_url) => onUpdate({ image_url })}
            />
            <div
                className="pointer-events-none absolute inset-0 z-10"
                style={{ backgroundColor: overlayColor, opacity: effectiveOverlayOpacity / 100 }}
            />
            <div className={`pointer-events-none absolute inset-0 z-10 bg-gradient-to-r ${mediaStyle.gradient}`} />

            <div className="relative z-20 mx-auto flex w-full max-w-7xl items-center px-7 py-20 sm:py-24">
                <div className="max-w-3xl">
                    <EditableText
                        value={data.tagline}
                        className={`block text-xs font-semibold uppercase tracking-[0.3em] ${mediaStyle.tagline}`}
                        onSave={(tagline) => onUpdate({ tagline })}
                    />
                    <EditableText
                        value={data.heading}
                        className={`mt-5 block text-5xl font-bold leading-[1.03] tracking-tight sm:text-6xl md:text-7xl lg:text-8xl ${mediaStyle.heading}`}
                        onSave={(heading) => onUpdate({ heading })}
                    />
                    <EditableText
                        value={data.text}
                        isTextArea
                        className={`mt-6 block max-w-2xl text-base leading-7 sm:text-lg sm:leading-8 ${mediaStyle.body}`}
                        onSave={(text) => onUpdate({ text })}
                    />
                    <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                        <EditableButton
                            label={data.primary_label}
                            url={data.primary_url}
                            className={`inline-flex min-h-[50px] items-center justify-center rounded-full px-7 font-bold ${primaryTheme.bg} !text-white`}
                            onSave={(primary_label, primary_url) => onUpdate({ primary_label, primary_url })}
                        />
                        <EditableButton
                            label={data.secondary_label}
                            url={data.secondary_url}
                            className={`inline-flex min-h-[50px] items-center justify-center rounded-full border px-7 font-bold transition ${mediaStyle.secondary}`}
                            onSave={(secondary_label, secondary_url) => onUpdate({ secondary_label, secondary_url })}
                        />
                    </div>
                </div>
            </div>
            <div className={`pointer-events-none absolute inset-x-0 bottom-0 z-20 h-px ${theme.border}`} />
        </section>
    );
}
