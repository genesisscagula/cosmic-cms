export default function HeroSplitImagePreview() {
    return (
        <div className="flex h-40 overflow-hidden rounded-xl border border-slate-700 bg-slate-950">
            <div className="flex w-[55%] flex-col justify-center p-4">
                <div className="h-1.5 w-16 rounded bg-violet-300/80" />
                <div className="mt-3 h-3 w-full rounded bg-slate-100" />
                <div className="mt-1.5 h-3 w-4/5 rounded bg-slate-100" />
                <div className="mt-3 h-1.5 w-11/12 rounded bg-slate-400/70" />
                <div className="mt-1.5 h-1.5 w-3/4 rounded bg-slate-500/70" />
                <div className="mt-4 flex gap-2">
                    <div className="h-5 w-16 rounded-full bg-violet-500" />
                    <div className="h-5 w-16 rounded-full border border-slate-500" />
                </div>
            </div>
            <div className="relative flex-1 bg-[linear-gradient(135deg,#64748b_0%,#334155_45%,#0f172a_100%)]">
                <div className="absolute bottom-3 left-3 h-4 w-20 rounded-full bg-slate-950/80" />
            </div>
        </div>
    );
}
