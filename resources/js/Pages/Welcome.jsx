import { Link } from '@inertiajs/react';
import SeoHead from '@/Components/Seo/SeoHead';
import PublicHeader from '@/Components/Public/PublicHeader';
import { trackCosmicEvent } from '@/Analytics/tracking';

const features = [
    {
        label: 'AI planning',
        title: 'Start with a business idea, not a blank canvas',
        description: 'Cosmic plans the page structure, chooses suitable Sparks, and creates an editable first draft around your business.',
        icon: '✦',
    },
    {
        label: 'Visual builder',
        title: 'Edit every section without touching code',
        description: 'Update copy, images, calls to action, layouts, headers, and footers through a focused visual workflow.',
        icon: '◫',
    },
    {
        label: 'Publishing',
        title: 'Launch fast, lightweight websites',
        description: 'Preview responsively, publish with confidence, and export clean static output built for speed and portability.',
        icon: '↗',
    },
];

const workflow = [
    ['01', 'Describe your business', 'Tell Cosmic what you offer, who you serve, and the style you want.'],
    ['02', 'Generate a complete starting point', 'AI selects the structure, content direction, imagery, and theme.'],
    ['03', 'Refine in the builder', 'Edit every Spark, page, image, and global website setting.'],
    ['04', 'Preview and publish', 'Review desktop, tablet, and mobile output before going live.'],
];

const plans = [
    {
        name: 'Starter',
        price: '$49',
        description: 'For a small business launching one polished website.',
        features: ['1 website', 'Posts / Updates included', '15 active Sparks', 'AI website generation', 'Basic SEO controls'],
    },
    {
        name: 'Growth',
        price: '$79',
        description: 'For a growing business that needs commerce and stronger marketing tools.',
        features: ['1 website', 'Full commerce store', '30 active Sparks', 'Lead history and analytics', 'Premium Spark purchasing'],
        featured: true,
    },
    {
        name: 'Agency',
        price: '$99',
        description: 'For freelancers managing multiple client websites.',
        features: ['Up to 3 websites', 'Agency workspace', 'Client-ready previews', 'Per-site analytics', 'Shared Cosmic Credits'],
    },
];

function CheckIcon() {
    return (
        <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-black text-emerald-700">
            ✓
        </span>
    );
}

