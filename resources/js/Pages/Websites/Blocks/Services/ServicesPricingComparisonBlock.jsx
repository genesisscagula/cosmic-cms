import { EditableButton } from "../Shared/EditableButton";
import { EditableText } from "../Shared/EditableText";
import { getEffectiveTheme } from "../../../../theme/Theme";
import { colorFamilies } from "../../../../theme/colorFamilies";
import { BoundedCountControls } from "../Shared/RepeatableControls";

export const ServicesPricingComparisonSchema = {
    type: "services_pricing_comparison",
    title: "Pricing Comparison Premium",
    category: "Services",
    purpose: "Compare three premium service packages through a clear feature matrix.",
    description: "A Pro-only pricing comparison section with editable plan positioning, six comparison rows, and conversion actions.",
    tags: ["services", "pricing", "comparison", "premium", "plans", "pro"],
    defaults: {
        eyebrow: "CHOOSE THE RIGHT LEVEL OF SUPPORT",
        heading: "Clear packages. No hidden complexity.",
        text: "Compare the level of strategy, delivery, and ongoing support included in each engagement.",
        starter_name: "Essential", starter_price: "$2,500", starter_period: "from", starter_description: "A focused foundation for one clear business priority.", starter_button_label: "Choose Essential", starter_button_url: "#",
        growth_name: "Growth", growth_price: "$6,500", growth_period: "from", growth_description: "A complete growth engagement for ambitious teams.", growth_button_label: "Choose Growth", growth_button_url: "#", growth_badge: "MOST POPULAR",
        pro_name: "Partner", pro_price: "Custom", pro_period: "", pro_description: "Embedded senior support for complex, ongoing work.", pro_button_label: "Talk to our team", pro_button_url: "#",
        feature_one: "Strategic discovery", starter_one: "Included", growth_one: "Extended", pro_one: "Ongoing",
        feature_two: "Design direction", starter_two: "1 concept", growth_two: "3 concepts", pro_two: "Unlimited scope",
        feature_three: "Delivery support", starter_three: "Launch", growth_three: "Launch + optimise", pro_three: "Embedded team",
        feature_four: "Reporting", starter_four: "Summary", growth_four: "Monthly", pro_four: "Custom dashboard",
        feature_five: "Response time", starter_five: "3 business days", growth_five: "1 business day", pro_five: "Priority",
        feature_six: "Best for", starter_six: "Focused projects", growth_six: "Growing teams", pro_six: "Complex programmes",
        footnote: "Every engagement is tailored before work begins. Prices shown are editable starting points.",
    },
};

