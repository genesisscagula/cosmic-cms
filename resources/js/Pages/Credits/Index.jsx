import { Head, Link } from '@inertiajs/react';
import axios from 'axios';
import { useMemo, useState } from 'react';

function formatDate(value) {
    if (!value) return '';

    return new Intl.DateTimeFormat('en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatMoney(amount, currency) {
    return new Intl.NumberFormat(currency === 'PHP' ? 'en-PH' : 'en-US', {
        style: 'currency',
        currency,
        maximumFractionDigits: currency === 'PHP' ? 0 : 2,
    }).format(Number(amount ?? 0));
}

export default function CreditsIndex({
    balance = 0,
    transactions,
    packages = {},
    plans = {},
    currentPlan = null,
    paymentRouting = {},
    developerPurchasesEnabled = false,
    statusMessage = '',
    paymentError = '',
}) {
    const currentBalance = Number(balance);
    const [selectedPackage, setSelectedPackage] = useState(null);
    const [purchasing, setPurchasing] = useState(false);
    const [managingSubscription, setManagingSubscription] = useState(false);
    const notice = statusMessage || '';
    const [error, setError] = useState(paymentError || '');
    const rows = transactions?.data ?? [];

    const packageRows = useMemo(
        () => Object.entries(packages).map(([key, value]) => ({ key, ...value })),
        [packages],
    );
    const planRows = useMemo(
        () => Object.entries(plans).map(([key, value]) => ({ key, product_type: 'plan', ...value })),
        [plans],
    );
    const planOrder = useMemo(
        () => Object.fromEntries(planRows.map((plan, index) => [plan.key, index])),
        [planRows],
    );

    const activePlanKey = currentPlan?.key || '';
    const activePlanStatus = String(currentPlan?.status || '').toLowerCase();
    const hasActivePlan = Boolean(activePlanKey) && ['active', 'approved'].includes(activePlanStatus);
    const hasPlanAccess = Boolean(currentPlan?.has_access ?? hasActivePlan);
    const cancelAtPeriodEnd = Boolean(currentPlan?.cancel_at_period_end);

    const country = paymentRouting.country || 'XX';
    const creditProvider = paymentRouting.credit_provider || 'paypal';
    const planProvider = paymentRouting.plan_provider || 'paypal';
    const paypalEnabled = Boolean(paymentRouting.providers?.paypal?.enabled);
    const paymongoEnabled = Boolean(paymentRouting.providers?.paymongo?.enabled);

    const selectedProductType = selectedPackage?.product_type || 'credits';
    const selectedProvider = selectedProductType === 'plan' ? planProvider : creditProvider;
    const selectedProviderEnabled = selectedProvider === 'paymongo' ? paymongoEnabled : paypalEnabled;
    const selectedCurrency = selectedProvider === 'paymongo' && selectedProductType === 'credits' ? 'PHP' : 'USD';
    const selectedPrice = selectedCurrency === 'PHP'
        ? selectedPackage?.price_php
        : selectedPackage?.price_usd;

    const startCheckout = async () => {
        if (!selectedPackage || purchasing) return;

        setPurchasing(true);
        setError('');

        try {
            const response = await axios.post(route('payments.checkout'), {
                product_type: selectedProductType,
                product_key: selectedPackage.key,
            });

            if (!response.data?.checkout_url) {
                throw new Error('Checkout URL was not returned.');
            }

            window.location.assign(response.data.checkout_url);
        } catch (checkoutError) {
            setError(
                checkoutError.response?.data?.message
                || checkoutError.message
                || 'Unable to start secure checkout.',
            );
            setPurchasing(false);
        }
    };

    const cancelSubscription = async () => {
        if (managingSubscription) return;

        const confirmed = window.confirm(
            'Cancel your PayPal subscription? You will keep access until the end of the current paid period.',
        );

        if (!confirmed) return;

        setManagingSubscription(true);
        setError('');

        try {
            const response = await axios.post(route('payments.subscription.cancel'));
            window.location.assign(`${route('credits.index')}?subscription_cancelled=1`);
        } catch (cancelError) {
            setError(cancelError.response?.data?.message || 'Unable to cancel the subscription.');
            setManagingSubscription(false);
        }
    };

    const syncSubscription = async () => {
        if (managingSubscription) return;

        setManagingSubscription(true);
        setError('');

        try {
            await axios.post(route('payments.subscription.sync'));
            window.location.reload();
        } catch (syncError) {
            setError(syncError.response?.data?.message || 'Unable to sync the subscription.');
            setManagingSubscription(false);
        }
    };

    const providerLabel = selectedProvider === 'paymongo' ? 'PayMongo' : 'PayPal';
    const providerDescription = selectedProvider === 'paymongo'
        ? 'GCash, Maya, and cards through PayMongo.'
        : 'Pay securely with PayPal or an eligible debit/credit card.';

    const planActionLabel = (planKey) => {
        if (!activePlanKey) return 'Subscribe';
        if (planKey === activePlanKey) return hasPlanAccess ? 'Current Plan' : 'Resume Plan';

        return (planOrder[planKey] ?? 0) > (planOrder[activePlanKey] ?? 0)
            ? 'Upgrade'
            : 'Downgrade';
    };

    return (
        <div className="min-h-screen bg-[#0a0a0b] text-slate-100">
            <Head title="Cosmic Credits" />

            <header className="border-b border-white/10 bg-[#111113]">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-4">
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-cyan-400 font-black text-slate-950">C</span>
                        <div>
                            <p className="font-semibold text-white">Cosmic Credits</p>
                            <p className="text-xs text-slate-500">Wallet, top-ups, and transaction history</p>
                        </div>
                    </div>
                    <Link href={route('dashboard')} className="rounded-lg border border-white/10 px-3 py-2 text-sm text-slate-300 transition hover:bg-white/5 hover:text-white">
                        Back to dashboard
                    </Link>
                </div>
            </header>

            <main className="mx-auto max-w-6xl px-5 py-10">
                <section className="rounded-3xl border border-cyan-400/15 bg-gradient-to-br from-cyan-400/10 via-white/[0.03] to-violet-500/10 p-7 shadow-2xl shadow-black/30">
                    <p className="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-300">Available balance</p>
                    <div className="mt-3 flex flex-wrap items-end justify-between gap-5">
                        <div className="flex items-center gap-3">
                            <span className="text-3xl">⚡</span>
                            <strong className="text-5xl font-black tracking-tight text-white">{currentBalance}</strong>
                            <span className="pb-1 text-slate-400">Cosmic Credits</span>
                        </div>
                        <button
                            type="button"
                            onClick={() => document.getElementById('credit-packages')?.scrollIntoView({ behavior: 'smooth' })}
                            className="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-950 transition hover:bg-cyan-100"
                        >
                            Buy Credits
                        </button>
                    </div>
                </section>

                {notice && <div className="mt-5 rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-200">{notice}</div>}
                {error && <div className="mt-5 rounded-xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">{error}</div>}

                <section className="mt-8 rounded-3xl border border-white/10 bg-[#141416] p-6">
                    <div className="flex flex-wrap items-start justify-between gap-5">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-violet-300">Subscription</p>
                            <h2 className="mt-2 text-xl font-semibold text-white">
                                {currentPlan ? `${currentPlan.label} Monthly` : 'No active monthly plan'}
                            </h2>
                            <p className="mt-1 text-sm text-slate-500">
                                {currentPlan
                                    ? `${currentPlan.credits} credits are included with each successful billing cycle.`
                                    : 'Choose a monthly plan below to receive recurring credits.'}
                            </p>
                        </div>

                        <span className={`rounded-full border px-3 py-1.5 text-xs font-bold uppercase tracking-wide ${
                            hasActivePlan
                                ? 'border-emerald-400/25 bg-emerald-400/10 text-emerald-200'
                                : 'border-white/10 bg-white/[0.04] text-slate-400'
                        }`}>
                            {cancelAtPeriodEnd ? '● Cancels at period end' : hasActivePlan ? '● Active' : activePlanStatus || 'Not subscribed'}
                        </span>
                    </div>

                    {currentPlan && (
                        <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="rounded-2xl border border-white/10 bg-black/20 p-4">
                                <p className="text-xs uppercase tracking-wide text-slate-500">Current plan</p>
                                <p className="mt-2 font-semibold text-white">{currentPlan.label}</p>
                            </div>
                            <div className="rounded-2xl border border-white/10 bg-black/20 p-4">
                                <p className="text-xs uppercase tracking-wide text-slate-500">Monthly credits</p>
                                <p className="mt-2 font-semibold text-cyan-300">⚡ {currentPlan.credits}</p>
                            </div>
                            <div className="rounded-2xl border border-white/10 bg-black/20 p-4">
                                <p className="text-xs uppercase tracking-wide text-slate-500">Next billing</p>
                                <p className="mt-2 font-semibold text-white">
                                    {currentPlan.renews_at ? formatDate(currentPlan.renews_at) : 'Pending PayPal sync'}
                                </p>
                            </div>
                            <div className="rounded-2xl border border-white/10 bg-black/20 p-4">
                                <p className="text-xs uppercase tracking-wide text-slate-500">Payment provider</p>
                                <p className="mt-2 font-semibold capitalize text-white">{currentPlan.provider || 'PayPal'}</p>
                            </div>
                        </div>
                    )}

                    {currentPlan?.subscription_id && (
                        <div className="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-5">
                            <p className="text-sm text-slate-400">
                                {cancelAtPeriodEnd
                                    ? `Your plan remains available until ${currentPlan.renews_at ? formatDate(currentPlan.renews_at) : 'the end of the paid period'}.`
                                    : 'You can sync your billing status or cancel future renewals at any time.'}
                            </p>
                            <div className="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    disabled={managingSubscription}
                                    onClick={syncSubscription}
                                    className="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5 disabled:opacity-50"
                                >
                                    {managingSubscription ? 'Working…' : 'Sync with PayPal'}
                                </button>
                                {!cancelAtPeriodEnd && (
                                    <button
                                        type="button"
                                        disabled={managingSubscription}
                                        onClick={cancelSubscription}
                                        className="rounded-xl border border-rose-400/25 bg-rose-400/10 px-4 py-2 text-sm font-semibold text-rose-200 transition hover:bg-rose-400/15 disabled:opacity-50"
                                    >
                                        Cancel subscription
                                    </button>
                                )}
                            </div>
                        </div>
                    )}
                </section>

                <section id="credit-packages" className="mt-8">
                    <div className="mb-4 flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <h1 className="text-xl font-semibold text-white">Buy Credits</h1>
                            <p className="mt-1 text-sm text-slate-500">
                                {creditProvider === 'paymongo'
                                    ? 'Philippine checkout uses PHP through PayMongo.'
                                    : 'International checkout uses USD through PayPal.'}
                            </p>
                        </div>
                        <span className="rounded-full border border-white/10 bg-white/[0.04] px-3 py-1.5 text-xs font-semibold text-slate-400">
                            Country: {country === 'XX' ? 'International fallback' : country}
                        </span>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        {packageRows.map((item) => {
                            const phpCheckout = creditProvider === 'paymongo';

                            return (
                                <button
                                    type="button"
                                    key={item.key}
                                    onClick={() => setSelectedPackage({ ...item, product_type: 'credits' })}
                                    className="rounded-2xl border border-white/10 bg-[#141416] p-5 text-left transition hover:-translate-y-0.5 hover:border-cyan-300/30 hover:bg-white/[0.06]"
                                >
                                    <p className="text-sm font-semibold text-slate-300">{item.label}</p>
                                    <p className="mt-4 text-2xl font-black text-white">⚡ {item.credits}</p>
                                    <p className="mt-1 text-sm font-semibold text-cyan-300">
                                        {formatMoney(phpCheckout ? item.price_php : item.price_usd, phpCheckout ? 'PHP' : 'USD')}
                                    </p>
                                </button>
                            );
                        })}
                    </div>

                    <div className="mb-4 mt-10">
                        <h2 className="text-xl font-semibold text-white">Monthly plans</h2>
                        <p className="mt-1 text-sm text-slate-500">Secure recurring billing through PayPal.</p>
                    </div>

                    <div className="grid gap-4 md:grid-cols-3">
                        {planRows.map((plan) => {
                            const isCurrent = hasPlanAccess && plan.key === activePlanKey;
                            const actionLabel = planActionLabel(plan.key);

                            return (
                                <button
                                    type="button"
                                    key={plan.key}
                                    disabled={isCurrent}
                                    onClick={() => !isCurrent && setSelectedPackage(plan)}
                                    className={`relative rounded-2xl p-5 text-left transition ${
                                        isCurrent
                                            ? 'cursor-default border border-emerald-300/40 bg-gradient-to-br from-emerald-400/15 via-[#141416] to-cyan-400/10 shadow-lg shadow-emerald-950/20 ring-1 ring-emerald-300/20'
                                            : 'border border-white/10 bg-[#141416] hover:-translate-y-0.5 hover:border-violet-300/30 hover:bg-white/[0.06]'
                                    }`}
                                >
                                    {isCurrent && (
                                        <span className="absolute right-4 top-4 rounded-full border border-emerald-300/25 bg-emerald-300/10 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-emerald-200">
                                            Current Plan
                                        </span>
                                    )}
                                    <p className="font-semibold text-white">{plan.label}</p>
                                    <p className="mt-3 text-2xl font-black text-white">
                                        {formatMoney(plan.price_usd, 'USD')}
                                        <span className="text-sm font-medium text-slate-500">/month</span>
                                    </p>
                                    <p className="mt-2 text-sm text-cyan-300">{plan.credits} monthly credits</p>
                                    <div className={`mt-5 rounded-xl px-3 py-2 text-center text-sm font-bold ${
                                        isCurrent
                                            ? 'bg-emerald-300/10 text-emerald-200'
                                            : 'border border-white/10 bg-white/[0.04] text-slate-200'
                                    }`}>
                                        {actionLabel}
                                    </div>
                                </button>
                            );
                        })}
                    </div>
                </section>

                <section className="mt-8 overflow-hidden rounded-2xl border border-white/10 bg-[#141416]">
                    <div className="border-b border-white/10 px-5 py-4">
                        <h1 className="text-lg font-semibold text-white">Transaction history</h1>
                        <p className="mt-1 text-sm text-slate-500">Every grant, charge, purchase, and refund appears here.</p>
                    </div>

                    {rows.length === 0 ? (
                        <div className="p-10 text-center text-sm text-slate-500">No credit transactions yet.</div>
                    ) : (
                        <div className="divide-y divide-white/10">
                            {rows.map((transaction) => {
                                const positive = transaction.amount > 0;

                                return (
                                    <article key={transaction.id} className="flex flex-wrap items-center justify-between gap-4 px-5 py-4">
                                        <div>
                                            <p className="font-medium text-white">{transaction.description}</p>
                                            <p className="mt-1 text-xs text-slate-500">
                                                {transaction.website?.name ? `${transaction.website.name} · ` : ''}{formatDate(transaction.created_at)}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className={`font-bold ${positive ? 'text-emerald-300' : 'text-rose-300'}`}>
                                                {positive ? '+' : ''}{transaction.amount}
                                            </p>
                                            <p className="mt-1 text-xs text-slate-500">Balance {transaction.balance_after}</p>
                                        </div>
                                    </article>
                                );
                            })}
                        </div>
                    )}
                </section>
            </main>

            {selectedPackage && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm"
                    onMouseDown={() => setSelectedPackage(null)}
                >
                    <section
                        className="w-full max-w-md rounded-3xl border border-white/10 bg-[#151519] p-6 shadow-2xl"
                        onMouseDown={(event) => event.stopPropagation()}
                    >
                        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Order summary</p>

                        <div className="mt-5 rounded-2xl border border-white/10 bg-black/20 p-5">
                            <div className="flex items-center justify-between gap-4">
                                <div>
                                    <p className="font-semibold text-white">{selectedPackage.label}</p>
                                    <p className="mt-1 text-2xl font-black text-white">⚡ {selectedPackage.credits}</p>
                                </div>
                                <p className="text-xl font-bold text-cyan-300">
                                    {formatMoney(selectedPrice, selectedCurrency)}
                                    {selectedProductType === 'plan' && (
                                        <span className="text-xs font-medium text-slate-500">/month</span>
                                    )}
                                </p>
                            </div>
                        </div>

                        {selectedProductType === 'plan' && activePlanKey && selectedPackage.key !== activePlanKey && (
                            <div className="mt-4 rounded-xl border border-amber-300/20 bg-amber-300/10 px-4 py-3 text-sm leading-6 text-amber-100">
                                This will {planActionLabel(selectedPackage.key).toLowerCase()} your current {currentPlan?.label} plan. Your old PayPal subscription is cancelled only after the new plan becomes active.
                            </div>
                        )}

                        <button
                            type="button"
                            onClick={() => {
                                if (!selectedProviderEnabled) {
                                    setError(
                                        `${providerLabel} is not configured yet. Add its sandbox keys to .env, then run php artisan optimize:clear.`,
                                    );
                                }
                            }}
                            className={`mt-5 w-full rounded-xl border px-4 py-3 text-left transition ${
                                selectedProviderEnabled
                                    ? 'cursor-pointer border-cyan-300/30 bg-cyan-300/[0.06] hover:border-cyan-200/50 hover:bg-cyan-300/[0.09]'
                                    : 'cursor-not-allowed border-white/10 bg-white/[0.03] opacity-80'
                            }`}
                            aria-pressed="true"
                            aria-disabled={!selectedProviderEnabled}
                        >
                            <div className="flex items-center justify-between gap-3">
                                <div className="flex items-start gap-3">
                                    <span
                                        className={`mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border ${
                                            selectedProviderEnabled
                                                ? 'border-cyan-300 bg-cyan-300 text-slate-950'
                                                : 'border-white/20 bg-white/5 text-transparent'
                                        }`}
                                        aria-hidden="true"
                                    >
                                        ✓
                                    </span>
                                    <div>
                                        <p className="text-sm font-semibold text-white">{providerLabel}</p>
                                        <p className="mt-1 text-xs leading-5 text-slate-400">{providerDescription}</p>
                                    </div>
                                </div>
                                <span className="rounded-full bg-cyan-400/10 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-cyan-300">
                                    {selectedProviderEnabled ? 'Selected' : 'Setup required'}
                                </span>
                            </div>
                        </button>

                        {!selectedProviderEnabled && !developerPurchasesEnabled && (
                            <div className="mt-4 rounded-xl border border-amber-300/20 bg-amber-300/10 px-4 py-3 text-sm text-amber-100">
                                {providerLabel} is not configured yet. Add its keys to the server environment before accepting payments.
                            </div>
                        )}

                        <div className="mt-6 flex justify-end gap-3">
                            <button
                                type="button"
                                onClick={() => setSelectedPackage(null)}
                                className="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/5"
                            >
                                Cancel
                            </button>

                            <button
                                type="button"
                                disabled={!selectedProviderEnabled || purchasing}
                                onClick={startCheckout}
                                title={
                                    selectedProviderEnabled
                                        ? `Open ${providerLabel} secure checkout`
                                        : `${providerLabel} sandbox keys are not configured`
                                }
                                className="rounded-xl bg-white px-4 py-2.5 text-sm font-black text-slate-950 transition hover:bg-cyan-100 disabled:cursor-not-allowed disabled:bg-white/10 disabled:text-slate-500"
                            >
                                {purchasing
                                    ? 'Opening checkout…'
                                    : selectedProviderEnabled
                                        ? `Continue with ${providerLabel}`
                                        : `Configure ${providerLabel}`}
                            </button>

                        </div>
                    </section>
                </div>
            )}
        </div>
    );
}
