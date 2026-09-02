import { useMemo, useState } from 'react';
import { Link } from '@inertiajs/react';
import SeoHead from '@/Components/Seo/SeoHead';
import PublicSiteLayout from '@/Components/Public/PublicSiteLayout';
import PublicInnerHero from '@/Components/Public/PublicInnerHero';
import PublicCta from '@/Components/Public/PublicCta';

const categories = [
    {
        icon: '01',
        title: 'Getting started',
        description: 'Create your first website, understand the workspace, and get from a business brief to a usable first draft.',
        href: '/docs/create-first-website',
        linkLabel: 'Start here',
    },
    {
        icon: '✦',
        title: 'Luna & Builder',
        description: 'Learn how to ask for page changes, refine sections, adjust styling, and keep edits predictable.',
        href: '/docs/luna-basics',
        linkLabel: 'Learn Luna',
    },
    {
        icon: '▦',
        title: 'Marketplace websites',
        description: 'Understand installed template design kits, reusable page patterns, and what stays protected from generic design changes.',
        href: '/docs/marketplace-websites',
        linkLabel: 'Marketplace help',
    },
    {
        icon: '↗',
        title: 'Preview & publish',
        description: 'Work through preview, staging, responsive checks, publishing, and common launch blockers.',
        href: '/docs/preview-and-publish',
        linkLabel: 'Publishing help',
    },
    {
        icon: '₵',
        title: 'Plans & Cosmic Credits',
        description: 'See how subscriptions, website limits, feature access, and AI credit usage fit together.',
        href: '/docs/plans-and-credits',
        linkLabel: 'Plans & credits',
    },
    {
        icon: '◎',
        title: 'Account & workspace',
        description: 'Find the right next step for login, workspace access, website ownership, and team-level questions.',
        href: '/login',
        linkLabel: 'Open account',
    },
];

const helpArticles = [
    {
        title: 'How do I create my first website?',
        description: 'A short walkthrough from creating a website to generating the first page and opening the Builder.',
        category: 'Getting started',
        href: '/docs/create-first-website',
        tags: ['first website', 'setup', 'start'],
    },
    {
        title: 'What should I ask Luna to change?',
        description: 'Use outcome-focused requests for content, sections, page structure, styling, and full-page actions.',
        category: 'Luna & Builder',
        href: '/docs/luna-page-actions',
        tags: ['luna', 'prompt', 'edit', 'rebuild'],
    },
    {
        title: 'Why does a Marketplace website behave differently?',
        description: 'Marketplace websites keep their installed design system as the source of truth while still allowing explicit Luna customization.',
        category: 'Marketplace',
        href: '/docs/marketplace-websites',
        tags: ['marketplace', 'template', 'design kit', 'sparks'],
    },
    {
        title: 'How do I preview and publish safely?',
        description: 'Check responsive output, content, navigation, and launch state before making a website public.',
        category: 'Publishing',
        href: '/docs/preview-and-publish',
        tags: ['publish', 'preview', 'staging', 'launch'],
    },
    {
        title: 'How are Cosmic Credits used?',
        description: 'Credits are for AI/API work, while your subscription controls plan-level product access and website limits.',
        category: 'Plans & billing',
        href: '/docs/plans-and-credits',
        tags: ['credits', 'billing', 'subscription', 'plan'],
    },
    {
        title: 'How do I keep new pages visually consistent?',
        description: 'Reuse the current website design language, existing patterns, typography, spacing, and shared page structure.',
        category: 'Design',
        href: '/guides/keep-pages-visually-consistent',
        tags: ['design', 'new page', 'consistent', 'styles'],
    },
    {
        title: 'What is Global Styling for?',
        description: 'Use Global Styling to manage shared typography, buttons, spacing, corners, and other website-wide visual defaults.',
        category: 'Luna & Builder',
        href: '/docs/global-styling',
        tags: ['global styling', 'fonts', 'buttons', 'spacing'],
    },
    {
        title: 'Which plan should I choose?',
        description: 'Compare website limits and product access, then choose the plan that matches your business or agency workflow.',
        category: 'Plans & billing',
        href: '/pricing',
        tags: ['pricing', 'starter', 'growth', 'pro', 'agency'],
    },
];

