export default function HeroHeadlinePreview() {
    return (
        <div className="h-40 bg-slate-900 rounded-xl border border-slate-800 flex items-center justify-center overflow-hidden relative">
            <div className="text-center scale-[0.6]">
                <div className="text-rose-500 text-xs font-bold uppercase mb-2">
                    FUTURE
                </div>

                <div className="text-2xl font-black text-white mb-2">
                    Digital Reality
                </div>

                <div className="w-20 h-2 bg-gradient-to-r from-rose-500 to-indigo-500 rounded-full mx-auto"></div>
            </div>
        </div>
    );
}