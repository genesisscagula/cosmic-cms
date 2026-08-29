import { usePage } from "@inertiajs/react";
import { EditableButton } from "../Shared/EditableButton";
import { EditableImage } from "../Shared/EditableImage";
import { EditableText } from "../Shared/EditableText";
import { getHeroThemeState } from "../../../../theme/heroTheme";
import { colorFamilies } from "../../../../theme/colorFamilies";
import { sparkTw, sparkTwItem } from "../Shared/sparkTailwindRuntime";

export const HeroAgencyShowcaseSchema = {
    type: "hero_agency_showcase",
    title: "Agency Showcase Hero",
    category: "Hero",
    purpose: "Present an agency through transformation proof, portfolio metrics, and client trust.",
    description: "A Pro-only agency hero with a before/after project showcase, performance metrics, and client logo proof.",
    tags: ["hero", "premium", "agency", "portfolio", "before-after", "metrics", "logos", "pro"],
    defaults: {
        eyebrow: "DESIGN THAT MOVES BUSINESS FORWARD",
        heading: "From overlooked to unforgettable.",
        text: "Pair strategic thinking with polished execution, then show visitors the difference your agency creates at a glance.",
        primary_label: "Start a project",
        primary_url: "#",
        secondary_label: "View case studies",
        secondary_url: "#",
        before_label: "Before",
        before_caption: "A fragmented digital experience",
        after_label: "After",
        after_caption: "A focused brand built to convert",
        metric_one_value: "48%",
        metric_one_label: "More qualified enquiries",
        metric_two_value: "2.4x",
        metric_two_label: "Higher conversion rate",
        metric_three_value: "6 weeks",
        metric_three_label: "From strategy to launch",
        logo_one: "NORTHSTAR",
        logo_two: "MORROW & CO",
        logo_three: "FOUNDRY",
        logo_four: "KINSHIP",
        before_image_url: "/storage/cms-images/background/background-2.avif",
        after_image_url: "/storage/cms-images/background/background-1.avif",
    },
};

