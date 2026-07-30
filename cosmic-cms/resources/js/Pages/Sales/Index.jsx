import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const FEATURES = [
    {
        title: 'AI website generation',
        text: 'Generate complete page directions from a short business prompt, then refine the result in the visual builder.',
    },
    {
        title: 'Reusable premium blocks',
        text: 'Build pages with a curated block library for heroes, services, testimonials, pricing, contact, and more.',
    },
    {
        title: 'Theme-aware builder',
        text: 'Every generated demo keeps the AI-selected theme from trial preview through the builder and purchased website.',
    },
    {
        title: 'Client-friendly editing',
        text: 'Clients can update text, images, links, and page content without touching code or complex design controls.',
    },
    {
        title: 'Multi-website workspace',
        text: 'Manage multiple client websites from one account with separate pages, settings, and publishing workflows.',
    },
    {
        title: 'Fast sales demos',
        text: 'Open a saved token demo instantly instead of spending API credits regenerating the same concept repeatedly.',
    },
];

function DemoCard({ demo }) {
    return (
        <article className="group flex h-full flex-col rounded-2xl border border-white/10 bg-white/[0.035] p-5 shadow-xl shadow-black/10 transition hover:-translate-y-0.5 hover:border-cyan-400/30 hover:bg-white/[0.055]">
            <div className="mb-5 flex items-start justify-between gap-4">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">
                        {demo.industry}
                    </p>
                    <h3 className="mt-2 text-xl font-semibold text-white">
                        {demo.business_name}
                    </h3>
                    <p className="mt-1 text-sm text-slate-400">{demo.location}</p>
                </div>
                <span className="rounded-full border border-white/10 bg-black/20 px-3 py-1 text-xs font-semibold capitalize text-slate-300">
                    {demo.theme}
                </span>
            </div>

            <div className="mt-auto flex items-center justify-between border-t border-white/10 pt-4 text-xs text-slate-400">
                <span>{demo.blocks_count} sections</span>
                <span>{demo.updated_at}</span>
            </div>

            <a
                href={demo.demo_url}
                target="_blank"
                rel="noreferrer"
                className="mt-4 inline-flex items-center justify-center rounded-xl bg-cyan-400 px-4 py-3 text-sm font-bold text-slate-950 transition hover:bg-cyan-300 focus:outline-none focus:ring-2 focus:ring-cyan-300 focus:ring-offset-2 focus:ring-offset-slate-950"
            >
                Open token demo
            </a>
        </article>
    );
}

export default function SalesIndex({ demos = [] }) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-400">
                            Sales toolkit
                        </p>
                        <h2 className="mt-1 text-xl font-semibold leading-tight text-white">
                            Cosmic CMS Sales Page
                        </h2>
                    </div>
                    <Link
                        href={route('dashboard')}
                        className="rounded-lg border border-white/10 px-4 py-2 text-sm font-semibold text-slate-300 transition hover:border-white/20 hover:bg-white/5 hover:text-white"
                    >
                        Back to dashboard
                    </Link>
                </div>
            }
        >
            <Head title="Sales" />

            <div className="min-h-screen bg-[#090b12] py-10 text-slate-100">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <section className="overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-cyan-400/10 via-white/[0.035] to-violet-500/10 px-6 py-10 shadow-2xl shadow-black/30 sm:px-10 sm:py-14">
                        <div className="max-w-3xl">
                            <span className="inline-flex rounded-full border border-cyan-300/20 bg-cyan-300/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-cyan-200">
                                Demo without regenerating
                            </span>
                            <h1 className="mt-5 text-4xl font-black tracking-tight text-white sm:text-5xl">
                                Sell the result, not the loading screen.
                            </h1>
                            <p className="mt-5 max-w-2xl text-base leading-7 text-slate-300 sm:text-lg">
                                Keep your strongest generated websites here and open their secure token links during calls, outreach, or client presentations. No extra AI generation required.
                            </p>
                            <div className="mt-8 flex flex-wrap gap-3">
                                <a
                                    href="#demos"
                                    className="rounded-xl bg-cyan-400 px-5 py-3 text-sm font-bold text-slate-950 transition hover:bg-cyan-300"
                                >
                                    Browse saved demos
                                </a>
                                <Link
                                    href={route('start')}
                                    className="rounded-xl border border-white/15 bg-white/5 px-5 py-3 text-sm font-bold text-white transition hover:bg-white/10"
                                >
                                    Create a new demo
                                </Link>
                            </div>
                        </div>
                    </section>

                    <section className="py-14">
                        <div className="max-w-2xl">
                            <p className="text-xs font-bold uppercase tracking-[0.18em] text-violet-300">Core features</p>
                            <h2 className="mt-3 text-3xl font-bold text-white">Everything needed for a faster sales workflow</h2>
                        </div>
                        <div className="mt-8 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {FEATURES.map((feature) => (
                                <article key={feature.title} className="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
                                    <h3 className="font-semibold text-white">{feature.title}</h3>
                                    <p className="mt-2 text-sm leading-6 text-slate-400">{feature.text}</p>
                                </article>
                            ))}
                        </div>
                    </section>

                    <section id="demos" className="scroll-mt-8 pb-16">
                        <div className="flex flex-wrap items-end justify-between gap-4">
                            <div>
                                <p className="text-xs font-bold uppercase tracking-[0.18em] text-cyan-300">Token demo library</p>
                                <h2 className="mt-3 text-3xl font-bold text-white">Ready-to-show websites</h2>
                                <p className="mt-2 text-sm text-slate-400">
                                    Latest completed trial generations are listed automatically.
                                </p>
                            </div>
                            <span className="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-slate-300">
                                {demos.length} saved {demos.length === 1 ? 'demo' : 'demos'}
                            </span>
                        </div>

                        {demos.length > 0 ? (
                            <div className="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                                {demos.map((demo) => <DemoCard key={demo.id} demo={demo} />)}
                            </div>
                        ) : (
                            <div className="mt-8 rounded-2xl border border-dashed border-white/15 bg-white/[0.025] px-6 py-14 text-center">
                                <h3 className="text-lg font-semibold text-white">No completed token demos yet</h3>
                                <p className="mx-auto mt-2 max-w-lg text-sm leading-6 text-slate-400">
                                    Generate one website from the Start page. Once the trial reaches ready status, it will appear here automatically.
                                </p>
                                <Link
                                    href={route('start')}
                                    className="mt-5 inline-flex rounded-xl bg-cyan-400 px-5 py-3 text-sm font-bold text-slate-950 hover:bg-cyan-300"
                                >
                                    Generate first demo
                                </Link>
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
