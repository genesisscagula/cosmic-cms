import { Head, Link } from '@inertiajs/react';
import SeoHead from '@/Components/Seo/SeoHead';
import cosmicLogo from '../../../images/cosmic-cms-logo.png';

const planTone = {
    starter: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    growth: 'border-indigo-200 bg-indigo-50 text-indigo-700',
    pro: 'border-amber-200 bg-amber-50 text-amber-800',
};

const planButton = {
    starter: 'from-emerald-500 to-emerald-600 shadow-emerald-200',
    growth: 'from-violet-600 to-indigo-600 shadow-violet-200',
    pro: 'from-amber-500 to-amber-600 shadow-amber-200',
};

function MarketplaceLogo({ href }) {
    return (
        <Link href={href} className="flex min-w-0 items-center gap-3" aria-label="Cosmic CMS Marketplace home">
            <img src={cosmicLogo} alt="Cosmic CMS" className="h-10 w-auto max-w-[140px] object-contain brightness-0 invert sm:h-11 sm:max-w-[156px]" />
            <span className="hidden h-8 w-px bg-white/15 sm:block" />
            <span className="hidden text-[10px] font-black uppercase tracking-[.26em] text-white/80 sm:block">Marketplace</span>
        </Link>
    );
}

function PageMap({ pages = [], demoPath }) {
    const childrenByParent = pages.reduce((groups, page) => {
        const key = page.parent_slug || '__root';
        groups[key] = [...(groups[key] || []), page];
        return groups;
    }, {});
    const roots = childrenByParent.__root || pages.filter((page) => !page.parent_slug);
    const hrefFor = (page) => page.slug === 'home' ? demoPath : `${demoPath}/${encodeURIComponent(page.slug)}`;

    return (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {roots.map((page) => (
                <div key={page.id || page.slug} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <Link href={hrefFor(page)} className="flex items-center justify-between gap-4 rounded-xl px-2 py-2 transition hover:bg-violet-50">
                        <div className="min-w-0"><p className="truncate text-sm font-black text-slate-950">{page.name}</p><p className="mt-1 text-[10px] font-black uppercase tracking-[.16em] text-slate-400">{page.page_intent || (page.is_home ? 'Home' : 'Page')}</p></div>
                        <span className="text-violet-500">↗</span>
                    </Link>
                    {(childrenByParent[page.slug] || []).length > 0 && (
                        <div className="mt-2 grid gap-1 border-l border-slate-200 pl-3">
                            {(childrenByParent[page.slug] || []).map((child) => (
                                <Link key={child.id || child.slug} href={hrefFor(child)} className="rounded-lg px-2 py-2 text-xs font-bold text-slate-600 transition hover:bg-slate-50 hover:text-violet-700">↳ {child.name}</Link>
                            ))}
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}

function RelatedTemplate({ template }) {
    const fallback = 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=1000&q=80';
    return (
        <article className="overflow-hidden rounded-[22px] border border-slate-200 bg-white shadow-[0_14px_40px_rgba(15,23,42,.06)]">
            <Link href={template.detailPath} className="block">
                <div className="relative aspect-[1.55/1] overflow-hidden bg-slate-900">
                    <img src={template.image || fallback} alt="" className="h-full w-full object-cover transition duration-500 hover:scale-[1.03]" loading="lazy" />
                    <div className="absolute inset-0 bg-gradient-to-t from-slate-950/75 via-slate-950/10 to-transparent" />
                    <span className="absolute left-4 top-4 rounded-full bg-white/90 px-2.5 py-1 text-[9px] font-black uppercase tracking-[.14em] text-slate-900">{template.plan}</span>
                    <p className="absolute bottom-4 left-4 right-4 text-lg font-black text-white">{template.name}</p>
                </div>
            </Link>
            <div className="p-5">
                <p className="text-xs font-bold text-slate-500">{template.industryLabel} · {template.pages} pages</p>
                <div className="mt-4 flex items-center justify-between gap-3"><p className="text-2xl font-black text-slate-950">{Number(template.creditPrice || 0).toLocaleString()}<span className="ml-1 text-xs font-bold text-slate-400">Cosmic Credits</span></p><Link href={template.demoPath} className="rounded-xl border border-slate-200 px-4 py-2 text-xs font-black text-slate-700 hover:bg-slate-50">Preview</Link></div>
            </div>
        </article>
    );
}

export default function MarketplaceTemplateShow({
    canLogin = true,
    canonicalUrl = 'https://marketplace.cosmiccms.com',
    template,
    relatedTemplates = [],
    marketplaceHome = '/marketplace',
    catalogPath = '/marketplace/templates',
    seoPath = '/templates',
}) {
    const seo = template?.seo || {};
    const title = seo.title || `${template.name} — ${template.industry.label} Website Template | Cosmic CMS`;
    const description = seo.description || template.description || template.summary || `Preview ${template.name}, a complete ${template.industry.label} website from Cosmic CMS Marketplace.`;
    const image = template.thumbnail_url || '/images/cosmic-cms-social-preview.png';
    const checkoutPath = template.checkout_path;
    const plan = String(template.plan || 'growth').toLowerCase();
    const features = Array.isArray(template.features) ? template.features : [];

    const schema = {
        '@context': 'https://schema.org',
        '@type': 'Product',
        name: `${template.name} Website`,
        description,
        category: `${template.industry.label} website template`,
        brand: { '@type': 'Brand', name: 'Cosmic CMS' },
        additionalProperty: [{
            '@type': 'PropertyValue',
            name: 'Template installation price',
            value: `${Number(template.credit_price || 0).toLocaleString()} Cosmic Credits`,
        }],
    };

    return (
        <>
            <SeoHead title={title} description={description} path={seoPath} image={image} baseUrl={canonicalUrl} schema={schema} />
            <Head><meta head-key="theme-color" name="theme-color" content="#070b1d" /></Head>
            <div className="min-h-screen bg-[#f7f8fc] text-slate-900" data-marketplace-surface="1">
                <header className="sticky top-0 z-50 border-b border-white/10 bg-[#060b1d]/95 text-white shadow-lg backdrop-blur-xl">
                    <div className="mx-auto flex min-h-[72px] max-w-[1480px] items-center justify-between gap-5 px-5 sm:px-7 lg:px-10">
                        <MarketplaceLogo href={marketplaceHome} />
                        <nav className="hidden items-center gap-6 lg:flex"><Link href={catalogPath} className="text-sm font-black text-white">Templates</Link><Link href={`${marketplaceHome}#how-it-works`} className="text-sm font-bold text-white/65 hover:text-white">How It Works</Link><Link href={`${marketplaceHome}#pricing`} className="text-sm font-bold text-white/65 hover:text-white">Pricing</Link><Link href={`${marketplaceHome}#website-care`} className="text-sm font-bold text-white/65 hover:text-white">Website Care</Link></nav>
                        <div className="flex items-center gap-2">{canLogin && <Link href="/login" className="hidden min-h-10 items-center rounded-xl border border-white/25 px-4 text-[13px] font-black text-white sm:flex">Login</Link>}<Link href={checkoutPath} className="flex min-h-10 items-center rounded-xl bg-violet-600 px-4 text-[13px] font-black text-white hover:bg-violet-500">Get This Website</Link></div>
                    </div>
                </header>

                <main>
                    <section className="relative overflow-hidden border-b border-slate-200 bg-white">
                        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_75%_20%,rgba(124,58,237,.09),transparent_34%),radial-gradient(circle_at_20%_80%,rgba(59,130,246,.06),transparent_28%)]" />
                        <div className="relative mx-auto max-w-[1480px] px-5 py-10 sm:px-7 lg:px-10 lg:py-16">
                            <div className="flex flex-wrap items-center gap-2 text-xs font-bold text-slate-400"><Link href={catalogPath} className="hover:text-violet-700">Templates</Link><span>/</span><Link href={`${catalogPath}/${template.industry.slug}`} className="hover:text-violet-700">{template.industry.label}</Link><span>/</span><span className="text-slate-700">{template.name}</span></div>

                            <div className="mt-8 grid gap-10 xl:grid-cols-[.72fr_1.28fr] xl:items-start">
                                <div className="xl:sticky xl:top-[104px]">
                                    <div className="flex flex-wrap gap-2"><span className={`rounded-full border px-3 py-1.5 text-[10px] font-black uppercase tracking-[.16em] ${planTone[plan] || planTone.growth}`}>{template.plan_label} website</span><span className="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-[10px] font-black uppercase tracking-[.16em] text-slate-600">{template.style}</span></div>
                                    <p className="mt-6 text-xs font-black uppercase tracking-[.2em] text-violet-600">{template.industry.label} · Complete Website</p>
                                    <h1 className="mt-3 text-4xl font-black leading-[.98] tracking-[-.05em] text-slate-950 sm:text-5xl lg:text-6xl">{template.name}</h1>
                                    <p className="mt-5 max-w-xl text-base leading-7 text-slate-600 sm:text-lg">{template.description || template.summary}</p>

                                    <div className="mt-7 grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-2">
                                        {[['▦', `${template.page_count} pages`, 'Complete website'], ['✦', 'AI setup', 'Luna personalizes it'], ['✎', 'No coding', 'Edit visually'], ['◈', 'Website care', 'Hosting & support']].map(([icon, heading, copy]) => <div key={heading} className="rounded-2xl border border-slate-200 bg-slate-50 p-4"><span className="text-violet-600">{icon}</span><p className="mt-2 text-sm font-black text-slate-950">{heading}</p><p className="mt-1 text-[11px] font-semibold text-slate-500">{copy}</p></div>)}
                                    </div>

                                    <div className="mt-8 rounded-[22px] border border-slate-200 bg-white p-5 shadow-[0_20px_55px_rgba(15,23,42,.08)] sm:p-6">
                                        <div className="flex flex-wrap items-end justify-between gap-3"><div><p className="text-xs font-black uppercase tracking-[.16em] text-slate-400">One template installation</p><p className="mt-2 text-4xl font-black tracking-[-.04em] text-slate-950">{Number(template.credit_price || 0).toLocaleString()}<span className="ml-2 text-sm font-bold text-slate-400">Cosmic Credits</span></p></div><span className="rounded-xl bg-slate-100 px-3 py-2 text-xs font-black text-slate-600">{template.plan_label}</span></div>
                                        <p className="mt-3 text-xs leading-5 text-slate-500">This is the one-time Marketplace template installation price. Normal editing does not charge the template price again; Agency access and AI usage continue under their existing rules.</p>
                                        <div className="mt-5 grid gap-2.5 sm:grid-cols-2"><Link href={template.demo_path} className="flex min-h-12 items-center justify-center rounded-xl border border-slate-200 bg-white px-5 text-sm font-black text-slate-800 transition hover:bg-slate-50">View Full Demo</Link><Link href={checkoutPath} className={`flex min-h-12 items-center justify-center rounded-xl bg-gradient-to-r px-5 text-sm font-black text-white shadow-lg transition hover:-translate-y-0.5 ${planButton[plan] || planButton.growth}`}>Get This Website</Link></div>
                                        <p className="mt-4 text-center text-[11px] font-semibold text-slate-400">No coding required · customize after AI setup</p>
                                    </div>
                                </div>

                                <div className="overflow-hidden rounded-[28px] border border-slate-200 bg-slate-200 shadow-[0_35px_80px_rgba(15,23,42,.16)]">
                                    <div className="flex h-12 items-center gap-2 border-b border-slate-200 bg-white px-4"><span className="h-2.5 w-2.5 rounded-full bg-red-400"/><span className="h-2.5 w-2.5 rounded-full bg-amber-400"/><span className="h-2.5 w-2.5 rounded-full bg-emerald-400"/><div className="mx-auto max-w-[52%] flex-1 truncate rounded-lg bg-slate-100 px-4 py-1.5 text-center text-[10px] font-bold text-slate-400">{template.name.toLowerCase().replace(/\s+/g, '-')}.com</div><span className="w-7" /></div>
                                    <iframe src={template.demo_embed_path} title={`${template.name} website preview`} className="block h-[680px] w-full bg-white sm:h-[760px] lg:h-[840px]" loading="eager" />
                                    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-white px-5 py-4"><p className="text-xs font-bold text-slate-500">Interactive multi-page demo</p><Link href={template.demo_path} className="text-xs font-black text-violet-700 hover:text-violet-900">Open full-screen preview →</Link></div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="mx-auto max-w-[1480px] px-5 py-14 sm:px-7 lg:px-10 lg:py-20">
                        <div className="grid gap-10 xl:grid-cols-[1.15fr_.85fr]">
                            <div>
                                <p className="text-xs font-black uppercase tracking-[.18em] text-violet-600">Complete site structure</p>
                                <h2 className="mt-2 text-3xl font-black tracking-[-.04em] text-slate-950 sm:text-4xl">Every page is already designed.</h2>
                                <p className="mt-3 max-w-2xl text-sm leading-6 text-slate-500">Preview any page below. Menu groups and service subpages are preserved when this website is created in your Cosmic account.</p>
                                <div className="mt-7"><PageMap pages={template.pages} demoPath={template.demo_path} /></div>
                            </div>
                            <div className="rounded-[26px] border border-violet-100 bg-gradient-to-br from-violet-50 via-white to-indigo-50 p-6 sm:p-8">
                                <p className="text-xs font-black uppercase tracking-[.18em] text-violet-600">What Luna changes for you</p>
                                <h2 className="mt-3 text-3xl font-black tracking-[-.04em] text-slate-950">Your business, not demo content.</h2>
                                <p className="mt-4 text-sm leading-6 text-slate-600">After you choose this website, Luna uses the existing premium layout as the design foundation and personalizes the site instead of rebuilding it from scratch.</p>
                                <div className="mt-6 grid gap-3">{['Business name, location and contact details', 'Services and industry-specific page copy', 'Calls to action, headlines and SEO basics', 'Logo, brand colors and relevant imagery', 'Inner pages, menus and submenu labels'].map((item) => <div key={item} className="flex gap-3 rounded-xl bg-white/80 p-3 text-sm font-bold text-slate-700 shadow-sm"><span className="text-violet-600">✦</span><span>{item}</span></div>)}</div>
                            </div>
                        </div>
                    </section>

                    <section className="border-y border-slate-200 bg-white">
                        <div className="mx-auto max-w-[1480px] px-5 py-14 sm:px-7 lg:px-10 lg:py-20">
                            <div className="grid gap-9 lg:grid-cols-[.8fr_1.2fr]"><div><p className="text-xs font-black uppercase tracking-[.18em] text-violet-600">Included features</p><h2 className="mt-2 text-3xl font-black tracking-[-.04em] text-slate-950">Ready for a real business launch.</h2><p className="mt-4 text-sm leading-6 text-slate-500">The installed website keeps the complete design system around the template, not just a downloadable file.</p></div><div className="grid gap-3 sm:grid-cols-2">{[...features, ...(template.ai_personalization_enabled ? ['Luna AI content personalization'] : []), ...(template.is_customizable ? ['Pure visual customization'] : []), ...(template.website_care_included ? ['Hosting, security & website care'] : [])].filter((value, index, list) => value && list.indexOf(value) === index).map((feature) => <div key={feature} className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm font-bold text-slate-700"><span className="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-emerald-100 text-xs text-emerald-700">✓</span>{feature}</div>)}</div></div>
                        </div>
                    </section>

                    {relatedTemplates.length > 0 && <section className="mx-auto max-w-[1480px] px-5 py-14 sm:px-7 lg:px-10 lg:py-20"><div className="flex items-end justify-between gap-4"><div><p className="text-xs font-black uppercase tracking-[.18em] text-violet-600">More {template.industry.label} designs</p><h2 className="mt-2 text-3xl font-black tracking-[-.04em] text-slate-950">Explore similar websites.</h2></div><Link href={`${catalogPath}/${template.industry.slug}`} className="hidden text-sm font-black text-violet-700 sm:block">View all →</Link></div><div className="mt-7 grid gap-6 md:grid-cols-2 xl:grid-cols-3">{relatedTemplates.map((item) => <RelatedTemplate key={item.slug} template={item} />)}</div></section>}

                    <section className="bg-[#070b1d] text-white"><div className="mx-auto flex max-w-[1200px] flex-col items-center px-5 py-16 text-center sm:px-7 lg:py-20"><p className="text-xs font-black uppercase tracking-[.2em] text-violet-300">Like this website?</p><h2 className="mt-3 max-w-3xl text-4xl font-black tracking-[-.045em] sm:text-5xl">Make {template.name} yours.</h2><p className="mt-4 max-w-2xl text-sm leading-6 text-slate-300">Choose the template, answer a few business questions, and Luna will prepare the complete website for you.</p><div className="mt-7 flex flex-col gap-3 sm:flex-row"><Link href={template.demo_path} className="flex min-h-12 items-center justify-center rounded-xl border border-white/20 px-6 text-sm font-black text-white hover:bg-white/10">Review Demo</Link><Link href={checkoutPath} className="flex min-h-12 items-center justify-center rounded-xl bg-violet-600 px-7 text-sm font-black text-white shadow-xl shadow-violet-950/30 hover:bg-violet-500">Get This Website — {Number(template.credit_price || 0).toLocaleString()} Credits</Link></div></div></section>
                </main>

                <footer className="border-t border-slate-800 bg-[#050918] text-slate-400"><div className="mx-auto flex max-w-[1480px] flex-col gap-6 px-5 py-9 sm:px-7 md:flex-row md:items-center md:justify-between lg:px-10"><MarketplaceLogo href={marketplaceHome} /><div className="flex flex-wrap gap-5 text-xs font-bold"><Link href={catalogPath} className="hover:text-white">Templates</Link><Link href={`${marketplaceHome}#pricing`} className="hover:text-white">Pricing</Link><Link href="/terms" className="hover:text-white">Terms</Link><Link href="/privacy" className="hover:text-white">Privacy</Link></div><p className="text-xs">© {new Date().getFullYear()} Cosmic CMS</p></div></footer>
            </div>
        </>
    );
}
