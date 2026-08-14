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
    burgundy: 50,
    sage: 50,
    sandstone: 50,
    copper: 50,
    arctic: 50,
    blush: 50,
    graphite: 50,
    cobalt: 50,
    moss: 50,
    champagne: 50,
};

export default function ThemeCard({
    theme,
    selected,
    onSelect,
    locked = false,
    nextPlan = null,
    hasLogo = false,
    brandMatchNeeded = false,
    onMatchBrandToLogo = null,
    brandMatchBusy = false,
}) {
    const [primary, surface, accent, text] = theme.colors;
    const isMyBrand = theme.id === 'my-brand';
    const canMatchBrandToLogo = isMyBrand && typeof onMatchBrandToLogo === 'function';

    return (
        <div
            role="button"
            onClick={() => { if (!locked) onSelect(theme.id); }}
            onKeyDown={(event) => {
                if (!locked && (event.key === 'Enter' || event.key === ' ')) {
                    event.preventDefault();
                    onSelect(theme.id);
                }
            }}
            aria-pressed={selected}
            aria-disabled={locked}
            tabIndex={0}
            className={`cosmic-theme-card group relative overflow-hidden rounded-xl border text-left transition duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400 focus-visible:ring-offset-2 focus-visible:ring-offset-[#111113] ${
                selected
                    ? "is-active border-violet-400/80 bg-violet-500/[0.07] shadow-[0_0_0_1px_rgba(167,139,250,0.22),0_16px_34px_rgba(0,0,0,0.28)]"
                    : locked
                        ? "cursor-not-allowed border-amber-300/20 bg-[#151517]"
                        : "border-white/10 bg-[#171719] hover:-translate-y-0.5 hover:border-white/25 hover:bg-white/[0.035]"
            }`}
        >
            <div
                className={`cosmic-theme-preview relative h-28 overflow-hidden border-b border-black/15 p-3 ${locked ? 'is-locked' : ''}`}
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

                {locked && (
                    <div
                        aria-hidden="true"
                        className="cosmic-theme-lock-veil pointer-events-none absolute inset-0 z-10 bg-slate-950/20 backdrop-blur-[1.5px]"
                    />
                )}

                {locked && (
                    <span className="cosmic-theme-unlock-cta absolute left-1/2 top-3 z-20 -translate-x-1/2 whitespace-nowrap rounded-full border border-white bg-white px-3.5 py-1.5 text-[10px] font-extrabold uppercase tracking-[0.14em] text-amber-800 shadow-[0_8px_22px_rgba(15,23,42,.28)] ring-1 ring-amber-200/80">
                        {nextPlan === 'Sign up' ? '🔒 Sign up to unlock' : `🔒 Upgrade to ${nextPlan || 'unlock'}`}
                    </span>
                )}

                {selected && (
                    <span
                        className="cosmic-theme-active-check absolute right-2.5 top-2.5 z-30 flex h-7 w-7 items-center justify-center rounded-full border-2 border-white bg-emerald-600 text-white shadow-[0_4px_12px_rgba(5,150,105,.38)] ring-1 ring-emerald-300/70"
                        aria-label="Current theme"
                        title="Current theme"
                    >
                        <svg
                            aria-hidden="true"
                            viewBox="0 0 20 20"
                            className="h-4 w-4"
                            fill="none"
                        >
                            <path
                                d="M5.25 10.25 8.3 13.3 14.75 6.85"
                                stroke="currentColor"
                                strokeWidth="2.25"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                            />
                        </svg>
                    </span>
                )}
            </div>

            <div className="p-3.5">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <div className="cosmic-theme-card-name truncate text-sm font-semibold text-white">
                            {theme.name}
                        </div>
                        <div className="cosmic-theme-card-category mt-1 text-xs text-slate-400">
                            {theme.category}
                        </div>
                    </div>

                    <span className={`relative z-10 shrink-0 rounded-full px-2 py-0.5 text-[9px] font-bold uppercase tracking-[0.12em] ${locked ? "bg-amber-400/10 text-amber-200" : "bg-violet-400/10 text-violet-300"}`}>
                        {selected ? "Active" : locked ? "🔒 Locked" : theme.featured ? "Featured" : `⚡${THEME_CREDITS[theme.id] ?? 20}`}
                    </span>
                </div>

                <p className="cosmic-theme-card-description mt-2 line-clamp-2 min-h-8 text-[11px] leading-4 text-slate-500">{theme.description}</p>

                <div className="mt-3 flex items-center gap-1.5" aria-label={`${theme.name} color palette`}>
                    {[primary, surface, accent, text].map((color, index) => (
                        <span
                            key={`${color}-${index}`}
                            className="h-2.5 w-2.5 rounded-full border border-white/15"
                            style={{ backgroundColor: color }}
                        />
                    ))}
                    <span className="cosmic-theme-card-palette-label ml-1 text-[10px] font-medium text-slate-500">Background · Surface · Accent · Text</span>
                </div>

                {canMatchBrandToLogo && brandMatchNeeded && (
                    <button
                        type="button"
                        disabled={brandMatchBusy}
                        onClick={(event) => {
                            event.preventDefault();
                            event.stopPropagation();
                            onMatchBrandToLogo();
                        }}
                        onKeyDown={(event) => event.stopPropagation()}
                        className="cosmic-theme-match-brand-cta mt-3 inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-emerald-400/30 bg-emerald-400/10 px-3 py-2 text-[10px] font-extrabold uppercase tracking-[0.12em] text-emerald-200 transition hover:bg-emerald-400/15 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span aria-hidden="true">✨</span>
                        {brandMatchBusy ? 'Matching…' : 'Match Theme to Logo'}
                    </button>
                )}


            </div>
        </div>
    );
}
