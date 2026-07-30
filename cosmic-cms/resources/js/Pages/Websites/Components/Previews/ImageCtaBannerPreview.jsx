export default function ImageCtaBannerPreview() {
    return (
        <div className="relative flex h-32 items-center justify-center overflow-hidden rounded-xl border border-slate-700 bg-slate-800 text-center">
            <div className="absolute inset-0 bg-[linear-gradient(115deg,#0f172a_0%,#334155_50%,#1e293b_100%)]" />
            <div className="absolute inset-0 bg-gradient-to-r from-slate-950/70 via-slate-950/35 to-slate-950/20" />
            <div className="relative w-2/3">
                <div className="mx-auto h-1.5 w-16 rounded bg-violet-300/80" />
                <div className="mx-auto mt-3 h-3 w-full rounded bg-white" />
                <div className="mx-auto mt-2 h-1.5 w-4/5 rounded bg-slate-300/70" />
                <div className="mt-4 flex justify-center gap-2">
                    <div className="h-5 w-16 rounded-full bg-white" />
                    <div className="h-5 w-16 rounded-full border border-white/50" />
                </div>
            </div>
        </div>
    );
}
