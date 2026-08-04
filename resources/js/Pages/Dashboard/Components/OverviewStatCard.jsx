export default function OverviewStatCard({ label, value, detail, accent, icon = "✦" }) {
    return (
        <article className="rounded-2xl border border-white/10 bg-white/[0.035] p-4 transition hover:border-white/20 hover:bg-white/[0.055]">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-xs font-medium uppercase tracking-[0.14em] text-slate-500">{label}</p>
                    <p className="mt-2 truncate text-2xl font-semibold tracking-tight text-white" title={String(value)}>{value}</p>
                </div>
                <span className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ${accent} text-sm`} aria-hidden="true">{icon}</span>
            </div>
            <p className="mt-3 text-xs text-slate-400">{detail}</p>
        </article>
    );
}
