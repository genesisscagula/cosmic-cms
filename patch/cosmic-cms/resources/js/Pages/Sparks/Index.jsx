import { Head, Link } from '@inertiajs/react';
import axios from 'axios';
import { useMemo, useState } from 'react';
import CreditBalanceBadge from '@/Components/CosmicCredits/CreditBalanceBadge';
import { useCreditBalance } from '@/Components/CosmicCredits/CreditBalanceContext';
import { showCosmicNotification } from '@/Components/CosmicNotification';

const tones = [
    'from-violet-500/30 via-fuchsia-500/10 to-cyan-400/20',
    'from-emerald-500/25 via-teal-500/10 to-blue-500/20',
    'from-amber-400/25 via-orange-500/10 to-rose-500/20',
    'from-sky-500/25 via-indigo-500/10 to-violet-500/20',
];

function SparkPreview({ spark, onClose, onUnlock, busy }) {
    if (!spark) return null;
    return <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm" onMouseDown={onClose}>
        <div className="w-full max-w-5xl overflow-hidden rounded-3xl border border-white/10 bg-[#111116] shadow-2xl" onMouseDown={(e) => e.stopPropagation()}>
            <div className="flex items-center justify-between border-b border-white/10 px-6 py-4">
                <div><p className="text-xs font-semibold uppercase tracking-[0.22em] text-violet-300">Spark preview</p><h2 className="mt-1 text-xl font-semibold text-white">{spark.name}</h2></div>
                <button onClick={onClose} className="rounded-xl border border-white/10 px-3 py-2 text-sm text-slate-300 hover:bg-white/5">Close</button>
            </div>
            <div className={`min-h-[420px] bg-gradient-to-br ${tones[spark.id % tones.length]} p-8`}>
                <div className="mx-auto max-w-4xl overflow-hidden rounded-2xl border border-white/15 bg-[#0b0b0e]/90 shadow-2xl">
                    <div className="flex items-center gap-2 border-b border-white/10 px-4 py-3"><span className="h-2.5 w-2.5 rounded-full bg-rose-400/80"/><span className="h-2.5 w-2.5 rounded-full bg-amber-300/80"/><span className="h-2.5 w-2.5 rounded-full bg-emerald-400/80"/></div>
                    <div className="grid gap-8 p-8 md:grid-cols-[1.2fr_.8fr] md:p-12"><div><span className="rounded-full border border-violet-300/20 bg-violet-300/10 px-3 py-1 text-xs font-semibold text-violet-200">{spark.category?.name}</span><h3 className="mt-5 text-4xl font-semibold tracking-tight text-white">A premium page, ready for your brand.</h3><p className="mt-4 max-w-xl text-slate-300">{spark.description}</p><div className="mt-7 flex gap-3"><div className="rounded-xl bg-white px-5 py-3 text-sm font-semibold text-slate-950">Primary action</div><div className="rounded-xl border border-white/15 px-5 py-3 text-sm font-semibold text-white">Learn more</div></div></div><div className="rounded-2xl border border-white/10 bg-white/[0.04] p-5"><div className="h-36 rounded-xl bg-gradient-to-br from-white/10 to-white/[0.02]"/><div className="mt-4 h-3 w-4/5 rounded bg-white/10"/><div className="mt-2 h-3 w-3/5 rounded bg-white/10"/><div className="mt-5 grid grid-cols-2 gap-3"><div className="h-16 rounded-lg bg-white/[0.05]"/><div className="h-16 rounded-lg bg-white/[0.05]"/></div></div></div>
                </div>
            </div>
            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-white/10 px-6 py-4"><p className="text-sm text-slate-400">Uses your website theme. AI personalisation arrives in the install step.</p>{spark.owned ? <span className="rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-2 text-sm font-semibold text-emerald-200">Owned</span> : <button disabled={busy} onClick={() => onUnlock(spark)} className="rounded-xl bg-white px-5 py-2.5 text-sm font-semibold text-slate-950 disabled:opacity-50">{busy ? 'Unlocking...' : `Unlock for ⚡ ${spark.credits}`}</button>}</div>
        </div>
    </div>;
}

