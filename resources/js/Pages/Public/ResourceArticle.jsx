import { Link } from '@inertiajs/react';
import SeoHead from '@/Components/Seo/SeoHead';
import PublicSiteLayout from '@/Components/Public/PublicSiteLayout';
import PublicBreadcrumbs from '@/Components/Public/PublicBreadcrumbs';
import PublicCta from '@/Components/Public/PublicCta';
import { docsSections, findDoc, findGuide, guides } from './resourceContent';

const sectionId = (heading) => String(heading || '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '');

function NotFound({ kind }) {
    const isGuide = kind === 'guide';
    return (
        <PublicSiteLayout>
            <SeoHead title="Resource not found | Cosmic CMS" description="The requested Cosmic CMS resource could not be found." noIndex />
            <section className="mx-auto max-w-[900px] px-5 py-24 text-center sm:px-6 lg:px-8 lg:py-32">
                <span className="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-emerald-50 text-lg font-extrabold text-emerald-700 ring-1 ring-emerald-100">?</span>
                <h1 className="mt-6 text-4xl font-extrabold tracking-[-.04em] text-[#07132c]">That resource is not here.</h1>
                <p className="mx-auto mt-4 max-w-xl text-base leading-7 text-slate-600">The page may have moved or the link may be incomplete. Browse the current {isGuide ? 'guide library' : 'documentation'} instead.</p>
                <Link href={isGuide ? '/guides' : '/docs'} className="mt-7 inline-flex min-h-12 items-center rounded-xl bg-emerald-700 px-6 text-sm font-extrabold text-white">Back to {isGuide ? 'Guides' : 'Documentation'} →</Link>
            </section>
        </PublicSiteLayout>
    );
}

function ArticleBody({ article }) {
    return (
        <article className="min-w-0">
            {article.takeaways?.length > 0 && (
                <div className="mb-9 rounded-[24px] border border-emerald-100 bg-emerald-50/55 p-6">
                    <p className="text-[10px] font-extrabold uppercase tracking-[.18em] text-emerald-700">What you will take away</p>
                    <div className="mt-4 grid gap-3 sm:grid-cols-3">
                        {article.takeaways.map((item) => (
                            <div key={item} className="flex gap-3 rounded-xl border border-emerald-100 bg-white/80 p-3.5">
                                <span className="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-emerald-700 text-[9px] font-extrabold text-white">✓</span>
                                <p className="text-xs font-semibold leading-5 text-emerald-950">{item}</p>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            <div className="space-y-10">
                {article.sections.map((section, index) => (
                    <section key={section.heading} id={sectionId(section.heading)} className="scroll-mt-28">
                        <div className="flex items-start gap-4">
                            <span className="mt-1 grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-[#07132c] text-[9px] font-extrabold text-white">0{index + 1}</span>
                            <div className="min-w-0 flex-1">
                                <h2 className="text-2xl font-extrabold tracking-[-.035em] text-[#07132c] sm:text-[28px]">{section.heading}</h2>
                                <div className="mt-4 space-y-4">
                                    {section.paragraphs?.map((paragraph) => (
                                        <p key={paragraph} className="text-[15px] leading-8 text-slate-600">{paragraph}</p>
                                    ))}
                                </div>
                                {section.bullets?.length > 0 && (
                                    <div className="mt-5 grid gap-2 sm:grid-cols-2">
                                        {section.bullets.map((bullet) => (
                                            <div key={bullet} className="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/75 px-4 py-3">
                                                <span className="h-2 w-2 shrink-0 rounded-full bg-emerald-500" />
                                                <span className="text-xs font-bold text-slate-700">{bullet}</span>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>
                    </section>
                ))}
            </div>
        </article>
    );
}

function Toc({ article }) {
    return (
        <div className="rounded-[22px] border border-slate-200 bg-slate-50/75 p-4">
            <p className="px-2 text-[10px] font-extrabold uppercase tracking-[.18em] text-slate-400">On this page</p>
            <nav className="mt-2" aria-label="Article sections">
                {article.sections.map((section, index) => (
                    <a key={section.heading} href={`#${sectionId(section.heading)}`} className="group flex items-start gap-3 rounded-xl px-2 py-2.5 transition hover:bg-white">
                        <span className="mt-0.5 text-[9px] font-extrabold text-emerald-700">0{index + 1}</span>
                        <span className="text-xs font-bold leading-5 text-slate-600 transition group-hover:text-[#07132c]">{section.heading}</span>
                    </a>
                ))}
            </nav>
        </div>
    );
}

function DocsNavigation({ currentSlug }) {
    return (
        <div className="rounded-[22px] border border-slate-200 bg-white p-4 shadow-[0_18px_55px_-42px_rgba(15,23,42,.25)]">
            <div className="flex items-center justify-between px-2 py-2">
                <Link href="/docs" className="text-xs font-extrabold text-[#07132c] hover:text-emerald-800">Documentation</Link>
                <span className="rounded-full bg-emerald-100 px-2 py-1 text-[9px] font-extrabold text-emerald-700">v1</span>
            </div>
            <div className="mt-3 space-y-4">
                {docsSections.map((section) => (
                    <div key={section.label}>
                        <p className="px-2 text-[9px] font-extrabold uppercase tracking-[.15em] text-slate-400">{section.label}</p>
                        <div className="mt-1.5 space-y-0.5">
                            {section.items.map((item) => (
                                <Link
                                    key={item.slug}
                                    href={`/docs/${item.slug}`}
                                    className={`block rounded-lg px-2.5 py-2 text-[11px] font-bold leading-4 transition ${currentSlug === item.slug ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-100' : 'text-slate-600 hover:bg-slate-50 hover:text-[#07132c]'}`}
                                >
                                    {item.title}
                                </Link>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

function RelatedGuides({ currentSlug }) {
    const related = guides.filter((guide) => guide.slug !== currentSlug).slice(0, 3);
    return (
        <section className="border-t border-slate-200 bg-slate-50/70">
            <div className="mx-auto max-w-[1100px] px-5 py-14 sm:px-6 lg:px-8 lg:py-16">
                <div className="flex items-end justify-between gap-4">
                    <div>
                        <p className="text-[10px] font-extrabold uppercase tracking-[.18em] text-emerald-700">Keep learning</p>
                        <h2 className="mt-2 text-2xl font-extrabold tracking-[-.035em] text-[#07132c]">Continue with another practical guide.</h2>
                    </div>
                    <Link href="/guides" className="hidden text-xs font-extrabold text-emerald-700 sm:inline">All guides →</Link>
                </div>
                <div className="mt-6 grid gap-4 md:grid-cols-3">
                    {related.map((guide) => (
                        <Link key={guide.slug} href={`/guides/${guide.slug}`} className="group rounded-[20px] border border-slate-200 bg-white p-5 transition hover:-translate-y-1 hover:border-emerald-200">
                            <p className="text-[9px] font-extrabold uppercase tracking-[.16em] text-emerald-700">{guide.category}</p>
                            <h3 className="mt-2 text-base font-semibold leading-6 text-[#07132c] group-hover:text-emerald-800">{guide.title}</h3>
                            <p className="mt-3 text-[11px] font-bold text-slate-400">{guide.readTime} · {guide.level}</p>
                        </Link>
                    ))}
                </div>
            </div>
        </section>
    );
}

export default function ResourceArticle({ kind = 'guide', slug }) {
    const isGuide = kind === 'guide';
    const article = isGuide ? findGuide(slug) : findDoc(slug);

    if (!article) return <NotFound kind={kind} />;

    const parentLabel = isGuide ? 'Guides' : 'Documentation';
    const parentHref = isGuide ? '/guides' : '/docs';
    const seoDescription = article.description;

    return (
        <PublicSiteLayout>
            <SeoHead
                title={`${article.title} | Cosmic CMS ${isGuide ? 'Guides' : 'Docs'}`}
                description={seoDescription}
                path={`${parentHref}/${article.slug}`}
                type="article"
            />

            <section className="relative overflow-hidden border-b border-slate-200 bg-[radial-gradient(circle_at_83%_14%,rgba(16,185,129,.13),transparent_27%),linear-gradient(180deg,#fff_0%,#f8fbfa_100%)]">
                <div className="pointer-events-none absolute -right-28 top-8 h-80 w-80 rounded-full border border-emerald-100/80" />
                <div className="mx-auto max-w-[1100px] px-5 py-14 sm:px-6 sm:py-16 lg:px-8 lg:py-20">
                    <PublicBreadcrumbs items={[{ label: parentLabel, href: parentHref }, { label: article.title }]} />
                    <div className="mt-8 max-w-4xl">
                        <div className="flex flex-wrap items-center gap-3">
                            <span className="inline-flex items-center rounded-full border border-emerald-200 bg-white/85 px-3.5 py-2 text-[10px] font-extrabold uppercase tracking-[.18em] text-emerald-700 shadow-sm">{article.icon}&nbsp;&nbsp;{article.category}</span>
                            {isGuide ? (
                                <>
                                    <span className="text-xs font-bold text-slate-400">{article.readTime}</span>
                                    <span className="h-1 w-1 rounded-full bg-slate-300" />
                                    <span className="text-xs font-bold text-slate-400">{article.level}</span>
                                </>
                            ) : (
                                <span className="text-xs font-bold text-slate-400">Updated {article.updated}</span>
                            )}
                        </div>
                        <h1 className="mt-5 max-w-4xl text-[clamp(2.25rem,9vw,3rem)] font-extrabold leading-[1.04] tracking-[-.045em] text-[#07132c] sm:text-5xl lg:text-[58px]">{article.title}</h1>
                        <p className="mt-5 max-w-3xl text-lg leading-8 text-slate-600">{article.description}</p>
                    </div>
                </div>
            </section>

            <section className="mx-auto max-w-[1100px] px-5 py-12 sm:px-6 lg:px-8 lg:py-16">
                <div className={`grid gap-8 ${isGuide ? 'lg:grid-cols-[220px_minmax(0,1fr)]' : 'lg:grid-cols-[230px_minmax(0,1fr)]'}`}>
                    <aside data-public-scroll-region className="max-h-[420px] self-start space-y-4 overflow-y-auto lg:sticky lg:top-24 lg:max-h-[calc(100vh-7rem)]">
                        {isGuide ? <Toc article={article} /> : <DocsNavigation currentSlug={article.slug} />}
                        {!isGuide && <Toc article={article} />}
                        <div className="rounded-[20px] bg-[#07132c] p-5 text-white">
                            <p className="text-[9px] font-extrabold uppercase tracking-[.17em] text-emerald-300">Need another path?</p>
                            <p className="mt-2 text-sm font-extrabold">{isGuide ? 'Looking for product-specific steps?' : 'Prefer practical strategy and examples?'}</p>
                            <Link href={isGuide ? '/docs' : '/guides'} className="mt-4 inline-flex text-[11px] font-extrabold text-emerald-300">{isGuide ? 'Open Documentation' : 'Browse Guides'} →</Link>
                        </div>
                    </aside>

                    <ArticleBody article={article} />
                </div>
            </section>

            {isGuide ? (
                <RelatedGuides currentSlug={article.slug} />
            ) : (
                <section className="border-t border-slate-200 bg-slate-50/70">
                    <div className="mx-auto max-w-[1100px] px-5 py-12 sm:px-6 lg:px-8">
                        <div className="flex flex-col gap-4 rounded-[24px] border border-slate-200 bg-white p-6 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p className="text-[10px] font-extrabold uppercase tracking-[.18em] text-emerald-700">Documentation support</p>
                                <h2 className="mt-2 text-xl font-extrabold text-[#07132c]">Could not find the workflow you need?</h2>
                                <p className="mt-2 text-sm text-slate-500">Browse the Help Center for troubleshooting, account, billing, and launch support.</p>
                            </div>
                            <Link href="/help" className="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl bg-emerald-700 px-5 text-xs font-extrabold text-white">Open Help Center →</Link>
                        </div>
                    </div>
                </section>
            )}

            <PublicCta
                eyebrow={isGuide ? 'Put the guide into practice' : 'From documentation to a live site'}
                title={isGuide ? 'Build the next version instead of only planning it.' : 'Ready to apply this workflow to your website?'}
                description={isGuide ? 'Use Luna to create the starting point, then refine the result with the same principles covered in this guide.' : 'Create a website or return to your workspace and use the documented workflow with Luna and the visual Builder.'}
                secondaryLabel={isGuide ? 'Browse documentation' : 'Browse all docs'}
                secondaryHref="/docs"
            />
        </PublicSiteLayout>
    );
}
