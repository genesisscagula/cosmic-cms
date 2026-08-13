import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Link, useForm } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useMemo, useState } from 'react';

const fieldClass = 'mt-2 block w-full rounded-xl border-slate-700 bg-[#0f1013] px-3.5 py-3 text-sm text-white shadow-none placeholder:text-slate-600 focus:border-emerald-400 focus:ring-emerald-400';
const textareaClass = `${fieldClass} min-h-28 resize-y`;

const plans = {
    starter: { name: 'Starter', price: '$49', credits: '500 free credits included once', welcome: 'Posts / Updates included' },
    growth: { name: 'Growth', price: '$79', credits: '1,000 free credits included once', welcome: 'Full commerce included' },
    pro: { name: 'Pro', price: '$129', credits: '1,500 free credits included once', welcome: '15 free Owned Sparks' },
    agency_starter: { name: 'Starter Agency', price: '$99', credits: '750 free credits included once', welcome: 'Up to 3 websites' },
    agency_growth: { name: 'Growth Agency', price: '$199', credits: '1,500 free credits included once', welcome: 'Up to 10 websites' },
    agency_pro: { name: 'Pro Agency', price: '$399', credits: '3,000 free credits included once', welcome: 'Unlimited websites under fair use' },
};

const industries = [
    'Construction', 'Restaurant', 'Coffee Shop', 'Bakery', 'Dentist', 'Medical', 'Law Firm', 'Fitness',
    'Real Estate', 'Hotel', 'Travel', 'Technology', 'Education', 'Finance', 'Electrician', 'Plumbing',
    'Cleaning', 'Landscaping', 'Automotive', 'Salon', 'Other',
];

const slugify = (value) => value
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 60);

function StepIndicator({ currentStep }) {
    const items = [
        { number: 1, label: 'Account' },
        { number: 2, label: 'Business' },
        { number: 3, label: 'Review' },
    ];

    return (
        <div className="mb-8 grid grid-cols-3 gap-2" aria-label="Registration progress">
            {items.map((item) => {
                const active = currentStep === item.number;
                const complete = currentStep > item.number;
                return (
                    <div key={item.number} className="relative text-center">
                        <div className={`mx-auto flex h-9 w-9 items-center justify-center rounded-full border text-sm font-semibold transition ${complete ? 'border-emerald-300 bg-emerald-300 text-slate-950' : active ? 'border-emerald-300 bg-emerald-300/15 text-emerald-200' : 'border-white/10 bg-white/5 text-slate-500'}`}>
                            {complete ? '✓' : item.number}
                        </div>
                        <p className={`mt-2 text-xs font-medium ${active || complete ? 'text-slate-200' : 'text-slate-500'}`}>{item.label}</p>
                        {item.number < 3 && <span className={`absolute left-[calc(50%+1.5rem)] right-[calc(-50%+1.5rem)] top-[1.1rem] h-px ${complete ? 'bg-emerald-300/70' : 'bg-white/10'}`} />}
                    </div>
                );
            })}
        </div>
    );
}

