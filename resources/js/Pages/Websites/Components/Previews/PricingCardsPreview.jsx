export default function PricingCardsPreview() {
    return (
        <div className="h-40 overflow-hidden rounded-xl border border-slate-800 bg-slate-950 p-4">
            <div className="mx-auto mb-3 flex w-1/2 flex-col items-center gap-1.5">
                <div className="h-1.5 w-10 rounded bg-slate-600" />
                <div className="h-2 w-full rounded bg-slate-100" />
            </div>
            <div className="grid grid-cols-3 gap-2 pt-1">
                {[1, 2, 3].map((plan) => (
                    <div key={plan} className={`relative rounded-lg border p-2 ${plan === 2 ? 'border-violet-400 bg-slate-800' : 'border-slate-700 bg-slate-900'}`}>
                        {plan === 2 && <div className="absolute -top-1 left-1/2 h-2 w-10 -translate-x-1/2 rounded-full bg-violet-500" />}
                        <div className="mt-1 h-1.5 w-3/4 rounded bg-slate-300" />
                        <div className="mt-3 h-3 w-2/3 rounded bg-slate-100" />
                        <div className="mt-2 space-y-1.5">
                            {[1, 2, 3].map((line) => <div key={line} className="h-1 w-full rounded bg-slate-700" />)}
                        </div>
                        <div className="mt-3 h-3 w-full rounded-full bg-slate-500" />
                    </div>
                ))}
            </div>
        </div>
    );
}
