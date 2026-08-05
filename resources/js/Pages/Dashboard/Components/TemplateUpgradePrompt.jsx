import { useEffect } from "react";

function humanize(value) {
    return String(value || "")
        .replaceAll("_", " ")
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export default function TemplateUpgradePrompt({ template, currentPlanName, onClose }) {
    useEffect(() => {
        if (!template) return undefined;
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = "hidden";
        const closeOnEscape = (event) => event.key === "Escape" && onClose();
        window.addEventListener("keydown", closeOnEscape);
        return () => {
            document.body.style.overflow = previousOverflow;
            window.removeEventListener("keydown", closeOnEscape);
        };
    }, [template, onClose]);

    if (!template) return null;

    const upgrade = template.upgrade_prompt || null;
    const required = humanize(template.requiredLevel || template.minimum_plan || "pro");
    const family = upgrade?.family || (String(template.collection || "personal").startsWith("agency") ? "agency" : "personal");
    const goToPlans = () => window.location.assign(route("credits.index", {
        family,
        plan: upgrade?.plan_key || undefined,
        source: "template",
        template: template.slug || template.id,
    }));

    return (
        <div className="fixed inset-0 z-[980] flex items-center justify-center p-4">
            <button type="button" className="absolute inset-0 bg-black/85 backdrop-blur-sm" onClick={onClose} aria-label="Close upgrade prompt" />
            <section role="dialog" aria-modal="true" aria-labelledby="template-upgrade-title" className="relative w-full max-w-xl rounded-3xl border border-amber-200/15 bg-[#151519] p-6 text-slate-100 shadow-2xl shadow-black/60 sm:p-7">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <span className="inline-flex rounded-full border border-amber-200/20 bg-amber-200/10 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-amber-100">Template upgrade</span>
                        <h2 id="template-upgrade-title" className="mt-4 text-2xl font-semibold tracking-tight text-white">Unlock {template.name}</h2>
                        <p className="mt-2 text-sm leading-6 text-slate-400">{template.lockMessage || `This template requires the ${required} template tier.`}</p>
                    </div>
                    <button type="button" onClick={onClose} className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-lg text-slate-400 transition hover:bg-white/10 hover:text-white" aria-label="Close">×</button>
                </div>

                <div className="mt-5 grid gap-3 sm:grid-cols-2">
                    <div className="rounded-2xl border border-white/10 bg-white/[0.035] p-4">
                        <p className="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500">Current plan</p>
                        <p className="mt-2 text-lg font-semibold text-white">{currentPlanName || "Starter"}</p>
                        <p className="mt-1 text-xs leading-5 text-slate-500">Preview remains available. Using the template stays locked until the plan changes.</p>
                    </div>
                    <div className="rounded-2xl border border-violet-300/20 bg-violet-400/[0.07] p-4">
                        <p className="text-[10px] font-semibold uppercase tracking-[0.14em] text-violet-300">Recommended</p>
                        <p className="mt-2 text-lg font-semibold text-white">{upgrade?.label || required}</p>
                        {upgrade?.price_usd ? <p className="mt-1 text-sm font-medium text-violet-200">${upgrade.price_usd}/month · {upgrade.credits?.toLocaleString()} credits</p> : null}
                        <p className="mt-2 text-xs leading-5 text-slate-400">{upgrade?.description || "Compare the plans that include this template tier."}</p>
                    </div>
                </div>

                <div className="mt-6 flex flex-col-reverse gap-2 border-t border-white/10 pt-5 sm:flex-row sm:items-center sm:justify-between">
                    <button type="button" onClick={onClose} className="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white">Keep browsing</button>
                    <button type="button" onClick={goToPlans} className="rounded-xl bg-white px-5 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-slate-200">{upgrade?.action_label || "Compare plans"}</button>
                </div>
            </section>
        </div>
    );
}
