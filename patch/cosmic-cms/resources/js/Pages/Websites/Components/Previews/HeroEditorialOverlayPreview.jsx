export default function HeroEditorialOverlayPreview() {
    return (
        <div className="relative flex h-40 overflow-hidden rounded-xl border border-slate-700 bg-slate-800">
            <div className="absolute inset-0 bg-[linear-gradient(115deg,#111827_0%,#334155_50%,#0f172a_100%)]" />
            <div className="absolute inset-0 bg-gradient-to-r from-slate-950/90 via-slate-950/55 to-slate-950/15" />
            <div className="relative flex w-2/3 flex-col justify-center p-4">
                <div className="h-1.5 w-16 rounded bg-violet-300/80" />
                <div className="mt-3 h-3 w-full rounded bg-white" />
                <div className="mt-1.5 h-3 w-4/5 rounded bg-white" />
                <div className="mt-3 h-1.5 w-11/12 rounded bg-slate-300/70" />
                <div className="mt-1.5 h-1.5 w-3/4 rounded bg-slate-400/60" />
                <div className="mt-4 flex gap-2">
                    <div className="h-5 w-16 rounded-full bg-violet-500" />
                    <div className="h-5 w-16 rounded-full border border-white/50" />
                </div>
            </div>
        </div>
    );
}
