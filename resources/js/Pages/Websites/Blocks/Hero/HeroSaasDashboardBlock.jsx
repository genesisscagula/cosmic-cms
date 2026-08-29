import { usePage } from "@inertiajs/react";

import { EditableButton } from "../Shared/EditableButton";
import { EditableText } from "../Shared/EditableText";
import { getEffectiveTheme } from "../../../../theme/Theme";
import { getHeroThemeState, resolveHeroThemeRequest } from "../../../../theme/heroTheme";
import { colorFamilies } from "../../../../theme/colorFamilies";
import { sparkTw, sparkTwItem } from "../Shared/sparkTailwindRuntime";

export const HeroSaasDashboardSchema = {
    type: "hero_saas_dashboard",
    title: "SaaS Dashboard Hero",
    category: "Hero",
    purpose: "Introduce a SaaS or digital product with product UI, metrics, and trust proof.",
    description: "A Pro-only SaaS hero with a dashboard mockup, live metrics, and customer logo strip.",
    tags: ["hero", "premium", "saas", "dashboard", "pro", "technology", "ai"],
    defaults: {
        eyebrow: "THE OPERATING SYSTEM FOR GROWTH",
        heading: "Turn your workflow into a clear, measurable advantage.",
        text: "Bring projects, performance, and customer momentum into one focused workspace built for modern teams.",
        primary_label: "Start building",
        primary_url: "#",
        secondary_label: "View product tour",
        secondary_url: "#",
        dashboard_title: "Workspace overview",
        dashboard_subtitle: "Live performance across your team",
        metric_one_value: "42%",
        metric_one_label: "Faster delivery",
        metric_two_value: "18.4k",
        metric_two_label: "Monthly actions",
        metric_three_value: "99.9%",
        metric_three_label: "Platform uptime",
        chart_label: "Growth this quarter",
        logo_one: "NORTHSTAR",
        logo_two: "ARC LABS",
        logo_three: "SCALEWORKS",
        logo_four: "FOUNDRY",
    },
};

