import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import CosmicBrandMark from '@/Components/CosmicBrandMark';

const FEATURES = [
    {
        title: 'AI website generation',
        text: 'Generate complete page directions from a short business prompt, then refine the result in the visual builder.',
    },
    {
        title: 'Reusable premium blocks',
        text: 'Build pages with a curated block library for heroes, services, testimonials, pricing, contact, and more.',
    },
    {
        title: 'Theme-aware builder',
        text: 'Every generated demo keeps the AI-selected theme from trial preview through the builder and purchased website.',
    },
    {
        title: 'Client-friendly editing',
        text: 'Clients can update text, images, links, and page content without touching code or complex design controls.',
    },
    {
        title: 'Multi-website workspace',
        text: 'Manage multiple client websites from one account with separate pages, settings, and publishing workflows.',
    },
];

const STATUS_LABELS = {
    active: 'Active / Paid',
    pending_payment: 'Pending payment',
    saved_trial: 'Saved trial',
    plan_selected: 'Plan selected',
    completed: 'Completed',
    failed: 'Failed',
    expired: 'Expired',
    generated_trial: 'Trial ready',
    building_trial: 'Building pages',
    partial_trial: 'Needs page retry',
};

function StatCard({ label, value, detail }) {
    return (
        <div className="rounded-2xl border border-white/10 bg-white/[0.035] p-5">
            <p className="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">{label}</p>
            <p className="mt-2 text-3xl font-black text-white">{value}</p>
            <p className="mt-1 text-xs text-slate-500">{detail}</p>
        </div>
    );
}

