import { useMemo, useState } from 'react';
import { Link } from '@inertiajs/react';
import SeoHead from '@/Components/Seo/SeoHead';
import PublicSiteLayout from '@/Components/Public/PublicSiteLayout';
import PublicInnerHero from '@/Components/Public/PublicInnerHero';
import PublicCta from '@/Components/Public/PublicCta';
import { guideCategories, guides } from './resourceContent';

function GuideCard({ guide }) {
    return (
        <Link
            href={`/guides/${guide.slug}`}
            className="group flex h-full flex-col rounded-[24px] border border-slate-200 bg-white p-6 shadow-[0_20px_60px_-38px_rgba(15,23,42,.32)] transition duration-300 hover:-translate-y-1 hover:border-emerald-200 hover:shadow-[0_28px_75px_-38px_rgba(5,150,105,.28)]"
        >
            <div className="flex items-start justify-between gap-4">
                <span className="grid h-11 w-11 place-items-center rounded-xl bg-emerald-50 text-sm font-extrabold text-emerald-700 ring-1 ring-emerald-100">{guide.icon}</span>
                <span className="rounded-full bg-slate-100 px-3 py-1.5 text-[10px] font-extrabold text-slate-500">{guide.readTime}</span>
            </div>
            <p className="mt-5 text-[10px] font-extrabold uppercase tracking-[.18em] text-emerald-700">{guide.category}</p>
            <h2 className="mt-2 text-xl font-extrabold tracking-[-.03em] text-[#07132c] transition group-hover:text-emerald-800">{guide.title}</h2>
            <p className="mt-3 flex-1 text-sm leading-6 text-slate-600">{guide.description}</p>
            <div className="mt-5 flex items-center justify-between border-t border-slate-100 pt-4">
                <span className="text-[11px] font-bold text-slate-400">{guide.level}</span>
                <span className="text-xs font-extrabold text-emerald-700">Read guide →</span>
            </div>
        </Link>
    );
}

