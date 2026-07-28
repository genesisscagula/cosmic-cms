import { usePage } from "@inertiajs/react";
import { useRef } from "react";

import { EditableButton } from "../Shared/EditableButton";
import { EditableImage } from "../Shared/EditableImage";
import { EditableText } from "../Shared/EditableText";
import { colorFamilies } from "../../../../theme/colorFamilies";

export const ImageCtaBannerSchema = {
    type: "image_cta_banner",
    title: "Image CTA Banner",
    category: "Call to action",
    purpose: "Add a compact image-led call to action between page sections.",
    description: "A shorter background image banner with editable copy and one or two actions.",
    tags: ["cta", "banner", "image", "conversion", "section"],
    defaults: {
        eyebrow: "READY WHEN YOU ARE",
        heading: "Let’s make your next step simple.",
        text: "Talk with our team and get a clear plan for moving forward.",
        primary_label: "Get started",
        primary_url: "#",
        secondary_label: "Learn more",
        secondary_url: "#",
        image_url: "/storage/cms-images/background/background-1.avif",
        overlayOpacity: 76,
    },
};

export function ImageCtaBannerBlock({ block, blockIndex, onUpdate, globalTheme }) {
    const imageRef = useRef(null);
    const primaryTheme = colorFamilies[globalTheme?.primary] || colorFamilies.emerald;
    const data = { ...ImageCtaBannerSchema.defaults, ...block };
    const { props } = usePage();
    const websiteId = props.page?.website_id || props.website?.id;
    const overlayOpacity = Math.max(0, Math.min(100, Number(data.overlayOpacity) || 76));

    return (
        <section className="relative flex min-h-[420px] overflow-hidden sm:min-h-[460px] lg:min-h-[500px]">
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
                className={`absolute inset-0 z-10 ${primaryTheme.bg}`}
                style={{ opacity: overlayOpacity / 100 }}
                onClick={() => imageRef.current?.openEditor()}
            />
            <div className="pointer-events-none absolute inset-0 z-10 bg-gradient-to-r from-slate-950/65 via-slate-950/25 to-slate-950/15" />

            <div className="relative z-20 mx-auto flex w-full max-w-7xl items-center justify-center px-7 py-16 text-center sm:px-10 sm:py-20">
                <div className="max-w-3xl">
                    <EditableText
                        value={data.eyebrow}
                        className="block text-xs font-semibold uppercase tracking-[0.3em] text-white/75"
                        onSave={(eyebrow) => onUpdate({ eyebrow })}
                    />
                    <EditableText
                        value={data.heading}
                        className="mt-4 block text-4xl font-bold leading-[1.05] tracking-tight text-white sm:text-5xl lg:text-[3.75rem]"
                        onSave={(heading) => onUpdate({ heading })}
                    />
                    <EditableText
                        value={data.text}
                        isTextArea
                        className="mx-auto mt-5 block max-w-2xl text-base leading-7 text-white/85 sm:text-lg sm:leading-8"
                        onSave={(text) => onUpdate({ text })}
                    />
                    <div className="mt-7 flex flex-col justify-center gap-3 sm:flex-row sm:items-center">
                        <EditableButton
                            label={data.primary_label}
                            url={data.primary_url}
                            className="inline-flex min-h-[48px] items-center justify-center rounded-full bg-white px-7 font-bold text-slate-950"
                            onSave={(primary_label, primary_url) => onUpdate({ primary_label, primary_url })}
                        />
                        <EditableButton
                            label={data.secondary_label}
                            url={data.secondary_url}
                            className="inline-flex min-h-[48px] items-center justify-center rounded-full border border-white/45 bg-white/5 px-7 font-bold text-white transition hover:bg-white/10"
                            onSave={(secondary_label, secondary_url) => onUpdate({ secondary_label, secondary_url })}
                        />
                    </div>
                </div>
            </div>
        </section>
    );
}
