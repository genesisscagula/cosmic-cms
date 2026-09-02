import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import SeoHead from '@/Components/Seo/SeoHead';
import PublicHeader from '@/Components/Public/PublicHeader';
import PublicFooter from '@/Components/Public/PublicFooter';
import CreateFreeDemoModal from '@/Components/CreateFreeDemoModal';
import { trackCosmicEvent } from '@/Analytics/tracking';
import rocketSpace from '../../images/cosmic-rocket-space.png';
import welcomeHeroBg from '../../images/cosmic-welcome-curve-rocket.png';
import cosmicLogo from '../../images/cosmic-cms-logo.png';

const featureCards = [
    ['AI planning', 'Start with a business idea, not a blank canvas', 'Luna turns a short prompt into a complete, suitable website structure and first draft.', '✦'],
    ['Visual builder', 'Edit every section without touching code', 'Update copy, images, colors, layout and interactions while keeping the output responsive.', '▦'],
    ['Publishing', 'Launch fast, lightweight websites', 'Preview responsively, publish with confidence, and export clean static output.', '↗'],
];

const workflow = [
    ['01', 'Describe your business', 'Tell Luna what you offer, who you serve and the style you want.'],
    ['02', 'Generate a starting point', 'AI builds your site structure, imagery direction and content.'],
    ['03', 'Refine in the builder', 'Edit every section, page, image and global setting visually.'],
    ['04', 'Preview and publish', 'Review desktop, tablet and mobile output, then go live.'],
];

const guides = [
    ['/ai-website-builder', 'AI Website Builder'],
    ['/ai-website-generator', 'AI Website Generator'],
    ['/modern-website-builder', 'Modern Website Builder'],
    ['/website-builder-for-small-business', 'Small Business Website Builder'],
    ['/no-code-website-builder', 'No-Code Website Builder'],
    ['/website-redesign-with-ai', 'Website Redesign with AI'],
];

const plans = [
    ['Starter', '$49', 'For a business launching one complete standard website.', ['1 Website', 'Luna AI Website Assistance', '30 Marketplace Sparks', 'Static Website Export', 'Basic SEO & Analytics']],
    ['Growth', '$79', 'For a growing business publishing content and capturing more leads.', ['1 Website', 'Everything in Starter', 'Posts & Updates', '100 Marketplace Sparks', 'Lead Tools + Enhanced SEO']],
    ['Pro', '$129', 'For a serious website that needs ecommerce and the complete single-site stack.', ['1 Website', 'Everything in Growth', 'Full Ecommerce', 'Unlimited Marketplace Sparks', 'Priority AI + Advanced Analytics']],
];

const technologies = [
    ['react', 'React'], ['tailwind', 'Tailwind CSS'], ['next', 'Next.js'], ['vercel', 'Vercel'],
    ['sanity', 'Sanity'], ['inertia', 'Inertia'], ['paypal', 'PayPal'], ['stripe', 'Stripe'],
];

