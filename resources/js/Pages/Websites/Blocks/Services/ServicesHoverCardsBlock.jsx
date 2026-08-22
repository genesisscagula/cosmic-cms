import { EditableButton } from "../Shared/EditableButton";
import { EditableText } from "../Shared/EditableText";
import { getEffectiveTheme } from "../../../../theme/Theme";
import { colorFamilies } from "../../../../theme/colorFamilies";
import { RepeatableControls, RepeatableRemoveButton } from "../Shared/RepeatableControls";

export const ServicesHoverCardsSchema = {
    type: "services_hover_cards",
    title: "Hover Cards Premium",
    category: "Services",
    purpose: "Present six premium services with concise summaries and hover-revealed detail.",
    description: "A Pro-only interactive service grid with six editable hover cards and a conversion CTA.",
    tags: ["services", "hover", "interactive", "premium", "cards", "pro"],
    defaults: {
        eyebrow: "EXPLORE OUR CAPABILITIES",
        heading: "Specialist services, designed to work better together.",
        text: "Move from first idea to measurable improvement with senior support across strategy, design, technology, and growth.",
        primary_label: "Discuss your project",
        primary_url: "#",
        card_one_number: "01", card_one_title: "Digital strategy", card_one_summary: "Set the direction.", card_one_text: "Clarify the opportunity, align priorities, and turn ambition into a focused roadmap.", card_one_link: "Explore strategy",
        card_two_number: "02", card_two_title: "Brand systems", card_two_summary: "Build recognition.", card_two_text: "Create a flexible visual and verbal system that keeps every touchpoint consistent.", card_two_link: "Explore branding",
        card_three_number: "03", card_three_title: "Experience design", card_three_summary: "Make journeys intuitive.", card_three_text: "Shape clear user flows and polished interfaces around the needs of real customers.", card_three_link: "Explore experience",
        card_four_number: "04", card_four_title: "Web platforms", card_four_summary: "Create a stronger foundation.", card_four_text: "Build fast, responsive websites and platforms designed to evolve with your team.", card_four_link: "Explore platforms",
        card_five_number: "05", card_five_title: "Growth systems", card_five_summary: "Connect the funnel.", card_five_text: "Bring campaigns, content, conversion, and measurement into one repeatable system.", card_five_link: "Explore growth",
        card_six_number: "06", card_six_title: "Optimisation", card_six_summary: "Keep improving.", card_six_text: "Use focused testing and insight to improve performance after launch.", card_six_link: "Explore optimisation",
    },
};

export function ServicesHoverCardsBlock({ block, onUpdate, globalTheme }) {
    const theme = getEffectiveTheme(block.theme && block.theme !== "auto" ? block.theme : block.resolvedTheme, globalTheme);
    const primaryTheme = colorFamilies[globalTheme?.primary] || colorFamilies.midnight;
    const data = { ...ServicesHoverCardsSchema.defaults, ...block };
    const isPrimary = (block.theme && block.theme !== "auto" ? block.theme : block.resolvedTheme) === "primary";
    const muted = isPrimary ? "text-white/70" : theme.sub;
    const border = isPrimary ? "border-white/20" : theme.border;
    const card = isPrimary ? "bg-white/10 text-white" : `${theme.surface} ${theme.text}`;
    const hoverBackground = isPrimary ? "#ffffff" : (primaryTheme?.palette?.background || theme?.palette?.background || "#243447");
    const hoverForeground = isPrimary ? "#0f172a" : "#ffffff";
    const buttonClass = isPrimary ? "bg-white text-slate-950" : `${primaryTheme.bg} ${primaryTheme.text}`;
    const save = (key) => (value) => onUpdate({ [key]: value });
    const cardCount = Math.max(1, Math.min(6, Number(data.service_count) || 6));
    const cards = ["one", "two", "three", "four", "five", "six"].slice(0,cardCount);

    return <section className={`group/repeatable-section relative overflow-hidden px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 ${theme.bg}`}>
        <div className="mx-auto max-w-7xl">
            <div className="grid gap-8 lg:grid-cols-[1fr_auto] lg:items-end">
                <div className="max-w-3xl">
                    <EditableText value={data.eyebrow} className={`text-xs font-bold uppercase tracking-[.28em] ${muted}`} onSave={save("eyebrow")} />
                    <EditableText value={data.heading} cosmicType="h2" className={`mt-5 block text-4xl font-semibold leading-[1.02] tracking-[-.045em] sm:text-5xl lg:text-6xl ${theme.text}`} onSave={save("heading")} />
                    <EditableText value={data.text} isTextArea className={`mt-5 block max-w-2xl text-base leading-7 sm:text-lg ${muted}`} onSave={save("text")} />
                </div>
                <EditableButton label={data.primary_label} url={data.primary_url} className={`inline-flex min-h-[48px] items-center justify-center rounded-full px-7 text-sm font-bold ${buttonClass}`} onSave={(label, url) => onUpdate({ primary_label: label, primary_url: url })} />
            </div>

            <div className="mt-12 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {cards.map((word, cardIndex) => <article key={word} data-cosmic-services-hover-card="true" style={{ "--cosmic-hover-card-bg": hoverBackground, "--cosmic-hover-card-fg": hoverForeground }} className={`group relative min-h-[300px] overflow-hidden rounded-[1.75rem] border p-6 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl sm:p-7 ${border} ${card}`}>
                    <RepeatableRemoveButton hoverScope="card" overlay label="Remove service card" disabled={cardCount<=1} onRemove={()=>{const all=["one","two","three","four","five","six"];const fields=["number","title","summary","text","link"];const patch={service_count:cardCount-1};for(let x=cardIndex;x<cardCount-1;x++){fields.forEach(f=>patch[`card_${all[x]}_${f}`]=data[`card_${all[x+1]}_${f}`]??"");}onUpdate(patch)}}/>
                    <div className="flex items-start justify-between gap-4">
                        <EditableText value={data[`card_${word}_number`]} className={`text-xs font-black tracking-[.2em] ${muted}`} onSave={save(`card_${word}_number`)} />
                        <span className={`flex h-10 w-10 items-center justify-center rounded-full border text-lg transition group-hover:rotate-45 ${border}`}>↗</span>
                    </div>
                    <div className="mt-14">
                        <EditableText value={data[`card_${word}_title`]} className="block text-2xl font-semibold tracking-[-.03em]" onSave={save(`card_${word}_title`)} />
                        <EditableText value={data[`card_${word}_summary`]} className={`mt-3 block text-sm font-semibold ${muted}`} onSave={save(`card_${word}_summary`)} />
                        <EditableText value={data[`card_${word}_text`]} isTextArea className="mt-5 block translate-y-3 text-sm leading-6 opacity-75 transition duration-300 group-hover:translate-y-0 group-hover:opacity-100" onSave={save(`card_${word}_text`)} />
                        <EditableText value={data[`card_${word}_link`]} className="mt-7 block text-xs font-black uppercase tracking-[.16em] opacity-70 group-hover:opacity-100" onSave={save(`card_${word}_link`)} />
                    </div>
                </article>)}
            </div>
            <RepeatableControls onAdd={()=>cardCount<6&&onUpdate({service_count:cardCount+1})} canAdd={cardCount<6} showRemove={false} addLabel="Add service card"/>
        </div>
    </section>;
}
