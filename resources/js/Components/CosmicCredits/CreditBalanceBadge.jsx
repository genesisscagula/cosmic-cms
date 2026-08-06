import { Link } from '@inertiajs/react';
import { useCreditBalance } from './CreditBalanceContext';

export default function CreditBalanceBadge({ balance, className = '' }) {
    const wallet = useCreditBalance();
    const rawAmount = balance ?? wallet.balance;
    const amount = rawAmount === null || rawAmount === undefined ? 0 : Number(rawAmount);

    return (
        <Link
            href={route('credits.index')}
            title="View Cosmic Credits"
            className={`cosmic-credit-badge inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg border px-3 text-xs font-extrabold transition focus:outline-none focus:ring-2 ${className}`}
        >
            <span aria-hidden="true">⚡</span>
            <span className="cosmic-credit-badge__amount">{Number.isFinite(amount) ? amount.toLocaleString() : 0}</span>
            <span className="hidden font-semibold sm:inline">Credits</span>
        </Link>
    );
}
