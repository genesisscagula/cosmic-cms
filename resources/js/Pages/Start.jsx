import { Head, Link, router, useForm } from '@inertiajs/react';
import { BlockRegistry } from './Websites/BlockRegistry';

const fieldClass = 'mt-2 w-full rounded-xl border border-white/10 bg-black/25 px-4 py-3 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-violet-400 focus:ring-2 focus:ring-violet-400/20';

const plans = [
    { id: 'starter', name: 'Starter', price: '$49', summary: 'A focused first website.', features: ['Up to 5 pages', 'AI-generated starting draft', 'Visual Builder and publishing'] },
    { id: 'growth', name: 'Growth', price: '$79', summary: 'For a growing business website.', features: ['Up to 10 pages', 'Forms, blog, and publishing', 'Priority launch support'], featured: true },
    { id: 'pro', name: 'Pro', price: '$129', summary: 'For teams with more to publish.', features: ['Flexible page growth', 'Full Builder section library', 'Hands-on launch planning'] },
];

export default function Start({ industries, trial }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        business_name: '',
        industry: '',
        location: '',
        business_description: '',
        prompt: '',
    });

    const submit = (event) => {
        event.preventDefault();
        post('/start');
    };

    const isReady = trial?.status === 'ready';
    const isFailed = trial?.status === 'failed';
    const selectedPlan = plans.find((plan) => plan.id === trial?.selected_plan);

    const selectPlan = (planId) => {
        if (!trial?.token) return;

        router.post(`/start/${encodeURIComponent(trial.token)}/plan`, { plan: planId }, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Start building with Cosmic CMS" />

            <main className="min-h-screen overflow-hidden bg-[#09090b] text-white">
                <div className="pointer-events-none absolute inset-x-0 top-0 h-[38rem] bg-[radial-gradient(circle_at_50%_-10%,rgba(124,58,237,0.28),transparent_48%),radial-gradient(circle_at_78%_12%,rgba(16,185,129,0.12),transparent_30%)]" />

                <header className="relative mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-6 sm:px-8">
                    <Link href="/" className="flex items-center gap-3 font-semibold tracking-tight text-white">
                        <span className="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-violet-500 to-cyan-400 text-sm font-bold text-[#10121c]">C</span>
                        <span>Cosmic <span className="text-emerald-300">CMS</span></span>
                    </Link>
                    <Link href="/login" className="text-sm font-medium text-slate-300 transition hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400 focus-visible:ring-offset-2 focus-visible:ring-offset-[#09090b]">Sign in</Link>
                </header>

                <section className="relative mx-auto grid w-full max-w-6xl gap-12 px-6 pb-20 pt-10 sm:px-8 lg:grid-cols-[0.9fr_1.1fr] lg:items-start lg:pt-20">
                    <div className="max-w-xl pt-2 lg:pt-8">
                        <p className="mb-5 inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1.5 text-xs font-semibold text-emerald-200">
                            <span className="h-1.5 w-1.5 rounded-full bg-emerald-300" />
                            Build your first draft in minutes
                        </p>
                        <h1 className="text-balance text-4xl font-semibold leading-[1.02] tracking-[-0.045em] text-white sm:text-5xl lg:text-6xl">
                            Describe your business. Start with a real website draft.
                        </h1>
                        <p className="mt-6 max-w-lg text-base leading-7 text-slate-300 sm:text-lg">
                            Cosmic plans a suitable page structure, writes editable content, and keeps you in control of every section before anything goes live.
                        </p>

                        <div className="mt-10 space-y-4 border-l border-white/10 pl-5 text-sm text-slate-300">
                            <p><span className="mr-2 text-violet-300">01</span> Tell us what your business does.</p>
                            <p><span className="mr-2 text-violet-300">02</span> Cosmic prepares an editable first draft.</p>
                            <p><span className="mr-2 text-violet-300">03</span> Review it, then create your workspace when ready.</p>
                        </div>
                    </div>

                    <div className="rounded-3xl border border-white/10 bg-[#15151a]/95 p-5 shadow-2xl shadow-black/40 backdrop-blur sm:p-7">
                        {isReady ? (
                            <div className="py-5 sm:py-8">
                                <div className="grid h-12 w-12 place-items-center rounded-2xl bg-emerald-400/15 text-xl text-emerald-300">✓</div>
                                <p className="mt-7 text-xs font-semibold uppercase tracking-[0.18em] text-violet-300">Draft prepared</p>
                                <h2 className="mt-3 text-3xl font-semibold tracking-tight">Your website direction is ready.</h2>
                                <p className="mt-4 max-w-lg leading-7 text-slate-300">
                                    We saved a {trial.blocks_count}-section starting draft for {trial.business_name}. Create an account to review, edit, and publish it from your Cosmic workspace.
                                </p>
                                <PlanSelection selectedPlan={selectedPlan?.id} onSelect={selectPlan} />
                                <div className="mt-7 flex flex-wrap gap-3">
                                    {selectedPlan ? (
                                        <Link href={`/register?trial=${encodeURIComponent(trial.token)}`} className="rounded-xl bg-white px-5 py-3 text-sm font-semibold text-[#121217] transition hover:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400">Continue with {selectedPlan.name}</Link>
                                    ) : (
                                        <a href="#plans" className="rounded-xl bg-white px-5 py-3 text-sm font-semibold text-[#121217] transition hover:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400">Choose a plan to continue</a>
                                    )}
                                    <Link href="/start" className="rounded-xl border border-white/10 px-5 py-3 text-sm font-semibold text-slate-200 transition hover:border-white/25 hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400">Create another draft</Link>
                                </div>
                                <p className="mt-7 text-sm text-slate-400">This is a private draft—not a published website.</p>
                            </div>
                        ) : isFailed ? (
                            <div className="py-5 sm:py-8">
                                <div className="grid h-12 w-12 place-items-center rounded-2xl bg-rose-400/15 text-xl text-rose-300">!</div>
                                <h2 className="mt-7 text-3xl font-semibold tracking-tight">We could not prepare that draft.</h2>
                                <p className="mt-4 max-w-lg leading-7 text-slate-300">{trial.error_message || 'Please try again in a moment.'}</p>
                                <Link href="/start" className="mt-8 inline-flex rounded-xl bg-white px-5 py-3 text-sm font-semibold text-[#121217] transition hover:bg-slate-200">Try again</Link>
                            </div>
                        ) : (
                            <form onSubmit={submit} noValidate>
                                <div className="mb-7">
                                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-violet-300">Start a draft</p>
                                    <h2 className="mt-2 text-2xl font-semibold tracking-tight">Tell Cosmic about the business.</h2>
                                    <p className="mt-2 text-sm leading-6 text-slate-400">We use these details to make the first draft relevant. Nothing is published from this form.</p>
                                </div>

                                <div className="grid gap-5 sm:grid-cols-2">
                                    <Field label="Your email" error={errors.email}>
                                        <input type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} className={fieldClass} placeholder="you@company.com" autoComplete="email" />
                                    </Field>
                                    <Field label="Business name" error={errors.business_name}>
                                        <input value={data.business_name} onChange={(event) => setData('business_name', event.target.value)} className={fieldClass} placeholder="BrightSmile Dental" autoComplete="organization" />
                                    </Field>
                                    <Field label="Industry" error={errors.industry}>
                                        <select value={data.industry} onChange={(event) => setData('industry', event.target.value)} className={fieldClass}>
                                            <option value="" disabled className="bg-[#15151a]">Select an industry</option>
                                            {industries.map((industry) => <option key={industry} value={industry} className="bg-[#15151a]">{industry}</option>)}
                                        </select>
                                    </Field>
                                    <Field label="Location" error={errors.location}>
                                        <input value={data.location} onChange={(event) => setData('location', event.target.value)} className={fieldClass} placeholder="e.g. New York, NY" autoComplete="address-level2" />
                                    </Field>
                                </div>

                                <div className="mt-5">
                                    <Field label="About your business" error={errors.business_description}>
                                        <textarea value={data.business_description} onChange={(event) => setData('business_description', event.target.value)} className={`${fieldClass} min-h-28 resize-y`} placeholder="What do you offer, who do you serve, and what makes your business different?" />
                                    </Field>
                                </div>
                                <div className="mt-5">
                                    <Field label="Anything specific?" optional error={errors.prompt}>
                                        <textarea value={data.prompt} onChange={(event) => setData('prompt', event.target.value)} className={`${fieldClass} min-h-20 resize-y`} placeholder="Optional: mention a goal, service, or style you want the draft to focus on." />
                                    </Field>
                                </div>

                                <button type="submit" disabled={processing} className="mt-7 flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-violet-500 to-indigo-500 px-5 py-3.5 text-sm font-semibold text-white shadow-lg shadow-violet-900/30 transition hover:from-violet-400 hover:to-indigo-400 disabled:cursor-not-allowed disabled:opacity-60 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-300 focus-visible:ring-offset-2 focus-visible:ring-offset-[#15151a]">
                                    {processing ? 'Preparing your draft…' : 'Generate my website draft'}
                                </button>
                                <p className="mt-4 text-center text-xs leading-5 text-slate-500">A draft is saved for you to review. No website is published automatically.</p>
                            </form>
                        )}
                    </div>
                </section>

                {isReady && Array.isArray(trial.generated_blocks) && trial.generated_blocks.length > 0 && (
                    <DraftPreview blocks={trial.generated_blocks} businessName={trial.business_name} trialToken={trial.token} selectedPlan={selectedPlan} />
                )}
            </main>
        </>
    );
}

