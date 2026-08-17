export default function OverviewStatCard({ label, value, detail, accent, icon = "✦" }) {
    return (
        <article className="cosmic-account-stat">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="cosmic-performance-label">{label}</p>
                    <p className="mt-2 truncate text-2xl font-semibold tracking-tight cosmic-text-strong" title={String(value)}>{value}</p>
                </div>
                <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ${accent} text-sm`} aria-hidden="true">{icon}</span>
            </div>
            <p className="mt-3 text-xs cosmic-text-muted">{detail}</p>
        </article>
    );
}
