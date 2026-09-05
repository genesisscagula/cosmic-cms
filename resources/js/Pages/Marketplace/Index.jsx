import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import SeoHead from '@/Components/Seo/SeoHead';
import cosmicLogo from '../../../images/cosmic-cms-logo.png';

const industryIcons = {
    accounting: '▤',
    restaurant: '♨',
    construction: '⌂',
    dental: '◇',
    medical: '✚',
    law: '⚖',
    'real-estate': '⌂',
    salon: '✺',
    automotive: '◉',
    fitness: '◎',
};

const pricingCopy = {
    starter: {
        description: 'Perfect for small businesses that want a polished online presence.',
        items: ['AI content setup', 'Fully visual editing', 'Hosting + SSL', 'Website care'],
    },
    growth: {
        description: 'For growing businesses that need more pages, content, and conversion tools.',
        featured: true,
        items: ['AI content setup', 'Advanced sections', 'Priority support', 'Hosting + website care'],
    },
    pro: {
        description: 'For ambitious brands that want the richest designs and advanced capabilities.',
        items: ['AI content setup', 'Premium Sparks', 'Ecommerce-ready options', 'Priority website care'],
    },
};

const decorativePreview = {
    slug: 'cosmic-marketplace-preview',
    name: 'Cosmic Marketplace',
    industry: 'business',
    industryLabel: 'Premium Business Website',
    plan: 'Growth',
    creditPrice: 1000,
    pages: 10,
    title: 'A premium website, ready to become yours.',
    copy: 'Choose a complete website and let Luna personalize the details for your business.',
    image: 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=1200&q=82',
    features: ['AI content setup', 'No-code customization', 'Website care'],
};

const planTone = {
    Starter: 'bg-emerald-500 text-white',
    Growth: 'bg-indigo-500 text-white',
    Pro: 'bg-amber-500 text-amber-950',
};

const previewGradient = {
    Starter: 'from-slate-950/90 via-slate-900/58 to-emerald-950/25',
    Growth: 'from-slate-950/90 via-indigo-950/60 to-indigo-900/20',
    Pro: 'from-slate-950/94 via-slate-950/64 to-amber-900/25',
};

function SparkleIcon({ className = '' }) {
    return (
        <svg viewBox="0 0 24 24" aria-hidden="true" className={className} fill="none">
            <path d="M12 2.8c.62 4.72 4.36 8.46 9.08 9.08C16.36 12.5 12.62 16.24 12 20.96 11.38 16.24 7.64 12.5 2.92 11.88 7.64 11.26 11.38 7.52 12 2.8Z" fill="currentColor" />
        </svg>
    );
}

function MarketplaceLogo({ href = '/marketplace' }) {
    return (
        <Link href={href} className="flex min-w-0 items-center gap-3" aria-label="Cosmic CMS Marketplace home">
            <img src={cosmicLogo} alt="Cosmic CMS" className="h-10 w-auto max-w-[140px] object-contain brightness-0 invert sm:h-11 sm:max-w-[156px]" />
            <span className="hidden h-8 w-px bg-white/15 sm:block" />
            <span className="hidden text-[10px] font-black uppercase tracking-[.26em] text-white/80 sm:block">Marketplace</span>
        </Link>
    );
}

function WebsitePreview({ template, compact = false }) {
    return (
        <div className={`relative overflow-hidden ${compact ? 'aspect-[1.24/1]' : 'aspect-[1.55/1]'} bg-slate-900`}>
            <img src={template.image} alt="" className="absolute inset-0 h-full w-full object-cover" loading="lazy" />
            <div className={`absolute inset-0 bg-gradient-to-r ${template.overlay || previewGradient[template.plan] || previewGradient.Growth}`} />
            <div className="absolute inset-x-0 top-0 flex h-9 items-center justify-between border-b border-white/10 bg-slate-950/20 px-4 backdrop-blur-sm">
                <div className="flex items-center gap-1.5">
                    <span className="h-2 w-2 rounded-full bg-white/75" />
                    <span className="h-2 w-8 rounded-full bg-white/20" />
                </div>
                <div className="flex gap-2 text-[5px] font-bold uppercase tracking-[.14em] text-white/60">
                    <span>About</span><span>Services</span><span>Contact</span>
                </div>
            </div>
            <div className="absolute inset-x-0 bottom-0 p-5 sm:p-6">
                <p className="text-[7px] font-black uppercase tracking-[.24em] text-white/70">{template.industryLabel}</p>
                <h3 className={`${compact ? 'mt-2 max-w-[250px] text-xl' : 'mt-2 max-w-[320px] text-2xl'} font-black leading-[.98] tracking-[-.04em] text-white`}>{template.title}</h3>
                <div className="mt-4 flex gap-2">
                    <span className="rounded-md bg-white px-3 py-1.5 text-[7px] font-black text-slate-950">Get started</span>
                    <span className="rounded-md border border-white/35 px-3 py-1.5 text-[7px] font-bold text-white">Learn more</span>
                </div>
            </div>
        </div>
    );
}