const previewTheme = {
    primary: 'midnight',
    secondary: 'white',
    tertiary: 'stone',
    auto: true,
};

function DraftPreview({ blocks, businessName, trialToken, selectedPlan }) {
    return (
        <section className="relative border-t border-white/10 bg-[#0d0d11] py-16 sm:py-20">
            <div className="mx-auto mb-10 w-full max-w-6xl px-6 sm:px-8">
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-violet-300">Private draft preview</p>
                <h2 className="mt-3 text-3xl font-semibold tracking-tight text-white sm:text-4xl">A starting point for {businessName}.</h2>
                <p className="mt-3 max-w-2xl text-sm leading-6 text-slate-400">This draft is generated from your business profile. Create your workspace to edit every section, image, and theme choice.</p>
            </div>
            <div className="mx-auto w-full max-w-[1440px] overflow-hidden border-y border-white/10 bg-white shadow-2xl shadow-black/30">
                {blocks.map((block, index) => {
                    const Component = BlockRegistry[block.type]?.component;

                    return Component ? (
                        <div key={`${block.type}-${index}`} className="pointer-events-none select-none">
                            <Component
                                block={{ ...block, resolvedTheme: block.resolvedTheme || 'auto' }}
                                blockIndex={index}
                                globalTheme={previewTheme}
                                onUpdate={() => {}}
                            />
                        </div>
                    ) : (
                        <div key={`${block.type}-${index}`} className="border-b border-slate-200 px-6 py-8 text-sm text-slate-600">
                            {block.type.replaceAll('_', ' ')}
                        </div>
                    );
                })}
            </div>
            <div className="mx-auto mt-10 flex w-full max-w-6xl justify-center px-6 sm:px-8">
                {selectedPlan ? (
                    <Link href={`/register?trial=${encodeURIComponent(trialToken)}`} className="rounded-xl bg-white px-5 py-3 text-sm font-semibold text-[#121217] transition hover:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400">Continue with {selectedPlan.name}</Link>
                ) : (
                    <a href="#plans" className="rounded-xl bg-white px-5 py-3 text-sm font-semibold text-[#121217] transition hover:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400">Choose a plan to continue</a>
                )}
            </div>
        </section>
    );
}

