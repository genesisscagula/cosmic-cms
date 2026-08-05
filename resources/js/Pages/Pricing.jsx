import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const planFamilies = {
    personal: [
        {
            key: 'starter',
            name: 'Starter',
            price: 49,
            positioning: 'For an individual, freelancer, or small business building one complete website.',
            credits: '150 credits / month',
            highlight: false,
            features: [
                '1 website',
                '5 core pages',
                'Up to 15 active Sparks',
                '5 Starter templates',
                '5 free Owned Sparks',
                'AI website, content, and image selection',
                'Header and footer builder',
                'Contact form and basic blog',
                'Static export and publishing',
                'Basic SEO and responsive output',
            ],
        },
        {
            key: 'growth',
            name: 'Growth',
            price: 79,
            positioning: 'For a growing business that needs more pages, AI usage, and marketing tools.',
            credits: '350 credits / month',
            highlight: true,
            badge: 'Most popular',
            features: [
                'Everything in Starter',
                'Up to 10 standard pages',
                'Up to 30 active Sparks',
                '10 templates and 10 free Owned Sparks',
                'Growth-tier and premium Spark access',
                'Landing pages and enhanced blog tools',
                'Lead history and form management',
                'Website duplication as draft',
                'Enhanced SEO and tracking scripts',
                'Basic traffic analytics',
            ],
        },
        {
            key: 'pro',
            name: 'Pro',
            price: 129,
            positioning: 'For a serious business or creator that wants the complete single-site experience.',
            credits: '750 credits / month',
            highlight: false,
            features: [
                'Everything in Growth',
                'Fair-use unlimited pages',
                'All Personal templates',
                '15 free Owned Sparks',
                'All Personal Spark tiers',
                'Premium AI layouts and generation',
                'Full lead and sales tracking',
                'Advanced analytics and SEO',
                'Version history and snapshots',
                'Remove “Built with Cosmic” branding',
            ],
        },
    ],
    agency: [
        {
            key: 'agency_starter',
            name: 'Starter Agency',
            price: 99,
            positioning: 'For a solo freelancer managing a small portfolio of client websites.',
            credits: '500 credits / month',
            highlight: false,
            features: [
                'Up to 3 websites',
                '3 curated Agency templates',
                '5 free Owned Sparks',
                'Website cloning',
                'Client website organization',
                'Website search and filtering',
                'Per-website lead inbox',
                'Per-website analytics summary',
                'Client-ready preview links',
                'Basic agency workspace',
            ],
        },
        {
            key: 'agency_growth',
            name: 'Growth Agency',
            price: 199,
            positioning: 'For a growing agency managing multiple active clients and team workflows.',
            credits: '1,500 credits / month',
            highlight: true,
            badge: 'Best for agencies',
            features: [
                'Everything in Starter Agency',
                'Up to 10 websites',
                '10 Agency templates',
                '10 free Owned Sparks',
                'Agency Insights dashboard',
                'Aggregated analytics and leads',
                'Website and date filtering',
                'Shared Sparks and templates',
                'Basic white-label controls',
                'Up to 3 team members',
            ],
        },
        {
            key: 'agency_pro',
            name: 'Pro Agency',
            price: 399,
            positioning: 'A complete operating system for professional agencies.',
            credits: '5,000 credits / month',
            highlight: false,
            features: [
                'Everything in Growth Agency',
                'Unlimited websites under fair use',
                'All templates and Sparks',
                'Full Agency Insights',
                'Aggregated analytics, leads, and sales',
                'Revenue and conversion reporting',
                'Advanced white labeling',
                'Client accounts and website handoff',
                'Up to 10 team members',
                'API access and webhooks',
            ],
        },
    ],
};

