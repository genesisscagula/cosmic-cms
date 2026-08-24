import { usePage } from "@inertiajs/react";

import { EditableButton } from "../Shared/EditableButton";
import { EditableImage } from "../Shared/EditableImage";
import { EditableText } from "../Shared/EditableText";
import { getEffectiveTheme } from "../../../../theme/Theme";
import { getHeroThemeState, resolveHeroThemeRequest } from "../../../../theme/heroTheme";
import { colorFamilies } from "../../../../theme/colorFamilies";
import { sparkTw } from "../Shared/sparkTailwindRuntime";

export const HeroFloatingGlassSchema = {
    type: "hero_floating_glass",
    title: "Floating Glass Hero",
    category: "Hero",
    purpose: "Open a premium website with an immersive image, layered glass cards, and concise proof.",
    description: "A Pro-only cinematic hero with translucent floating panels, badges, and an image-led composition.",
    tags: ["hero", "premium", "glass", "pro", "image", "saas", "agency"],
    defaults: {
        eyebrow: "BUILT FOR MOMENTUM",
        heading: "A clearer way to move your business forward.",
        text: "Bring your offer, proof, and next step together in one immersive opening experience.",
        primary_label: "Start a project",
        primary_url: "#",
        secondary_label: "See how it works",
        secondary_url: "#",
        glass_title: "Made for decisive teams",
        glass_text: "A focused digital experience designed to turn attention into action.",
        metric_value: "3.2x",
        metric_label: "Faster path to launch",
        badge_one: "Strategy-led",
        badge_two: "Conversion-ready",
        image_url: "/storage/cms-images/background/background-1.avif",
    },
};

