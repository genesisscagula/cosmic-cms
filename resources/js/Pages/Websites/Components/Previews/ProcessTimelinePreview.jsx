export default function ProcessTimelinePreview() {
    return (
        <div className="h-40 bg-slate-900 rounded-xl border border-slate-800 flex items-center justify-center overflow-hidden">
            <div className="w-full px-4 scale-75">

                <div className="w-32 h-2 bg-slate-700 rounded mx-auto mb-3"></div>
                <div className="w-48 h-3 bg-slate-600 rounded mx-auto mb-8"></div>

                <div className="grid grid-cols-4 gap-3">

                    {[1, 2, 3, 4].map((step) => (
                        <div
                            key={step}
                            className="relative flex flex-col items-center"
                        >
                            <div className="w-10 h-10 rounded-full bg-cyan-600 flex items-center justify-center text-[10px] font-bold text-white">
                                {`0${step}`}
                            </div>

                            <div className="w-10 h-2 bg-slate-700 rounded mt-3"></div>
                            <div className="w-14 h-2 bg-slate-800 rounded mt-2"></div>
                        </div>
                    ))}

                </div>

            </div>
        </div>
    );
}