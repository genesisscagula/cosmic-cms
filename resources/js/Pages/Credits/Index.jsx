import { Head, Link } from '@inertiajs/react';
import axios from 'axios';
import { useMemo, useState } from 'react';
import CosmicBrandMark from '@/Components/CosmicBrandMark';

function formatDate(value) {
    if (!value) return '';

    return new Intl.DateTimeFormat('en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}


const subscriptionBadgeClasses = {
    active: 'border-emerald-400/25 bg-emerald-400/10 text-emerald-200',
    pending: 'border-amber-300/25 bg-amber-300/10 text-amber-100',
    danger: 'border-rose-400/25 bg-rose-400/10 text-rose-200',
    warning: 'border-orange-300/25 bg-orange-300/10 text-orange-100',
    cancelled: 'border-slate-400/20 bg-slate-400/10 text-slate-300',
    expired: 'border-white/10 bg-white/[0.04] text-slate-400',
};

function compactIdentifier(value) {
    if (!value) return 'Not available';
    if (value.length <= 18) return value;

    return `${value.slice(0, 9)}…${value.slice(-6)}`;
}

function formatMoney(amount, currency) {
    return new Intl.NumberFormat(currency === 'PHP' ? 'en-PH' : 'en-US', {
        style: 'currency',
        currency,
        maximumFractionDigits: currency === 'PHP' ? 0 : 2,
    }).format(Number(amount ?? 0));
}

function capabilityLabel(value, labels = {}) {
    if (value === null || value === 'unlimited') return 'Unlimited';
    if (value === true) return 'Included';
    if (value === false || value === 'none' || value === 0) return 'Not included';
    return labels[value] || String(value).replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

const COMPARISON_ROWS = [
    { label: 'Websites', key: 'max_sites', format: (value) => value == null ? 'Unlimited' : String(value) },
    { label: 'Pages per website', key: 'max_pages_per_site', format: (value) => value == null ? 'Unlimited' : String(value) },
    { label: 'Active Sparks', key: 'max_sparks_per_site', format: (value) => value == null ? 'Unlimited' : String(value) },
    { label: 'Owned Sparks', key: 'max_owned_sparks', format: (value) => value == null ? 'Unlimited' : String(value) },
    { label: 'Templates', key: 'template_limit', format: (value) => value == null ? 'All' : String(value) },
    { label: 'Analytics', key: 'analytics_level' },
    { label: 'Leads', key: 'leads_level' },
    { label: 'Sales', key: 'sales_level' },
    { label: 'Team members', key: 'team_members', format: (value) => value == null ? 'Unlimited' : String(value) },
    { label: 'White label', key: 'white_label_level' },
    { label: 'API access', key: 'api_access' },
];

export default function CreditsIndex({
    balance = 0,
    creditsSummary = {},
    transactions,
    packages = {},
    plans = {},
    currentPlan = null,
    pendingCheckout = null,
    paymentRouting = {},
    developerPurchasesEnabled = false,
    statusMessage = '',
    paymentError = '',
    billingTransactions = [],
    planChangeMatrix = [],
}) {
    const currentBalance = Number(creditsSummary.current_balance ?? balance);
    const summaryCards = [
        { label: 'Current Credits', value: currentBalance, detail: 'Available to use now', icon: '⚡' },
        {
            label: 'Monthly Included',
            value: Number(creditsSummary.monthly_included ?? currentPlan?.credits ?? 0),
            detail: currentPlan ? `${currentPlan.label} plan allocation` : 'No active monthly plan',
            icon: '↻',
        },
        { label: 'Purchased Credits', value: Number(creditsSummary.purchased_total ?? 0), detail: 'All-time top-up credits', icon: '+' },
        {
            label: 'Used This Month',
            value: Number(creditsSummary.used_this_month ?? 0),
            detail: creditsSummary.period_label || 'Current month',
            icon: '−',
        },
        { label: 'Granted This Month', value: Number(creditsSummary.granted_this_month ?? 0), detail: 'Plans, purchases, and refunds', icon: '↑' },
    ];
    const [selectedPackage, setSelectedPackage] = useState(null);
    const [purchasing, setPurchasing] = useState(false);
    const [subscriptionAction, setSubscriptionAction] = useState('');
    const [transactionFilter, setTransactionFilter] = useState('all');
    const upgradeQuery = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : null;
    const recommendedPlanKey = upgradeQuery?.get('plan') || '';
    const requestedFamily = upgradeQuery?.get('family');
    const [selectedPlanFamily, setSelectedPlanFamily] = useState(requestedFamily === 'agency' || requestedFamily === 'personal' ? requestedFamily : (currentPlan?.key?.startsWith('agency_') ? 'agency' : 'personal'));
    const notice = statusMessage || '';
    const [error, setError] = useState(paymentError || '');
    const rows = transactions?.data ?? [];
    const usageByCategory = creditsSummary.usage_by_category ?? {};
    const usageRows = [
        ['websites', 'Websites'],
        ['ai', 'AI & Images'],
        ['sparks', 'Sparks'],
        ['themes', 'Themes'],
        ['other', 'Other'],
    ].map(([key, label]) => ({ key, label, value: Number(usageByCategory[key] ?? 0) }));
    const filteredRows = transactionFilter === 'all'
        ? rows
        : rows.filter((transaction) => transaction.category === transactionFilter);

    const packageRows = useMemo(
        () => Object.entries(packages).map(([key, value]) => ({ key, ...value })),
        [packages],
    );
    const planRows = useMemo(
        () => Object.entries(plans).map(([key, value]) => ({ key, product_type: 'plan', ...value })),
        [plans],
    );
    const planOrder = useMemo(
        () => Object.fromEntries(planRows.map((plan, index) => [plan.key, Number(plan.rank ?? index)])),
        [planRows],
    );
    const planFamilies = useMemo(() => ([
        { key: 'personal', label: 'Personal plans', description: 'One complete website for your business or brand.' },
        { key: 'agency', label: 'Agency plans', description: 'Multi-website tools for freelancers and client teams.' },
    ]).map((family) => ({
        ...family,
        plans: planRows.filter((plan) => (plan.family || 'personal') === family.key),
    })).filter((family) => family.plans.length > 0), [planRows]);
    const visiblePlanFamily = planFamilies.find((family) => family.key === selectedPlanFamily) || planFamilies[0];
    const transitionByTarget = useMemo(
        () => Object.fromEntries((planChangeMatrix || []).map((decision) => [decision.to, decision])),
        [planChangeMatrix],
    );

    const activePlanKey = currentPlan?.key || '';
    const activePlanStatus = String(currentPlan?.status || '').toLowerCase();
    const hasActivePlan = Boolean(activePlanKey) && activePlanStatus === 'active';
    const hasPlanAccess = Boolean(currentPlan?.has_access ?? hasActivePlan);
    const cancelAtPeriodEnd = Boolean(currentPlan?.cancel_at_period_end);
    const subscriptionBadge = currentPlan?.status_badge || {
        label: activePlanStatus ? activePlanStatus.replaceAll('_', ' ') : 'Not Subscribed',
        tone: hasActivePlan ? 'active' : 'expired',
    };
    const subscriptionBadgeClass = subscriptionBadgeClasses[subscriptionBadge.tone] || subscriptionBadgeClasses.expired;

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
    const selectedPlanTransition = selectedProductType === 'plan'
        ? transitionByTarget[selectedPackage?.key]
        : null;

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

    const resumePayment = () => {
        if (!pendingCheckout?.checkout_url || purchasing) return;

        setPurchasing(true);
        setError('');
        window.location.assign(pendingCheckout.checkout_url);
    };

    const cancelSubscription = async () => {
        if (Boolean(subscriptionAction)) return;

        const confirmed = window.confirm(
            'Cancel your PayPal subscription? You will keep access until the end of the current paid period.',
        );

        if (!confirmed) return;

        setSubscriptionAction('cancel');
        setError('');

        try {
            const response = await axios.post(route('payments.subscription.cancel'));
            window.location.assign(`${route('credits.index')}?subscription_cancelled=1`);
        } catch (cancelError) {
            setError(cancelError.response?.data?.message || 'Unable to cancel the subscription.');
            setSubscriptionAction('');
        }
    };

    const syncSubscription = async () => {
        if (Boolean(subscriptionAction)) return;

        setSubscriptionAction('sync');
        setError('');

        try {
            await axios.post(route('payments.subscription.sync'));
            window.location.reload();
        } catch (syncError) {
            setError(syncError.response?.data?.message || 'Unable to sync the subscription.');
            setSubscriptionAction('');
        }
    };

    const recoverSubscription = async () => {
        if (Boolean(subscriptionAction)) return;

        setSubscriptionAction('recover');
        setError('');

        try {
            const response = await axios.post(route('payments.subscription.recover'));
            if (!response.data?.recovered) {
                setError(response.data?.message || 'PayPal has not restored the subscription yet.');
                setSubscriptionAction('');
                return;
            }
            window.location.assign(`${route('credits.index')}?billing_recovered=1`);
        } catch (recoveryError) {
            setError(recoveryError.response?.data?.message || 'Unable to recover the subscription.');
            setSubscriptionAction('');
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
        <div className="cosmic-ui-shell cosmic-credits-page min-h-screen bg-[#0a0a0b] text-slate-100">
            <Head title="Cosmic Credits" />

            <header className="border-b border-white/10 bg-[#111113]">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-4">
                    <div className="flex items-center gap-3">
                        <CosmicBrandMark />
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

                <section className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    {summaryCards.map((card) => (
                        <article key={card.label} className="rounded-2xl border border-white/10 bg-[#141416] p-5">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{card.label}</p>
                                    <p className="mt-3 text-3xl font-black tracking-tight text-white">{card.value.toLocaleString()}</p>
                                </div>
                                <span className="flex h-9 w-9 items-center justify-center rounded-xl border border-cyan-300/15 bg-cyan-300/10 font-black text-cyan-200">
                                    {card.icon}
                                </span>
                            </div>
                            <p className="mt-3 text-xs text-slate-500">{card.detail}</p>
                        </article>
                    ))}
                </section>

                <section className="mt-6 rounded-2xl border border-white/10 bg-[#141416] p-5">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="font-semibold text-white">Account wallet usage</h2>
                            <p className="mt-1 text-sm text-slate-500">One Cosmic Credits balance shared across your websites, AI tools, themes, and Sparks.</p>
                        </div>
                        <span className="rounded-full border border-cyan-300/20 bg-cyan-300/10 px-3 py-1 text-xs font-semibold text-cyan-200">Account-wide wallet</span>
                    </div>
                    <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        {usageRows.map((item) => (
                            <div key={item.key} className="rounded-xl border border-white/10 bg-black/20 p-4">
                                <p className="text-xs uppercase tracking-wide text-slate-500">{item.label}</p>
                                <p className="mt-2 text-2xl font-black text-white">{item.value}</p>
                                <p className="mt-1 text-xs text-slate-600">credits used this month</p>
                            </div>
                        ))}
                    </div>
                </section>

                {notice && <div className="mt-5 rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-200">{notice}</div>}
                {error && <div className="mt-5 rounded-xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">{error}</div>}

                {pendingCheckout && (
                    <section className="mt-8 rounded-3xl border border-amber-300/20 bg-amber-300/[0.07] p-6">
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-amber-200">Pending payment</p>
                                <h2 className="mt-2 text-lg font-semibold text-white">
                                    {pendingCheckout.is_plan_change
                                        ? `Finish your ${pendingCheckout.change_type || 'plan'} to ${pendingCheckout.plan_label}`
                                        : `Finish your ${pendingCheckout.plan_label} subscription`}
                                </h2>
                                <p className="mt-1 text-sm text-slate-400">
                                    The same PayPal checkout will reopen. Your current plan stays active until the new plan is confirmed, and no duplicate subscription will be created.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={resumePayment}
                                disabled={purchasing}
                                className="rounded-xl bg-amber-300 px-4 py-2.5 text-sm font-bold text-slate-950 transition hover:bg-amber-200 disabled:opacity-60"
                            >
                                {purchasing ? 'Opening PayPal…' : pendingCheckout.cancelled_at ? 'Retry Payment' : 'Resume Payment'}
                            </button>
                        </div>
                    </section>
                )}

                <section className="mt-8 overflow-hidden rounded-3xl border border-white/10 bg-[#141416]">
                    <div className="border-b border-white/10 p-6">
                        <div className="flex flex-wrap items-start justify-between gap-5">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-[0.2em] text-violet-300">Subscription Center</p>
                                <h2 className="mt-2 text-xl font-semibold text-white">
                                    {currentPlan ? `${currentPlan.label} Monthly` : 'No active monthly plan'}
                                </h2>
                                <p className="mt-1 text-sm text-slate-500">
                                    {currentPlan
                                        ? 'Manage your recurring plan, billing status, renewal, and plan changes.'
                                        : 'Choose a monthly plan below to activate recurring credits.'}
                                </p>
                            </div>

                            <span className={`rounded-full border px-3 py-1.5 text-xs font-bold uppercase tracking-wide ${subscriptionBadgeClass}`}>
                                ● {subscriptionBadge.label}
                            </span>
                        </div>
                    </div>

                    {currentPlan ? (
                        <div className="p-6">
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <div className="rounded-2xl border border-white/10 bg-black/20 p-4">
                                    <p className="text-xs uppercase tracking-wide text-slate-500">Current plan</p>
                                    <p className="mt-2 font-semibold text-white">{currentPlan.label}</p>
                                    <p className="mt-1 text-xs text-slate-500">{currentPlan.billing_cycle || 'Monthly'} subscription</p>
                                </div>
                                <div className="rounded-2xl border border-white/10 bg-black/20 p-4">
                                    <p className="text-xs uppercase tracking-wide text-slate-500">Plan price</p>
                                    <p className="mt-2 font-semibold text-white">{formatMoney(currentPlan.price_usd, 'USD')}/month</p>
                                    <p className="mt-1 text-xs text-slate-500">Recurring through {currentPlan.provider || 'PayPal'}</p>
                                </div>
                                <div className="rounded-2xl border border-white/10 bg-black/20 p-4">
                                    <p className="text-xs uppercase tracking-wide text-slate-500">Monthly credits</p>
                                    <p className="mt-2 font-semibold text-cyan-300">⚡ {currentPlan.credits}</p>
                                    <p className="mt-1 text-xs text-slate-500">Granted after successful billing</p>
                                </div>
                                <div className="rounded-2xl border border-white/10 bg-black/20 p-4">
                                    <p className="text-xs uppercase tracking-wide text-slate-500">Credits remaining</p>
                                    <p className="mt-2 font-semibold text-cyan-300">⚡ {currentBalance}</p>
                                    <p className="mt-1 text-xs text-slate-500">Available across your workspace</p>
                                </div>
                                <div className="rounded-2xl border border-white/10 bg-black/20 p-4">
                                    <p className="text-xs uppercase tracking-wide text-slate-500">Started</p>
                                    <p className="mt-2 font-semibold text-white">{currentPlan.started_at ? formatDate(currentPlan.started_at) : 'Pending activation'}</p>
                                </div>
                                <div className="rounded-2xl border border-white/10 bg-black/20 p-4">
                                    <p className="text-xs uppercase tracking-wide text-slate-500">Next billing</p>
                                    <p className="mt-2 font-semibold text-white">
                                        {currentPlan.renews_at ? formatDate(currentPlan.renews_at) : 'Pending provider sync'}
                                    </p>
                                </div>
                                <div className="rounded-2xl border border-white/10 bg-black/20 p-4">
                                    <p className="text-xs uppercase tracking-wide text-slate-500">Payment provider</p>
                                    <p className="mt-2 font-semibold capitalize text-white">{currentPlan.provider || 'PayPal'}</p>
                                    <p className="mt-1 text-xs text-slate-500">Subscription payments</p>
                                </div>
                                <div className="rounded-2xl border border-white/10 bg-black/20 p-4">
                                    <p className="text-xs uppercase tracking-wide text-slate-500">Subscription ID</p>
                                    <p className="mt-2 break-all font-mono text-sm font-semibold text-white" title={currentPlan.subscription_id || ''}>
                                        {compactIdentifier(currentPlan.subscription_id)}
                                    </p>
                                </div>
                            </div>

                            <div className="mt-5 grid gap-3 lg:grid-cols-2">
                                <div className="rounded-2xl border border-white/10 bg-white/[0.025] p-4">
                                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Billing lifecycle</p>
                                    <div className="mt-3 space-y-2 text-sm">
                                        <div className="flex items-center justify-between gap-4"><span className="text-slate-400">Status</span><span className="font-semibold text-white">{subscriptionBadge.label}</span></div>
                                        <div className="flex items-center justify-between gap-4"><span className="text-slate-400">Last payment</span><span className="text-right font-semibold text-white">{currentPlan.last_payment_at ? formatDate(currentPlan.last_payment_at) : 'No completed payment recorded'}</span></div>
                                        <div className="flex items-center justify-between gap-4"><span className="text-slate-400">Last provider sync</span><span className="text-right font-semibold text-white">{currentPlan.last_synced_at ? formatDate(currentPlan.last_synced_at) : 'Not synced yet'}</span></div>
                                    </div>
                                </div>

                                <div className="rounded-2xl border border-white/10 bg-white/[0.025] p-4">
                                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Renewal status</p>
                                    <p className="mt-3 text-sm leading-6 text-slate-400">
                                        {cancelAtPeriodEnd
                                            ? `Renewal is cancelled. Access remains available until ${currentPlan.renews_at ? formatDate(currentPlan.renews_at) : 'the end of the paid period'}.`
                                            : 'Automatic renewal is enabled. PayPal will process the next monthly payment on the billing date shown above.'}
                                    </p>
                                    {currentPlan.cancelled_at && (
                                        <p className="mt-2 text-xs text-slate-500">Cancellation recorded {formatDate(currentPlan.cancelled_at)}.</p>
                                    )}
                                </div>
                            </div>

                            {currentPlan.subscription_id && (
                                <div className="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-5">
                                    <p className="max-w-2xl text-sm text-slate-400">
                                        Upgrade or downgrade by choosing another plan below. Your current subscription stays active until PayPal confirms the replacement plan.
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        {currentPlan.can_recover && (
                                            <button type="button" disabled={Boolean(subscriptionAction)} onClick={recoverSubscription} className="rounded-xl bg-amber-300 px-4 py-2 text-sm font-bold text-slate-950 transition hover:bg-amber-200 disabled:opacity-50">
                                                {subscriptionAction === 'recover' ? 'Recovering…' : currentPlan.status === 'suspended' ? 'Reactivate Subscription' : 'Retry Billing Recovery'}
                                            </button>
                                        )}
                                        <button type="button" disabled={Boolean(subscriptionAction)} onClick={syncSubscription} className="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5 disabled:opacity-50">
                                            {subscriptionAction === 'sync' ? 'Syncing…' : 'Sync with PayPal'}
                                        </button>
                                        {!cancelAtPeriodEnd && (
                                            <button type="button" disabled={Boolean(subscriptionAction)} onClick={cancelSubscription} className="cosmic-cancel-subscription rounded-xl border border-rose-400/40 bg-rose-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-600 disabled:opacity-50">
                                                {subscriptionAction === 'cancel' ? 'Cancelling…' : 'Cancel Subscription'}
                                            </button>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="p-8 text-center">
                            <p className="text-sm text-slate-400">No subscription is currently connected to this account.</p>
                            <a href="#monthly-plans" className="mt-4 inline-flex rounded-xl bg-violet-300 px-4 py-2.5 text-sm font-bold text-slate-950 transition hover:bg-violet-200">Choose a plan</a>
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

                    <div id="monthly-plans" className="mb-5 mt-10 scroll-mt-8">
                        <div className="flex flex-wrap items-end justify-between gap-4">
                            <div>
                                <h2 className="text-xl font-semibold text-white">Compare monthly plans</h2>
                                <p className="mt-1 text-sm text-slate-500">Choose Personal for one brand website or Agency for multi-client operations.</p>
                            </div>
                            <div className="inline-flex rounded-xl border border-white/10 bg-black/20 p-1">
                                {planFamilies.map((family) => (
                                    <button
                                        key={family.key}
                                        type="button"
                                        onClick={() => setSelectedPlanFamily(family.key)}
                                        className={`rounded-lg px-4 py-2 text-sm font-bold transition ${
                                            selectedPlanFamily === family.key
                                                ? 'bg-white text-slate-950'
                                                : 'text-slate-400 hover:text-white'
                                        }`}
                                    >
                                        {family.key === 'personal' ? 'Personal' : 'Agency'}
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>

                    {visiblePlanFamily && (
                        <div className="space-y-5">
                            <div>
                                <h3 className="text-base font-semibold text-white">{visiblePlanFamily.label}</h3>
                                <p className="mt-1 text-sm text-slate-500">{visiblePlanFamily.description}</p>
                            </div>

                            <div className="grid gap-4 md:grid-cols-3">
                                {visiblePlanFamily.plans.map((plan) => {
                                    const isCurrent = hasPlanAccess && plan.key === activePlanKey;
                                    const isRecommended = !isCurrent && recommendedPlanKey === plan.key;
                                    const transition = transitionByTarget[plan.key];
                                    const actionLabel = isCurrent
                                        ? 'Current plan'
                                        : transition?.type === 'downgrade'
                                            ? 'Downgrade'
                                            : transition?.family_change
                                                ? `Switch to ${plan.family === 'agency' ? 'Agency' : 'Personal'}`
                                                : 'Upgrade';
                                    const maxSites = plan.capabilities?.max_sites;
                                    const sitesLabel = maxSites == null ? 'Unlimited websites' : `${maxSites} website${Number(maxSites) === 1 ? '' : 's'}`;
                                    const featureHighlights = [
                                        `${plan.capabilities?.max_pages_per_site == null ? 'Unlimited' : plan.capabilities?.max_pages_per_site} pages/site`,
                                        `${plan.capabilities?.max_owned_sparks ?? 'Unlimited'} Owned Sparks`,
                                        capabilityLabel(plan.capabilities?.analytics_level) + ' analytics',
                                    ];

                                    return (
                                        <article
                                            key={plan.key}
                                            className={`cosmic-plan-card relative flex flex-col rounded-2xl p-5 transition ${
                                                isCurrent
                                                    ? 'cosmic-plan-card--current border border-emerald-300/40 bg-white shadow-lg ring-1 ring-emerald-300/20'
                                                    : isRecommended
                                                        ? 'cosmic-plan-card--recommended border border-violet-300/45 bg-white shadow-lg ring-1 ring-violet-300/25'
                                                        : 'border border-white/10 bg-[#141416] hover:-translate-y-0.5 hover:border-violet-300/30 hover:bg-white/[0.06]'
                                            }`}
                                        >
                                            {isRecommended && (
                                                <span className="absolute right-4 top-4 rounded-full border border-violet-300/25 bg-violet-300/10 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-violet-200">
                                                    Recommended
                                                </span>
                                            )}
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
                                            <p className="mt-2 text-sm text-cyan-300">{Number(plan.credits || 0).toLocaleString()} monthly credits</p>
                                            <p className="mt-3 text-sm leading-6 text-slate-400">{plan.description}</p>
                                            <p className="mt-3 text-xs font-semibold uppercase tracking-wide text-violet-300">{sitesLabel}</p>
                                            <ul className="mt-4 space-y-2 text-sm text-slate-300">
                                                {featureHighlights.map((highlight) => (
                                                    <li key={highlight} className="flex gap-2"><span className="text-emerald-300">✓</span><span>{highlight}</span></li>
                                                ))}
                                            </ul>
                                            {transition?.warnings?.length > 0 && !isCurrent && (
                                                <p className="mt-4 rounded-xl border border-amber-300/15 bg-amber-300/[0.06] px-3 py-2 text-xs leading-5 text-amber-100/80">
                                                    {transition.warnings[0]}
                                                </p>
                                            )}
                                            <button
                                                type="button"
                                                disabled={isCurrent || transition?.allowed === false}
                                                onClick={() => !isCurrent && transition?.allowed !== false && setSelectedPackage(plan)}
                                                className={`cosmic-plan-action mt-auto pt-5 ${isCurrent || transition?.allowed === false ? 'cursor-default' : ''}`}
                                            >
                                                <span className={`block rounded-xl px-3 py-2 text-center text-sm font-bold ${
                                                    isCurrent
                                                        ? 'bg-emerald-300/10 text-emerald-200'
                                                        : transition?.allowed === false
                                                            ? 'bg-white/[0.03] text-slate-600'
                                                            : 'border border-white/10 bg-white/[0.04] text-slate-200 hover:bg-white/[0.08]'
                                                }`}>
                                                    {transition?.allowed === false && !isCurrent ? transition.reason || 'Unavailable' : actionLabel}
                                                </span>
                                            </button>
                                        </article>
                                    );
                                })}
                            </div>

                            <div className="overflow-x-auto rounded-2xl border border-white/10 bg-[#141416]">
                                <table className="min-w-[760px] w-full text-left text-sm">
                                    <thead className="border-b border-white/10 bg-white/[0.025]">
                                        <tr>
                                            <th className="px-4 py-3 font-semibold text-slate-400">Feature</th>
                                            {visiblePlanFamily.plans.map((plan) => (
                                                <th key={plan.key} className="px-4 py-3 font-semibold text-white">{plan.label}</th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-white/10">
                                        {COMPARISON_ROWS.map((row) => (
                                            <tr key={row.key}>
                                                <th className="px-4 py-3 font-medium text-slate-400">{row.label}</th>
                                                {visiblePlanFamily.plans.map((plan) => {
                                                    const value = plan.capabilities?.[row.key];
                                                    const display = row.format ? row.format(value) : capabilityLabel(value);
                                                    const unavailable = display === 'Not included';
                                                    return (
                                                        <td key={plan.key} className={`px-4 py-3 font-semibold ${unavailable ? 'text-slate-600' : 'text-slate-200'}`}>
                                                            {unavailable ? '—' : display}
                                                        </td>
                                                    );
                                                })}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </section>

                {billingTransactions.length > 0 && (
                    <section className="mt-8 overflow-hidden rounded-2xl border border-white/10 bg-[#141416]">
                        <div className="border-b border-white/10 px-5 py-4">
                            <h2 className="text-lg font-semibold text-white">Billing activity</h2>
                            <p className="mt-1 text-sm text-slate-500">Recent subscription payments, renewals, and failed billing attempts.</p>
                        </div>
                        <div className="divide-y divide-white/10">
                            {billingTransactions.map((transaction) => (
                                <article key={transaction.id} className="flex flex-wrap items-center justify-between gap-4 px-5 py-4">
                                    <div>
                                        <p className="font-medium capitalize text-white">{transaction.type.replaceAll('_', ' ')} payment</p>
                                        <p className="mt-1 text-xs text-slate-500">{formatDate(transaction.occurred_at)} · {transaction.provider}</p>
                                    </div>
                                    <div className="text-right">
                                        <p className={transaction.status === 'completed' ? 'font-bold text-emerald-300' : 'font-bold text-rose-300'}>
                                            {transaction.status === 'completed' ? 'Successful' : 'Failed'}
                                        </p>
                                        <p className="mt-1 text-xs text-slate-500">
                                            {formatMoney(transaction.amount_minor / 100, transaction.currency)}
                                            {transaction.credits_granted > 0 ? ` · +${transaction.credits_granted} credits` : ''}
                                        </p>
                                    </div>
                                </article>
                            ))}
                        </div>
                    </section>
                )}

                <section className="mt-8 overflow-hidden rounded-2xl border border-white/10 bg-[#141416]">
                    <div className="flex flex-wrap items-center justify-between gap-4 border-b border-white/10 px-5 py-4">
                        <div>
                            <h1 className="text-lg font-semibold text-white">Transaction history</h1>
                            <p className="mt-1 text-sm text-slate-500">Every account-level grant, charge, purchase, and refund appears here.</p>
                        </div>
                        <select value={transactionFilter} onChange={(event) => setTransactionFilter(event.target.value)} className="rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-sm text-slate-300 outline-none">
                            <option value="all">All activity</option>
                            <option value="websites">Websites</option>
                            <option value="ai">AI & Images</option>
                            <option value="sparks">Sparks</option>
                            <option value="themes">Themes</option>
                            <option value="subscription">Subscriptions</option>
                            <option value="purchase">Purchases</option>
                            <option value="refund">Refunds</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    {filteredRows.length === 0 ? (
                        <div className="p-10 text-center text-sm text-slate-500">No credit transactions yet.</div>
                    ) : (
                        <div className="divide-y divide-white/10">
                            {filteredRows.map((transaction) => {
                                const positive = transaction.amount > 0;

                                return (
                                    <article key={transaction.id} className="flex flex-wrap items-center justify-between gap-4 px-5 py-4">
                                        <div>
                                            <p className="font-medium text-white">{transaction.description}</p>
                                            <p className="mt-1 text-xs text-slate-500">
                                                <span className="mr-2 rounded-full border border-white/10 bg-white/[0.04] px-2 py-0.5 uppercase tracking-wide text-slate-400">{transaction.category || 'other'}</span>{transaction.website?.name ? `${transaction.website.name} · ` : ''}{formatDate(transaction.created_at)}
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

                    {(transactions?.links?.length ?? 0) > 3 && (
                        <nav className="flex flex-wrap items-center justify-center gap-2 border-t border-white/10 px-5 py-4" aria-label="Credit transaction pages">
                            {transactions.links.map((link, index) => (
                                link.url ? (
                                    <Link
                                        key={`${link.label}-${index}`}
                                        href={link.url}
                                        preserveScroll
                                        className={`min-w-9 rounded-lg border px-3 py-2 text-center text-xs font-semibold transition ${
                                            link.active
                                                ? 'border-cyan-300/30 bg-cyan-300/10 text-cyan-200'
                                                : 'border-white/10 text-slate-400 hover:bg-white/5 hover:text-white'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : (
                                    <span
                                        key={`${link.label}-${index}`}
                                        className="min-w-9 cursor-not-allowed rounded-lg border border-white/5 px-3 py-2 text-center text-xs font-semibold text-slate-700"
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                )
                            ))}
                        </nav>
                    )}
                </section>
            </main>

            {selectedPackage && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm"
                    onMouseDown={() => setSelectedPackage(null)}
                >
                    <section
                        className="cosmic-checkout-modal w-full max-w-md rounded-3xl border border-white/10 bg-[#151519] p-6 shadow-2xl"
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
                                <p>
                                    This will {(selectedPlanTransition?.type || planActionLabel(selectedPackage.key)).toLowerCase()} your current {currentPlan?.label} plan. Your old PayPal subscription is cancelled only after the new plan becomes active.
                                </p>
                                {selectedPlanTransition?.warnings?.map((warning) => (
                                    <p key={warning} className="mt-2 text-xs text-amber-100/75">• {warning}</p>
                                ))}
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