export function HeroFloatingGlassBlock({ block, blockIndex, onUpdate, globalTheme }) {
    const { requestedTheme, theme } = getHeroThemeState(block, globalTheme);
    const primaryTheme = colorFamilies[globalTheme?.primary] || colorFamilies.emerald;
    const data = { ...HeroFloatingGlassSchema.defaults, ...block };
    const { props } = usePage();
    const websiteId = props.page?.website_id || props.website?.id;
    const isPrimary = requestedTheme === "primary";
    const primaryButton = isPrimary ? "bg-white text-slate-950" : `${primaryTheme.bg} ${primaryTheme.text}`;

    return (
        <section className={sparkTw(block, "section", `relative flex items-center overflow-hidden px-6 py-0 sm:px-10 lg:px-14 ${theme.bg} transition-colors duration-500`)} style={{minHeight:"var(--cosmic-hero-fold-height, calc(100svh - 80px))"}}>
            <div className={sparkTw(block, "wrapper", "pointer-events-none absolute inset-0 opacity-70")} style={{backgroundImage:"radial-gradient(circle at 15% 20%, rgba(255,255,255,.2), transparent 30%), radial-gradient(circle at 85% 80%, rgba(148,163,184,.18), transparent 34%)"}} />
            <div className={sparkTw(block, "wrapper_2", "relative mx-auto w-full max-w-7xl")}>
                <div className={sparkTw(block, "wrapper_3", "grid items-center gap-10 lg:grid-cols-[minmax(0,.9fr)_minmax(460px,1.1fr)] lg:gap-14")}>
                    <div className={sparkTw(block, "wrapper_4", "relative z-20")}>
                        <EditableText value={data.eyebrow} className={sparkTw(block, "text", `text-xs font-bold uppercase tracking-[.28em] ${theme.sub}`)} onSave={(eyebrow)=>onUpdate({eyebrow})} />
                        <EditableText value={data.heading} cosmicType="h1" className={sparkTw(block, "text_2", `mt-5 block max-w-3xl text-4xl font-semibold leading-[1] tracking-[-.045em] sm:text-5xl lg:text-6xl xl:text-7xl ${theme.text}`)} onSave={(heading)=>onUpdate({heading})} />
                        <EditableText value={data.text} isTextArea className={sparkTw(block, "text_3", `mt-6 block max-w-xl text-base leading-7 sm:text-lg sm:leading-8 ${theme.sub}`)} onSave={(text)=>onUpdate({text})} />
                        <div className={sparkTw(block, "wrapper_5", "mt-8 flex flex-col gap-3 sm:flex-row")}>
                            <EditableButton label={data.primary_label} url={data.primary_url} className={sparkTw(block, "button", `inline-flex min-h-[50px] items-center justify-center rounded-full px-7 font-bold ${primaryButton}`)} onSave={(primary_label,primary_url)=>onUpdate({primary_label,primary_url})}/>
                            <EditableButton label={data.secondary_label} url={data.secondary_url} className={sparkTw(block, "button_2", `inline-flex min-h-[50px] items-center justify-center rounded-full border px-7 font-bold ${theme.border} ${theme.text}`)} onSave={(secondary_label,secondary_url)=>onUpdate({secondary_label,secondary_url})}/>
                        </div>
                        <div className={sparkTw(block, "wrapper_6", "mt-8 flex flex-wrap gap-2")}>
                            {[['badge_one',data.badge_one],['badge_two',data.badge_two]].map(([key,value])=><EditableText key={key} value={value} className={sparkTw(block, "text_4", `rounded-full border px-4 py-2 text-xs font-semibold ${theme.border} ${theme.sub}`)} onSave={(v)=>onUpdate({[key]:v})}/>) }
                        </div>
                    </div>
                    <div className={sparkTw(block, "wrapper_7", "relative min-h-[480px] sm:min-h-[560px]")}>
                        <div className={sparkTw(block, "wrapper_8", `absolute inset-4 overflow-hidden rounded-[2.25rem] border shadow-2xl sm:inset-8 ${theme.border}`)}>
                            <EditableImage websiteId={websiteId} blockIndex={blockIndex} src={data.image_url} className={sparkTw(block, "image", "h-full w-full object-cover")} onSave={(image_url)=>onUpdate({image_url})}/>
                            <div className={sparkTw(block, "wrapper_9", "pointer-events-none absolute inset-0 bg-gradient-to-br from-slate-950/10 via-transparent to-slate-950/45")} />
                        </div>
                        <div className={sparkTw(block, "wrapper_10", "absolute left-0 top-10 max-w-[280px] rounded-[1.6rem] border border-white/50 bg-white/65 p-5 text-slate-900 shadow-2xl backdrop-blur-xl sm:left-2 sm:top-14")}>
                            <EditableText value={data.glass_title} className={sparkTw(block, "text_5", "block text-lg font-bold")} onSave={(glass_title)=>onUpdate({glass_title})}/>
                            <EditableText value={data.glass_text} isTextArea className={sparkTw(block, "text_6", "mt-2 block text-sm leading-6 text-slate-600")} onSave={(glass_text)=>onUpdate({glass_text})}/>
                        </div>
                        <div className={sparkTw(block, "wrapper_11", "absolute bottom-5 right-0 min-w-[190px] rounded-[1.6rem] border border-white/50 bg-slate-950/60 p-5 text-white shadow-2xl backdrop-blur-xl sm:right-2 sm:bottom-8")}>
                            <EditableText value={data.metric_value} className={sparkTw(block, "text_7", "block text-4xl font-semibold tracking-tight text-white")} onSave={(metric_value)=>onUpdate({metric_value})}/>
                            <EditableText value={data.metric_label} className={sparkTw(block, "text_8", "mt-2 block text-xs font-medium text-white/75")} onSave={(metric_label)=>onUpdate({metric_label})}/>
                        </div>
                        <div className={sparkTw(block, "wrapper_12", `absolute right-3 top-1/2 h-24 w-24 -translate-y-1/2 rounded-full ${primaryTheme.bg} opacity-85 blur-[1px] sm:right-0`)} />
                    </div>
                </div>
            </div>
        </section>
    );
}
