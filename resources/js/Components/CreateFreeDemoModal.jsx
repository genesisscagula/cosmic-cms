import axios from 'axios';
import { useEffect, useMemo, useRef, useState } from 'react';
import { handoffToBuilder } from '@/Support/builderHandoff';
import { trackCosmicEvent } from '@/Analytics/tracking';
import '../../css/start.css';

const industryGroups = [
    {
        label: 'Business & Professional',
        options: [
            'Accounting & Bookkeeping', 'Advertising & Marketing', 'Architecture', 'Business Consulting',
            'Financial Services', 'Insurance', 'Law Firm', 'Mortgage & Lending', 'Property Management',
            'Real Estate', 'Recruitment & Staffing', 'Technology & IT Services', 'SaaS / Software',
            'Web Design & Development', 'Cybersecurity', 'Telecommunications',
        ],
    },
    {
        label: 'Construction & Home Services',
        options: [
            'Construction', 'Glass & Aluminum Installation', 'Roofing', 'Plumbing', 'Electrical Services',
            'HVAC / Air Conditioning', 'Landscaping', 'Cleaning Services', 'Pest Control', 'Painting',
            'Carpentry', 'Flooring', 'Solar Installation', 'Security Systems', 'Handyman Services',
            'Interior Design', 'Architecture & Design', 'Pool Services', 'Garage Door Services',
        ],
    },
    {
        label: 'Health & Wellness',
        options: [
            'Medical Clinic', 'Dental Clinic', 'Veterinary Clinic', 'Pharmacy', 'Optometry', 'Chiropractic',
            'Physical Therapy', 'Mental Health & Counseling', 'Wellness Center', 'Home Healthcare',
            'Senior Care', 'Nutrition & Dietetics', 'Dermatology', 'Aesthetic Clinic',
        ],
    },
    {
        label: 'Food, Hospitality & Events',
        options: [
            'Restaurant', 'Cafe / Coffee Shop', 'Bakery', 'Catering', 'Food Delivery', 'Hotel', 'Resort',
            'Travel Agency', 'Tour Operator', 'Event Planning', 'Wedding Services', 'Venue & Events',
        ],
    },
    {
        label: 'Beauty, Fitness & Lifestyle',
        options: [
            'Beauty Salon', 'Spa', 'Barbershop', 'Nail Salon', 'Cosmetics & Skincare', 'Fitness Gym',
            'Personal Training', 'Yoga / Pilates', 'Martial Arts', 'Sports Club', 'Dance Studio',
        ],
    },
    {
        label: 'Retail & Ecommerce',
        options: [
            'Ecommerce Store', 'Fashion & Apparel', 'Jewelry', 'Furniture', 'Electronics', 'Grocery',
            'Florist', 'Pet Store', 'Home & Living', 'Gifts & Crafts', 'Wholesale & Distribution',
        ],
    },
    {
        label: 'Automotive & Marine',
        options: [
            'Automotive Repair', 'Car Dealership', 'Car Wash', 'Auto Detailing', 'Motorcycle Services',
            'Tire Shop', 'Towing Services', 'Marine Engine Repair', 'Boat & Yacht Services',
        ],
    },
    {
        label: 'Education & Training',
        options: [
            'School / Academy', 'College / University', 'Preschool / Daycare', 'Tutoring',
            'Training Center', 'Online Courses', 'Language School', 'Driving School',
        ],
    },
    {
        label: 'Industrial & Logistics',
        options: [
            'Manufacturing', 'Engineering Services', 'Logistics & Freight', 'Courier & Delivery',
            'Warehousing', 'Agriculture', 'Farm & Agribusiness', 'Food Manufacturing',
            'Equipment Rental', 'Industrial Supplies',
        ],
    },
    {
        label: 'Creative & Media',
        options: [
            'Photography', 'Videography', 'Graphic Design', 'Creative Agency', 'Printing Services',
            'Music & Entertainment', 'Content Creator', 'Media Production', 'Portfolio / Personal Brand',
        ],
    },
    {
        label: 'Community & Organizations',
        options: [
            'Nonprofit Organization', 'Community Organization', 'Religious Organization',
            'Professional Association', 'Government / Public Service', 'Charity / Foundation',
        ],
    },
];

const allIndustries = industryGroups.flatMap((group) => group.options);

const generationSteps = [
    { label: 'Understand brief', threshold: 12 },
    { label: 'Plan sections', threshold: 30 },
    { label: 'Create content', threshold: 52 },
    { label: 'Prepare images', threshold: 68 },
    { label: 'Match theme', threshold: 78 },
    { label: 'Finalize website', threshold: 88 },
];

