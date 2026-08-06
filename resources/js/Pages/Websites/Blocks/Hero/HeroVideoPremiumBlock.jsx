import { usePage } from "@inertiajs/react";
import { EditableButton } from "../Shared/EditableButton";
import { EditableText } from "../Shared/EditableText";
import { getEffectiveTheme } from "../../../../theme/Theme";
import { colorFamilies } from "../../../../theme/colorFamilies";

export const HeroVideoPremiumSchema = {
    type: "hero_video_premium",
    title: "Video Hero Premium",
    category: "Hero",
    purpose: "Create a cinematic first impression with background motion, premium overlays, and a clear scroll cue.",
    description: "A Pro-only full-screen video hero with gradient overlays, CTA actions, a media badge, and scroll indicator.",
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
    const theme = getEffectiveTheme(block.resolvedTheme, globalTheme);
    const primaryTheme = colorFamilies[globalTheme?.primary] || colorFamilies.emerald;
    const data = { ...HeroVideoPremiumSchema.defaults, ...block };
    const { props } = usePage();
    const isPrimary = block.resolvedTheme === "primary";
    const primaryButton = isPrimary ? "bg-white text-slate-950" : `${primaryTheme.bg} ${primaryTheme.text}`;

    return (
        <section className={`relative min-h-[84vh] overflow-hidden ${theme.bg}`}>
            <video className="absolute inset-0 h-full w-full object-cover" autoPlay muted loop playsInline poster={data.poster_image_url}>
                <source src={data.video_url} type="video/mp4" />
            </video>
            <div className="pointer-events-none absolute inset-0 bg-gradient-to-r from-slate-950/88 via-slate-950/58 to-slate-950/18" />
            <div className="pointer-events-none absolute inset-0 bg-gradient-to-t from-slate-950/75 via-transparent to-slate-950/25" />
            <div className="relative mx-auto flex min-h-[84vh] max-w-7xl flex-col justify-between px-6 py-8 sm:px-10 sm:py-10 lg:px-14 lg:py-12">
                <div className="flex items-center justify-between border-b border-white/25 pb-5 text-white">
                    <EditableText value={data.eyebrow} className="text-[11px] font-bold uppercase tracking-[.34em] text-white/80" onSave={(eyebrow)=>onUpdate({eyebrow})}/>
                    <EditableText value={data.media_badge} className="rounded-full border border-white/30 bg-white/10 px-4 py-2 text-[11px] font-semibold text-white backdrop-blur-md" onSave={(media_badge)=>onUpdate({media_badge})}/>
                </div>
                <div className="max-w-4xl py-14 sm:py-20 lg:py-24">
                    <EditableText value={data.heading} className="block max-w-4xl text-5xl font-semibold leading-[.95] tracking-[-.05em] text-white sm:text-7xl lg:text-[6.6rem]" onSave={(heading)=>onUpdate({heading})}/>
                    <EditableText value={data.text} isTextArea className="mt-7 block max-w-2xl text-base leading-7 text-white/75 sm:text-lg sm:leading-8" onSave={(text)=>onUpdate({text})}/>
                    <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                        <EditableButton label={data.primary_label} url={data.primary_url} className={`inline-flex min-h-[52px] items-center justify-center rounded-full px-7 font-bold ${primaryButton}`} onSave={(primary_label,primary_url)=>onUpdate({primary_label,primary_url})}/>
                        <EditableButton label={data.secondary_label} url={data.secondary_url} className="inline-flex min-h-[52px] items-center justify-center rounded-full border border-white/45 bg-white/5 px-7 font-bold text-white backdrop-blur-sm" onSave={(secondary_label,secondary_url)=>onUpdate({secondary_label,secondary_url})}/>
                    </div>
                </div>
                <div className="flex items-center justify-between border-t border-white/25 pt-5 text-white">
                    <EditableText value={data.scroll_label} className="text-xs font-semibold uppercase tracking-[.2em] text-white/75" onSave={(scroll_label)=>onUpdate({scroll_label})}/>
                    <span className="flex h-10 w-6 items-start justify-center rounded-full border border-white/45 p-1"><span className="h-2 w-1 rounded-full bg-white animate-bounce" /></span>
                </div>
            </div>
        </section>
    );
}
