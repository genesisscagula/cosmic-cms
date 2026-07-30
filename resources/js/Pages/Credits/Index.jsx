import { Head, Link, router } from '@inertiajs/react';
import axios from 'axios';
import { useMemo, useState } from 'react';
import { useCreditBalance } from '@/Components/CosmicCredits/CreditBalanceContext';

function formatDate(value) {
    if (!value) return '';

    return new Intl.DateTimeFormat('en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export default function CreditsIndex({
    balance = 0,
    transactions,
    packages = {},
    paymentsEnabled = false,
    developerPurchasesEnabled = false,
}) {
    const wallet = useCreditBalance();
    const [currentBalance, setCurrentBalance] = useState(Number(balance));
    const [selectedPackage, setSelectedPackage] = useState(null);
    const [purchasing, setPurchasing] = useState(false);
    const [notice, setNotice] = useState('');
    const [error, setError] = useState('');
    const rows = transactions?.data ?? [];

    const packageRows = useMemo(
        () => Object.entries(packages).map(([key, value]) => ({ key, ...value })),
        [packages],
    );

    const syncBalance = (nextBalance) => {
        const normalized = Number(nextBalance ?? 0);
        setCurrentBalance(normalized);
        wallet.setBalance(normalized);
    };

    const simulatePurchase = async (packageKey) => {
        if (!developerPurchasesEnabled || purchasing) return;

        setPurchasing(true);
        setError('');
        setNotice('');

        try {
            const response = await axios.post(route('credits.purchase'), { package: packageKey });
            syncBalance(response.data.credit_balance);
            setNotice(response.data.message);
            setSelectedPackage(null);
            router.reload({ only: ['transactions'], preserveScroll: true, preserveState: true });
        } catch (purchaseError) {
            setError(purchaseError.response?.data?.message || 'Unable to add credits right now.');
        } finally {
            setPurchasing(false);
        }
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

                <section id="credit-packages" className="mt-8">
                    <div className="mb-4">
                        <h1 className="text-xl font-semibold text-white">Buy Credits</h1>
                        <p className="mt-1 text-sm text-slate-500">USD is shown only when topping up your Cosmic Credits.</p>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        {packageRows.map((item) => (
                            <button
                                type="button"
                                key={item.key}
                                onClick={() => setSelectedPackage(item)}
                                className="rounded-2xl border border-white/10 bg-[#141416] p-5 text-left transition hover:-translate-y-0.5 hover:border-cyan-300/30 hover:bg-white/[0.06]"
                            >
                                <p className="text-sm font-semibold text-slate-300">{item.label}</p>
                                <p className="mt-4 text-2xl font-black text-white">⚡ {item.credits}</p>
                                <p className="mt-1 text-sm font-semibold text-cyan-300">${item.price_usd} USD</p>
                            </button>
                        ))}
                    </div>

                    {developerPurchasesEnabled && (
                        <div className="mt-4 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-amber-300/20 bg-amber-300/[0.07] p-5">
                            <div>
                                <p className="font-semibold text-amber-100">Developer Test Top-up</p>
                                <p className="mt-1 text-sm text-amber-100/60">Adds ⚡1000 instantly and records it in transaction history.</p>
                            </div>
                            <button
                                type="button"
                                disabled={purchasing}
                                onClick={() => simulatePurchase('developer_1000')}
                                className="rounded-xl bg-amber-300 px-4 py-2.5 text-sm font-black text-amber-950 transition hover:bg-amber-200 disabled:cursor-wait disabled:opacity-60"
                            >
                                {purchasing ? 'Adding…' : 'Load ⚡1000'}
                            </button>
                        </div>
                    )}
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
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm" onMouseDown={() => setSelectedPackage(null)}>
                    <section className="w-full max-w-md rounded-3xl border border-white/10 bg-[#151519] p-6 shadow-2xl" onMouseDown={(event) => event.stopPropagation()}>
                        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Order summary</p>
                        <div className="mt-5 rounded-2xl border border-white/10 bg-black/20 p-5">
                            <div className="flex items-center justify-between gap-4">
                                <div>
                                    <p className="font-semibold text-white">{selectedPackage.label}</p>
                                    <p className="mt-1 text-2xl font-black text-white">⚡ {selectedPackage.credits}</p>
                                </div>
                                <p className="text-xl font-bold text-cyan-300">${selectedPackage.price_usd} USD</p>
                            </div>
                        </div>

                        <div className="mt-5 rounded-xl border border-white/10 bg-white/[0.03] px-4 py-3 text-sm text-slate-400">
                            {developerPurchasesEnabled
                                ? 'Developer simulation is active. No real payment will be processed.'
                                : paymentsEnabled
                                    ? 'Continue to secure payment.'
                                    : 'Online payments are coming soon.'}
                        </div>

                        <div className="mt-6 flex justify-end gap-3">
                            <button type="button" onClick={() => setSelectedPackage(null)} className="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/5">
                                Cancel
                            </button>
                            <button
                                type="button"
                                disabled={!developerPurchasesEnabled || purchasing}
                                onClick={() => simulatePurchase(selectedPackage.key)}
                                className="rounded-xl bg-white px-4 py-2.5 text-sm font-black text-slate-950 transition hover:bg-cyan-100 disabled:cursor-not-allowed disabled:bg-white/10 disabled:text-slate-500"
                            >
                                {purchasing ? 'Processing…' : developerPurchasesEnabled ? 'Simulate Purchase' : 'Payments Coming Soon'}
                            </button>
                        </div>
                    </section>
                </div>
            )}
        </div>
    );
}
