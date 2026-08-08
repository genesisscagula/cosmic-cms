import { useEffect, useMemo, useState } from "react";
import { createPortal } from "react-dom";

import themeMetadata from "./ThemeMetadata";
import ThemeGrid from "./ThemeGrid";

export default function ThemeModal({
    open,
    onClose,
    selectedTheme,
    onSelect,
    themeAccess = null,
    signupUrl = null,
    customTheme = null,
    hasLogo = false,
    brandMatchNeeded = false,
    onMatchBrandToLogo = null,
    brandMatchBusy = false,
}) {
    const [search, setSearch] = useState("");
    const [category, setCategory] = useState("All");

    const customThemeMetadata = useMemo(() => customTheme ? {
        id: 'my-brand',
        name: customTheme.name || 'My Brand Theme',
        category: 'Brand',
        description: customTheme.description || 'A custom Cosmic color family generated from your logo.',
        featured: true,
        colors: [
            customTheme.palette?.background || customTheme.palette?.primary || '#243447',
            customTheme.palette?.surface || '#30475E',
            customTheme.palette?.accent || '#60A5FA',
            customTheme.palette?.text || '#F8FAFC',
        ],
    } : null, [customTheme]);
    const allThemes = useMemo(() => customThemeMetadata ? [customThemeMetadata, ...themeMetadata] : themeMetadata, [customThemeMetadata]);

    const filteredThemes = useMemo(() => {
        const keyword = search.toLowerCase().trim();

        return allThemes.filter((theme) => {
            const matchesCategory = category === "All" || theme.category === category;
            const matchesSearch = !keyword
                || theme.name.toLowerCase().includes(keyword)
                || theme.category.toLowerCase().includes(keyword)
                || theme.description.toLowerCase().includes(keyword)
                || theme.id.toLowerCase().includes(keyword);

            return matchesCategory && matchesSearch;
        });
    }, [search, category, allThemes]);

    const categories = ["All", ...new Set(allThemes.map((theme) => theme.category))];
    const allThemeIds = allThemes.map((theme) => theme.id);
    const catalogThemeIds = useMemo(() => new Set(allThemeIds), [allThemeIds.join("|")]);
    const allowedThemeIds = useMemo(() => {
        if (themeAccess?.unlimited) {
            return allThemeIds;
        }

        if (!Array.isArray(themeAccess?.keys)) {
            return [];
        }

        // The backend entitlement keys are the source of truth, but the visible
        // count must always match themes that actually exist in this Builder
        // bundle. This prevents a stale/renamed theme id from making the banner
        // disagree with the cards that are really unlocked.
        const allowed = [...new Set(themeAccess.keys)]
            .map((key) => String(key || "").trim())
            .filter((key) => catalogThemeIds.has(key));
        if (customThemeMetadata && !allowed.includes('my-brand')) allowed.unshift('my-brand');
        return allowed;
    }, [themeAccess?.unlimited, themeAccess?.keys, catalogThemeIds, allThemeIds, customThemeMetadata]);
    const themeLimit = themeAccess?.unlimited ? null : allowedThemeIds.length;
    const nextPlan = themeAccess?.next_plan || null;

    useEffect(() => {
        if (!open) return;

        // Do not carry a previous modal session's filters into a newly opened
        // entitlement view after an upgrade/downgrade or an Inertia refresh.
        setSearch("");
        setCategory("All");
    }, [open, themeAccess?.unlimited, themeLimit]);

    const handleMatchBrandToLogo = async () => {
        if (typeof onMatchBrandToLogo !== 'function' || brandMatchBusy) return;
        // Close the Theme chooser first so the Builder AI overlay and the
        // follow-up Apply Theme preview become the only visible modal layers.
        onClose();
        await Promise.resolve();
        await onMatchBrandToLogo();
    };

    if (!open) {
        return null;
    }

    const selectedThemeData = allThemes.find((theme) => theme.id === selectedTheme);

    return createPortal(
        <div className="fixed inset-0 z-[9999] flex items-center justify-center p-3 sm:p-6">
            <button
                type="button"
                aria-label="Close theme dialog"
                onClick={onClose}
                className="absolute inset-0 bg-black/75 backdrop-blur-sm"
            />

            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="theme-modal-title"
                className="cosmic-theme-modal relative flex h-[min(86dvh,760px)] max-h-[calc(100dvh-1.5rem)] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-white/10 bg-[#111113] text-white shadow-2xl shadow-black/50"
            >
                <header className="flex shrink-0 items-center justify-between border-b border-white/10 bg-[#18181b] px-4 py-4 sm:px-6">
                    <div className="flex min-w-0 items-center gap-3">
                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-violet-400/20 bg-violet-400/10 text-[9px] font-bold uppercase tracking-[0.12em] text-violet-200" aria-hidden="true">
                            Style
                        </span>
                        <div className="min-w-0">
                            <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-violet-300">Website appearance</p>
                            <h2 id="theme-modal-title" className="mt-0.5 text-lg font-semibold text-white sm:text-xl">Choose a theme</h2>
                            <p className="mt-0.5 text-sm text-slate-400">Your selection updates the site palette while keeping your content intact.</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-lg text-slate-400 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400"
                        aria-label="Close"
                    >
                        ×
                    </button>
                </header>

                <div className="min-h-0 flex-1 overflow-y-auto bg-[#111113] p-4 sm:p-6">
                    <div className="mb-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h3 className="text-sm font-semibold text-white">Theme library</h3>
                            <p className="mt-1 text-sm text-slate-400">Pick the visual direction that fits this website. Your Builder preview updates instantly.</p>
                        </div>
                        <label className="w-full sm:w-72">
                            <span className="sr-only">Search themes</span>
                            <input
                                type="search"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Search themes..."
                                className="h-10 w-full rounded-lg border border-white/10 bg-white/[0.04] px-3 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-violet-400/70 focus:ring-2 focus:ring-violet-400/15"
                            />
                        </label>
                    </div>

                    {themeLimit !== null && (
                        <div className="mb-5 flex items-center justify-between gap-4 rounded-xl border border-amber-400/20 bg-amber-400/[0.07] px-4 py-3">
                            <div>
                                <p className="text-sm font-semibold text-amber-100">{themeAccess?.trial ? `${themeLimit} themes available in your trial` : `${themeLimit} themes included with your plan`}</p>
                                <p className="mt-0.5 text-xs text-amber-200/70">{themeAccess?.trial ? 'Sign up to unlock more themes and keep customizing this website.' : `Locked themes unlock when you upgrade to ${nextPlan}.`}</p>
                            </div>
                            {themeAccess?.trial && signupUrl ? (
                                <a href={signupUrl} className="shrink-0 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-emerald-500">Sign Up to Unlock More Themes</a>
                            ) : (
                                <span className="shrink-0 rounded-full border border-amber-300/25 bg-amber-300/10 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.14em] text-amber-100">Plan access</span>
                            )}
                        </div>
                    )}

                    <div className="mb-5 flex flex-wrap gap-2">
                        {categories.map((item) => (
                            <button
                                key={item}
                                type="button"
                                onClick={() => setCategory(item)}
                                className={`rounded-full border px-3 py-1.5 text-xs font-medium transition ${
                                    category === item
                                        ? "border-violet-400/50 bg-violet-400/15 text-violet-200"
                                        : "border-white/10 bg-white/[0.025] text-slate-400 hover:border-white/20 hover:text-white"
                                }`}
                            >
                                {item}
                            </button>
                        ))}
                    </div>

                    <ThemeGrid
                        themes={filteredThemes}
                        selectedTheme={selectedTheme}
                        onSelect={onSelect}
                        allowedThemeIds={allowedThemeIds}
                        nextPlan={nextPlan}
                        hasLogo={hasLogo}
                        brandMatchNeeded={brandMatchNeeded}
                        onMatchBrandToLogo={handleMatchBrandToLogo}
                        brandMatchBusy={brandMatchBusy}
                    />

                    {filteredThemes.length === 0 && (
                        <div className="rounded-xl border border-dashed border-white/15 px-6 py-14 text-center text-sm text-slate-500">
                            No themes match that search.
                        </div>
                    )}
                </div>

                <footer className="flex shrink-0 items-center justify-between gap-3 border-t border-white/10 bg-[#18181b] px-4 py-3 sm:px-6">
                    <div className="flex min-w-0 items-center gap-2.5">
                        {selectedThemeData?.colors && (
                            <div className="hidden gap-1 sm:flex" aria-hidden="true">
                                {selectedThemeData.colors.map((color) => (
                                    <span key={color} className="h-3 w-3 rounded-full border border-white/10" style={{ backgroundColor: color }} />
                                ))}
                            </div>
                        )}
                        <div className="min-w-0">
                            <p className="text-[9px] font-semibold uppercase tracking-[0.14em] text-slate-500">Active theme</p>
                            <span className="block truncate text-sm font-medium text-white">{selectedThemeData?.name || selectedTheme}</span>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="h-9 shrink-0 rounded-lg bg-white px-4 text-sm font-semibold text-slate-950 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-400"
                    >
                        Done
                    </button>
                </footer>
            </div>
        </div>,
        document.body,
    );
}
