import { useMemo, useState } from 'react';
import { Link } from '@inertiajs/react';
import SeoHead from '@/Components/Seo/SeoHead';
import PublicSiteLayout from '@/Components/Public/PublicSiteLayout';
import PublicInnerHero from '@/Components/Public/PublicInnerHero';
import PublicCta from '@/Components/Public/PublicCta';

const filters = ['All', 'Luna', 'Builder', 'Marketplace', 'Platform'];

const releases = [
    {
        date: 'Sep 2026',
        label: 'Marketplace',
        status: 'New',
        title: 'Marketplace websites now keep a reusable installed design kit',
        summary: 'Purchased templates stay visually coherent when customers create new pages later instead of falling back to unrelated generic Sparks.',
        details: [
            'Marketplace origin metadata stays attached to the website.',
            'New pages can reuse template colors, typography, spacing, buttons, cards, headers, footers, and page patterns.',
            'Normal generic theme and Sparks controls stay out of the way for Marketplace websites.',
        ],
        links: [
            ['Marketplace documentation', '/docs/marketplace-websites'],
            ['Browse templates', '/templates'],
        ],
    },
    {
        date: 'Sep 2026',
        label: 'Platform',
        status: 'New',
        title: 'A complete public learning layer for Cosmic CMS',
        summary: 'Guides, documentation, templates, examples, Help Center, and Product Updates now share one premium public design system.',
        details: [
            'Reusable public header, Resources navigation, inner-page hero, CTA, and footer patterns.',
            'Searchable Guides and Documentation experiences with dedicated article pages.',
            'Templates and Website Examples reuse the live Marketplace catalog rather than creating a second catalog.',
        ],
        links: [
            ['Browse guides', '/guides'],
            ['Open documentation', '/docs'],
        ],
    },
    {
        date: 'Aug 2026',
        label: 'Luna',
        status: 'Improved',
        title: 'Luna page actions are more intentional and context-aware',
        summary: 'Website changes are moving toward clear outcome-based requests instead of exposing every underlying Builder control directly.',
        details: [
            'Full-page rebuild, theme changes, and page-style changes are separated by intent.',
            'Marketplace websites prefer their installed design language when Luna creates or rebuilds pages.',
            'AI/media requests can distinguish stock imagery from generated imagery when the request requires it.',
        ],
        links: [
            ['Luna page actions', '/docs/luna-page-actions'],
            ['Prompt writing guide', '/guides/write-better-luna-prompts'],
        ],
    },
    {
        date: 'Aug 2026',
        label: 'Builder',
        status: 'Improved',
        title: 'Builder editing is cleaner, more reusable, and easier to reason about',
        summary: 'Shared website styling and section-level editing continue to move toward safer editing without losing the premium design defaults.',
        details: [
            'Global Styling centralizes typography, buttons, spacing, corners, and visual defaults.',
            'New-page workflows can inherit an existing website design instead of starting from an empty visual system.',
            'Section editing and media behaviors are being separated so hover and popup actions do not compete for the same interaction.',
        ],
        links: [
            ['Builder overview', '/docs/builder-overview'],
            ['Global Styling', '/docs/global-styling'],
        ],
    },
    {
        date: 'Aug 2026',
        label: 'Platform',
        status: 'Improved',
        title: 'Preview, publishing, and background work are becoming more resilient',
        summary: 'Long-running website work is being structured so creation, preview, and launch flows are less dependent on one browser tab staying open.',
        details: [
            'Queue-backed generation is the preferred path for longer website creation work.',
            'Preview and staging workflows are treated as first-class launch steps.',
            'Publishing checks focus on reliable output, responsive review, and clearer progress feedback.',
        ],
        links: [
            ['Preview & publish docs', '/docs/preview-and-publish'],
            ['Launch checklist', '/guides/launch-checklist'],
        ],
    },
    {
        date: 'Aug 2026',
        label: 'Platform',
        status: 'Clarified',
        title: 'Plans and Cosmic Credits have clearer jobs',
        summary: 'Subscriptions control plan access and website limits, while Cosmic Credits are reserved for work that actually calls AI or other paid APIs.',
        details: [
            'Manual website actions should not consume credits simply because they happen inside Cosmic.',
            'Plan pages now explain feature differences more directly for business and agency customers.',
            'Credit language is separated from subscription entitlement language to reduce checkout confusion.',
        ],
        links: [
            ['Plans & credits docs', '/docs/plans-and-credits'],
            ['View pricing', '/pricing'],
        ],
    },
];

const statusStyles = {
    New: 'bg-emerald-100 text-emerald-800 ring-emerald-200',
    Improved: 'bg-sky-100 text-sky-800 ring-sky-200',
    Clarified: 'bg-violet-100 text-violet-800 ring-violet-200',
};

