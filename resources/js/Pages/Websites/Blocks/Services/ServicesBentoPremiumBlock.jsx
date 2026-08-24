import { EditableButton } from "../Shared/EditableButton";
import { EditableText } from "../Shared/EditableText";
import { getEffectiveTheme } from "../../../../theme/Theme";
import { colorFamilies } from "../../../../theme/colorFamilies";
import { sparkTw } from "../Shared/sparkTailwindRuntime";

export const ServicesBentoPremiumSchema = {
    type: "services_bento_premium",
    title: "Services Bento Premium",
    category: "Services",
    purpose: "Present a premium service offer through an asymmetrical bento grid.",
    description: "A Pro-only services section with one featured offer, four supporting cards, proof details, and a clear conversion path.",
    tags: ["services", "premium", "bento", "agency", "consulting", "technology", "pro"],
    defaults: {
        eyebrow: "SERVICES DESIGNED AROUND MOMENTUM",
        heading: "Specialist thinking, connected into one clear growth system.",
        text: "Combine strategy, design, technology, and optimisation in a flexible service model built around the way your business actually works.",
        primary_label: "Explore our services",
        primary_url: "#",
        featured_number: "01",
        featured_title: "Digital strategy",
        featured_text: "Clarify the opportunity, align the priorities, and turn ambitious goals into an actionable roadmap.",
        featured_meta: "Research · Positioning · Roadmaps",
        service_two_number: "02",
        service_two_title: "Experience design",
        service_two_text: "Shape intuitive journeys and interfaces that make every interaction feel considered.",
        service_three_number: "03",
        service_three_title: "Web platforms",
        service_three_text: "Build fast, scalable digital foundations designed to evolve with your team.",
        service_four_number: "04",
        service_four_title: "Growth systems",
        service_four_text: "Connect content, campaigns, and measurement into a repeatable growth engine.",
        service_five_number: "05",
        service_five_title: "Ongoing optimisation",
        service_five_text: "Improve performance continuously through testing, insight, and focused iteration.",
        service_six_number: "06",
        service_six_title: "Specialist support",
        service_six_text: "Add another relevant service when the offer needs more depth.",
        service_seven_number: "07",
        service_seven_title: "Extended care",
        service_seven_text: "Add a seventh service when it genuinely improves the customer journey.",
        service_count: 5,
        proof_value: "5 disciplines",
        proof_label: "One integrated senior team",
    },
};

