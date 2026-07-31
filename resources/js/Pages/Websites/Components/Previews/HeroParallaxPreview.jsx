export default function HeroParallaxPreview() {
    return (
        <div className="relative h-40 overflow-hidden rounded-xl border border-violet-400/30 bg-slate-950">
            <div
                className="absolute -inset-3 scale-110 bg-cover bg-center"
                style={{ backgroundImage: "url('/storage/cms-images/background/background-1.avif')" }}
            />
            <div className="absolute inset-0 bg-gradient-to-r from-slate-950 via-slate-950/65 to-slate-950/15" />
            <div className="relative z-10 flex h-full flex-col justify-center px-5">
                <div className="h-2 w-24 rounded-full bg-violet-300/80" />
                <div className="mt-3 h-4 w-4/5 rounded-full bg-white" />
                <div className="mt-2 h-4 w-3/5 rounded-full bg-white" />
                <div className="mt-3 h-1.5 w-2/3 rounded-full bg-white/55" />
                <div className="mt-4 flex gap-2">
                    <div className="h-5 w-20 rounded-full bg-violet-400" />
                    <div className="h-5 w-20 rounded-full border border-white/35 bg-white/10" />
                </div>
            </div>
            <div className="absolute right-3 top-3 rounded-full border border-violet-300/30 bg-violet-400/15 px-2 py-1 text-[8px] font-bold tracking-wider text-violet-100">PRO</div>
        </div>
    );
}
