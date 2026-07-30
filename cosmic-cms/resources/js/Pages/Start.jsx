import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import '../../css/start.css';

const fieldClass = 'mt-2 w-full rounded-xl border border-white/10 bg-black/25 px-4 py-3 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-violet-400 focus:ring-2 focus:ring-violet-400/20';

const plans = [
    { id: 'starter', name: 'Starter', price: '$49', summary: 'A focused first website.', features: ['Up to 5 pages', 'AI-generated starting draft', 'Visual Builder and publishing'] },
    { id: 'growth', name: 'Growth', price: '$79', summary: 'For a growing business website.', features: ['Up to 10 pages', 'Forms, blog, and publishing', 'Priority launch support'], featured: true },
    { id: 'pro', name: 'Pro', price: '$129', summary: 'For teams with more to publish.', features: ['Flexible page growth', 'Full Builder section library', 'Hands-on launch planning'] },
];

const quickIdeas = [
    { label: 'Restaurant', prompt: 'Create a modern restaurant website for a neighborhood dining spot in New York. Include menus, reservations, testimonials, and a contact page.' },
    { label: 'Coffee Shop', prompt: 'Create a warm website for an independent coffee shop in Brooklyn. Highlight specialty coffee, pastries, the menu, and private event enquiries.' },
    { label: 'Dentist', prompt: 'Create a modern website for BrightSmile Dental in New York. We provide family dentistry, implants, and teeth whitening. Use blue and white colors. Include online booking, testimonials, and an About page.' },
    { label: 'Construction', prompt: 'Create a confident website for a construction company in Austin. Showcase residential projects, services, the process, and a quote request page.' },
    { label: 'Law Firm', prompt: 'Create a professional website for a law firm in Chicago. Explain practice areas, build trust with the team, and include a consultation enquiry page.' },
    { label: 'Hotel', prompt: 'Create an elegant website for a boutique hotel in Miami. Feature rooms, amenities, local experiences, testimonials, and direct booking enquiries.' },
    { label: 'Fitness', prompt: 'Create an energetic website for a fitness studio in Los Angeles. Include classes, coaches, membership options, testimonials, and a trial booking page.' },
];

const animatedPrompts = [
    'Create a modern website for a family dental clinic with online booking, services, testimonials, and FAQs.',
    'Build a premium restaurant website with a menu, table reservations, gallery, and customer reviews.',
    'Design a warm and modern coffee shop website with online ordering, opening hours, and location details.',
    'Create a professional construction company website featuring services, completed projects, and quote requests.',
    'Build a trustworthy law firm website with practice areas, attorney profiles, consultations, and testimonials.',
    'Design a luxury hotel website with rooms, amenities, booking, gallery, and nearby attractions.',
    'Create an energetic fitness gym website with classes, coaches, membership plans, and trial bookings.',
    'Build a refined real estate website with featured properties, local expertise, and viewing requests.',
    'Create a reassuring medical clinic website with services, practitioners, patient information, and appointments.',
    'Design a stylish salon website with services, stylist profiles, gallery, and online appointments.',
    'Build a welcoming bakery website with signature products, custom orders, opening hours, and location details.',
    'Create an inspiring travel agency website with destinations, itineraries, travel planning, and enquiries.',
    'Build a confident technology company website with solutions, case studies, team expertise, and consultations.',
    'Design a dependable cleaning service website with service packages, service areas, reviews, and quote requests.',
    'Create a polished automotive business website with inventory, services, financing, and test-drive bookings.',
];

const generationSteps = [
    { label: 'Understand brief', threshold: 10 },
    { label: 'Plan sections', threshold: 30 },
    { label: 'Create content', threshold: 72 },
    { label: 'Build page', threshold: 95 },
];

const startGenerationStages = [
    { message: 'Understanding your request...', target: 10, duration: 700 },
    { message: 'Planning the right sections...', target: 30, duration: 850 },
    { message: 'Writing professional content...', target: 72, duration: 1400 },
    { message: 'Building your page...', target: 90, duration: 1600 },
];

