import { useMemo, useState } from "react";
import { createPortal } from "react-dom";

import themeMetadata from "./ThemeMetadata";
import ThemeGrid from "./ThemeGrid";

export default function ThemeModal({ open, onClose, selectedTheme, onSelect }) {
    const [search, setSearch] = useState("");
    const [category, setCategory] = useState("All");

    const filteredThemes = useMemo(() => {
        const keyword = search.toLowerCase().trim();

        return themeMetadata.filter((theme) => {
            const matchesCategory = category === "All" || theme.category === category;
            const matchesSearch = !keyword
                || theme.name.toLowerCase().includes(keyword)
                || theme.category.toLowerCase().includes(keyword)
                || theme.description.toLowerCase().includes(keyword)
                || theme.id.toLowerCase().includes(keyword);

            return matchesCategory && matchesSearch;
        });
    }, [search, category]);

    const categories = ["All", ...new Set(themeMetadata.map((theme) => theme.category))];

    if (!open) {
        return null;
    }

    const selectedThemeData = themeMetadata.find((theme) => theme.id === selectedTheme);

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
                className="relative flex h-[min(84dvh,720px)] max-h-[calc(100dvh-1.5rem)] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-white/10 bg-[#111113] text-white shadow-2xl shadow-black/50"
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

                    <ThemeGrid themes={filteredThemes} selectedTheme={selectedTheme} onSelect={onSelect} />

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
