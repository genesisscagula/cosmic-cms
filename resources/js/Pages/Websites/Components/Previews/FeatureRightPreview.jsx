export default function FeatureRightPreview() {
    return (
        <div className="h-40 bg-slate-900 rounded-xl border border-slate-800 flex overflow-hidden">
            {/* Text Lines (Now on the Left) */}
            <div className="w-1/2 p-3 space-y-2 flex flex-col justify-center border-r border-slate-700">
                <div className="h-2 w-16 bg-slate-700 rounded-full"></div>
                <div className="h-3 w-full bg-slate-600 rounded-full"></div>
                <div className="h-2 w-3/4 bg-slate-700 rounded-full"></div>
            </div>
            {/* Image Box (Now on the Right) */}
            <div className="w-1/2 h-full bg-slate-800 flex items-center justify-center">
                <span className="text-slate-600 text-[10px] uppercase font-bold">Image</span>
            </div>
        </div>
    );
}