export default function StatsModernPreview() {
    return (
        <div className="flex h-40 items-center overflow-hidden rounded-xl border border-slate-800 bg-slate-950 px-4">
            <div className="w-full">
                <div className="mb-3 h-2 w-16 rounded bg-slate-700" />
                <div className="mb-6 h-3 w-40 rounded bg-slate-500" />
                <div className="grid grid-cols-4 divide-x divide-slate-700 border-y border-slate-700">
                    {["15+", "250+", "98%", "24/7"].map((value) => (
                        <div key={value} className="px-2 py-3 first:pl-0 last:pr-0">
                            <div className="text-sm font-bold text-white">{value}</div>
                            <div className="mt-1 h-1.5 w-full rounded bg-slate-700" />
                            <div className="mt-1 h-1.5 w-3/4 rounded bg-slate-800" />
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
