export default function TeamModernPreview() {
    return (
        <div className="h-40 overflow-hidden rounded-xl border border-slate-800 bg-slate-950 p-4">
            <div className="mb-4 h-2 w-16 rounded bg-violet-400/80" />
            <div className="mb-5 h-3 w-40 rounded bg-slate-200" />
            <div className="grid grid-cols-4 gap-2">
                {[1, 2, 3, 4].map((member) => (
                    <div key={member} className="overflow-hidden rounded-lg border border-slate-800 bg-slate-900">
                        <div className="aspect-[4/3] bg-slate-700" />
                        <div className="space-y-1.5 p-2">
                            <div className="h-1.5 w-4/5 rounded bg-slate-300" />
                            <div className="h-1.5 w-3/5 rounded bg-slate-600" />
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
