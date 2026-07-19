export default function ServicesGridPreview() {
    return (
        <div className="h-40 bg-slate-900 rounded-xl border border-slate-800 flex items-center justify-center text-slate-600 font-bold overflow-hidden">
             <div className="grid grid-cols-3 gap-2 w-full px-4 scale-75">
                <div className="h-20 bg-slate-800 rounded"></div>
                <div className="h-20 bg-slate-800 rounded"></div>
                <div className="h-20 bg-slate-800 rounded"></div>
             </div>
        </div>
    );
}