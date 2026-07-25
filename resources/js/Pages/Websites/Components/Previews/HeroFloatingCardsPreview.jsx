export default function HeroFloatingCardsPreview() {
    return (
        <div className="flex h-40 overflow-hidden rounded-xl border border-slate-700 bg-slate-950">
            <div className="flex w-[48%] flex-col justify-center p-4">
                <div className="h-1.5 w-14 rounded bg-violet-300/80" />

                <div className="mt-3 h-3 w-full rounded bg-slate-100" />
                <div className="mt-1.5 h-3 w-5/6 rounded bg-slate-100" />

                <div className="mt-3 h-1.5 w-11/12 rounded bg-slate-400/70" />
                <div className="mt-1.5 h-1.5 w-4/5 rounded bg-slate-500/70" />

                <div className="mt-4 flex gap-2">
                    <div className="h-5 w-16 rounded-full bg-violet-500" />
                    <div className="h-5 w-16 rounded-full border border-slate-500" />
                </div>
            </div>

            <div className="relative flex-1 p-3">
                <div className="h-full rounded-xl bg-[linear-gradient(135deg,#64748b_0%,#334155_45%,#0f172a_100%)]" />

                <div className="absolute -bottom-1 left-0 w-16 rounded-lg border border-slate-600 bg-slate-900 p-2 shadow-lg">
                    <div className="h-3 w-8 rounded bg-slate-100" />
                    <div className="mt-1 h-1.5 w-full rounded bg-slate-500" />
                </div>

                <div className="absolute right-0 top-1 w-20 rounded-lg border border-slate-600 bg-slate-900 p-2 shadow-lg">
                    <div className="h-3 w-3 rounded bg-violet-500" />
                    <div className="mt-1.5 h-1.5 w-14 rounded bg-slate-200" />
                    <div className="mt-1 h-1.5 w-full rounded bg-slate-500" />
                </div>
            </div>
        </div>
    );
}