export default function Register({ trialToken = '', trialEmail = '', trialPlan = '' }) {
    const storageKey = `cosmic:onboarding:${trialToken || 'new'}`;
    const selectedPlan = plans[trialPlan] || plans.starter;
    const stepStorageKey = `${storageKey}:step`;
    const [step, setStep] = useState(() => {
        const storedStep = Number(window.sessionStorage.getItem(stepStorageKey));
        return [1, 2, 3].includes(storedStep) ? storedStep : 1;
    });
    const [clientErrors, setClientErrors] = useState({});
    const [slugTouched, setSlugTouched] = useState(false);
    const [submitError, setSubmitError] = useState('');
    const [submitMessage, setSubmitMessage] = useState('');
    const [processing, setProcessing] = useState(false);

    const { data, setData, errors } = useForm({
        name: '',
        email: trialEmail || '',
        password: '',
        password_confirmation: '',
        trial_token: trialToken || '',
        selected_plan: trialPlan || 'starter',
        website_name: '',
        website_url: '',
        industry: '',
        business_description: '',
        location: '',
    });

    useEffect(() => {
        try {
            const saved = JSON.parse(window.localStorage.getItem(storageKey) || 'null');
            if (!saved) return;
            Object.entries(saved).forEach(([key, value]) => {
                if (key !== 'password' && key !== 'password_confirmation' && value !== undefined) setData(key, value);
            });
        } catch (_) {
            // A damaged browser draft should never block registration.
        }
    }, []);

    useEffect(() => {
        const safeDraft = { ...data, password: '', password_confirmation: '' };
        window.localStorage.setItem(storageKey, JSON.stringify(safeDraft));
    }, [data]);

    useEffect(() => {
        window.sessionStorage.setItem(stepStorageKey, String(step));
    }, [step, stepStorageKey]);

    const websitePreview = useMemo(() => data.website_url ? `${data.website_url}.cosmiccms.com` : 'your-business.cosmiccms.com', [data.website_url]);

    const updateWebsiteName = (value) => {
        setData('website_name', value);
        if (!slugTouched) setData('website_url', slugify(value));
    };

    const validateAccount = () => {
        const next = {};
        if (!data.name.trim()) next.name = 'Enter your name.';
        if (!/^\S+@\S+\.\S+$/.test(data.email)) next.email = 'Enter a valid email address.';
        if (data.password.length < 8) next.password = 'Use at least 8 characters.';
        if (data.password !== data.password_confirmation) next.password_confirmation = 'Passwords do not match.';
        setClientErrors(next);
        return Object.keys(next).length === 0;
    };

    const validateBusiness = () => {
        const next = {};
        if (!data.website_name.trim()) next.website_name = 'Enter your website name.';
        if (!data.website_url.trim()) next.website_url = 'Choose a website address.';
        if (!data.industry) next.industry = 'Select an industry.';
        if (data.business_description.trim().length < 20) next.business_description = 'Tell us a little more about your company (at least 20 characters).';
        if (!data.location.trim()) next.location = 'Enter your business location.';
        setClientErrors(next);
        return Object.keys(next).length === 0;
    };

    const moveToStep = (targetStep) => {
        setClientErrors({});
        setSubmitError('');
        setStep(targetStep);
        window.sessionStorage.setItem(stepStorageKey, String(targetStep));
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const nextStep = () => {
        if (step === 1) {
            if (!validateAccount()) return;
            moveToStep(2);
            return;
        }

        if (step === 2) {
            if (!validateBusiness()) return;
            moveToStep(3);
        }
    };

    const submit = (event) => {
        event.preventDefault();
        if (step !== 3) return nextStep();

        if (processing) return;

        setProcessing(true);
        setSubmitError('');
        setSubmitMessage('Creating your account…');

        axios.post(route('register'), data, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        }).then((response) => {
            const redirectUrl = response.data?.redirect_url;

            if (!redirectUrl) {
                throw new Error('The payment setup page was not returned.');
            }

            window.localStorage.removeItem(storageKey);
            window.sessionStorage.removeItem(stepStorageKey);
            setSubmitMessage('Account created. Opening secure checkout…');

            // Use a native browser navigation after the authenticated session has
            // been created. This avoids Inertia re-hydrating the guest register
            // page and resetting the wizard back to step 1 after a successful POST.
            window.location.assign(redirectUrl);
        }).catch((requestError) => {
            const serverErrors = requestError.response?.data?.errors || {};
            const accountFields = ['name', 'email', 'password', 'password_confirmation'];
            const businessFields = ['website_name', 'website_url', 'industry', 'business_description', 'location'];

            setSubmitMessage('');

            if (accountFields.some((field) => serverErrors[field])) moveToStep(1);
            else if (businessFields.some((field) => serverErrors[field])) moveToStep(2);

            setSubmitError(
                Object.values(serverErrors || {}).flat()[0]
                || requestError.response?.data?.message
                || requestError.message
                || 'We could not create your account. Review the highlighted fields and try again.'
            );
            window.scrollTo({ top: 0, behavior: 'smooth' });
            setProcessing(false);
        });
    };

    const errorFor = (field) => clientErrors[field] || errors[field];

    return (
        <GuestLayout wide title="Create your Cosmic CMS account" subtitle="Set up your account and business details. You can review everything before continuing.">
            <StepIndicator currentStep={step} />

            {trialToken && (
                <div className="mb-6 rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm leading-5 text-emerald-100">
                    Your generated landing page will be preserved and attached to this account.
                    <span className="mt-1 block text-emerald-200">Selected plan: {selectedPlan.name}.</span>
                </div>
            )}

            <form onSubmit={submit} noValidate onKeyDown={(event) => {
                if (event.key === 'Enter' && step < 3 && event.target.tagName !== 'TEXTAREA') {
                    event.preventDefault();
                    nextStep();
                }
            }}>
                {submitMessage && !submitError && (
                    <div className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status">
                        {submitMessage}
                    </div>
                )}
                {submitError && (
                    <div className="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700" role="alert">
                        {submitError}
                    </div>
                )}
                {step === 1 && (
                    <section className="grid gap-5 md:grid-cols-2">
                        <div className="md:col-span-2">
                            <h2 className="text-lg font-semibold text-white">Account details</h2>
                            <p className="mt-1 text-sm text-slate-400">Use an email you can access. We’ll use it for your account and saved website links.</p>
                        </div>
                        <div>
                            <InputLabel htmlFor="name" value="Your name" className="text-sm font-medium text-slate-200" />
                            <TextInput id="name" value={data.name} className={fieldClass} autoComplete="name" isFocused onChange={(event) => setData('name', event.target.value)} required />
                            <InputError message={errorFor('name')} className="mt-2 text-rose-300" />
                        </div>
                        <div>
                            <InputLabel htmlFor="email" value="Email address" className="text-sm font-medium text-slate-200" />
                            <TextInput id="email" type="email" value={data.email} className={fieldClass} autoComplete="username" onChange={(event) => setData('email', event.target.value)} required />
                            <InputError message={errorFor('email')} className="mt-2 text-rose-300" />
                        </div>
                        <div>
                            <InputLabel htmlFor="password" value="Password" className="text-sm font-medium text-slate-200" />
                            <TextInput id="password" type="password" value={data.password} className={fieldClass} autoComplete="new-password" onChange={(event) => setData('password', event.target.value)} required />
                            <InputError message={errorFor('password')} className="mt-2 text-rose-300" />
                        </div>
                        <div>
                            <InputLabel htmlFor="password_confirmation" value="Confirm password" className="text-sm font-medium text-slate-200" />
                            <TextInput id="password_confirmation" type="password" value={data.password_confirmation} className={fieldClass} autoComplete="new-password" onChange={(event) => setData('password_confirmation', event.target.value)} required />
                            <InputError message={errorFor('password_confirmation')} className="mt-2 text-rose-300" />
                        </div>
                    </section>
                )}

                {step === 2 && (
                    <section className="grid gap-5 md:grid-cols-2">
                        <div className="md:col-span-2">
                            <h2 className="text-lg font-semibold text-white">Business details</h2>
                            <p className="mt-1 text-sm text-slate-400">We’ll use this information to prepare your website workspace and settings.</p>
                        </div>
                        <div>
                            <InputLabel htmlFor="website_name" value="Website name" className="text-sm font-medium text-slate-200" />
                            <TextInput id="website_name" value={data.website_name} className={fieldClass} placeholder="Acme Studio" onChange={(event) => updateWebsiteName(event.target.value)} required />
                            <InputError message={errorFor('website_name')} className="mt-2 text-rose-300" />
                        </div>
                        <div>
                            <InputLabel htmlFor="website_url" value="Preferred website address" className="text-sm font-medium text-slate-200" />
                            <TextInput id="website_url" value={data.website_url} className={fieldClass} placeholder="acme-studio" onChange={(event) => { setSlugTouched(true); setData('website_url', slugify(event.target.value)); }} required />
                            <p className="mt-2 text-xs text-slate-500">Preview: {websitePreview}</p>
                            <InputError message={errorFor('website_url')} className="mt-2 text-rose-300" />
                        </div>
                        <div>
                            <InputLabel htmlFor="industry" value="Industry" className="text-sm font-medium text-slate-200" />
                            <select id="industry" value={data.industry} onChange={(event) => setData('industry', event.target.value)} className={fieldClass} required>
                                <option value="">Select an industry</option>
                                {industries.map((industry) => <option key={industry} value={industry}>{industry}</option>)}
                            </select>
                            <InputError message={errorFor('industry')} className="mt-2 text-rose-300" />
                        </div>
                        <div>
                            <InputLabel htmlFor="location" value="Business location" className="text-sm font-medium text-slate-200" />
                            <TextInput id="location" value={data.location} className={fieldClass} placeholder="Ormoc City, Philippines" onChange={(event) => setData('location', event.target.value)} required />
                            <InputError message={errorFor('location')} className="mt-2 text-rose-300" />
                        </div>
                        <div className="md:col-span-2">
                            <InputLabel htmlFor="business_description" value="Tell us about your company" className="text-sm font-medium text-slate-200" />
                            <textarea id="business_description" value={data.business_description} className={textareaClass} maxLength={1000} placeholder="What does your company do, who do you serve, and what makes it different?" onChange={(event) => setData('business_description', event.target.value)} required />
                            <div className="mt-2 flex justify-between gap-3 text-xs text-slate-500"><InputError message={errorFor('business_description')} className="text-rose-300" /><span className="ml-auto">{data.business_description.length}/1000</span></div>
                        </div>
                    </section>
                )}

                {step === 3 && (
                    <section>
                        <div>
                            <h2 className="text-lg font-semibold text-white">Review your setup</h2>
                            <p className="mt-1 text-sm text-slate-400">Confirm your account, business details, and selected plan.</p>
                        </div>
                        <div className="mt-6 grid gap-4 lg:grid-cols-[1fr_0.8fr]">
                            <div className="rounded-2xl border border-white/10 bg-black/20 p-5">
                                <h3 className="text-sm font-semibold uppercase tracking-[0.16em] text-slate-400">Business</h3>
                                <dl className="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                                    <div><dt className="text-slate-500">Account</dt><dd className="mt-1 font-medium text-white">{data.name}<span className="block font-normal text-slate-400">{data.email}</span></dd></div>
                                    <div><dt className="text-slate-500">Website</dt><dd className="mt-1 font-medium text-white">{data.website_name}<span className="block font-normal text-slate-400">{websitePreview}</span></dd></div>
                                    <div><dt className="text-slate-500">Industry</dt><dd className="mt-1 font-medium text-white">{data.industry}</dd></div>
                                    <div><dt className="text-slate-500">Location</dt><dd className="mt-1 font-medium text-white">{data.location}</dd></div>
                                    <div className="sm:col-span-2"><dt className="text-slate-500">About company</dt><dd className="mt-1 leading-6 text-slate-200">{data.business_description}</dd></div>
                                </dl>
                            </div>
                            <aside className="rounded-2xl border border-emerald-300/25 bg-emerald-300/[0.07] p-5">
                                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-300">Selected plan</p>
                                <div className="mt-3 flex items-end justify-between gap-3"><h3 className="text-2xl font-bold text-white">{selectedPlan.name}</h3><p className="text-2xl font-bold text-white">{selectedPlan.price}<span className="text-sm font-normal text-slate-400">/mo</span></p></div>
                                <ul className="mt-5 space-y-3 text-sm text-slate-300">
                                    <li>✓ {selectedPlan.credits}</li>
                                    <li>✓ {selectedPlan.welcome}</li>
                                    <li>✓ Your trial landing page is preserved</li>
                                </ul>
                                <p className="mt-5 rounded-xl border border-white/10 bg-black/20 px-3 py-3 text-xs leading-5 text-slate-400">After account creation, you’ll be redirected securely to PayPal to activate this plan.</p>
                            </aside>
                        </div>
                    </section>
                )}

                <div className="mt-8 flex flex-col-reverse gap-3 border-t border-white/10 pt-6 sm:flex-row sm:justify-between">
                    <div>
                        {step > 1 ? <button type="button" onClick={() => moveToStep(Math.max(1, step - 1))} className="w-full rounded-xl border border-white/10 px-5 py-3 text-sm font-semibold text-slate-200 transition hover:bg-white/5 sm:w-auto">Back</button> : <p className="py-3 text-sm text-slate-400">Already have an account? <Link href={route('login')} className="font-medium text-emerald-300 hover:text-emerald-200">Sign in</Link></p>}
                    </div>
                    {step < 3 ? (
                        <button type="button" onClick={nextStep} className="rounded-xl bg-gradient-to-r from-emerald-400 to-cyan-300 px-6 py-3 text-sm font-semibold text-slate-950 shadow-lg shadow-emerald-400/15 transition hover:from-emerald-300 hover:to-cyan-200">Continue</button>
                    ) : (
                        <button type="submit" disabled={processing} aria-busy={processing} className="rounded-xl bg-gradient-to-r from-emerald-400 to-cyan-300 px-6 py-3 text-sm font-semibold text-slate-950 shadow-lg shadow-emerald-400/15 transition hover:from-emerald-300 hover:to-cyan-200 disabled:cursor-not-allowed disabled:opacity-60">
                            {processing ? 'Opening PayPal…' : 'Create account & continue to PayPal'}
                        </button>
                    )}
                </div>
                <InputError message={errors.trial_token} className="mt-4 text-center text-rose-300" />
            </form>
        </GuestLayout>
    );
}