function ReleaseCard({ release }) {
    return (
        <article className="relative rounded-[26px] border border-slate-200 bg-white p-6 shadow-[0_24px_70px_-46px_rgba(15,23,42,.34)] sm:p-7">
            <div className="flex flex-wrap items-center gap-2">
                <span className="rounded-full bg-[#07132c] px-3 py-1.5 text-[9px] font-extrabold uppercase tracking-[.14em] text-white">{release.date}</span>
                <span className="rounded-full bg-slate-100 px-3 py-1.5 text-[9px] font-extrabold uppercase tracking-[.14em] text-slate-500">{release.label}</span>
                <span className={`rounded-full px-3 py-1.5 text-[9px] font-extrabold uppercase tracking-[.14em] ring-1 ${statusStyles[release.status] ?? 'bg-slate-100 text-slate-600 ring-slate-200'}`}>{release.status}</span>
            </div>

            <h2 className="mt-5 max-w-3xl text-2xl font-extrabold tracking-[-.035em] text-[#07132c] sm:text-[28px]">{release.title}</h2>
            <p className="mt-3 max-w-3xl text-sm leading-7 text-slate-600">{release.summary}</p>

            <div className="mt-6 grid gap-3 lg:grid-cols-3">
                {release.details.map((detail, index) => (
                    <div key={detail} className="rounded-2xl border border-slate-100 bg-slate-50/80 p-4">
                        <div className="flex items-start gap-3">
                            <span className="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-emerald-100 text-[9px] font-extrabold text-emerald-800">0{index + 1}</span>
                            <p className="text-xs leading-5 text-slate-600">{detail}</p>
                        </div>
                    </div>
                ))}
            </div>

            <div className="mt-6 flex flex-wrap gap-3 border-t border-slate-100 pt-5">
                {release.links.map(([label, href], index) => (
                    <Link
                        key={href}
                        href={href}
                        className={index === 0
                            ? 'inline-flex min-h-10 items-center rounded-xl bg-emerald-700 px-4 text-[11px] font-extrabold text-white transition hover:bg-emerald-800'
                            : 'inline-flex min-h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-[11px] font-extrabold text-slate-700 transition hover:border-emerald-200 hover:text-emerald-700'}
                    >
                        {label} →
                    </Link>
                ))}
            </div>
        </article>
    );
}

