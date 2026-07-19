export default function HeroCenteredPreview() {
    return (
        <div className="h-40 bg-slate-900 rounded-xl border border-slate-800 flex items-center justify-center text-slate-600 font-bold overflow-hidden relative">
             <div className="text-center scale-75">
                <div className="text-emerald-500 text-xs font-bold uppercase mb-2">TAGLINE</div>
                <div className="text-xl font-black text-white mb-2">Main Headline</div>
                <div className="w-16 h-8 bg-emerald-600 rounded-full mx-auto"></div>
             </div>
        </div>
    );
}