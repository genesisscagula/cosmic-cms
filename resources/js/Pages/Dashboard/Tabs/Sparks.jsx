import { router } from "@inertiajs/react";
import axios from "axios";
import { useMemo, useState } from "react";
import { ActualSparkPreview } from "../../Websites/Components/AddSectionModal";
import { BlockRegistry } from "../../Websites/Components/SparkRegistry";

const viewCopy = {
    owned: {
        eyebrow: "Your library",
        title: "Owned Sparks",
        description: "Every Spark unlocked by this account is available here across your Cosmic workspace.",
    },
    marketplace: {
        eyebrow: "Discover",
        title: "Sparks Marketplace",
        description: "Browse, preview, and add reusable sections without leaving the dashboard.",
    },
    favorites: {
        eyebrow: "Shortlist",
        title: "Favorite Sparks",
        description: "Keep a shortlist of Sparks you want to revisit or use later.",
    },
    purchased: {
        eyebrow: "Account collection",
        title: "Purchased Sparks",
        description: "Every premium Spark purchased by this account remains available here.",
    },
};

const marketplaceFilters = [
    { id: "all", label: "All" },
    { id: "free", label: "Free" },
    { id: "premium", label: "Premium" },
    { id: "new", label: "New" },
    { id: "popular", label: "Popular" },
    { id: "staff", label: "Staff Picks" },
];

