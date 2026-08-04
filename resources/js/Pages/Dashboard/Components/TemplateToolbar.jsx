export default function TemplateToolbar({
    query,
    onQueryChange,
    category,
    onCategoryChange,
    sort,
    onSortChange,
    theme,
    onThemeChange,
    status,
    onStatusChange,
    categories,
    themes,
    statuses,
    onClear,
    hasActiveFilters,
}) {
    return (
        <div className="space-y-3 rounded-2xl border border-white/10 bg-white/[0.025] p-3">
            <div className="flex flex-col gap-3 lg:flex-row">
                <label className="relative block min-w-0 flex-1">
                    <span className="sr-only">Search templates</span>
                    <span className="pointer-events-none absolute inset-y-0 left-4 flex items-center text-slate-500" aria-hidden="true">⌕</span>
                    <input type="search" value={query} onChange={(event) => onQueryChange(event.target.value)} placeholder="Search by template, industry, or tag..." className="h-10 w-full rounded-xl border border-white/10 bg-white/[0.04] py-2 pl-10 pr-4 text-sm text-white outline-none transition placeholder:text-slate-500 hover:border-white/20 focus:border-violet-400/70 focus:ring-2 focus:ring-violet-400/15" />
                </label>
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:flex">
                    <select value={theme} onChange={(event) => onThemeChange(event.target.value)} className="h-10 min-w-0 rounded-xl border border-white/10 bg-[#18181b] px-3 text-sm text-slate-300 outline-none transition hover:border-white/20 focus:border-violet-400/70 focus:ring-2 focus:ring-violet-400/15">
                        {themes.map((item) => <option key={item} value={item}>{item === "All themes" ? item : `${item.charAt(0).toUpperCase()}${item.slice(1)}`}</option>)}
                    </select>
                    <select value={status} onChange={(event) => onStatusChange(event.target.value)} className="h-10 min-w-0 rounded-xl border border-white/10 bg-[#18181b] px-3 text-sm text-slate-300 outline-none transition hover:border-white/20 focus:border-violet-400/70 focus:ring-2 focus:ring-violet-400/15">
                        {statuses.map((item) => <option key={item} value={item}>{item}</option>)}
                    </select>
                    <select value={sort} onChange={(event) => onSortChange(event.target.value)} className="col-span-2 h-10 min-w-0 rounded-xl border border-white/10 bg-[#18181b] px-3 text-sm text-slate-300 outline-none transition hover:border-white/20 focus:border-violet-400/70 focus:ring-2 focus:ring-violet-400/15 sm:col-span-1">
                        <option value="featured">Featured first</option>
                        <option value="recent">Recently added</option>
                        <option value="used">Most used</option>
                        <option value="name">Name A–Z</option>
                    </select>
                </div>
            </div>
            <div className="flex items-center gap-2">
                <div className="flex min-w-0 flex-1 gap-2 overflow-x-auto pb-1">
                    {categories.map((item) => <button key={item} type="button" onClick={() => onCategoryChange(item)} className={`shrink-0 rounded-full border px-3 py-1.5 text-xs font-medium transition focus:outline-none focus:ring-2 focus:ring-violet-400 ${category === item ? "border-white bg-white text-slate-950" : "border-white/10 bg-white/[0.035] text-slate-400 hover:border-white/20 hover:text-white"}`}>{item}</button>)}
                </div>
                {hasActiveFilters && <button type="button" onClick={onClear} className="shrink-0 rounded-lg px-2 py-1.5 text-xs font-semibold text-violet-300 transition hover:bg-violet-400/10 hover:text-violet-200 focus:outline-none focus:ring-2 focus:ring-violet-400">Clear filters</button>}
            </div>
        </div>
    );
}
