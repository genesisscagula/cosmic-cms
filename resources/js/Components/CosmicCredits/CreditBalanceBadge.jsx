import { Link } from '@inertiajs/react';
import { useCreditBalance } from './CreditBalanceContext';

export default function CreditBalanceBadge({ balance, className = '' }) {
    const wallet = useCreditBalance();
    const rawAmount = balance ?? wallet.balance;
    const amount = rawAmount === null || rawAmount === undefined ? null : Number(rawAmount);

    return (
        <Link
            href={route('credits.index')}
            title="View Cosmic Credits"
            className={`inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg border border-amber-300/20 bg-amber-300/[0.08] px-3 text-xs font-bold text-amber-100 transition hover:border-amber-300/35 hover:bg-amber-300/[0.13] focus:outline-none focus:ring-2 focus:ring-amber-300/50 ${className}`}
        >
            <span aria-hidden="true">⚡</span>
            <span>{amount}</span>
            <span className="hidden font-medium text-amber-100/70 sm:inline">Credits</span>
        </Link>
    );
}