function PlanSelection({ selectedPlan, onSelect }) {
    return (
        <section id="plans" className="mt-8" aria-labelledby="plan-selection-title">
            <div className="flex flex-wrap items-end justify-between gap-2">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-violet-300">Choose your launch plan</p>
                    <h3 id="plan-selection-title" className="mt-2 text-lg font-semibold text-white">Select the workspace that fits your launch.</h3>
                </div>
                <p className="text-xs text-slate-500">No payment on this page</p>
            </div>

            <div className="mt-4 grid gap-3 md:grid-cols-3">
                {plans.map((plan) => {
                    const isSelected = selectedPlan === plan.id;

                    return (
                        <button
                            type="button"
                            key={plan.id}
                            onClick={() => onSelect(plan.id)}
                            aria-pressed={isSelected}
                            className={`relative rounded-2xl border p-4 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-300 ${isSelected ? 'border-violet-400 bg-violet-500/10 shadow-lg shadow-violet-950/20' : 'border-white/10 bg-black/20 hover:border-white/25 hover:bg-white/[0.03]'}`}
                        >
                            {plan.featured && (
                                <span className="absolute right-3 top-3 rounded-full bg-violet-400/15 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-violet-200">Popular</span>
                            )}
                            <span className="text-sm font-semibold text-white">{plan.name}</span>
                            <span className="mt-1 block text-2xl font-semibold tracking-tight text-white">
                                {plan.price}<span className="ml-1 text-xs font-medium text-slate-400">/ month</span>
                            </span>
                            <span className="mt-2 block text-xs leading-5 text-slate-400">{plan.summary}</span>
                            <span className="mt-3 block space-y-1 border-t border-white/10 pt-3 text-xs leading-5 text-slate-300">
                                {plan.features.map((feature) => <span key={feature} className="block">✓ {feature}</span>)}
                            </span>
                            <span className={`mt-4 block text-xs font-semibold ${isSelected ? 'text-violet-200' : 'text-slate-400'}`}>
                                {isSelected ? 'Selected' : 'Choose plan'}
                            </span>
                        </button>
                    );
                })}
            </div>
        </section>
    );
}

function Field({ label, optional = false, error, children }) {
    return (
        <label className="block text-sm font-medium text-slate-200">
            {label} {optional && <span className="font-normal text-slate-500">(optional)</span>}
            {children}
            {error && <span className="mt-1.5 block text-xs text-rose-300">{error}</span>}
        </label>
    );
}
