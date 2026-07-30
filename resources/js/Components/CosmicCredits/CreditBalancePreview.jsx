export default function CreditBalancePreview({ balance = 0, cost = 0, className = '' }) {
    const current = Number(balance ?? 0);
    const price = Number(cost ?? 0);
    const after = Math.max(0, current - price);
    const insufficient = current < price;

    return (
        <div className={`grid grid-cols-3 gap-2 rounded-xl border border-white/10 bg-black/20 p-3 text-center ${className}`}>
            <div>
                <p className="text-[9px] font-semibold uppercase tracking-[0.12em] text-slate-500">Balance</p>
                <p className="mt-1 text-sm font-semibold text-white">⚡ {current}</p>
            </div>
            <div>
                <p className="text-[9px] font-semibold uppercase tracking-[0.12em] text-slate-500">Cost</p>
                <p className="mt-1 text-sm font-semibold text-amber-200">− ⚡ {price}</p>
            </div>
            <div>
                <p className="text-[9px] font-semibold uppercase tracking-[0.12em] text-slate-500">After</p>
                <p className={`mt-1 text-sm font-semibold ${insufficient ? 'text-rose-300' : 'text-emerald-200'}`}>⚡ {after}</p>
            </div>
        </div>
    );
}