// Reused from the previous /start experience. Progress is intentionally
// perceived progress only: it never enters 90–100% until /start succeeds.
const startGenerationStages = [
    { message: 'Understanding your request...', target: 12, duration: 1800 },
    { message: 'Planning the right sections...', target: 30, duration: 2600 },
    { message: 'Writing professional content...', target: 52, duration: 5000 },
    { message: 'Preparing the right images...', target: 68, duration: 7000 },
    { message: 'Matching colors and theme...', target: 78, duration: 7000 },
    { message: 'Finalizing your website...', target: 86, duration: 9000 },
];

function getErrorMessage(error, fallback) {
    const errors = error?.response?.data?.errors;
    if (errors && typeof errors === 'object') {
        const first = Object.values(errors).flat().find(Boolean);
        if (first) return String(first);
    }

    return error?.response?.data?.message || error?.message || fallback;
}

export default function CreateFreeDemoModal({ open, source = 'home', initialPrompt = '', onClose }) {
    const [websiteName, setWebsiteName] = useState('');
    const [industrySearch, setIndustrySearch] = useState('');
    const [selectedIndustry, setSelectedIndustry] = useState('');
    const [customIndustry, setCustomIndustry] = useState('');
    const [email, setEmail] = useState('');
    const [additionalPrompt, setAdditionalPrompt] = useState('');
    const [industryOpen, setIndustryOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const [generationComplete, setGenerationComplete] = useState(false);
    const [status, setStatus] = useState('');
    const [error, setError] = useState('');
    const [loadingStage, setLoadingStage] = useState(startGenerationStages[0].message);
    const [loadingProgress, setLoadingProgress] = useState(0);
    const [loadingNotice, setLoadingNotice] = useState('');
    const appliedPromptRef = useRef('');

    useEffect(() => {
        if (!open) return;
        const incomingPrompt = String(initialPrompt || '').trim();
        if (!incomingPrompt || appliedPromptRef.current === incomingPrompt) return;

        setAdditionalPrompt(incomingPrompt);
        appliedPromptRef.current = incomingPrompt;
        setError('');
    }, [open, initialPrompt]);

    useEffect(() => {
        if (open) return;
        appliedPromptRef.current = '';
    }, [open]);


    useEffect(() => {
        if (!busy || generationComplete) return undefined;

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
                if (cancelled) return;
                setLoadingStage('Still building your draft. This can take up to a minute...');
                setLoadingNotice('Please keep this tab open. Cosmic will take you to the Builder automatically when Home and your starter brand are ready.');
            }, 9000);
        };

        const playStage = (stageIndex) => {
            if (cancelled) return;

            const stage = startGenerationStages[stageIndex];
            setLoadingStage(stage.message);

            const advanceProgress = () => {
                if (cancelled || progress >= stage.target) return;
                progress = Math.min(stage.target, progress + Math.max(1, Math.ceil((stage.target - progress) / 18)));
                setLoadingProgress(progress);
                progressTimer = window.setTimeout(advanceProgress, 260);
            };

            advanceProgress();
            if (stageIndex < startGenerationStages.length - 1) {
                stageTimer = window.setTimeout(() => playStage(stageIndex + 1), stage.duration);
            } else {
                stageTimer = window.setTimeout(holdAtFinalizing, stage.duration);
            }
        };

        setLoadingProgress(0);
        setLoadingNotice('');
        playStage(0);

        return () => {
            cancelled = true;
            window.clearTimeout(progressTimer);
            window.clearTimeout(stageTimer);
            window.clearTimeout(finishingTimer);
        };
    }, [busy, generationComplete]);

    const normalizedSearch = industrySearch.trim().toLowerCase();
    const filteredGroups = useMemo(() => {
        if (!normalizedSearch) return industryGroups;

        return industryGroups
            .map((group) => ({
                ...group,
                options: group.options.filter((option) => option.toLowerCase().includes(normalizedSearch)),
            }))
            .filter((group) => group.options.length > 0);
    }, [normalizedSearch]);

    if (!open) return null;

    const finalIndustry = selectedIndustry === 'Other' ? customIndustry.trim() : selectedIndustry.trim();

    const selectIndustry = (industry) => {
        setSelectedIndustry(industry);
        setIndustrySearch(industry);
        setIndustryOpen(false);
        setError('');
        if (industry !== 'Other') setCustomIndustry('');
    };

    const resetAndClose = () => {
        if (busy) return;
        setWebsiteName('');
        setIndustrySearch('');
        setSelectedIndustry('');
        setCustomIndustry('');
        setEmail('');
        setAdditionalPrompt('');
        setIndustryOpen(false);
        setStatus('');
        setError('');
        setGenerationComplete(false);
        setLoadingProgress(0);
        setLoadingStage(startGenerationStages[0].message);
        setLoadingNotice('');
        appliedPromptRef.current = '';
        onClose?.();
    };

    const submit = async (event) => {
        event.preventDefault();
        if (busy) return;

        setError('');

        if (websiteName.trim().length < 2) {
            setError('Enter your website or business name.');
            return;
        }
        if (!finalIndustry) {
            setError('Select your industry, or choose Other and enter it.');
            return;
        }
        if (!/^\S+@\S+\.\S+$/.test(email.trim())) {
            setError('Enter a valid email address for your private demo link.');
            return;
        }

        setGenerationComplete(false);
        setBusy(true);
        try {
            setStatus('Creating your free demo…');
            trackCosmicEvent('trial_demo_submit', { source, industry: finalIndustry });

            const response = await axios.post('/start', {
                website_name: websiteName.trim(),
                industry: finalIndustry,
                email: email.trim(),
                additional_prompt: additionalPrompt.trim() || null,
            }, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const payload = response?.data?.data ?? response?.data ?? {};
            const token = payload?.trial_token ?? payload?.token ?? payload?.trial?.token;
            const builderUrl = payload?.builder_url ?? payload?.redirect_url ?? payload?.url
                ?? response?.headers?.['x-cosmic-builder-url'];

            if (!token || !builderUrl) {
                throw new Error('The demo was created, but the private Builder link could not be resolved.');
            }

            trackCosmicEvent('trial_demo_email_captured', { source, industry: finalIndustry });
            setStatus('Demo ready — opening Builder…');
            setGenerationComplete(true);
            setLoadingStage('Website ready — opening Builder...');
            setLoadingNotice('');
            setLoadingProgress(100);
            await new Promise((resolve) => window.setTimeout(resolve, 320));
            handoffToBuilder(builderUrl);
        } catch (requestError) {
            setStatus('');
            setError(getErrorMessage(requestError, 'We could not create your demo. Please try again.'));
            setBusy(false);
            setGenerationComplete(false);
            setLoadingProgress(0);
            setLoadingStage(startGenerationStages[0].message);
            setLoadingNotice('');
        }
    };

    if (busy) {
        return (
            <div className="cosmic-create-demo-modal cosmic-start fixed inset-0 z-[12000] flex items-center justify-center overflow-y-auto bg-slate-950/70 p-4 backdrop-blur-md sm:p-8" role="status" aria-live="polite">
                <div className="relative w-full max-w-3xl">
                    <div className="pointer-events-none absolute -inset-8 rounded-[3rem] bg-gradient-to-r from-violet-500/25 via-cyan-400/20 to-emerald-400/25 blur-3xl" />
                    <section className="relative overflow-hidden rounded-[2rem] border border-emerald-200 bg-white/95 p-6 text-slate-900 shadow-2xl shadow-slate-950/25 sm:p-9">
                        <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(rgba(16,185,129,0.035)_1px,transparent_1px),linear-gradient(90deg,rgba(16,185,129,0.035)_1px,transparent_1px)] bg-[size:42px_42px] opacity-60" />
                        <div className="relative">
                            <div className="mx-auto grid h-16 w-16 place-items-center rounded-2xl border border-violet-200 bg-violet-50 shadow-lg shadow-violet-100">
                                <span className="cosmic-start-spinner block h-8 w-8 rounded-full" aria-hidden="true" />
                            </div>
                            <p className="mt-6 text-center text-[10px] font-black uppercase tracking-[.2em] text-emerald-700">Cosmic AI is working</p>
                            <h3 className="mt-2 text-center text-2xl font-black tracking-[-.035em] text-[#10203b] sm:text-3xl">Building {websiteName || 'your website'}...</h3>
                            <p className="mt-3 text-center text-sm font-semibold text-slate-600">{loadingStage}</p>

                            <div className="mt-7 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                {generationSteps.map((step, index) => {
                                    const isComplete = loadingProgress >= step.threshold;
                                    const isCurrent = !isComplete && (index === 0 || loadingProgress >= generationSteps[index - 1].threshold);

                                    return (
                                        <div key={step.label} className={`flex items-center gap-2 rounded-lg border px-2.5 py-2 text-left text-[10px] font-medium sm:text-xs ${isComplete ? 'border-emerald-300 bg-emerald-50 text-emerald-800' : isCurrent ? 'border-violet-300 bg-violet-50 text-violet-800' : 'border-slate-200 bg-slate-50 text-slate-500'}`}>
                                            <span className={`grid h-4 w-4 shrink-0 place-items-center rounded-full text-[9px] ${isComplete ? 'bg-emerald-500 text-white' : isCurrent ? 'bg-violet-500 text-white' : 'bg-slate-200 text-slate-500'}`}>
                                                {isComplete ? '✓' : index + 1}
                                            </span>
                                            <span className="leading-4">{step.label}</span>
                                        </div>
                                    );
                                })}
                            </div>

                            <div className="mt-6 h-2 overflow-hidden rounded-full bg-slate-200">
                                <div className="h-full rounded-full bg-gradient-to-r from-violet-500 via-cyan-400 to-emerald-400 transition-[width] duration-200" style={{ width: `${loadingProgress}%` }} />
                            </div>
                            <div className="mt-3 flex items-center justify-between text-xs font-semibold text-slate-600">
                                <span>{loadingProgress >= 100 ? 'Ready' : 'Generating...'}</span>
                                <span>{loadingProgress}%</span>
                            </div>

                            {loadingNotice && (
                                <p className="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-800">{loadingNotice}</p>
                            )}
                            <p className="mt-5 text-center text-[11px] font-semibold text-slate-400">Keep this tab open. Your private Builder link is also being sent to {email || 'your email'}.</p>
                        </div>
                    </section>
                </div>
            </div>
        );
    }

    return (
        <div
            className="cosmic-create-demo-modal fixed inset-0 z-[12000] flex items-center justify-center bg-slate-950/60 p-3 backdrop-blur-sm sm:p-6"
            role="presentation"
            onMouseDown={(event) => {
                if (event.target === event.currentTarget) resetAndClose();
            }}
        >
            <section
                role="dialog"
                aria-modal="true"
                aria-labelledby="create-free-demo-title"
                className="w-full max-w-[720px] overflow-hidden rounded-[28px] border border-slate-200 bg-white text-slate-900 shadow-2xl"
            >
                <header className="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-5 sm:px-7">
                    <div>
                        <div className="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-[10px] font-black uppercase tracking-[.16em] text-emerald-700">
                            <span>✦</span> Free website demo
                        </div>
                        <h2 id="create-free-demo-title" className="mt-3 text-2xl font-black tracking-[-.03em] text-[#10203b] sm:text-[30px]">
                            Create your free demo
                        </h2>
                        <p className="mt-2 max-w-xl text-sm leading-6 text-slate-500">
                            Give us the basics and Luna will prepare a complete editable starting point for your business.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={resetAndClose}
                        disabled={busy}
                        aria-label="Close Create Free Demo"
                        className="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-xl font-medium text-slate-500 transition hover:bg-slate-50 disabled:opacity-40"
                    >
                        ×
                    </button>
                </header>

                <form onSubmit={submit} className="px-5 py-5 sm:px-7 sm:py-6">
                    <div className="grid gap-5 sm:grid-cols-2">
                        <label className="block sm:col-span-2">
                            <span className="text-xs font-black text-slate-700">Website Name</span>
                            <input
                                type="text"
                                value={websiteName}
                                onChange={(event) => setWebsiteName(event.target.value)}
                                disabled={busy}
                                maxLength={120}
                                autoComplete="organization"
                                placeholder="e.g. Summit Line Glass & Aluminum"
                                className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100 disabled:bg-slate-50"
                            />
                        </label>

                        <div className="relative sm:col-span-2">
                            <label className="block">
                                <span className="text-xs font-black text-slate-700">Industry</span>
                                <div className="relative mt-2">
                                    <input
                                        type="search"
                                        value={industrySearch}
                                        onChange={(event) => {
                                            setIndustrySearch(event.target.value);
                                            setSelectedIndustry('');
                                            setIndustryOpen(true);
                                        }}
                                        onFocus={() => setIndustryOpen(true)}
                                        disabled={busy}
                                        autoComplete="off"
                                        placeholder="Search or select your industry"
                                        className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 pr-11 text-sm font-semibold text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100 disabled:bg-slate-50"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setIndustryOpen((value) => !value)}
                                        disabled={busy}
                                        tabIndex={-1}
                                        className="absolute inset-y-0 right-0 grid w-11 place-items-center text-sm text-slate-400"
                                        aria-label="Toggle industry options"
                                    >
                                        ▾
                                    </button>
                                </div>
                            </label>

                            {industryOpen && !busy && (
                                <div className="absolute left-0 right-0 top-[76px] z-20 max-h-[310px] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-2xl shadow-slate-900/15">
                                    {filteredGroups.length ? filteredGroups.map((group) => (
                                        <div key={group.label} className="py-1">
                                            <p className="px-3 pb-1 pt-2 text-[9px] font-black uppercase tracking-[.16em] text-slate-400">{group.label}</p>
                                            {group.options.map((option) => (
                                                <button
                                                    key={option}
                                                    type="button"
                                                    onClick={() => selectIndustry(option)}
                                                    className="cosmic-create-demo-industry-option block w-full rounded-lg px-3 py-2.5 text-left text-sm font-semibold text-slate-700 transition hover:bg-emerald-50 hover:text-emerald-800"
                                                >
                                                    {option}
                                                </button>
                                            ))}
                                        </div>
                                    )) : (
                                        <p className="px-3 py-4 text-sm text-slate-500">No matching industry. Choose Other below.</p>
                                    )}
                                    <div className="sticky bottom-0 border-t border-slate-100 bg-white pt-2">
                                        <button
                                            type="button"
                                            onClick={() => selectIndustry('Other')}
                                            className="cosmic-create-demo-industry-option cosmic-create-demo-industry-other block w-full rounded-lg bg-slate-50 px-3 py-2.5 text-left text-sm font-black text-slate-700 transition hover:bg-emerald-50 hover:text-emerald-800"
                                        >
                                            Other — enter my industry
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>

                        {selectedIndustry === 'Other' && (
                            <label className="block sm:col-span-2">
                                <span className="text-xs font-black text-slate-700">Enter your industry</span>
                                <input
                                    type="text"
                                    value={customIndustry}
                                    onChange={(event) => setCustomIndustry(event.target.value)}
                                    disabled={busy}
                                    maxLength={120}
                                    autoFocus
                                    placeholder="e.g. Glass fabrication and installation"
                                    className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100 disabled:bg-slate-50"
                                />
                            </label>
                        )}

                        <label className="block sm:col-span-2">
                            <span className="text-xs font-black text-slate-700">Email</span>
                            <input
                                type="email"
                                value={email}
                                onChange={(event) => setEmail(event.target.value)}
                                disabled={busy}
                                maxLength={255}
                                autoComplete="email"
                                placeholder="you@example.com"
                                className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100 disabled:bg-slate-50"
                            />
                            <span className="mt-2 block text-[11px] leading-5 text-slate-500">We’ll email your private editable demo link here.</span>
                        </label>

                        <label className="block sm:col-span-2">
                            <span className="flex items-center justify-between gap-3 text-xs font-black text-slate-700">
                                <span>Additional Instructions</span>
                                <span className="font-bold text-slate-400">Optional</span>
                            </span>
                            <textarea
                                value={additionalPrompt}
                                onChange={(event) => setAdditionalPrompt(event.target.value)}
                                disabled={busy}
                                maxLength={1000}
                                rows={4}
                                placeholder="Anything specific? e.g. Use a premium dark style, highlight residential projects, add a quote request CTA…"
                                className="mt-2 w-full resize-none rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium leading-6 text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100 disabled:bg-slate-50"
                            />
                        </label>
                    </div>

                    {error && (
                        <div className="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700" role="alert">
                            {error}
                        </div>
                    )}

                    {status && !error && (
                        <div className="mt-5 flex items-center gap-3 rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status" aria-live="polite">
                            <span className="h-4 w-4 animate-spin rounded-full border-2 border-emerald-200 border-t-emerald-700" />
                            {status}
                        </div>
                    )}

                    <div className="mt-6 flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-center text-[11px] font-semibold text-slate-400 sm:text-left">No credit card required.</p>
                        <button
                            type="submit"
                            disabled={busy}
                            className="inline-flex min-h-12 items-center justify-center rounded-xl bg-emerald-700 px-6 py-3 text-sm font-black text-white shadow-lg shadow-emerald-200 transition hover:-translate-y-0.5 hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {busy ? 'Creating Demo…' : 'Build My Demo ✦'}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    );
}