export function HeroSaasDashboardBlock({ block, onUpdate, globalTheme }) {
    const { requestedTheme, theme } = getHeroThemeState(block, globalTheme);
    const primaryTheme = colorFamilies[globalTheme?.primary] || colorFamilies.emerald;
    const data = { ...HeroSaasDashboardSchema.defaults, ...block };
    const isPrimary = requestedTheme === "primary";
    const primaryButton = isPrimary ? "bg-white text-slate-950" : `${primaryTheme.bg} ${primaryTheme.text}`;

    const metrics = [
        ["metric_one_value", "metric_one_label"],
        ["metric_two_value", "metric_two_label"],
        ["metric_three_value", "metric_three_label"],
    ];

    return (
        <section className={sparkTw(block, "section", `relative flex items-center overflow-hidden px-6 py-0 sm:px-10 lg:px-14 ${theme.bg} transition-colors duration-500`)} style={{minHeight:"var(--cosmic-hero-fold-height, calc(100svh - 80px))"}}>
            <div className={sparkTw(block, "wrapper", "pointer-events-none absolute inset-x-0 top-0 h-64 bg-gradient-to-b from-white/10 to-transparent")} />
            <div className={sparkTw(block, "wrapper_2", "relative mx-auto w-full max-w-7xl")}>
                <div className={sparkTw(block, "wrapper_3", "mx-auto max-w-4xl text-center")}>
                    <EditableText value={data.eyebrow} className={sparkTw(block, "text", `text-xs font-bold uppercase tracking-[.28em] ${theme.sub}`)} onSave={(eyebrow)=>onUpdate({eyebrow})} />
                    <EditableText value={data.heading} cosmicType="h1" className={sparkTw(block, "text_2", `mt-5 block text-4xl font-semibold leading-[1] tracking-[-.045em] sm:text-5xl lg:text-6xl xl:text-7xl ${theme.text}`)} onSave={(heading)=>onUpdate({heading})} />
                    <EditableText value={data.text} isTextArea className={sparkTw(block, "text_3", `mx-auto mt-6 block max-w-2xl text-base leading-7 sm:text-lg sm:leading-8 ${theme.sub}`)} onSave={(text)=>onUpdate({text})} />
                    <div className={sparkTw(block, "wrapper_4", "mt-8 flex flex-col justify-center gap-3 sm:flex-row")}>
                        <EditableButton label={data.primary_label} url={data.primary_url} className={sparkTw(block, "button", `inline-flex min-h-[50px] items-center justify-center rounded-full px-7 font-bold ${primaryButton}`)} onSave={(primary_label,primary_url)=>onUpdate({primary_label,primary_url})}/>
                        <EditableButton label={data.secondary_label} url={data.secondary_url} className={sparkTw(block, "button_2", `inline-flex min-h-[50px] items-center justify-center rounded-full border px-7 font-bold ${theme.border} ${theme.text}`)} onSave={(secondary_label,secondary_url)=>onUpdate({secondary_label,secondary_url})}/>
                    </div>
                </div>

                <div className={sparkTw(block, "wrapper_5", `mt-14 overflow-hidden rounded-[2rem] border shadow-2xl ${theme.border}`)}>
                    <div className={sparkTw(block, "wrapper_6", `flex items-center justify-between border-b px-5 py-4 sm:px-7 ${theme.border} ${theme.surface}`)}>
                        <div>
                            <EditableText value={data.dashboard_title} className={sparkTw(block, "text_4", `block text-sm font-bold ${theme.text}`)} onSave={(dashboard_title)=>onUpdate({dashboard_title})}/>
                            <EditableText value={data.dashboard_subtitle} className={sparkTw(block, "text_5", `mt-1 block text-xs ${theme.sub}`)} onSave={(dashboard_subtitle)=>onUpdate({dashboard_subtitle})}/>
                        </div>
                        <div className={sparkTw(block, "wrapper_7", "flex gap-1.5")}><span className={sparkTw(block, "label", "h-2.5 w-2.5 rounded-full bg-rose-400")}/><span className={sparkTw(block, "label_2", "h-2.5 w-2.5 rounded-full bg-amber-400")}/><span className={sparkTw(block, "label_3", "h-2.5 w-2.5 rounded-full bg-emerald-400")}/></div>
                    </div>
                    <div className={sparkTw(block, "wrapper_8", `grid gap-0 lg:grid-cols-[240px_minmax(0,1fr)] ${theme.surface}`)}>
                        <aside className={sparkTw(block, "wrapper_9", `hidden border-r p-5 lg:block ${theme.border}`)}>
                            <div className={sparkTw(block, "wrapper_10", `rounded-xl px-3 py-2 text-xs font-semibold ${primaryTheme.card || primaryTheme.bg} ${primaryTheme.text}`)}>Overview</div>
                            {["Projects","Analytics","Customers","Automations"].map((label, index)=><div key={label} className={sparkTwItem(block, "nav_items", index, "item", sparkTw(block, "wrapper_11", `mt-2 rounded-xl px-3 py-2 text-xs ${theme.sub}`))}>{label}</div>)}
                        </aside>
                        <div className={sparkTw(block, "wrapper_12", "p-5 sm:p-7")}>
                            <div className={sparkTw(block, "wrapper_13", "grid gap-4 md:grid-cols-3")}>
                                {metrics.map(([valueKey,labelKey], index)=><div key={valueKey} className={sparkTwItem(block, "metrics", index, "card", sparkTw(block, "wrapper_14", `rounded-2xl border p-5 ${theme.border} ${theme.bg}`))}>
                                    <EditableText value={data[valueKey]} className={sparkTwItem(block, "metrics", index, "value", sparkTw(block, "text_6", `block text-3xl font-semibold tracking-tight ${theme.text}`))} onSave={(v)=>onUpdate({[valueKey]:v})}/>
                                    <EditableText value={data[labelKey]} className={sparkTwItem(block, "metrics", index, "label", sparkTw(block, "text_7", `mt-2 block text-xs font-medium ${theme.sub}`))} onSave={(v)=>onUpdate({[labelKey]:v})}/>
                                </div>)}
                            </div>
                            <div className={sparkTw(block, "wrapper_15", `mt-4 rounded-2xl border p-5 ${theme.border} ${theme.bg}`)}>
                                <EditableText value={data.chart_label} className={sparkTw(block, "text_8", `block text-sm font-semibold ${theme.text}`)} onSave={(chart_label)=>onUpdate({chart_label})}/>
                                <div className={sparkTw(block, "wrapper_16", "mt-7 flex h-40 items-end gap-2 sm:gap-3")}>
                                    {[38,58,48,72,66,88,78,96,84,100].map((height,index)=><div key={index} className={sparkTwItem(block, "chart_bars", index, "bar", sparkTw(block, "wrapper_17", `flex-1 rounded-t-lg ${primaryTheme.bg}`))} style={{height:`${height}%`,opacity:.42 + index*.045}} />)}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div className={sparkTw(block, "wrapper_18", `mt-8 border-t pt-7 text-center ${theme.border}`)}>
                    <p className={sparkTw(block, "body", `text-[11px] font-bold uppercase tracking-[.26em] ${theme.sub}`)}>Trusted by teams building what comes next</p>
                    <div className={sparkTw(block, "wrapper_19", "mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4")}>
                        {["logo_one","logo_two","logo_three","logo_four"].map((key, index)=><EditableText key={key} value={data[key]} className={sparkTwItem(block, "logos", index, "label", sparkTw(block, "text_9", `text-xs font-bold tracking-[.16em] ${theme.sub}`))} onSave={(v)=>onUpdate({[key]:v})}/>) }
                    </div>
                </div>
            </div>
        </section>
    );
}
