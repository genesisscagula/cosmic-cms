export default function HeroBackgroundImagePreview() {
    return (
        <div
            className="relative h-40 overflow-hidden rounded-xl border border-slate-700 bg-slate-900"
            style={{
                backgroundImage: "url('/storage/cms-images/background/background-1.avif')",
                backgroundPosition: "center",
                backgroundSize: "cover",
            }}
        >
            <div className="absolute inset-0 bg-gradient-to-b from-slate-950/45 via-slate-950/60 to-slate-950/85" />

            <div className="relative z-10 flex h-full flex-col items-center justify-center px-5 text-center">
                <div className="h-1.5 w-20 rounded-full bg-white/70" />
                <div className="mt-3 h-3 w-3/4 rounded-full bg-white" />
                <div className="mt-1.5 h-3 w-1/2 rounded-full bg-white" />
                <div className="mt-3 h-1.5 w-3/5 rounded-full bg-white/60" />
                <div className="mt-1.5 h-1.5 w-2/5 rounded-full bg-white/45" />
                <div className="mt-4 h-5 w-20 rounded-full bg-white shadow-lg" />
            </div>
        </div>
    );
}