export default function Index({ sparks = [], categories = [], ownedCount = 0 }) {
    const { balance, setBalance } = useCreditBalance();
    const [query, setQuery] = useState('');
    const [category, setCategory] = useState('all');
    const [view, setView] = useState('marketplace');
    const [items, setItems] = useState(sparks);
    const [preview, setPreview] = useState(null);
    const [busyId, setBusyId] = useState(null);

    const filtered = useMemo(() => items.filter((spark) => {
        if (view === 'owned' && !spark.owned) return false;
        if (category !== 'all' && spark.category?.slug !== category) return false;
        const haystack = `${spark.name} ${spark.description} ${spark.category?.name}`.toLowerCase();
        return haystack.includes(query.trim().toLowerCase());
    }), [items, query, category, view]);

    const unlock = async (spark) => {
        if (balance < spark.credits) { showCosmicNotification({ title: 'Not enough credits', message: `You need ⚡ ${spark.credits} to unlock this Spark.`, tone: 'error' }); return; }
        setBusyId(spark.id);
        try {
            const response = await axios.post(route('sparks.unlock', spark.id));
            setItems((current) => current.map((item) => item.id === spark.id ? { ...item, owned: true } : item));
            setPreview((current) => current?.id === spark.id ? { ...current, owned: true } : current);
            setBalance(response.data.credit_balance);
            showCosmicNotification({ title: 'Spark unlocked', message: response.data.message, tone: 'success' });
        } catch (error) {
            showCosmicNotification({ title: 'Could not unlock Spark', message: error.response?.data?.message || 'Please try again.', tone: 'error' });
        } finally { setBusyId(null); }
    };

    return <div className="min-h-screen bg-[#09090b] text-white">
        <Head title="Sparks Marketplace" />
        <header className="border-b border-white/10 bg-[#0d0d10]/95 backdrop-blur">
            <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-4 lg:px-8">
                <div className="flex items-center gap-5"><Link href={route('dashboard')} className="text-sm font-semibold text-slate-400 hover:text-white">← Workspace</Link><div className="h-5 w-px bg-white/10"/><div><p className="text-xs font-semibold uppercase tracking-[0.22em] text-violet-300">Cosmic CMS v2.6</p><h1 className="text-lg font-semibold">Sparks Marketplace</h1></div></div>
                <CreditBalanceBadge balance={balance}/>
            </div>
        </header>
        <main className="mx-auto max-w-7xl px-5 py-10 lg:px-8">
            <section className="overflow-hidden rounded-3xl border border-violet-300/15 bg-gradient-to-br from-violet-500/20 via-[#111116] to-cyan-400/10 p-7 md:p-10">
                <div className="max-w-3xl"><span className="rounded-full border border-white/10 bg-white/[0.05] px-3 py-1 text-xs font-semibold text-violet-200">✨ Premium page experiences</span><h2 className="mt-5 text-4xl font-semibold tracking-tight md:text-5xl">Start with a Spark.<br/>Make it completely yours.</h2><p className="mt-4 max-w-2xl text-base leading-7 text-slate-300">Unlock reusable premium page layouts with Cosmic Credits. Install and AI personalisation are the next step in v2.6.</p></div>
            </section>
            <div className="mt-8 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex rounded-xl border border-white/10 bg-white/[0.03] p-1"><button onClick={() => setView('marketplace')} className={`rounded-lg px-4 py-2 text-sm font-semibold ${view === 'marketplace' ? 'bg-white text-slate-950' : 'text-slate-400'}`}>Marketplace</button><button onClick={() => setView('owned')} className={`rounded-lg px-4 py-2 text-sm font-semibold ${view === 'owned' ? 'bg-white text-slate-950' : 'text-slate-400'}`}>My Sparks ({items.filter(i => i.owned).length || ownedCount})</button></div>
                <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Search Sparks..." className="h-11 w-full rounded-xl border border-white/10 bg-white/[0.04] px-4 text-sm text-white placeholder:text-slate-600 focus:border-violet-400 focus:outline-none lg:w-80"/>
            </div>
            <div className="mt-5 flex flex-wrap gap-2"><button onClick={() => setCategory('all')} className={`rounded-full px-3 py-1.5 text-xs font-semibold ${category === 'all' ? 'bg-violet-400 text-slate-950' : 'border border-white/10 text-slate-400'}`}>All</button>{categories.map((item) => <button key={item.id} onClick={() => setCategory(item.slug)} className={`rounded-full px-3 py-1.5 text-xs font-semibold ${category === item.slug ? 'bg-violet-400 text-slate-950' : 'border border-white/10 text-slate-400'}`}>{item.name}</button>)}</div>
            {filtered.length ? <div className="mt-7 grid gap-5 md:grid-cols-2 xl:grid-cols-3">{filtered.map((spark, index) => <article key={spark.id} className="group overflow-hidden rounded-2xl border border-white/10 bg-[#111116] transition hover:-translate-y-0.5 hover:border-violet-300/30">
                <button onClick={() => setPreview(spark)} className={`relative block h-52 w-full overflow-hidden bg-gradient-to-br ${tones[index % tones.length]} p-5 text-left`}><div className="absolute inset-5 rounded-xl border border-white/10 bg-[#0b0b0e]/80 p-5 shadow-xl"><div className="h-2.5 w-20 rounded bg-white/15"/><div className="mt-7 h-5 w-4/5 rounded bg-white/20"/><div className="mt-3 h-3 w-3/5 rounded bg-white/10"/><div className="mt-8 grid grid-cols-3 gap-2"><div className="h-12 rounded bg-white/[0.06]"/><div className="h-12 rounded bg-white/[0.06]"/><div className="h-12 rounded bg-white/[0.06]"/></div></div>{spark.is_featured && <span className="absolute right-4 top-4 rounded-full bg-white px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-950">Featured</span>}</button>
                <div className="p-5"><div className="flex items-start justify-between gap-3"><div><p className="text-xs font-semibold uppercase tracking-wider text-violet-300">{spark.category?.name}</p><h3 className="mt-1 text-xl font-semibold">{spark.name}</h3></div><span className="rounded-lg border border-amber-300/20 bg-amber-300/[0.08] px-2.5 py-1.5 text-xs font-bold text-amber-100">⚡ {spark.credits}</span></div><p className="mt-3 min-h-12 text-sm leading-6 text-slate-400">{spark.description}</p><div className="mt-5 flex gap-2"><button onClick={() => setPreview(spark)} className="flex-1 rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-white hover:bg-white/5">Preview</button>{spark.owned ? <button className="flex-1 cursor-default rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-2.5 text-sm font-semibold text-emerald-200">Owned</button> : <button disabled={busyId === spark.id} onClick={() => unlock(spark)} className="flex-1 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-950 disabled:opacity-50">{busyId === spark.id ? 'Unlocking...' : 'Unlock'}</button>}</div></div>
            </article>)}</div> : <div className="mt-8 rounded-2xl border border-dashed border-white/10 p-12 text-center text-slate-400">No Sparks match this view yet.</div>}
        </main>
        <SparkPreview spark={preview} onClose={() => setPreview(null)} onUnlock={unlock} busy={busyId === preview?.id}/>
    </div>;
}