export default function Sparks({ dashboard }) {
    const library = dashboard?.spark_library ?? { items: [], categories: [], count: 0 };
    const marketplace = dashboard?.spark_marketplace ?? { items: [], categories: [], count: 0, owned_count: 0 };
    const [marketItems, setMarketItems] = useState(marketplace.items);
    const [activeView, setActiveView] = useState("owned");
    const [query, setQuery] = useState("");
    const [category, setCategory] = useState("All");
    const [marketFilter, setMarketFilter] = useState("all");
    const [busyKey, setBusyKey] = useState(null);
    const [previewSpark, setPreviewSpark] = useState(null);
    const [previewVariant, setPreviewVariant] = useState("white");
    const copy = viewCopy[activeView];

    const registry = useMemo(() => new Map(BlockRegistry.map((item) => [item.type, item])), []);
    const ownedCount = marketItems.filter((item) => item.owned).length || library.count;
    const favoriteCount = marketItems.filter((item) => item.favorited).length;
    const purchasedCount = marketItems.filter((item) => item.purchased).length;

    const views = [
        { id: "owned", label: "Owned", count: ownedCount },
        { id: "marketplace", label: "Marketplace", count: marketplace.count },
        { id: "favorites", label: "Favorites", count: favoriteCount },
        { id: "purchased", label: "Purchased", count: purchasedCount },
    ];

    const filteredOwned = useMemo(() => {
        const normalized = query.trim().toLowerCase();

        return library.items.filter((spark) => {
            if (category !== "All" && spark.category !== category) return false;
            if (!normalized) return true;

            return `${spark.name} ${spark.description} ${spark.category} ${spark.collection}`
                .toLowerCase()
                .includes(normalized);
        });
    }, [library.items, category, query]);

    const filteredMarketplace = useMemo(() => {
        const normalized = query.trim().toLowerCase();

        return marketItems
            .filter((spark) => registry.has(spark.key))
            .filter((spark) => {
                if (category !== "All" && spark.category !== category) return false;
                if (marketFilter === "free" && !spark.is_free) return false;
                if (marketFilter === "premium" && !spark.is_premium) return false;
                if (marketFilter === "new" && !spark.is_new) return false;
                if (marketFilter === "popular" && !spark.popular) return false;
                if (marketFilter === "staff" && !spark.staff_pick) return false;
                if (!normalized) return true;

                return `${spark.name} ${spark.description} ${spark.category} ${spark.collection}`
                    .toLowerCase()
                    .includes(normalized);
            });
    }, [marketItems, registry, category, marketFilter, query]);


    const filteredCollection = useMemo(() => {
        const normalized = query.trim().toLowerCase();
        return marketItems
            .filter((spark) => registry.has(spark.key))
            .filter((spark) => activeView === "favorites" ? spark.favorited : spark.purchased)
            .filter((spark) => {
                if (category !== "All" && spark.category !== category) return false;
                if (!normalized) return true;
                return `${spark.name} ${spark.description} ${spark.category} ${spark.collection}`.toLowerCase().includes(normalized);
            });
    }, [marketItems, registry, activeView, category, query]);

    const removeSpark = async (spark) => {
        if (!window.confirm(`Remove “${spark.name}” from Owned Sparks?`)) return;

        setBusyKey(spark.key);
        try {
            await axios.delete(`/sparks/${spark.key}/owned`);
            setMarketItems((items) => items.map((item) => item.key === spark.key ? { ...item, owned: false } : item));
            router.reload({ only: ["dashboard"], preserveScroll: true, preserveState: true });
        } finally {
            setBusyKey(null);
        }
    };

    const unlockSpark = async (spark) => {
        setBusyKey(spark.key);
        try {
            await axios.post(`/sparks/${spark.key}/unlock`);
            setMarketItems((items) => items.map((item) => item.key === spark.key ? { ...item, owned: true } : item));
            router.reload({ only: ["dashboard"], preserveScroll: true, preserveState: true });
        } catch (error) {
            window.alert(error.response?.data?.message || "Could not add this Spark. Please try again.");
        } finally {
            setBusyKey(null);
        }
    };

    const toggleFavorite = async (spark) => {
        setBusyKey(`favorite-${spark.key}`);
        try {
            const { data } = await axios.post(`/sparks/${spark.key}/favorite`);
            setMarketItems((items) => items.map((item) => item.key === spark.key ? { ...item, favorited: data.favorited } : item));
        } catch (error) {
            window.alert(error.response?.data?.message || "Could not update Favorites.");
        } finally {
            setBusyKey(null);
        }
    };

    const switchView = (view) => {
        setActiveView(view);
        setQuery("");
        setCategory("All");
        setMarketFilter("all");
    };

    return (
        <section>
            <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p className="text-sm font-medium text-violet-300">Sparks Center</p>
                    <h1 className="mt-2 text-3xl font-semibold tracking-tight text-white">Reusable sections, centralized.</h1>
                    <p className="mt-3 max-w-2xl text-sm leading-6 text-slate-400">
                        Manage owned Sparks and discover new reusable sections from one account-level library.
                    </p>
                </div>

                <button type="button" onClick={() => switchView("marketplace")} className="inline-flex h-11 items-center justify-center rounded-xl bg-white px-5 text-sm font-semibold text-slate-950 transition hover:bg-violet-100">
                    Browse Marketplace
                </button>
            </div>

            <div className="mt-8 flex gap-2 overflow-x-auto rounded-2xl border border-white/10 bg-white/[0.025] p-1.5">
                {views.map((view) => {
                    const isActive = activeView === view.id;
                    return (
                        <button key={view.id} type="button" onClick={() => switchView(view.id)} className={`flex min-w-max items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition ${isActive ? "bg-white text-slate-950 shadow-sm" : "text-slate-400 hover:bg-white/[0.06] hover:text-white"}`}>
                            {view.label}
                            {typeof view.count === "number" && <span className={`rounded-full px-2 py-0.5 text-[10px] ${isActive ? "bg-slate-200" : "bg-white/10"}`}>{view.count}</span>}
                        </button>
                    );
                })}
            </div>

            <div className="mt-6 rounded-3xl border border-white/10 bg-[#111113] p-6 sm:p-8">
                <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.2em] text-violet-300">{copy.eyebrow}</p>
                        <h2 className="mt-2 text-2xl font-semibold text-white">{copy.title}</h2>
                        <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-400">{copy.description}</p>
                    </div>

                    {(["owned", "marketplace", "favorites", "purchased"].includes(activeView)) && (
                        <div className="flex w-full max-w-xl flex-col gap-2 sm:flex-row">
                            <input value={query} onChange={(event) => setQuery(event.target.value)} placeholder={`Search ${activeView === "owned" ? "Owned " : ""}Sparks...`} className="h-11 min-w-0 flex-1 rounded-xl border border-white/10 bg-white/[0.035] px-4 text-sm text-white placeholder:text-slate-600 focus:border-violet-400 focus:outline-none" />
                            <select value={category} onChange={(event) => setCategory(event.target.value)} className="h-11 rounded-xl border border-white/10 bg-[#18181b] px-4 text-sm text-slate-200 focus:border-violet-400 focus:outline-none">
                                <option>All</option>
                                {(activeView === "owned" ? library.categories : marketplace.categories).map((item) => <option key={item}>{item}</option>)}
                            </select>
                        </div>
                    )}
                </div>

                {activeView === "marketplace" && (
                    <div className="mt-6 flex flex-wrap gap-2">
                        {marketplaceFilters.map((filter) => (
                            <button key={filter.id} type="button" onClick={() => setMarketFilter(filter.id)} className={`rounded-full px-3.5 py-2 text-xs font-semibold transition ${marketFilter === filter.id ? "bg-violet-400 text-slate-950" : "border border-white/10 text-slate-400 hover:border-violet-300/30 hover:text-white"}`}>
                                {filter.label}
                            </button>
                        ))}
                    </div>
                )}

                {activeView === "owned" ? (
                    filteredOwned.length ? (
                        <div className="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {filteredOwned.map((spark) => (
                                <OwnedSparkCard key={spark.key} spark={spark} busy={busyKey === spark.key} onPreview={() => setPreviewSpark({ ...spark, registry: registry.get(spark.key) })} onRemove={() => removeSpark(spark)} onFavorite={() => toggleFavorite(spark)} favoriteBusy={busyKey === `favorite-${spark.key}`} />
                            ))}
                        </div>
                    ) : (
                        <EmptyState title={library.count ? "No Sparks match your search" : "Your Owned Sparks library is ready"} description={library.count ? "Try another keyword or category." : "Add reusable Sparks from the marketplace and they will appear here automatically."} action={!library.count ? <button type="button" onClick={() => switchView("marketplace")} className="mt-5 inline-flex rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-950">Browse Marketplace</button> : null} />
                    )
                ) : activeView === "marketplace" ? (
                    filteredMarketplace.length ? (
                        <div className="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {filteredMarketplace.map((spark) => (
                                <MarketplaceSparkCard key={spark.key} spark={spark} busy={busyKey === spark.key} onPreview={() => setPreviewSpark({ ...spark, registry: registry.get(spark.key) })} onUnlock={() => unlockSpark(spark)} onFavorite={() => toggleFavorite(spark)} favoriteBusy={busyKey === `favorite-${spark.key}`} />
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No Sparks match these filters" description="Try another keyword, category, or marketplace filter." />
                    )
                ) : filteredCollection.length ? (
                    <div className="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {filteredCollection.map((spark) => (
                            <MarketplaceSparkCard key={spark.key} spark={spark} busy={busyKey === spark.key} favoriteBusy={busyKey === `favorite-${spark.key}`} onPreview={() => setPreviewSpark({ ...spark, registry: registry.get(spark.key) })} onUnlock={() => unlockSpark(spark)} onFavorite={() => toggleFavorite(spark)} />
                        ))}
                    </div>
                ) : (
                    <EmptyState title={activeView === "favorites" ? "No Favorite Sparks yet" : "No purchased Sparks yet"} description={activeView === "favorites" ? "Use the heart button on any marketplace Spark to save it here." : "Premium Sparks purchased with Cosmic Credits will remain available here."} action={<button type="button" onClick={() => switchView("marketplace")} className="mt-5 inline-flex rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-950">Browse Marketplace</button>} />
                )}
            </div>

            {previewSpark && (
                <SparkPreviewModal spark={previewSpark} previewVariant={previewVariant} setPreviewVariant={setPreviewVariant} onClose={() => setPreviewSpark(null)} />
            )}
        </section>
    );
}

