export default function HeroSliderFadePreview() {
    return (
        <div className="relative h-full min-h-[158px] overflow-hidden rounded-xl border border-slate-700 bg-slate-950">
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_75%_35%,rgba(139,92,246,0.28),transparent_36%),linear-gradient(115deg,#020617_0%,#111827_55%,#263449_100%)]" />
            <div className="relative flex h-full flex-col justify-center px-6 py-5">
                <span className="mb-3 h-1.5 w-16 rounded-full bg-violet-400" />
                <span className="mb-2 h-3 w-4/5 rounded bg-slate-100" />
                <span className="mb-2 h-3 w-3/5 rounded bg-slate-200" />
                <span className="mt-2 h-2 w-2/3 rounded bg-slate-500" />
                <div className="mt-4 flex gap-1.5">
                    <span className="h-6 w-16 rounded-full bg-white" />
                    <span className="h-6 w-14 rounded-full border border-white/35 bg-white/10" />
                    <span className="h-6 w-12 rounded-full border border-white/35 bg-white/10" />
                    <span className="h-6 w-10 rounded-full border border-white/25 bg-black/30" />
                </div>
            </div>
            <div className="absolute bottom-4 right-4 flex gap-1.5"><span className="h-1.5 w-5 rounded-full bg-white" /><span className="h-1.5 w-1.5 rounded-full bg-white/40" /><span className="h-1.5 w-1.5 rounded-full bg-white/40" /></div>
            <div className="absolute right-4 top-4 rounded-md border border-white/15 bg-black/30 px-2 py-1 text-[9px] font-bold uppercase tracking-wider text-white/70">Fade</div>
        </div>
    );
}
