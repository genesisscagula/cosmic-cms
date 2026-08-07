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
    const normalizedGlobalTheme = typeof globalTheme === 'string' ? { primary: globalTheme } : (globalTheme || {});
    const primaryTheme = colorFamilies[normalizedGlobalTheme.primary] || colorFamilies.midnight;
    const isLightMediaTheme = ["white", "surface", "stone"].includes(block.resolvedTheme);
    const overlayColor = isLightMediaTheme ? '#ffffff' : '#020617';
    const mediaStyle = isLightMediaTheme
        ? {
            overlay: "bg-white",
            gradient: "from-white/98 via-white/84 to-white/62",
            eyebrow: "text-slate-700",
            heading: "text-slate-950",
            body: "text-slate-700",
            primary: `${primaryTheme.bg} text-white`,
            secondary: "border-slate-900/20 bg-white/50 text-slate-950 hover:bg-white/75",
        }
        : {
            overlay: "bg-slate-950",
            gradient: "from-slate-950/65 via-slate-950/25 to-slate-950/15",
            eyebrow: "text-white/75",
            heading: "text-white",
            body: "text-white/85",
            primary: "bg-white text-slate-950",
            secondary: "border-white/45 bg-white/5 text-white hover:bg-white/10",
        };
    const data = { ...ImageCtaBannerSchema.defaults, ...block };
    const { props } = usePage();
    const websiteId = props.page?.website_id || props.website?.id;
    const configuredOverlayOpacity = Math.max(0, Math.min(100, Number(data.overlayOpacity) || 76));
    const overlayOpacity = isLightMediaTheme ? Math.max(82, configuredOverlayOpacity) : configuredOverlayOpacity;

    const handleSectionImageEdit = (event) => {
        // Keep copy, links, and Builder controls independently editable.
        // Clicking the remaining banner canvas opens the background image editor.
        if (event.target.closest("button, a, input, textarea, select, label, [contenteditable='true'], [role='button'], [data-cosmic-edit-control]")) {
            return;
        }

        imageRef.current?.openEditor();
    };

    return (
        <section
            className="relative flex min-h-[420px] cursor-pointer overflow-hidden sm:min-h-[460px] lg:min-h-[500px]"
            onClick={handleSectionImageEdit}
        >
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
                style={{ backgroundColor: overlayColor, opacity: overlayOpacity / 100 }}
            />
            <div className={`pointer-events-none absolute inset-0 z-10 bg-gradient-to-r ${mediaStyle.gradient}`} />

            <div
                className="relative z-20 mx-auto flex w-full max-w-7xl items-center justify-center px-7 py-16 text-center sm:px-10 sm:py-20"
            >
                <div className="max-w-3xl">
                    <EditableText
                        value={data.eyebrow}
                        className={`block text-xs font-semibold uppercase tracking-[0.3em] ${mediaStyle.eyebrow}`}
                        onSave={(eyebrow) => onUpdate({ eyebrow })}
                    />
                    <EditableText
                        value={data.heading}
                        className={`mt-4 block text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] ${mediaStyle.heading}`}
                        onSave={(heading) => onUpdate({ heading })}
                    />
                    <EditableText
                        value={data.text}
                        isTextArea
                        className={`mx-auto mt-5 block max-w-2xl text-base leading-7 sm:text-lg sm:leading-8 ${mediaStyle.body}`}
                        onSave={(text) => onUpdate({ text })}
                    />
                    <div className="mt-7 flex flex-col justify-center gap-3 sm:flex-row sm:items-center">
                        <EditableButton
                            label={data.primary_label}
                            url={data.primary_url}
                            className={`inline-flex min-h-[48px] items-center justify-center rounded-full px-7 font-bold ${mediaStyle.primary}`}
                            onSave={(primary_label, primary_url) => onUpdate({ primary_label, primary_url })}
                        />
                        <EditableButton
                            label={data.secondary_label}
                            url={data.secondary_url}
                            className={`inline-flex min-h-[48px] items-center justify-center rounded-full border px-7 font-bold transition ${mediaStyle.secondary}`}
                            onSave={(secondary_label, secondary_url) => onUpdate({ secondary_label, secondary_url })}
                        />
                    </div>
                </div>
            </div>
        </section>
    );
}
