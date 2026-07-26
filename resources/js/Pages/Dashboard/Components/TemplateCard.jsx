import { useState } from "react";
import themeMetadata from "../../Websites/Theme/ThemeMetadata";

function TemplatePreview({ template, large = false }) {
    const theme = themeMetadata.find((item) => item.id === template.themeId) || themeMetadata.find((item) => item.id === "slate");
    const [primary, surface, text] = theme.colors;
    const isBlank = template.id === "blank";

    return (
        <div
            className={`relative overflow-hidden rounded-xl border border-white/10 ${large ? "h-32" : "h-24"}`}
            style={{ backgroundColor: surface }}
        >
            <div className="absolute inset-0 bg-gradient-to-br from-white/10 via-transparent to-black/20" />
            <div className="absolute inset-x-2.5 top-2.5 flex h-3 items-center gap-1 rounded-md bg-black/20 px-1.5">
                <span className="h-1 w-1 rounded-full bg-white/50" />
                <span className="h-1 w-1 rounded-full bg-white/35" />
                <span className="h-1 w-1 rounded-full bg-white/20" />
                <span className="ml-1 h-1 w-12 rounded-full bg-white/25" />
            </div>

            <div
                className="absolute inset-x-2.5 top-7 rounded-lg p-2.5"
                style={{ background: isBlank ? "rgba(15, 23, 42, 0.38)" : `linear-gradient(135deg, ${primary}, ${surface})` }}
            >
                <div className="h-1 w-8 rounded-full bg-white/65" />
                <div className="mt-1.5 h-2.5 w-3/5 rounded-sm bg-white/90" />
                <div className="mt-1 h-1 w-4/5 rounded-full bg-white/45" />
                {!isBlank && <span className="mt-2 block h-2.5 w-9 rounded-sm bg-white/90" />}
            </div>

            <div className="absolute inset-x-2.5 bottom-2.5 grid grid-cols-3 gap-1.5">
                {[0, 1, 2].map((item) => (
                    <span
                        key={item}
                        className="h-4 rounded border border-white/10 bg-white/15"
                        style={{ backgroundColor: item === 1 ? `${text}35` : undefined }}
                    />
                ))}
            </div>
        </div>
    );
}

export function FeaturedTemplateCard({ template, onPreview, onUse }) {
    return <article className="flex flex-col gap-4 rounded-2xl border border-violet-400/20 bg-gradient-to-br from-violet-500/[0.10] to-white/[0.035] p-4 sm:flex-row"><div className="sm:w-56"><TemplatePreview template={template} large /></div><div className="min-w-0 flex-1"><div className="flex items-center gap-2"><span className="rounded-full bg-violet-400/15 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-violet-200">Featured</span><span className="text-xs text-slate-400">{template.category}</span></div><h2 className="mt-2 text-lg font-semibold tracking-tight text-white">{template.name}</h2><p className="mt-1 text-sm text-slate-400">{template.description}</p><p className="mt-3 text-xs text-slate-500">{template.pageCount} pages · {template.industry}</p><div className="mt-4 flex gap-2"><button type="button" onClick={() => onPreview(template)} className="rounded-lg border border-white/10 px-3 py-2 text-xs font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400">Preview</button><button type="button" onClick={() => onUse(template)} className="rounded-lg bg-white px-3 py-2 text-xs font-semibold text-slate-950 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-400">Use Template</button></div></div></article>;
}

export default function TemplateCard({ template, onPreview, onUse, onSave, onDuplicate }) {
    const [menuOpen, setMenuOpen] = useState(false);
    return <article className="group relative rounded-2xl border border-white/10 bg-white/[0.035] p-3 transition hover:-translate-y-0.5 hover:border-violet-400/35 hover:bg-white/[0.055] hover:shadow-xl hover:shadow-black/20 focus-within:border-violet-400/55"><TemplatePreview template={template} /><div className="mt-3 flex items-start gap-2"><div className="min-w-0 flex-1"><div className="flex items-center gap-1.5"><h2 className="truncate text-sm font-semibold text-white">{template.name}</h2>{template.isNew && <span className="rounded-full bg-cyan-400/10 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-cyan-200">New</span>}{template.featured && <span className="rounded-full bg-violet-400/10 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-violet-200">Featured</span>}</div><p className="mt-1 text-xs text-slate-500">{template.category} · {template.pageCount} pages</p></div><button type="button" aria-label={`More actions for ${template.name}`} aria-expanded={menuOpen} onClick={() => setMenuOpen((value) => !value)} className="rounded-lg p-1 text-slate-500 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400">•••</button></div><div className="mt-3 flex gap-2"><button type="button" onClick={() => onPreview(template)} className="flex-1 rounded-lg border border-white/10 py-1.5 text-xs font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400">Preview</button><button type="button" onClick={() => onUse(template)} className="flex-1 rounded-lg bg-white py-1.5 text-xs font-semibold text-slate-950 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-400">Use</button></div>{menuOpen && <div className="absolute right-3 top-32 z-10 w-32 rounded-xl border border-white/10 bg-[#1a1a1d] p-1 shadow-2xl shadow-black/40"><button type="button" onClick={() => { onSave(template); setMenuOpen(false); }} className="w-full rounded-lg px-3 py-2 text-left text-xs font-medium text-slate-200 transition hover:bg-white/10 focus:bg-white/10 focus:outline-none">Save</button><button type="button" onClick={() => { onDuplicate(template); setMenuOpen(false); }} className="w-full rounded-lg px-3 py-2 text-left text-xs font-medium text-slate-200 transition hover:bg-white/10 focus:bg-white/10 focus:outline-none">Duplicate</button></div>}</article>;
}
