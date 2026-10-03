import './Pending.css';
import axios from 'axios';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

const LockIcon = () => <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3" /></svg>;

const CheckoutLayout = ({ children }) => (
    <div className="cosmic-guest-light cosmic-force-light pending-checkout">
        <div className="pending-shell">
            <header className="pending-brand"><Link href="/" className="pending-logo"><span>✦</span><div><strong>Cosmic CMS</strong><small>AI WEBSITE PLATFORM</small></div></Link><a href="/pricing" className="pending-help">ⓘ &nbsp; Plan questions?</a></header>
            <main className="pending-surface">{children}</main>
            <footer><Link href="/pricing">Pricing</Link><Link href="/privacy">Privacy</Link><Link href="/terms">Terms</Link></footer>
        </div>
    </div>
);

const Step = ({ number, title, active, complete }) => (
    <div className={`pending-step flex items-center gap-3 rounded-xl border px-3 py-3 ${
        complete
            ? 'border-emerald-200 bg-emerald-50'
            : active
                ? 'border-emerald-300 bg-white shadow-sm'
                : 'border-slate-200 bg-slate-50'
    }`}>
        <span className={`grid h-8 w-8 shrink-0 place-items-center rounded-full text-xs font-bold ${
            complete
                ? 'bg-emerald-600 text-white'
                : active
                    ? 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-300'
                    : 'bg-white text-slate-400 ring-1 ring-slate-200'
        }`}>
            {complete ? '✓' : number}
        </span>
        <span className={`text-sm font-semibold ${active || complete ? 'text-slate-900' : 'text-slate-500'}`}>{title}<small>{number === "1" ? "Your details are secure" : number === "2" ? (complete ? "Payment confirmed" : "Complete with PayPal") : (complete ? "Ready to build" : "Start building")}</small></span>
    </div>
);

