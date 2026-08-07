import GuestLayout from '@/Layouts/GuestLayout';
import axios from 'axios';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

const Step = ({ number, title, active, complete }) => (
    <div className={`flex items-center gap-3 rounded-xl border px-3 py-3 ${
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
        <span className={`text-sm font-semibold ${active || complete ? 'text-slate-900' : 'text-slate-500'}`}>{title}</span>
    </div>
);

export default function Pending({ onboarding, status, paymentError, autoCheckout = false }) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(paymentError || '');
    const [recovering, setRecovering] = useState(false);
    const [provisioningSeconds, setProvisioningSeconds] = useState(0);
    const autoCheckoutStarted = useRef(false);

    const paymentConfirmed = ['payment_confirmed', 'subscription_ready', 'completed'].includes(onboarding.status);
    const paymentCancelled = onboarding.status === 'payment_cancelled';
    const provisioningFailed = onboarding.provisioning_status === 'failed';
    const expired = onboarding.is_expired;

    useEffect(() => {
        if (!paymentConfirmed || provisioningFailed) return undefined;

        const startedAt = Date.now();
        const reload = () => {
            router.reload({
                only: ['onboarding', 'status', 'paymentError'],
                preserveScroll: true,
                preserveState: true,
            });
        };

        // Keep the wait visibly alive and check quickly because provisioning is
        // normally completed by the PayPal return request or webhook in seconds.
        const clock = window.setInterval(() => {
            setProvisioningSeconds(Math.floor((Date.now() - startedAt) / 1000));
        }, 1000);
        const poller = window.setInterval(reload, 1200);
        const onVisible = () => {
            if (document.visibilityState === 'visible') reload();
        };
        document.addEventListener('visibilitychange', onVisible);

        return () => {
            window.clearInterval(clock);
            window.clearInterval(poller);
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, [paymentConfirmed, provisioningFailed]);

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
            const response = await axios.post(route('onboarding.checkout'));
            const checkoutUrl = response.data?.checkout_url;
            if (!checkoutUrl) throw new Error('PayPal checkout URL was not returned.');
            window.location.assign(checkoutUrl);
        } catch (requestError) {
            setError(requestError.response?.data?.message || requestError.message || 'Unable to open PayPal checkout. Please try again.');
            setLoading(false);
        }
    };

    useEffect(() => {
        if (!autoCheckout || autoCheckoutStarted.current || paymentConfirmed || expired) return;

        autoCheckoutStarted.current = true;
        // Remove the one-shot query flag before leaving this page so Back/refresh
        // cannot create another checkout automatically.
        window.history.replaceState({}, '', route('onboarding.pending'));
        continueToPayPal();
    }, [autoCheckout, paymentConfirmed, expired]);

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
        <GuestLayout
            wide
            forceLight
            title={paymentConfirmed ? 'Your workspace is almost ready' : "You're one step away"}
            subtitle={paymentConfirmed
                ? 'Payment is confirmed. We are applying your plan, credits, website, and starter Sparks.'
                : 'Your account and business details are saved. Complete secure PayPal checkout to activate your workspace.'}
        >
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
                    <div className="border-b border-emerald-100 bg-[radial-gradient(circle_at_top_right,rgba(16,185,129,.13),transparent_36%),linear-gradient(135deg,#ecfdf5_0%,#ffffff_56%,#ecfeff_100%)] px-6 py-7 sm:px-8">
                        <div className={`inline-flex rounded-full border px-3 py-1 text-xs font-bold uppercase tracking-[0.14em] ${paymentConfirmed
                            ? 'border-emerald-200 bg-white text-emerald-700'
                            : paymentCancelled || expired
                                ? 'border-rose-200 bg-rose-50 text-rose-700'
                                : 'border-amber-200 bg-amber-50 text-amber-700'
                        }`}>
                            {badge}
                        </div>
                        <h2 className="mt-4 text-2xl font-extrabold tracking-tight text-slate-950 sm:text-3xl">
                            {paymentConfirmed
                                ? `${onboarding.plan_name} is being activated`
                                : paymentCancelled
                                    ? 'Your checkout was cancelled'
                                    : expired
                                        ? 'This saved setup has expired'
                                        : `Activate your ${onboarding.plan_name} plan`}
                        </h2>
                        <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                            {paymentConfirmed
                                ? 'No further action is needed. You will be redirected to the dashboard as soon as provisioning completes.'
                                : paymentCancelled
                                    ? 'Nothing was charged. Your setup is still saved and you can safely reopen checkout.'
                                    : expired
                                        ? 'Create a fresh onboarding request to continue securely.'
                                        : 'You will be redirected to PayPal to approve your subscription. Your plan activates only after payment is confirmed.'}
                        </p>
                    </div>

                    <div className="p-6 sm:p-8">
                        <div className="grid gap-3 sm:grid-cols-3">
                            <Step number="1" title="Account saved" complete />
                            <Step number="2" title="Payment approval" active={!paymentConfirmed} complete={paymentConfirmed} />
                            <Step number="3" title="Workspace ready" active={paymentConfirmed} />
                        </div>

                        <div className="mt-7 grid gap-4 sm:grid-cols-2">
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

                        {!paymentConfirmed && !expired && (
                            <div className="mt-7 rounded-2xl border border-emerald-100 bg-emerald-50/70 p-4 sm:p-5">
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                                <button
                                    type="button"
                                    onClick={continueToPayPal}
                                    disabled={loading}
                                    className="cosmic-paypal-cta inline-flex min-h-14 flex-1 items-center justify-center rounded-2xl bg-gradient-to-r from-emerald-700 to-green-700 px-6 text-sm font-black !text-white shadow-[0_16px_38px_rgba(5,150,105,0.28)] transition hover:-translate-y-0.5 hover:from-emerald-800 hover:to-green-800 disabled:cursor-not-allowed disabled:opacity-70"
                                >
                                    {loading
                                        ? 'Redirecting to PayPal…'
                                        : onboarding.has_pending_checkout
                                            ? 'Resume checkout with PayPal'
                                            : paymentCancelled
                                                ? `Try PayPal again — ${onboarding.price}/month`
                                                : `Continue to PayPal — ${onboarding.price}/month`}
                                </button>
                                <p className="text-xs leading-5 text-slate-600 sm:max-w-[220px]">Secure payment is handled by PayPal. You can safely return and resume later.</p>
                                </div>
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
                                                <Link
                                                    href={route('dashboard')}
                                                    replace
                                                    className="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-700 px-4 text-sm font-bold !text-white transition hover:bg-emerald-800"
                                                >
                                                    Continue to dashboard
                                                </Link>
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
                    <div className="cosmic-selected-plan-header relative overflow-hidden bg-emerald-950 px-6 py-7 !text-white">
                        <div className="pointer-events-none absolute -right-10 -top-10 h-32 w-32 rounded-full bg-emerald-400/20 blur-2xl" /><p className="relative text-xs font-bold uppercase tracking-[0.18em] !text-emerald-200">Selected plan</p>
                        <div className="mt-3 flex items-end justify-between gap-4">
                            <p className="cosmic-selected-plan-name relative text-3xl font-black !text-white">{onboarding.plan_name}</p>
                            <p className="cosmic-selected-plan-price relative pb-1 text-sm font-bold !text-emerald-200">{onboarding.price}/month</p>
                        </div>
                    </div>
                    <div className="p-6 sm:p-7">
                        <p className="text-sm font-bold text-slate-900">Your setup includes</p>
                        <ul className="mt-4 space-y-3 text-sm text-slate-600">
                            <li className="flex gap-3"><span className="text-emerald-600">✓</span><span>Your private Cosmic CMS workspace</span></li>
                            <li className="flex gap-3"><span className="text-emerald-600">✓</span><span>Your selected monthly credits and plan access</span></li>
                            <li className="flex gap-3"><span className="text-emerald-600">✓</span><span>Your generated trial website and business profile</span></li>
                        </ul>
                        <div className="mt-6 rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-xs leading-5 text-emerald-800">
                            Your website remains protected until PayPal confirmation and provisioning are complete.
                        </div>
                        <div className="mt-6 grid grid-cols-2 gap-2 text-[11px] font-semibold text-slate-600">
                            <span className="rounded-lg bg-slate-50 px-3 py-2 text-center">Secure PayPal</span>
                            <span className="rounded-lg bg-slate-50 px-3 py-2 text-center">Cancel anytime</span>
                        </div>
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
        </GuestLayout>
    );
}
