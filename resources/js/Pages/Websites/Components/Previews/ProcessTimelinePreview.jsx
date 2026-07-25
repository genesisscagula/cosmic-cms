export default function ProcessTimelinePreview() {
    return (
        <div className="h-40 overflow-hidden rounded-xl border border-slate-800 bg-slate-950 p-4">
            <div className="mx-auto mb-4 flex w-1/2 flex-col items-center gap-1.5">
                <div className="h-1.5 w-10 rounded bg-slate-600" />
                <div className="h-2 w-full rounded bg-slate-200" />
            </div>
            <div className="grid grid-cols-4 gap-2">
                {["01", "02", "03", "04"].map((step) => (
                    <div key={step} className="rounded-lg border border-slate-700 bg-slate-900 p-2">
                        <div className="text-sm font-bold leading-none text-slate-600">{step}</div>
                        <div className="mt-3 h-1.5 w-full rounded bg-slate-300" />
                        <div className="mt-2 h-1 w-4/5 rounded bg-slate-700" />
                        <div className="mt-1 h-1 w-2/3 rounded bg-slate-800" />
                    </div>
                ))}
            </div>
        </div>
    );
}