function OwnedSparkCard({ spark, busy, onPreview, onRemove, onFavorite, favoriteBusy }) {
    return (
        <article className="rounded-2xl border border-white/10 bg-black/20 p-5 transition hover:border-violet-300/25 hover:bg-white/[0.025]">
            <div className="flex items-start justify-between gap-4">
                <div className="flex h-11 w-11 items-center justify-center rounded-xl border border-violet-300/20 bg-violet-400/10 text-lg text-violet-200">✦</div>
                <div className="flex items-center gap-2"><button type="button" disabled={favoriteBusy} onClick={onFavorite} className={`flex h-8 w-8 items-center justify-center rounded-full border text-sm transition ${spark.favorited ? "border-rose-300/30 bg-rose-400/10 text-rose-200" : "border-white/10 text-slate-400 hover:text-white"}`}>{spark.favorited ? "♥" : "♡"}</button><span className="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-200">Owned</span></div>
            </div>
            <p className="mt-5 text-[10px] font-bold uppercase tracking-[0.18em] text-violet-300">{spark.category} · {spark.collection}</p>
            <h3 className="mt-1 text-lg font-semibold text-white">{spark.name}</h3>
            <p className="mt-2 min-h-12 text-sm leading-6 text-slate-400">{spark.description}</p>{spark.purchased && <p className="mt-3 text-[10px] font-bold uppercase tracking-[0.16em] text-amber-200">Purchased · permanent account access</p>}
            <div className="mt-4 flex flex-wrap gap-2 text-xs text-slate-500">
                <span>{spark.source}</span><span>•</span><span>Added {spark.unlocked_label ?? "recently"}</span><span>•</span><span>{spark.usage_count} uses</span>
            </div>
            <div className="mt-5 flex gap-2">
                <button type="button" onClick={onPreview} className="flex-1 rounded-xl border border-white/10 px-3 py-2.5 text-center text-sm font-semibold text-slate-200 hover:bg-white/5">Preview</button>
                <button type="button" disabled={busy} onClick={onRemove} className="rounded-xl border border-rose-400/20 px-3 py-2.5 text-sm font-semibold text-rose-200 transition hover:bg-rose-400/10 disabled:opacity-50">{busy ? "Removing..." : "Remove"}</button>
            </div>
        </article>
    );
}

