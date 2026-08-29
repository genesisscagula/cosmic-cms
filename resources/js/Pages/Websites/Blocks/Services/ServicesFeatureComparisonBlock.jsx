import { EditableButton } from "../Shared/EditableButton";
import { EditableText } from "../Shared/EditableText";
import { getEffectiveTheme } from "../../../../theme/Theme";
import { colorFamilies } from "../../../../theme/colorFamilies";
import { RepeatableControls, RepeatableRemoveButton } from "../Shared/RepeatableControls";
import { sparkTw, sparkTwItem, sparkTwPath } from "../Shared/sparkTailwindRuntime";

export const ServicesFeatureComparisonSchema = {
    type: "services_feature_comparison",
    title: "Feature Comparison Premium",
    category: "Services",
    purpose: "Compare three service approaches by capability, depth, and ideal use case.",
    description: "A Pro-only feature comparison section with three editable columns and eight capability rows.",
    tags: ["services", "features", "comparison", "premium", "capabilities", "pro"],
    defaults: {
        eyebrow: "COMPARE THE APPROACH",
        heading: "Choose the level of capability your next stage needs.",
        text: "See how each service model differs across strategy, delivery, collaboration, and ongoing support.",
        option_one_name: "Foundation",
        option_one_kicker: "Focused project",
        option_one_text: "A clear, senior-led engagement for one defined priority.",
        option_two_name: "Growth System",
        option_two_kicker: "Most versatile",
        option_two_text: "Connected strategy and delivery for teams building momentum.",
        option_two_badge: "RECOMMENDED",
        option_three_name: "Embedded Partner",
        option_three_kicker: "Ongoing capability",
        option_three_text: "Flexible senior support across complex, evolving priorities.",
        feature_one: "Strategic direction", option_one_one: "Focused", option_two_one: "Integrated", option_three_one: "Embedded",
        feature_two: "Research depth", option_one_two: "Essentials", option_two_two: "Extended", option_three_two: "Continuous",
        feature_three: "Design systems", option_one_three: "Core", option_two_three: "Scalable", option_three_three: "Multi-brand",
        feature_four: "Delivery support", option_one_four: "Launch", option_two_four: "Launch + optimise", option_three_four: "Ongoing",
        feature_five: "Team access", option_one_five: "Lead specialist", option_two_five: "Cross-functional", option_three_five: "Dedicated pod",
        feature_six: "Reporting", option_one_six: "Wrap-up", option_two_six: "Monthly", option_three_six: "Custom cadence",
        feature_seven: "Best suited to", option_one_seven: "One clear priority", option_two_seven: "Growing teams", option_three_seven: "Complex programmes",
        feature_eight: "Engagement style", option_one_eight: "Fixed scope", option_two_eight: "Phased roadmap", option_three_eight: "Flexible retainer",
        primary_label: "Discuss the right approach",
        primary_url: "#",
        footnote: "Every engagement is shaped around your goals, team, and delivery requirements.",
    },
};

