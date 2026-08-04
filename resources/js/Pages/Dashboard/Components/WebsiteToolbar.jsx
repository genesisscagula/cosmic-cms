export default function WebsiteToolbar({ query, onQueryChange, onCreate, planCapabilities = {} }) {
    const locked = planCapabilities.can_add_sites === false;

    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
            <label className="relative min-w-0 flex-1">
                <span className="sr-only">Search websites</span>
                <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-500" aria-hidden="true">⌕</span>
                <input value={query} onChange={(event) => onQueryChange(event.target.value)} placeholder="Search websites..." className="h-10 w-full rounded-xl border border-white/10 bg-white/[0.04] py-2 pl-10 pr-4 text-sm text-white outline-none transition placeholder:text-slate-500 hover:border-white/20 focus:border-violet-400/70 focus:ring-2 focus:ring-violet-400/15" />
            </label>
            <button type="button" onClick={onCreate} aria-disabled={locked} title={locked ? planCapabilities.upgrade_message : "Create a new website"} className={`inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-xl px-4 text-sm font-semibold shadow-sm transition focus:outline-none focus:ring-2 focus:ring-violet-400 focus:ring-offset-2 focus:ring-offset-[#0a0a0b] ${locked ? "cursor-pointer border border-amber-300/20 bg-amber-300/10 text-amber-100 hover:bg-amber-300/15" : "bg-white text-slate-950 hover:bg-slate-200"}`}>
                <span aria-hidden="true">{locked ? "🔒" : "+"}</span>{locked ? "Upgrade to add sites" : "New Website"}
            </button>
        </div>
    );
}