function LeadTable({ leads }) {
    const [search, setSearch] = useState('');
    const [source, setSource] = useState('all');
    const [status, setStatus] = useState('all');
    const [copiedKey, setCopiedKey] = useState(null);

    const copyTrialLink = async (lead) => {
        if (!lead?.trial_url) return;

        try {
            await navigator.clipboard.writeText(lead.trial_url);
            setCopiedKey(lead.key);
            window.setTimeout(() => setCopiedKey((current) => current === lead.key ? null : current), 1600);
        } catch (error) {
            window.prompt('Copy trial link', lead.trial_url);
        }
    };

    const filtered = useMemo(() => {
        const needle = search.trim().toLowerCase();
        return leads.filter((lead) => {
            const matchesSearch = !needle || [lead.name, lead.email, lead.business_name, lead.industry, lead.selected_plan, lead.trial_url]
                .filter(Boolean)
                .some((value) => String(value).toLowerCase().includes(needle));
            const matchesSource = source === 'all' || lead.source === source;
            const matchesStatus = status === 'all' || lead.status === status;
            return matchesSearch && matchesSource && matchesStatus;
        });
    }, [leads, search, source, status]);

    const statuses = [...new Set(leads.map((lead) => lead.status).filter(Boolean))];

    return (
        <section id="leads" className="scroll-mt-8 py-14">
            <div className="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <p className="text-xs font-bold uppercase tracking-[0.18em] text-emerald-300">Owner CRM</p>
                    <h2 className="mt-3 text-3xl font-bold text-white">Start & pricing signups</h2>
                    <p className="mt-2 text-sm text-slate-400">Every Luna staging website, captured lead, and registered pricing account—latest first.</p>
                </div>
                <div className="grid gap-2 sm:grid-cols-3 xl:w-[680px]">
                    <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search email, business, plan…" className="rounded-xl border border-white/10 bg-black/20 px-3 py-2.5 text-sm text-white outline-none placeholder:text-slate-600 focus:border-emerald-400/50" />
                    <select value={source} onChange={(e) => setSource(e.target.value)} className="rounded-xl border border-white/10 bg-[#111318] px-3 py-2.5 text-sm text-slate-300">
                        <option value="all">All sources</option><option value="start">Luna trial</option><option value="pricing">Pricing page</option>
                    </select>
                    <select value={status} onChange={(e) => setStatus(e.target.value)} className="rounded-xl border border-white/10 bg-[#111318] px-3 py-2.5 text-sm text-slate-300">
                        <option value="all">All statuses</option>{statuses.map((item) => <option key={item} value={item}>{STATUS_LABELS[item] || item.replaceAll('_', ' ')}</option>)}
                    </select>
                </div>
            </div>

            <div className="mt-6 overflow-hidden rounded-2xl border border-white/10 bg-white/[0.025]">
                <div className="overflow-x-auto">
                    <table className="min-w-full text-left text-sm">
                        <thead className="bg-white/[0.035] text-xs uppercase tracking-[0.12em] text-slate-500"><tr><th className="px-4 py-3">Contact</th><th className="px-4 py-3">Business</th><th className="px-4 py-3">Source</th><th className="px-4 py-3">Plan</th><th className="px-4 py-3">Status</th><th className="px-4 py-3">When</th><th className="min-w-[320px] px-4 py-3">Trial link</th></tr></thead>
                        <tbody>
                            {filtered.map((lead) => <tr key={lead.key} className="border-t border-white/[0.07] text-slate-300">
                                <td className="px-4 py-4"><div className="font-semibold text-white">{lead.email}</div>{lead.name !== '—' && <div className="mt-0.5 text-xs text-slate-500">{lead.name}</div>}</td>
                                <td className="px-4 py-4"><div className="font-medium text-slate-200">{lead.business_name}</div><div className="mt-0.5 text-xs text-slate-500">{lead.industry}{lead.location && lead.location !== '—' ? ` · ${lead.location}` : ''}</div></td>
                                <td className="px-4 py-4"><span className={`rounded-full px-2.5 py-1 text-xs font-bold ${lead.source === 'start' ? 'bg-cyan-400/10 text-cyan-300' : 'bg-violet-400/10 text-violet-300'}`}>{lead.source === 'start' ? 'Luna trial' : 'Pricing'}</span></td>
                                <td className="px-4 py-4 text-sm capitalize text-slate-300">{lead.selected_plan ? lead.selected_plan.replaceAll('_', ' ') : '—'}</td>
                                <td className="px-4 py-4"><span className={`rounded-full px-2.5 py-1 text-xs font-bold ${lead.status === 'active' ? 'bg-emerald-400/10 text-emerald-300' : lead.status === 'failed' || lead.status === 'expired' ? 'bg-rose-400/10 text-rose-300' : 'bg-amber-400/10 text-amber-300'}`}>{STATUS_LABELS[lead.status] || String(lead.status || 'unknown').replaceAll('_', ' ')}</span></td>
                                <td className="whitespace-nowrap px-4 py-4 text-xs text-slate-500">{lead.created_at_label || '—'}</td>
                                <td className="px-4 py-4">
                                    {lead.trial_url ? (
                                        <div className="min-w-[300px]">
                                            <a
                                                href={lead.trial_url}
                                                target="_blank"
                                                rel="noreferrer"
                                                title={lead.trial_url}
                                                className="block max-w-[420px] truncate font-mono text-[11px] text-cyan-300 transition hover:text-cyan-200 hover:underline"
                                            >
                                                {lead.trial_url}
                                            </a>
                                            <div className="mt-2 flex items-center gap-2">
                                                <a
                                                    href={lead.trial_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="inline-flex rounded-lg border border-cyan-300/20 bg-cyan-300/10 px-3 py-1.5 text-xs font-bold text-cyan-200 transition hover:bg-cyan-300/15 hover:text-white"
                                                >
                                                    Open staging
                                                </a>
                                                <button
                                                    type="button"
                                                    onClick={() => copyTrialLink(lead)}
                                                    className="inline-flex rounded-lg border border-white/10 px-3 py-1.5 text-xs font-bold text-slate-300 transition hover:bg-white/5 hover:text-white"
                                                >
                                                    {copiedKey === lead.key ? 'Copied!' : 'Copy link'}
                                                </button>
                                            </div>
                                            <p className="mt-2 text-[11px] text-slate-500">
                                                {lead.page_count || 1} page{Number(lead.page_count || 1) === 1 ? '' : 's'}
                                                {lead.bundle_status ? ` · ${String(lead.bundle_status).replaceAll('_', ' ')}` : ''}
                                                {' · Regenerate inside Builder'}
                                            </p>
                                        </div>
                                    ) : <span className="text-xs text-slate-700">No trial link</span>}
                                </td>
                            </tr>)}
                        </tbody>
                    </table>
                </div>
                {!filtered.length && <div className="px-6 py-12 text-center text-sm text-slate-500">No leads match the current filters.</div>}
            </div>
            <p className="mt-3 text-right text-xs text-slate-600">Showing {filtered.length} of {leads.length} records</p>
        </section>
    );
}

export default function SalesIndex({ leads = [], leadStats = {} }) {
    const user = usePage().props.auth?.user;
    const logout = () => router.post(route('logout'));

    return (
        <div className="cosmic-ui-shell min-h-screen bg-[#090b12] text-slate-100">
            <Head title="Sales" />

            <header className="border-b border-white/10 bg-[#111113]">
                <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                    <div className="flex min-w-0 items-center gap-3">
                        <CosmicBrandMark />
                        <div className="min-w-0">
                            <p className="truncate font-semibold text-white">Cosmic CMS</p>
                            <p className="truncate text-xs text-slate-500">Sales toolkit</p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Link
                            href={route('dashboard')}
                            className="rounded-lg border border-white/10 px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5 hover:text-white"
                        >
                            Dashboard
                        </Link>
                        <button
                            type="button"
                            onClick={logout}
                            className="hidden rounded-lg border border-white/10 px-3 py-2 text-sm text-slate-400 transition hover:bg-white/5 hover:text-white sm:inline-flex"
                        >
                            Log out{user?.name ? ` · ${user.name}` : ''}
                        </button>
                    </div>
                </div>
            </header>

            <main className="py-10">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <section className="overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-cyan-400/10 via-white/[0.035] to-violet-500/10 px-6 py-10 shadow-2xl shadow-black/30 sm:px-10 sm:py-14">
                        <div className="max-w-3xl">
                            <span className="inline-flex rounded-full border border-cyan-300/20 bg-cyan-300/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-cyan-200">
                                Sales overview
                            </span>
                            <h1 className="mt-5 text-4xl font-black tracking-tight text-white sm:text-5xl">
                                Follow every lead from first visit to paid customer.
                            </h1>
                            <p className="mt-5 max-w-2xl text-base leading-7 text-slate-300 sm:text-lg">
                                Review trial and pricing leads, account registrations, and paid conversions from one focused sales workspace.
                            </p>
                            <div className="mt-8">
                                <Link
                                    href="/"
                                    className="inline-flex rounded-xl bg-cyan-400 px-5 py-3 text-sm font-bold text-slate-950 transition hover:bg-cyan-300"
                                >
                                    Open public Luna
                                </Link>
                            </div>
                        </div>
                    </section>

                    <section className="pt-10">
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                            <StatCard label="Total leads" value={leadStats.total || 0} detail="Saved + registered" />
                            <StatCard label="Start page" value={leadStats.start || 0} detail="Trial-origin contacts" />
                            <StatCard label="Pricing page" value={leadStats.pricing || 0} detail="Direct registrations" />
                            <StatCard label="Registered" value={leadStats.registered || 0} detail="Accounts created" />
                            <StatCard label="Active / paid" value={leadStats.paid || 0} detail="Plan active" />
                        </div>
                    </section>

                    <LeadTable leads={leads} />

                    <section className="py-14">
                        <div className="max-w-2xl">
                            <p className="text-xs font-bold uppercase tracking-[0.18em] text-violet-300">Core features</p>
                            <h2 className="mt-3 text-3xl font-bold text-white">Everything needed for a faster sales workflow</h2>
                        </div>
                        <div className="mt-8 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {FEATURES.map((feature) => (
                                <article key={feature.title} className="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
                                    <h3 className="font-semibold text-white">{feature.title}</h3>
                                    <p className="mt-2 text-sm leading-6 text-slate-400">{feature.text}</p>
                                </article>
                            ))}
                        </div>
                    </section>
                </div>
            </main>
        </div>
    );
}