function shufflePromptBag(previousPrompt = null) {
    const bag = [...animatedPrompts];

    for (let index = bag.length - 1; index > 0; index -= 1) {
        const randomIndex = Math.floor(Math.random() * (index + 1));
        [bag[index], bag[randomIndex]] = [bag[randomIndex], bag[index]];
    }

    if (previousPrompt && bag.length > 1 && bag[0] === previousPrompt) {
        [bag[0], bag[1]] = [bag[1], bag[0]];
    }

    return bag;
}

function useAnimatedPrompt(isEmpty) {
    const [prompt, setPrompt] = useState('');
    const [reducedMotion, setReducedMotion] = useState(false);

    useEffect(() => {
        const mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
        const updatePreference = () => setReducedMotion(mediaQuery.matches);

        updatePreference();
        mediaQuery.addEventListener?.('change', updatePreference);

        return () => mediaQuery.removeEventListener?.('change', updatePreference);
    }, []);

    useEffect(() => {
        if (!isEmpty) {
            setPrompt('');
            return undefined;
        }

        if (reducedMotion) {
            setPrompt(animatedPrompts[0]);
            return undefined;
        }

        let cancelled = false;
        let timeoutId;
        let bag = shufflePromptBag();
        let bagIndex = 0;
        let previousPrompt = null;

        const wait = (duration) => new Promise((resolve) => {
            timeoutId = window.setTimeout(resolve, duration);
        });

        const animate = async () => {
            while (!cancelled) {
                const nextPrompt = bag[bagIndex];

                for (let length = 1; length <= nextPrompt.length; length += 1) {
                    if (cancelled) return;
                    setPrompt(nextPrompt.slice(0, length));
                    await wait(50);
                }

                await wait(2000);

                for (let length = nextPrompt.length - 1; length >= 0; length -= 1) {
                    if (cancelled) return;
                    setPrompt(nextPrompt.slice(0, length));
                    await wait(20);
                }

                await wait(400);
                previousPrompt = nextPrompt;
                bagIndex += 1;

                if (bagIndex === bag.length) {
                    bag = shufflePromptBag(previousPrompt);
                    bagIndex = 0;
                }
            }
        };

        animate();

        return () => {
            cancelled = true;
            window.clearTimeout(timeoutId);
        };
    }, [isEmpty, reducedMotion]);

    return prompt;
}