function TechnologyLogo({ type }) {
    if (type === 'react') return <svg viewBox="0 0 32 32" aria-hidden="true" className="h-7 w-7 fill-none stroke-current"><circle cx="16" cy="16" r="2.4" fill="currentColor" stroke="none" /><ellipse cx="16" cy="16" rx="13" ry="5.2" /><ellipse cx="16" cy="16" rx="13" ry="5.2" transform="rotate(60 16 16)" /><ellipse cx="16" cy="16" rx="13" ry="5.2" transform="rotate(120 16 16)" /></svg>;
    if (type === 'tailwind') return <svg viewBox="0 0 36 24" aria-hidden="true" className="h-7 w-9 fill-current"><path d="M9 8.2c1.2-4 3.7-6 7.6-6 5.8 0 6.5 4.4 9.4 5.1 1.9.5 3.6-.2 5-2-1.2 4-3.7 6-7.6 6-5.8 0-6.5-4.4-9.4-5.1-1.9-.5-3.6.2-5 2Zm-5 8.5c1.2-4 3.7-6 7.6-6 5.8 0 6.5 4.4 9.4 5.1 1.9.5 3.6-.2 5-2-1.2 4-3.7 6-7.6 6-5.8 0-6.5-4.4-9.4-5.1-1.9-.5-3.6.2-5 2Z" /></svg>;
    if (type === 'next') return <span aria-hidden="true" className="grid h-8 w-8 place-items-center rounded-full border border-current font-serif text-base font-bold">N</span>;
    if (type === 'vercel') return <svg viewBox="0 0 30 26" aria-hidden="true" className="h-7 w-8 fill-current"><path d="M15 2 29 25H1L15 2Z" /></svg>;
    if (type === 'sanity') return <span aria-hidden="true" className="text-4xl font-black italic leading-none tracking-[-.2em]">S</span>;
    if (type === 'inertia') return <svg viewBox="0 0 38 26" aria-hidden="true" className="h-7 w-9 fill-none stroke-current stroke-[4]"><path d="m3 3 9 10-9 10M14 3l9 10-9 10M25 3l9 10-9 10" /></svg>;
    if (type === 'paypal') return <span aria-hidden="true" className="relative block h-8 w-8 font-black italic"><span className="absolute left-1 top-0 text-3xl opacity-60">P</span><span className="absolute left-0 top-0 text-3xl">P</span></span>;
    return <span aria-hidden="true" className="text-4xl font-black italic leading-none">S</span>;
}

function Check({ children }) {
    return <span className="inline-flex items-center gap-2"><span className="grid h-5 w-5 place-items-center rounded-full bg-emerald-100 text-[11px] font-bold text-emerald-700">✓</span>{children}</span>;
}