export function ServicesBentoPremiumBlock({ block, onUpdate, globalTheme }) {
    const theme = getEffectiveTheme(block.theme && block.theme !== "auto" ? block.theme : block.resolvedTheme, globalTheme);
    const primaryTheme = colorFamilies[globalTheme?.primary] || colorFamilies.emerald;
    const data = { ...ServicesBentoPremiumSchema.defaults, ...block };
    const isPrimary = (block.theme && block.theme !== "auto" ? block.theme : block.resolvedTheme) === "primary";
    const card = isPrimary ? "border-white/20 bg-white/10 text-white" : `${theme.border} ${theme.surface} ${theme.text}`;
    const muted = isPrimary ? "text-white/70" : theme.sub;
    const softMuted = isPrimary ? "text-white/70" : primaryTheme.sub;
    const soft = isPrimary ? "border-white/20 bg-slate-950/15 text-white" : `${primaryTheme.border || theme.border} ${primaryTheme.card || primaryTheme.bg} ${primaryTheme.text}`;
    const primaryButton = isPrimary ? "bg-white text-slate-950" : `${primaryTheme.bg} ${primaryTheme.text}`;
    const familyGlow = primaryTheme.gradient?.glowSoft || "rgba(124, 58, 237, 0.20)";

    const smallCards = [
        ["service_two_number", "service_two_title", "service_two_text"],
        ["service_three_number", "service_three_title", "service_three_text"],
        ["service_four_number", "service_four_title", "service_four_text"],
        ["service_five_number", "service_five_title", "service_five_text"],
        ["service_six_number", "service_six_title", "service_six_text"],
        ["service_seven_number", "service_seven_title", "service_seven_text"],
    ];
    const serviceCount = Math.max(1, Math.min(7, Number(data.service_count) || 5));
    const visibleSmallCards = smallCards.slice(0, Math.max(0, serviceCount - 1));

    return (
        <section data-cosmic-services-bento-premium="true" className={sparkTw(block, "section", `relative overflow-hidden px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 ${theme.bg}`)}>
            <div className={sparkTw(block, "wrapper", "pointer-events-none absolute inset-0")} style={{ background: `radial-gradient(circle at 12% 18%, ${familyGlow}, transparent 26%)` }} />
            <div className={sparkTw(block, "wrapper_2", "relative mx-auto max-w-7xl")}>
                <div className={sparkTw(block, "wrapper_3", "grid gap-8 lg:grid-cols-[1fr_.72fr] lg:items-end lg:gap-16")}>
                    <div>
                        <EditableText value={data.eyebrow} className={sparkTw(block, "text", `text-xs font-bold uppercase tracking-[.28em] ${muted}`)} onSave={(eyebrow)=>onUpdate({eyebrow})}/>
                        <EditableText value={data.heading} cosmicType="h2" className={sparkTw(block, "text_2", `mt-5 block max-w-4xl text-4xl font-semibold leading-[1] tracking-[-.045em] sm:text-5xl lg:text-6xl ${theme.text}`)} onSave={(heading)=>onUpdate({heading})}/>
                    </div>
                    <div className={sparkTw(block, "wrapper_4", "lg:pb-1")}>
                        <EditableText value={data.text} isTextArea className={sparkTw(block, "text_3", `block text-base leading-7 sm:text-lg sm:leading-8 ${muted}`)} onSave={(text)=>onUpdate({text})}/>
                        <EditableButton label={data.primary_label} url={data.primary_url} className={sparkTw(block, "button", `mt-6 inline-flex min-h-[50px] items-center justify-center rounded-full px-7 font-bold ${primaryButton}`)} onSave={(primary_label,primary_url)=>onUpdate({primary_label,primary_url})}/>
                    </div>
                </div>

                <div className={sparkTw(block, "wrapper_5", "mt-12 grid gap-4 lg:grid-cols-12")}>
                    <article className={sparkTw(block, "card", `rounded-[2rem] border p-7 sm:p-9 lg:col-span-7 lg:row-span-2 ${card}`)}>
                        <div className={sparkTw(block, "wrapper_6", "flex items-center justify-between gap-4")}>
                            <EditableText value={data.featured_number} className={sparkTw(block, "text_4", `text-xs font-black tracking-[.22em] ${muted}`)} onSave={(featured_number)=>onUpdate({featured_number})}/>
                            <span className={sparkTw(block, "label", `h-2.5 w-2.5 rounded-full ${isPrimary ? "bg-white" : primaryTheme.bg}`)} />
                        </div>
                        <EditableText value={data.featured_title} className={sparkTw(block, "text_5", "mt-14 block max-w-2xl text-3xl font-semibold tracking-[-.035em] sm:text-4xl")} onSave={(featured_title)=>onUpdate({featured_title})}/>
                        <EditableText value={data.featured_text} isTextArea className={sparkTw(block, "text_6", `mt-5 block max-w-2xl text-base leading-7 ${muted}`)} onSave={(featured_text)=>onUpdate({featured_text})}/>
                        <div className={sparkTw(block, "wrapper_7", `mt-10 border-t pt-6 ${isPrimary ? "border-white/20" : theme.border}`)}>
                            <EditableText value={data.featured_meta} className={sparkTw(block, "text_7", `text-sm font-semibold ${muted}`)} onSave={(featured_meta)=>onUpdate({featured_meta})}/>
                        </div>
                    </article>

                    {visibleSmallCards.slice(0,2).map(([number,title,text]) => (
                        <article key={title} className={sparkTw(block, "card_2", `rounded-[2rem] border p-6 lg:col-span-5 ${card}`)}>
                            <EditableText value={data[number]} className={sparkTw(block, "text_8", `text-[11px] font-black tracking-[.2em] ${muted}`)} onSave={(v)=>onUpdate({[number]:v})}/>
                            <EditableText value={data[title]} className={sparkTw(block, "text_9", "mt-8 block text-xl font-semibold tracking-[-.02em]")} onSave={(v)=>onUpdate({[title]:v})}/>
                            <EditableText value={data[text]} isTextArea className={sparkTw(block, "text_10", `mt-3 block text-sm leading-6 ${muted}`)} onSave={(v)=>onUpdate({[text]:v})}/>
                        </article>
                    ))}

                    {visibleSmallCards.slice(2).map(([number,title,text]) => (
                        <article key={title} className={sparkTw(block, "card_3", `rounded-[2rem] border p-6 lg:col-span-4 ${card}`)}>
                            <EditableText value={data[number]} className={sparkTw(block, "text_11", `text-[11px] font-black tracking-[.2em] ${muted}`)} onSave={(v)=>onUpdate({[number]:v})}/>
                            <EditableText value={data[title]} className={sparkTw(block, "text_12", "mt-8 block text-lg font-semibold tracking-[-.02em]")} onSave={(v)=>onUpdate({[title]:v})}/>
                            <EditableText value={data[text]} isTextArea className={sparkTw(block, "text_13", `mt-3 block text-sm leading-6 ${muted}`)} onSave={(v)=>onUpdate({[text]:v})}/>
                        </article>
                    ))}

                    <article data-cosmic-services-bento-proof="true" className={sparkTw(block, "card_4", `rounded-[2rem] border p-6 lg:col-span-4 ${soft}`)}>
                        <EditableText value={data.proof_value || `${serviceCount} services`} className={sparkTw(block, "text_14", "cosmic-services-bento-proof-value block text-3xl font-semibold tracking-[-.035em]")} onSave={(proof_value)=>onUpdate({proof_value})}/>
                        <EditableText value={data.proof_label} className={sparkTw(block, "text_15", "cosmic-services-bento-proof-label mt-3 block text-sm leading-6")} onSave={(proof_label)=>onUpdate({proof_label})}/>
                    </article>
                </div>
            </div>
        </section>
    );
}