export function ServicesPricingComparisonBlock({ block, onUpdate, globalTheme }) {
    const theme = getEffectiveTheme(block.theme && block.theme !== "auto" ? block.theme : block.resolvedTheme, globalTheme);
    const primaryTheme = colorFamilies[globalTheme?.primary] || colorFamilies.emerald;
    const data = { ...ServicesPricingComparisonSchema.defaults, ...block };
    const isPrimary = (block.theme && block.theme !== "auto" ? block.theme : block.resolvedTheme) === "primary";
    const muted = isPrimary ? "text-white/70" : theme.sub;
    const featuredMuted = isPrimary ? "text-slate-600" : primaryTheme.sub;
    const border = isPrimary ? "border-white/20" : theme.border;
    const baseCard = isPrimary ? "bg-white/10 text-white" : `${theme.surface} ${theme.text}`;
    const featuredCard = isPrimary ? "bg-white text-slate-950" : `${primaryTheme.card || primaryTheme.bg} ${primaryTheme.text}`;
    const normalButton = isPrimary ? "bg-white text-slate-950" : `${primaryTheme.bg} ${primaryTheme.text}`;
    const featuredButton = isPrimary ? `${primaryTheme.bg} ${primaryTheme.text}` : `${primaryTheme.bg} ${primaryTheme.text}`;
    const rowCount = Math.max(1, Math.min(6, Number(data.comparison_row_count) || 6));
    const rows = [1,2,3,4,5,6].slice(0,rowCount).map((n)=>({
        label: `feature_${['one','two','three','four','five','six'][n-1]}`,
        starter: `starter_${['one','two','three','four','five','six'][n-1]}`,
        growth: `growth_${['one','two','three','four','five','six'][n-1]}`,
        pro: `pro_${['one','two','three','four','five','six'][n-1]}`,
    }));
    const plans = [
        { key:"starter", name:data.starter_name, price:data.starter_price, period:data.starter_period, description:data.starter_description, label:data.starter_button_label, url:data.starter_button_url },
        { key:"growth", name:data.growth_name, price:data.growth_price, period:data.growth_period, description:data.growth_description, label:data.growth_button_label, url:data.growth_button_url, featured:true },
        { key:"pro", name:data.pro_name, price:data.pro_price, period:data.pro_period, description:data.pro_description, label:data.pro_button_label, url:data.pro_button_url },
    ];
    const save=(key)=>(value)=>onUpdate({[key]:value});
    return <section className={`relative overflow-hidden px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 ${theme.bg}`}>
        <div className="mx-auto max-w-7xl">
            <div className="max-w-3xl">
                <EditableText value={data.eyebrow} className={`text-xs font-bold uppercase tracking-[.28em] ${muted}`} onSave={save('eyebrow')}/>
                <EditableText value={data.heading} className={`mt-5 block text-4xl font-semibold leading-[1.02] tracking-[-.045em] sm:text-5xl lg:text-6xl ${theme.text}`} onSave={save('heading')}/>
                <EditableText value={data.text} isTextArea className={`mt-5 block max-w-2xl text-base leading-7 sm:text-lg ${muted}`} onSave={save('text')}/>
            </div>
            <div className="mt-12 overflow-hidden rounded-[2rem] border shadow-sm" style={{borderColor:'currentColor'}}>
                <div className={`grid lg:grid-cols-[1.15fr_repeat(3,1fr)] ${baseCard}`}>
                    <div className={`hidden border-b p-6 lg:block ${border}`}><span className={`text-xs font-bold uppercase tracking-[.22em] ${muted}`}>Compare packages</span></div>
                    {plans.map((plan)=><article key={plan.key} className={`relative border-b p-6 sm:p-7 ${border} ${plan.featured?featuredCard:baseCard}`}>
                        {plan.featured && <EditableText value={data.growth_badge} className={`mb-5 inline-flex rounded-full px-3 py-1 text-[10px] font-black tracking-[.16em] ${primaryTheme.bg} ${primaryTheme.text}`} onSave={save('growth_badge')}/>} 
                        <EditableText value={plan.name} className="block text-xl font-semibold" onSave={save(`${plan.key}_name`)}/>
                        <div className="mt-4 flex items-end gap-2"><EditableText value={plan.price} className="block text-4xl font-semibold tracking-[-.04em]" onSave={save(`${plan.key}_price`)}/><EditableText value={plan.period} className={`mb-1 text-xs font-semibold uppercase tracking-wider ${plan.featured?featuredMuted:muted}`} onSave={save(`${plan.key}_period`)}/></div>
                        <EditableText value={plan.description} isTextArea className={`mt-4 block text-sm leading-6 ${plan.featured?featuredMuted:muted}`} onSave={save(`${plan.key}_description`)}/>
                        <EditableButton label={plan.label} url={plan.url} className={`mt-6 inline-flex min-h-[46px] w-full items-center justify-center rounded-full px-5 text-sm font-bold ${plan.featured?featuredButton:normalButton}`} onSave={(label,url)=>onUpdate({[`${plan.key}_button_label`]:label,[`${plan.key}_button_url`]:url})}/>
                    </article>)}
                    {rows.map((row,index)=><div key={row.label} className="contents">
                        <div className={`border-b p-5 lg:p-6 ${border} ${baseCard}`}><EditableText value={data[row.label]} className="text-sm font-semibold" onSave={save(row.label)}/></div>
                        {['starter','growth','pro'].map((key)=><div key={key} className={`border-b p-5 text-sm lg:p-6 ${border} ${key==='growth'?featuredCard:baseCard}`}><EditableText value={data[row[key]]} className={`font-semibold ${key==='growth'?featuredMuted:''}`} onSave={save(row[key])}/></div>)}
                    </div>)}
                </div>
            </div>
            <BoundedCountControls count={rowCount} min={1} max={6} addLabel="Add comparison row" removeLabel="Remove last row" onChange={comparison_row_count=>onUpdate({comparison_row_count})}/>
            <EditableText value={data.footnote} isTextArea className={`mx-auto mt-6 block max-w-3xl text-center text-xs leading-5 ${muted}`} onSave={save('footnote')}/>
        </div>
    </section>;
}
