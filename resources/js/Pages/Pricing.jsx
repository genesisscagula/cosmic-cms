import { Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import SeoHead from '@/Components/Seo/SeoHead';
import PublicSiteLayout from '@/Components/Public/PublicSiteLayout';
import PublicCta from '@/Components/Public/PublicCta';
import { trackCosmicEvent } from '@/Analytics/tracking';

const planFamilies = {
    personal: [
        {
            key: 'starter',
            name: 'Starter',
            price: 49,
            credits: '500',
            highlight: false,
            eyebrow: 'Launch one complete website',
            positioning: 'For a small business, creator, or freelancer that needs a polished standard website with Luna built in.',
            features: [
                '1 website with unlimited standard pages',
                'Luna website, content, and image assistance',
                '30 Marketplace Sparks',
                'Contact forms and structured publishing',
                'Static export and publishing',
                'Basic SEO and traffic analytics',
            ],
            note: 'Best when you need a strong business website without blog or ecommerce workflows.',
        },
        {
            key: 'growth',
            name: 'Growth',
            price: 79,
            credits: '1,000',
            highlight: true,
            badge: 'Most popular',
            eyebrow: 'Grow content and leads',
            positioning: 'For a growing business that needs ongoing content, more design options, lead tools, and stronger marketing controls.',
            features: [
                'Everything in Starter',
                'Posts & Updates for blogs, events, projects, FAQs, and custom content',
                '100 Marketplace Sparks',
                'Landing pages and website duplication drafts',
                'Lead history and submission management',
                'Custom scripts and enhanced SEO',
                'Standard traffic analytics',
            ],
            note: 'Best for service businesses and content-led websites that publish and capture leads regularly.',
        },
        {
            key: 'pro',
            name: 'Pro',
            price: 129,
            credits: '1,500',
            highlight: false,
            eyebrow: 'Unlock the complete site stack',
            positioning: 'For a serious business or creator that needs ecommerce, advanced reporting, premium AI, and the full personal library.',
            features: [
                'Everything in Growth',
                'Full ecommerce: products, variants, shipping, checkout, and orders',
                'Unlimited Marketplace Sparks',
                'All personal templates + 15 free built-in Sparks',
                'Full lead, sales, conversion, and analytics tools',
                'Version history, redirects, and advanced publishing',
                'Priority AI, custom forms, and booking UI Sparks',
                'Custom domain + remove “Built with Cosmic” branding',
            ],
            note: 'Best when the website is an active sales channel and you want the full single-site experience.',
        },
    ],
    agency: [
        {
            key: 'agency_starter',
            name: 'Starter Agency',
            price: 99,
            credits: '750',
            highlight: false,
            eyebrow: 'Start managing clients',
            positioning: 'For a solo freelancer or small studio managing a focused portfolio of client websites.',
            features: [
                'Up to 3 client websites',
                'Website cloning and organization',
                'Client-ready preview links',
                'Per-website lead inbox and analytics summary',
                '3 Agency templates + 5 free built-in Sparks',
                'Up to 5 team members',
                'Essential agency workspace and activity history',
            ],
            note: 'Best for a small client roster that needs one workspace, previews, and reliable handoff-ready organization.',
        },
        {
            key: 'agency_growth',
            name: 'Growth Agency',
            price: 199,
            credits: '1,500',
            highlight: true,
            badge: 'Best for agencies',
            eyebrow: 'Run a growing portfolio',
            positioning: 'For an active agency managing more websites, team workflows, shared design assets, leads, and aggregated insights.',
            features: [
                'Everything in Starter Agency',
                'Up to 10 client websites',
                '10 Agency templates + 10 free built-in Sparks',
                'Full ecommerce store capability',
                'Agency Insights with aggregated analytics and leads',
                'Shared Sparks, templates, and assets',
                'Client handoff + custom preview branding',
                'Basic white label + up to 10 team members',
            ],
            note: 'Best for a growing studio that needs shared assets, client handoffs, and a consolidated view across sites.',
        },
        {
            key: 'agency_pro',
            name: 'Pro Agency',
            price: 399,
            credits: '3,000',
            highlight: false,
            eyebrow: 'Operate without portfolio limits',
            positioning: 'A complete website operating system for professional agencies with advanced reporting, permissions, automation, and white label.',
            features: [
                'Everything in Growth Agency',
                'Unlimited websites under fair use',
                'All templates and Sparks',
                'Full Agency Insights, leads, sales, and revenue reporting',
                'Advanced white label + branded reports',
                'Ownership transfer and granular permissions',
                'Unlimited team members',
                'API access, webhooks, bulk actions, and bulk export',
                'Priority AI and early-access agency tools',
            ],
            note: 'Best for established agencies that want Cosmic CMS to become the core operating layer for client websites.',
        },
    ],
};

const comparisonByFamily = {
    personal: [
        ['Websites', '1', '1', '1'],
        ['Standard pages', 'Unlimited', 'Unlimited', 'Unlimited'],
        ['Posts / Updates', '—', 'Included', 'Advanced'],
        ['Marketplace Sparks', '30', '100', 'Unlimited'],
        ['Personal templates', 'Starter access', 'Growth access', 'All'],
        ['Ecommerce', '—', '—', 'Advanced store'],
        ['Lead management', 'Form inbox', 'Lead history', 'Full'],
        ['Analytics', 'Basic', 'Standard', 'Advanced'],
        ['Sales reporting', '—', 'Basic events', 'Full'],
        ['Custom forms', '—', '—', 'Included'],
        ['Version history', '—', '—', 'Included'],
        ['Priority AI', '—', '—', 'Included'],
        ['Custom domain', '—', '—', 'Included'],
        ['Cosmic branding', 'Shown', 'Shown', 'Removed'],
        ['Credits on first purchase', '500', '1,000', '1,500'],
    ],
    agency: [
        ['Websites', 'Up to 3', 'Up to 10', 'Unlimited'],
        ['Pages per website', 'Up to 5', 'Up to 10', 'Unlimited'],
        ['Sparks per website', 'Up to 15', 'Up to 30', 'Unlimited'],
        ['Agency templates', '3', '10', 'All'],
        ['Free built-in Sparks', '5', '10', '15'],
        ['Team members', 'Up to 5', 'Up to 10', 'Unlimited'],
        ['Client preview links', 'Included', 'Included', 'Included'],
        ['Website cloning', 'Included', 'Included', 'Included'],
        ['Agency Insights', 'Essential', 'Included', 'Full'],
        ['Aggregated analytics', '—', 'Included', 'Full'],
        ['Aggregated leads', '—', 'Included', 'Full'],
        ['Sales reporting', '—', 'Summary', 'Full'],
        ['Shared Sparks / templates', '—', 'Included', 'Included'],
        ['Client handoff', '—', 'Included', 'Ownership transfer'],
        ['White label', '—', 'Basic', 'Advanced'],
        ['API + webhooks', '—', '—', 'Included'],
        ['Credits on first purchase', '750', '1,500', '3,000'],
    ],
};

const familyMeta = {
    personal: {
        label: 'Business',
        short: 'One website',
        description: 'Starter, Growth, and Pro scale one business website from a polished launch to content, leads, ecommerce, and advanced growth tools.',
    },
    agency: {
        label: 'Agency',
        short: 'Multiple websites',
        description: 'Agency plans add multi-site management, team access, client previews, shared assets, consolidated insights, and white-label workflows.',
    },
};

const faqs = [
    {
        question: 'What are Cosmic Credits?',
        answer: 'Cosmic Credits power metered AI and generation actions across your account. Your plan includes a one-time credit allocation on first purchase, and normal non-AI editing does not use AI credits.',
    },
    {
        question: 'Which plan do I need for Posts & Updates?',
        answer: 'Growth is the first Business plan with Posts & Updates, including blogs, events, projects, FAQs, and other structured content. Pro keeps those tools and adds the complete single-site stack.',
    },
    {
        question: 'Which plan includes ecommerce?',
        answer: 'Business Pro includes the advanced ecommerce store. Growth Agency and Pro Agency also include full store capability for managed client websites.',
    },
    {
        question: 'Can I change plans later?',
        answer: 'Yes. Cosmic CMS is designed so you can start with the plan that fits today and move to a different tier as your website, content, or client portfolio grows.',
    },
    {
        question: 'How are Marketplace templates charged?',
        answer: 'Marketplace is available to Agency plans. Your Agency subscription controls account access and the shared website allowance, while each Marketplace template installation uses its displayed Cosmic Credit price. Normal editing does not charge the template price again.',
    },
    {
        question: 'What is the difference between Business and Agency?',
        answer: 'Business plans focus on one website. Agency plans are built for multiple client websites and add portfolio management, client previews, team access, shared assets, handoff workflows, consolidated reporting, and stronger white-label controls as you move up tiers.',
    },
];

function CheckIcon({ subtle = false }) {
    return (
        <span className={`mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full text-[10px] font-black ${subtle ? 'bg-slate-100 text-slate-500' : 'bg-emerald-600 text-white shadow-sm shadow-emerald-950/15'}`}>
            ✓
        </span>
    );
}

function PricingPreview({ family }) {
    const plan = family === 'agency'
        ? { label: 'Growth Agency', sites: '10 websites', credits: '1,500', tools: ['Agency Insights', 'Shared assets', 'Client handoff'] }
        : { label: 'Growth', sites: '1 website', credits: '1,000', tools: ['Posts & Updates', 'Lead history', '100 Sparks'] };

    return (
        <div className="relative mx-auto max-w-[520px]">
            <div className="pointer-events-none absolute -inset-10 rounded-[44px] bg-emerald-400/10 blur-3xl" />
            <div className="relative overflow-hidden rounded-[28px] border border-white/10 bg-white/95 p-4 text-left shadow-[0_35px_100px_-45px_rgba(1,15,31,.75)] backdrop-blur sm:p-5">
                <div className="flex items-center justify-between rounded-2xl bg-[#07132c] px-4 py-4 text-white">
                    <div>
                        <p className="text-[9px] font-extrabold uppercase tracking-[.18em] text-emerald-300">Recommended starting point</p>
                        <p className="mt-1 text-lg font-extrabold">{plan.label}</p>
                    </div>
                    <span className="rounded-xl bg-emerald-500 px-3 py-2 text-[10px] font-extrabold text-white">Active plan</span>
                </div>

                <div className="mt-3 grid grid-cols-2 gap-3">
                    <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p className="text-[9px] font-extrabold uppercase tracking-[.16em] text-slate-400">Capacity</p>
                        <p className="mt-2 text-sm font-extrabold text-[#07132c]">{plan.sites}</p>
                    </div>
                    <div className="rounded-2xl border border-emerald-100 bg-emerald-50/70 p-4">
                        <p className="text-[9px] font-extrabold uppercase tracking-[.16em] text-emerald-600">Included once</p>
                        <p className="mt-2 text-sm font-extrabold text-[#07132c]">{plan.credits} credits</p>
                    </div>
                </div>

                <div className="mt-3 rounded-2xl border border-slate-200 bg-white p-4">
                    <div className="flex items-center justify-between">
                        <p className="text-xs font-extrabold text-[#07132c]">What this tier unlocks</p>
                        <span className="text-[9px] font-bold text-emerald-700">Plan access</span>
                    </div>
                    <div className="mt-3 space-y-2">
                        {plan.tools.map((tool) => (
                            <div key={tool} className="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2.5">
                                <span className="text-[10px] font-bold text-slate-600">{tool}</span>
                                <span className="text-[10px] font-black text-emerald-600">✓</span>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="mt-3 rounded-2xl bg-[linear-gradient(135deg,#ecfdf5,#f8fafc)] p-4">
                    <div className="flex items-center gap-3">
                        <span className="grid h-9 w-9 place-items-center rounded-xl bg-emerald-600 text-xs font-extrabold text-white">✦</span>
                        <div>
                            <p className="text-[10px] font-extrabold text-[#07132c]">Credits follow the account</p>
                            <p className="mt-1 text-[9px] leading-4 text-slate-500">Use them when Luna or another metered AI action does generation work.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

function PlanCard({ plan, family, registrationUrl }) {
    return (
        <article className={`relative flex h-full flex-col overflow-hidden rounded-[28px] border bg-white p-6 shadow-[0_24px_70px_-42px_rgba(15,23,42,.3)] transition duration-300 sm:p-7 ${plan.highlight ? 'border-emerald-400 ring-1 ring-emerald-300/60 lg:-translate-y-2 lg:shadow-[0_30px_90px_-42px_rgba(5,150,105,.48)]' : 'border-slate-200 hover:-translate-y-1 hover:border-emerald-200'}`}>
            {plan.highlight && <div className="pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-emerald-400 via-emerald-600 to-teal-400" />}
            <div className="flex min-h-7 items-start justify-between gap-3">
                <p className="text-[10px] font-extrabold uppercase tracking-[.18em] text-emerald-700">{plan.eyebrow}</p>
                {plan.badge && <span className="shrink-0 rounded-full bg-emerald-100 px-3 py-1 text-[9px] font-extrabold uppercase tracking-[.12em] text-emerald-800">{plan.badge}</span>}
            </div>

            <h2 className="mt-4 text-2xl font-extrabold tracking-[-.035em] text-[#07132c]">{plan.name}</h2>
            <div className="mt-5 flex items-end gap-2">
                <span className="text-5xl font-extrabold tracking-[-.055em] text-[#07132c]">${plan.price}</span>
                <span className="pb-1.5 text-sm font-bold text-slate-400">USD / month</span>
            </div>
            <p className="mt-5 min-h-[84px] text-[15px] leading-7 text-slate-600">{plan.positioning}</p>

            <div className="mt-5 rounded-2xl border border-emerald-100 bg-emerald-50/70 p-4">
                <p className="text-[9px] font-extrabold uppercase tracking-[.16em] text-emerald-700">First purchase bonus</p>
                <div className="mt-1 flex items-baseline gap-2">
                    <span className="text-xl font-extrabold text-[#07132c]">{plan.credits}</span>
                    <span className="text-xs font-bold text-slate-500">Cosmic Credits included once</span>
                </div>
            </div>

            <ul className="mt-6 flex-1 space-y-3.5">
                {plan.features.map((feature) => (
                    <li key={feature} className="flex gap-3 text-sm font-semibold leading-6 text-slate-700">
                        <CheckIcon />
                        <span>{feature}</span>
                    </li>
                ))}
            </ul>

            <div className="mt-7 border-t border-slate-100 pt-5">
                <p className="min-h-[60px] text-xs font-medium leading-5 text-slate-500">{plan.note}</p>
                <Link
                    href={registrationUrl(plan.key)}
                    onClick={() => trackCosmicEvent('plan_selected', { plan_key: plan.key, plan_family: family, plan_name: plan.name, source: 'pricing_page_batch4' })}
                    className={`mt-5 flex min-h-12 items-center justify-center rounded-xl px-5 text-sm font-extrabold transition ${plan.highlight ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-900/15 hover:bg-emerald-500' : 'border border-slate-200 bg-[#07132c] text-white hover:bg-slate-800'}`}
                >
                    Choose {plan.name} →
                </Link>
            </div>
        </article>
    );
}

export default function Pricing({ trialToken = null }) {
    const [family, setFamily] = useState('personal');
    const plans = useMemo(() => planFamilies[family], [family]);
    const comparisonRows = comparisonByFamily[family];
    const registrationUrl = (planKey) => `/register?plan=${encodeURIComponent(planKey)}${trialToken ? `&trial=${encodeURIComponent(trialToken)}` : ''}`;
    const headerPlan = family === 'agency' ? 'agency_growth' : 'growth';

    return (
        <>
            <SeoHead
                title="AI Website Builder Pricing & Plans | Cosmic CMS"
                description="Compare Cosmic CMS plans for business websites and agencies. Start with Luna AI, scale into content and leads, unlock ecommerce, or manage multiple client websites."
                path="/pricing"
            />

            <PublicSiteLayout getStartedHref={registrationUrl(headerPlan)}>
                {trialToken && (
                    <div className="border-b border-emerald-200 bg-emerald-50 px-5 py-3 text-center text-sm font-bold text-emerald-900">
                        Your generated website is reserved. Choose a plan to move it into your Cosmic CMS workspace after checkout.
                    </div>
                )}

                <section className="relative overflow-hidden bg-[#06132d] text-white">
                    <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_78%_15%,rgba(52,211,153,.20),transparent_25%),radial-gradient(circle_at_92%_75%,rgba(14,165,233,.10),transparent_30%)]" />
                    <div className="pointer-events-none absolute -right-24 top-10 h-[420px] w-[420px] rounded-full border border-emerald-300/10" />
                    <div className="pointer-events-none absolute right-8 top-32 h-[260px] w-[260px] rounded-full border border-white/5" />

                    <div className="relative mx-auto grid max-w-[1240px] gap-12 px-5 py-16 sm:px-6 sm:py-20 lg:grid-cols-[1.06fr_.94fr] lg:items-center lg:px-8 lg:py-24">
                        <div>
                            <span className="inline-flex items-center rounded-full border border-emerald-300/20 bg-emerald-300/10 px-4 py-2 text-[10px] font-extrabold uppercase tracking-[.2em] text-emerald-300">
                                ✦&nbsp;&nbsp;Simple monthly pricing
                            </span>
                            <h1 className="mt-6 max-w-3xl text-4xl font-extrabold tracking-[-.05em] sm:text-5xl lg:text-6xl">
                                Start with what you need. <span className="text-emerald-400">Scale when the website earns it.</span>
                            </h1>
                            <p className="mt-6 max-w-2xl text-lg leading-8 text-slate-300">
                                Build one business website or operate a client portfolio. Every tier keeps Luna, visual editing, and publishing connected while advanced content, ecommerce, agency, and reporting tools unlock as you grow.
                            </p>

                            <div className="mt-8 flex flex-wrap gap-3 text-xs font-bold text-slate-300">
                                {['Monthly subscription', 'Cancel or change plans', 'AI credits included once', 'No hidden platform fees'].map((item) => (
                                    <span key={item} className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3.5 py-2">
                                        <span className="text-emerald-300">✓</span>{item}
                                    </span>
                                ))}
                            </div>
                        </div>

                        <PricingPreview family={family} />
                    </div>
                </section>

                <section className="border-b border-slate-200 bg-[linear-gradient(180deg,#f8fbfa_0%,#ffffff_100%)] px-5 py-12 sm:px-6 lg:px-8">
                    <div className="mx-auto max-w-[1240px]">
                        <div className="mx-auto flex max-w-3xl flex-col items-center text-center">
                            <p className="text-[10px] font-extrabold uppercase tracking-[.2em] text-emerald-700">Choose your workspace</p>
                            <h2 className="mt-3 text-3xl font-extrabold tracking-[-.04em] text-[#07132c] sm:text-4xl">One website or a whole client portfolio.</h2>
                            <p className="mt-4 text-base leading-7 text-slate-600">The plans below follow the actual feature tiers already used by Cosmic CMS.</p>

                            <div className="mt-7 grid w-full max-w-xl grid-cols-2 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-sm" role="tablist" aria-label="Pricing family">
                                {Object.entries(familyMeta).map(([key, meta]) => (
                                    <button
                                        key={key}
                                        type="button"
                                        role="tab"
                                        aria-selected={family === key}
                                        onClick={() => setFamily(key)}
                                        className={`rounded-xl px-4 py-3 text-left transition sm:px-5 ${family === key ? 'bg-[#07132c] text-white shadow-md' : 'text-slate-600 hover:bg-slate-50 hover:text-[#07132c]'}`}
                                    >
                                        <span className="block text-sm font-extrabold">{meta.label}</span>
                                        <span className={`mt-0.5 block text-[10px] font-bold ${family === key ? 'text-emerald-300' : 'text-slate-400'}`}>{meta.short}</span>
                                    </button>
                                ))}
                            </div>
                            <p className="mt-4 max-w-2xl text-sm leading-6 text-slate-500">{familyMeta[family].description}</p>
                        </div>
                    </div>
                </section>

                <section className="px-5 py-16 sm:px-6 sm:py-20 lg:px-8">
                    <div className="mx-auto grid max-w-[1240px] gap-6 lg:grid-cols-3">
                        {plans.map((plan) => (
                            <PlanCard key={plan.key} plan={plan} family={family} registrationUrl={registrationUrl} />
                        ))}
                    </div>

                    <div className="mx-auto mt-10 grid max-w-5xl gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {[
                            ['↻', 'Change plans', 'Move tiers as your needs change.'],
                            ['◇', 'Secure billing', 'Subscription checkout stays connected to your account.'],
                            ['✦', 'Credits for AI', 'Generation power is separate from normal editing.'],
                            ['↗', 'Keep your work', 'Your website remains the same project as you scale.'],
                        ].map(([icon, title, text]) => (
                            <div key={title} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                <span className="grid h-9 w-9 place-items-center rounded-xl bg-emerald-50 text-xs font-black text-emerald-700">{icon}</span>
                                <p className="mt-3 text-sm font-extrabold text-[#07132c]">{title}</p>
                                <p className="mt-1 text-xs leading-5 text-slate-500">{text}</p>
                            </div>
                        ))}
                    </div>
                </section>

                <section className="border-y border-slate-200 bg-[#f8fbfa] px-5 py-16 sm:px-6 sm:py-20 lg:px-8">
                    <div className="mx-auto grid max-w-[1240px] gap-10 lg:grid-cols-[.88fr_1.12fr] lg:items-center">
                        <div className="max-w-xl">
                            <p className="text-[10px] font-extrabold uppercase tracking-[.2em] text-emerald-700">Cosmic Credits</p>
                            <h2 className="mt-3 text-3xl font-extrabold tracking-[-.04em] text-[#07132c] sm:text-4xl">Subscription access and AI usage stay easy to understand.</h2>
                            <p className="mt-5 text-base leading-7 text-slate-600">
                                Your plan unlocks product capabilities. Cosmic Credits are the account balance used when Luna or another metered AI action performs generation work. That keeps normal visual editing separate from AI usage.
                            </p>

                            <div className="mt-7 space-y-3">
                                {[
                                    ['Plan', 'Controls websites, content tools, ecommerce, agency features, reporting, and access tiers.'],
                                    ['Credits', 'Power AI-assisted generation and other actions that explicitly consume Cosmic Credits.'],
                                    ['Marketplace', 'Premium Marketplace designs can have their own access or credit requirements while preserving the installed design kit after provisioning.'],
                                ].map(([title, text]) => (
                                    <div key={title} className="flex gap-3 rounded-2xl border border-slate-200 bg-white p-4">
                                        <CheckIcon />
                                        <div>
                                            <p className="text-sm font-extrabold text-[#07132c]">{title}</p>
                                            <p className="mt-1 text-sm leading-6 text-slate-500">{text}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="relative">
                            <div className="pointer-events-none absolute -inset-8 rounded-[40px] bg-emerald-200/25 blur-3xl" />
                            <div className="relative overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-[0_35px_100px_-48px_rgba(15,23,42,.4)]">
                                <div className="flex items-center justify-between border-b border-slate-200 bg-slate-50/80 px-5 py-4">
                                    <div>
                                        <p className="text-sm font-extrabold text-[#07132c]">Credits &amp; plan access</p>
                                        <p className="mt-0.5 text-[9px] font-bold uppercase tracking-[.16em] text-slate-400">Example account overview</p>
                                    </div>
                                    <span className="rounded-full bg-emerald-100 px-3 py-1.5 text-[9px] font-extrabold text-emerald-700">Growth</span>
                                </div>
                                <div className="p-5 sm:p-6">
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="rounded-2xl bg-[#07132c] p-5 text-white">
                                            <p className="text-[9px] font-extrabold uppercase tracking-[.16em] text-emerald-300">Cosmic Credits</p>
                                            <div className="mt-2 flex items-end gap-2">
                                                <span className="text-3xl font-extrabold">824</span>
                                                <span className="pb-1 text-[10px] text-slate-400">available</span>
                                            </div>
                                            <div className="mt-4 h-2 overflow-hidden rounded-full bg-white/10">
                                                <div className="h-full w-[72%] rounded-full bg-emerald-400" />
                                            </div>
                                            <p className="mt-2 text-[9px] text-slate-400">Used only by metered generation actions.</p>
                                        </div>
                                        <div className="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                                            <p className="text-[9px] font-extrabold uppercase tracking-[.16em] text-slate-400">Current plan</p>
                                            <p className="mt-2 text-xl font-extrabold text-[#07132c]">Growth</p>
                                            <p className="mt-1 text-[10px] leading-4 text-slate-500">Posts &amp; Updates · leads · 100 Sparks · enhanced SEO</p>
                                        </div>
                                    </div>

                                    <div className="mt-4 space-y-2.5">
                                        {[
                                            ['AI page generation', '-5 credits', 'AI'],
                                            ['Visual section editing', 'No AI charge', 'Manual'],
                                            ['Rewrite with Luna', 'Metered when used', 'AI'],
                                            ['Move / reorder section', 'No AI charge', 'Manual'],
                                        ].map(([action, charge, type]) => (
                                            <div key={action} className="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-3">
                                                <span className={`grid h-8 w-8 place-items-center rounded-lg text-[9px] font-black ${type === 'AI' ? 'bg-violet-50 text-violet-700' : 'bg-emerald-50 text-emerald-700'}`}>{type === 'AI' ? '✦' : '◇'}</span>
                                                <div className="min-w-0 flex-1">
                                                    <p className="text-[10px] font-extrabold text-[#07132c]">{action}</p>
                                                    <p className="mt-0.5 text-[9px] text-slate-400">{type} action</p>
                                                </div>
                                                <span className="text-[9px] font-extrabold text-slate-500">{charge}</span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="px-5 py-16 sm:px-6 sm:py-20 lg:px-8">
                    <div className="mx-auto max-w-[1240px]">
                        <div className="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
                            <div className="max-w-3xl">
                                <p className="text-[10px] font-extrabold uppercase tracking-[.2em] text-emerald-700">Full comparison</p>
                                <h2 className="mt-3 text-3xl font-extrabold tracking-[-.04em] text-[#07132c] sm:text-4xl">Compare {familyMeta[family].label.toLowerCase()} plans at a glance.</h2>
                                <p className="mt-4 text-base leading-7 text-slate-600">Switch between Business and Agency above to compare the feature set that matches how you operate.</p>
                            </div>
                            <span className="inline-flex w-fit rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-xs font-extrabold text-slate-500">Monthly USD pricing</span>
                        </div>

                        <p className="mt-6 text-xs font-bold text-slate-400 sm:hidden">Swipe sideways to compare every plan →</p>
                        <div data-public-scroll-region className="mt-3 overflow-x-auto rounded-[24px] border border-slate-200 bg-white shadow-sm sm:mt-8">
                            <table className="w-full min-w-[760px] border-collapse text-left text-sm">
                                <thead className="bg-[#07132c] text-white">
                                    <tr>
                                        <th className="px-5 py-4 text-[10px] font-extrabold uppercase tracking-[.15em] text-slate-300">Feature</th>
                                        {plans.map((plan) => (
                                            <th key={plan.key} className="px-5 py-4">
                                                <span className="block text-sm font-extrabold">{plan.name}</span>
                                                <span className="mt-1 block text-[10px] font-bold text-emerald-300">${plan.price}/month</span>
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {comparisonRows.map((row, index) => (
                                        <tr key={row[0]} className={index % 2 ? 'bg-slate-50/60' : 'bg-white'}>
                                            <td className="border-b border-slate-100 px-5 py-4 font-extrabold text-[#07132c]">{row[0]}</td>
                                            {row.slice(1).map((cell, cellIndex) => (
                                                <td key={`${row[0]}-${cellIndex}`} className="border-b border-slate-100 px-5 py-4 font-semibold text-slate-600">{cell}</td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section className="border-y border-slate-200 bg-[#07132c] px-5 py-16 text-white sm:px-6 sm:py-20 lg:px-8">
                    <div className="mx-auto grid max-w-[1240px] gap-10 lg:grid-cols-[.78fr_1.22fr]">
                        <div>
                            <p className="text-[10px] font-extrabold uppercase tracking-[.2em] text-emerald-300">Pricing FAQ</p>
                            <h2 className="mt-3 text-3xl font-extrabold tracking-[-.04em] sm:text-4xl">Know what you are paying for before you launch.</h2>
                            <p className="mt-5 max-w-md text-base leading-7 text-slate-300">The plan controls product access. Credits handle metered AI generation. Marketplace designs can remain a separate design purchase or credit decision.</p>
                            <Link href="/help" className="mt-7 inline-flex min-h-11 items-center rounded-xl border border-white/15 bg-white/5 px-5 text-sm font-extrabold text-white transition hover:bg-white/10">Visit Help Center →</Link>
                        </div>

                        <div className="grid gap-3">
                            {faqs.map((faq, index) => (
                                <details key={faq.question} className="group rounded-2xl border border-white/10 bg-white/[.045] px-5 py-4 open:bg-white/[.07]">
                                    <summary className="flex cursor-pointer list-none items-center justify-between gap-5 text-sm font-extrabold text-white [&::-webkit-details-marker]:hidden">
                                        <span>{faq.question}</span>
                                        <span className="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-white/10 bg-white/5 text-emerald-300 transition group-open:rotate-45">＋</span>
                                    </summary>
                                    <p className="mt-3 max-w-3xl pr-10 text-sm leading-6 text-slate-300">{faq.answer}</p>
                                </details>
                            ))}
                        </div>
                    </div>
                </section>

                <PublicCta
                    eyebrow="Launch with the right tier"
                    title="Start with a free concept, then choose the plan that fits."
                    description="Generate a website direction first, review what Cosmic CMS can build, and move into Business or Agency when you are ready to keep building."
                    primaryLabel="Create free demo"
                    primaryHref="/start"
                    secondaryLabel="Explore features"
                    secondaryHref="/features"
                />
            </PublicSiteLayout>
        </>
    );
}
