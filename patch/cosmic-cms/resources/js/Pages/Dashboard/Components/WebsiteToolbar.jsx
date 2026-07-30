export default function WebsiteToolbar({ query, onQueryChange, onCreate }) {
    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
            <label className="relative block min-w-0 flex-1">
                <span className="sr-only">Search websites</span>
                <span className="pointer-events-none absolute inset-y-0 left-4 flex items-center text-slate-500" aria-hidden="true">⌕</span>
                <input type="search" value={query} onChange={(event) => onQueryChange(event.target.value)} placeholder="Search websites..." className="h-10 w-full rounded-xl border border-white/10 bg-white/[0.04] py-2 pl-10 pr-4 text-sm text-white outline-none transition placeholder:text-slate-500 hover:border-white/20 focus:border-violet-400/70 focus:ring-2 focus:ring-violet-400/15" />
            </label>
            <button type="button" onClick={onCreate} className="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-xl bg-white px-4 text-sm font-semibold text-slate-950 shadow-sm transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-400 focus:ring-offset-2 focus:ring-offset-[#0a0a0b]"><span aria-hidden="true">+</span>New Website</button>
        </div>
    );
}