function MarketplaceSparkCard({ spark, busy, onPreview, onUnlock, onFavorite, favoriteBusy }) {
    return (
        <article className="overflow-hidden rounded-2xl border border-white/10 bg-black/20 transition hover:-translate-y-0.5 hover:border-violet-300/30">
            <div className="relative h-36 overflow-hidden bg-gradient-to-br from-violet-500/20 via-indigo-500/10 to-cyan-400/10 p-4">
                <div className="h-full rounded-xl border border-white/10 bg-[#0d0d10]/85 p-4">
                    <div className="h-2 w-16 rounded bg-white/15" />
                    <div className="mt-5 h-4 w-4/5 rounded bg-white/20" />
                    <div className="mt-2 h-2.5 w-3/5 rounded bg-white/10" />
                    <div className="mt-5 grid grid-cols-3 gap-2"><div className="h-8 rounded bg-white/[0.06]" /><div className="h-8 rounded bg-white/[0.06]" /><div className="h-8 rounded bg-white/[0.06]" /></div>
                </div>
                <div className="absolute right-3 top-3 flex flex-wrap justify-end gap-1.5">
                    {spark.staff_pick && <Badge>Staff Pick</Badge>}
                    {spark.is_new && <Badge>New</Badge>}
                </div>
            </div>
            <div className="p-5">
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <p className="text-[10px] font-bold uppercase tracking-[0.18em] text-violet-300">{spark.category} · {spark.collection}</p>
                        <h3 className="mt-1 text-lg font-semibold text-white">{spark.name}</h3>
                    </div>
                    <div className="flex items-center gap-2"><button type="button" disabled={favoriteBusy} onClick={onFavorite} className={`flex h-8 w-8 items-center justify-center rounded-full border text-sm transition ${spark.favorited ? "border-rose-300/30 bg-rose-400/10 text-rose-200" : "border-white/10 text-slate-400 hover:text-white"}`}>{spark.favorited ? "♥" : "♡"}</button>{spark.owned ? <span className="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-200">Owned</span> : <span className="rounded-full border border-amber-300/20 bg-amber-300/10 px-2.5 py-1 text-xs font-bold text-amber-100">{spark.is_free ? "Free" : `⚡ ${spark.credits}`}</span>}</div>
                </div>
                <p className="mt-2 min-h-12 text-sm leading-6 text-slate-400">{spark.description}</p>{spark.purchased && <p className="mt-3 text-[10px] font-bold uppercase tracking-[0.16em] text-amber-200">Purchased · permanent account access</p>}
                <div className="mt-5 flex gap-2">
                    <button type="button" onClick={onPreview} className="rounded-xl border border-white/10 px-3 py-2.5 text-sm font-semibold text-slate-200 hover:bg-white/5">Preview</button>
                    {spark.owned ? <div className="flex-1 rounded-xl border border-emerald-400/20 bg-emerald-400/[0.06] px-3 py-2.5 text-center text-sm font-semibold text-emerald-200">✓ In Owned Sparks</div> : <button type="button" disabled={busy} onClick={onUnlock} className="flex-1 rounded-xl bg-white px-3 py-2.5 text-sm font-semibold text-slate-950 disabled:opacity-50">{busy ? "Adding..." : spark.is_free ? "Add Free Spark" : `Add to Owned · ⚡${spark.credits}`}</button>}
                </div>
            </div>
        </article>
    );
}

