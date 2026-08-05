import { useEffect, useMemo, useState } from "react";

const viewportOptions = [
    { id: "desktop", label: "Desktop", width: "100%" },
    { id: "tablet", label: "Tablet", width: "760px" },
    { id: "mobile", label: "Mobile", width: "390px" },
];

function humanize(value) {
    return String(value || "")
        .replaceAll("_", " ")
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export default function TemplatePreviewModal({ template, currentPlanName, onClose, onUse }) {
    const [viewport, setViewport] = useState("desktop");
    const activeViewport = viewportOptions.find((item) => item.id === viewport) || viewportOptions[0];

    const requiredPlan = useMemo(
        () => humanize(template?.requiredLevel || template?.minimum_plan || "pro"),
        [template],
    );

    useEffect(() => {
        if (!template) return undefined;

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = "hidden";

        const handleKeyDown = (event) => {
            if (event.key === "Escape") onClose();
        };

        window.addEventListener("keydown", handleKeyDown);

        return () => {
            document.body.style.overflow = previousOverflow;
            window.removeEventListener("keydown", handleKeyDown);
        };
    }, [template, onClose]);

    if (!template) return null;

    const goToPlans = () => {
        window.location.assign(route("credits.index", { family: String(template.collection || "personal").startsWith("agency") ? "agency" : "personal" }));
    };

    return (
        <div className="fixed inset-0 z-[950] flex items-center justify-center p-2 sm:p-5">
            <button type="button" className="absolute inset-0 bg-black/85 backdrop-blur-sm" onClick={onClose} aria-label="Close template preview" />

            <section role="dialog" aria-modal="true" aria-labelledby="template-preview-title" className="relative z-10 flex max-h-[96vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-white/10 bg-[#101014] text-white shadow-2xl sm:rounded-3xl">
                <header className="flex flex-col gap-4 border-b border-white/10 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="text-[10px] font-semibold uppercase tracking-[0.22em] text-violet-300">Template preview</p>
                            {template.locked && <span className="rounded-full border border-amber-300/20 bg-amber-300/10 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-amber-100">Preview only</span>}
                        </div>
                        <h2 id="template-preview-title" className="mt-1 truncate text-xl font-semibold sm:text-2xl">{template.name}</h2>
                    </div>

                    <div className="flex items-center justify-between gap-2 sm:justify-end">
                        <div className="flex rounded-xl border border-white/10 bg-white/[0.035] p-1" aria-label="Preview viewport">
                            {viewportOptions.map((option) => <button key={option.id} type="button" onClick={() => setViewport(option.id)} aria-pressed={viewport === option.id} className={`rounded-lg px-3 py-1.5 text-[11px] font-semibold transition ${viewport === option.id ? "bg-white text-slate-950" : "text-slate-400 hover:text-white"}`}>{option.label}</button>)}
                        </div>
                        <button type="button" onClick={onClose} className="rounded-xl border border-white/10 px-3 py-2 text-slate-400 transition hover:bg-white/10 hover:text-white" aria-label="Close preview">✕</button>
                    </div>
                </header>

                {template.locked && (
                    <div className="border-b border-amber-300/15 bg-amber-300/[0.06] px-4 py-3 sm:px-6">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p className="text-sm font-semibold text-amber-100">Available on {requiredPlan} or higher</p>
                                <p className="mt-1 text-xs leading-5 text-amber-100/65">You can inspect the full responsive layout on your {currentPlanName || "current"} plan. Creating a website from it remains locked.</p>
                            </div>
                            <button type="button" onClick={goToPlans} className="shrink-0 rounded-xl border border-amber-200/25 bg-amber-200/10 px-4 py-2 text-xs font-semibold text-amber-50 transition hover:bg-amber-200/15">Compare plans</button>
                        </div>
                    </div>
                )}

                <div className="min-h-0 flex-1 overflow-auto bg-[#08080b] p-3 sm:p-6">
                    <div className="mx-auto overflow-hidden rounded-xl border border-white/10 bg-slate-950 shadow-2xl transition-[width] duration-300" style={{ width: activeViewport.width, maxWidth: "100%" }}>
                        <div className="flex h-8 items-center gap-1.5 border-b border-white/10 bg-[#17171c] px-3">
                            <span className="h-2.5 w-2.5 rounded-full bg-white/15" /><span className="h-2.5 w-2.5 rounded-full bg-white/15" /><span className="h-2.5 w-2.5 rounded-full bg-white/15" />
                            <div className="ml-3 h-4 flex-1 rounded bg-white/[0.06]" />
                        </div>

                        <div className={`bg-gradient-to-br ${template.previewClass || "from-slate-900 via-slate-800 to-violet-950"}`}>
                            <nav className="flex items-center justify-between border-b border-white/10 px-5 py-4 sm:px-8"><span className="text-sm font-bold tracking-tight">{template.name}</span><div className="hidden gap-5 text-[10px] font-medium text-white/65 sm:flex"><span>About</span><span>Services</span><span>Contact</span></div><span className="rounded-lg bg-white px-3 py-1.5 text-[10px] font-bold text-slate-950">Get started</span></nav>
                            <div className="px-6 py-14 sm:px-10 sm:py-20">
                                <span className="rounded-full border border-white/20 bg-black/20 px-3 py-1 text-[10px] font-semibold">{template.industry}</span>
                                <h3 className="mt-5 max-w-3xl text-3xl font-bold tracking-tight sm:text-5xl">A polished starting point for your next website.</h3>
                                <p className="mt-4 max-w-xl text-sm leading-6 text-white/70">Responsive layout using the {template.themeId || "midnight"} family and {template.sparkCount || 0} curated Cosmic Sparks.</p>
                                <div className="mt-7 flex flex-wrap gap-3"><span className="rounded-xl bg-white px-4 py-2 text-xs font-semibold text-slate-950">Primary action</span><span className="rounded-xl border border-white/20 px-4 py-2 text-xs font-semibold">Learn more</span></div>
                            </div>
                        </div>

                        <div className="grid gap-3 bg-[#111116] p-4 sm:grid-cols-3 sm:p-6">{["Hero & navigation", "Content & proof", "CTA & contact"].map((label, index) => <div key={label} className="rounded-xl border border-white/10 bg-white/[0.035] p-4"><div className={`h-24 rounded-lg bg-gradient-to-br ${index === 0 ? "from-white/10 to-violet-400/10" : index === 1 ? "from-white/10 to-cyan-400/10" : "from-white/10 to-amber-300/10"}`} /><p className="mt-4 text-sm font-semibold">{label}</p><div className="mt-3 space-y-2"><div className="h-2 rounded bg-white/10"/><div className="h-2 w-4/5 rounded bg-white/10"/></div></div>)}</div>
                    </div>
                </div>

                <footer className="flex flex-col gap-3 border-t border-white/10 bg-[#111116] px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div><p className="text-xs text-slate-400">{template.pageCount || 1} page · {template.sparkCount || 0} Sparks · {humanize(template.themeId || "midnight")} family</p><p className="mt-1 text-[11px] text-slate-600">Previewing never consumes credits and does not install the template.</p></div>
                    <div className="flex gap-2"><button type="button" onClick={onClose} className="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-slate-300 transition hover:bg-white/10">Close</button>{template.locked ? <button type="button" onClick={goToPlans} className="rounded-xl bg-amber-200 px-5 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-amber-100">Upgrade to use</button> : <button type="button" onClick={() => onUse(template)} className="rounded-xl bg-white px-5 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-slate-200">Use this template</button>}</div>
                </footer>
            </section>
        </div>
    );
}
