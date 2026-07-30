export default function HeroVideoStylePreview() {
    return (
        <div className="flex h-40 overflow-hidden rounded-xl border border-slate-700 bg-slate-950">
            <div className="flex w-[46%] flex-col justify-center p-4">
                <div className="h-1.5 w-14 rounded bg-violet-300/80" />

                <div className="mt-3 h-3 w-full rounded bg-slate-100" />
                <div className="mt-1.5 h-3 w-5/6 rounded bg-slate-100" />

                <div className="mt-3 h-1.5 w-full rounded bg-slate-400/70" />
                <div className="mt-1.5 h-1.5 w-4/5 rounded bg-slate-500/70" />

                <div className="mt-4 flex gap-2">
                    <div className="h-5 w-16 rounded-full bg-violet-500" />
                    <div className="h-5 w-16 rounded-full border border-slate-500" />
                </div>
            </div>

            <div className="relative flex-1 p-3">
                <div className="relative flex h-full items-center justify-center overflow-hidden rounded-xl bg-[linear-gradient(135deg,#64748b_0%,#334155_45%,#0f172a_100%)]">
                    <div className="flex h-10 w-10 items-center justify-center rounded-full border-2 border-white/30 bg-white text-xs text-slate-950 shadow-lg">
                        ▶
                    </div>

                    <div className="absolute bottom-3 left-3 h-2 w-20 rounded bg-white/80" />

                    <div className="absolute bottom-3 right-3 h-4 w-10 rounded-full border border-white/30 bg-slate-950/60" />
                </div>

                <div className="absolute -bottom-1 right-0 flex w-24 items-center gap-1.5 rounded-lg border border-slate-600 bg-slate-900 p-2 shadow-lg">
                    <div className="flex h-4 w-4 items-center justify-center rounded-full bg-violet-500 text-[6px] text-white">
                        ▶
                    </div>

                    <div className="flex-1">
                        <div className="h-1.5 w-full rounded bg-slate-200" />
                        <div className="mt-1 h-1 w-4/5 rounded bg-slate-500" />
                    </div>
                </div>
            </div>
        </div>
    );
}