function TemplateCard({ template, catalogPath = '/marketplace/templates' }) {
    const checkoutHref = template.checkoutPath || `/marketplace/checkout/${encodeURIComponent(template.slug)}`;

    return (
        <article className="group overflow-hidden rounded-[22px] border border-slate-200 bg-white shadow-[0_16px_45px_rgba(15,23,42,.06)] transition duration-300 hover:-translate-y-1 hover:shadow-[0_24px_60px_rgba(15,23,42,.12)]">
            <Link href={template.detailPath || `${catalogPath}?q=${encodeURIComponent(template.name)}`} className="relative m-3 block overflow-hidden rounded-[16px]">
                <WebsitePreview template={template} compact />
                <span className={`absolute left-3 top-3 rounded-full px-2.5 py-1 text-[9px] font-black uppercase tracking-[.14em] shadow-sm ${planTone[template.plan]}`}>
                    {template.plan}
                </span>
            </Link>
            <div className="px-5 pb-5 pt-2">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <Link href={template.detailPath || `${catalogPath}?q=${encodeURIComponent(template.name)}`} className="text-lg font-black tracking-[-.025em] text-slate-950 hover:text-violet-700">{template.name}</Link>
                        <p className="mt-0.5 text-sm font-medium text-slate-500">{template.industryLabel}</p>
                    </div>
                    <button type="button" className="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-slate-200 text-lg text-slate-500 transition hover:border-violet-200 hover:bg-violet-50 hover:text-violet-600" aria-label={`Favorite ${template.name}`}>♡</button>
                </div>

                <ul className="mt-4 space-y-2 text-[13px] font-semibold text-slate-600">
                    <li className="flex items-center gap-2"><span className="text-emerald-600">✓</span>{template.pages} Pages</li>
                    {template.features.slice(0, 2).map((feature) => <li key={feature} className="flex items-center gap-2"><span className="text-emerald-600">✓</span>{feature}</li>)}
                </ul>

                <div className="mt-5 flex items-end gap-1">
                    <span className="text-3xl font-black tracking-[-.04em] text-slate-950">{Number(template.creditPrice || 0).toLocaleString()}</span>
                    <span className="pb-1 text-sm font-semibold text-slate-500">Cosmic Credits</span>
                </div>

                <div className="mt-5 grid grid-cols-[.82fr_1.18fr] gap-2.5">
                    <Link href={template.demoPath || `${catalogPath}?q=${encodeURIComponent(template.name)}`} className="flex min-h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-black text-slate-800 transition hover:bg-slate-50">Preview</Link>
                    <Link href={checkoutHref} className="flex min-h-11 items-center justify-center rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 px-4 text-center text-sm font-black text-white shadow-lg shadow-violet-200 transition hover:from-violet-700 hover:to-indigo-700">Get This Website</Link>
                </div>
            </div>
        </article>
    );
}

function FeatureItem({ icon, title, copy }) {
    return (
        <div className="flex gap-4 px-5 py-5 lg:px-6">
            <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-violet-100 text-lg text-violet-700">{icon}</div>
            <div>
                <p className="text-sm font-black text-slate-950">{title}</p>
                <p className="mt-1 text-xs leading-5 text-slate-500">{copy}</p>
            </div>
        </div>
    );
}

