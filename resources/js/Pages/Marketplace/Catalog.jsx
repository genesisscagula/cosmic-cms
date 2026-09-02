import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import SeoHead from '@/Components/Seo/SeoHead';
import cosmicLogo from '../../../images/cosmic-cms-logo.png';

const FAVORITES_KEY = 'cosmic_marketplace_template_favorites_v1';

const planTone = {
    Starter: 'bg-emerald-500 text-white',
    Growth: 'bg-indigo-500 text-white',
    Pro: 'bg-amber-500 text-amber-950',
};

const previewGradient = {
    Starter: 'from-slate-950/90 via-slate-900/58 to-emerald-950/20',
    Growth: 'from-slate-950/90 via-indigo-950/60 to-indigo-900/15',
    Pro: 'from-slate-950/94 via-slate-950/64 to-amber-900/20',
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

function WebsitePreview({ template }) {
    const image = template.image || 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=1200&q=82';
    return (
        <div className="relative aspect-[1.42/1] overflow-hidden bg-slate-900">
            <img src={image} alt="" className="absolute inset-0 h-full w-full object-cover" loading="lazy" />
            <div className={`absolute inset-0 bg-gradient-to-r ${previewGradient[template.plan] || previewGradient.Growth}`} />
            <div className="absolute inset-x-0 top-0 flex h-9 items-center justify-between border-b border-white/10 bg-slate-950/20 px-4 backdrop-blur-sm">
                <div className="flex items-center gap-1.5"><span className="h-2 w-2 rounded-full bg-white/75" /><span className="h-2 w-8 rounded-full bg-white/20" /></div>
                <div className="flex gap-2 text-[5px] font-bold uppercase tracking-[.14em] text-white/60"><span>About</span><span>Services</span><span>Contact</span></div>
            </div>
            <div className="absolute inset-x-0 bottom-0 p-5">
                <p className="text-[7px] font-black uppercase tracking-[.24em] text-white/70">{template.industryLabel}</p>
                <h3 className="mt-2 max-w-[300px] text-2xl font-black leading-[.98] tracking-[-.04em] text-white">{template.title}</h3>
                <div className="mt-4 flex gap-2"><span className="rounded-md bg-white px-3 py-1.5 text-[7px] font-black text-slate-950">Get started</span><span className="rounded-md border border-white/35 px-3 py-1.5 text-[7px] font-bold text-white">Learn more</span></div>
            </div>
        </div>
    );
}

function TemplateCard({ template, favorite, onFavorite }) {
    const checkoutHref = template.checkoutPath || `/marketplace/checkout/${encodeURIComponent(template.slug)}`;
    return (
        <article className="group overflow-hidden rounded-[22px] border border-slate-200 bg-white shadow-[0_16px_45px_rgba(15,23,42,.06)] transition duration-300 hover:-translate-y-1 hover:shadow-[0_24px_60px_rgba(15,23,42,.12)]">
            <Link href={template.detailPath} className="relative m-3 block overflow-hidden rounded-[16px]">
                <WebsitePreview template={template} />
                <span className={`absolute left-3 top-3 rounded-full px-2.5 py-1 text-[9px] font-black uppercase tracking-[.14em] shadow-sm ${planTone[template.plan] || planTone.Growth}`}>{template.plan}</span>
            </Link>
            <div className="px-5 pb-5 pt-2">
                <div className="flex items-start justify-between gap-4">
                    <div className="min-w-0"><Link href={template.detailPath} className="block truncate text-lg font-black tracking-[-.025em] text-slate-950 hover:text-violet-700">{template.name}</Link><p className="mt-0.5 text-sm font-medium text-slate-500">{template.industryLabel} · {template.style}</p></div>
                    <button type="button" onClick={() => onFavorite(template.slug)} className={`grid h-10 w-10 shrink-0 place-items-center rounded-xl border text-lg transition ${favorite ? 'border-violet-300 bg-violet-50 text-violet-700' : 'border-slate-200 text-slate-500 hover:border-violet-200 hover:bg-violet-50 hover:text-violet-600'}`} aria-label={`${favorite ? 'Remove' : 'Add'} ${template.name} ${favorite ? 'from' : 'to'} favorites`}>{favorite ? '♥' : '♡'}</button>
                </div>
                <p className="mt-3 line-clamp-2 min-h-[40px] text-[13px] leading-5 text-slate-500">{template.copy}</p>
                <ul className="mt-4 space-y-2 text-[13px] font-semibold text-slate-600">
                    <li className="flex items-center gap-2"><span className="text-emerald-600">✓</span>{template.pages} Pages</li>
                    {template.features.slice(0, 2).map((feature) => <li key={feature} className="flex items-center gap-2"><span className="text-emerald-600">✓</span>{feature}</li>)}
                </ul>
                <div className="mt-5 flex items-end gap-1"><span className="text-3xl font-black tracking-[-.04em] text-slate-950">${template.price}</span><span className="pb-1 text-sm font-semibold text-slate-500">/month</span></div>
                <div className="mt-5 grid grid-cols-[.82fr_1.18fr] gap-2.5">
                    <Link href={template.demoPath} className="flex min-h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-black text-slate-800 transition hover:bg-slate-50">Preview</Link>
                    <Link href={checkoutHref} className="flex min-h-11 items-center justify-center rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 px-4 text-center text-sm font-black text-white shadow-lg shadow-violet-200 transition hover:from-violet-700 hover:to-indigo-700">Get This Website</Link>
                </div>
            </div>
        </article>
    );
}


export default function MarketplaceCatalog({
    canLogin = true,
    marketplaceHost = false,
    canonicalUrl = 'https://marketplace.cosmiccms.com',
    catalogReady = true,
    templates = [],
    pagination = null,
    industries = [],
    styles = [],
    filters = {},
    favoritesOnly = false,
    catalogPath = '/marketplace/templates',
    marketplaceHome = '/marketplace',
}) {
    const [search, setSearch] = useState(filters.q || '');
    const [favorites, setFavorites] = useState([]);
    const [favoritesOnlyView, setFavoritesOnly] = useState(favoritesOnly);

    useEffect(() => {
        try {
            const stored = JSON.parse(localStorage.getItem(FAVORITES_KEY) || '[]');
            setFavorites(Array.isArray(stored) ? stored : []);
        } catch { setFavorites([]); }
    }, []);

    const displayTemplates = useMemo(() => favoritesOnlyView ? templates.filter((template) => favorites.includes(template.slug)) : templates, [templates, favoritesOnlyView, favorites]);

    const updateFavorite = (slug) => {
        setFavorites((current) => {
            const next = current.includes(slug) ? current.filter((item) => item !== slug) : [...current, slug];
            localStorage.setItem(FAVORITES_KEY, JSON.stringify(next));
            return next;
        });
    };

    const applyFilters = (updates = {}) => {
        setFavoritesOnly(false);
        const next = { q: search, industry: filters.industry || '', plan: filters.plan || '', style: filters.style || '', sort: filters.sort || 'popular', ...updates };
        Object.keys(next).forEach((key) => { if (!next[key] || (key === 'sort' && next[key] === 'popular')) delete next[key]; });
        router.get(catalogPath, next, { preserveState: true, preserveScroll: true, replace: true });
    };

    const titleIndustry = industries.find((item) => item.key === filters.industry)?.label;
    const pageTitle = titleIndustry ? `${titleIndustry} Website Templates | Cosmic CMS Marketplace` : 'Premium Website Templates | Cosmic CMS Marketplace';
    const pageDescription = titleIndustry ? `Browse premium ${titleIndustry.toLowerCase()} website templates with AI personalization, no-code customization, hosting, and website care.` : 'Browse premium business website templates by industry, plan, and style. Personalize with AI and customize everything without coding.';

    return (
        <>
            <SeoHead title={pageTitle} description={pageDescription} path="/templates" baseUrl={canonicalUrl} />
            <Head><meta head-key="theme-color" name="theme-color" content="#070b1d" /></Head>
            <div className="min-h-screen bg-[#f7f8fc] text-slate-900" data-marketplace-surface="1">
                <header className="sticky top-0 z-50 border-b border-white/10 bg-[#060b1d]/95 text-white shadow-lg backdrop-blur-xl">
                    <div className="mx-auto flex min-h-[72px] max-w-[1480px] items-center justify-between gap-5 px-5 sm:px-7 lg:px-10">
                        <MarketplaceLogo href={marketplaceHome} />
                        <nav className="hidden items-center gap-6 lg:flex"><Link href={catalogPath} className="text-sm font-black text-white">Templates</Link><Link href={`${marketplaceHome}#how-it-works`} className="text-sm font-bold text-white/65 hover:text-white">How It Works</Link><Link href={`${marketplaceHome}#pricing`} className="text-sm font-bold text-white/65 hover:text-white">Pricing</Link><Link href={`${marketplaceHome}#website-care`} className="text-sm font-bold text-white/65 hover:text-white">Website Care</Link></nav>
                        <div className="flex items-center gap-2"><button type="button" onClick={() => setFavoritesOnly((value) => !value)} className={`hidden min-h-10 items-center gap-2 rounded-xl px-4 text-[13px] font-black sm:flex ${favoritesOnlyView ? 'bg-violet-500 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white'}`}>♥ Favorites <span className="rounded-full bg-white/10 px-2 py-0.5 text-[10px]">{favorites.length}</span></button>{canLogin && <Link href="/login" className="flex min-h-10 items-center rounded-xl border border-white/25 px-4 text-[13px] font-black text-white">Login</Link>}</div>
                    </div>
                </header>

                <main>
                    <section className="border-b border-slate-200 bg-white">
                        <div className="mx-auto max-w-[1480px] px-5 py-12 sm:px-7 lg:px-10 lg:py-16">
                            <p className="text-xs font-black uppercase tracking-[.2em] text-violet-600">Cosmic CMS Marketplace</p>
                            <div className="mt-3 flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                                <div><h1 className="text-4xl font-black tracking-[-.045em] text-slate-950 sm:text-5xl">{titleIndustry ? `${titleIndustry} Website Templates` : 'Premium Website Templates'}</h1><p className="mt-4 max-w-3xl text-base leading-7 text-slate-500">Choose a complete website, personalize it with Luna, then change content, design, images, and layout visually — no coding required.</p></div>
                                <form onSubmit={(event) => { event.preventDefault(); applyFilters({ q: search }); }} className="flex w-full max-w-xl gap-2"><div className="relative flex-1"><span className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">⌕</span><input value={search} onChange={(event) => setSearch(event.target.value)} className="h-12 w-full rounded-xl border-slate-200 pl-11 pr-4 text-sm font-semibold focus:border-violet-400 focus:ring-violet-300" placeholder="Search templates or industries..." /></div><button className="rounded-xl bg-slate-950 px-6 text-sm font-black text-white">Search</button></form>
                            </div>
                        </div>
                    </section>

                    <section className="border-b border-slate-200 bg-[#fbfbfd]">
                        <div className="mx-auto max-w-[1480px] px-5 py-5 sm:px-7 lg:px-10">
                            <div className="flex flex-wrap gap-2">
                                <button type="button" onClick={() => applyFilters({ industry: '' })} className={`rounded-xl border px-4 py-2 text-xs font-black ${!filters.industry ? 'border-violet-500 bg-violet-600 text-white' : 'border-slate-200 bg-white text-slate-600'}`}>All Industries</button>
                                {industries.map((item) => <button key={item.key} type="button" onClick={() => applyFilters({ industry: item.key })} className={`rounded-xl border px-4 py-2 text-xs font-black ${filters.industry === item.key ? 'border-violet-500 bg-violet-600 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-violet-200 hover:text-violet-700'}`}>{item.label} <span className="opacity-60">{item.count}</span></button>)}
                            </div>
                            <div className="mt-4 grid gap-3 sm:grid-cols-3 lg:grid-cols-[1fr_1fr_1fr_auto]">
                                <select value={filters.plan || ''} onChange={(event) => applyFilters({ plan: event.target.value })} className="h-11 rounded-xl border-slate-200 bg-white text-sm font-bold text-slate-700 focus:border-violet-400 focus:ring-violet-300"><option value="">All plans</option><option value="starter">Starter</option><option value="growth">Growth</option><option value="pro">Pro</option></select>
                                <select value={filters.style || ''} onChange={(event) => applyFilters({ style: event.target.value })} className="h-11 rounded-xl border-slate-200 bg-white text-sm font-bold text-slate-700 focus:border-violet-400 focus:ring-violet-300"><option value="">All styles</option>{styles.map((item) => <option key={item.key} value={item.key}>{item.label} ({item.count})</option>)}</select>
                                <select value={filters.sort || 'popular'} onChange={(event) => applyFilters({ sort: event.target.value })} className="h-11 rounded-xl border-slate-200 bg-white text-sm font-bold text-slate-700 focus:border-violet-400 focus:ring-violet-300"><option value="popular">Popular first</option><option value="newest">Newest</option><option value="price-low">Price: low to high</option><option value="price-high">Price: high to low</option><option value="name">Name A–Z</option></select>
                                <button type="button" onClick={() => { setSearch(''); router.get(catalogPath, {}, { preserveScroll: true }); }} className="h-11 rounded-xl border border-slate-200 bg-white px-5 text-xs font-black text-slate-600 hover:text-violet-700">Clear filters</button>
                            </div>
                        </div>
                    </section>

                    <section className="mx-auto max-w-[1480px] px-5 py-10 sm:px-7 lg:px-10 lg:py-14">
                        <div className="mb-7 flex flex-wrap items-center justify-between gap-3"><div><h2 className="text-2xl font-black tracking-[-.03em] text-slate-950">{favoritesOnlyView ? 'Your Favorites' : 'Website Collection'}</h2><p className="mt-1 text-sm font-semibold text-slate-500">{favoritesOnlyView ? `${displayTemplates.length} favorites on this page` : pagination ? `${pagination.total} published template${pagination.total === 1 ? '' : 's'}` : `${templates.length} published templates`}</p></div>{favoritesOnlyView && <button type="button" onClick={() => setFavoritesOnly(false)} className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-black text-slate-600">Show all</button>}</div>

                        {!catalogReady && <div className="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm font-semibold text-amber-900">Marketplace inventory database is not ready yet. Run <code className="font-black">php artisan migrate</code> and <code className="font-black">php artisan cosmic:seed-marketplace-templates --audit</code>.</div>}
                        {catalogReady && displayTemplates.length === 0 && <div className="rounded-2xl border border-slate-200 bg-white p-12 text-center"><p className="text-xl font-black text-slate-900">No templates matched those filters.</p><p className="mt-2 text-sm text-slate-500">Try another industry, plan, style, or search phrase.</p></div>}
                        <div className="grid gap-6 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">{displayTemplates.map((template) => <TemplateCard key={template.slug} template={template} favorite={favorites.includes(template.slug)} onFavorite={updateFavorite} />)}</div>

                        {!favoritesOnlyView && pagination && pagination.lastPage > 1 && <div className="mt-10 flex items-center justify-between gap-4 border-t border-slate-200 pt-7"><p className="text-xs font-bold text-slate-500">Showing {pagination.from}–{pagination.to} of {pagination.total}</p><div className="flex gap-2">{pagination.prevUrl ? <Link href={pagination.prevUrl} preserveScroll className="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-black text-slate-700">← Previous</Link> : <span className="rounded-xl border border-slate-100 bg-slate-50 px-5 py-2.5 text-sm font-black text-slate-300">← Previous</span>}{pagination.nextUrl ? <Link href={pagination.nextUrl} preserveScroll className="rounded-xl bg-slate-950 px-5 py-2.5 text-sm font-black text-white">Next →</Link> : <span className="rounded-xl bg-slate-100 px-5 py-2.5 text-sm font-black text-slate-300">Next →</span>}</div></div>}
                    </section>
                </main>

                <footer className="border-t border-slate-800 bg-[#050918] text-slate-400"><div className="mx-auto flex max-w-[1480px] flex-col gap-6 px-5 py-9 sm:px-7 md:flex-row md:items-center md:justify-between lg:px-10"><MarketplaceLogo href={marketplaceHome} /><div className="flex flex-wrap gap-5 text-xs font-bold"><Link href={catalogPath} className="hover:text-white">Templates</Link><Link href={`${marketplaceHome}#pricing`} className="hover:text-white">Pricing</Link><Link href="/terms" className="hover:text-white">Terms</Link><Link href="/privacy" className="hover:text-white">Privacy</Link></div><p className="text-xs">© {new Date().getFullYear()} Cosmic CMS</p></div></footer>
            </div>
        </>
    );
}
