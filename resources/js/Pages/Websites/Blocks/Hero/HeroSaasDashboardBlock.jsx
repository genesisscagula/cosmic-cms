import { usePage } from "@inertiajs/react";

import { EditableButton } from "../Shared/EditableButton";
import { EditableText } from "../Shared/EditableText";
import { getEffectiveTheme } from "../../../../theme/Theme";
import { getHeroThemeState, resolveHeroThemeRequest } from "../../../../theme/heroTheme";
import { colorFamilies } from "../../../../theme/colorFamilies";

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
        <section className={`relative overflow-hidden px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 ${theme.bg} transition-colors duration-500`}>
            <div className="pointer-events-none absolute inset-x-0 top-0 h-64 bg-gradient-to-b from-white/10 to-transparent" />
            <div className="relative mx-auto max-w-7xl">
                <div className="mx-auto max-w-4xl text-center">
                    <EditableText value={data.eyebrow} className={`text-xs font-bold uppercase tracking-[.28em] ${theme.sub}`} onSave={(eyebrow)=>onUpdate({eyebrow})} />
                    <EditableText value={data.heading} className={`mt-5 block text-5xl font-semibold leading-[.98] tracking-[-.05em] sm:text-6xl lg:text-7xl ${theme.text}`} onSave={(heading)=>onUpdate({heading})} />
                    <EditableText value={data.text} isTextArea className={`mx-auto mt-6 block max-w-2xl text-base leading-7 sm:text-lg sm:leading-8 ${theme.sub}`} onSave={(text)=>onUpdate({text})} />
                    <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                        <EditableButton label={data.primary_label} url={data.primary_url} className={`inline-flex min-h-[50px] items-center justify-center rounded-full px-7 font-bold ${primaryButton}`} onSave={(primary_label,primary_url)=>onUpdate({primary_label,primary_url})}/>
                        <EditableButton label={data.secondary_label} url={data.secondary_url} className={`inline-flex min-h-[50px] items-center justify-center rounded-full border px-7 font-bold ${theme.border} ${theme.text}`} onSave={(secondary_label,secondary_url)=>onUpdate({secondary_label,secondary_url})}/>
                    </div>
                </div>

                <div className={`mt-14 overflow-hidden rounded-[2rem] border shadow-2xl ${theme.border}`}>
                    <div className={`flex items-center justify-between border-b px-5 py-4 sm:px-7 ${theme.border} ${theme.surface}`}>
                        <div>
                            <EditableText value={data.dashboard_title} className={`block text-sm font-bold ${theme.text}`} onSave={(dashboard_title)=>onUpdate({dashboard_title})}/>
                            <EditableText value={data.dashboard_subtitle} className={`mt-1 block text-xs ${theme.sub}`} onSave={(dashboard_subtitle)=>onUpdate({dashboard_subtitle})}/>
                        </div>
                        <div className="flex gap-1.5"><span className="h-2.5 w-2.5 rounded-full bg-rose-400"/><span className="h-2.5 w-2.5 rounded-full bg-amber-400"/><span className="h-2.5 w-2.5 rounded-full bg-emerald-400"/></div>
                    </div>
                    <div className={`grid gap-0 lg:grid-cols-[240px_minmax(0,1fr)] ${theme.surface}`}>
                        <aside className={`hidden border-r p-5 lg:block ${theme.border}`}>
                            <div className={`rounded-xl px-3 py-2 text-xs font-semibold ${primaryTheme.card || primaryTheme.bg} ${primaryTheme.text}`}>Overview</div>
                            {["Projects","Analytics","Customers","Automations"].map((label)=><div key={label} className={`mt-2 rounded-xl px-3 py-2 text-xs ${theme.sub}`}>{label}</div>)}
                        </aside>
                        <div className="p-5 sm:p-7">
                            <div className="grid gap-4 md:grid-cols-3">
                                {metrics.map(([valueKey,labelKey])=><div key={valueKey} className={`rounded-2xl border p-5 ${theme.border} ${theme.bg}`}>
                                    <EditableText value={data[valueKey]} className={`block text-3xl font-semibold tracking-tight ${theme.text}`} onSave={(v)=>onUpdate({[valueKey]:v})}/>
                                    <EditableText value={data[labelKey]} className={`mt-2 block text-xs font-medium ${theme.sub}`} onSave={(v)=>onUpdate({[labelKey]:v})}/>
                                </div>)}
                            </div>
                            <div className={`mt-4 rounded-2xl border p-5 ${theme.border} ${theme.bg}`}>
                                <EditableText value={data.chart_label} className={`block text-sm font-semibold ${theme.text}`} onSave={(chart_label)=>onUpdate({chart_label})}/>
                                <div className="mt-7 flex h-40 items-end gap-2 sm:gap-3">
                                    {[38,58,48,72,66,88,78,96,84,100].map((height,index)=><div key={index} className={`flex-1 rounded-t-lg ${primaryTheme.bg}`} style={{height:`${height}%`,opacity:.42 + index*.045}} />)}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div className={`mt-8 border-t pt-7 text-center ${theme.border}`}>
                    <p className={`text-[11px] font-bold uppercase tracking-[.26em] ${theme.sub}`}>Trusted by teams building what comes next</p>
                    <div className="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
                        {["logo_one","logo_two","logo_three","logo_four"].map((key)=><EditableText key={key} value={data[key]} className={`text-xs font-bold tracking-[.16em] ${theme.sub}`} onSave={(v)=>onUpdate({[key]:v})}/>) }
                    </div>
                </div>
            </div>
        </section>
    );
}