function BuilderMockup() {
    const nav = [
        ['⌂', 'Overview', true],
        ['▣', 'Websites', false],
        ['▥', 'Agency Insights', false],
        ['♙', 'Team', false],
        ['◇', 'Branding', false],
        ['⊕', 'Health', false],
        ['▧', 'Media', false],
    ];

    const stats = [
        ['Visitors', '0', 'Last 30 days', '♙'],
        ['Page views', '0', 'Across 0 tracked sites', '◎'],
        ['Sessions', '0', 'Tracked browsing sessions', '⌁'],
        ['Conversion rate', '0.0%', '0 tracked conversions', '✓'],
    ];

    return (
        <div className="overflow-hidden rounded-[26px] border border-emerald-100 bg-[#f7faf8] shadow-[0_34px_90px_-36px_rgba(2,32,24,.30)]">
            <div className="grid min-h-[610px] md:grid-cols-[190px_1fr] lg:grid-cols-[220px_1fr]">
                <aside className="hidden border-r border-slate-200 bg-white p-4 md:flex md:flex-col">
                    <div className="flex items-center gap-2.5 px-1 pb-5">
                        <span className="grid h-8 w-8 place-items-center rounded-lg bg-emerald-500 text-sm font-bold text-white">✦</span>
                        <div>
                            <p className="text-sm font-bold text-[#07132c]">Cosmic CMS</p>
                            <p className="text-[8px] font-bold uppercase tracking-[.18em] text-slate-400">AI website platform</p>
                        </div>
                        <span className="ml-auto rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-[8px] font-bold text-slate-400">CMS</span>
                    </div>

                    <div className="rounded-xl border border-emerald-100 bg-emerald-50/60 p-3">
                        <p className="text-[8px] font-bold uppercase tracking-[.2em] text-emerald-700">Cosmic credits</p>
                        <div className="mt-1 flex items-center justify-between">
                            <span className="text-[9px] text-slate-400">Available balance</span>
                            <span className="text-sm font-bold text-[#07132c]">⚡ 0</span>
                        </div>
                    </div>

                    <nav className="mt-4 space-y-1.5">
                        {nav.map(([icon, label, active]) => (
                            <div key={label} className={`flex items-center gap-3 rounded-xl px-3 py-2.5 text-[11px] font-semibold ${active ? 'border border-emerald-100 bg-emerald-50 text-emerald-800' : 'text-slate-500'}`}>
                                <span className={`grid h-7 w-7 place-items-center rounded-lg ${active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}>{icon}</span>
                                <span>{label}</span>
                                {active && <span className="ml-auto h-1.5 w-1.5 rounded-full bg-emerald-500" />}
                            </div>
                        ))}
                    </nav>

                    <div className="mt-auto space-y-1.5 border-t border-slate-100 pt-4">
                        {['⚙  Settings', '↪  Log out'].map((item) => <div key={item} className="rounded-lg px-3 py-2 text-[11px] font-semibold text-slate-500">{item}</div>)}
                    </div>
                </aside>

                <div className="bg-[radial-gradient(circle_at_15%_5%,rgba(16,185,129,.06),transparent_30%),#f8fbf9] p-4 sm:p-6 lg:p-8">
                    <div className="mx-auto max-w-[1040px]">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <p className="text-[10px] font-medium text-emerald-700">Cosmic workspace</p>
                                <h3 className="mt-1 text-2xl font-semibold tracking-[-.04em] text-[#07132c] sm:text-3xl">Overview</h3>
                                <p className="mt-1 text-[10px] text-slate-500 sm:text-xs">Your websites, performance, releases, and next steps in one place.</p>
                            </div>
                            <div className="hidden items-center gap-2 sm:flex">
                                <span className="rounded-full border border-slate-200 bg-white px-3 py-2 text-[9px] font-bold text-slate-500">● Updated 1 hour ago</span>
                                <span className="grid h-8 w-8 place-items-center rounded-full border border-slate-200 bg-white text-[11px]">☼</span>
                            </div>
                        </div>

                        <div className="mt-6 grid gap-3 md:grid-cols-3">
                            {[
                                ['New Website', 'Start from a blank canvas', '＋', 'emerald'],
                                ['Browse Starter Kits', 'Explore prebuilt starting points', '▦', 'blue'],
                                ['Open AI Studio', 'Generate content faster', '✦', 'violet'],
                            ].map(([title, copy, icon, tone]) => (
                                <div key={title} className="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-4">
                                    <div className={`grid h-8 w-8 place-items-center rounded-lg text-xs font-bold ${tone === 'emerald' ? 'bg-emerald-100 text-emerald-700' : tone === 'blue' ? 'bg-blue-100 text-blue-600' : 'bg-violet-100 text-violet-600'}`}>{icon}</div>
                                    <p className={`mt-2 text-[11px] font-semibold ${tone === 'emerald' ? 'text-emerald-700' : tone === 'blue' ? 'text-blue-600' : 'text-violet-600'}`}>{title}</p>
                                    <p className="mt-1 text-[9px] text-slate-400">{copy}</p>
                                    <span className="absolute right-4 top-4 text-[10px] text-slate-400">↗</span>
                                </div>
                            ))}
                        </div>

                        <div className="mt-6 flex items-end justify-between">
                            <div>
                                <p className="text-[8px] font-bold uppercase tracking-[.22em] text-emerald-700">Performance</p>
                                <p className="mt-1 text-sm font-bold text-[#07132c]">Your websites at a glance</p>
                            </div>
                            <div className="hidden rounded-xl border border-slate-200 bg-white p-1 text-[8px] font-bold text-slate-400 sm:flex">
                                <span className="px-2 py-1">7 days</span><span className="rounded-lg bg-emerald-50 px-2 py-1 text-emerald-700">30 days</span><span className="px-2 py-1">90 days</span>
                            </div>
                        </div>

                        <div className="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            {stats.map(([label, value, copy, icon]) => (
                                <div key={label} className="rounded-2xl border border-emerald-100 bg-white p-4">
                                    <div className="flex items-start justify-between">
                                        <div><p className="text-[8px] font-bold uppercase tracking-[.14em] text-slate-500">{label}</p><p className="mt-2 text-2xl font-bold text-[#07132c]">{value}</p></div>
                                        <span className="grid h-8 w-8 place-items-center rounded-xl bg-emerald-50 text-xs text-emerald-700">{icon}</span>
                                    </div>
                                    <p className="mt-2 text-[9px] text-slate-400">{copy}</p>
                                </div>
                            ))}
                        </div>

                        <div className="mt-3 grid gap-3 xl:grid-cols-[1.65fr_.85fr]">
                            <div className="rounded-2xl border border-emerald-100 bg-white p-4">
                                <div className="flex items-start justify-between">
                                    <div><p className="text-[11px] font-bold text-[#07132c]">Website traffic</p><p className="mt-1 text-[9px] text-slate-400">Page views across your tracked websites</p></div>
                                    <span className="rounded-full border border-emerald-100 bg-emerald-50 px-2.5 py-1 text-[8px] font-bold text-emerald-700">● Live data</span>
                                </div>
                                <div className="mt-4 grid h-40 place-items-center rounded-xl border border-dashed border-emerald-100 bg-emerald-50/50">
                                    <div className="text-center"><div className="mx-auto grid h-8 w-8 place-items-center rounded-lg bg-emerald-100 text-emerald-700">↗</div><p className="mt-3 text-xs font-bold text-slate-500">Analytics are ready</p><p className="mt-1 text-[9px] text-slate-400">Traffic will appear here as your published websites receive tracked visits.</p></div>
                                </div>
                            </div>
                            <div className="rounded-2xl border border-emerald-100 bg-white p-4">
                                <div className="flex items-start justify-between"><div><p className="text-[11px] font-bold text-[#07132c]">Top websites</p><p className="mt-1 text-[9px] text-slate-400">Ranked by page views</p></div><span className="text-[9px] font-bold text-emerald-700">Insights</span></div>
                                <div className="grid h-40 place-items-center text-center"><div><div className="mx-auto text-xl text-emerald-600">◎</div><p className="mt-3 text-[9px] text-slate-400">Top websites appear after traffic is recorded.</p></div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function Welcome() {
    const [demoModal, setDemoModal] = useState({ open: false, source: 'home', initialPrompt: '' });

    const openFreeDemo = (source = 'home', initialPrompt = '') => {
        trackCosmicEvent('trial_cta_click', { source, flow: 'create_free_demo' });
        setDemoModal({ open: true, source, initialPrompt: String(initialPrompt || '').trim() });
    };

    useEffect(() => {
        const handleLunaDemoRequest = (event) => {
            const detail = event?.detail || {};
            openFreeDemo(detail.source || 'luna_welcome', detail.prompt || '');
        };

        window.addEventListener('cosmic:open-free-demo', handleLunaDemoRequest);
        return () => window.removeEventListener('cosmic:open-free-demo', handleLunaDemoRequest);
    }, []);

    return (
        <>
            <SeoHead
                title="AI Website Builder for Modern Business Websites | Cosmic CMS"
                description="Create modern, responsive business websites with Cosmic CMS. Generate a website with AI, customize it in the builder, and launch faster without starting from scratch."
                path="/"
            />
            <div className="cosmic-public-site cosmic-public-light cosmic-welcome-page min-h-screen overflow-x-clip bg-white font-sans text-[#162238] selection:bg-emerald-100 selection:text-emerald-950">
                <a href="#main-content" className="fixed left-4 top-3 z-[120] -translate-y-20 rounded-xl bg-[#07132c] px-4 py-2.5 text-sm font-bold text-white shadow-xl transition-transform focus:translate-y-0 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2">Skip to content</a>
                <PublicHeader logoSrc={cosmicLogo} logoAlt="Cosmic CMS" />

                <main id="main-content" tabIndex={-1} className="outline-none">
                    <section className="relative overflow-hidden bg-white">
                        <img
                            src={welcomeHeroBg}
                            alt=""
                            aria-hidden="true"
                            className="pointer-events-none absolute inset-0 h-full w-full object-cover object-center"
                        />
                        <div className="pointer-events-none absolute inset-0 bg-gradient-to-r from-white/96 via-white/72 to-transparent lg:via-white/22" />
                        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_16%_18%,rgba(16,185,129,.06),transparent_28%)]" />

                        <div className="relative mx-auto max-w-[1440px] px-5 pb-0 pt-12 sm:px-6 sm:pt-16 lg:px-8 lg:pt-20">
                            <div className="grid gap-10 lg:grid-cols-[.84fr_1.16fr] lg:items-start">
                                <div className="relative z-10 max-w-xl pb-16 lg:pb-24">
                                    <div className="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-white/90 px-4 py-2 text-[11px] font-bold uppercase tracking-[.14em] text-emerald-800 shadow-sm">
                                        <span>✦</span> AI-assisted website creation
                                    </div>
                                    <h1 className="mt-7 text-[46px] font-black leading-[1.01] tracking-[-.04em] text-[#162238] sm:text-6xl lg:text-[64px]">
                                        Build websites
                                        <span className="mt-2 block text-[#21845f]">at launch speed.</span>
                                    </h1>
                                    <p className="mt-6 max-w-lg text-base leading-7 text-slate-700 sm:text-lg sm:leading-8">
                                        Describe your business and Luna builds the first draft. Refine visually, add content, and publish — all in one connected platform.
                                    </p>
                                    <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                                        <button type="button" onClick={() => openFreeDemo('home_hero')} className="inline-flex items-center justify-center rounded-lg bg-emerald-700 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-emerald-200 transition hover:-translate-y-0.5 hover:bg-emerald-800">Create Free Demo <span className="ml-2">→</span></button>
                                        <a href="#workflow" className="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-6 py-3.5 text-sm font-bold text-[#07132c] shadow-sm transition hover:bg-slate-50"><span className="mr-2 grid h-5 w-5 place-items-center rounded-full bg-emerald-600 text-[9px] text-white shadow-sm">▶</span> See how it works</a>
                                    </div>
                                    <div className="mt-7 grid max-w-[520px] grid-cols-1 gap-3 sm:grid-cols-3">
                                        {[['⌁', 'No code', 'required'], ['✦', 'AI-assisted', 'creation'], ['◉', 'Publish in', 'minutes']].map(([icon, title, copy]) => <div key={title} className="flex items-center gap-3 rounded-xl border border-slate-200 bg-white/85 px-4 py-3 shadow-sm backdrop-blur"><span className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-emerald-50 text-xs font-black text-emerald-700">{icon}</span><span className="text-[11px] font-bold leading-4 text-[#162238]">{title}<span className="block font-semibold text-slate-500">{copy}</span></span></div>)}
                                    </div>
                                    <div className="mt-7 flex flex-wrap items-center gap-4 text-xs font-semibold text-slate-500">
                                        <span className="text-lg tracking-[.08em] text-emerald-600">★★★★★</span>
                                        <span>Loved by 2,000+ teams</span>
                                        <span className="flex -space-x-2">{['A','K','M','R'].map((letter, index) => <span key={letter} className="grid h-8 w-8 place-items-center rounded-full border-2 border-white text-[9px] font-black text-white" style={{ background: ['#334155','#0f766e','#7c3aed','#c2410c'][index] }}>{letter}</span>)}</span>
                                        <span className="grid h-9 min-w-9 place-items-center rounded-full bg-emerald-50 px-2 text-[10px] font-black text-emerald-700">2K+</span>
                                    </div>
                                </div>
                                <div className="min-h-[260px] lg:min-h-[470px]" />
                            </div>

                        </div>
                    </section>

                    <section className="border-y border-white/10 bg-[#03112a] py-5 text-white shadow-[0_14px_40px_rgba(3,17,42,.12)]">
                        <div className="mx-auto max-w-[1540px] px-5 sm:px-6 lg:px-8">
                            <p className="text-center text-[11px] font-bold uppercase tracking-[.24em] text-emerald-300 sm:text-xs">A modern workflow built on technology teams already trust</p>
                            <div className="mt-5 grid grid-cols-2 text-slate-100 sm:grid-cols-4 xl:grid-cols-8">
                                {technologies.map(([type, label], index) => <div key={type} className={`flex min-h-14 items-center justify-center gap-3 border-white/10 px-3 py-3 ${index % 2 ? 'border-l' : ''} sm:border-l sm:first:border-l-0`}><TechnologyLogo type={type} /><span className="text-sm font-semibold whitespace-nowrap">{label}</span></div>)}
                            </div>
                        </div>
                    </section>

                    <section className="border-b border-slate-100 bg-white py-10 sm:py-14 lg:py-16">
                        <div className="mx-auto max-w-[1440px] px-5 sm:px-6 lg:px-8">
                            <div className="mx-auto max-w-[1240px]">
                                <BuilderMockup />
                            </div>
                        </div>
                    </section>

                    <section id="features" className="bg-white py-16 sm:py-20">
                        <div className="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
                            <div className="grid gap-8 lg:grid-cols-[.9fr_1.5fr] lg:items-start">
                                <div>
                                    <span className="text-[10px] font-bold uppercase tracking-[.24em] text-emerald-700">One connected platform</span>
                                    <h2 className="mt-3 text-4xl font-bold leading-[1.12] tracking-[-.025em] text-[#162238] sm:text-[44px]">From the first prompt to the published website.</h2>
                                    <p className="mt-5 max-w-md leading-7 text-slate-600">Cosmic combines AI generation, visual CMS, publishing, analytics, leads, and agency operations in one professional workspace.</p>
                                </div>
                                <div className="grid gap-4 md:grid-cols-3">
                                    {featureCards.map(([label, title, description, icon]) => (
                                        <article key={title} className="rounded-2xl border border-slate-200 bg-white p-5">
                                            <div className="grid h-9 w-9 place-items-center rounded-lg bg-emerald-100 font-bold text-emerald-700">{icon}</div>
                                            <p className="mt-5 text-[9px] font-bold uppercase tracking-[.20em] text-emerald-700">{label}</p>
                                            <h3 className="mt-2 text-[17px] font-semibold leading-snug text-[#07132c]">{title}</h3>
                                            <p className="mt-3 text-sm leading-6 text-slate-600">{description}</p>
                                        </article>
                                    ))}
                                </div>
                            </div>

                            <div className="mt-6 grid gap-5 lg:grid-cols-[1.35fr_.65fr]">
                                <div className="relative overflow-hidden rounded-2xl border border-emerald-900/30 bg-gradient-to-r from-[#031a2b] via-[#01352f] to-[#052623] p-7 text-white sm:p-8">
                                    <div className="grid gap-6 lg:grid-cols-[1fr_310px] lg:items-center">
                                        <div><span className="text-[10px] font-bold uppercase tracking-[.20em] text-emerald-300">Built for agencies too</span><h3 className="mt-3 text-[28px] font-semibold leading-[1.08] tracking-[-.025em]">Manage websites, leads, sales, teams, and client access from one dashboard.</h3><p className="mt-4 max-w-2xl text-sm leading-6 text-slate-300">Save time and deliver excellent results with a platform designed for modern agencies.</p></div>
                                        <div className="grid grid-cols-3 gap-3 text-center text-xs font-bold"><div className="rounded-xl border border-white/10 bg-white/5 p-4">10 Sites</div><div className="rounded-xl border border-white/10 bg-white/5 p-4">3 Team Seats</div><div className="rounded-xl border border-white/10 bg-white/5 p-4">White Label</div></div>
                                    </div>
                                </div>
                                <div className="rounded-2xl border border-emerald-100 bg-gradient-to-br from-emerald-50 to-lime-50 p-7 sm:p-8">
                                    <span className="text-[9px] font-bold uppercase tracking-[.22em] text-emerald-700">Account-wide credits</span><h3 className="mt-3 text-3xl font-semibold leading-none tracking-[-.035em] text-[#07132c]">One wallet.<br />Every website.</h3><p className="mt-4 text-sm leading-6 text-slate-600">Use credits for AI generation, content, Sparks, premium features, and more.</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="workflow" className="relative overflow-hidden bg-gradient-to-r from-[#021127] via-[#04233a] to-[#063b38] py-16 text-white sm:py-20">
                        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_82%_18%,rgba(16,185,129,.12),transparent_32%)]" />
                        <div className="relative mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
                            <div className="grid gap-10 lg:grid-cols-[.72fr_1.28fr] lg:items-center">
                                <div><span className="text-[10px] font-bold uppercase tracking-[.18em] text-emerald-300">How it works</span><h2 className="mt-3 text-4xl font-extrabold leading-[1.08] tracking-[-.025em]">A faster path from idea to live website.</h2><p className="mt-4 max-w-md leading-7 text-slate-300">AI handles the repetitive starting work. You stay in control of the content, design, and final result.</p><button type="button" onClick={() => openFreeDemo('home_workflow')} className="mt-6 rounded-lg bg-emerald-600 px-5 py-3 text-sm font-bold text-white">Create Free Demo →</button></div>
                                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                    {workflow.map(([num, title, copy]) => <div key={num} className="border-t border-emerald-400/40 pt-5"><div className="grid h-9 w-9 place-items-center rounded-full border border-emerald-400 bg-emerald-400/10 text-[10px] font-bold text-emerald-300">{num}</div><h3 className="mt-4 text-base font-semibold">{title}</h3><p className="mt-2 text-xs leading-5 text-slate-400">{copy}</p></div>)}
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="bg-white py-16 sm:py-20">
                        <div className="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><span className="text-[10px] font-bold uppercase tracking-[.18em] text-emerald-800">Website builder guides</span><h2 className="mt-3 text-3xl font-extrabold leading-tight tracking-[-.025em] text-[#162238] sm:text-4xl">Explore the right way to build with AI.</h2><p className="mt-3 max-w-2xl text-sm leading-6 text-slate-700">Learn new approaches to AI website generation, modern visual editing, no-code workflows, and smart business website creation.</p></div><Link href="/ai-website-builder" className="text-sm font-bold text-emerald-800">View all guides →</Link></div>
                            <div className="mt-8 grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                                {guides.map(([href, title]) => (
                                    <Link
                                        key={href}
                                        href={href}
                                        className="cosmic-welcome-guide-card group flex min-h-[76px] items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4 transition hover:border-emerald-300 hover:bg-emerald-50/40"
                                    >
                                        <span className="flex min-w-0 items-center gap-3">
                                            <span className="cosmic-welcome-guide-icon grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-emerald-100 text-base font-bold">✦</span>
                                            <span className="cosmic-welcome-guide-title truncate text-sm font-bold">{title}</span>
                                        </span>
                                        <span className="cosmic-welcome-guide-cta shrink-0 text-xs font-bold">Read guide →</span>
                                    </Link>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section id="pricing" className="border-t border-slate-100 bg-white pb-16 sm:pb-20">
                        <div className="mx-auto max-w-[1440px] px-5 pt-12 sm:px-6 lg:px-8">
                            <div className="text-center"><span className="text-[10px] font-bold uppercase tracking-[.18em] text-emerald-800">Simple monthly plans</span><h2 className="mt-3 text-4xl font-extrabold leading-tight tracking-[-.025em] text-[#162238] sm:text-[42px]">Start with one website. Scale when you are ready.</h2><p className="mx-auto mt-3 max-w-2xl text-sm leading-6 text-slate-700">Choose a plan based on how many pages, Sparks, marketing tools, and client websites you need.</p></div>
                            <div className="mx-auto mt-10 grid max-w-6xl gap-5 lg:grid-cols-3">
                                {plans.map(([name, price, desc, items], index) => <article key={name} className={`relative rounded-2xl border p-7 sm:p-8 ${index === 1 ? 'border-emerald-400 bg-emerald-50/60 shadow-xl shadow-emerald-100' : 'border-slate-200 bg-white shadow-sm'}`}>{index === 1 && <span className="absolute right-4 top-4 rounded-full bg-emerald-600 px-2.5 py-1 text-[8px] font-bold uppercase tracking-[.14em] text-white">Most popular</span>}<h3 className="text-base font-semibold text-[#07132c]">{name}</h3><div className="mt-2 flex items-end gap-1"><span className="text-5xl font-bold tracking-[-.05em] text-[#07132c]">{price}</span><span className="pb-1 text-xs text-slate-500">/month</span></div><p className="mt-4 min-h-[52px] text-sm leading-6 text-slate-600">{desc}</p><ul className="mt-6 space-y-2.5 text-sm font-semibold text-slate-700">{items.map((item) => <li key={item} className="flex gap-2"><span className="text-emerald-600">✓</span>{item}</li>)}</ul><Link href="/pricing" className={`mt-7 flex items-center justify-center rounded-lg px-4 py-3.5 text-sm font-bold ${index === 1 ? 'bg-emerald-700 text-white' : 'border border-slate-300 text-[#07132c]'}`}>Choose {name}</Link></article>)}
                            </div>
                        </div>
                    </section>

                    <section className="px-5 pb-14 sm:px-6 sm:pb-16 lg:px-8">
                        <div className="relative mx-auto max-w-7xl overflow-hidden rounded-2xl bg-[#03112a] px-7 py-10 text-white sm:px-10 sm:py-12">
                            <img src={welcomeHeroBg} alt="" aria-hidden="true" loading="lazy" decoding="async" className="absolute inset-y-0 right-0 h-full w-[56%] object-cover object-right opacity-75" />
                            <div className="absolute inset-0 bg-gradient-to-r from-[#03112a] via-[#03112a]/95 to-[#03112a]/15" />
                            <div className="relative max-w-[650px] pr-3 sm:pr-8 lg:pr-16"><span className="text-[9px] font-bold uppercase tracking-[.18em] text-emerald-300">Your next website can start today</span><h2 className="mt-3 max-w-[580px] text-3xl font-extrabold leading-[1.1] tracking-[-.025em] sm:text-4xl">Ready to launch your next website?</h2><p className="mt-4 max-w-[560px] text-sm leading-6 text-slate-300">Turn a short business description into a complete, editable website in minutes.</p><div className="mt-6 grid gap-3 sm:flex"><button type="button" onClick={() => openFreeDemo('home_final_cta')} className="min-h-12 rounded-lg bg-emerald-600 px-5 py-3 text-sm font-bold">Create Free Demo →</button><Link href="/pricing" className="inline-flex min-h-12 items-center justify-center rounded-lg border border-white/25 bg-white/10 px-5 py-3 text-sm font-bold">View plans</Link></div></div>
                        </div>
                    </section>
                </main>

                <PublicFooter />
            </div>

            <CreateFreeDemoModal
                open={demoModal.open}
                source={demoModal.source}
                initialPrompt={demoModal.initialPrompt}
                onClose={() => setDemoModal((current) => ({ ...current, open: false }))}
            />
        </>
    );
}