export default function Updates() {
    const [filter, setFilter] = useState('All');

    const visibleReleases = useMemo(() => {
        if (filter === 'All') return releases;
        return releases.filter((release) => release.label === filter);
    }, [filter]);

    const latest = releases[0];

    return (
        <PublicSiteLayout>
            <SeoHead
                title="Product Updates | Cosmic CMS"
                description="Follow meaningful Cosmic CMS releases across Luna, the Builder, Marketplace websites, publishing, plans, credits, and the public product experience."
                path="/updates"
            />

            <PublicInnerHero
                eyebrow="Product Updates"
                title="See what is changing across "
                highlight="Cosmic CMS."
                description="Release notes focused on meaningful product behavior — what changed, why it matters, and where to learn the workflow behind it."
                breadcrumbs={[{ label: 'Product Updates' }]}
            >
                <div className="grid max-w-4xl gap-3 sm:grid-cols-3">
                    {[
                        ['Latest', latest.date, 'Most recent product notes'],
                        ['Focus', 'Luna + Builder', 'Creation and editing workflows'],
                        ['Also evolving', 'Marketplace', 'Reusable installed design systems'],
                    ].map(([label, value, copy]) => (
                        <div key={label} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-[0_16px_45px_-36px_rgba(15,23,42,.3)]">
                            <p className="text-[9px] font-extrabold uppercase tracking-[.16em] text-emerald-700">{label}</p>
                            <p className="mt-1.5 text-sm font-extrabold text-[#07132c]">{value}</p>
                            <p className="mt-1 text-[11px] leading-5 text-slate-500">{copy}</p>
                        </div>
                    ))}
                </div>
            </PublicInnerHero>

            <section className="mx-auto max-w-[1240px] px-5 py-14 sm:px-6 lg:px-8 lg:py-20">
                <div className="relative overflow-hidden rounded-[30px] bg-[radial-gradient(circle_at_90%_15%,rgba(52,211,153,.25),transparent_28%),linear-gradient(120deg,#06142e_0%,#082c39_58%,#07503d_100%)] p-7 text-white shadow-[0_30px_90px_-42px_rgba(2,32,44,.68)] sm:p-9 lg:p-11">
                    <div className="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full border border-emerald-300/20" />
                    <div className="relative grid gap-8 lg:grid-cols-[1.15fr_.85fr] lg:items-end">
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="rounded-full bg-emerald-400 px-3 py-1.5 text-[9px] font-extrabold uppercase tracking-[.15em] text-emerald-950">Latest</span>
                                <span className="text-[10px] font-extrabold uppercase tracking-[.16em] text-emerald-300">{latest.label} · {latest.date}</span>
                            </div>
                            <h2 className="mt-4 max-w-3xl text-3xl font-extrabold tracking-[-.04em] sm:text-4xl">{latest.title}</h2>
                            <p className="mt-4 max-w-2xl text-sm leading-7 text-slate-300">{latest.summary}</p>
                        </div>
                        <div className="grid gap-2.5">
                            {latest.details.map((detail, index) => (
                                <div key={detail} className="flex items-start gap-3 rounded-2xl border border-white/10 bg-white/[.06] p-4 backdrop-blur-sm">
                                    <span className="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-emerald-400 text-[9px] font-extrabold text-emerald-950">0{index + 1}</span>
                                    <p className="text-xs leading-5 text-slate-300">{detail}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="mt-12 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-[10px] font-extrabold uppercase tracking-[.18em] text-emerald-700">Release notes</p>
                        <h2 className="mt-2 text-3xl font-extrabold tracking-[-.04em] text-[#07132c]">Browse changes by product area.</h2>
                    </div>
                    <div className="flex flex-wrap gap-2" aria-label="Filter product updates">
                        {filters.map((item) => (
                            <button
                                key={item}
                                type="button"
                                onClick={() => setFilter(item)}
                                className={`rounded-full px-4 py-2 text-[10px] font-extrabold transition ${filter === item ? 'bg-[#07132c] text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-500 hover:border-emerald-200 hover:text-emerald-700'}`}
                            >
                                {item}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="mt-7 space-y-5">
                    {visibleReleases.map((release) => <ReleaseCard key={release.title} release={release} />)}
                </div>
            </section>

            <section className="border-y border-slate-200 bg-slate-50/70">
                <div className="mx-auto max-w-[1240px] px-5 py-14 sm:px-6 lg:px-8 lg:py-20">
                    <div className="grid gap-7 lg:grid-cols-[.8fr_1.2fr] lg:items-start">
                        <div>
                            <p className="text-[10px] font-extrabold uppercase tracking-[.18em] text-emerald-700">How Cosmic evolves</p>
                            <h2 className="mt-2 text-3xl font-extrabold tracking-[-.04em] text-[#07132c]">Ship improvements that reduce friction, not just add buttons.</h2>
                            <p className="mt-4 max-w-lg text-sm leading-7 text-slate-600">The product direction is centered on faster website creation, safer editing, consistent design systems, clearer billing, and dependable launch workflows.</p>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            {[
                                ['✦', 'More capable Luna', 'Better intent recognition, safer page actions, and stronger awareness of the website design context.'],
                                ['▦', 'Cleaner Builder UX', 'Fewer competing controls, reusable visual systems, and clearer editing boundaries.'],
                                ['◎', 'Consistent Marketplace sites', 'Purchased templates remain coherent as customers add new pages and content over time.'],
                                ['↗', 'Reliable delivery', 'Preview, staging, queues, publishing, and progress feedback continue to get more resilient.'],
                            ].map(([icon, title, copy]) => (
                                <div key={title} className="rounded-[22px] border border-slate-200 bg-white p-5 shadow-[0_16px_50px_-40px_rgba(15,23,42,.28)]">
                                    <span className="grid h-10 w-10 place-items-center rounded-xl bg-emerald-50 text-xs font-extrabold text-emerald-700 ring-1 ring-emerald-100">{icon}</span>
                                    <h3 className="mt-4 text-sm font-semibold text-[#07132c]">{title}</h3>
                                    <p className="mt-2 text-xs leading-5 text-slate-500">{copy}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </section>

            <section className="mx-auto max-w-[1240px] px-5 py-14 sm:px-6 lg:px-8 lg:py-20">
                <div className="grid gap-5 md:grid-cols-3">
                    {[
                        ['Guides', 'Turn product capabilities into practical website workflows.', '/guides', 'Browse guides'],
                        ['Documentation', 'See the detailed product behavior behind Luna, Builder, Marketplace, publishing, and credits.', '/docs', 'Open docs'],
                        ['Help Center', 'Find fast answers when you know the problem but not the right product page.', '/help', 'Get help'],
                    ].map(([title, copy, href, label]) => (
                        <Link key={title} href={href} className="group rounded-[24px] border border-slate-200 bg-white p-6 transition hover:-translate-y-1 hover:border-emerald-200 hover:shadow-[0_24px_65px_-42px_rgba(5,150,105,.3)]">
                            <p className="text-[10px] font-extrabold uppercase tracking-[.17em] text-emerald-700">Keep learning</p>
                            <h3 className="mt-3 text-lg font-semibold text-[#07132c]">{title}</h3>
                            <p className="mt-2 text-sm leading-6 text-slate-500">{copy}</p>
                            <span className="mt-5 inline-flex text-[11px] font-extrabold text-emerald-700">{label} →</span>
                        </Link>
                    ))}
                </div>
            </section>

            <PublicCta
                eyebrow="Build with the current workflow"
                title="Ready to turn the latest Cosmic improvements into a real website?"
                description="Create a starting point with Luna, refine it visually, and use the guides and documentation whenever you want more detail."
                primaryLabel="Create free demo"
                primaryHref="/start"
                secondaryLabel="Browse guides"
                secondaryHref="/guides"
            />
        </PublicSiteLayout>
    );
}
