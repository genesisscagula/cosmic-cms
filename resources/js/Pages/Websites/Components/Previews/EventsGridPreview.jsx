export default function EventsGridPreview() {
    return (
        <div className="h-full rounded-xl border border-slate-700 bg-slate-950 p-3">
            <div className="mb-3 h-2 w-16 rounded bg-violet-400" />
            <div className="mb-4 h-3 w-3/5 rounded bg-slate-100" />
            <div className="grid grid-cols-3 gap-2">
                {[1, 2, 3].map((item) => (
                    <div key={item} className="rounded-lg border border-slate-700 p-2">
                        <div className="mb-2 h-5 w-5 rounded bg-violet-400/70" />
                        <div className="mb-1 h-2 rounded bg-slate-200" />
                        <div className="h-1.5 w-3/4 rounded bg-slate-600" />
                    </div>
                ))}
            </div>
        </div>
    );
}
