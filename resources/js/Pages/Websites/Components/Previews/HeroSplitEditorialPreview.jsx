export default function HeroSplitEditorialPreview() {
    return (
        <div className="h-full w-full overflow-hidden bg-[#f6f6f2] p-3 text-slate-900">
            <div className="flex items-center justify-between border-b border-slate-300 pb-1 text-[5px] font-bold uppercase tracking-[0.22em] text-slate-500">
                <span>A new standard</span><span>01</span>
            </div>
            <div className="mt-3 grid grid-cols-[1.08fr_0.92fr] items-end gap-3">
                <div>
                    <div className="text-[15px] font-semibold leading-[0.94] tracking-[-0.055em]">Designed to make the right first impression.</div>
                    <div className="mt-2 h-1.5 w-4/5 rounded bg-slate-300" />
                    <div className="mt-1 h-1.5 w-3/5 rounded bg-slate-200" />
                    <div className="mt-3 flex gap-1"><span className="rounded-full bg-emerald-600 px-2 py-1 text-[5px] font-bold text-white">Start a conversation</span><span className="rounded-full border border-slate-300 px-2 py-1 text-[5px] font-bold">Explore work</span></div>
                </div>
                <div className="relative aspect-[4/5] overflow-hidden rounded-xl bg-gradient-to-br from-slate-300 via-slate-200 to-emerald-100 shadow-lg">
                    <div className="absolute bottom-2 left-2 right-2 text-[5px] font-semibold text-white">Built with clarity and care.</div>
                </div>
            </div>
        </div>
    );
}
