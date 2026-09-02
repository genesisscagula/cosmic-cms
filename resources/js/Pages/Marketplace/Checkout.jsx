import { Head, Link } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useState } from 'react';
import cosmicLogo from '../../../images/cosmic-cms-logo.png';

const planTone = {
    starter: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    growth: 'border-indigo-200 bg-indigo-50 text-indigo-700',
    pro: 'border-amber-200 bg-amber-50 text-amber-800',
};

export default function MarketplaceCheckout({
    authenticated = false,
    account = null,
    template,
    plan,
    registerUrl,
    loginUrl,
    startPath,
    statusPath,
    retryPath,
    initialError = '',
    marketplaceHome = '/marketplace',
    catalogPath = '/marketplace/templates',
}) {
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState(initialError || '');
    const [message, setMessage] = useState('');
    const [canRetryProvisioning, setCanRetryProvisioning] = useState(false);

    useEffect(() => {
        if (!authenticated || !statusPath) return undefined;

        let cancelled = false;
        const checkStatus = async () => {
            try {
                const { data } = await axios.get(statusPath);
                if (cancelled) return;
                setCanRetryProvisioning(Boolean(data?.can_retry_provisioning));
                if (data?.ready && data?.next_url) {
                    setMessage(data?.message || 'Your Marketplace website is ready.');
                } else if (['subscription_ready', 'paid'].includes(data?.status)) {
                    setMessage(data?.message || 'Subscription confirmed. Website setup is ready to continue.');
                }
            } catch (_) {
                // Checkout remains usable even if the optional recovery status check fails.
            }
        };

        checkStatus();
        const timer = window.setInterval(checkStatus, 5000);
        return () => { cancelled = true; window.clearInterval(timer); };
    }, [authenticated, statusPath]);

    const retryProvisioning = async () => {
        if (processing || !retryPath) return;
        setProcessing(true);
        setError('');
        try {
            const { data } = await axios.post(retryPath);
            setMessage(data?.message || 'Website setup completed.');
            if (data?.next_url) window.location.assign(data.next_url);
        } catch (requestError) {
            setError(requestError?.response?.data?.message || 'Unable to resume website setup. Please try again.');
        } finally {
            setProcessing(false);
        }
    };

    const beginCheckout = async () => {
        if (!authenticated || processing) return;
        setProcessing(true);
        setError('');
        setMessage('');

        try {
            const { data } = await axios.post(startPath);
            if (data?.checkout_url) {
                window.location.assign(data.checkout_url);
                return;
            }
            if (data?.subscription_ready) {
                setMessage(data.message || 'Your subscription already covers this website.');
                if (data?.next_url) {
                    window.setTimeout(() => window.location.assign(data.next_url), 900);
                }
                return;
            }
            setMessage(data?.message || 'Checkout is ready.');
        } catch (requestError) {
            setError(requestError?.response?.data?.message || 'Unable to start checkout. Please try again.');
        } finally {
            setProcessing(false);
        }
    };

    const features = [
        `${template.pages} complete pages`,
        ...(template.aiPersonalization ? ['Luna AI content personalization'] : []),
        ...(template.customizable ? ['Pure visual customization — no coding'] : []),
        ...(template.websiteCare ? ['Hosting, security, backups & website care'] : []),
    ];

    return (
        <>
            <Head title={`${template.name} — Checkout | Cosmic CMS Marketplace`}>
                <meta name="robots" content="noindex,nofollow" />
            </Head>
            <div className="min-h-screen bg-[#f5f7fb] text-slate-950" data-marketplace-surface="1">
                <header className="border-b border-slate-200 bg-[#070b1d] text-white">
                    <div className="mx-auto flex min-h-20 max-w-[1380px] items-center justify-between gap-4 px-5 sm:px-7 lg:px-10">
                        <Link href={marketplaceHome} className="flex items-center gap-3">
                            <img src={cosmicLogo} alt="Cosmic CMS" className="h-10 w-auto max-w-[145px] object-contain brightness-0 invert" />
                            <span className="hidden h-7 w-px bg-white/15 sm:block" />
                            <span className="hidden text-[10px] font-black uppercase tracking-[.23em] text-white/75 sm:block">Marketplace</span>
                        </Link>
                        <Link href={template.detailPath} className="rounded-xl border border-white/15 px-4 py-2.5 text-xs font-black text-white/85 transition hover:bg-white/10">← Back to template</Link>
                    </div>
                </header>

                <main className="mx-auto grid max-w-[1280px] gap-7 px-5 py-8 sm:px-7 lg:grid-cols-[1.08fr_.92fr] lg:px-10 lg:py-12">
                    <section className="rounded-[30px] border border-slate-200 bg-white p-6 shadow-[0_24px_70px_rgba(15,23,42,.08)] sm:p-8">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className={`rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[.14em] ${planTone[template.plan] || planTone.growth}`}>{plan.label}</span>
                            <span className="rounded-full bg-violet-50 px-3 py-1 text-[10px] font-black uppercase tracking-[.14em] text-violet-700">Website care included</span>
                        </div>
                        <h1 className="mt-5 text-3xl font-black tracking-[-.045em] text-slate-950 sm:text-4xl">Get {template.name}</h1>
                        <p className="mt-3 max-w-2xl text-sm leading-6 text-slate-600">Subscribe to the complete website, then personalize the copy, images, colors and business details with Luna. You are not buying a locked download.</p>

                        <div className="mt-7 overflow-hidden rounded-[24px] border border-slate-200 bg-slate-950">
                            {template.thumbnail ? (
                                <img src={template.thumbnail} alt="" className="aspect-[1.7/1] w-full object-cover opacity-90" />
                            ) : (
                                <div className="grid aspect-[1.7/1] place-items-center bg-gradient-to-br from-slate-900 via-indigo-950 to-violet-950 px-8 text-center text-3xl font-black text-white">{template.name}</div>
                            )}
                        </div>

                        <div className="mt-7 grid gap-3 sm:grid-cols-2">
                            {features.map((feature) => (
                                <div key={feature} className="flex gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm font-bold text-slate-700">
                                    <span className="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-emerald-100 text-xs text-emerald-700">✓</span>
                                    <span>{feature}</span>
                                </div>
                            ))}
                        </div>

                        <div className="mt-7 flex flex-wrap gap-3">
                            <Link href={template.demoPath} className="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-800 hover:bg-slate-50">Preview full website</Link>
                            <Link href={catalogPath} className="rounded-xl px-5 py-3 text-sm font-black text-violet-700 hover:bg-violet-50">Browse other templates</Link>
                        </div>
                    </section>

                    <aside className="self-start overflow-hidden rounded-[30px] border border-slate-200 bg-white shadow-[0_24px_70px_rgba(15,23,42,.08)] lg:sticky lg:top-6">
                        <div className="bg-[#0a1028] p-6 text-white sm:p-8">
                            <p className="text-xs font-black uppercase tracking-[.18em] text-violet-300">Monthly website subscription</p>
                            <div className="mt-4 flex items-end gap-2">
                                <span className="text-5xl font-black tracking-[-.05em]">${plan.price}</span>
                                <span className="pb-1.5 text-sm font-bold text-slate-300">USD / month</span>
                            </div>
                            <p className="mt-3 text-sm leading-6 text-slate-300">{plan.label} includes the template, Cosmic CMS access, AI tools, hosting and ongoing website care.</p>
                        </div>

                        <div className="p-6 sm:p-8">
                            <h2 className="text-lg font-black text-slate-950">Your order</h2>
                            <div className="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div className="flex items-start justify-between gap-4"><div><p className="font-black text-slate-950">{template.name}</p><p className="mt-1 text-xs font-semibold text-slate-500">{template.industry} · {template.pages} pages</p></div><p className="font-black text-slate-950">${plan.price}/mo</p></div>
                            </div>

                            <ul className="mt-5 space-y-3 text-sm font-semibold text-slate-600">
                                {['No setup fee', 'Cancel anytime', 'Secure PayPal subscription', `${plan.credits.toLocaleString()} signup AI credits`, 'Website care included'].map((item) => (
                                    <li key={item} className="flex gap-3"><span className="text-emerald-600">✓</span><span>{item}</span></li>
                                ))}
                            </ul>

                            {error && <div className="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{error}</div>}
                            {message && <div className="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{message}</div>}

                            {!authenticated ? (
                                <div className="mt-7 space-y-3">
                                    <a href={registerUrl} className="flex min-h-13 w-full items-center justify-center rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 px-5 py-3.5 text-sm font-black text-white shadow-lg shadow-violet-200 transition hover:from-violet-700 hover:to-indigo-700">Create account & continue</a>
                                    <a href={loginUrl} className="flex min-h-12 w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-700 transition hover:bg-slate-50">Already have an account? Sign in</a>
                                    <p className="text-center text-[11px] leading-5 text-slate-400">Your selected website and plan stay attached to the account setup.</p>
                                </div>
                            ) : (
                                <div className="mt-7">
                                    <div className="mb-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm">
                                        <p className="font-black text-slate-900">Signed in as {account?.email}</p>
                                        <p className="mt-1 text-xs leading-5 text-slate-500">{account?.plan ? `Current plan: ${account.plan}` : 'No active paid plan yet.'}</p>
                                    </div>
                                    <button type="button" onClick={beginCheckout} disabled={processing} className="flex min-h-13 w-full items-center justify-center rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 px-5 py-3.5 text-sm font-black text-white shadow-lg shadow-violet-200 transition hover:from-violet-700 hover:to-indigo-700 disabled:cursor-not-allowed disabled:opacity-60">
                                        {processing ? 'Opening secure checkout…' : `Continue with ${plan.label}`}
                                    </button>
                                    {canRetryProvisioning && (
                                        <button type="button" onClick={retryProvisioning} disabled={processing} className="mt-3 flex min-h-12 w-full items-center justify-center rounded-xl border border-violet-200 bg-violet-50 px-5 py-3 text-sm font-black text-violet-700 transition hover:bg-violet-100 disabled:cursor-not-allowed disabled:opacity-60">
                                            Resume website setup — no new charge
                                        </button>
                                    )}
                                </div>
                            )}

                            <p className="mt-5 text-center text-[11px] leading-5 text-slate-400">By continuing, you agree to the Cosmic CMS Terms and recurring monthly billing until cancelled.</p>
                        </div>
                    </aside>
                </main>
            </div>
        </>
    );
}
