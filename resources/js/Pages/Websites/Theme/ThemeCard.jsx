const THEME_CREDITS = {
    emerald: 10,
    ocean: 10,
    indigo: 10,
    amber: 10,
    teal: 10,
    coffee: 20,
    rose: 20,
    forest: 20,
    terracotta: 20,
    charcoal: 20,
    void: 20,
    olive: 20,
    slate: 20,
    midnight: 30,
    navy: 30,
    sapphire: 30,
    obsidian: 40,
    espresso: 40,
    plum: 40,
    violet: 50,
    ruby: 50,
    asphalt: 50,
};

export default function ThemeCard({
    theme,
    selected,
    onSelect,
}) {
    const [primary, surface, accent, text] = theme.colors;

    return (
        <button
            type="button"
            onClick={() => onSelect(theme.id)}
            aria-pressed={selected}
            className={`cosmic-theme-card group relative overflow-hidden rounded-xl border text-left transition duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400 focus-visible:ring-offset-2 focus-visible:ring-offset-[#111113] ${
                selected
                    ? "is-active border-violet-400/80 bg-violet-500/[0.07] shadow-[0_0_0_1px_rgba(167,139,250,0.22),0_16px_34px_rgba(0,0,0,0.28)]"
                    : "border-white/10 bg-[#171719] hover:-translate-y-0.5 hover:border-white/25 hover:bg-white/[0.035]"
            }`}
        >
            <div
                className="relative h-28 overflow-hidden border-b border-black/15 p-3"
                style={{ backgroundColor: primary }}
            >
                <div className="absolute inset-0 bg-gradient-to-br from-white/10 via-transparent to-black/25" />
                <div className="relative rounded-md border border-white/15 bg-black/10 p-2.5 shadow-sm">
                    <div className="h-1.5 w-12 rounded-full bg-white/70" />
                    <div className="mt-2 h-4 w-4/5 rounded-sm bg-white/85" />
                    <div className="mt-1.5 h-1.5 w-3/5 rounded-full bg-white/45" />
                    <div className="mt-3 flex gap-1.5">
                        <span className="h-4 w-8 rounded bg-white/90" />
                        <span className="h-4 w-8 rounded border border-white/45" />
                    </div>
                </div>

                {selected && (
                    <span className="cosmic-theme-active-check absolute right-2.5 top-2.5 flex h-6 w-6 items-center justify-center rounded-full bg-violet-500 text-sm font-bold text-white shadow-lg shadow-violet-950/60">
                        ✓
                    </span>
                )}
            </div>

            <div className="p-3.5">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <div className="truncate text-sm font-semibold text-white">
                            {theme.name}
                        </div>
                        <div className="mt-1 text-xs text-slate-400">
                            {theme.category}
                        </div>
                    </div>

                    <span className="shrink-0 rounded-full bg-violet-400/10 px-2 py-0.5 text-[9px] font-bold uppercase tracking-[0.12em] text-violet-300">
                        {selected ? "Active" : theme.featured ? "Featured" : `⚡${THEME_CREDITS[theme.id] ?? 20}`}
                    </span>
                </div>

                <p className="mt-2 line-clamp-2 min-h-8 text-[11px] leading-4 text-slate-500">{theme.description}</p>

                <div className="mt-3 flex items-center gap-1.5" aria-label={`${theme.name} color palette`}>
                    {[primary, surface, accent, text].map((color, index) => (
                        <span
                            key={`${color}-${index}`}
                            className="h-2.5 w-2.5 rounded-full border border-white/15"
                            style={{ backgroundColor: color }}
                        />
                    ))}
                    <span className="ml-1 text-[10px] font-medium text-slate-500">Background · Surface · Accent · Text</span>
                </div>
            </div>
        </button>
    );
}
