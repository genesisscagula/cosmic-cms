import { useEffect } from "react";

const DEFAULT_OPTIONS = [
    { key: "business", label: "Business", sites: "3 websites", description: "Manage several business websites from one account." },
    { key: "agency", label: "Agency", sites: "10 websites", description: "Built for growing client work and shared operations." },
    { key: "agency_pro", label: "Agency Pro", sites: "Unlimited websites", description: "Maximum capacity for established agencies." },
];

export default function UpgradePlanModal({ open, onClose, capabilities = {} }) {
    useEffect(() => {
        if (!open) return undefined;
        const handleKeyDown = (event) => {
            if (event.key === "Escape") onClose();
        };
        window.addEventListener("keydown", handleKeyDown);
        return () => window.removeEventListener("keydown", handleKeyDown);
    }, [open, onClose]);

    if (!open) return null;

    const options = capabilities.upgrade_options?.length ? capabilities.upgrade_options : DEFAULT_OPTIONS;

    return (
        <div className="fixed inset-0 z-[110] flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm" role="presentation">
            <button type="button" aria-label="Close upgrade plan dialog" onClick={onClose} className="absolute inset-0 cursor-default" />
            <section role="dialog" aria-modal="true" aria-labelledby="upgrade-plan-title" className="relative max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-3xl border border-white/10 bg-[#151519] p-5 text-slate-100 shadow-2xl shadow-black/60 sm:p-7">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-violet-300">More websites</p>
                        <h2 id="upgrade-plan-title" className="mt-2 text-2xl font-semibold tracking-tight text-white">Upgrade your workspace</h2>
                        <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-400">Your {capabilities.plan_label || "Personal"} plan includes {capabilities.max_sites_label || 1} website. Business and Agency checkout will be available in the next billing update.</p>
                    </div>
                    <button type="button" onClick={onClose} className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-lg text-slate-400 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400" aria-label="Close">×</button>
                </div>

                <div className="mt-5 flex flex-wrap items-center gap-2 rounded-2xl border border-amber-300/15 bg-amber-300/[0.06] p-4">
                    <span className="rounded-full border border-amber-200/15 bg-black/20 px-3 py-1 text-xs font-semibold text-amber-100">{capabilities.site_count ?? 1} of {capabilities.max_sites_label || 1} used</span>
                    <p className="text-sm text-amber-100/75">{capabilities.upgrade_message || "Upgrade to add more websites."}</p>
                </div>

                <div className="mt-6 grid gap-3 md:grid-cols-3">
                    {options.map((option) => (
                        <article key={option.key} className="flex min-h-48 flex-col rounded-2xl border border-white/10 bg-white/[0.035] p-4 transition hover:border-violet-300/25 hover:bg-violet-400/[0.055]">
                            <p className="text-xs font-semibold uppercase tracking-[0.14em] text-violet-300">{option.label}</p>
                            <h3 className="mt-3 text-xl font-semibold text-white">{option.sites}</h3>
                            <p className="mt-2 text-sm leading-6 text-slate-400">{option.description}</p>
                            <span className="mt-auto pt-5 text-xs font-semibold text-slate-500">Coming soon</span>
                        </article>
                    ))}
                </div>

                <div className="mt-6 flex flex-col-reverse gap-2 border-t border-white/10 pt-5 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-xs leading-5 text-slate-500">No plan or payment changes will be made from this preview.</p>
                    <button type="button" onClick={onClose} className="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-400 focus:ring-offset-2 focus:ring-offset-[#151519]">Got it</button>
                </div>
            </section>
        </div>
    );
}
