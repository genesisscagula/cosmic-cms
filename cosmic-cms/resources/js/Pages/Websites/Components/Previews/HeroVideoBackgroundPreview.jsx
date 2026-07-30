export default function HeroVideoBackgroundPreview() {
    return (
        <div className="relative h-40 overflow-hidden rounded-xl border border-slate-700 bg-[linear-gradient(135deg,#334155_0%,#0f172a_55%,#020617_100%)]">
            <div className="absolute inset-0 bg-gradient-to-r from-slate-950/90 via-slate-950/50 to-transparent" />

            <div className="relative z-10 flex h-full flex-col justify-center p-5">
                <div className="h-1.5 w-16 rounded bg-violet-300/80" />

                <div className="mt-3 h-3 w-2/3 rounded bg-white" />
                <div className="mt-1.5 h-3 w-1/2 rounded bg-white" />

                <div className="mt-3 h-1.5 w-3/5 rounded bg-white/50" />
                <div className="mt-1.5 h-1.5 w-2/5 rounded bg-white/40" />

                <div className="mt-4 flex gap-2">
                    <div className="h-5 w-16 rounded-full bg-violet-500" />
                    <div className="h-5 w-16 rounded-full border border-white/30 bg-white/10" />
                </div>
            </div>

            <div className="absolute right-5 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full border-2 border-white/30 bg-white text-xs text-slate-950 shadow-xl">
                ▶
            </div>

            <div className="absolute bottom-3 left-5 flex items-center gap-1.5">
                <div className="h-4 w-2.5 rounded-full border border-white/40" />
                <div className="h-1 w-9 rounded bg-white/50" />
            </div>

            <div className="absolute bottom-3 right-3 h-8 w-14 overflow-hidden rounded-md border border-white/20 bg-slate-700">
                <div className="h-full w-full bg-[linear-gradient(135deg,#64748b,#1e293b)]" />
            </div>
        </div>
    );
}