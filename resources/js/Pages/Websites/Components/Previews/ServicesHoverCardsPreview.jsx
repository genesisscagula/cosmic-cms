export default function ServicesHoverCardsPreview() {
    return <div className="h-full w-full bg-slate-950 p-3 text-white">
        <div className="text-[5px] font-bold uppercase tracking-[.22em] text-emerald-300">Explore our capabilities</div>
        <div className="mt-1 text-[10px] font-semibold leading-tight">Services that work better together</div>
        <div className="mt-3 grid grid-cols-3 gap-1">
            {[1,2,3,4,5,6].map((item) => <div key={item} className={`h-10 rounded-md border p-1.5 ${item === 2 ? "border-emerald-300 bg-emerald-300 text-slate-950" : "border-white/15 bg-white/5"}`}>
                <div className="text-[4px] font-bold">0{item}</div><div className="mt-2 h-1 w-2/3 rounded bg-current opacity-50"/><div className="mt-1 h-1 rounded bg-current opacity-20"/>
            </div>)}
        </div>
    </div>;
}