export default function Start({ trial }) {
    const { data, setData, post, processing, errors } = useForm({
        prompt: '',
    });
    const [prompt, setPrompt] = useState('');
    const [showLoading, setShowLoading] = useState(false);
    const [loadingStage, setLoadingStage] = useState(startGenerationStages[0].message);
    const [loadingProgress, setLoadingProgress] = useState(0);
    const quickIdeaTimerRef = useRef(null);
    const animatedPrompt = useAnimatedPrompt(prompt.length === 0);

    const clearQuickIdeaTimer = () => {
        if (quickIdeaTimerRef.current) {
            window.clearTimeout(quickIdeaTimerRef.current);
            quickIdeaTimerRef.current = null;
        }
    };

    const updatePrompt = (value) => {
        setPrompt(value);
        setData('prompt', value);
    };

    const handlePromptChange = (event) => {
        clearQuickIdeaTimer();
        updatePrompt(event.target.value);
    };

    const typeQuickIdea = (value) => {
        clearQuickIdeaTimer();
        updatePrompt('');

        let characterIndex = 0;

        const typeNextCharacter = () => {
            characterIndex += 1;
            updatePrompt(value.slice(0, characterIndex));

            if (characterIndex < value.length) {
                quickIdeaTimerRef.current = window.setTimeout(typeNextCharacter, 14);
            } else {
                quickIdeaTimerRef.current = null;
            }
        };

        quickIdeaTimerRef.current = window.setTimeout(typeNextCharacter, 14);
    };

    useEffect(() => () => clearQuickIdeaTimer(), []);

    useEffect(() => {
        if (!showLoading) return undefined;

        let cancelled = false;
        let progressTimer;
        let stageTimer;
        let finishingTimer;
        let progress = 0;

        const holdAtFinalizing = () => {
            if (cancelled) return;

            setLoadingStage('Finalizing your draft...');
            setLoadingProgress(progress);

            finishingTimer = window.setTimeout(() => {
                if (!cancelled) {
                    setLoadingStage('Still building your draft. This can take up to a minute...');
                }
            }, 9000);
        };

        const playStage = (stageIndex) => {
            if (cancelled) return;

            const stage = startGenerationStages[stageIndex];
            setLoadingStage(stage.message);

            const advanceProgress = () => {
                if (cancelled || progress >= stage.target) return;

                progress = Math.min(stage.target, progress + Math.max(1, Math.ceil((stage.target - progress) / 9)));
                setLoadingProgress(progress);
                progressTimer = window.setTimeout(advanceProgress, 70);
            };

            advanceProgress();

            if (stageIndex < startGenerationStages.length - 1) {
                stageTimer = window.setTimeout(() => playStage(stageIndex + 1), stage.duration);
            } else {
                stageTimer = window.setTimeout(holdAtFinalizing, stage.duration);
            }
        };

        setLoadingProgress(0);
        playStage(0);

        return () => {
            cancelled = true;
            window.clearTimeout(progressTimer);
            window.clearTimeout(stageTimer);
            window.clearTimeout(finishingTimer);
        };
    }, [showLoading]);

    const submit = (event) => {
        event.preventDefault();
    setShowLoading(true);
    post('/start', {
        // A successful Inertia redirect replaces this page. Closing the
        // overlay in `onFinish` makes it flash away before that page renders.
        onError: () => setShowLoading(false),
    });
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

            <main className="cosmic-start relative min-h-screen overflow-hidden bg-[#09090b] text-white">
                <div className="pointer-events-none absolute inset-x-0 top-0 h-[42rem] bg-[radial-gradient(circle_at_50%_-10%,rgba(124,58,237,0.25),transparent_48%),radial-gradient(circle_at_78%_12%,rgba(16,185,129,0.15),transparent_32%)]" />

                <section className="relative mx-auto flex min-h-screen w-full max-w-6xl items-center justify-center px-5 py-10 sm:px-8 sm:py-16">
                    <div className="relative w-full max-w-4xl">
                        <div className="pointer-events-none absolute -inset-10 rounded-[3rem] bg-gradient-to-r from-violet-500/20 via-cyan-400/15 to-emerald-400/20 blur-3xl" />
                        <div className="relative overflow-hidden rounded-[2rem] border border-emerald-200/30 bg-[radial-gradient(circle_at_100%_0%,rgba(20,184,166,0.20),transparent_36%),radial-gradient(circle_at_0%_0%,rgba(124,58,237,0.23),transparent_38%),linear-gradient(145deg,#181627_0%,#0d1017_56%,#10221f_100%)] p-5 shadow-2xl shadow-black/60 sm:p-8 lg:p-12">
                            <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.025)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.025)_1px,transparent_1px)] bg-[size:42px_42px] opacity-30" />
                            <div className="relative">
                        {isReady ? (
                            <div className="mx-auto max-w-3xl py-5 sm:py-8">
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
                                        <button
                                            type="button"
                                            disabled
                                            className="cursor-not-allowed rounded-xl bg-white/60 px-5 py-3 text-sm font-semibold text-[#121217]/70"
                                        >
                                            Select a plan above
                                        </button>
                                    )}
                                    <Link href="/start" className="rounded-xl border border-white/10 px-5 py-3 text-sm font-semibold text-slate-200 transition hover:border-white/25 hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400">Create another draft</Link>
                                </div>
                                <p className="mt-7 text-sm text-slate-400">This is a private draft—not a published website.</p>
                            </div>
                        ) : isFailed ? (
                            <div className="mx-auto max-w-3xl py-5 sm:py-8">
                                <div className="grid h-12 w-12 place-items-center rounded-2xl bg-rose-400/15 text-xl text-rose-300">!</div>
                                <h2 className="mt-7 text-3xl font-semibold tracking-tight">We could not prepare that draft.</h2>
                                <p className="mt-4 max-w-lg leading-7 text-slate-300">{trial.error_message || 'Please try again in a moment.'}</p>
                                <Link href="/start" className="mt-8 inline-flex rounded-xl bg-white px-5 py-3 text-sm font-semibold text-[#121217] transition hover:bg-slate-200">Try again</Link>
                            </div>
                        ) : (
                            <form onSubmit={submit} noValidate className="relative mx-auto max-w-3xl">
                                <div className="mx-auto mb-9 max-w-2xl text-center">
                                    <Link href="/" className="inline-flex items-center gap-3 font-semibold tracking-tight text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-300 focus-visible:ring-offset-4 focus-visible:ring-offset-[#13151d]">
                                        <span className="grid h-12 w-12 place-items-center rounded-2xl bg-gradient-to-br from-violet-500 via-cyan-400 to-emerald-400 text-xl font-bold text-[#10121c] shadow-lg shadow-cyan-950/50">C</span>
                                        <span className="text-2xl">Cosmic <span className="text-emerald-300">CMS</span></span>
                                    </Link>
                                    <p className="mx-auto mt-8 inline-flex items-center gap-2 rounded-full border border-white/15 bg-black/20 px-3.5 py-1.5 text-xs font-medium text-slate-200">
                                        <span className="h-1.5 w-1.5 rounded-full bg-emerald-300 shadow-[0_0_12px_rgba(110,231,183,0.9)]" />
                                        Build your first draft in minutes
                                    </p>
                                    <h1 className="mt-5 text-balance text-4xl font-semibold leading-[1.04] tracking-[-0.045em] text-white sm:text-5xl">
                                        Describe your business. Start with a real website draft.
                                    </h1>
                                    <p className="mx-auto mt-5 max-w-xl text-base leading-7 text-slate-300 sm:text-lg">
                                        Tell Cosmic what you do, who you serve, and what you want your website to help visitors do.
                                    </p>
                                </div>

                                <Field label="Describe your business" error={errors.prompt}>
                                    <div className="relative">
                                    <textarea
                                        value={prompt}
                                        onChange={handlePromptChange}
                                        className={`${fieldClass} min-h-52 resize-y border-white/15 bg-black/20 px-5 py-5 leading-7 shadow-inner shadow-black/20`}
                                        placeholder=""
                                    />
                                    {prompt.length === 0 && animatedPrompt && (
                                        <span aria-hidden="true" className="pointer-events-none absolute left-5 right-5 top-5 whitespace-pre-line text-sm leading-7 text-slate-500">
                                            {animatedPrompt}
                                            <span className="ml-0.5 inline-block h-4 w-px animate-pulse bg-emerald-300/80 align-[-3px]" />
                                        </span>
                                    )}
                                    </div>
                                </Field>

                                <div className="mt-5">
                                    <p className="text-center text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Quick ideas</p>
                                    <div className="mt-3 flex flex-wrap justify-center gap-2">
                                        {quickIdeas.map((idea) => (
                                            <button
                                                key={idea.label}
                                                type="button"
                                                onClick={() => typeQuickIdea(idea.prompt)}
                                                className="rounded-full border border-white/10 bg-black/20 px-3.5 py-2 text-xs font-medium text-slate-100 transition hover:border-violet-300/60 hover:bg-violet-400/15 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400"
                                            >
                                                {idea.label}
                                            </button>
                                        ))}
                                    </div>
                                </div>

                                <button type="submit" disabled={processing || prompt.trim().length < 20} className="mt-8 flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-violet-500 via-cyan-500 to-emerald-400 px-5 py-4 text-base font-semibold text-white shadow-xl shadow-cyan-950/30 transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-60 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-200 focus-visible:ring-offset-2 focus-visible:ring-offset-[#13151a]">
                                    {processing ? 'Preparing your draft…' : 'Generate my website draft'}
                                </button>
                                <p className="mt-5 text-center text-xs leading-5 text-slate-400">Your private draft is saved for you to review. <span className="text-emerald-300">Nothing is published automatically.</span></p>

                                {showLoading && (
                                    <div className="absolute inset-0 z-10 grid place-items-center rounded-2xl bg-[#15151a]/95 px-6 text-center backdrop-blur-sm" role="status" aria-live="polite">
                                        <div className="w-full max-w-xl rounded-2xl border border-white/10 bg-[#151519]/95 px-5 py-7 shadow-2xl shadow-black/60 sm:px-8 sm:py-8">
                                            <div className="relative mx-auto h-16 w-16" aria-hidden="true">
                                                <div className="cosmic-start-spinner absolute inset-0 rounded-full" />
                                                <div className="absolute inset-[3px] grid place-items-center rounded-full bg-[#17171d] text-xl text-cyan-300 shadow-lg shadow-violet-950/50">✦</div>
                                            </div>
                                            <p className="mt-5 text-xs font-semibold uppercase tracking-[0.2em] text-violet-300">Cosmic AI</p>
                                            <h3 className="mt-2 text-2xl font-semibold tracking-tight text-white">Building your page</h3>
                                            <p className="mt-3 text-sm text-slate-300">{loadingStage}</p>

                                            <div className="mt-7 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                                {generationSteps.map((step, index) => {
                                                    const isComplete = loadingProgress >= step.threshold;
                                                    const isCurrent = !isComplete && (index === 0 || loadingProgress >= generationSteps[index - 1].threshold);

                                                    return (
                                                        <div
                                                            key={step.label}
                                                            className={`flex items-center gap-2 rounded-lg border px-2.5 py-2 text-left text-[10px] font-medium sm:text-xs ${
                                                                isComplete
                                                                    ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-200'
                                                                    : isCurrent
                                                                        ? 'border-violet-400/45 bg-violet-400/10 text-violet-100'
                                                                        : 'border-white/10 bg-white/[0.02] text-slate-500'
                                                            }`}
                                                        >
                                                            <span className={`grid h-4 w-4 shrink-0 place-items-center rounded-full text-[9px] ${isComplete ? 'bg-emerald-400 text-emerald-950' : isCurrent ? 'bg-violet-400 text-white' : 'bg-white/10 text-slate-400'}`}>
                                                                {isComplete ? '✓' : index + 1}
                                                            </span>
                                                            <span className="leading-4">{step.label}</span>
                                                        </div>
                                                    );
                                                })}
                                            </div>

                                            <div className="mt-6 h-2 overflow-hidden rounded-full bg-white/10">
                                                <div className="h-full rounded-full bg-gradient-to-r from-violet-500 via-cyan-400 to-emerald-400 transition-[width] duration-200" style={{ width: `${loadingProgress}%` }} />
                                            </div>
                                            <div className="mt-3 flex items-center justify-between text-xs text-slate-500">
                                                <span>Generating...</span>
                                                <span>{loadingProgress}%</span>
                                            </div>
                                        </div>
                                        <div className="hidden">
                                            <div className="mx-auto grid h-14 w-14 place-items-center rounded-2xl border border-violet-400/30 bg-violet-400/10 text-2xl shadow-lg shadow-violet-950/40">✨</div>
                                            <p className="mt-6 text-xs font-semibold uppercase tracking-[0.18em] text-violet-300">Cosmic AI is working</p>
                                            <h3 className="mt-2 text-xl font-semibold text-white">Building your website draft</h3>
                                            <p className="mt-3 text-sm leading-6 text-slate-400">Understanding your business, choosing a suitable structure, and writing editable content.</p>
                                            <div className="mt-6 h-2 overflow-hidden rounded-full bg-white/10">
                                                <div className="h-full w-2/3 animate-pulse rounded-full bg-gradient-to-r from-violet-400 via-fuchsia-400 to-cyan-300" />
                                            </div>
                                            <p className="mt-3 text-xs text-slate-500">Preparing your private preview…</p>
                                        </div>
                                    </div>
                                )}
                            </form>
                        )}
                    </div>
                </div>
            </div>
                </section>

            </main>
        </>
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
