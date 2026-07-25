export default function ServicesBentoPreview() {
    return (
        <div className="h-40 overflow-hidden rounded-xl border border-slate-800 bg-slate-950 p-4">
            <div className="mb-3">
                <div className="h-1.5 w-12 rounded bg-slate-600" />
                <div className="mt-2 h-3 w-2/3 rounded bg-slate-200" />
            </div>
            <div className="space-y-2">
                {[1, 2, 3].map((row) => (
                    <div key={row} className="flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-900 px-2.5 py-2">
                        <div className="h-6 w-6 shrink-0 rounded-lg border border-slate-600 bg-slate-800" />
                        <div className="flex-1">
                            <div className="h-1.5 w-20 rounded bg-slate-300" />
                            <div className="mt-1 h-1 w-28 rounded bg-slate-700" />
                        </div>
                        <div className="h-1.5 w-8 rounded bg-slate-500" />
                    </div>
                ))}
            </div>
        </div>
    );
}
