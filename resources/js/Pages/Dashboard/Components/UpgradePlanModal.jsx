import { useEffect } from "react";

const AGENCY_OPTIONS = [
    {
        key: "agency_starter",
        label: "Starter Agency",
        price: "$99/mo",
        sites: "Up to 3 websites",
        credits: "500 credits / month",
        description: "For freelancers managing a small client portfolio.",
    },
    {
        key: "agency_growth",
        label: "Growth Agency",
        price: "$199/mo",
        sites: "Up to 10 websites",
        credits: "1,500 credits / month",
        description: "For growing teams managing multiple active clients.",
        featured: true,
    },
    {
        key: "agency_pro",
        label: "Pro Agency",
        price: "$399/mo",
        sites: "Unlimited websites",
        credits: "5,000 credits / month",
        description: "Full agency operations, insights, teams, and white label.",
    },
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

    const choosePlan = (planKey) => {
        window.location.assign(route("credits.index", {
            family: "agency",
            plan: planKey,
            source: "website-limit",
        }));
    };

    return (
        <div className="fixed inset-0 z-[110] flex items-center justify-center bg-slate-950/65 p-4 backdrop-blur-sm" role="presentation">
            <button type="button" aria-label="Close upgrade plan dialog" onClick={onClose} className="absolute inset-0 cursor-default" />
            <section role="dialog" aria-modal="true" aria-labelledby="upgrade-plan-title" className="relative max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-3xl border border-slate-200 bg-white p-5 text-slate-950 shadow-2xl shadow-slate-950/30 sm:p-7">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">More websites</p>
                        <h2 id="upgrade-plan-title" className="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Upgrade your workspace</h2>
                        <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Your {capabilities.plan_label || "Personal"} plan includes {capabilities.max_sites_label || 1} website. Choose an Agency plan to add more client websites now.</p>
                    </div>
                    <button type="button" onClick={onClose} className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 text-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-950 focus:outline-none focus:ring-2 focus:ring-emerald-500" aria-label="Close">×</button>
                </div>

                <div className="mt-5 flex flex-wrap items-center gap-2 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                    <span className="rounded-full border border-amber-200 bg-white px-3 py-1 text-xs font-semibold text-amber-800">{capabilities.site_count ?? 1} of {capabilities.max_sites_label || 1} used</span>
                    <p className="text-sm text-amber-800">{capabilities.upgrade_message || "Switch to an Agency plan to manage multiple websites."}</p>
                </div>

                <div className="mt-6 grid gap-3 md:grid-cols-3">
                    {AGENCY_OPTIONS.map((option) => (
                        <article key={option.key} className={`relative flex min-h-64 flex-col rounded-2xl border p-4 transition ${option.featured ? "border-emerald-400 bg-emerald-50/70 shadow-lg shadow-emerald-900/5" : "border-slate-200 bg-slate-50/70 hover:border-emerald-300"}`}>
                            {option.featured && <span className="absolute right-3 top-3 rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-800">Popular</span>}
                            <p className="pr-16 text-xs font-semibold uppercase tracking-[0.14em] text-emerald-700">{option.label}</p>
                            <div className="mt-3 flex items-end justify-between gap-3">
                                <h3 className="text-xl font-semibold text-slate-950">{option.sites}</h3>
                                <span className="shrink-0 text-sm font-bold text-slate-950">{option.price}</span>
                            </div>
                            <p className="mt-2 text-xs font-semibold text-emerald-700">{option.credits}</p>
                            <p className="mt-3 text-sm leading-6 text-slate-500">{option.description}</p>
                            <button type="button" onClick={() => choosePlan(option.key)} className={`mt-auto rounded-xl px-4 py-2.5 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 ${option.featured ? "bg-emerald-700 text-white hover:bg-emerald-800" : "border border-emerald-700 bg-white text-emerald-800 hover:bg-emerald-50"}`}>
                                Choose {option.label}
                            </button>
                        </article>
                    ))}
                </div>

                <div className="mt-6 flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-xs leading-5 text-slate-500">You will review the selected plan before secure checkout opens.</p>
                    <button type="button" onClick={onClose} className="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">Not now</button>
                </div>
            </section>
        </div>
    );
}