function Badge({ children }) {
    return <span className="rounded-full border border-white/10 bg-black/60 px-2 py-1 text-[9px] font-bold uppercase tracking-wide text-white">{children}</span>;
}

function EmptyState({ title, description, action = null }) {
    return (
        <div className="mt-8 rounded-2xl border border-dashed border-white/10 bg-black/10 px-6 py-14 text-center">
            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl border border-violet-300/20 bg-violet-400/10 text-xl text-violet-200">✦</div>
            <h3 className="mt-4 text-lg font-semibold text-white">{title}</h3>
            <p className="mx-auto mt-2 max-w-lg text-sm leading-6 text-slate-400">{description}</p>
            {action}
        </div>
    );
}

function SparkPreviewModal({ spark, previewVariant, setPreviewVariant, onClose }) {
    return (
        <div className="fixed inset-0 z-[980] flex items-center justify-center p-3 sm:p-6">
            <button type="button" onClick={onClose} className="absolute inset-0 bg-black/85 backdrop-blur-sm" aria-label="Close Spark preview" />
            <section role="dialog" aria-modal="true" className="relative z-10 flex max-h-[94vh] w-full max-w-7xl flex-col overflow-hidden rounded-3xl border border-white/10 bg-[#101014] text-white shadow-2xl">
                <header className="flex items-start justify-between gap-4 border-b border-white/10 px-5 py-4 sm:px-7">
                    <div><p className="text-[10px] font-bold uppercase tracking-[0.2em] text-violet-300">Spark Preview · {spark.category}</p><h3 className="mt-1 text-xl font-semibold">{spark.name}</h3><p className="mt-1 max-w-3xl text-sm text-slate-400">{spark.description}</p></div>
                    <button type="button" onClick={onClose} className="rounded-xl border border-white/10 px-3 py-2 text-slate-400 hover:bg-white/5 hover:text-white">✕</button>
                </header>
                <div className="min-h-0 flex-1 overflow-auto bg-[#e5e7eb] p-3 sm:p-6">
                    <div className="mx-auto min-h-[620px] max-w-[1440px] overflow-hidden rounded-2xl bg-white shadow-2xl">
                        <div className="pointer-events-none min-w-[1100px] origin-top-left"><ActualSparkPreview spark={spark} previewVariant={previewVariant} websiteTheme="midnight" /></div>
                    </div>
                </div>
                <footer className="flex flex-col gap-3 border-t border-white/10 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                    <p className="text-xs text-slate-500">Preview uses sample content and does not change your website.</p>
                    <div className="flex gap-2">
                        <button type="button" onClick={() => setPreviewVariant("white")} className={`rounded-xl px-3 py-2 text-xs font-semibold ${previewVariant === "white" ? "bg-white text-slate-950" : "border border-white/10 text-slate-300"}`}>Light</button>
                        <button type="button" onClick={() => setPreviewVariant("dark")} className={`rounded-xl px-3 py-2 text-xs font-semibold ${previewVariant === "dark" ? "bg-white text-slate-950" : "border border-white/10 text-slate-300"}`}>Dark</button>
                    </div>
                </footer>
            </section>
        </div>
    );
}
