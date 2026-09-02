import { useMemo, useState } from 'react';
import { Link } from '@inertiajs/react';
import SeoHead from '@/Components/Seo/SeoHead';
import PublicSiteLayout from '@/Components/Public/PublicSiteLayout';
import PublicInnerHero from '@/Components/Public/PublicInnerHero';
import PublicCta from '@/Components/Public/PublicCta';
import MarketplaceTemplateCard from '@/Components/Public/MarketplaceTemplateCard';

const fallbackIndustries = [
    { key: 'restaurant', label: 'Restaurant' },
    { key: 'professional-services', label: 'Professional services' },
    { key: 'real-estate', label: 'Real estate' },
    { key: 'health', label: 'Health' },
];

const installSteps = [
    ['01', 'Choose the design', 'Start from a complete Marketplace website built around a clear industry and visual direction.'],
    ['02', 'Install your copy', 'Cosmic provisions a customer-owned website copy. The master Marketplace template stays untouched.'],
    ['03', 'Personalize with Luna', 'Replace business details, images, calls to action, and content while preserving the template language by default.'],
    ['04', 'Keep the design kit', 'Future pages can reuse the same typography, spacing, buttons, cards, header, footer, and section patterns.'],
];

const designKit = [
    'Color and theme tokens',
    'Typography and heading scale',
    'Spacing, corners, and effects',
    'Buttons and form styling',
    'Header and footer patterns',
    'Hero and section patterns',
    'Cards, grids, and CTA patterns',
    'Navigation and page layouts',
];