export default function Pending({ onboarding, marketplaceTemplate = null, marketplaceCreditTopUp = null, status, paymentError, autoCheckout = false }) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(paymentError || '');
    const [recovering, setRecovering] = useState(false);
    const [provisioningSeconds, setProvisioningSeconds] = useState(0);
    const topUpOptions = marketplaceCreditTopUp?.options || [];
    const [selectedTopUpKey, setSelectedTopUpKey] = useState(
        marketplaceCreditTopUp?.selected_key || marketplaceCreditTopUp?.default_key || '',
    );
    const autoCheckoutStarted = useRef(false);
    const redirectStarted = useRef(false);

    const paymentConfirmed = ['payment_confirmed', 'subscription_ready', 'completed'].includes(onboarding.status);
    const paymentCancelled = onboarding.status === 'payment_cancelled';
    const provisioningFailed = onboarding.provisioning_status === 'failed';
    const expired = onboarding.is_expired;
    const workspaceReady = Boolean(onboarding.workspace_ready);
    const dashboardUrl = onboarding.redirect_url || route('dashboard');
    const requiresMarketplaceTopUp = Boolean(marketplaceCreditTopUp?.requires_topup);
    const selectedTopUp = topUpOptions.find((option) => option.key === selectedTopUpKey)
        || marketplaceCreditTopUp?.selected
        || topUpOptions[0]
        || null;
    const canResumeSelectedCheckout = Boolean(onboarding.has_pending_checkout)
        && String(onboarding.pending_credit_option_key || '') === String(selectedTopUp?.key || '');
    const formatCredits = (value) => Number(value || 0).toLocaleString();
    const formatUsd = (value) => new Intl.NumberFormat('en-US', {
        style: 'currency', currency: 'USD', minimumFractionDigits: 0, maximumFractionDigits: 2,
    }).format(Number(value || 0));

    useEffect(() => {
        const nextKey = marketplaceCreditTopUp?.selected_key || marketplaceCreditTopUp?.default_key || '';
        if (nextKey && !topUpOptions.some((option) => option.key === selectedTopUpKey)) {
            setSelectedTopUpKey(nextKey);
        }
    }, [marketplaceCreditTopUp?.selected_key, marketplaceCreditTopUp?.default_key]);

    const redirectToDashboard = () => {
        if (redirectStarted.current) return;
        redirectStarted.current = true;
        window.location.replace(dashboardUrl);
    };

    useEffect(() => {
        if (workspaceReady) redirectToDashboard();
    }, [workspaceReady, dashboardUrl]);

    useEffect(() => {
        if (!paymentConfirmed || provisioningFailed || workspaceReady) return undefined;

        const startedAt = Date.now();
        let active = true;
        let checking = false;

        const reload = () => {
            if (!active || checking || redirectStarted.current) return;
            checking = true;

            router.reload({
                only: ['onboarding', 'status', 'paymentError'],
                preserveScroll: true,
                preserveState: true,
                onSuccess: (page) => {
                    const latest = page.props?.onboarding;
                    if (latest?.workspace_ready) {
                        redirectStarted.current = true;
                        window.location.replace(latest.redirect_url || dashboardUrl);
                    }
                },
                onFinish: () => {
                    checking = false;
                },
            });
        };

        reload();
        const clock = window.setInterval(() => {
            setProvisioningSeconds(Math.floor((Date.now() - startedAt) / 1000));
        }, 1000);
        const poller = window.setInterval(reload, 1200);
        const onVisible = () => {
            if (document.visibilityState === 'visible') reload();
        };
        document.addEventListener('visibilitychange', onVisible);

        return () => {
            active = false;
            window.clearInterval(clock);
            window.clearInterval(poller);
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, [paymentConfirmed, provisioningFailed, workspaceReady, dashboardUrl]);

    const expiryLabel = useMemo(() => {
        if (!onboarding.expires_at) return null;
        return new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
            .format(new Date(onboarding.expires_at));
    }, [onboarding.expires_at]);

    const continueToPayPal = async () => {
        if (loading || expired) return;
        setLoading(true);
        setError('');

        try {
            const response = await axios.post(route('onboarding.checkout'), {
                marketplace_credit_option: selectedTopUpKey || null,
            });
            const checkoutUrl = response.data?.checkout_url;
            if (!checkoutUrl) throw new Error('PayPal checkout URL was not returned.');
            window.location.assign(checkoutUrl);
        } catch (requestError) {
            const message = requestError.response?.data?.message
                || requestError.message
                || 'Unable to open PayPal checkout. Please try again.';
            setError(message);
            setLoading(false);
            window.requestAnimationFrame(() => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }
    };

    useEffect(() => {
        if (!autoCheckout || autoCheckoutStarted.current || paymentConfirmed || expired) return;

        autoCheckoutStarted.current = true;
        // A Marketplace shortfall must be visible so the customer can choose how
        // many credits to add. Remove the auto flag, but do not leave for PayPal yet.
        window.history.replaceState(window.history.state, '', route('onboarding.pending'));
        if (requiresMarketplaceTopUp) return;

        continueToPayPal();
    }, [autoCheckout, paymentConfirmed, expired, requiresMarketplaceTopUp]);

    const recoverProvisioning = () => {
        if (recovering) return;
        setRecovering(true);
        setError('');
        router.post(route('onboarding.recover'), {}, {
            preserveScroll: true,
            onError: () => setError('Unable to retry provisioning.'),
            onFinish: () => setRecovering(false),
        });
    };

    const badge = provisioningFailed
        ? 'Setup needs attention'
        : paymentConfirmed
            ? 'Payment confirmed'
            : paymentCancelled
                ? 'Payment cancelled'
                : expired
                    ? 'Setup expired'
                    : 'Secure checkout pending';

    return (
        <CheckoutLayout>
            <Head title="Cosmic CMS" />

            {(status || error) && (
                <div className={`mb-6 rounded-2xl border px-5 py-4 text-sm font-medium ${error
                    ? 'border-rose-200 bg-rose-50 text-rose-700'
                    : 'border-emerald-200 bg-emerald-50 text-emerald-800'
                }`}>
                    {error || status}
                </div>
            )}

            <div className="cosmic-pending-grid grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                <section className="cosmic-pending-main overflow-hidden rounded-[28px] border border-emerald-100 bg-white shadow-[0_28px_90px_rgba(15,23,42,0.10)]">
                    <Link href="/pricing" className="pending-back">← &nbsp; Back to plans</Link>
                    <h1 className="pending-title">{paymentConfirmed ? 'Your workspace is almost ready' : "You’re one step away"}</h1>
                    <p className="pending-intro">{paymentConfirmed ? 'Payment is confirmed. We are activating your plan and preparing your workspace.' : 'Your account and business details are saved. Complete secure PayPal checkout to activate your workspace.'}</p>
                    <div className={`pending-status ${paymentCancelled || expired || provisioningFailed ? 'pending-status-warning' : ''}`}>
                        <span className="pending-status-icon">{paymentConfirmed ? '✓' : <LockIcon />}</span>
                        <div><strong>{badge}</strong><p>{paymentConfirmed ? 'You will be redirected when your workspace is ready.' : paymentCancelled ? 'Nothing was charged. You can safely reopen checkout.' : expired ? 'Create a fresh onboarding request to continue securely.' : 'Your plan activates only after payment is confirmed.'}</p></div>
                    </div>

                    <div className="p-6 sm:p-8">
                        {marketplaceTemplate && (
                            <div className="pending-template">
                                <div className="pending-section-label"><span className="pending-number">1</span><strong>Marketplace website selected</strong><span className="pending-saved">Saved through checkout</span></div>
                                <div className="pending-template-detail">
                                    {marketplaceTemplate.image && <img src={marketplaceTemplate.image} alt={`${marketplaceTemplate.name} website preview`} />}
                                    <div><h2>{marketplaceTemplate.name}</h2><p>{marketplaceTemplate.industry} · {marketplaceTemplate.pages} pages · {formatCredits(marketplaceTemplate.credit_price)} Cosmic Credits</p></div>
                                </div>
                            </div>
                        )}

                        <div className="pending-steps grid gap-3 sm:grid-cols-3">
                            <Step number="1" title="Account saved" complete />
                            <Step number="2" title="Payment approval" active={!paymentConfirmed} complete={paymentConfirmed} />
                            <Step number="3" title="Workspace ready" active={paymentConfirmed && !workspaceReady} complete={workspaceReady} />
                        </div>

                        <div className="pending-details mt-7 grid gap-4 sm:grid-cols-2">
                            <div className="rounded-2xl border border-slate-200 bg-slate-50/70 p-5">
                                <p className="text-xs font-bold uppercase tracking-[0.14em] text-emerald-700">Website</p>
                                <p className="mt-2 text-lg font-bold text-slate-950">{onboarding.website_name}</p>
                                <p className="mt-1 break-all text-sm text-slate-500">{onboarding.website_slug}.cosmiccms.com</p>
                            </div>
                            <div className="rounded-2xl border border-slate-200 bg-slate-50/70 p-5">
                                <p className="text-xs font-bold uppercase tracking-[0.14em] text-emerald-700">Business profile</p>
                                <p className="mt-2 text-sm font-semibold text-slate-900">{onboarding.industry}</p>
                                <p className="mt-1 text-sm text-slate-500">{onboarding.location}</p>
                                {expiryLabel && <p className="mt-3 text-xs text-slate-400">Reserved until {expiryLabel}</p>}
                            </div>
                        </div>

                        {marketplaceTemplate && marketplaceCreditTopUp && !paymentConfirmed && !expired && (
                            <div className="pending-credits mt-7 rounded-2xl border border-violet-200 bg-white p-5 shadow-sm">
                                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <p className="text-xs font-black uppercase tracking-[0.16em] text-violet-600">Marketplace credits</p>
                                        <h3 className="mt-1 text-lg font-extrabold text-slate-950">Cover the template with your first PayPal checkout</h3>
                                        <p className="mt-2 text-sm leading-6 text-slate-600">
                                            {formatCredits(marketplaceCreditTopUp.required_template_credits)} credits required · {formatCredits(marketplaceCreditTopUp.included_plan_credits)} included once with {onboarding.plan_name}
                                        </p>
                                    </div>
                                    <div className="rounded-xl bg-violet-50 px-4 py-3 text-right">
                                        <p className="text-[10px] font-black uppercase tracking-[0.14em] text-violet-500">Credit gap</p>
                                        <p className="mt-1 text-xl font-black text-violet-800">{formatCredits(marketplaceCreditTopUp.shortfall_credits)}</p>
                                    </div>
                                </div>

                                {requiresMarketplaceTopUp ? (
                                    <div className="mt-5 grid gap-3">
                                        {topUpOptions.map((option) => (
                                            <label key={option.key} className={`flex cursor-pointer items-center justify-between gap-4 rounded-xl border p-4 transition ${selectedTopUp?.key === option.key ? 'border-violet-400 bg-violet-50 ring-2 ring-violet-100' : 'border-slate-200 bg-white hover:border-violet-200'}`}>
                                                <span className="flex items-start gap-3">
                                                    <input
                                                        type="radio"
                                                        name="marketplace_credit_topup"
                                                        value={option.key}
                                                        checked={selectedTopUp?.key === option.key}
                                                        onChange={() => setSelectedTopUpKey(option.key)}
                                                        className="mt-1"
                                                    />
                                                    <span>
                                                        <span className="flex flex-wrap items-center gap-2">
                                                            <span className="font-extrabold text-slate-900">+{formatCredits(option.credits)} credits</span>
                                                            {option.recommended && <span className="rounded-full bg-violet-600 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-white">Recommended</span>}
                                                        </span>
                                                        <span className="mt-1 block text-xs text-slate-500">{option.label} · {formatCredits(option.balance_after_install)} credits remain after installing {marketplaceTemplate.name}</span>
                                                    </span>
                                                </span>
                                                <span className="shrink-0 text-right">
                                                    <span className="block text-base font-black text-slate-950">{formatUsd(option.price_usd)}</span>
                                                    <span className="text-[10px] font-bold uppercase tracking-wide text-slate-400">one-time</span>
                                                </span>
                                            </label>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                                        Your included Agency credits already cover this template. No credit top-up is needed.
                                    </div>
                                )}
                            </div>
                        )}

                        {!paymentConfirmed && !expired && (
                            <div className="pending-payment mt-7 rounded-2xl border border-emerald-100 bg-emerald-50/70 p-4 sm:p-5">
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                                <button
                                    type="button"
                                    onClick={continueToPayPal}
                                    disabled={loading}
                                    className="cosmic-paypal-cta inline-flex min-h-14 flex-1 items-center justify-center rounded-2xl bg-gradient-to-r from-emerald-700 to-green-700 px-6 text-sm font-bold !text-white shadow-[0_16px_38px_rgba(5,150,105,0.28)] transition hover:-translate-y-0.5 hover:from-emerald-800 hover:to-green-800 disabled:cursor-not-allowed disabled:opacity-70"
                                >
                                    {loading
                                        ? 'Redirecting to PayPal…'
                                        : canResumeSelectedCheckout
                                            ? 'Resume checkout with PayPal'
                                            : paymentCancelled
                                                ? selectedTopUp
                                                    ? `Try PayPal again — ${onboarding.price}/month + ${formatUsd(selectedTopUp.price_usd)} one-time`
                                                    : `Try PayPal again — ${onboarding.price}/month`
                                                : selectedTopUp
                                                    ? `Continue to PayPal — ${onboarding.price}/month + ${formatUsd(selectedTopUp.price_usd)} one-time`
                                                    : `Continue to PayPal — ${onboarding.price}/month`}
                                </button>
                                <p className="text-xs leading-5 text-slate-600 sm:max-w-[220px]">Secure payment is handled by PayPal. You can safely return and resume later.</p>
                                </div>
                                {error && (
                                    <div className="mt-3 rounded-xl border border-rose-200 bg-white px-4 py-3 text-xs font-semibold leading-5 text-rose-700">
                                        PayPal checkout could not open: {error}
                                    </div>
                                )}
                            </div>
                        )}

                        {paymentConfirmed && !provisioningFailed && (
                            <div className="mt-7 rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                                <div className="flex items-start gap-4">
                                    <span className="mt-0.5 grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-600 text-lg text-white">✓</span>
                                    <div>
                                        <p className="font-bold text-emerald-950">Payment approved</p>
                                        <p className="mt-1 text-sm leading-6 text-emerald-800">We are creating your workspace, applying your plan credits, and transferring your saved website. This page checks progress automatically.</p>
                                        {provisioningSeconds >= 8 && (
                                            <div className="mt-4 flex flex-wrap items-center gap-3">
                                                <button
                                                    type="button"
                                                    onClick={redirectToDashboard}
                                                    className="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-700 px-4 text-sm font-bold !text-white transition hover:bg-emerald-800"
                                                >
                                                    Continue to dashboard
                                                </button>
                                                <span className="text-xs text-emerald-700">Still checking automatically…</span>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        )}

                        {provisioningFailed && (
                            <div className="mt-7 rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-700">
                                <p className="font-bold">Workspace setup needs another attempt.</p>
                                <p className="mt-1 text-rose-600">{onboarding.provisioning_error || 'Your payment is safe. Retry the remaining setup steps below.'}</p>
                                <button type="button" onClick={recoverProvisioning} disabled={recovering} className="mt-4 rounded-lg bg-rose-600 px-4 py-2.5 font-bold text-white disabled:opacity-60">
                                    {recovering ? 'Recovering…' : 'Retry workspace setup'}
                                </button>
                            </div>
                        )}

                        {expired && (
                            <Link href={route('start')} className="mt-7 inline-flex min-h-12 items-center justify-center rounded-xl bg-emerald-600 px-6 text-sm font-bold text-white">
                                Start a new website
                            </Link>
                        )}
                    </div>
                </section>

                <aside className="cosmic-plan-summary overflow-hidden rounded-[28px] border border-emerald-200 bg-white shadow-[0_28px_90px_rgba(15,23,42,0.10)] lg:sticky lg:top-8">
                    <p className="pending-powered"><LockIcon /> Secure checkout powered by <strong>Pay<span>Pal</span></strong></p>
                    <div className="cosmic-selected-plan-header relative overflow-hidden bg-emerald-950 px-6 py-7 !text-white">
                        <div className="pointer-events-none absolute -right-10 -top-10 h-32 w-32 rounded-full bg-emerald-400/20 blur-2xl" /><p className="relative text-xs font-bold uppercase tracking-[0.18em] !text-emerald-200">Selected plan</p>
                        <div className="mt-3 flex items-end justify-between gap-4">
                            <p className="cosmic-selected-plan-name relative text-3xl font-bold !text-white">{onboarding.plan_name}</p>
                            <p className="cosmic-selected-plan-price relative pb-1 text-sm font-bold !text-emerald-200">{onboarding.price}/month</p>
                        </div>
                        <p className="pending-plan-description">Everything you need to build and scale client websites.</p>
                    </div>
                    <div className="pending-summary-body p-6 sm:p-7">
                        <p className="text-sm font-bold text-slate-900">Your setup includes</p>
                        <ul className="mt-4 space-y-3 text-sm text-slate-600">
                            <li className="flex gap-3"><span className="text-emerald-600">✓</span><span>Your private Cosmic CMS workspace</span></li>
                            <li className="flex gap-3"><span className="text-emerald-600">✓</span><span>{formatCredits(onboarding.included_credits)} Cosmic Credits included once with your plan</span></li>
                            <li className="flex gap-3"><span className="text-emerald-600">✓</span><span>{marketplaceTemplate ? `${marketplaceTemplate.name} reserved at ${formatCredits(marketplaceTemplate.credit_price)} credits` : 'Your generated trial website and business profile'}</span></li>
                            {selectedTopUp && <li className="flex gap-3"><span className="text-violet-600">+</span><span>{formatCredits(selectedTopUp.credits)} extra credits for {formatUsd(selectedTopUp.price_usd)} one-time</span></li>}
                        </ul>
                        <div className="mt-6 rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-xs leading-5 text-emerald-800">
                            {selectedTopUp
                                ? `PayPal will approve ${onboarding.price}/month for the Agency plan plus ${formatUsd(selectedTopUp.price_usd)} once for the selected credit top-up. The template credit spend is confirmed after activation.`
                                : 'Your Agency subscription is handled securely through PayPal. The template credit spend is confirmed after activation.'}
                        </div>
                        <div className="pending-benefits">
                            <div><span>ϟ</span><strong>Instant activation</strong><small>Get started right away</small></div>
                            <div><span>♧</span><strong>Secure payment</strong><small>Powered by PayPal</small></div>
                            <div><span>∞</span><strong>Cancel anytime</strong><small>No long-term lock-in</small></div>
                        </div>
                        <div className="pending-total"><div><strong>One-time credit top-up</strong><b>{formatUsd(selectedTopUp?.price_usd || 0)}</b></div><p>{selectedTopUp ? `${formatCredits(selectedTopUp.credits)} extra credits, charged once at checkout` : 'No additional credit purchase needed'}</p></div>
                        <div className="pending-renewal"><span>✦</span><div><strong>{onboarding.price}/month subscription</strong><p>Your subscription will renew monthly.<br />You can manage or cancel anytime.</p></div></div>
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="mt-6 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                        >
                            Sign out
                        </Link>
                    </div>
                </aside>
            </div>
        </CheckoutLayout>
    );
}
