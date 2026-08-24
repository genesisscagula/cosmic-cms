import { usePage } from "@inertiajs/react";

import { EditableButton } from "../Shared/EditableButton";
import { EditableImage } from "../Shared/EditableImage";
import { EditableText } from "../Shared/EditableText";
import { getEffectiveTheme } from "../../../../theme/Theme";
import { getHeroThemeState, resolveHeroThemeRequest } from "../../../../theme/heroTheme";
import { colorFamilies } from "../../../../theme/colorFamilies";
import { sparkTw } from "../Shared/sparkTailwindRuntime";

export const HeroSplitImageSchema = {
    type: "hero_split_image",
    title: "Hero Split Image",
    category: "Hero",
    purpose: "Introduce a business with editorial copy beside a large, editable image.",
    description: "A flexible two-column hero with two actions and an optional trust line.",
    tags: ["hero", "split", "image", "cta", "landing"],
    defaults: {
        tagline: "BUILT FOR WHAT'S NEXT",
        heading: "Make a stronger first impression.",
        text: "Tell your story clearly, show what makes your business different, and guide visitors toward the next step.",
        primary_label: "Get started",
        primary_url: "#",
        secondary_label: "Learn more",
        secondary_url: "#",
        trust_line: "Trusted by customers who value quality work.",
        image_badge: "Serving your community",
        image_url: "/storage/cms-images/background/background-1.avif",
    },
};

export function HeroSplitImageBlock({ block, blockIndex, onUpdate, globalTheme }) {
    const { requestedTheme, theme } = getHeroThemeState(block, globalTheme);
    const primaryTheme = colorFamilies[globalTheme?.primary] || colorFamilies.emerald;
    const data = { ...HeroSplitImageSchema.defaults, ...block };
    const { props } = usePage();
    const websiteId = props.page?.website_id || props.website?.id;
    const isPrimarySection = requestedTheme === "primary";
    const primaryButtonStyle = isPrimarySection
        ? { bg: "bg-white", text: "text-slate-950" }
        : { bg: primaryTheme.bg, text: primaryTheme.text };

    return (
        <section className={sparkTw(block, "section", `relative flex items-center overflow-hidden px-7 py-0 sm:px-10 lg:px-12 ${theme.bg} transition-colors duration-500`)} style={{minHeight:"var(--cosmic-hero-fold-height, calc(100svh - 80px))"}}>
            <div className={sparkTw(block, "wrapper", `pointer-events-none absolute -left-32 top-1/2 h-80 w-80 -translate-y-1/2 rounded-full ${primaryTheme.bg} opacity-[0.08] blur-[110px]`)} />

            <div className={sparkTw(block, "wrapper_2", "relative mx-auto grid w-full max-w-7xl items-center gap-12 lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)] lg:gap-20")}>
                <div className={sparkTw(block, "wrapper_3", "order-2 max-w-2xl lg:order-1")}>
                    <EditableText
                        value={data.tagline}
                        className={sparkTw(block, "text", `block text-xs font-semibold uppercase tracking-[0.3em] ${theme.sub}`)}
                        onSave={(tagline) => onUpdate({ tagline })}
                    />

                    <EditableText
                        value={data.heading} cosmicType="h1"
                        className={sparkTw(block, "text_2", `mt-5 block text-4xl font-bold leading-[1.02] tracking-tight sm:text-5xl lg:text-6xl xl:text-7xl ${theme.text}`)}
                        onSave={(heading) => onUpdate({ heading })}
                    />

                    <EditableText
                        value={data.text}
                        isTextArea
                        className={sparkTw(block, "text_3", `mt-6 block max-w-xl text-base leading-7 sm:text-lg sm:leading-8 ${theme.sub}`)}
                        onSave={(text) => onUpdate({ text })}
                    />

                    <div className={sparkTw(block, "wrapper_4", "mt-8 flex flex-col gap-3 sm:flex-row sm:items-center")}>
                        <EditableButton
                            label={data.primary_label}
                            url={data.primary_url}
                            className={sparkTw(block, "button", `inline-flex min-h-[50px] items-center justify-center rounded-full px-7 font-bold ${primaryButtonStyle.bg} ${primaryButtonStyle.text}`)}
                            onSave={(primary_label, primary_url) => onUpdate({ primary_label, primary_url })}
                        />
                        <EditableButton
                            label={data.secondary_label}
                            url={data.secondary_url}
                            className={sparkTw(block, "button_2", `inline-flex min-h-[50px] items-center justify-center rounded-full border px-7 font-bold transition hover:opacity-80 ${theme.border} ${theme.text}`)}
                            onSave={(secondary_label, secondary_url) => onUpdate({ secondary_label, secondary_url })}
                        />
                    </div>

                    <EditableText
                        value={data.trust_line}
                        className={sparkTw(block, "text_4", `mt-8 block border-t pt-5 text-sm ${theme.border} ${theme.sub}`)}
                        onSave={(trust_line) => onUpdate({ trust_line })}
                    />
                </div>

                <div className={sparkTw(block, "wrapper_5", "order-1 lg:order-2")}>
                    <div className={sparkTw(block, "wrapper_6", `relative overflow-hidden rounded-[2rem] border shadow-2xl ${theme.border}`)}>
                        <EditableImage
                            websiteId={websiteId}
                            blockIndex={blockIndex}
                            src={data.image_url}
                            className={sparkTw(block, "image", "aspect-[4/3] w-full object-cover")}
                            onSave={(image_url) => onUpdate({ image_url })}
                        />
                        <EditableText
                            value={data.image_badge}
                            className={sparkTw(block, "text_5", "absolute bottom-5 left-5 rounded-full bg-slate-950/80 px-4 py-2 text-xs font-semibold text-white backdrop-blur")}
                            onSave={(image_badge) => onUpdate({ image_badge })}
                        />
                    </div>
                </div>
            </div>
        </section>
    );
}