const comparisonRows = [
    ['Websites', '1', '1', '1', '3', '10', 'Unlimited'],
    ['Pages', '5', '10', 'Fair-use unlimited', 'Per-site limits', 'Per-site limits', 'Fair-use unlimited'],
    ['Templates', '5', '10', 'All Personal', '3 Agency', '10 Agency', 'All'],
    ['Free Owned Sparks', '5', '10', '15', '5', '10', '15'],
    ['Marketplace Sparks', 'Free tier', 'Growth tier', 'All Personal', 'Starter tier', 'Growth tier', 'All'],
    ['Analytics', 'Basic', 'Standard', 'Advanced', 'Per website', 'Aggregated', 'Full Agency'],
    ['Leads', 'Form inbox', 'Lead history', 'Full leads', 'Per website', 'Aggregated', 'Full Agency'],
    ['Sales', '—', 'Basic events', 'Full website sales', '—', 'Summary', 'Full Agency'],
    ['Team members', '0', '0', '0', '0', '3', '10'],
    ['White label', '—', '—', 'Branding removed', '—', 'Basic', 'Full'],
    ['API access', '—', '—', '—', '—', '—', 'Yes'],
    ['Monthly credits', '150', '350', '750', '500', '1,500', '5,000'],
];

function CheckIcon() {
    return <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-black text-emerald-700">✓</span>;
}

