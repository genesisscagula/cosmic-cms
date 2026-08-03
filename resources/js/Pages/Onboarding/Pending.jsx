import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

export default function Pending({ onboarding, status, paymentError }) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(paymentError || '');

    const paymentConfirmed = onboarding.status === 'payment_confirmed';
    const paymentCancelled = onboarding.status === 'payment_cancelled';
    const expired = onboarding.is_expired;

    const expiryLabel = useMemo(() => {
        if (!onboarding.expires_at) return null;

        return new Intl.DateTimeFormat(undefined, {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        }).format(new Date(onboarding.expires_at));
    }, [onboarding.expires_at]);

    const continueToPayPal = async () => {
        if (loading || expired) return;

        setLoading(true);
        setError('');

        try {
            const response = await axios.post(route('onboarding.checkout'));
            const checkoutUrl = response.data?.checkout_url;

            if (!checkoutUrl) {
                throw new Error('PayPal checkout URL was not returned.');
            }

            window.location.assign(checkoutUrl);
        } catch (requestError) {
            setError(
                requestError.response?.data?.message ||
                requestError.message ||
                'Unable to open PayPal checkout. Please try again.'
            );
            setLoading(false);
        }
    };

    const badge = paymentConfirmed
        ? 'Payment confirmed'
        : paymentCancelled
            ? 'Payment cancelled'
            : expired
                ? 'Setup expired'
                : 'Pending payment';

    return (
        <GuestLayout
            wide
            title={paymentConfirmed ? 'Finalizing your workspace' : 'Complete your subscription'}
            subtitle={paymentConfirmed
                ? 'PayPal approved your subscription. We are safely preparing your website workspace.'
                : 'Your account and business details are saved, so you can resume without starting over.'}
        >
            <Head title="Complete setup" />

            {(status || error) && (
                <div className={`mb-6 rounded-xl border px-4 py-3 text-sm ${error
                    ? 'border-rose-400/20 bg-rose-400/10 text-rose-100'
                    : 'border-emerald-400/20 bg-emerald-400/10 text-emerald-100'
                }`}>
                    {error || status}
                </div>
            )}

            <div className="grid items-start gap-6 lg:grid-cols-[1.25fr_0.75fr]">
                <section className="rounded-2xl border border-white/10 bg-white/[0.035] p-6">
                    <div className={`inline-flex rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] ${paymentConfirmed
                        ? 'border-emerald-300/20 bg-emerald-300/10 text-emerald-200'
                        : paymentCancelled || expired
                            ? 'border-rose-300/20 bg-rose-300/10 text-rose-200'
                            : 'border-amber-300/20 bg-amber-300/10 text-amber-200'
                    }`}>
                        {badge}
                    </div>

                    <h2 className="mt-5 text-2xl font-semibold text-white">
                        {paymentConfirmed
                            ? `${onboarding.plan_name} subscription approved`
                            : paymentCancelled
                                ? 'Your payment was cancelled'
                                : expired
                                    ? 'This onboarding session has expired'
                                    : `Complete your ${onboarding.plan_name} subscription`}
                    </h2>

                    <p className="mt-2 max-w-xl text-sm leading-6 text-slate-400">
                        {paymentConfirmed
                            ? 'No action is needed. Refresh this page shortly if you are not redirected automatically.'
                            : paymentCancelled
                                ? 'Nothing was charged. Your information is still saved, and you can start a fresh PayPal checkout below.'
                                : expired
                                    ? 'For security, this saved setup is no longer eligible for payment. Please create a new onboarding request.'
                                    : 'Paid features and dashboard access stay locked until PayPal confirms your subscription.'}
                    </p>

                    <div className="mt-6 rounded-xl border border-white/10 bg-black/20 p-4 text-sm text-slate-300">
                        <p><span className="text-slate-500">Website:</span> {onboarding.website_name}</p>
                        <p className="mt-2"><span className="text-slate-500">Address:</span> {onboarding.website_slug}.cosmiccms.com</p>
                        <p className="mt-2"><span className="text-slate-500">Industry:</span> {onboarding.industry}</p>
                        <p className="mt-2"><span className="text-slate-500">Location:</span> {onboarding.location}</p>
                        {expiryLabel && (
                            <p className="mt-2"><span className="text-slate-500">Setup reserved until:</span> {expiryLabel}</p>
                        )}
                    </div>

                    {!paymentConfirmed && !expired && (
                        <div className="mt-6">
                            <button
                                type="button"
                                onClick={continueToPayPal}
                                disabled={loading}
                                className="inline-flex w-full items-center justify-center rounded-xl bg-emerald-400 px-5 py-3.5 text-sm font-semibold text-slate-950 transition hover:bg-emerald-300 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
                            >
                                {loading
                                    ? 'Opening PayPal…'
                                    : onboarding.has_pending_checkout
                                        ? 'Resume PayPal checkout'
                                        : paymentCancelled
                                            ? `Try PayPal again — ${onboarding.price}/month`
                                            : `Continue with PayPal — ${onboarding.price}/month`}
                            </button>
                            <p className="mt-3 text-xs leading-5 text-slate-500">
                                You can safely close this page and sign in later. Your saved onboarding will resume here.
                            </p>
                        </div>
                    )}

                    {paymentConfirmed && (
                        <div className="mt-6 rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-100">
                            Your payment is verified. Cosmic CMS is finalizing your workspace and transferring your saved trial page.
                        </div>
                    )}

                    {expired && (
                        <div className="mt-6">
                            <Link
                                href={route('start')}
                                className="inline-flex w-full items-center justify-center rounded-xl bg-emerald-400 px-5 py-3.5 text-sm font-semibold text-slate-950 transition hover:bg-emerald-300 sm:w-auto"
                            >
                                Start a new website
                            </Link>
                        </div>
                    )}
                </section>

                <aside className="rounded-2xl border border-white/10 bg-[#111318] p-6">
                    <p className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Selected plan</p>
                    <p className="mt-3 text-3xl font-semibold text-white">{onboarding.plan_name}</p>
                    <p className="mt-1 text-sm text-slate-400">{onboarding.price}/month</p>
                    <div className="mt-6 border-t border-white/10 pt-5 text-sm leading-6 text-slate-400">
                        Your account, business profile, and generated trial page remain protected until payment is verified.
                    </div>
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="mt-6 w-full rounded-xl border border-white/10 px-4 py-3 text-sm font-medium text-slate-300 transition hover:border-white/20 hover:bg-white/5"
                    >
                        Sign out
                    </Link>
                </aside>
            </div>
        </GuestLayout>
    );
}
