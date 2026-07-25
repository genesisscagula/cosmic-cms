export default function TestimonialsCarouselPreview() {
    return (
        <div className="h-40 overflow-hidden rounded-xl border border-slate-800 bg-slate-950 p-4">
            <div className="mx-auto mb-4 flex w-1/2 flex-col items-center gap-1.5">
                <div className="h-1.5 w-12 rounded bg-slate-600" />
                <div className="h-2 w-full rounded bg-slate-100" />
            </div>
            <div className="grid grid-cols-3 gap-2">
                {[1, 2, 3].map((card) => (
                    <div key={card} className="rounded-lg bg-slate-800 p-2.5">
                        <div className="text-[8px] tracking-[0.08em] text-amber-300">★★★★★</div>
                        <div className="mt-3 h-1.5 w-full rounded bg-slate-600" />
                        <div className="mt-1.5 h-1.5 w-4/5 rounded bg-slate-600" />
                        <div className="mt-4 flex items-center gap-1.5">
                            <div className="h-4 w-4 rounded-full bg-slate-500" />
                            <div className="h-1.5 w-8 rounded bg-slate-500" />
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