const faqs = [
    {
        question: 'Does asking Luna a normal question use Cosmic Credits?',
        answer: 'Cosmic Credits are intended for actual AI/API work. Ordinary navigation or manual product actions should not be treated like generated AI work. When an action needs generation, the interface can show the relevant credit cost before or during the action.',
    },
    {
        question: 'Can Luna change a Marketplace template?',
        answer: 'Yes, when you explicitly request a change. By default Luna should preserve the installed Marketplace design language and reuse its colors, typography, spacing, components, header, footer, and page patterns instead of switching to unrelated generic Sparks.',
    },
    {
        question: 'What should I check before publishing?',
        answer: 'Review the main pages, navigation, mobile and tablet layouts, calls to action, images, forms, SEO basics, and any domain or staging settings. The Preview & Publish documentation gives you the safest sequence.',
    },
    {
        question: 'Where should I start if the Builder does not look right?',
        answer: 'First identify whether the issue is limited to one section, one page, or the entire website. Then check Global Styling, the current page design, and responsive preview. If it is a Marketplace website, verify that its installed design kit is still being used.',
    },
    {
        question: 'Can I build a new page without starting from a blank canvas?',
        answer: 'Yes. Luna can create a page from the current website context. For Marketplace websites, it should prefer the installed template design kit and existing patterns so the new page looks like it belonged to the original template.',
    },
];

function SearchResult({ article }) {
    return (
        <Link
            href={article.href}
            className="group flex items-start gap-4 rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-emerald-200 hover:bg-emerald-50/40"
        >
            <span className="mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-emerald-50 text-xs font-extrabold text-emerald-700 ring-1 ring-emerald-100">↗</span>
            <span className="min-w-0 flex-1">
                <span className="block text-[10px] font-extrabold uppercase tracking-[.15em] text-emerald-700">{article.category}</span>
                <span className="mt-1 block text-sm font-extrabold text-[#07132c] group-hover:text-emerald-800">{article.title}</span>
                <span className="mt-1.5 block text-xs leading-5 text-slate-500">{article.description}</span>
            </span>
            <span className="mt-1 text-sm font-bold text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-emerald-600">→</span>
        </Link>
    );
}

