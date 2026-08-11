import axios from "axios";
import { Head, Link } from "@inertiajs/react";
import { useMemo, useState } from "react";
import { BlockRegistry } from "../Websites/Components/SparkRegistry";
import { ActualSparkPreview } from "../Websites/Components/AddSectionModal";
import CreditBalanceBadge from "../../Components/CosmicCredits/CreditBalanceBadge";
import { useCreditBalance } from '@/Hooks/useCreditBalance';
import { showCosmicNotification } from "../../Components/CosmicNotification";

const tones = [
    "from-violet-500/30 via-indigo-500/10 to-cyan-400/20",
    "from-emerald-500/25 via-teal-500/10 to-blue-400/20",
    "from-amber-500/25 via-rose-500/10 to-violet-400/20",
];

const sparkPreviewCycle = ["primary", "white", "surface", "white", "primary"];

function SparkCardPreview({ spark, index }) {
    const Preview = spark.registry?.preview;
    if (!Preview) return null;

    return (
        <div className="pointer-events-none h-full w-full">
            <Preview
                {...(spark.registry?.payload || {})}
                previewVariant={sparkPreviewCycle[index % sparkPreviewCycle.length]}
                websiteTheme="midnight"
            />
        </div>
    );
}


export default function Index({ sparks = [], categories = [], ownedCount = 0, ownedSparkSlots = null, previewPolicy = null, sharedSparkLibrary = null }) {
    const { balance, setBalance } = useCreditBalance();
    const [items, setItems] = useState(sparks);
    const [view, setView] = useState("marketplace");
    const [query, setQuery] = useState("");
    const [category, setCategory] = useState("All");
    const [busyKey, setBusyKey] = useState(null);
    const [previewSpark, setPreviewSpark] = useState(null);
    const [previewVariant, setPreviewVariant] = useState("primary");

    const registry = useMemo(() => new Map(BlockRegistry.map((item) => [item.type, item])), []);

    const catalogItems = useMemo(() => items.map((spark) => ({ ...spark, registry: registry.get(spark.key) })).filter((spark) => spark.registry), [items, registry]);

    const filtered = useMemo(() => catalogItems.filter((spark) => {
        if (view === "owned" && !spark.owned) return false;
        if (view === "favorites" && !spark.favorited) return false;
        if (category !== "All" && spark.category !== category) return false;
        return `${spark.name} ${spark.description} ${spark.category}`.toLowerCase().includes(query.toLowerCase());
    }), [catalogItems, view, category, query]);

    const toggleShare = async (spark) => {
        setBusyKey(`share:${spark.key}`);
        try {
            const request = spark.shared
                ? axios.delete(`/sparks/${spark.key}/share`)
                : axios.post(`/sparks/${spark.key}/share`);
            const { data } = await request;
            setItems((current) => current.map((item) => item.key === spark.key ? { ...item, shared: data.shared } : item));
            showCosmicNotification({ title: data.shared ? "Shared with agency" : "Removed from shared library", message: data.message, tone: "success" });
        } catch (error) {
            showCosmicNotification({ title: "Could not update sharing", message: error.response?.data?.message || "Please try again.", tone: "error" });
        } finally {
            setBusyKey(null);
        }
    };

    const unlock = async (spark) => {
        setBusyKey(spark.key);
        try {
            const { data } = await axios.post(`/sparks/${spark.key}/unlock`);
            setItems((current) => current.map((item) => item.key === spark.key ? { ...item, owned: true } : item));
            setBalance(data.credit_balance);
            showCosmicNotification({ title: "Added to My Sparks", message: data.message, tone: "success" });
        } catch (error) {
            showCosmicNotification({ title: "Could not unlock Spark", message: error.response?.data?.message || "Please try again.", tone: "error" });
        } finally {
            setBusyKey(null);
        }
    };

    return <div className="cosmic-ui-shell cosmic-sparks-page min-h-screen bg-[#09090b] text-white">
        <Head title="Sparks Marketplace" />
        <header className="border-b border-white/10 bg-[#0d0d10]/95 backdrop-blur">
            <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-4 lg:px-8">
                <div className="flex items-center gap-5"><Link href={route("dashboard")} className="text-sm font-semibold text-slate-400 hover:text-white">← Workspace</Link><div className="h-5 w-px bg-white/10"/><div><p className="text-xs font-semibold uppercase tracking-[0.22em] text-violet-300">Cosmic CMS v2.7</p><h1 className="text-lg font-semibold">Sparks Marketplace</h1></div></div>
                <CreditBalanceBadge balance={balance}/>
            </div>
        </header>

        <main className="mx-auto max-w-7xl px-5 py-10 lg:px-8">
            <section className="cosmic-sparks-hero overflow-hidden rounded-2xl border border-violet-300/15 bg-gradient-to-br from-violet-500/20 via-[#111116] to-cyan-400/10 p-7 md:p-10">
                <div className="max-w-3xl"><span className="rounded-full border border-white/10 bg-white/[0.05] px-3 py-1 text-xs font-semibold text-violet-200">✨ Reusable premium sections</span><h2 className="mt-5 text-4xl font-semibold tracking-tight md:text-5xl">Build your Spark collection.</h2><p className="mt-4 max-w-2xl text-base leading-7 text-slate-300">Unlock a Spark once, reuse it on any page, and add generic content for free. AI personalization costs only ⚡20.</p></div><div className="mt-6 flex flex-wrap gap-3"><div className="inline-flex items-center gap-3 rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-sm text-slate-200"><span className="font-semibold">Owned Sparks {ownedSparkSlots?.used ?? ownedCount}/{ownedSparkSlots?.limit_label ?? "Unlimited"}</span>{ownedSparkSlots?.at_limit && <Link href="/credits" className="font-semibold text-amber-200 underline underline-offset-2">Upgrade</Link>}</div><div className="inline-flex items-center rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-sm text-slate-300">Preview access: <span className="ml-1 font-semibold text-white">{previewPolicy?.limit_label ?? "All Sparks"}</span></div>{sharedSparkLibrary?.enabled && <div className="inline-flex items-center rounded-xl border border-cyan-300/15 bg-cyan-300/[0.06] px-3 py-2 text-sm text-cyan-100">Shared with {sharedSparkLibrary.workspace_name}: <span className="ml-1 font-semibold">{items.filter((item) => item.shared).length}</span></div>}</div>
            </section>

            <div className="mt-8 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex rounded-xl border border-white/10 bg-white/[0.03] p-1"><button onClick={() => setView("marketplace")} className={`rounded-lg px-4 py-2 text-sm font-semibold ${view === "marketplace" ? "bg-white text-slate-950" : "text-slate-400"}`}>Marketplace ({items.length})</button><button onClick={() => setView("owned")} className={`rounded-lg px-4 py-2 text-sm font-semibold ${view === "owned" ? "bg-white text-slate-950" : "text-slate-400"}`}>Owned ({items.filter((item) => item.owned).length || ownedCount})</button><button onClick={() => setView("favorites")} className={`rounded-lg px-4 py-2 text-sm font-semibold ${view === "favorites" ? "bg-white text-slate-950" : "text-slate-400"}`}>Favorites ({items.filter((item) => item.favorited).length})</button></div>
                <input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search Sparks..." className="h-11 w-full rounded-xl border border-white/10 bg-white/[0.04] px-4 text-sm text-white placeholder:text-slate-600 focus:border-violet-400 focus:outline-none lg:w-80"/>
            </div>

            <div className="mt-5 flex flex-wrap gap-2"><button onClick={() => setCategory("All")} className={`rounded-full px-3 py-1.5 text-xs font-semibold ${category === "All" ? "bg-violet-400 text-slate-950" : "border border-white/10 text-slate-400"}`}>All</button>{categories.map((item) => <button key={item} onClick={() => setCategory(item)} className={`rounded-full px-3 py-1.5 text-xs font-semibold ${category === item ? "bg-violet-400 text-slate-950" : "border border-white/10 text-slate-400"}`}>{item}</button>)}</div>

            {filtered.length ? <div className="mt-7 grid gap-5 md:grid-cols-2 xl:grid-cols-3">{filtered.map((spark, index) => <article key={spark.key} className="cosmic-marketplace-card group overflow-hidden rounded-xl border border-white/10 bg-[#111116] transition hover:border-violet-300/30">
                <div className={`relative h-52 overflow-hidden bg-gradient-to-br ${tones[index % tones.length]} p-3`}>
                    <div className="h-full overflow-hidden rounded-xl border border-white/10 bg-white shadow-xl">
                        <SparkCardPreview spark={spark} index={index} />
                    </div>
                    {spark.featured && <span className="absolute right-4 top-4 rounded-full bg-white px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-950">Featured</span>}
                </div>
                <div className="p-5"><div className="flex items-start justify-between gap-3"><div><p className="text-xs font-semibold uppercase tracking-wider text-violet-300">{spark.category} · {spark.collection}</p><h3 className="mt-1 text-xl font-semibold">{spark.name}</h3></div><span className={`rounded-lg border px-2.5 py-1.5 text-xs font-bold ${["owned", "purchased"].includes(spark.usage_state?.key) ? "border-emerald-400/20 bg-emerald-400/10 text-emerald-200" : spark.usage_state?.key === "shared" ? "border-cyan-300/20 bg-cyan-300/10 text-cyan-100" : ["plan_locked", "slots_full", "credits_needed", "preview_locked"].includes(spark.usage_state?.key) ? "border-amber-300/20 bg-amber-300/[0.08] text-amber-100" : "border-white/10 bg-white/[0.04] text-slate-200"}`}>{spark.usage_state?.label || (spark.owned ? "Owned" : `⚡ ${spark.credits}`)}</span></div><p className="mt-3 min-h-12 text-sm leading-6 text-slate-400">{spark.description}</p>{spark.usage_state?.message && ["plan_locked", "slots_full", "credits_needed", "preview_locked", "removed", "shared"].includes(spark.usage_state.key) && <p className="mt-2 text-xs leading-5 text-slate-500">{spark.usage_state.message}</p>}<div className="mt-5 flex gap-2"><button type="button" onClick={() => { if (spark.can_preview === false) { showCosmicNotification({ title: "Preview locked", message: spark.preview_access?.message || "Upgrade your plan to preview this Spark.", tone: "warning" }); return; } setPreviewSpark(spark); setPreviewVariant("white"); }} className="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:border-violet-400/40 hover:bg-white/5">{spark.can_preview === false ? "Preview locked" : "Preview"}</button>{spark.owned ? <div className="flex flex-1 gap-2"><div className="flex-1 rounded-xl border border-emerald-400/20 bg-emerald-400/[0.06] px-4 py-2.5 text-center text-sm font-semibold text-emerald-200">✓ In My Sparks</div>{sharedSparkLibrary?.can_manage && <button type="button" disabled={busyKey === `share:${spark.key}`} onClick={() => toggleShare(spark)} className={`rounded-xl border px-3 py-2.5 text-xs font-semibold transition disabled:opacity-50 ${spark.shared ? "border-cyan-300/25 bg-cyan-300/10 text-cyan-100" : "border-white/10 text-slate-300 hover:border-cyan-300/30"}`}>{busyKey === `share:${spark.key}` ? "Saving..." : spark.shared ? "Shared ✓" : "Share"}</button>}</div> : spark.can_install === false && spark.usage_state?.upgrade_url ? <Link href={spark.usage_state.upgrade_url} className="flex-1 rounded-xl bg-amber-200 px-4 py-2.5 text-center text-sm font-semibold text-slate-950">{spark.usage_state.actionLabel || "View upgrade"}</Link> : spark.can_install === false && spark.usage_state?.action === "buy_credits" ? <Link href="/credits" className="flex-1 rounded-xl bg-amber-200 px-4 py-2.5 text-center text-sm font-semibold text-slate-950">Add Cosmic Credits</Link> : <button disabled={busyKey === spark.key || spark.can_install === false} onClick={() => unlock(spark)} className="flex-1 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-950 disabled:opacity-50">{busyKey === spark.key ? "Adding..." : spark.usage_state?.actionLabel || `Add to My Sparks · ⚡${spark.credits}`}</button>}</div></div>
            </article>)}</div> : <div className="mt-8 rounded-2xl border border-dashed border-white/10 p-12 text-center text-slate-400">No Sparks match this view yet.</div>}
        </main>

        {previewSpark && <div className="fixed inset-0 z-[980] flex items-stretch justify-stretch">
            <button type="button" onClick={() => setPreviewSpark(null)} className="absolute inset-0 bg-black/85 backdrop-blur-sm" aria-label="Close Spark preview" />
            <section role="dialog" aria-modal="true" className="cosmic-spark-preview-modal relative z-10 flex h-screen w-screen max-w-none flex-col overflow-hidden rounded-none border border-white/10 bg-[#101014] text-white shadow-2xl">
                <header className="flex items-start justify-between gap-4 border-b border-white/10 px-5 py-4 sm:px-7">
                    <div><p className="text-[10px] font-bold uppercase tracking-[0.2em] text-violet-300">Spark Preview · {previewSpark.category}</p><h3 className="mt-1 text-xl font-semibold">{previewSpark.name}</h3><p className="mt-1 max-w-3xl text-sm text-slate-400">{previewSpark.description}</p></div>
                    <button type="button" onClick={() => setPreviewSpark(null)} className="rounded-xl border border-white/10 px-3 py-2 text-slate-400 hover:bg-white/5 hover:text-white">✕</button>
                </header>
                <div className="min-h-0 flex-1 overflow-auto bg-[#e5e7eb] p-0">
                    <div className="cosmic-spark-preview-stage min-h-full w-full"><div className="cosmic-spark-preview-stage-inner">
                        <div className="pointer-events-none w-full min-w-0 origin-top-left"><ActualSparkPreview spark={previewSpark} previewVariant={previewVariant} websiteTheme="midnight" /></div>
                    </div></div>
                </div>
                <footer className="flex flex-col gap-3 border-t border-white/10 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                    <div className="flex items-center gap-2 text-xs text-slate-400"><span>Preview appearance</span>{["primary", "white", "surface"].map((variant) => <button key={variant} type="button" aria-pressed={previewVariant === variant} data-active={previewVariant === variant ? "true" : "false"} onClick={() => setPreviewVariant(variant)} className="cosmic-spark-variant-toggle rounded-full px-2.5 py-1 font-semibold capitalize transition">{variant}</button>)}</div>
                    <div className="flex gap-2"><button type="button" onClick={() => setPreviewSpark(null)} className="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-slate-300">Close</button>{!previewSpark.owned && <button type="button" disabled={busyKey === previewSpark.key} onClick={() => unlock(previewSpark)} className="rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-slate-950 disabled:opacity-50">{busyKey === previewSpark.key ? "Adding..." : `Add to My Sparks · ⚡${previewSpark.credits}`}</button>}</div>
                </footer>
            </section>
        </div>}
    </div>;
}