export default function MarketplaceIndex({ canLogin = true, canRegister = true, marketplaceHost = false, canonicalUrl = 'https://marketplace.cosmiccms.com', catalogReady = true, featuredTemplates = [], industries: industryFacets = [], plans = [], catalogPath = '/marketplace/templates' }) {
    const marketplaceHome = marketplaceHost ? '/' : '/marketplace';
    const [industry, setIndustry] = useState('all');
    const [mobileOpen, setMobileOpen] = useState(false);

    const industries = useMemo(() => ([
        { key: 'all', label: 'All', icon: '✦', count: featuredTemplates.length },
        ...industryFacets.map((item) => ({ ...item, icon: industryIcons[item.key] || '◈' })),
    ]), [industryFacets, featuredTemplates.length]);

    const templates = featuredTemplates;
    const heroTemplates = templates.length ? templates : [decorativePreview, decorativePreview, decorativePreview];
    const pricing = plans.map((plan) => ({
        ...plan,
        description: pricingCopy[plan.key]?.description || 'A one-install Marketplace template price.',
        featured: Boolean(pricingCopy[plan.key]?.featured),
        items: [`${plan.pageCount} page website`, ...(pricingCopy[plan.key]?.items || [])],
    }));

    const visibleTemplates = useMemo(() => {
        if (industry === 'all') return templates.slice(0, 4);
        return templates.filter((item) => item.industry === industry).slice(0, 4);
    }, [industry, templates]);

    const schema = {
        '@context': 'https://schema.org',
        '@type': 'WebSite',
        name: 'Cosmic CMS Marketplace',
        url: canonicalUrl,
        description: 'Premium customizable business website templates with AI setup, hosting, and ongoing website care.',
    };

    return (
        <>
            <SeoHead
                title="Premium Website Templates & Managed Websites | Cosmic CMS Marketplace"
                description="Choose a premium website template for your industry, personalize it with AI, customize everything without coding, and launch with hosting and website care included."
                path="/"
                baseUrl={canonicalUrl}
                schema={schema}
            />
            <Head>
                <meta head-key="theme-color" name="theme-color" content="#070b1d" />
            </Head>

            <div className="min-h-screen bg-[#f7f8fc] font-sans text-slate-900 selection:bg-violet-200 selection:text-violet-950" data-marketplace-surface="1">
                <header className="sticky top-0 z-50 border-b border-white/10 bg-[#060b1d]/95 text-white shadow-lg shadow-slate-950/10 backdrop-blur-xl">
                    <div className="mx-auto flex min-h-[72px] max-w-[1480px] items-center justify-between gap-5 px-5 sm:px-7 lg:px-10">
                        <MarketplaceLogo href={marketplaceHome} />

                        <nav className="hidden items-center gap-7 xl:flex" aria-label="Marketplace navigation">
                            {[
                                ['Templates', catalogPath],
                                ['Industries', '#industries'],
                                ['Features', '#features'],
                                ['How It Works', '#how-it-works'],
                                ['Pricing', '#pricing'],
                                ['Website Care', '#website-care'],
                            ].map(([label, href]) => (
                                <a key={label} href={href} className="text-[13px] font-bold text-white/70 transition hover:text-white">{label}</a>
                            ))}
                        </nav>

                        <div className="hidden items-center gap-2 lg:flex">
                            <Link href={`${catalogPath}?favorites=1`} className="flex min-h-10 items-center gap-2 rounded-xl px-3 text-[13px] font-bold text-white/70 transition hover:bg-white/10 hover:text-white">♡ <span>Favorites</span></Link>
                            {canLogin && <Link href="/login" className="flex min-h-10 items-center rounded-xl border border-white/25 px-4 text-[13px] font-black text-white transition hover:bg-white/10">Login</Link>}
                            <a href="#featured" className="flex min-h-10 items-center rounded-xl bg-gradient-to-r from-violet-500 to-indigo-500 px-5 text-[13px] font-black text-white shadow-lg shadow-violet-950/25 transition hover:from-violet-400 hover:to-indigo-400">Get Started</a>
                        </div>

                        <button type="button" onClick={() => setMobileOpen((value) => !value)} className="grid h-11 w-11 place-items-center rounded-xl border border-white/15 bg-white/5 text-xl lg:hidden" aria-label="Toggle marketplace navigation">{mobileOpen ? '×' : '☰'}</button>
                    </div>
                    {mobileOpen && (
                        <div className="border-t border-white/10 bg-[#070d22] px-5 py-5 lg:hidden">
                            <div className="mx-auto grid max-w-[1480px] gap-2">
                                {[
                                    ['Templates', catalogPath], ['Industries', '#industries'], ['Features', '#features'], ['How It Works', '#how-it-works'], ['Pricing', '#pricing'], ['Website Care', '#website-care'],
                                ].map(([label, href]) => <a key={label} href={href} onClick={() => setMobileOpen(false)} className="rounded-xl px-4 py-3 text-sm font-bold text-white/80 hover:bg-white/5 hover:text-white">{label}</a>)}
                                <div className="mt-2 grid grid-cols-2 gap-2 border-t border-white/10 pt-4">
                                    <Link href="/login" className="flex min-h-11 items-center justify-center rounded-xl border border-white/20 text-sm font-black text-white">Login</Link>
                                    <a href="#featured" onClick={() => setMobileOpen(false)} className="flex min-h-11 items-center justify-center rounded-xl bg-violet-600 text-sm font-black text-white">Get Started</a>
                                </div>
                            </div>
                        </div>
                    )}
                </header>

                <main>
                    <section className="relative overflow-hidden bg-[#070b1d] text-white">
                        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_22%_20%,rgba(124,58,237,.28),transparent_28%),radial-gradient(circle_at_82%_36%,rgba(59,130,246,.20),transparent_30%),linear-gradient(120deg,#070b1d_0%,#0b1028_55%,#07142f_100%)]" />
                        <div className="pointer-events-none absolute inset-0 opacity-35 [background-image:radial-gradient(circle_at_center,rgba(255,255,255,.18)_0_1px,transparent_1.25px)] [background-size:34px_34px] [mask-image:linear-gradient(to_bottom,black,transparent_88%)]" />

                        <div className="relative mx-auto grid max-w-[1480px] gap-14 px-5 pb-20 pt-16 sm:px-7 sm:pt-20 lg:grid-cols-[.9fr_1.1fr] lg:items-center lg:px-10 lg:pb-24 lg:pt-24">
                            <div className="max-w-2xl">
                                <div className="inline-flex items-center gap-2 rounded-full border border-violet-400/25 bg-violet-400/10 px-4 py-2 text-[11px] font-black uppercase tracking-[.18em] text-violet-200">
                                    <SparkleIcon className="h-4 w-4" /> Premium website marketplace
                                </div>
                                <h1 className="mt-7 text-[46px] font-black leading-[.98] tracking-[-.05em] text-white sm:text-6xl lg:text-[70px]">
                                    Premium websites built for <span className="bg-gradient-to-r from-violet-300 via-white to-blue-300 bg-clip-text text-transparent">your industry.</span>
                                </h1>
                                <p className="mt-7 max-w-xl text-base leading-7 text-slate-300 sm:text-lg sm:leading-8">
                                    Choose a professionally designed website, personalize the content with AI, and customize everything visually — hosting, security, and website care included.
                                </p>

                                <div className="mt-8 grid gap-4 text-sm sm:grid-cols-3">
                                    {[
                                        ['▦', 'Complete website', '5–15 pages included'],
                                        ['✦', 'AI-powered setup', 'Personalized for you'],
                                        ['◈', 'No coding needed', 'Change anything visually'],
                                    ].map(([icon, title, copy]) => (
                                        <div key={title} className="flex gap-3">
                                            <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-violet-300/20 bg-violet-300/10 text-violet-200">{icon}</span>
                                            <div><p className="font-black text-white">{title}</p><p className="mt-1 text-xs text-slate-400">{copy}</p></div>
                                        </div>
                                    ))}
                                </div>

                                <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                                    <a href="#featured" className="flex min-h-[52px] items-center justify-center rounded-xl bg-gradient-to-r from-violet-500 to-indigo-500 px-7 text-sm font-black text-white shadow-[0_18px_35px_rgba(99,102,241,.28)] transition hover:-translate-y-0.5 hover:from-violet-400 hover:to-indigo-400">Browse Templates</a>
                                    <a href="#how-it-works" className="flex min-h-[52px] items-center justify-center rounded-xl border border-white/25 bg-white/5 px-7 text-sm font-black text-white backdrop-blur transition hover:bg-white/10">How It Works</a>
                                </div>
                            </div>

                            <div className="relative mx-auto w-full max-w-[720px] pb-8 pt-6 lg:pl-12">
                                <div className="absolute left-[2%] top-[13%] w-[48%] -rotate-6 overflow-hidden rounded-2xl border border-white/10 bg-slate-900 opacity-[.65] shadow-2xl">
                                    <WebsitePreview template={heroTemplates[1] || heroTemplates[0]} compact />
                                </div>
                                <div className="absolute right-[1%] top-[18%] w-[45%] rotate-5 overflow-hidden rounded-2xl border border-white/10 bg-slate-900 opacity-[.55] shadow-2xl">
                                    <WebsitePreview template={heroTemplates[2] || heroTemplates[0]} compact />
                                </div>
                                <div className="relative z-10 mx-auto w-[68%] min-w-[270px] overflow-hidden rounded-[22px] border border-white/15 bg-white p-2 shadow-[0_40px_90px_rgba(0,0,0,.5)]">
                                    <div className="overflow-hidden rounded-[16px]"><WebsitePreview template={heroTemplates[0]} /></div>
                                    <div className="grid grid-cols-3 gap-2 p-3">
                                        {['Services', 'About', 'Contact'].map((label) => <div key={label} className="rounded-lg bg-slate-100 px-2 py-3 text-center text-[8px] font-black uppercase tracking-[.12em] text-slate-500">{label}</div>)}
                                    </div>
                                </div>
                                <div className="absolute bottom-0 right-[4%] z-20 max-w-[220px] rounded-2xl border border-white/70 bg-white p-4 text-slate-900 shadow-2xl">
                                    <div className="flex gap-3"><span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-violet-500 to-indigo-500 text-sm font-black text-white">✓</span><div><p className="text-sm font-black">100% Customizable</p><p className="mt-1 text-xs leading-5 text-slate-500">Change anything, no coding needed.</p></div></div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="industries" className="border-b border-slate-200 bg-white">
                        <div className="mx-auto max-w-[1480px] px-5 py-10 sm:px-7 lg:px-10">
                            <div className="flex flex-wrap items-end justify-between gap-4">
                                <div><p className="text-xs font-black uppercase tracking-[.18em] text-violet-600">Find your starting point</p><h2 className="mt-2 text-2xl font-black tracking-[-.035em] text-slate-950 sm:text-3xl">Browse by Industry</h2></div>
                                <Link href={catalogPath} className="text-sm font-black text-violet-600 hover:text-violet-800">View all industries →</Link>
                            </div>
                            <div className="mt-7 flex gap-3 overflow-x-auto pb-2 [scrollbar-width:thin]">
                                {industries.slice(1).map((item) => (
                                    <Link key={item.key} href={`${catalogPath}/${item.key}`} data-marketplace-industry-card="1" className={`flex min-w-[112px] flex-col items-center justify-center rounded-2xl border px-4 py-5 text-center transition ${industry === item.key ? 'border-violet-400 bg-violet-50 text-violet-700 shadow-sm' : 'border-slate-200 bg-white text-slate-700 hover:border-violet-200 hover:bg-violet-50/50'}`}>
                                        <span data-marketplace-industry-icon className="grid h-9 w-9 place-items-center rounded-xl bg-slate-50 text-lg">{item.icon}</span>
                                        <span data-marketplace-industry-label className="mt-3 text-xs font-black">{item.label}</span>
                                        <span data-marketplace-industry-count className="mt-1 text-[10px] font-bold text-slate-400">{item.count} template{item.count === 1 ? '' : 's'}</span>
                                    </Link>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section id="featured" className="scroll-mt-20 bg-[#f7f8fc]">
                        <div className="mx-auto max-w-[1480px] px-5 py-16 sm:px-7 lg:px-10 lg:py-20">
                            <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                                <div>
                                    <p className="text-xs font-black uppercase tracking-[.18em] text-violet-600">Ready-made website concepts</p>
                                    <h2 className="mt-2 text-3xl font-black tracking-[-.04em] text-slate-950 sm:text-4xl">Featured Templates</h2>
                                    <p className="mt-3 max-w-2xl text-sm leading-6 text-slate-500">Pick the design direction you love. In the next setup step, Luna personalizes the content, branding, services, and details for your business.</p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {industries.slice(0, 5).map((item) => <button key={item.key} type="button" onClick={() => setIndustry(item.key)} className={`rounded-xl border px-4 py-2 text-xs font-black transition ${industry === item.key ? 'border-violet-500 bg-violet-600 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-violet-200 hover:text-violet-700'}`}>{item.label}</button>)}
                                </div>
                            </div>

                            <div className="mt-9 grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
                                {visibleTemplates.map((template) => <TemplateCard key={template.slug} template={template} catalogPath={catalogPath} />)}
                            </div>

                            {!catalogReady && (
                                <div className="mt-8 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm font-semibold text-amber-900">
                                    Marketplace database setup is pending. Run the marketplace migration and seed command to load the published website inventory.
                                </div>
                            )}
                            {catalogReady && visibleTemplates.length === 0 && (
                                <div className="mt-8 rounded-2xl border border-slate-200 bg-white px-5 py-8 text-center text-sm font-semibold text-slate-500">No published templates are available in this industry yet.</div>
                            )}
                            <div className="mt-8 text-center">
                                <Link href={catalogPath} className="inline-flex rounded-xl border border-slate-300 bg-white px-6 py-3 text-sm font-black text-slate-800 shadow-sm transition hover:border-violet-300 hover:text-violet-700">View All Templates</Link>
                            </div>
                        </div>
                    </section>

                    <section id="features" className="bg-white">
                        <div className="mx-auto max-w-[1480px] px-5 py-8 sm:px-7 lg:px-10">
                            <div className="grid overflow-hidden rounded-[24px] border border-violet-100 bg-gradient-to-r from-violet-50/80 via-white to-indigo-50/80 shadow-[0_18px_55px_rgba(99,102,241,.07)] sm:grid-cols-2 xl:grid-cols-5">
                                <FeatureItem icon="✎" title="Purely Customizable" copy="Change colors, fonts, images, text, layouts — visually." />
                                <FeatureItem icon="✦" title="AI Content Generation" copy="Luna writes and personalizes your website content automatically." />
                                <FeatureItem icon="⌘" title="No Coding Needed" copy="Edit the complete website without touching HTML or CSS." />
                                <FeatureItem icon="◈" title="Hosting & Security" copy="Fast hosting, SSL, backups, and security are included." />
                                <FeatureItem icon="♙" title="Website Care Included" copy="Updates, support, backups, and maintenance are handled for you." />
                            </div>
                        </div>
                    </section>

                    <section id="how-it-works" className="scroll-mt-20 bg-white">
                        <div className="mx-auto grid max-w-[1480px] gap-12 px-5 py-20 sm:px-7 lg:grid-cols-[.92fr_1.08fr] lg:px-10">
                            <div>
                                <p className="text-xs font-black uppercase tracking-[.18em] text-violet-600">From template to live website</p>
                                <h2 className="mt-3 text-3xl font-black tracking-[-.04em] text-slate-950 sm:text-4xl">How It Works</h2>
                                <div className="mt-9 space-y-6">
                                    {[
                                        ['1', 'Choose Your Template', 'Pick a website style you already love from the Cosmic Marketplace.'],
                                        ['2', 'Personalize with AI', 'Answer a few questions. Luna adapts the copy, services, details, and branding for your business.'],
                                        ['3', 'Review & Customize', 'Change text, colors, images, sections, and layouts visually with no coding required.'],
                                        ['4', 'Launch + Website Care', 'Publish when ready. Cosmic handles hosting, security, backups, maintenance, and ongoing care.'],
                                    ].map(([step, title, copy], index) => (
                                        <div key={step} className="relative flex gap-5">
                                            {index < 3 && <span className="absolute left-5 top-10 h-[calc(100%+8px)] w-px bg-violet-200" />}
                                            <span className="relative z-10 grid h-10 w-10 shrink-0 place-items-center rounded-full border border-violet-200 bg-violet-50 text-sm font-black text-violet-700">{step}</span>
                                            <div className="pb-2"><h3 className="text-base font-black text-slate-950">{title}</h3><p className="mt-2 max-w-xl text-sm leading-6 text-slate-500">{copy}</p></div>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="relative min-h-[420px] overflow-hidden rounded-[30px] border border-slate-200 bg-gradient-to-br from-slate-50 to-violet-50 p-6 shadow-[0_24px_70px_rgba(15,23,42,.08)] sm:p-9">
                                <div className="mx-auto max-w-[560px] overflow-hidden rounded-[22px] border border-slate-200 bg-white p-2 shadow-xl">
                                    <div className="overflow-hidden rounded-[16px]"><WebsitePreview template={heroTemplates[0]} /></div>
                                </div>
                                <div className="absolute bottom-8 left-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-xl sm:left-8">
                                    <p className="text-[9px] font-black uppercase tracking-[.18em] text-slate-400">Brand colors</p>
                                    <div className="mt-3 flex gap-2">{['bg-emerald-500','bg-sky-500','bg-violet-500','bg-amber-400','bg-rose-500'].map((tone) => <span key={tone} className={`h-7 w-7 rounded-full ${tone}`} />)}</div>
                                </div>
                                <div className="absolute bottom-8 right-4 w-[190px] rounded-2xl border border-violet-100 bg-white p-4 shadow-xl sm:right-8 sm:w-[220px]">
                                    <div className="flex items-center gap-2 text-violet-700"><SparkleIcon className="h-4 w-4" /><span className="text-[10px] font-black uppercase tracking-[.14em]">AI Content</span></div>
                                    <div className="mt-3 space-y-2 text-xs font-bold text-slate-600">{['Business name', 'Services', 'Location', 'About your business'].map((item) => <p key={item}>✓ {item}</p>)}</div>
                                    <button type="button" className="mt-4 w-full rounded-lg bg-violet-600 px-3 py-2 text-[10px] font-black text-white">Generate with AI</button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="pricing" className="scroll-mt-20 border-y border-slate-200 bg-[#f8f9fd]">
                        <div className="mx-auto max-w-[1180px] px-5 py-20 sm:px-7 lg:px-10">
                            <div className="text-center">
                                <p className="text-xs font-black uppercase tracking-[.18em] text-violet-600">One template installation · Cosmic Credits</p>
                                <h2 className="mt-3 text-3xl font-black tracking-[-.04em] text-slate-950 sm:text-4xl">Simple Marketplace Pricing</h2>
                                <p className="mx-auto mt-4 max-w-2xl text-sm leading-6 text-slate-500">Marketplace templates use Cosmic Credits for one installation into a normal Agency website. Normal editing does not charge the template price again, and AI usage keeps its existing credit rules.</p>
                            </div>

                            <div className="mt-10 grid gap-5 lg:grid-cols-3">
                                {pricing.map((plan) => (
                                    <article key={plan.key} className={`relative rounded-[24px] border bg-white p-6 shadow-[0_18px_55px_rgba(15,23,42,.06)] ${plan.featured ? 'border-violet-400 ring-2 ring-violet-100' : 'border-slate-200'}`}>
                                        {plan.featured && <span className="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-violet-600 px-4 py-1 text-[9px] font-black uppercase tracking-[.16em] text-white shadow-lg">Most Popular</span>}
                                        <h3 className="text-xl font-black text-slate-950">{plan.name}</h3>
                                        <p className="mt-2 min-h-[44px] text-sm leading-6 text-slate-500">{plan.description}</p>
                                        <div className="mt-6 flex items-end gap-2"><span className="text-4xl font-black tracking-[-.05em] text-slate-950">{Number(plan.creditPrice || 0).toLocaleString()}</span><span className="pb-1 text-sm font-bold text-slate-500">Cosmic Credits</span></div>
                                        <ul className="mt-6 space-y-3 border-t border-slate-100 pt-6 text-sm font-semibold text-slate-600">
                                            {plan.items.map((item) => <li key={item} className="flex gap-2"><span className="font-black text-emerald-600">✓</span>{item}</li>)}
                                        </ul>
                                        <Link href={`${catalogPath}?plan=${plan.key}`} className={`mt-7 flex min-h-12 items-center justify-center rounded-xl px-5 text-sm font-black transition ${plan.featured ? 'bg-gradient-to-r from-violet-600 to-indigo-600 text-white shadow-lg shadow-violet-200 hover:from-violet-700 hover:to-indigo-700' : 'border border-slate-300 bg-white text-slate-900 hover:border-violet-300 hover:text-violet-700'}`}>Browse {plan.name} Templates</Link>
                                    </article>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section id="website-care" className="scroll-mt-20 bg-white">
                        <div className="mx-auto max-w-[1480px] px-5 py-16 sm:px-7 lg:px-10">
                            <div className="grid gap-6 rounded-[28px] border border-slate-200 bg-gradient-to-r from-white via-violet-50/45 to-white p-7 shadow-[0_20px_60px_rgba(15,23,42,.06)] md:grid-cols-3 md:p-9">
                                <div className="flex gap-4"><span className="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-violet-100 text-xl text-violet-700">◇</span><div><h3 className="font-black text-slate-950">14-Day Peace of Mind</h3><p className="mt-2 text-sm leading-6 text-slate-500">Review your new website and make changes before you fully settle into your new online home.</p></div></div>
                                <div className="flex gap-4"><span className="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-indigo-100 text-xl text-indigo-700">♧</span><div><h3 className="font-black text-slate-950">Real Website Care</h3><p className="mt-2 text-sm leading-6 text-slate-500">Hosting, SSL, backups, updates, security, and ongoing support continue through your Agency plan.</p></div></div>
                                <div className="flex gap-4"><span className="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-amber-100 text-xl text-amber-700">★</span><div><h3 className="font-black text-slate-950">Built to Stay Editable</h3><p className="mt-2 text-sm leading-6 text-slate-500">Your website remains fully customizable in Cosmic Studio without requiring code.</p></div></div>
                            </div>
                        </div>
                    </section>

                    <section className="bg-[#070b1d] text-white">
                        <div className="mx-auto flex max-w-[1180px] flex-col items-center px-5 py-20 text-center sm:px-7 lg:px-10">
                            <SparkleIcon className="h-8 w-8 text-violet-300" />
                            <h2 className="mt-5 max-w-3xl text-3xl font-black tracking-[-.04em] sm:text-5xl">Choose the website you love. Make it completely yours.</h2>
                            <p className="mt-5 max-w-2xl text-base leading-7 text-slate-300">Start from a premium design instead of a blank page. Luna handles the first personalization, then you stay in control with a no-code visual builder.</p>
                            <a href="#featured" className="mt-8 flex min-h-[52px] items-center justify-center rounded-xl bg-gradient-to-r from-violet-500 to-indigo-500 px-8 text-sm font-black text-white shadow-xl shadow-violet-950/30">Browse Website Templates</a>
                        </div>
                    </section>
                </main>

                <footer className="border-t border-slate-800 bg-[#050918] text-slate-400">
                    <div className="mx-auto flex max-w-[1480px] flex-col gap-6 px-5 py-10 sm:px-7 md:flex-row md:items-center md:justify-between lg:px-10">
                        <MarketplaceLogo href={marketplaceHome} />
                        <div className="flex flex-wrap gap-x-6 gap-y-2 text-xs font-bold">
                            <Link href={catalogPath} className="hover:text-white">Templates</Link>
                            <a href="#pricing" className="hover:text-white">Pricing</a>
                            <Link href="/terms" className="hover:text-white">Terms</Link>
                            <Link href="/privacy" className="hover:text-white">Privacy</Link>
                            <Link href="/" className="hover:text-white">Cosmic CMS</Link>
                        </div>
                        <p className="text-xs">© {new Date().getFullYear()} Cosmic CMS</p>
                    </div>
                </footer>
            </div>
        </>
    );
}
