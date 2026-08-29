import { useEffect, useMemo, useState } from "react";
import { createPortal } from "react-dom";

import themeMetadata from "./ThemeMetadata";
import ThemeGrid from "./ThemeGrid";
import { useAppearance } from "../../../Appearance/AppearanceContext";

const COLOR_FAMILY_SCHEMA = {
    sourceColor: '#RRGGBB', primary: '#RRGGBB', primaryText: '#RRGGBB', primaryHover: '#RRGGBB', primarySoft: '#RRGGBB',
    secondary: '#RRGGBB', secondaryText: '#RRGGBB', accent: '#RRGGBB', accentText: '#RRGGBB',
    background: '#RRGGBB', backgroundText: '#RRGGBB', surface: '#RRGGBB',
    surfaceMuted: '#RRGGBB', surfaceText: '#RRGGBB', heading: '#RRGGBB', text: '#RRGGBB', muted: '#RRGGBB',
    border: '#RRGGBB', buttonPrimary: '#RRGGBB', buttonText: '#RRGGBB', buttonSecondary: '#RRGGBB',
    buttonSecondaryText: '#RRGGBB', success: '#RRGGBB', warning: '#RRGGBB', error: '#RRGGBB', onPrimary: '#RRGGBB',
    onDark: '#RRGGBB', gradient: { from: '#RRGGBB', via: '#RRGGBB', to: '#RRGGBB', glow: '#RRGGBB', angle: 125 },
};

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
    onApply = null,
    onAskLuna = null,
    onThemeLunaIntent = null,
    onGenerateCustomTheme = null,
    onSelectGeneratedTheme = null,
    lunaBusy = false,
}) {
    const [search, setSearch] = useState("");
    const [category, setCategory] = useState("All");
    const [lunaPrompt, setLunaPrompt] = useState('');
    const [lunaMessages, setLunaMessages] = useState([]);
    const [generatedThemes, setGeneratedThemes] = useState([]);
    const { resolvedTheme: appAppearanceTheme } = useAppearance();
    const isDark = appAppearanceTheme === 'dark';

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
            const matchesCategory = category !== "Luna Theme" && (category === "All" || theme.category === category);
            const matchesSearch = !keyword
                || theme.name.toLowerCase().includes(keyword)
                || theme.category.toLowerCase().includes(keyword)
                || theme.description.toLowerCase().includes(keyword)
                || theme.id.toLowerCase().includes(keyword);

            return matchesCategory && matchesSearch;
        });
    }, [search, category, allThemes]);

    const categories = ["All", ...new Set(allThemes.map((theme) => theme.category)), "Luna Theme"];
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
        setLunaPrompt('');
        setLunaMessages([]);
        // Keep Luna-generated palettes available across close/reopen cycles in this Builder session.
    }, [open, themeAccess?.unlimited, themeLimit]);

    const handleMatchBrandToLogo = async () => {
        if (typeof onMatchBrandToLogo !== 'function' || brandMatchBusy) return;
        // Close the Theme chooser first so the Builder AI overlay and the
        // follow-up Apply Theme preview become the only visible modal layers.
        onClose();
        await Promise.resolve();
        await onMatchBrandToLogo();
    };

    const askThemeLuna = async () => {
        const prompt = String(lunaPrompt || '').trim();
        if (!prompt || lunaBusy) return;
        setLunaMessages((current) => [...current, { role: 'user', text: prompt }]);
        setLunaPrompt('');
        try {
            const intentResponse = typeof onThemeLunaIntent === 'function'
                ? await onThemeLunaIntent(prompt, allThemeIds)
                : (typeof onAskLuna === 'function' ? await onAskLuna(prompt) : null);

            if (intentResponse?.type === 'action' && intentResponse?.action === 'select_theme' && intentResponse?.theme_key) {
                const themeKey = String(intentResponse.theme_key);
                if (catalogThemeIds.has(themeKey) || themeKey === 'my-brand') {
                    onSelect?.(themeKey);
                    const themeName = allThemes.find((theme) => theme.id === themeKey)?.name || themeKey;
                    setLunaMessages((current) => [...current, { role: 'assistant', text: `${themeName} is selected in this popup. Click Save Theme to apply it.` }]);
                    return;
                }
            }

            if (intentResponse?.type === 'action' && intentResponse?.action === 'custom_theme') {
                if (typeof onGenerateCustomTheme !== 'function') throw new Error('Custom theme generation is unavailable.');
                const generated = await onGenerateCustomTheme({
                    direction: intentResponse?.direction || prompt,
                    seedColor: intentResponse?.seed_color || null,
                    colorFamilySchema: COLOR_FAMILY_SCHEMA,
                });
                const family = generated?.brand_color_family || null;
                if (!family || typeof family !== 'object' || !family.primary) throw new Error('Luna did not return a valid color family.');

                const candidate = {
                    key: `luna-theme-${Date.now()}`,
                    name: generated?.name || "Luna's Theme",
                    label: "Luna's Theme",
                    mode: generated?.mode || 'dark',
                    palette: {
                        sourceColor: family.sourceColor || family.source_color || family.primary,
                        primary: family.primary, primaryHover: family.primaryHover || family.primary_hover, primarySoft: family.primarySoft || family.primary_soft,
                        secondary: family.secondary, accent: family.accent, background: family.background, surface: family.surface,
                        surfaceMuted: family.surfaceMuted || family.surface_alt, surfaceText: family.surfaceText || family.text || family.body,
                        heading: family.heading || family.primary, text: family.text || family.body, muted: family.muted, border: family.border,
                        buttonPrimary: family.buttonPrimary || family.button_primary || family.primary, buttonText: family.buttonText || family.button_text || family.primaryText || family.onPrimary,
                        buttonSecondary: family.buttonSecondary || family.button_secondary, buttonSecondaryText: family.buttonSecondaryText || family.button_secondary_text,
                        success: family.success, warning: family.warning, error: family.error, onPrimary: family.onPrimary || family.on_primary || family.primaryText, onDark: family.onDark || family.on_dark || family.backgroundText,
                        gradient: family.gradient || null,
                    },
                };
                setGeneratedThemes((current) => [...current, candidate]);
                setSearch('');
                setCategory('Luna Theme');
                onSelectGeneratedTheme?.(candidate);
                setLunaMessages((current) => [...current, {
                    role: 'assistant',
                    text: `I created ${candidate.name} and opened Luna Theme so you can review the palette. Save Theme when you are ready to apply it.`,
                }]);
                return;
            }

            setLunaMessages((current) => [...current, {
                role: 'assistant',
                text: intentResponse?.reply || 'I can help select an existing theme or create a new custom color family.',
            }]);
        } catch (error) {
            setLunaMessages((current) => [...current, { role: 'assistant', text: error?.response?.data?.message || error?.message || 'Luna could not update the theme.' }]);
        }
    };

    if (!open) {
        return null;
    }

    const selectedGeneratedTheme = generatedThemes.find((theme) => theme.key === selectedTheme) || null;
    const selectedThemeData = selectedGeneratedTheme
        ? { id: 'luna-generated', name: selectedGeneratedTheme.name, colors: [selectedGeneratedTheme.palette?.primary, selectedGeneratedTheme.palette?.secondary, selectedGeneratedTheme.palette?.accent, selectedGeneratedTheme.palette?.background].filter(Boolean) }
        : allThemes.find((theme) => theme.id === selectedTheme);

    return createPortal(
        <div className="fixed inset-0 z-[9999] flex items-center justify-center p-3 sm:p-6">
            <button
                type="button"
                aria-label="Close theme dialog"
                onClick={onClose}
                style={{ backgroundColor: 'rgba(2, 6, 23, 0.66)', backdropFilter: 'blur(8px)' }}
                className="cosmic-native-text-overlay absolute inset-0 bg-black/75"
            />

            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="theme-modal-title"
                id="cosmic-theme-modal"
                data-cosmic-app-modal="theme"
                data-appearance={isDark ? 'dark' : 'light'}
                className={`cosmic-theme-modal cosmic-native-text-layer relative flex h-[min(86dvh,760px)] max-h-[calc(100dvh-1.5rem)] w-full max-w-7xl flex-col overflow-hidden rounded-2xl border shadow-2xl ${isDark ? 'border-white/10 bg-[#111113] text-white shadow-black/50' : 'border-slate-200 bg-white text-slate-900 shadow-slate-900/20'}`}
            >
                <header className={`flex shrink-0 items-center justify-between border-b px-4 py-4 sm:px-6 ${isDark ? 'border-white/10 bg-[#18181b]' : 'border-slate-200 bg-white'}`}>
                    <div className="flex min-w-0 items-center gap-3">
                        <span className={`cosmic-theme-style-badge flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border px-1.5 text-[8px] font-extrabold uppercase leading-none tracking-[0.10em] ${isDark ? 'border-violet-400/30 bg-violet-500/10 text-violet-200' : 'border-violet-300 bg-violet-50 text-violet-700'}`} aria-hidden="true">
                            Style
                        </span>
                        <div className="min-w-0">
                            <p className={`text-[10px] font-semibold uppercase tracking-[0.16em] ${isDark ? 'text-violet-300' : 'text-violet-700'}`}>Website appearance</p>
                            <h2 id="theme-modal-title" className={`mt-0.5 text-lg font-semibold sm:text-xl ${isDark ? 'text-white' : 'text-slate-900'}`}>Choose a theme</h2>
                            <p className={`mt-0.5 text-sm ${isDark ? 'text-slate-400' : 'text-slate-600'}`}>Choose or generate a theme here. The Builder stays unchanged until you click Save Theme.</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-lg transition focus:outline-none focus:ring-2 focus:ring-violet-400 ${isDark ? 'text-slate-400 hover:bg-white/10 hover:text-white' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900'}`}
                        aria-label="Close"
                    >
                        ×
                    </button>
                </header>

                <div className={`min-h-0 flex-1 overflow-hidden ${isDark ? 'bg-[#111113]' : 'bg-slate-50'}`}>
                    <div className="grid h-full min-h-0 grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <div className="min-h-0 overflow-y-auto p-4 sm:p-6">
                    <div className="mb-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h3 className={`text-sm font-semibold ${isDark ? 'text-white' : 'text-slate-900'}`}>Theme library</h3>
                            <p className={`mt-1 text-sm ${isDark ? 'text-slate-400' : 'text-slate-600'}`}>Pick a visual direction. Selection stays local to this popup until Save Theme.</p>
                        </div>
                        <label className="w-full sm:w-72">
                            <span className="sr-only">Search themes</span>
                            <input
                                type="search"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Search themes..."
                                className={`h-10 w-full rounded-lg border px-3 text-sm outline-none transition focus:border-violet-400/70 focus:ring-2 focus:ring-violet-400/15 ${isDark ? 'border-white/10 bg-white/[0.04] text-white placeholder:text-slate-500' : 'border-slate-300 bg-white text-slate-900 placeholder:text-slate-400'}`}
                            />
                        </label>
                    </div>

                    {themeLimit !== null && (
                        <div className={`mb-5 flex items-center justify-between gap-4 rounded-xl border px-4 py-3 ${isDark ? 'border-amber-400/20 bg-amber-400/[0.07]' : 'border-amber-200 bg-amber-50'}`}>
                            <div>
                                <p className={`text-sm font-semibold ${isDark ? 'text-amber-100' : 'text-amber-900'}`}>{themeAccess?.trial ? `${themeLimit} themes available in your trial` : `${themeLimit} themes included with your plan`}</p>
                                <p className={`mt-0.5 text-xs ${isDark ? 'text-amber-200/70' : 'text-amber-700'}`}>{themeAccess?.trial ? 'Sign up to unlock more themes and keep customizing this website.' : `Locked themes unlock when you upgrade to ${nextPlan}.`}</p>
                            </div>
                            {themeAccess?.trial && signupUrl ? (
                                <a href={signupUrl} className="shrink-0 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-emerald-500">Sign Up to Unlock More Themes</a>
                            ) : (
                                <span className={`shrink-0 rounded-full border px-3 py-1 text-[10px] font-bold uppercase tracking-[0.14em] ${isDark ? 'border-amber-300/25 bg-amber-300/10 text-amber-100' : 'border-amber-300 bg-amber-100 text-amber-800'}`}>Plan access</span>
                            )}
                        </div>
                    )}

                    <div className="mb-5 flex flex-wrap gap-2">
                        {categories.map((item) => (
                            <button
                                key={item}
                                type="button"
                                onClick={() => setCategory(item)}
                                className={`cosmic-theme-filter-pill ${item === 'Luna Theme' ? 'cosmic-luna-theme-filter' : ''} rounded-full border px-3.5 py-2 text-xs font-semibold leading-none transition ${
                                    item === 'Luna Theme'
                                        ? (category === item ? 'is-active border-transparent text-white shadow-sm' : 'border-transparent text-white shadow-sm hover:brightness-110')
                                        : (category === item
                                            ? (isDark ? "is-active border-violet-400/60 bg-violet-400/15 text-violet-100" : "is-active border-violet-500 bg-violet-100 text-violet-800")
                                            : (isDark ? "border-white/15 bg-white/[0.035] text-slate-300 hover:border-white/30 hover:bg-white/[0.06] hover:text-white" : "border-slate-300 bg-white text-slate-700 hover:border-violet-300 hover:bg-violet-50/50 hover:text-slate-950"))
                                }`}
                            >
                                {item === 'Luna Theme' ? <><span aria-hidden="true">✦</span><span className="ml-1.5">Luna Theme</span></> : item}
                            </button>
                        ))}
                    </div>

                    {category === 'Luna Theme' && (
                        <div className="mb-5">
                            {generatedThemes.length > 0 ? (
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                    {generatedThemes.map((theme) => {
                                        const active = selectedTheme === theme.key;
                                        const swatches = [theme.palette?.primary, theme.palette?.secondary, theme.palette?.accent, theme.palette?.surface, theme.palette?.buttonText].filter(Boolean);
                                        return (
                                            <button
                                                key={theme.key}
                                                type="button"
                                                onClick={() => onSelectGeneratedTheme?.(theme)}
                                                className={`cosmic-luna-palette-card overflow-hidden rounded-xl border text-left transition ${active ? (isDark ? 'border-violet-400 ring-2 ring-violet-400/20' : 'border-violet-500 ring-2 ring-violet-200') : (isDark ? 'border-white/10 bg-white/[0.035] hover:border-violet-400/50' : 'border-slate-200 bg-white hover:border-violet-300')}`}
                                            >
                                                <div className="h-24 p-3" style={{ background: `linear-gradient(135deg, ${theme.palette?.primary || '#6D28D9'}, ${theme.palette?.secondary || theme.palette?.accent || '#A855F7'})` }}>
                                                    <div className="flex h-full items-end justify-between gap-2 rounded-lg border border-white/25 bg-black/10 p-2">
                                                        <span className="text-xs font-bold text-white drop-shadow">✦ Luna</span>
                                                        {active && <span className="rounded-full bg-white px-2 py-1 text-[9px] font-extrabold uppercase tracking-[.12em] text-violet-700">Selected</span>}
                                                    </div>
                                                </div>
                                                <div className={`p-3 ${isDark ? 'bg-[#17171a]' : 'bg-white'}`}>
                                                    <p className={`truncate text-sm font-semibold ${isDark ? 'text-white' : 'text-slate-900'}`}>{theme.name}</p>
                                                    <p className={`mt-1 text-xs ${isDark ? 'text-slate-400' : 'text-slate-600'}`}>Custom semantic palette generated by Luna.</p>
                                                    <div className="mt-3 flex gap-1.5">
                                                        {swatches.map((color, colorIndex) => <span key={`${color}-${colorIndex}`} className={`h-5 flex-1 rounded-md border ${isDark ? 'border-white/10' : 'border-slate-200'}`} style={{ backgroundColor: color }} />)}
                                                    </div>
                                                </div>
                                            </button>
                                        );
                                    })}
                                </div>
                            ) : (
                                <div className={`rounded-xl border border-dashed px-6 py-12 text-center ${isDark ? 'border-violet-400/25 bg-violet-500/[0.04]' : 'border-violet-200 bg-violet-50/50'}`}>
                                    <p className={`text-sm font-semibold ${isDark ? 'text-violet-200' : 'text-violet-800'}`}>No Luna themes yet</p>
                                    <p className={`mt-1 text-xs ${isDark ? 'text-slate-400' : 'text-slate-600'}`}>Ask Luna for a color family and every generated palette from this popup session will appear here.</p>
                                </div>
                            )}
                        </div>
                    )}

                    {category !== 'Luna Theme' && <ThemeGrid
                        themes={filteredThemes}
                        selectedTheme={selectedTheme}
                        onSelect={onSelect}
                        allowedThemeIds={allowedThemeIds}
                        nextPlan={nextPlan}
                        hasLogo={hasLogo}
                        brandMatchNeeded={brandMatchNeeded}
                        onMatchBrandToLogo={handleMatchBrandToLogo}
                        brandMatchBusy={brandMatchBusy}
                        isDark={isDark}
                    />}

                    {category !== 'Luna Theme' && filteredThemes.length === 0 && (
                        <div className={`rounded-xl border border-dashed px-6 py-14 text-center text-sm ${isDark ? 'border-white/15 text-slate-500' : 'border-slate-300 text-slate-500'}`}>
                            No themes match that search.
                        </div>
                    )}
                    </div>
                    <aside className={`cosmic-theme-luna-panel flex min-h-0 flex-col border-l p-4 ${isDark ? 'border-white/10 bg-black/20' : 'border-slate-200 bg-white'}`}>
                        <div>
                            <p className={`text-[10px] font-bold uppercase tracking-[.18em] ${isDark ? 'text-violet-300' : 'text-violet-700'}`}>✦ Luna · Theme</p>
                            <h3 className={`mt-1 text-sm font-semibold ${isDark ? 'text-white' : 'text-slate-900'}`}>Custom color family</h3>
                            <p className={`mt-1 text-xs leading-5 ${isDark ? 'text-slate-400' : 'text-slate-600'}`}>Ask for a palette, typography mood, logo-derived colors, or a custom brand direction. Luna only previews the theme until you save.</p>
                        </div>
                        <div className="mt-4 min-h-0 flex-1 space-y-2 overflow-y-auto">
                            {lunaMessages.length ? lunaMessages.map((message, index) => (
                                <div key={`${index}-${message.role}`} className={`cosmic-theme-luna-message rounded-xl px-3 py-2 text-xs leading-5 ${message.role === 'user' ? 'ml-6 is-user bg-violet-600 text-white' : (isDark ? 'mr-3 is-assistant border border-white/10 bg-white/[0.06] text-slate-100' : 'mr-3 is-assistant border border-slate-200 bg-slate-50 text-slate-900')}`}>
                                    {message.text}
                                </div>
                            )) : <div className={`rounded-xl border p-3 text-xs leading-5 ${isDark ? 'border-white/10 bg-white/[0.035] text-slate-400' : 'border-slate-200 bg-slate-50 text-slate-700'}`}>Try “premium navy and warm gold”, “use colors from my logo”, or “make this warmer and less corporate”.</div>}
                        </div>
                        <textarea value={lunaPrompt} onChange={(event) => setLunaPrompt(event.target.value)} onKeyDown={(event) => { if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); askThemeLuna(); } }} rows={4} placeholder="Ask Luna about this theme…" className={`mt-4 w-full resize-none rounded-xl border px-3 py-3 text-xs leading-5 outline-none focus:border-violet-400/50 ${isDark ? 'border-white/10 bg-black/30 text-white placeholder:text-slate-600' : 'border-slate-300 bg-white text-slate-900 placeholder:text-slate-400'}`} />
                        <button type="button" disabled={lunaBusy || !lunaPrompt.trim()} onClick={askThemeLuna} className="mt-2 h-9 rounded-lg bg-violet-500 px-4 text-xs font-bold text-white transition hover:bg-violet-400 disabled:opacity-40">{lunaBusy ? 'Updating preview…' : 'Ask Luna'}</button>
                    </aside>
                    </div>
                </div>

                <footer className={`flex shrink-0 items-center justify-between gap-3 border-t px-4 py-3 sm:px-6 ${isDark ? 'border-white/10 bg-[#18181b]' : 'border-slate-200 bg-white'}`}>
                    <div className="flex min-w-0 items-center gap-2.5">
                        {selectedThemeData?.colors && (
                            <div className="hidden gap-1 sm:flex" aria-hidden="true">
                                {selectedThemeData.colors.map((color) => (
                                    <span key={color} className={`h-3 w-3 rounded-full border ${isDark ? 'border-white/10' : 'border-slate-200'}`} style={{ backgroundColor: color }} />
                                ))}
                            </div>
                        )}
                        <div className="min-w-0">
                            <p className={`text-[9px] font-semibold uppercase tracking-[0.14em] ${isDark ? 'text-slate-500' : 'text-slate-500'}`}>Active theme</p>
                            <span className={`block truncate text-sm font-medium ${isDark ? 'text-white' : 'text-slate-900'}`}>{selectedThemeData?.name || selectedTheme}</span>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <button type="button" onClick={onClose} className={`h-9 shrink-0 rounded-lg border px-4 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-violet-400 ${isDark ? 'border-white/10 text-slate-300 hover:bg-white/5' : 'border-slate-300 text-slate-700 hover:bg-slate-50'}`}>Cancel</button>
                        <button type="button" onClick={onApply || onClose} className={`h-9 shrink-0 rounded-lg px-4 text-sm font-semibold text-white transition focus:outline-none focus:ring-2 focus:ring-violet-400 ${isDark ? 'bg-violet-500 hover:bg-violet-400' : 'bg-emerald-600 hover:bg-emerald-500'}`}>Save Theme</button>
                    </div>
                </footer>
            </div>
        </div>,
        document.body,
    );
}
