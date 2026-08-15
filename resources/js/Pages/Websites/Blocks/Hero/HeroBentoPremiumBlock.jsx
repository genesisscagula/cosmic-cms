import { usePage } from "@inertiajs/react";
import { EditableButton } from "../Shared/EditableButton";
import { EditableImage } from "../Shared/EditableImage";
import { EditableText } from "../Shared/EditableText";
import { getEffectiveTheme } from "../../../../theme/Theme";
import { colorFamilies } from "../../../../theme/colorFamilies";

export const HeroBentoPremiumSchema = {
    type: "hero_bento_premium",
    title: "Bento Hero",
    category: "Hero",
    purpose: "Introduce a premium brand through an asymmetrical multi-card visual story.",
    description: "A Pro-only hero with an editorial headline, image card, metric card, proof card, and CTA card arranged in a responsive bento composition.",
    tags: ["hero", "premium", "bento", "asymmetrical", "cards", "metrics", "pro"],
    defaults: {
        eyebrow: "BUILT TO STAND APART",
        heading: "One clear idea, expressed from every angle.",
        text: "Bring your message, proof, imagery, and next step together in a flexible bento composition designed for modern brands.",
        primary_label: "Start a project",
        primary_url: "#",
        secondary_label: "Explore the work",
        secondary_url: "#",
        image_url: "/storage/cms-images/background/background-1.avif",
        image_label: "Featured perspective",
        metric_value: "3.4x",
        metric_label: "More engaged visitors",
        proof_title: "Built around clarity",
        proof_text: "A modular opening experience with strong hierarchy and deliberate rhythm.",
        card_one_label: "Strategy-led",
        card_two_label: "Responsive by design",
        card_three_label: "Ready to publish",
    },
};

export function HeroBentoPremiumBlock({ block, blockIndex, onUpdate, globalTheme }) {
    const theme = getEffectiveTheme(block.resolvedTheme, globalTheme);
    const primaryTheme = colorFamilies[globalTheme?.primary] || colorFamilies.emerald;
    const data = { ...HeroBentoPremiumSchema.defaults, ...block };
    const { props } = usePage();
    const websiteId = props.page?.website_id || props.website?.id;
    const isPrimary = block.resolvedTheme === "primary";
    const primaryButton = isPrimary ? "bg-white text-slate-950" : `${primaryTheme.bg} ${primaryTheme.text}`;
    const softCard = isPrimary ? "border-white/20 bg-white/10 text-white" : `${theme.border} ${theme.surface} ${theme.text}`;
    const softSub = isPrimary ? "text-white/70" : theme.sub;
    const proofSub = isPrimary ? "text-white/70" : primaryTheme.sub;

    return (
        <section className={`relative overflow-hidden px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 ${theme.bg}`}>
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_80%_12%,rgba(16,185,129,.13),transparent_28%)]" />
            <div className="relative mx-auto max-w-7xl">
                <div className="grid gap-4 lg:grid-cols-12 lg:grid-rows-[auto_auto]">
                    <div className={`rounded-[2rem] border p-7 sm:p-10 lg:col-span-7 lg:row-span-2 ${softCard}`}>
                        <EditableText value={data.eyebrow} className={`text-xs font-bold uppercase tracking-[.28em] ${softSub}`} onSave={(eyebrow)=>onUpdate({eyebrow})}/>
                        <EditableText value={data.heading} className="mt-5 block max-w-4xl text-5xl font-semibold leading-[.95] tracking-[-.055em] sm:text-6xl lg:text-7xl" onSave={(heading)=>onUpdate({heading})}/>
                        <EditableText value={data.text} isTextArea className={`mt-6 block max-w-2xl text-base leading-7 sm:text-lg sm:leading-8 ${softSub}`} onSave={(text)=>onUpdate({text})}/>
                        <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                            <EditableButton label={data.primary_label} url={data.primary_url} className={`inline-flex min-h-[50px] items-center justify-center rounded-full px-7 font-bold ${primaryButton}`} onSave={(primary_label,primary_url)=>onUpdate({primary_label,primary_url})}/>
                            <EditableButton label={data.secondary_label} url={data.secondary_url} className={`inline-flex min-h-[50px] items-center justify-center rounded-full border px-7 font-bold ${isPrimary ? 'border-white/25 text-white' : `${theme.border} ${theme.text}`}`} onSave={(secondary_label,secondary_url)=>onUpdate({secondary_label,secondary_url})}/>
                        </div>
                        <div className="mt-10 grid gap-3 sm:grid-cols-3">
                            {["card_one_label","card_two_label","card_three_label"].map((key)=><div key={key} className={`rounded-2xl border px-4 py-4 text-sm font-semibold ${isPrimary ? 'border-white/20 bg-white/10 text-white' : `${theme.border} ${theme.bg} ${theme.text}`}`}><EditableText value={data[key]} onSave={(v)=>onUpdate({[key]:v})}/></div>)}
                        </div>
                    </div>

                    <div className="relative min-h-[310px] overflow-hidden rounded-[2rem] lg:col-span-5">
                        <EditableImage websiteId={websiteId} blockIndex={blockIndex} src={data.image_url} className="absolute inset-0 h-full w-full object-cover" onSave={(image_url)=>onUpdate({image_url})}/>
                        <div className="pointer-events-none absolute inset-0 bg-gradient-to-t from-slate-950/75 via-slate-950/10 to-transparent" />
                        <div className="absolute inset-x-0 bottom-0 p-6 text-white sm:p-8"><EditableText value={data.image_label} className="text-sm font-semibold text-white" onSave={(image_label)=>onUpdate({image_label})}/></div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2 lg:col-span-5">
                        <div className={`rounded-[2rem] border p-6 ${softCard}`}><EditableText value={data.metric_value} className="block text-5xl font-semibold tracking-[-.04em]" onSave={(metric_value)=>onUpdate({metric_value})}/><EditableText value={data.metric_label} className={`mt-3 block text-sm leading-6 ${softSub}`} onSave={(metric_label)=>onUpdate({metric_label})}/></div>
                        <div className={`rounded-[2rem] border p-6 ${isPrimary ? 'border-white/20 bg-slate-950/20 text-white' : `${primaryTheme.border || theme.border} ${primaryTheme.card || primaryTheme.bg} ${primaryTheme.text}`}`}><EditableText value={data.proof_title} className="block text-lg font-semibold" onSave={(proof_title)=>onUpdate({proof_title})}/><EditableText value={data.proof_text} isTextArea className={`mt-3 block text-sm leading-6 ${proofSub}`} onSave={(proof_text)=>onUpdate({proof_text})}/></div>
                    </div>
                </div>
            </div>
        </section>
    );
}
