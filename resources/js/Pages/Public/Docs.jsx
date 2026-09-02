import { useMemo, useState } from 'react';
import { Link } from '@inertiajs/react';
import SeoHead from '@/Components/Seo/SeoHead';
import PublicSiteLayout from '@/Components/Public/PublicSiteLayout';
import PublicInnerHero from '@/Components/Public/PublicInnerHero';
import PublicCta from '@/Components/Public/PublicCta';
import { docs, docsSections } from './resourceContent';

function DocLink({ item, compact = false }) {
    const doc = docs.find((entry) => entry.slug === item.slug);
    return (
        <Link
            href={`/docs/${item.slug}`}
            className={`group block rounded-xl border border-transparent transition hover:border-emerald-100 hover:bg-emerald-50/60 ${compact ? 'px-3 py-2.5' : 'p-4'}`}
        >
            <span className="block text-sm font-extrabold text-[#07132c] transition group-hover:text-emerald-800">{item.title}</span>
            {!compact && doc?.description && <span className="mt-1 block text-xs leading-5 text-slate-500">{doc.description}</span>}
        </Link>
    );
}

export default function Docs() {
    const [query, setQuery] = useState('');
    const needle = query.trim().toLowerCase();

    const searchResults = useMemo(() => {
        if (!needle) return [];
        return docs.filter((doc) => `${doc.title} ${doc.description} ${doc.category}`.toLowerCase().includes(needle));
    }, [needle]);

    return (
        <PublicSiteLayout>
            <SeoHead
                title="Documentation | Cosmic CMS"
                description="Documentation for Luna, the Cosmic CMS visual Builder, Marketplace websites, publishing, plans, credits, and core website workflows."
                path="/docs"
            />

            <PublicInnerHero
                eyebrow="Documentation"
                title="Clear documentation for the "
                highlight="Cosmic platform."
                description="Find the product behavior, workflows, and reference material you need for Luna, the Builder, Marketplace websites, publishing, and your account."
                breadcrumbs={[{ label: 'Documentation' }]}
            >
                <div className="relative max-w-3xl">
                    <div className="rounded-2xl border border-slate-200 bg-white p-2 shadow-[0_18px_60px_-34px_rgba(15,23,42,.32)]">
                        <label className="flex items-center gap-3 rounded-xl px-3">
                            <span className="text-slate-400">⌕</span>
                            <input
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                type="search"
                                placeholder="Search documentation — Luna, Builder, publishing…"
                                className="min-h-11 flex-1 border-0 bg-transparent px-0 text-sm text-slate-800 placeholder:text-slate-400 focus:ring-0"
                                aria-label="Search documentation"
                            />
                            <span className="hidden rounded-lg bg-slate-100 px-2 py-1 text-[9px] font-extrabold uppercase tracking-[.12em] text-slate-400 sm:inline">Docs</span>
                        </label>
                    </div>
                    {needle && (
                        <div className="mt-3 overflow-hidden rounded-2xl border border-slate-200 bg-white p-2 shadow-[0_24px_75px_-30px_rgba(15,23,42,.28)]">
                            {searchResults.length ? searchResults.slice(0, 6).map((doc) => (
                                <Link key={doc.slug} href={`/docs/${doc.slug}`} className="flex items-start gap-3 rounded-xl px-3 py-3 transition hover:bg-emerald-50">
                                    <span className="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-slate-100 text-[10px] font-extrabold text-slate-600">{doc.icon}</span>
                                    <span>
                                        <span className="block text-sm font-extrabold text-[#07132c]">{doc.title}</span>
                                        <span className="mt-0.5 block text-[11px] text-slate-500">{doc.category}</span>
                                    </span>
                                </Link>
                            )) : (
                                <div className="px-4 py-5 text-sm text-slate-500">No documentation matches “{query}”.</div>
                            )}
                        </div>
                    )}
                </div>
            </PublicInnerHero>

            <section className="mx-auto max-w-[1240px] px-5 py-14 sm:px-6 lg:px-8 lg:py-20">
                <div className="grid gap-7 lg:grid-cols-[270px_minmax(0,1fr)]">
                    <aside data-public-scroll-region className="max-h-[320px] self-start overflow-y-auto rounded-[24px] border border-slate-200 bg-slate-50/70 p-4 lg:sticky lg:top-24 lg:max-h-[calc(100vh-7rem)]">
                        <div className="flex items-center justify-between px-3 py-2">
                            <p className="text-[10px] font-extrabold uppercase tracking-[.18em] text-slate-400">Documentation</p>
                            <span className="rounded-full bg-emerald-100 px-2 py-1 text-[9px] font-extrabold text-emerald-700">v1</span>
                        </div>
                        <div className="mt-2 space-y-4">
                            {docsSections.map((section) => (
                                <div key={section.label}>
                                    <p className="px-3 text-[10px] font-extrabold uppercase tracking-[.16em] text-emerald-700">{section.label}</p>
                                    <div className="mt-1.5">
                                        {section.items.map((item) => <DocLink key={item.slug} item={item} compact />)}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </aside>

                    <div className="min-w-0">
                        <div className="relative overflow-hidden rounded-[30px] bg-[radial-gradient(circle_at_90%_25%,rgba(52,211,153,.24),transparent_28%),linear-gradient(120deg,#06142e_0%,#082c39_58%,#07503d_100%)] p-7 text-white shadow-[0_30px_90px_-42px_rgba(2,32,44,.68)] sm:p-9">
                            <div className="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full border border-emerald-300/20" />
                            <div className="relative grid gap-8 lg:grid-cols-[1fr_.82fr] lg:items-end">
                                <div>
                                    <p className="text-[10px] font-extrabold uppercase tracking-[.19em] text-emerald-300">Quick start</p>
                                    <h1 className="mt-3 max-w-2xl text-3xl font-extrabold tracking-[-.04em] sm:text-4xl">Understand the platform in three short reads.</h1>
                                    <p className="mt-4 max-w-2xl text-sm leading-7 text-slate-300">Start with the platform overview, create a first website, then learn how Luna fits into ongoing editing and page creation.</p>
                                </div>
                                <div className="grid gap-2.5">
                                    {[
                                        ['01', 'Cosmic CMS overview', '/docs/cosmic-overview'],
                                        ['02', 'Create your first website', '/docs/create-first-website'],
                                        ['03', 'Working with Luna', '/docs/luna-basics'],
                                    ].map(([number, title, href]) => (
                                        <Link key={href} href={href} className="flex items-center gap-3 rounded-xl border border-white/10 bg-white/[.06] p-3 backdrop-blur-sm transition hover:bg-white/[.1]">
                                            <span className="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-emerald-400 text-[9px] font-extrabold text-emerald-950">{number}</span>
                                            <span className="flex-1 text-xs font-extrabold text-white">{title}</span>
                                            <span className="text-emerald-300">→</span>
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        </div>

                        <div className="mt-10">
                            <div className="flex items-end justify-between gap-6">
                                <div>
                                    <p className="text-[10px] font-extrabold uppercase tracking-[.18em] text-emerald-700">Browse by area</p>
                                    <h2 className="mt-2 text-2xl font-extrabold tracking-[-.035em] text-[#07132c] sm:text-3xl">Everything is grouped around real workflows.</h2>
                                </div>
                                <Link href="/help" className="hidden text-xs font-extrabold text-emerald-700 sm:inline">Need help? →</Link>
                            </div>

                            <div className="mt-6 grid gap-4 md:grid-cols-2">
                                {docsSections.map((section, index) => (
                                    <div key={section.label} className="rounded-[22px] border border-slate-200 bg-white p-5 shadow-[0_18px_55px_-40px_rgba(15,23,42,.28)]">
                                        <div className="flex items-center gap-3">
                                            <span className="grid h-10 w-10 place-items-center rounded-xl bg-emerald-50 text-[11px] font-extrabold text-emerald-700 ring-1 ring-emerald-100">{['01', '02', '03', '04', '05', '06'][index]}</span>
                                            <div>
                                                <p className="text-[9px] font-extrabold uppercase tracking-[.16em] text-slate-400">Documentation area</p>
                                                <h3 className="mt-0.5 text-base font-semibold text-[#07132c]">{section.label}</h3>
                                            </div>
                                        </div>
                                        <div className="mt-3 -mx-1">
                                            {section.items.map((item) => <DocLink key={item.slug} item={item} />)}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="mt-10 grid gap-4 sm:grid-cols-3">
                            {[
                                ['✦', 'Ask Luna', 'For a supported website change, describe the outcome directly inside Cosmic.'],
                                ['?', 'Help Center', 'Use troubleshooting and account guidance when something is not behaving as expected.'],
                                ['↗', 'Product updates', 'Check recent platform changes when a workflow or feature has evolved.'],
                            ].map(([icon, title, copy], index) => (
                                <div key={title} className="rounded-[22px] border border-slate-200 bg-slate-50/75 p-5">
                                    <span className="grid h-10 w-10 place-items-center rounded-xl bg-white text-xs font-extrabold text-emerald-700 shadow-sm ring-1 ring-slate-200">{icon}</span>
                                    <h3 className="mt-4 text-sm font-semibold text-[#07132c]">{title}</h3>
                                    <p className="mt-2 text-xs leading-5 text-slate-500">{copy}</p>
                                    {index === 1 && <Link href="/help" className="mt-4 inline-flex text-[11px] font-extrabold text-emerald-700">Open Help Center →</Link>}
                                    {index === 2 && <Link href="/updates" className="mt-4 inline-flex text-[11px] font-extrabold text-emerald-700">View updates →</Link>}
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </section>

            <PublicCta
                eyebrow="From docs to done"
                title="Ready to put the workflow into practice?"
                description="Create a website, use Luna for the first draft, and keep the documentation nearby when you need product-level detail."
                secondaryLabel="Browse guides"
                secondaryHref="/guides"
            />
        </PublicSiteLayout>
    );
}