export default function Guides() {
    const [category, setCategory] = useState('All');
    const [query, setQuery] = useState('');

    const filteredGuides = useMemo(() => {
        const needle = query.trim().toLowerCase();
        return guides.filter((guide) => {
            const categoryMatches = category === 'All' || guide.category === category;
            const queryMatches = !needle || `${guide.title} ${guide.description} ${guide.category}`.toLowerCase().includes(needle);
            return categoryMatches && queryMatches;
        });
    }, [category, query]);

    const featuredGuide = guides.find((guide) => guide.featured) ?? guides[0];

    return (
        <PublicSiteLayout>
            <SeoHead
                title="Website Building Guides | Cosmic CMS"
                description="Practical guides for AI website creation, design systems, content, SEO, publishing, and growing a website with Cosmic CMS."
                path="/guides"
            />

            <PublicInnerHero
                eyebrow="Learn with Cosmic"
                title="Practical guides for building the "
                highlight="right way."
                description="Useful playbooks for planning, creating, refining, launching, and improving modern business websites — without turning every task into a technical project."
                breadcrumbs={[{ label: 'Guides' }]}
            >
                <div className="max-w-2xl rounded-2xl border border-slate-200 bg-white p-2 shadow-[0_18px_60px_-34px_rgba(15,23,42,.32)]">
                    <label className="flex items-center gap-3 rounded-xl px-3">
                        <span className="text-slate-400">⌕</span>
                        <input
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            type="search"
                            placeholder="Search guides — prompts, design, SEO, publishing…"
                            className="min-h-11 flex-1 border-0 bg-transparent px-0 text-sm text-slate-800 placeholder:text-slate-400 focus:ring-0"
                            aria-label="Search guides"
                        />
                        <span className="hidden rounded-lg bg-slate-100 px-2 py-1 text-[9px] font-extrabold uppercase tracking-[.12em] text-slate-400 sm:inline">Guides</span>
                    </label>
                </div>
            </PublicInnerHero>

            <section className="mx-auto max-w-[1240px] px-5 py-16 sm:px-6 lg:px-8 lg:py-20">
                <div className="grid items-stretch gap-6 lg:grid-cols-[1.18fr_.82fr]">
                    <Link href={`/guides/${featuredGuide.slug}`} className="group relative overflow-hidden rounded-[30px] bg-[radial-gradient(circle_at_82%_25%,rgba(52,211,153,.24),transparent_27%),linear-gradient(125deg,#06142e_0%,#08283a_58%,#07503d_100%)] p-7 text-white shadow-[0_30px_90px_-42px_rgba(2,32,44,.7)] sm:p-9">
                        <div className="pointer-events-none absolute -right-16 -top-20 h-64 w-64 rounded-full border border-emerald-300/20" />
                        <div className="relative flex min-h-[315px] flex-col justify-between">
                            <div>
                                <div className="flex flex-wrap items-center gap-3">
                                    <span className="rounded-full border border-emerald-300/25 bg-emerald-300/10 px-3 py-1.5 text-[10px] font-extrabold uppercase tracking-[.16em] text-emerald-200">Featured guide</span>
                                    <span className="text-[11px] font-bold text-slate-400">{featuredGuide.readTime}</span>
                                </div>
                                <h2 className="mt-6 max-w-2xl text-3xl font-extrabold tracking-[-.04em] sm:text-4xl">{featuredGuide.title}</h2>
                                <p className="mt-4 max-w-2xl text-sm leading-7 text-slate-300 sm:text-base">{featuredGuide.description}</p>
                            </div>
                            <div className="mt-8 flex items-end justify-between gap-6">
                                <div className="flex flex-wrap gap-2">
                                    {featuredGuide.takeaways.slice(0, 2).map((item) => (
                                        <span key={item} className="rounded-xl border border-white/10 bg-white/[.06] px-3 py-2 text-[10px] font-bold text-slate-300 backdrop-blur-sm">{item}</span>
                                    ))}
                                </div>
                                <span className="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-emerald-400 text-lg font-extrabold text-emerald-950 transition group-hover:translate-x-1">→</span>
                            </div>
                        </div>
                    </Link>

                    <div className="rounded-[30px] border border-slate-200 bg-[linear-gradient(145deg,#fff,#f7fbf9)] p-7 shadow-[0_25px_75px_-45px_rgba(15,23,42,.3)] sm:p-8">
                        <p className="text-[10px] font-extrabold uppercase tracking-[.19em] text-emerald-700">Suggested learning path</p>
                        <h2 className="mt-3 text-2xl font-extrabold tracking-[-.035em] text-[#07132c]">From first idea to a launch-ready site.</h2>
                        <div className="mt-6 space-y-4">
                            {[
                                ['01', 'Plan', 'Clarify the business goal, audience, offer, and page structure.'],
                                ['02', 'Build & refine', 'Use Luna, reusable patterns, and the visual design system.'],
                                ['03', 'Launch & improve', 'Preview carefully, publish, then iterate from real results.'],
                            ].map(([number, title, copy]) => (
                                <div key={number} className="flex gap-4 rounded-2xl border border-slate-200 bg-white p-4">
                                    <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#07132c] text-[10px] font-extrabold text-white">{number}</span>
                                    <div>
                                        <p className="text-sm font-extrabold text-[#07132c]">{title}</p>
                                        <p className="mt-1 text-xs leading-5 text-slate-500">{copy}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                        <Link href="/docs" className="mt-6 inline-flex text-xs font-extrabold text-emerald-700 hover:text-emerald-800">Need product instructions? Open Documentation →</Link>
                    </div>
                </div>
            </section>

            <section className="border-y border-slate-200 bg-slate-50/70">
                <div className="mx-auto max-w-[1240px] px-5 py-14 sm:px-6 lg:px-8 lg:py-16">
                    <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p className="text-[10px] font-extrabold uppercase tracking-[.19em] text-emerald-700">Guide library</p>
                            <h2 className="mt-2 text-3xl font-extrabold tracking-[-.04em] text-[#07132c]">Learn by the task in front of you.</h2>
                            <p className="mt-3 max-w-2xl text-sm leading-6 text-slate-600">Filter by topic or search for the workflow you are trying to improve.</p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {guideCategories.map((item) => (
                                <button
                                    key={item}
                                    type="button"
                                    onClick={() => setCategory(item)}
                                    className={`rounded-full px-4 py-2 text-[11px] font-extrabold transition ${category === item ? 'bg-[#07132c] text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-600 hover:border-emerald-200 hover:text-emerald-800'}`}
                                >
                                    {item}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="mt-8 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                        {filteredGuides.map((guide) => <GuideCard key={guide.slug} guide={guide} />)}
                    </div>

                    {filteredGuides.length === 0 && (
                        <div className="mt-8 rounded-[24px] border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
                            <p className="text-lg font-extrabold text-[#07132c]">No guides match that search.</p>
                            <p className="mt-2 text-sm text-slate-500">Try a broader keyword or choose another category.</p>
                            <button type="button" onClick={() => { setQuery(''); setCategory('All'); }} className="mt-5 rounded-xl bg-emerald-700 px-5 py-3 text-xs font-extrabold text-white">Reset filters</button>
                        </div>
                    )}
                </div>
            </section>

            <section className="mx-auto max-w-[1240px] px-5 py-16 sm:px-6 lg:px-8 lg:py-20">
                <div className="grid gap-5 md:grid-cols-3">
                    {[
                        ['⌘', 'Product instructions', 'Need exact product behavior, Builder workflows, or Marketplace rules?', 'Browse Documentation', '/docs'],
                        ['▦', 'Premium starting points', 'Want to start from a complete design instead of a blank website?', 'Browse Marketplace', '/marketplace'],
                        ['?', 'Troubleshooting', 'Need help with an account, publishing, credits, or a website workflow?', 'Visit Help Center', '/help'],
                    ].map(([icon, title, copy, label, href]) => (
                        <div key={title} className="rounded-[24px] border border-slate-200 bg-white p-6 shadow-[0_20px_60px_-40px_rgba(15,23,42,.28)]">
                            <span className="grid h-11 w-11 place-items-center rounded-xl bg-emerald-50 text-sm font-extrabold text-emerald-700 ring-1 ring-emerald-100">{icon}</span>
                            <h3 className="mt-5 text-lg font-semibold text-[#07132c]">{title}</h3>
                            <p className="mt-2 text-sm leading-6 text-slate-600">{copy}</p>
                            <Link href={href} className="mt-5 inline-flex text-xs font-extrabold text-emerald-700">{label} →</Link>
                        </div>
                    ))}
                </div>
            </section>

            <PublicCta
                eyebrow="Build while you learn"
                title="Turn the next guide into a real website."
                description="Start with a short business brief, let Luna create the first draft, then use the same principles to refine and launch it."
            />
        </PublicSiteLayout>
    );
}