export function ServicesFeatureComparisonBlock({ block, onUpdate, globalTheme }) {
    const theme = getEffectiveTheme(block.theme && block.theme !== "auto" ? block.theme : block.resolvedTheme, globalTheme);
    const primaryTheme = colorFamilies[globalTheme?.primary] || colorFamilies.midnight;
    const data = { ...ServicesFeatureComparisonSchema.defaults, ...block };
    const isPrimary = (block.theme && block.theme !== "auto" ? block.theme : block.resolvedTheme) === "primary";
    const muted = isPrimary ? "text-white/70" : theme.sub;
    const border = isPrimary ? "border-white/20" : theme.border;
    const baseCard = isPrimary ? "bg-white/10 text-white" : `${theme.surface} ${theme.text}`;
    const featuredCard = isPrimary ? "bg-white text-slate-950" : `${primaryTheme.card || primaryTheme.bg} ${primaryTheme.text}`;
    const buttonClass = isPrimary ? "bg-white text-slate-950" : `${primaryTheme.bg} ${primaryTheme.text}`;
    const rowCount = Math.max(1, Math.min(8, Number(data.feature_row_count) || 8));
    const words = ["one", "two", "three", "four", "five", "six", "seven", "eight"].slice(0,rowCount);
    const save = (key) => (value) => onUpdate({ [key]: value });
    const options = [
        { key: "option_one", featured: false },
        { key: "option_two", featured: true },
        { key: "option_three", featured: false },
    ];

    return <section className={sparkTw(block, "section", `group/repeatable-section relative overflow-hidden px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 ${theme.bg}`)}>
        <div className={sparkTw(block, "wrapper", "mx-auto max-w-7xl")}>
            <div className={sparkTw(block, "wrapper_2", "max-w-3xl")}>
                <EditableText value={data.eyebrow} className={sparkTw(block, "text", `text-xs font-bold uppercase tracking-[.28em] ${muted}`)} onSave={save("eyebrow")} />
                <EditableText value={data.heading} cosmicType="h2" className={sparkTw(block, "text_2", `mt-5 block text-4xl font-semibold leading-[1.02] tracking-[-.045em] sm:text-5xl lg:text-6xl ${theme.text}`)} onSave={save("heading")} />
                <EditableText value={data.text} isTextArea className={sparkTw(block, "text_3", `mt-5 block max-w-2xl text-base leading-7 sm:text-lg ${muted}`)} onSave={save("text")} />
            </div>

            <div className={sparkTw(block, "wrapper_3", `mt-12 overflow-hidden rounded-[2rem] border shadow-sm ${border}`)}>
                <div className={sparkTw(block, "wrapper_4", `grid lg:grid-cols-[1.15fr_repeat(3,1fr)] ${baseCard}`)}>
                    <div className={sparkTw(block, "wrapper_5", `hidden border-b p-6 lg:block ${border}`)}>
                        <span className={sparkTw(block, "label", `text-xs font-bold uppercase tracking-[.22em] ${muted}`)}>Capabilities</span>
                    </div>
                    {options.map((option, optionIndex) => <article key={option.key} data-cosmic-contrast-surface={option.featured && !isPrimary ? "brand" : undefined} className={sparkTwItem(block, "options", optionIndex, "card", `group relative border-b p-6 sm:p-7 ${border} ${option.featured ? featuredCard : baseCard}`)}>
                        {option.featured && <EditableText value={data.option_two_badge} className={sparkTw(block, "text_4", `mb-5 inline-flex rounded-full px-3 py-1 text-[10px] font-black tracking-[.16em] ${primaryTheme.bg} ${primaryTheme.text}`)} onSave={save("option_two_badge")} />}
                        <EditableText value={data[`${option.key}_kicker`]} className={sparkTw(block, "text_5", `block text-[11px] font-bold uppercase tracking-[.2em] ${option.featured && !isPrimary ? "text-white/65" : muted}`)} onSave={save(`${option.key}_kicker`)} />
                        <EditableText value={data[`${option.key}_name`]} data-cosmic-preserve-heading-color={option.featured && !isPrimary ? "1" : undefined} className={sparkTw(block, "text_6", "mt-3 block text-2xl font-semibold tracking-[-.03em]")} onSave={save(`${option.key}_name`)} />
                        <EditableText value={data[`${option.key}_text`]} isTextArea className={sparkTw(block, "text_7", `mt-4 block text-sm leading-6 ${option.featured && !isPrimary ? "text-white/65" : muted}`)} onSave={save(`${option.key}_text`)} />
                    </article>)}

                    {words.map((word, rowIndex) => <div key={word} className={sparkTw(block, "wrapper_6", "group relative contents")}>
                        <div className={sparkTw(block, "wrapper_7", `group relative border-b p-5 pr-12 lg:p-6 lg:pr-12 ${border} ${baseCard}`)}>
                            <RepeatableRemoveButton hoverScope="item" overlay placement="row" label="Remove feature row" disabled={rowCount<=1} onRemove={()=>{const all=["one","two","three","four","five","six","seven","eight"];const patch={feature_row_count:rowCount-1};for(let x=rowIndex;x<rowCount-1;x++){const a=all[x],b=all[x+1];patch[`feature_${a}`]=data[`feature_${b}`]??"";options.forEach(({key})=>{patch[`${key}_${a}`]=data[`${key}_${b}`]??"";});}onUpdate(patch)}} />
                            <EditableText value={data[`feature_${word}`]} className={sparkTw(block, "text_8", "text-sm font-semibold")} onSave={save(`feature_${word}`)} />
                        </div>
                        {options.map((option, optionIndex) => <div key={`${word}-${option.key}`} data-cosmic-contrast-surface={option.featured && !isPrimary ? "brand" : undefined} className={sparkTwPath(block, ["features", rowIndex, "options", optionIndex], "cell", `group relative border-b p-5 text-sm lg:p-6 ${border} ${option.featured ? featuredCard : baseCard}`)}>
                            <EditableText value={data[`${option.key}_${word}`]} className={sparkTw(block, "feature_value", option.featured && !isPrimary ? "font-semibold text-white" : "font-semibold")} onSave={save(`${option.key}_${word}`)} />
                        </div>)}
                    </div>)}
                </div>
            </div>

            <RepeatableControls onAdd={()=>rowCount<8&&onUpdate({feature_row_count:rowCount+1})} canAdd={rowCount<8} showRemove={false} addLabel="Add feature row"/>
            <div className={sparkTw(block, "wrapper_9", "mt-8 flex flex-col items-center gap-4 text-center")}>
                <EditableButton label={data.primary_label} url={data.primary_url} className={sparkTw(block, "button", `inline-flex min-h-[48px] items-center justify-center rounded-full px-7 text-sm font-bold ${buttonClass}`)} onSave={(label, url) => onUpdate({ primary_label: label, primary_url: url })} />
                <EditableText value={data.footnote} isTextArea className={sparkTw(block, "text_9", `max-w-3xl text-xs leading-5 ${muted}`)} onSave={save("footnote")} />
            </div>
        </div>
    </section>;
}