export default function Templates({ catalogReady = false, templates = [], industries = [], marketplaceHome = '/marketplace', catalogPath = '/marketplace/templates' }) {
    const [industry, setIndustry] = useState('all');
    const loadedIndustryKeys = new Set(templates.map((template) => template.industry).filter(Boolean));
    const catalogFacets = industries.filter((item) => loadedIndustryKeys.has(item.key));
    const facets = catalogFacets.length ? catalogFacets : fallbackIndustries;
    const visibleTemplates = useMemo(() => {
        if (industry === 'all') return templates.slice(0, 9);
        return templates.filter((template) => template.industry === industry).slice(0, 9);
    }, [industry, templates]);

    return (
        <PublicSiteLayout>
            <SeoHead
                title="Premium Website Templates | Cosmic CMS"
                description="Explore premium Cosmic CMS Marketplace templates, install a customer-owned copy, personalize it with Luna, and reuse the same design system as your website grows."
                path="/templates"
            />

            <PublicInnerHero
                eyebrow="Premium website templates"
                title="Start with a complete design, then make it "
                highlight="your own."
                description="Marketplace templates are complete website systems — not isolated page skins. Install one, personalize the content with Luna, and keep its visual language available for every page you add later."
                breadcrumbs={[{ label: 'Templates' }]}
            >
                <div className="flex flex-wrap gap-3">
                    <Link href={catalogPath} className="inline-flex min-h-12 items-center rounded-xl bg-emerald-700 px-6 text-sm font-black text-white shadow-lg shadow-emerald-900/10 transition hover:bg-emerald-800">Browse Marketplace →</Link>
                    <Link href="/start" className="inline-flex min-h-12 items-center rounded-xl border border-slate-300 bg-white px-6 text-sm font-black text-slate-800 transition hover:bg-slate-50">Build from scratch</Link>
                </div>
            </PublicInnerHero>

            <section className="border-y border-slate-200 bg-slate-50/75">
                <div className="mx-auto grid max-w-[1240px] gap-4 px-5 py-6 sm:grid-cols-2 sm:px-6 lg:grid-cols-4 lg:px-8">
                    {[
                        ['Complete websites', 'Multi-page designs with navigation and reusable patterns.'],
                        ['AI personalization', 'Luna adapts content without randomly replacing the design system.'],
                        ['Reusable design kit', 'New pages inherit the same visual language by default.'],
                        ['Website care ready', 'Built to fit the Marketplace subscription and care workflow.'],
                    ].map(([title, copy]) => (
                        <div key={title} className="rounded-2xl border border-slate-200 bg-white p-4">
                            <p className="text-xs font-black text-[#07132c]">{title}</p>
                            <p className="mt-1.5 text-[11px] leading-5 text-slate-500">{copy}</p>
                        </div>
                    ))}
                </div>
            </section>

            <section className="mx-auto max-w-[1240px] px-5 py-16 sm:px-6 lg:px-8 lg:py-20">
                <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div className="max-w-2xl">
                        <p className="text-[11px] font-black uppercase tracking-[.18em] text-emerald-700">Featured designs</p>
                        <h2 className="mt-3 text-3xl font-black tracking-[-.04em] text-[#07132c] sm:text-4xl">Browse real Marketplace starting points.</h2>
                        <p className="mt-4 text-sm leading-7 text-slate-500">This page reads from the same published Marketplace catalog. There is no duplicate template library to maintain.</p>
                    </div>
                    <Link href={catalogPath} className="text-sm font-black text-emerald-700 hover:text-emerald-800">View full catalog →</Link>
                </div>

                <div className="mt-7 flex flex-wrap gap-2">
                    <button type="button" onClick={() => setIndustry('all')} className={`rounded-full px-4 py-2 text-xs font-black transition ${industry === 'all' ? 'bg-[#07132c] text-white' : 'border border-slate-200 bg-white text-slate-600 hover:border-emerald-200'}`}>All templates</button>
                    {facets.slice(0, 7).map((item) => (
                        <button key={item.key} type="button" onClick={() => setIndustry(item.key)} className={`rounded-full px-4 py-2 text-xs font-black transition ${industry === item.key ? 'bg-[#07132c] text-white' : 'border border-slate-200 bg-white text-slate-600 hover:border-emerald-200'}`}>
                            {item.label}{item.count ? ` · ${item.count}` : ''}
                        </button>
                    ))}
                </div>

                {catalogReady && visibleTemplates.length > 0 ? (
                    <div className="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                        {visibleTemplates.map((template, index) => <MarketplaceTemplateCard key={template.id || template.slug} template={template} index={index} />)}
                    </div>
                ) : (
                    <div className="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                        {[0, 1, 2].map((index) => <MarketplaceTemplateCard key={index} index={index} template={{ name: ['Ember & Olive', 'Northline Studio', 'Harbor & Stone'][index], industryLabel: ['Restaurant', 'Professional services', 'Real estate'][index] }} />)}
                    </div>
                )}
            </section>

            <section className="relative overflow-hidden bg-[#07132c] text-white">
                <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_15%_15%,rgba(16,185,129,.18),transparent_28%),radial-gradient(circle_at_85%_65%,rgba(99,102,241,.14),transparent_30%)]" />
                <div className="relative mx-auto max-w-[1240px] px-5 py-16 sm:px-6 lg:px-8 lg:py-20">
                    <div className="max-w-2xl">
                        <p className="text-[11px] font-black uppercase tracking-[.18em] text-emerald-300">How installation works</p>
                        <h2 className="mt-3 text-3xl font-black tracking-[-.04em] sm:text-4xl">The template becomes your website — not a locked demo.</h2>
                        <p className="mt-4 text-sm leading-7 text-slate-300">The installed customer site gets its own reusable design information, while the Marketplace master remains unchanged.</p>
                    </div>
                    <div className="mt-10 grid gap-4 lg:grid-cols-4">
                        {installSteps.map(([number, title, copy]) => (
                            <div key={number} className="rounded-[24px] border border-white/10 bg-white/[.055] p-5 backdrop-blur-sm">
                                <span className="text-[10px] font-black tracking-[.16em] text-emerald-300">{number}</span>
                                <h3 className="mt-4 text-lg font-semibold">{title}</h3>
                                <p className="mt-3 text-xs leading-6 text-slate-400">{copy}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <section className="mx-auto grid max-w-[1240px] gap-10 px-5 py-16 sm:px-6 lg:grid-cols-[.9fr_1.1fr] lg:items-center lg:px-8 lg:py-20">
                <div>
                    <p className="text-[11px] font-black uppercase tracking-[.18em] text-emerald-700">Installed design kit</p>
                    <h2 className="mt-3 text-3xl font-black tracking-[-.04em] text-[#07132c] sm:text-4xl">Add a new page months later and it still belongs.</h2>
                    <p className="mt-4 text-sm leading-7 text-slate-500">When a Marketplace website needs a new Catering, Team, Services, or other page, Luna can prefer the installed design kit and existing page patterns instead of reaching for unrelated generic Sparks.</p>
                    <div className="mt-6 flex flex-wrap gap-2">
                        {designKit.map((item) => <span key={item} className="rounded-xl border border-emerald-100 bg-emerald-50/60 px-3 py-2 text-[10px] font-black text-emerald-900">{item}</span>)}
                    </div>
                </div>

                <div className="rounded-[30px] border border-slate-200 bg-slate-50 p-4 shadow-[0_30px_80px_-50px_rgba(15,23,42,.5)] sm:p-6">
                    <div className="rounded-[24px] bg-white p-5 ring-1 ring-slate-200">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-4">
                            <div>
                                <p className="text-sm font-black text-[#07132c]">Ember &amp; Olive</p>
                                <p className="mt-1 text-[9px] font-black uppercase tracking-[.15em] text-slate-400">Installed Marketplace design kit</p>
                            </div>
                            <span className="rounded-full bg-emerald-50 px-3 py-1.5 text-[9px] font-black text-emerald-700">Reusable</span>
                        </div>
                        <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            {['Home', 'About', 'Menu', 'Reservations', 'Contact', '+ Catering'].map((page, index) => (
                                <div key={page} className={`rounded-2xl border p-4 ${index === 5 ? 'border-emerald-200 bg-emerald-50/70' : 'border-slate-200 bg-slate-50'}`}>
                                    <span className={`block h-2 w-9 rounded-full ${index === 5 ? 'bg-emerald-500' : 'bg-[#6d412b]'}`} />
                                    <p className="mt-4 text-xs font-black text-[#07132c]">{page}</p>
                                    <p className="mt-1 text-[9px] leading-4 text-slate-400">Same type, spacing, buttons, cards, and navigation language.</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </section>

            <section className="mx-auto max-w-[1240px] px-5 pb-16 sm:px-6 lg:px-8 lg:pb-20">
                <div className="grid overflow-hidden rounded-[30px] border border-slate-200 bg-white lg:grid-cols-2">
                    <div className="p-7 sm:p-9">
                        <p className="text-[10px] font-black uppercase tracking-[.17em] text-violet-600">Cosmic Studio</p>
                        <h3 className="mt-3 text-2xl font-semibold text-[#07132c]">Start flexible.</h3>
                        <p className="mt-3 text-sm leading-7 text-slate-500">Best when you want Luna and the Builder to freely explore the broader Cosmic design system from scratch.</p>
                        <Link href="/start" className="mt-6 inline-flex text-sm font-black text-violet-700">Create with Studio →</Link>
                    </div>
                    <div className="border-t border-slate-200 bg-emerald-50/50 p-7 sm:p-9 lg:border-l lg:border-t-0">
                        <p className="text-[10px] font-black uppercase tracking-[.17em] text-emerald-700">Marketplace</p>
                        <h3 className="mt-3 text-2xl font-semibold text-[#07132c]">Start designed.</h3>
                        <p className="mt-3 text-sm leading-7 text-slate-500">Best when the purchased template should remain the primary visual system while Luna personalizes and expands it coherently.</p>
                        <Link href={marketplaceHome} className="mt-6 inline-flex text-sm font-black text-emerald-800">Explore Marketplace →</Link>
                    </div>
                </div>
            </section>

            <PublicCta title="Find the right starting point for your next website." description="Browse complete Marketplace designs or start fresh in Cosmic Studio — both stay editable with Luna." primaryLabel="Browse Marketplace" primaryHref={catalogPath} secondaryLabel="Create free demo" secondaryHref="/start" />
        </PublicSiteLayout>
    );
}
