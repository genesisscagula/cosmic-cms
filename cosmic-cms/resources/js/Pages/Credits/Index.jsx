import { Head, Link } from '@inertiajs/react';

function formatDate(value) {
    if (!value) return '';

    return new Intl.DateTimeFormat('en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export default function CreditsIndex({ balance = 0, transactions }) {
    const rows = transactions?.data ?? [];

    return (
        <div className="min-h-screen bg-[#0a0a0b] text-slate-100">
            <Head title="Cosmic Credits" />

            <header className="border-b border-white/10 bg-[#111113]">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-4">
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-cyan-400 font-black text-slate-950">C</span>
                        <div>
                            <p className="font-semibold text-white">Cosmic Credits</p>
                            <p className="text-xs text-slate-500">Wallet and transaction history</p>
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
                            <strong className="text-5xl font-black tracking-tight text-white">{balance}</strong>
                            <span className="pb-1 text-slate-400">Cosmic Credits</span>
                        </div>
                        <button type="button" disabled className="cursor-not-allowed rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm font-semibold text-slate-500">
                            Buy credits — coming soon
                        </button>
                    </div>
                </section>

                <section className="mt-8 overflow-hidden rounded-2xl border border-white/10 bg-[#141416]">
                    <div className="border-b border-white/10 px-5 py-4">
                        <h1 className="text-lg font-semibold text-white">Transaction history</h1>
                        <p className="mt-1 text-sm text-slate-500">Every grant, charge, and refund will appear here.</p>
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
        </div>
    );
}
