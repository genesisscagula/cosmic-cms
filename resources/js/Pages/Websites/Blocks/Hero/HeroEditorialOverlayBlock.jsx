import { usePage } from "@inertiajs/react";
import { useRef } from "react";
import { EditableButton } from "../Shared/EditableButton";
import { EditableImage } from "../Shared/EditableImage";
import { EditableText } from "../Shared/EditableText";
import { getEffectiveTheme } from "../../../../theme/Theme";
import { colorFamilies } from "../../../../theme/colorFamilies";

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
    const imageRef = useRef(null);
    const theme = getEffectiveTheme(block.resolvedTheme, globalTheme);
    const primaryTheme = colorFamilies[globalTheme.primary];
    const { props } = usePage();
    const websiteId = props.page?.website_id || props.website?.id;
    const data = { ...HeroEditorialOverlaySchema.defaults, ...block };
    const height = {
        medium: "min-h-[520px]",
        large: "min-h-[650px]",
        screen: "min-h-[72svh] sm:min-h-[80vh] md:min-h-[85vh] lg:min-h-[90vh]",
    }[data.height] || "min-h-[650px]";

    return (
        <section className={`relative flex overflow-hidden ${height}`}>
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
                className="absolute inset-0 z-10 bg-slate-950"
                style={{ opacity: Math.max(0, Math.min(100, Number(data.overlayOpacity) || 72)) / 100 }}
                onClick={() => imageRef.current?.openEditor()}
            />
            <div className="absolute inset-0 z-10 bg-gradient-to-r from-slate-950/80 via-slate-950/40 to-transparent" />

            <div className="relative z-20 mx-auto flex w-full max-w-7xl items-center px-6 py-20 sm:px-[8%] sm:py-24">
                <div className="max-w-3xl">
                    <EditableText
                        value={data.tagline}
                        className="block text-xs font-semibold uppercase tracking-[0.3em] text-white/75"
                        onSave={(tagline) => onUpdate({ tagline })}
                    />
                    <EditableText
                        value={data.heading}
                        className="mt-5 block text-5xl font-black leading-[1.03] tracking-tight text-white sm:text-6xl md:text-7xl lg:text-8xl"
                        onSave={(heading) => onUpdate({ heading })}
                    />
                    <EditableText
                        value={data.text}
                        isTextArea
                        className="mt-6 block max-w-2xl text-base leading-7 text-white/80 sm:text-lg sm:leading-8"
                        onSave={(text) => onUpdate({ text })}
                    />
                    <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                        <EditableButton
                            label={data.primary_label}
                            url={data.primary_url}
                            className={`inline-flex min-h-[50px] items-center justify-center rounded-full px-7 font-bold ${primaryTheme.bg} ${primaryTheme.text}`}
                            onSave={(primary_label, primary_url) => onUpdate({ primary_label, primary_url })}
                        />
                        <EditableButton
                            label={data.secondary_label}
                            url={data.secondary_url}
                            className="inline-flex min-h-[50px] items-center justify-center rounded-full border border-white/40 bg-white/5 px-7 font-bold text-white transition hover:bg-white/10"
                            onSave={(secondary_label, secondary_url) => onUpdate({ secondary_label, secondary_url })}
                        />
                    </div>
                </div>
            </div>
            <div className={`pointer-events-none absolute inset-x-0 bottom-0 z-20 h-px ${theme.border}`} />
        </section>
    );
}