export default function Help() {
    const [query, setQuery] = useState('');
    const [openFaq, setOpenFaq] = useState(0);

    const results = useMemo(() => {
        const needle = query.trim().toLowerCase();
        if (!needle) return [];

        return helpArticles.filter((article) => {
            const searchable = `${article.title} ${article.description} ${article.category} ${article.tags.join(' ')}`.toLowerCase();
            return searchable.includes(needle);
        });
    }, [query]);

    return (
        <PublicSiteLayout>
            <SeoHead
                title="Help Center | Cosmic CMS"
                description="Get help with Luna, the Cosmic CMS Builder, Marketplace websites, publishing, plans, credits, and common website workflows."
                path="/help"
            />

            <PublicInnerHero
                eyebrow="Cosmic Help Center"
                title="Find the right answer and keep your website "
                highlight="moving."
                description="Search practical help for Luna, the Builder, Marketplace websites, publishing, plans, credits, and the workflows you use most."
                breadcrumbs={[{ label: 'Help Center' }]}
            >
                <div className="max-w-3xl">
                    <div className="rounded-[22px] border border-slate-200 bg-white p-2 shadow-[0_24px_70px_-34px_rgba(15,23,42,.3)]">
                        <label className="flex min-h-14 items-center gap-3 rounded-2xl px-4">
                            <span className="text-xl text-emerald-700">⌕</span>
                            <input
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                type="search"
                                placeholder="Search help — Luna, publishing, credits, Marketplace…"
                                className="min-h-12 flex-1 border-0 bg-transparent px-0 text-sm font-semibold text-slate-800 placeholder:font-normal placeholder:text-slate-400 focus:ring-0"
                                aria-label="Search Cosmic CMS help"
                            />
                            <span className="hidden rounded-lg bg-slate-100 px-2.5 py-1.5 text-[9px] font-extrabold uppercase tracking-[.12em] text-slate-400 sm:inline">Help</span>
                        </label>
                    </div>

                    {query.trim() && (
                        <div className="mt-3 rounded-[22px] border border-slate-200 bg-white p-2 shadow-[0_26px_75px_-30px_rgba(15,23,42,.3)]">
                            {results.length ? (
                                <div className="space-y-2">
                                    {results.slice(0, 6).map((article) => <SearchResult key={article.title} article={article} />)}
                                </div>
                            ) : (
                                <div className="px-5 py-7">
                                    <p className="text-sm font-extrabold text-[#07132c]">No exact help match yet.</p>
                                    <p className="mt-1 text-xs leading-5 text-slate-500">Try a shorter term such as “Luna”, “publish”, “credits”, “template”, or “style”.</p>
                                </div>
                            )}
                        </div>
                    )}

                    <div className="mt-5 flex flex-wrap gap-2 text-[11px] font-bold">
                        <span className="text-slate-400">Popular:</span>
                        {['Luna', 'Publish', 'Marketplace', 'Credits'].map((term) => (
                            <button key={term} type="button" onClick={() => setQuery(term)} className="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-slate-600 transition hover:border-emerald-200 hover:text-emerald-700">
                                {term}
                            </button>
                        ))}
                    </div>
                </div>
            </PublicInnerHero>

            <section className="mx-auto max-w-[1240px] px-5 py-14 sm:px-6 lg:px-8 lg:py-20">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-[10px] font-extrabold uppercase tracking-[.18em] text-emerald-700">Browse help by area</p>
                        <h2 className="mt-2 max-w-2xl text-3xl font-extrabold tracking-[-.04em] text-[#07132c] sm:text-4xl">Start with the part of Cosmic you are working in.</h2>
                    </div>
                    <Link href="/docs" className="text-xs font-extrabold text-emerald-700">Browse all documentation →</Link>
                </div>

                <div className="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {categories.map((category) => (
                        <Link key={category.title} href={category.href} className="group rounded-[24px] border border-slate-200 bg-white p-6 shadow-[0_20px_58px_-42px_rgba(15,23,42,.3)] transition duration-300 hover:-translate-y-1 hover:border-emerald-200 hover:shadow-[0_28px_76px_-42px_rgba(5,150,105,.3)]">
                            <div className="flex items-start justify-between gap-4">
                                <span className="grid h-11 w-11 place-items-center rounded-xl bg-emerald-50 text-xs font-extrabold text-emerald-700 ring-1 ring-emerald-100">{category.icon}</span>
                                <span className="text-sm font-bold text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-emerald-600">→</span>
                            </div>
                            <h3 className="mt-5 text-lg font-semibold tracking-[-.025em] text-[#07132c]">{category.title}</h3>
                            <p className="mt-2 text-sm leading-6 text-slate-500">{category.description}</p>
                            <span className="mt-5 inline-flex text-[11px] font-extrabold text-emerald-700">{category.linkLabel} →</span>
                        </Link>
                    ))}
                </div>
            </section>

            <section className="border-y border-slate-200 bg-slate-50/70">
                <div className="mx-auto grid max-w-[1240px] gap-8 px-5 py-14 sm:px-6 lg:grid-cols-[.82fr_1.18fr] lg:px-8 lg:py-20">
                    <div className="self-start lg:sticky lg:top-28">
                        <p className="text-[10px] font-extrabold uppercase tracking-[.18em] text-emerald-700">Common questions</p>
                        <h2 className="mt-2 text-3xl font-extrabold tracking-[-.04em] text-[#07132c]">Quick answers before you dig deeper.</h2>
                        <p className="mt-4 max-w-md text-sm leading-7 text-slate-600">These cover the questions that most often affect website creation, Marketplace behavior, credits, and publishing.</p>
                        <div className="mt-6 flex flex-wrap gap-3">
                            <Link href="/guides" className="inline-flex min-h-11 items-center rounded-xl bg-[#07132c] px-5 text-xs font-extrabold text-white transition hover:bg-slate-800">Browse guides</Link>
                            <Link href="/updates" className="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-5 text-xs font-extrabold text-slate-700 transition hover:border-emerald-200 hover:text-emerald-700">Product updates</Link>
                        </div>
                    </div>

                    <div className="space-y-3">
                        {faqs.map((faq, index) => {
                            const open = openFaq === index;
                            return (
                                <div key={faq.question} className="overflow-hidden rounded-[20px] border border-slate-200 bg-white shadow-[0_16px_45px_-38px_rgba(15,23,42,.28)]">
                                    <button
                                        type="button"
                                        onClick={() => setOpenFaq(open ? -1 : index)}
                                        className="flex w-full items-center justify-between gap-5 px-5 py-5 text-left sm:px-6"
                                        aria-expanded={open}
                                    >
                                        <span className="text-sm font-extrabold text-[#07132c] sm:text-[15px]">{faq.question}</span>
                                        <span className={`grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-slate-100 text-lg font-light text-slate-500 transition ${open ? 'rotate-45 bg-emerald-50 text-emerald-700' : ''}`}>+</span>
                                    </button>
                                    <div className={`grid transition-all duration-300 ${open ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'}`}>
                                        <div className="overflow-hidden">
                                            <p className="border-t border-slate-100 px-5 py-5 text-sm leading-7 text-slate-600 sm:px-6">{faq.answer}</p>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </section>

            <section className="mx-auto max-w-[1240px] px-5 py-14 sm:px-6 lg:px-8 lg:py-20">
                <div className="relative overflow-hidden rounded-[28px] bg-[radial-gradient(circle_at_88%_15%,rgba(52,211,153,.22),transparent_28%),linear-gradient(120deg,#06142e_0%,#082c39_60%,#07503d_100%)] p-7 text-white shadow-[0_30px_90px_-42px_rgba(2,32,44,.68)] sm:p-9 lg:p-11">
                    <div className="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full border border-emerald-300/20" />
                    <div className="relative grid gap-8 lg:grid-cols-[1fr_.95fr] lg:items-center">
                        <div>
                            <p className="text-[10px] font-extrabold uppercase tracking-[.18em] text-emerald-300">Troubleshoot faster</p>
                            <h2 className="mt-3 text-3xl font-extrabold tracking-[-.04em] sm:text-4xl">Narrow the problem before changing the website.</h2>
                            <p className="mt-4 max-w-2xl text-sm leading-7 text-slate-300">A clear scope makes Builder and Luna fixes safer. Identify where the issue lives, verify the website context, then apply the smallest useful change.</p>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
                            {[
                                ['01', 'Scope it', 'One section, one page, or site-wide?'],
                                ['02', 'Check context', 'Studio or Marketplace design system?'],
                                ['03', 'Apply precisely', 'Fix the smallest layer that owns the issue.'],
                            ].map(([number, title, copy]) => (
                                <div key={number} className="flex items-start gap-3 rounded-2xl border border-white/10 bg-white/[.06] p-4 backdrop-blur-sm">
                                    <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-emerald-400 text-[10px] font-extrabold text-emerald-950">{number}</span>
                                    <div>
                                        <p className="text-xs font-extrabold text-white">{title}</p>
                                        <p className="mt-1 text-[11px] leading-5 text-slate-400">{copy}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </section>

            <PublicCta
                eyebrow="Need a deeper walkthrough?"
                title="Use the docs for product detail and the guides for practical playbooks."
                description="The Help Center gets you to the right answer quickly. Documentation explains product behavior, while guides show how to apply it to a real website."
                primaryLabel="Open documentation"
                primaryHref="/docs"
                secondaryLabel="Browse guides"
                secondaryHref="/guides"
            />
        </PublicSiteLayout>
    );
}