export default function Pricing() {
    const [family, setFamily] = useState('personal');
    const plans = useMemo(() => planFamilies[family], [family]);

    return (
        <>
            <Head title="Pricing | Cosmic CMS" />
            <div className="min-h-screen bg-[#fbfefc] text-slate-900 selection:bg-emerald-100 selection:text-emerald-950">
                <header className="sticky top-0 z-50 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl">
                    <div className="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 sm:px-6 lg:px-8">
                        <Link href="/" className="flex items-center gap-3">
                            <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-600 to-green-700 text-lg font-black text-white shadow-sm shadow-emerald-200">✦</span>
                            <span>
                                <span className="block text-lg font-black tracking-tight text-slate-950">Cosmic CMS</span>
                                <span className="block text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">AI website platform</span>
                            </span>
                        </Link>
                        <div className="flex items-center gap-2 sm:gap-3">
                            <Link href="/login" className="rounded-lg px-3 py-2 text-sm font-bold text-slate-600 transition hover:bg-slate-100 hover:text-slate-950 sm:px-4">Log in</Link>
                            <Link href="/register?plan=growth" className="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-800 sm:px-5">Get started</Link>
                        </div>
                    </div>
                </header>

                <main>
                    <section className="relative overflow-hidden border-b border-emerald-100 bg-[linear-gradient(180deg,#fbfffc_0%,#f3fbf6_62%,#ffffff_100%)] px-5 pb-16 pt-20 text-center sm:px-6 sm:pb-20 sm:pt-24">
                        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,rgba(16,185,129,0.045)_1px,transparent_0)] bg-[size:28px_28px]" />
                        <div className="relative mx-auto max-w-4xl">
                            <span className="inline-flex rounded-full border border-emerald-200 bg-white px-4 py-2 text-sm font-bold text-emerald-700 shadow-sm">Simple monthly pricing</span>
                            <h1 className="mt-7 text-4xl font-black tracking-[-0.04em] text-slate-950 sm:text-6xl">Choose the plan that fits how you build.</h1>
                            <p className="mx-auto mt-6 max-w-2xl text-lg leading-8 text-slate-600">Personal plans are designed for one business website. Agency plans unlock multi-website management, teams, client access, insights, and white labeling.</p>

                            <div className="mx-auto mt-9 inline-flex rounded-2xl border border-slate-200 bg-white p-1.5 shadow-sm" role="tablist" aria-label="Pricing family">
                                {['personal', 'agency'].map((item) => (
                                    <button
                                        key={item}
                                        type="button"
                                        role="tab"
                                        aria-selected={family === item}
                                        onClick={() => setFamily(item)}
                                        className={`min-w-32 rounded-xl px-6 py-3 text-sm font-black capitalize transition ${family === item ? 'bg-emerald-700 text-white shadow-md shadow-emerald-100' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950'}`}
                                    >
                                        {item}
                                    </button>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section className="px-5 py-16 sm:px-6 sm:py-20 lg:px-8">
                        <div className="mx-auto grid max-w-7xl gap-6 lg:grid-cols-3">
                            {plans.map((plan) => (
                                <article key={plan.key} className={`relative flex h-full flex-col rounded-[28px] border bg-white p-7 shadow-sm ${plan.highlight ? 'border-emerald-400 shadow-[0_24px_70px_-36px_rgba(5,150,105,0.45)] lg:-translate-y-2' : 'border-slate-200'}`}>
                                    {plan.badge && <span className="absolute right-6 top-6 rounded-full bg-emerald-100 px-3 py-1 text-xs font-black uppercase tracking-wider text-emerald-700">{plan.badge}</span>}
                                    <p className="text-sm font-black uppercase tracking-[0.14em] text-emerald-700">{family === 'personal' ? 'Personal' : 'Agency'}</p>
                                    <h2 className="mt-3 text-2xl font-black text-slate-950">{plan.name}</h2>
                                    <div className="mt-5 flex items-end gap-2"><span className="text-5xl font-black tracking-tight text-slate-950">${plan.price}</span><span className="pb-1.5 font-semibold text-slate-500">/month</span></div>
                                    <p className="mt-5 min-h-20 leading-7 text-slate-600">{plan.positioning}</p>
                                    <div className="mt-5 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{plan.credits}</div>
                                    <ul className="mt-7 flex-1 space-y-3.5">
                                        {plan.features.map((feature) => <li key={feature} className="flex gap-3 text-sm leading-6 text-slate-700"><CheckIcon />{feature}</li>)}
                                    </ul>
                                    <Link href={`/register?plan=${plan.key}`} className={`mt-8 flex items-center justify-center rounded-xl px-5 py-3.5 text-sm font-black transition ${plan.highlight ? 'bg-emerald-700 text-white hover:bg-emerald-800' : 'border border-slate-300 bg-white text-slate-900 hover:border-emerald-400 hover:bg-emerald-50'}`}>Choose {plan.name}</Link>
                                </article>
                            ))}
                        </div>
                    </section>

                    <section className="border-y border-slate-200 bg-white px-5 py-20 sm:px-6 lg:px-8">
                        <div className="mx-auto max-w-7xl">
                            <div className="max-w-3xl">
                                <p className="text-sm font-black uppercase tracking-[0.14em] text-emerald-700">Full comparison</p>
                                <h2 className="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">Compare every plan at a glance.</h2>
                                <p className="mt-4 leading-7 text-slate-600">All Cosmic Credits are account-wide. Agency users can share permitted templates and Owned Sparks across their managed websites.</p>
                            </div>

                            <div className="mt-10 overflow-x-auto rounded-2xl border border-slate-200">
                                <table className="min-w-[1100px] w-full border-collapse text-left text-sm">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            {['Feature', 'Starter', 'Growth', 'Pro', 'Starter Agency', 'Growth Agency', 'Pro Agency'].map((heading) => <th key={heading} className="border-b border-slate-200 px-5 py-4 font-black text-slate-900">{heading}</th>)}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {comparisonRows.map((row, index) => (
                                            <tr key={row[0]} className={index % 2 ? 'bg-slate-50/50' : 'bg-white'}>
                                                {row.map((cell, cellIndex) => <td key={`${row[0]}-${cellIndex}`} className={`border-b border-slate-100 px-5 py-4 ${cellIndex === 0 ? 'font-black text-slate-900' : 'text-slate-600'}`}>{cell}</td>)}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <section className="px-5 py-20 text-center sm:px-6 sm:py-24">
                        <div className="mx-auto max-w-4xl rounded-[32px] border border-emerald-200 bg-[linear-gradient(135deg,#f2fbf5_0%,#ffffff_48%,#effcf7_100%)] px-6 py-12 shadow-sm sm:px-12">
                            <h2 className="text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">Not ready to choose yet?</h2>
                            <p className="mx-auto mt-4 max-w-2xl leading-7 text-slate-600">Generate a free website concept first. You can review the direction before selecting a paid Personal or Agency plan.</p>
                            <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                                <Link href="/start" className="rounded-xl bg-emerald-700 px-7 py-3.5 text-base font-black text-white transition hover:bg-emerald-800">Generate a free concept</Link>
                                <Link href="/" className="rounded-xl border border-slate-300 bg-white px-7 py-3.5 text-base font-black text-slate-800 transition hover:bg-slate-50">Back to home</Link>
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="border-t border-slate-200 bg-white px-5 py-8 sm:px-6 lg:px-8">
                    <div className="mx-auto flex max-w-7xl flex-col gap-4 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                        <span className="font-bold text-slate-700">Cosmic CMS</span>
                        <div className="flex flex-wrap gap-5">
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
