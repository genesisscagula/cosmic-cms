import { Head, Link } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useMemo, useState } from 'react';
import cosmicLogo from '../../../images/cosmic-cms-logo.png';

const planTone = {
    starter: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    growth: 'border-indigo-200 bg-indigo-50 text-indigo-700',
    pro: 'border-amber-200 bg-amber-50 text-amber-800',
};

const formatCredits = (value) => Number(value || 0).toLocaleString();

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
    initialMessage = '',
    purchaseMode = 'cosmic_credits',
    creditCheckoutReady = false,
    purchaseState = null,
    checkoutIntent = null,
    installedWebsiteUrl = null,
    newInstallPath = null,
    marketplaceHome = '/marketplace',
    catalogPath = '/marketplace/templates',
}) {
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState(initialError || '');
    const [message, setMessage] = useState(initialMessage || '');
    const [state, setState] = useState(purchaseState || null);
    const [canRetryProvisioning, setCanRetryProvisioning] = useState(Boolean(purchaseState?.can_retry_provisioning));

    const requiredCredits = Number(state?.required_credits ?? plan?.creditPrice ?? template?.creditPrice ?? 0);
    const currentBalance = Number(state?.credit_balance ?? 0);
    const balanceAfter = Number(state?.balance_after_installation ?? Math.max(0, currentBalance - requiredCredits));
    const missingCredits = Number(state?.missing_credits ?? Math.max(0, requiredCredits - currentBalance));
    const access = state?.access || null;
    const websiteLimit = state?.website_limit || null;
    const completed = Boolean(state?.completed);
    const creditsCharged = Boolean(state?.credits_charged);
    const slotAvailable = websiteLimit ? Boolean(websiteLimit.slot_available) : true;
    const eligibleToInstall = Boolean(state?.can_install ?? creditCheckoutReady);

    const blockedReason = useMemo(() => {
        if (!authenticated || completed || creditsCharged) return null;
        if (access && !access.allowed) {
            return access.is_agency_plan ? 'inactive_agency' : 'agency_required';
        }
        if (!slotAvailable) return 'website_limit';
        if (missingCredits > 0) return 'insufficient_credits';
        return null;
    }, [authenticated, completed, creditsCharged, access, slotAvailable, missingCredits]);

    useEffect(() => {
        if (!authenticated || !statusPath) return undefined;

        let cancelled = false;
        const checkStatus = async () => {
            try {
                const { data } = await axios.get(statusPath);
                if (cancelled) return;
                if (data?.purchase_state) setState(data.purchase_state);
                setCanRetryProvisioning(Boolean(data?.can_retry_provisioning || data?.purchase_state?.can_retry_provisioning));
                if (data?.message && ['credits_charged', 'completed'].includes(data?.status)) {
                    setMessage(data.message);
                }
                if (data?.ready && data?.next_url) {
                    setMessage(data?.message || 'Your Marketplace website is ready.');
                }
            } catch (_) {
                // The confirmation screen stays usable if this optional recovery poll fails.
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
            setCanRetryProvisioning(Boolean(data?.can_retry_provisioning));
            if (data?.next_url) window.location.assign(data.next_url);
        } catch (requestError) {
            setCanRetryProvisioning(Boolean(requestError?.response?.data?.can_retry_provisioning ?? true));
            setError(requestError?.response?.data?.message || 'Unable to resume website setup. Please try again.');
        } finally {
            setProcessing(false);
        }
    };

    const beginCheckout = async () => {
        if (!authenticated || processing || !eligibleToInstall || !checkoutIntent?.uuid) return;
        setProcessing(true);
        setError('');
        setMessage('');

        try {
            const { data } = await axios.post(startPath, {
                checkout_uuid: checkoutIntent.uuid,
                confirmed: true,
            });

            if (data?.purchase_state) setState(data.purchase_state);
            setCanRetryProvisioning(Boolean(data?.can_retry_provisioning));
            setMessage(data?.message || 'Marketplace installation confirmed.');

            if (data?.ready && data?.next_url) {
                window.location.assign(data.next_url);
            }
        } catch (requestError) {
            const response = requestError?.response?.data;
            if (response?.purchase_state) setState(response.purchase_state);
            setCanRetryProvisioning(Boolean(response?.purchase_state?.can_retry_provisioning));
            setError(response?.message || 'Unable to start the Marketplace installation. Please try again.');
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

    const limitLabel = websiteLimit?.is_unlimited
        ? 'Unlimited websites'
        : `${Number(websiteLimit?.used || 0)} of ${websiteLimit?.limit_label || websiteLimit?.limit || 0} websites used`;

    return (
        <>
            <Head title={`${template.name} — Checkout | Cosmic CMS Marketplace`}>
                <meta name="robots" content="noindex,nofollow" />
            </Head>
            <div className="min-h-screen bg-[#f5f7fb] text-slate-950" data-marketplace-surface="1" data-purchase-mode={purchaseMode}>
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
                            <span className="rounded-full bg-violet-50 px-3 py-1 text-[10px] font-black uppercase tracking-[.14em] text-violet-700">Agency Marketplace</span>
                        </div>
                        <h1 className="mt-5 text-3xl font-black tracking-[-.045em] text-slate-950 sm:text-4xl">Get {template.name}</h1>
                        <p className="mt-3 max-w-2xl text-sm leading-6 text-slate-600">Install this complete Marketplace website into your Agency account, then personalize the copy, images, colors and business details with Luna. The template price is charged once per installation.</p>

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
                            <p className="text-xs font-black uppercase tracking-[.18em] text-violet-300">Marketplace template installation</p>
                            <div className="mt-4 flex flex-wrap items-end gap-2">
                                <span className="text-5xl font-black tracking-[-.05em]">{formatCredits(requiredCredits)}</span>
                                <span className="pb-1.5 text-sm font-bold text-slate-300">Cosmic Credits</span>
                            </div>
                            <p className="mt-3 text-sm leading-6 text-slate-300">One installation into a normal Agency website. Normal editing does not charge this template price again.</p>
                        </div>

                        <div className="p-6 sm:p-8">
                            <h2 className="text-lg font-black text-slate-950">Confirm installation</h2>
                            <div className="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <p className="font-black text-slate-950">{template.name}</p>
                                        <p className="mt-1 text-xs font-semibold text-slate-500">{template.industry} · {template.pages} pages</p>
                                    </div>
                                    <p className="text-right font-black text-slate-950">{formatCredits(requiredCredits)}<span className="block text-[10px] font-bold text-slate-400">Cosmic Credits</span></p>
                                </div>
                            </div>

                            {authenticated && state && (
                                <div className="mt-4 overflow-hidden rounded-2xl border border-slate-200">
                                    <div className="flex items-center justify-between gap-4 border-b border-slate-100 px-4 py-3 text-sm">
                                        <span className="font-semibold text-slate-500">Template Price</span>
                                        <span className="font-black text-slate-900">{formatCredits(requiredCredits)} Cosmic Credits</span>
                                    </div>
                                    <div className="flex items-center justify-between gap-4 border-b border-slate-100 px-4 py-3 text-sm">
                                        <span className="font-semibold text-slate-500">Your Balance</span>
                                        <span className="font-black text-slate-900">{formatCredits(currentBalance)} Cosmic Credits</span>
                                    </div>
                                    <div className="flex items-center justify-between gap-4 px-4 py-3 text-sm">
                                        <span className="font-semibold text-slate-500">Balance After Installation</span>
                                        <span className="font-black text-slate-900">{formatCredits(balanceAfter)} Cosmic Credits</span>
                                    </div>
                                </div>
                            )}

                            <ul className="mt-5 space-y-3 text-sm font-semibold text-slate-600">
                                {['One template installation', 'No repeat template charge for normal editing', 'Uses the same shared Agency website allowance as Cosmic Studio', 'Installed design kit stays attached for future pages', 'Luna/AI usage follows existing AI credit rules'].map((item) => (
                                    <li key={item} className="flex gap-3"><span className="text-emerald-600">✓</span><span>{item}</span></li>
                                ))}
                            </ul>

                            {error && <div className="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{error}</div>}
                            {message && <div className="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{message}</div>}

                            {!authenticated ? (
                                <div className="mt-7 space-y-3">
                                    <a href={registerUrl} className="flex min-h-13 w-full items-center justify-center rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 px-5 py-3.5 text-sm font-black text-white shadow-lg shadow-violet-200 transition hover:from-violet-700 hover:to-indigo-700">Create Agency account & continue</a>
                                    <a href={loginUrl} className="flex min-h-12 w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-700 transition hover:bg-slate-50">Already have an account? Sign in</a>
                                    <p className="text-center text-[11px] leading-5 text-slate-400">Marketplace is an Agency feature. Account subscription and template Cosmic Credits remain separate.</p>
                                </div>
                            ) : (
                                <div className="mt-7">
                                    <div className="mb-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm">
                                        <p className="font-black text-slate-900">Signed in as {account?.email}</p>
                                        <div className="mt-1 flex flex-wrap items-center justify-between gap-2 text-xs leading-5 text-slate-500">
                                            <span>{access?.plan_label ? `Agency plan: ${access.plan_label}` : (account?.plan ? `Current plan: ${account.plan}` : 'No active paid plan yet.')}</span>
                                            {websiteLimit && <span>{limitLabel}</span>}
                                        </div>
                                    </div>

                                    {completed && (
                                        <div className="space-y-3">
                                            <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold leading-6 text-emerald-800">This installation is complete. Opening or editing the website does not charge the Marketplace template again.</div>
                                            {installedWebsiteUrl && <a href={installedWebsiteUrl} className="flex min-h-13 w-full items-center justify-center rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 px-5 py-3.5 text-sm font-black text-white">Open Website in Builder</a>}
                                            {newInstallPath && <a href={newInstallPath} className="flex min-h-12 w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-700 hover:bg-slate-50">Install another copy</a>}
                                        </div>
                                    )}

                                    {!completed && creditsCharged && (
                                        <div className="space-y-3">
                                            <div className="rounded-2xl border border-violet-200 bg-violet-50 p-4 text-sm font-semibold leading-6 text-violet-800">Template credits were already charged once. Website setup can safely resume without another Marketplace charge.</div>
                                            <button type="button" onClick={retryProvisioning} disabled={processing || !retryPath} className="flex min-h-13 w-full items-center justify-center rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 px-5 py-3.5 text-sm font-black text-white shadow-lg shadow-violet-200 transition hover:from-violet-700 hover:to-indigo-700 disabled:cursor-not-allowed disabled:opacity-60">
                                                {processing ? 'Resuming website setup…' : 'Resume Website Setup — No New Charge'}
                                            </button>
                                        </div>
                                    )}

                                    {!completed && !creditsCharged && blockedReason === 'agency_required' && (
                                        <div className="space-y-3">
                                            <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold leading-6 text-amber-900"><strong className="block font-black">Marketplace is an Agency feature</strong>{access?.message || 'Choose Agency Starter, Growth, or Pro before installing this template.'}</div>
                                            <a href={state?.upgrade_url || '/credits?family=agency'} className="flex min-h-12 w-full items-center justify-center rounded-xl border border-violet-200 bg-violet-50 px-5 py-3 text-sm font-black text-violet-700 hover:bg-violet-100">Choose Agency Plan</a>
                                        </div>
                                    )}

                                    {!completed && !creditsCharged && blockedReason === 'inactive_agency' && (
                                        <div className="space-y-3">
                                            <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold leading-6 text-amber-900"><strong className="block font-black">Agency subscription inactive</strong>{access?.message}</div>
                                            <a href={state?.upgrade_url || '/credits?family=agency'} className="flex min-h-12 w-full items-center justify-center rounded-xl border border-violet-200 bg-violet-50 px-5 py-3 text-sm font-black text-violet-700 hover:bg-violet-100">Manage Agency Plan</a>
                                        </div>
                                    )}

                                    {!completed && !creditsCharged && blockedReason === 'website_limit' && (
                                        <div className="space-y-3">
                                            <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold leading-6 text-amber-900"><strong className="block font-black">Website limit reached</strong>{limitLabel}. No Cosmic Credits were deducted.</div>
                                            <a href={state?.upgrade_url || '/credits?family=agency'} className="flex min-h-12 w-full items-center justify-center rounded-xl border border-violet-200 bg-violet-50 px-5 py-3 text-sm font-black text-violet-700 hover:bg-violet-100">Upgrade Agency Plan</a>
                                        </div>
                                    )}

                                    {!completed && !creditsCharged && blockedReason === 'insufficient_credits' && (
                                        <div className="space-y-3">
                                            <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">
                                                <strong className="block font-black">Insufficient Cosmic Credits</strong>
                                                <div className="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-xs font-semibold">
                                                    <span>Current balance</span><span className="text-right font-black">{formatCredits(currentBalance)}</span>
                                                    <span>Required</span><span className="text-right font-black">{formatCredits(requiredCredits)}</span>
                                                    <span>Missing</span><span className="text-right font-black">{formatCredits(missingCredits)}</span>
                                                </div>
                                                <p className="mt-2 text-xs font-semibold">No website was provisioned and no template credits were deducted.</p>
                                            </div>
                                            <a href={state?.add_credits_url || '/credits'} className="flex min-h-12 w-full items-center justify-center rounded-xl border border-violet-200 bg-violet-50 px-5 py-3 text-sm font-black text-violet-700 hover:bg-violet-100">Add / Buy Cosmic Credits</a>
                                        </div>
                                    )}

                                    {!completed && !creditsCharged && !blockedReason && (
                                        <div className="space-y-3">
                                            <div className="rounded-2xl border border-violet-200 bg-violet-50 p-4 text-xs font-semibold leading-5 text-violet-800">By confirming, {formatCredits(requiredCredits)} Cosmic Credits will be deducted once and one normal Agency website slot will be used unless this checkout already has a reserved website.</div>
                                            <button type="button" onClick={beginCheckout} disabled={processing || !eligibleToInstall || !checkoutIntent?.uuid} className="flex min-h-13 w-full items-center justify-center rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 px-5 py-3.5 text-sm font-black text-white shadow-lg shadow-violet-200 transition hover:from-violet-700 hover:to-indigo-700 disabled:cursor-not-allowed disabled:opacity-60">
                                                {processing ? 'Installing website…' : `Install Website — ${formatCredits(requiredCredits)} Credits`}
                                            </button>
                                            <Link href={template.detailPath} className="flex min-h-11 w-full items-center justify-center rounded-xl px-5 py-2.5 text-sm font-black text-slate-500 hover:bg-slate-50">Cancel</Link>
                                        </div>
                                    )}

                                    {canRetryProvisioning && !creditsCharged && !completed && (
                                        <button type="button" onClick={retryProvisioning} disabled={processing} className="mt-3 flex min-h-12 w-full items-center justify-center rounded-xl border border-violet-200 bg-violet-50 px-5 py-3 text-sm font-black text-violet-700 transition hover:bg-violet-100 disabled:cursor-not-allowed disabled:opacity-60">Resume website setup — no new charge</button>
                                    )}
                                </div>
                            )}

                            <p className="mt-5 text-center text-[11px] leading-5 text-slate-400">Marketplace template credits are charged once per confirmed installation. Your Agency subscription, Add Credits payments, and normal editing remain separate.</p>
                        </div>
                    </aside>
                </main>
            </div>
        </>
    );
}
