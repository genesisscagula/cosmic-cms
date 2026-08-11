import themeMetadata from "../../Websites/Theme/ThemeMetadata";
import StarterKitSparkPreview, { starterKitCompositionLabels } from "./StarterKitSparkPreview";

function TemplatePreview({ template, large = false }) {
    const fixedThemeFamily = template.themeFamily || template.theme_family || template.themeId || "midnight";
    const theme = themeMetadata.find((item) => item.id === fixedThemeFamily) || themeMetadata.find((item) => item.id === "midnight");
    const [accent, surface] = theme?.colors || ["#7c3aed", "#0f172a"];

    return (
        <div
            className={`relative overflow-hidden rounded-xl border border-white/10 p-3 ${large ? "h-36" : "h-32"}`}
            style={{
                background: `linear-gradient(135deg, ${accent}22, ${surface}18 52%, ${accent}12)`,
            }}
        >
            <div className="h-full overflow-hidden rounded-lg border border-white/10 bg-[#0d0d10]/85 shadow-inner">
                <StarterKitSparkPreview template={template} compact />
            </div>
        </div>
    );
}

function Badges({ template }) {
    return <div className="flex flex-wrap items-center gap-1.5">{template.featured && <span className="rounded-full bg-violet-400/12 px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-violet-200">Featured</span>}{template.isNew && <span className="rounded-full bg-cyan-400/10 px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-cyan-200">New</span>}{template.popularity && <span className="rounded-full bg-amber-400/10 px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-amber-200">Popular</span>}{template.premiumReady && <span className="rounded-full bg-emerald-400/10 px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-emerald-200">Premium ready</span>}</div>;
}

export function FeaturedTemplateCard({ template, onPreview, onUse }) {
    const composition = starterKitCompositionLabels(template, 3);
    return <article className="group flex flex-col gap-4 rounded-2xl border border-violet-400/20 bg-gradient-to-br from-violet-500/[0.10] to-white/[0.035] p-4 transition hover:border-violet-400/40 hover:shadow-xl hover:shadow-violet-950/20 sm:flex-row"><div className="sm:w-60"><TemplatePreview template={template} large /></div><div className="min-w-0 flex-1"><Badges template={template} /><p className="mt-2 text-xs font-medium text-slate-400">{template.category} · {template.industry}</p><h2 className="mt-1 text-lg font-semibold tracking-tight text-white">{template.name}</h2><p className="mt-1 line-clamp-2 text-sm text-slate-400">{template.description}</p>{composition.length > 0 && <div className="mt-3 flex flex-wrap gap-1.5">{composition.map((label) => <span key={label} className="rounded-full border border-white/10 bg-white/[0.035] px-2 py-1 text-[10px] font-medium text-slate-400">{label}</span>)}</div>}<div className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500"><span>{template.pageCount} page</span><span>{template.sparkCount} curated Sparks</span><span className="capitalize">{template.themeFamily || template.theme_family || template.themeId} family</span></div><div className="mt-4 flex gap-2"><button type="button" onClick={() => onPreview(template)} className="rounded-lg border border-white/10 px-3 py-2 text-xs font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400">Preview</button><button type="button" onClick={() => onUse(template)} className="rounded-lg bg-white px-3 py-2 text-xs font-semibold text-slate-950 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-400">Use Starter Kit</button></div></div></article>;
}

export default function TemplateCard({ template, onPreview, onUse }) {
    const composition = starterKitCompositionLabels(template, 2);
    return <article className="group relative rounded-2xl border border-white/10 bg-white/[0.035] p-3 transition hover:-translate-y-0.5 hover:border-violet-400/35 hover:bg-white/[0.055] hover:shadow-xl hover:shadow-black/20 focus-within:border-violet-400/55"><TemplatePreview template={template} /><div className="mt-3"><Badges template={template} /><div className="mt-2 flex items-start gap-2"><div className="min-w-0 flex-1"><div className="flex items-center gap-2"><h2 className="truncate text-sm font-semibold text-white">{template.name}</h2></div><p className="mt-1 truncate text-xs text-slate-500">{template.category} · {template.industry}</p></div></div>{composition.length > 0 && <div className="mt-2 flex min-h-5 flex-wrap gap-1">{composition.map((label) => <span key={label} className="rounded-full bg-white/[0.04] px-1.5 py-0.5 text-[9px] font-medium text-slate-500">{label}</span>)}</div>}<div className="mt-2 flex items-center justify-between text-[11px] text-slate-500"><span>{template.sparkCount || 0} Sparks</span><span className="capitalize">{template.themeFamily || template.theme_family || template.themeId}</span></div></div><div className="mt-3 flex gap-2"><button type="button" onClick={() => onPreview(template)} className="flex-1 rounded-lg border border-white/10 py-1.5 text-xs font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400">Preview</button><button type="button" onClick={() => onUse(template)} className="flex-1 rounded-lg bg-white py-1.5 text-xs font-semibold text-slate-950 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-400">Use</button></div></article>;
}
