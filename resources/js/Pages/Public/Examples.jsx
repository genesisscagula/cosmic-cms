import { Link } from '@inertiajs/react';
import SeoHead from '@/Components/Seo/SeoHead';
import PublicSiteLayout from '@/Components/Public/PublicSiteLayout';
import PublicInnerHero from '@/Components/Public/PublicInnerHero';
import PublicCta from '@/Components/Public/PublicCta';
import MarketplaceTemplateCard from '@/Components/Public/MarketplaceTemplateCard';

const reviewPoints = [
    ['Visual hierarchy', 'Look for a clear headline, readable content rhythm, intentional contrast, and calls to action that are easy to find.'],
    ['Page continuity', 'Headers, type, cards, spacing, forms, and footers should feel like one system as you move from page to page.'],
    ['Conversion flow', 'A polished example should guide visitors toward the next useful action instead of simply showing attractive sections.'],
];

export default function Examples({ catalogReady = false, templates = [], marketplaceHome = '/marketplace', catalogPath = '/marketplace/templates' }) {
    const examples = templates.slice(0, 6);

    return (
        <PublicSiteLayout>
            <SeoHead
                title="Website Examples | Cosmic CMS"
                description="Explore complete website examples and live Marketplace demos built around consistent typography, layout, navigation, and conversion patterns."
                path="/examples"
            />

            <PublicInnerHero
                eyebrow="Website examples"
                title="See how a complete website feels when every page "
                highlight="belongs together."
                description="These examples focus on more than a strong homepage. Explore coherent design systems, multi-page journeys, and live Marketplace demos that show how the same visual language carries across a real site."
                breadcrumbs={[{ label: 'Website Examples' }]}
            >
                <div className="flex flex-wrap gap-3">
                    <Link href="#examples" className="inline-flex min-h-12 items-center rounded-xl bg-emerald-700 px-6 text-sm font-black text-white shadow-lg shadow-emerald-900/10 transition hover:bg-emerald-800">Explore examples ↓</Link>
                    <Link href={catalogPath} className="inline-flex min-h-12 items-center rounded-xl border border-slate-300 bg-white px-6 text-sm font-black text-slate-800 transition hover:bg-slate-50">Browse templates</Link>
                </div>
            </PublicInnerHero>

            <section className="mx-auto max-w-[1240px] px-5 py-16 sm:px-6 lg:px-8 lg:py-20" id="examples">
                <div className="grid gap-6 md:grid-cols-3">
                    {reviewPoints.map(([title, copy], index) => (
                        <div key={title} className="rounded-[24px] border border-slate-200 bg-white p-6 shadow-[0_18px_50px_-40px_rgba(15,23,42,.4)]">
                            <span className="grid h-10 w-10 place-items-center rounded-xl bg-emerald-50 text-xs font-black text-emerald-700">0{index + 1}</span>
                            <h2 className="mt-5 text-lg font-black text-[#07132c]">{title}</h2>
                            <p className="mt-2 text-sm leading-7 text-slate-500">{copy}</p>
                        </div>
                    ))}
                </div>

                <div className="mt-14 flex items-end justify-between gap-6">
                    <div>
                        <p className="text-[11px] font-black uppercase tracking-[.18em] text-emerald-700">Live examples</p>
                        <h2 className="mt-3 text-3xl font-black tracking-[-.04em] text-[#07132c] sm:text-4xl">Open the real demos, not static screenshots.</h2>
                    </div>
                    <Link href={catalogPath} className="hidden text-sm font-black text-emerald-700 sm:inline-flex">See all templates →</Link>
                </div>

                {catalogReady && examples.length > 0 ? (
                    <div className="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                        {examples.map((template, index) => <MarketplaceTemplateCard key={template.id || template.slug} template={template} index={index} example />)}
                    </div>
                ) : (
                    <div className="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                        {[0, 1, 2].map((index) => <MarketplaceTemplateCard key={index} index={index} example template={{ name: ['Ember & Olive', 'Northline Studio', 'Harbor & Stone'][index], industryLabel: ['Restaurant', 'Professional services', 'Real estate'][index] }} />)}
                    </div>
                )}
            </section>

            <section className="overflow-hidden bg-[#07132c] text-white">
                <div className="mx-auto grid max-w-[1240px] gap-10 px-5 py-16 sm:px-6 lg:grid-cols-[.92fr_1.08fr] lg:items-center lg:px-8 lg:py-20">
                    <div>
                        <p className="text-[11px] font-black uppercase tracking-[.18em] text-emerald-300">Example: Ember &amp; Olive</p>
                        <h2 className="mt-3 text-3xl font-black tracking-[-.04em] sm:text-4xl">A new page should look like it shipped with the original site.</h2>
                        <p className="mt-4 text-sm leading-7 text-slate-300">A Marketplace website can retain its installed design kit, so asking Luna for a Catering page later does not have to introduce a random theme or unrelated generic layout.</p>
                        <div className="mt-6 space-y-3">
                            {[
                                'Reuse the same header, footer, navigation, type, and buttons',
                                'Prefer existing card, CTA, hero, and section patterns',
                                'Generate Catering-specific content inside the same visual system',
                            ].map((item) => (
                                <div key={item} className="flex items-start gap-3 text-xs leading-6 text-slate-300"><span className="mt-1 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-emerald-400/15 text-[9px] font-black text-emerald-300">✓</span>{item}</div>
                            ))}
                        </div>
                    </div>

                    <div className="rounded-[30px] border border-white/10 bg-white/[.055] p-5 backdrop-blur-sm sm:p-6">
                        <div className="rounded-[24px] bg-[#fbf6ee] p-5 text-[#2f211a]">
                            <div className="flex items-center justify-between border-b border-[#2f211a]/10 pb-4">
                                <div>
                                    <p className="text-sm font-black">EMBER &amp; OLIVE</p>
                                    <p className="mt-1 text-[8px] font-black uppercase tracking-[.18em] text-[#8e6a52]">Warm editorial restaurant</p>
                                </div>
                                <span className="rounded-full border border-[#2f211a]/15 px-3 py-1.5 text-[8px] font-black">Reserve</span>
                            </div>
                            <div className="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
                                {[
                                    ['Home', 'Original'], ['About', 'Original'], ['Menu', 'Original'], ['Reservations', 'Original'], ['Contact', 'Original'], ['Catering', 'Added later'],
                                ].map(([page, state], index) => (
                                    <div key={page} className={`rounded-2xl border p-4 ${index === 5 ? 'border-[#9f7254]/40 bg-white' : 'border-[#2f211a]/10 bg-white/55'}`}>
                                        <span className="block h-1.5 w-8 rounded-full bg-[#7b5038]" />
                                        <p className="mt-5 text-xs font-black">{page}</p>
                                        <p className="mt-1 text-[8px] font-bold uppercase tracking-[.12em] text-[#9c806d]">{state}</p>
                                    </div>
                                ))}
                            </div>
                            <div className="mt-4 rounded-2xl bg-[#2f211a] p-4 text-white">
                                <p className="text-[9px] font-black uppercase tracking-[.15em] text-[#d6b99f]">Design continuity</p>
                                <p className="mt-2 text-xs font-black">Six pages. One visual language.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section className="mx-auto max-w-[1240px] px-5 py-16 sm:px-6 lg:px-8 lg:py-20">
                <div className="grid gap-5 lg:grid-cols-3">
                    {[
                        ['Service businesses', 'Use clear proof, services, process, FAQs, and conversion-focused calls to action without making the site feel generic.'],
                        ['Hospitality & lifestyle', 'Let imagery, editorial spacing, menus, booking paths, and brand character carry consistently across the whole experience.'],
                        ['Agencies & teams', 'Use reusable layouts, strong portfolio presentation, structured services, and a coherent system that can expand for client needs.'],
                    ].map(([title, copy]) => (
                        <div key={title} className="rounded-[24px] border border-slate-200 bg-slate-50/70 p-6">
                            <p className="text-[10px] font-black uppercase tracking-[.16em] text-slate-400">Website direction</p>
                            <h3 className="mt-3 text-xl font-semibold text-[#07132c]">{title}</h3>
                            <p className="mt-3 text-sm leading-7 text-slate-500">{copy}</p>
                        </div>
                    ))}
                </div>

                <div className="mt-10 rounded-[28px] border border-emerald-100 bg-emerald-50/55 p-6 sm:flex sm:items-center sm:justify-between sm:gap-8 sm:p-8">
                    <div>
                        <p className="text-[10px] font-black uppercase tracking-[.17em] text-emerald-700">Want the complete catalog?</p>
                        <h3 className="mt-2 text-2xl font-semibold text-[#07132c]">Every example can lead directly into the Marketplace workflow.</h3>
                        <p className="mt-2 text-sm leading-6 text-slate-500">Preview the full website, inspect its pages, then choose the template when it fits your business.</p>
                    </div>
                    <Link href={marketplaceHome} className="mt-5 inline-flex min-h-11 shrink-0 items-center rounded-xl bg-emerald-700 px-5 text-xs font-black text-white sm:mt-0">Open Marketplace →</Link>
                </div>
            </section>

            <PublicCta title="See a direction you like? Make it yours." description="Start from a premium Marketplace website or ask Luna to create a fresh direction in Cosmic Studio." primaryLabel="Browse templates" primaryHref={catalogPath} secondaryLabel="Create free demo" secondaryHref="/start" />
        </PublicSiteLayout>
    );
}
