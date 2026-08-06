export default function ServicesFeatureComparisonPreview() {
    return <div className="h-full w-full bg-slate-950 p-3 text-white">
        <div className="text-[5px] font-bold uppercase tracking-[.22em] text-emerald-300">Compare the approach</div>
        <div className="mt-1 text-[10px] font-semibold leading-tight">Capability, depth, and support</div>
        <div className="mt-3 grid grid-cols-3 gap-1">
            {["Foundation", "Growth", "Partner"].map((label, index) => <div key={label} className={`rounded-md border p-1.5 ${index === 1 ? "border-emerald-300 bg-emerald-300 text-slate-950" : "border-white/15 bg-white/5"}`}>
                <div className="text-[5px] font-bold">{label}</div>
                <div className="mt-1 space-y-1">{[1,2,3,4].map((row) => <div key={row} className={`h-1 rounded ${index === 1 ? "bg-slate-900/25" : "bg-white/15"}`} />)}</div>
            </div>)}
        </div>
    </div>;
}