export function HeroAgencyShowcaseBlock({ block, blockIndex, onUpdate, globalTheme }) {
    const heroState = getHeroThemeState(block, globalTheme);
    const { theme, isPrimary } = heroState;
    const primaryKey = typeof globalTheme === "string" ? globalTheme : (globalTheme?.primary || "midnight");
    const primaryTheme = colorFamilies[primaryKey] || colorFamilies.midnight;
    const data = { ...HeroAgencyShowcaseSchema.defaults, ...block };
    const { props } = usePage();
    const websiteId = props.page?.website_id || props.website?.id;
    const primaryButton = isPrimary ? "bg-white text-slate-950" : `${primaryTheme.bg} ${primaryTheme.text}`;
    const metricSurface = isPrimary ? "border-white/20 bg-white/10 text-white" : `${theme.border} ${theme.surface} ${theme.text}`;

    return (
        <section className={sparkTw(block, "section", `relative flex items-center overflow-hidden px-6 py-0 sm:px-10 lg:px-14 ${theme.bg}`)} style={{minHeight:"var(--cosmic-hero-fold-height, calc(100svh - 80px))"}}>
            <div className={sparkTw(block, "wrapper", "pointer-events-none absolute inset-0")} style={{ background: `radial-gradient(circle at 18% 15%, ${primaryTheme.gradient?.glowSoft || "rgba(124,58,237,.14)"}, transparent 30%)` }} />
            <div className={sparkTw(block, "wrapper_2", "relative mx-auto w-full max-w-7xl")}>
                <div className={sparkTw(block, "wrapper_3", "grid items-end gap-10 lg:grid-cols-[1fr_.72fr] lg:gap-16")}>
                    <div>
                        <EditableText value={data.eyebrow} className={sparkTw(block, "text", `text-xs font-bold uppercase tracking-[.28em] ${theme.sub}`)} onSave={(eyebrow)=>onUpdate({eyebrow})}/>
                        <EditableText value={data.heading} cosmicType="h1" className={sparkTw(block, "text_2", `mt-5 block max-w-4xl text-4xl font-semibold leading-[.98] tracking-[-.045em] sm:text-5xl lg:text-6xl xl:text-7xl ${theme.text}`)} onSave={(heading)=>onUpdate({heading})}/>
                    </div>
                    <div className={sparkTw(block, "wrapper_4", "lg:pb-2")}>
                        <EditableText value={data.text} isTextArea className={sparkTw(block, "text_3", `block text-base leading-7 sm:text-lg sm:leading-8 ${theme.sub}`)} onSave={(text)=>onUpdate({text})}/>
                        <div className={sparkTw(block, "wrapper_5", "mt-7 flex flex-col gap-3 sm:flex-row lg:flex-col xl:flex-row")}>
                            <EditableButton label={data.primary_label} url={data.primary_url} className={sparkTw(block, "button", `inline-flex min-h-[50px] items-center justify-center rounded-full px-7 font-bold ${primaryButton}`)} onSave={(primary_label,primary_url)=>onUpdate({primary_label,primary_url})}/>
                            <EditableButton label={data.secondary_label} url={data.secondary_url} className={sparkTw(block, "button_2", `inline-flex min-h-[50px] items-center justify-center rounded-full border px-7 font-bold ${theme.border} ${theme.text}`)} onSave={(secondary_label,secondary_url)=>onUpdate({secondary_label,secondary_url})}/>
                        </div>
                    </div>
                </div>

                <div className={sparkTw(block, "wrapper_6", `mt-12 rounded-[2rem] border p-3 shadow-2xl sm:p-4 ${theme.border} ${theme.surface}`)}>
                    <div className={sparkTw(block, "wrapper_7", "grid gap-3 md:grid-cols-2")}>
                        {[['before','before_image_url'],['after','after_image_url']].map(([prefix,imageKey]) => (
                            <div key={prefix} className={sparkTwItem(block, "showcase_panels", prefix, "card", sparkTw(block, "wrapper_8", "relative min-h-[300px] overflow-hidden rounded-[1.45rem] sm:min-h-[390px]"))}>
                                <EditableImage websiteId={websiteId} blockIndex={blockIndex} src={data[imageKey]} className={sparkTwItem(block, "showcase_panels", prefix, "image", sparkTw(block, "image", `absolute inset-0 h-full w-full object-cover ${prefix === 'before' ? 'grayscale' : ''}`))} onSave={(value)=>onUpdate({[imageKey]:value})}/>
                                <div className={sparkTwItem(block, "showcase_panels", prefix, "overlay", sparkTw(block, "wrapper_9", `pointer-events-none absolute inset-0 ${prefix === 'before' ? 'bg-slate-950/50' : 'bg-gradient-to-t from-slate-950/70 via-slate-950/10 to-transparent'}`))} />
                                <div className={sparkTwItem(block, "showcase_panels", prefix, "content", sparkTw(block, "wrapper_10", "absolute inset-x-0 bottom-0 p-5 text-white sm:p-7"))}>
                                    <EditableText value={data[`${prefix}_label`]} className={sparkTwItem(block, "showcase_panels", prefix, "label", sparkTw(block, "text_4", "text-[11px] font-bold uppercase tracking-[.24em] text-white/70"))} onSave={(v)=>onUpdate({[`${prefix}_label`]:v})}/>
                                    <EditableText value={data[`${prefix}_caption`]} className={sparkTwItem(block, "showcase_panels", prefix, "caption", sparkTw(block, "text_5", "mt-2 block max-w-sm text-xl font-semibold leading-tight text-white sm:text-2xl"))} onSave={(v)=>onUpdate({[`${prefix}_caption`]:v})}/>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                <div className={sparkTw(block, "wrapper_11", "mt-6 grid gap-3 sm:grid-cols-3")}>
                    {[1,2,3].map((index) => {
                        const valueKey = `metric_${['one','two','three'][index-1]}_value`;
                        const labelKey = `metric_${['one','two','three'][index-1]}_label`;
                        return <div key={index} className={sparkTwItem(block, "metrics", index - 1, "card", sparkTw(block, "wrapper_12", `rounded-2xl border p-5 ${metricSurface}`))}><EditableText value={data[valueKey]} className={sparkTwItem(block, "metrics", index - 1, "value", sparkTw(block, "text_6", "block text-3xl font-semibold tracking-tight"))} onSave={(v)=>onUpdate({[valueKey]:v})}/><EditableText value={data[labelKey]} className={sparkTwItem(block, "metrics", index - 1, "label", sparkTw(block, "text_7", `mt-2 block text-sm ${isPrimary ? 'text-white/70' : theme.sub}`))} onSave={(v)=>onUpdate({[labelKey]:v})}/></div>
                    })}
                </div>

                <div className={sparkTw(block, "wrapper_13", `mt-8 flex flex-wrap items-center justify-between gap-x-8 gap-y-4 border-t pt-7 ${theme.border}`)}>
                    <span className={sparkTw(block, "label", `text-[10px] font-bold uppercase tracking-[.24em] ${theme.sub}`)}>Selected client work</span>
                    <div className={sparkTw(block, "wrapper_14", "flex flex-wrap items-center gap-x-8 gap-y-3")}>
                        {["logo_one","logo_two","logo_three","logo_four"].map((key, index)=><EditableText key={key} value={data[key]} className={sparkTwItem(block, "logos", index, "label", sparkTw(block, "text_8", `text-xs font-black tracking-[.15em] ${theme.text}`))} onSave={(v)=>onUpdate({[key]:v})}/>) }
                    </div>
                </div>
            </div>
        </section>
    );
}