export default function Welcome() {

    return (
        <>
            <SeoHead
                title="AI Website Builder for Modern Business Websites | Cosmic CMS"
                description="Create modern, responsive business websites with Cosmic CMS. Generate a website with AI, customize it in the builder, and launch faster without starting from scratch."
                path="/"
                schema={[
                    {
                        '@context': 'https://schema.org',
                        '@type': 'Organization',
                        name: 'Cosmic CMS',
                        url: 'https://www.cosmiccms.com/',
                    },
                    {
                        '@context': 'https://schema.org',
                        '@type': 'SoftwareApplication',
                        name: 'Cosmic CMS',
                        applicationCategory: 'BusinessApplication',
                        operatingSystem: 'Web',
                        url: 'https://www.cosmiccms.com/',
                        description: 'AI website builder for generating and customizing modern business websites.',
                    },
                ]}
            />

            <div className="cosmic-public-light cosmic-welcome-page min-h-screen bg-white text-slate-900 selection:bg-emerald-100 selection:text-emerald-950">
                <PublicHeader />

                <main>
                    <section className="relative overflow-hidden border-b border-slate-200 bg-[linear-gradient(180deg,#fbfffc_0%,#f6fcf8_48%,#ffffff_100%)]">
                        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,rgba(16,185,129,0.055)_1px,transparent_0)] bg-[size:28px_28px]">
                            <div className="absolute left-1/2 top-[-320px] h-[640px] w-[900px] -translate-x-1/2 rounded-full bg-emerald-100/45 blur-3xl" />
                            <div className="absolute right-[-180px] top-36 h-96 w-96 rounded-full bg-lime-50/60 blur-3xl" />
                            <div className="absolute left-[-220px] top-96 h-96 w-96 rounded-full bg-teal-50/55 blur-3xl" />
                        </div>

                        <div className="relative mx-auto max-w-7xl px-5 pb-20 pt-20 sm:px-6 sm:pb-24 sm:pt-24 lg:px-8 lg:pb-28 lg:pt-28">
                            <div className="mx-auto max-w-4xl text-center">
                                <div className="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-white px-4 py-2 text-sm font-bold text-emerald-700 shadow-sm">
                                    <span className="h-2 w-2 rounded-full bg-emerald-500" />
                                    AI-assisted website creation, end to end
                                </div>

                                <h1 className="mt-8 text-5xl font-black leading-[1.10] tracking-[-0.045em] text-slate-950 sm:text-6xl lg:text-7xl">
                                    Build a professional website
                                    <span className="block pb-2 bg-gradient-to-r from-emerald-700 via-green-600 to-teal-600 bg-clip-text text-transparent">without starting from zero.</span>
                                </h1>

                                <p className="mx-auto mt-7 max-w-2xl text-lg leading-8 text-slate-600 sm:text-xl">
                                    Describe the business once. Cosmic plans the pages, writes the first draft, selects suitable imagery, and gives you a polished website you can refine before publishing.
                                </p>

                                <div className="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row">
                                    <Link href="/start" onClick={() => trackCosmicEvent('trial_cta_click', { source: 'home_hero' })} className="inline-flex w-full items-center justify-center rounded-xl bg-emerald-700 px-7 py-3.5 text-base font-black text-white shadow-lg shadow-emerald-200 transition hover:-translate-y-0.5 hover:bg-emerald-800 sm:w-auto">
                                        Generate a free concept
                                        <span className="ml-2">→</span>
                                    </Link>
                                    <a href="/#workflow" className="inline-flex w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-7 py-3.5 text-base font-black text-slate-800 shadow-sm transition hover:border-slate-400 hover:bg-slate-50 sm:w-auto">
                                        See how it works
                                    </a>
                                </div>

                                <div className="mt-8 flex flex-wrap items-center justify-center gap-x-6 gap-y-3 text-sm font-semibold text-slate-500">
                                    <span className="flex items-center gap-2"><CheckIcon /> No blank canvas</span>
                                    <span className="flex items-center gap-2"><CheckIcon /> Fully editable</span>
                                    <span className="flex items-center gap-2"><CheckIcon /> Responsive output</span>
                                </div>
                            </div>

                            <div className="relative mx-auto mt-16 max-w-6xl">
                                <div className="absolute inset-x-16 bottom-[-24px] h-28 rounded-full bg-emerald-200/70 blur-3xl" />
                                <div className="relative overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-[0_32px_90px_-32px_rgba(15,23,42,0.28)]">
                                    <div className="flex items-center gap-4 border-b border-slate-200 bg-slate-50 px-5 py-4">
                                        <div className="flex gap-1.5">
                                            <span className="h-3 w-3 rounded-full bg-rose-300" />
                                            <span className="h-3 w-3 rounded-full bg-amber-300" />
                                            <span className="h-3 w-3 rounded-full bg-emerald-300" />
                                        </div>
                                        <div className="mx-auto rounded-lg border border-slate-200 bg-white px-5 py-2 text-xs font-semibold text-slate-400 shadow-sm">
                                            app.cosmiccms.dev/builder
                                        </div>
                                    </div>

                                    <div className="grid min-h-[560px] lg:grid-cols-[240px_1fr]">
                                        <aside className="hidden border-r border-slate-200 bg-slate-950 p-5 text-white lg:block">
                                            <div className="flex items-center gap-3 border-b border-white/10 pb-5">
                                                <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-500 font-black">✦</span>
                                                <div>
                                                    <p className="text-sm font-black">North & Co.</p>
                                                    <p className="text-xs text-slate-400">Homepage</p>
                                                </div>
                                            </div>
                                            <p className="mt-6 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">Page sections</p>
                                            <div className="mt-3 space-y-2">
                                                {['Hero', 'Services', 'Why choose us', 'Process', 'Testimonials', 'Contact'].map((item, index) => (
                                                    <div key={item} className={`rounded-lg px-3 py-2.5 text-sm font-semibold ${index === 0 ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:bg-white/5'}`}>
                                                        {item}
                                                    </div>
                                                ))}
                                            </div>
                                        </aside>

                                        <div className="bg-slate-100 p-4 sm:p-6 lg:p-8">
                                            <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                                                <div>
                                                    <p className="text-sm font-black text-slate-900">Homepage</p>
                                                    <p className="text-xs font-medium text-slate-500">All changes saved</p>
                                                </div>
                                                <div className="flex gap-2">
                                                    <span className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600">Preview</span>
                                                    <span className="rounded-lg bg-slate-950 px-3 py-2 text-xs font-bold text-white">Publish</span>
                                                </div>
                                            </div>

                                            <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                                                <div className="grid items-center gap-8 px-7 py-10 sm:px-10 lg:grid-cols-[1.05fr_.95fr] lg:px-12 lg:py-14">
                                                    <div>
                                                        <span className="inline-flex rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-black uppercase tracking-[0.14em] text-emerald-700">Built for growing businesses</span>
                                                        <div className="mt-5 h-8 w-full max-w-md rounded-lg bg-slate-900" />
                                                        <div className="mt-3 h-8 w-4/5 rounded-lg bg-slate-900" />
                                                        <div className="mt-5 h-3 w-full max-w-lg rounded bg-slate-200" />
                                                        <div className="mt-2 h-3 w-4/5 rounded bg-slate-200" />
                                                        <div className="mt-7 flex gap-3">
                                                            <div className="h-11 w-32 rounded-lg bg-emerald-600" />
                                                            <div className="h-11 w-28 rounded-lg border border-slate-300 bg-white" />
                                                        </div>
                                                    </div>
                                                    <div className="relative h-64 overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-100 via-green-50 to-lime-100">
                                                        <div className="absolute inset-7 rounded-2xl border border-white/80 bg-white/75 shadow-xl backdrop-blur">
                                                            <div className="grid h-full grid-cols-2 gap-3 p-4">
                                                                <div className="rounded-xl bg-emerald-600" />
                                                                <div className="grid gap-3">
                                                                    <div className="rounded-xl bg-slate-900" />
                                                                    <div className="rounded-xl bg-lime-300" />
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div className="grid gap-4 border-t border-slate-100 bg-slate-50 p-5 sm:grid-cols-3 sm:p-7">
                                                    {['Strategy-led sections', 'Brand-ready styling', 'Responsive by default'].map((item) => (
                                                        <div key={item} className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                                            <div className="h-9 w-9 rounded-lg bg-emerald-100" />
                                                            <p className="mt-4 text-sm font-black text-slate-900">{item}</p>
                                                            <div className="mt-2 h-2.5 w-full rounded bg-slate-100" />
                                                            <div className="mt-2 h-2.5 w-3/4 rounded bg-slate-100" />
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="border-b border-slate-200 bg-white py-14">
                        <div className="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
                            <p className="text-center text-xs font-black uppercase tracking-[0.24em] text-slate-400">A modern workflow built on technology teams already trust</p>
                            <div className="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                                {['Cosmic CMS', 'React', 'Tailwind', 'Inertia', 'PayPal', 'Static HTML'].map((item) => (
                                    <div key={item} className="flex h-14 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-sm font-black text-slate-500">
                                        {item}
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section id="features" data-perf="deferred" className="bg-white py-16 sm:py-20 lg:py-24">
                        <div className="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
                            <div className="max-w-3xl">
                                <span className="text-sm font-black uppercase tracking-[0.2em] text-emerald-700">One connected platform</span>
                                <h2 className="mt-4 text-4xl font-black tracking-[-0.035em] text-slate-950 sm:text-5xl">From the first prompt to the published website.</h2>
                                <p className="mt-5 text-lg leading-8 text-slate-600">Cosmic combines AI generation, a visual CMS, publishing, analytics, leads, and agency operations in one professional workspace.</p>
                            </div>

                            <div className="mt-14 grid gap-6 lg:grid-cols-3">
                                {features.map((feature) => (
                                    <article key={feature.title} className="group flex h-full flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:border-emerald-200 hover:shadow-xl hover:shadow-slate-200/60 sm:p-7">
                                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100 text-xl font-black text-emerald-700 transition group-hover:bg-emerald-700 group-hover:text-white">{feature.icon}</div>
                                        <p className="mt-7 text-xs font-black uppercase tracking-[0.18em] text-emerald-700">{feature.label}</p>
                                        <h3 className="mt-3 text-2xl font-black tracking-tight text-slate-950">{feature.title}</h3>
                                        <p className="mt-4 leading-7 text-slate-600">{feature.description}</p>
                                    </article>
                                ))}
                            </div>

                            <div className="mt-6 grid gap-6 lg:grid-cols-[1.35fr_.65fr]">
                                <div className="overflow-hidden rounded-3xl border border-emerald-200 bg-gradient-to-br from-emerald-700 via-green-700 to-teal-700 p-8 text-white shadow-xl shadow-emerald-200/60 sm:p-10">
                                    <div className="grid items-end gap-8 lg:grid-cols-[1fr_280px]">
                                        <div>
                                            <span className="inline-flex rounded-full bg-white/10 px-3 py-1.5 text-xs font-black uppercase tracking-[0.16em] text-emerald-100">Built for agencies too</span>
                                            <h3 className="mt-5 text-3xl font-black tracking-tight sm:text-4xl">Manage websites, leads, sales, teams, and client access from one dashboard.</h3>
                                            <p className="mt-5 max-w-2xl leading-7 text-slate-300">Scale from one business website to a white-labelled agency operation without replacing your platform.</p>
                                        </div>
                                        <div className="grid grid-cols-2 gap-3">
                                            {['10 sites', '3 team roles', 'Lead inbox', 'White label'].map((item) => (
                                                <div key={item} className="rounded-xl border border-white/10 bg-white/5 p-4 text-sm font-bold text-slate-200">{item}</div>
                                            ))}
                                        </div>
                                    </div>
                                </div>

                                <div className="rounded-3xl border border-slate-200 bg-gradient-to-br from-emerald-50 to-lime-50 p-8 sm:p-10">
                                    <p className="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Account-wide credits</p>
                                    <p className="mt-4 text-5xl font-black tracking-tight text-slate-950">One wallet.</p>
                                    <p className="mt-2 text-2xl font-black text-slate-500">Every website.</p>
                                    <p className="mt-5 leading-7 text-slate-600">Use Cosmic Credits across AI actions, Spark purchases, themes, and all websites in your workspace.</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="workflow" data-perf="deferred" className="border-y border-slate-200 bg-slate-50 py-16 sm:py-20 lg:py-24">
                        <div className="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
                            <div className="grid gap-14 lg:grid-cols-[.8fr_1.2fr] lg:items-start">
                                <div className="lg:sticky lg:top-28">
                                    <span className="text-sm font-black uppercase tracking-[0.2em] text-emerald-700">How it works</span>
                                    <h2 className="mt-4 text-4xl font-black tracking-[-0.035em] text-slate-950 sm:text-5xl">A faster path from idea to live website.</h2>
                                    <p className="mt-5 text-lg leading-8 text-slate-600">AI handles the repetitive starting work. You stay in control of the content, design, and final result.</p>
                                    <Link href="/start" onClick={() => trackCosmicEvent('trial_cta_click', { source: 'home_workflow' })} className="mt-8 inline-flex items-center rounded-xl bg-slate-950 px-6 py-3.5 text-sm font-black text-white transition hover:bg-emerald-700">Try the workflow <span className="ml-2">→</span></Link>
                                </div>

                                <div className="space-y-4">
                                    {workflow.map(([number, title, description]) => (
                                        <div key={number} className="grid gap-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:grid-cols-[64px_1fr] sm:p-7">
                                            <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-emerald-100 text-sm font-black text-emerald-700">{number}</div>
                                            <div>
                                                <h3 className="text-xl font-black text-slate-950">{title}</h3>
                                                <p className="cosmic-welcome-guide-copy mt-2 leading-7 text-slate-600">{description}</p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="border-b border-slate-200 bg-white py-16 sm:py-20" aria-labelledby="website-builder-guides-heading">
                        <div className="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
                            <div className="max-w-3xl">
                                <span className="text-sm font-black uppercase tracking-[0.2em] text-emerald-700">Website builder guides</span>
                                <h2 id="website-builder-guides-heading" className="mt-4 text-3xl font-black tracking-[-0.035em] text-slate-950 sm:text-4xl">Explore the right way to build your website with AI.</h2>
                                <p className="mt-5 text-lg leading-8 text-slate-600">Learn how Cosmic CMS approaches AI-assisted website generation, modern visual editing, no-code workflows, and small-business website creation.</p>
                            </div>
                            <div className="mt-10 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                {[
                                    ['/ai-website-builder', 'AI Website Builder', 'Generate a structured, editable business website with AI assistance.'],
                                    ['/ai-website-generator', 'AI Website Generator', 'Turn a short business description into a responsive website starting point.'],
                                    ['/modern-website-builder', 'Modern Website Builder', 'Build responsive, conversion-focused pages with a modern editing workflow.'],
                                    ['/website-builder-for-small-business', 'Small Business Website Builder', 'Create a professional website designed around small-business needs.'],
                                    ['/no-code-website-builder', 'No-Code Website Builder', 'Customize content and design visually without starting from code.'],
                                ].map(([href, title, description]) => (
                                    <Link key={href} href={href} className="cosmic-welcome-guide-card group flex min-h-[190px] flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-300 hover:bg-emerald-50/60 hover:shadow-lg">
                                        <h3 className="cosmic-welcome-guide-title text-lg font-black text-slate-950 group-hover:text-emerald-800">{title}</h3>
                                        <p className="mt-2 leading-7 text-slate-600">{description}</p>
                                        <span className="cosmic-welcome-guide-cta mt-auto inline-flex pt-4 text-sm font-black text-emerald-700">Read guide →</span>
                                    </Link>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section id="pricing" className="bg-white py-16 sm:py-20 lg:py-24">
                        <div className="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
                            <div className="mx-auto max-w-3xl text-center">
                                <span className="text-sm font-black uppercase tracking-[0.2em] text-emerald-700">Simple monthly plans</span>
                                <h2 className="mt-4 text-4xl font-black tracking-[-0.035em] text-slate-950 sm:text-5xl">Start with one website. Scale when you are ready.</h2>
                                <p className="mt-5 text-lg leading-8 text-slate-600">Choose a plan based on how many pages, Sparks, marketing tools, and client websites you need.</p>
                            </div>

                            <div className="mt-14 grid gap-6 lg:grid-cols-3">
                                {plans.map((plan) => (
                                    <article key={plan.name} className={`relative flex h-full flex-col rounded-3xl border p-7 sm:p-8 ${plan.featured ? 'border-emerald-300 bg-emerald-50/60 shadow-xl shadow-emerald-100' : 'border-slate-200 bg-white shadow-sm'}`}>
                                        {plan.featured && <span className="absolute right-6 top-6 rounded-full bg-emerald-700 px-3 py-1 text-[11px] font-black uppercase tracking-[0.12em] text-white">Most popular</span>}
                                        <h3 className="text-xl font-black text-slate-950">{plan.name}</h3>
                                        <div className="mt-5 flex items-end gap-2">
                                            <span className="text-5xl font-black tracking-tight text-slate-950">{plan.price}</span>
                                            <span className="pb-1 text-sm font-bold text-slate-500">/ month</span>
                                        </div>
                                        <p className="mt-4 min-h-[56px] leading-7 text-slate-600">{plan.description}</p>
                                        <ul className="mt-7 flex-1 space-y-3">
                                            {plan.features.map((feature) => (
                                                <li key={feature} className="flex items-center gap-3 text-sm font-semibold text-slate-700"><CheckIcon />{feature}</li>
                                            ))}
                                        </ul>
                                        <Link href="/pricing" className={`mt-8 flex w-full items-center justify-center rounded-xl px-5 py-3.5 text-sm font-black transition ${plan.featured ? 'bg-emerald-700 text-white hover:bg-emerald-800' : 'border border-slate-300 bg-white text-slate-900 hover:border-slate-400 hover:bg-slate-50'}`}>
                                            Choose {plan.name}
                                        </Link>
                                    </article>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section className="px-5 pb-16 sm:px-6 sm:pb-20 lg:px-8">
                        <div className="relative mx-auto max-w-7xl overflow-hidden rounded-[32px] border border-emerald-200 bg-gradient-to-br from-emerald-50 via-green-50 to-lime-50 px-6 py-14 text-center text-slate-950 shadow-2xl shadow-emerald-100 sm:px-10 sm:py-16">
                            <div className="absolute left-1/2 top-[-220px] h-96 w-96 -translate-x-1/2 rounded-full bg-emerald-500/30 blur-3xl" />
                            <div className="relative mx-auto max-w-3xl">
                                <span className="text-sm font-black uppercase tracking-[0.2em] text-emerald-700">Your next website can start today</span>
                                <h2 className="mt-4 text-4xl font-black tracking-[-0.035em] sm:text-5xl">Turn a short business description into an editable website concept.</h2>
                                <p className="mx-auto mt-5 max-w-2xl text-lg leading-8 text-slate-600">Build the starting point with AI, refine it visually, and keep full control of what gets published.</p>
                                <div className="mt-9 flex flex-col justify-center gap-3 sm:flex-row">
                                    <Link href="/start" onClick={() => trackCosmicEvent('trial_cta_click', { source: 'home_final_cta' })} className="rounded-xl bg-emerald-700 px-7 py-3.5 text-base font-black text-white shadow-lg shadow-emerald-200 transition hover:bg-emerald-800">Generate a free concept</Link>
                                    <Link href="/pricing" className="cosmic-welcome-view-plans rounded-xl border border-emerald-300 bg-white px-7 py-3.5 text-base font-black text-emerald-800 transition hover:bg-emerald-50">View plans</Link>
                                </div>
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="border-t border-slate-200 bg-slate-50">
                    <div className="mx-auto flex max-w-7xl flex-col gap-8 px-5 py-10 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
                        <div className="flex items-center gap-3">
                            <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-emerald-600 to-green-700 font-black text-white">✦</span>
                            <div>
                                <p className="font-black text-slate-950">Cosmic CMS</p>
                                <p className="text-sm text-slate-500">AI-assisted website creation for businesses and agencies.</p>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-x-6 gap-y-3 text-sm font-semibold text-slate-500">
                            <Link href="/terms" className="hover:text-slate-950">Terms</Link>
                            <Link href="/privacy" className="hover:text-slate-950">Privacy</Link>
                            <Link href="/cookies" className="hover:text-slate-950">Cookies</Link>
                            <span>© {new Date().getFullYear()} Cosmic CMS</span>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
