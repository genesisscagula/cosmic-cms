export default function TestimonialsCarouselPreview() {
    return (
        <div className="h-40 bg-slate-900 rounded-xl border border-slate-800 flex items-center justify-center overflow-hidden">

            <div className="w-full px-5 scale-90">

                <div className="bg-slate-800 rounded-xl p-4">

                    {/* Stars */}

                    <div className="flex gap-1 mb-3 text-yellow-400 text-[10px]">
                        ★★★★★
                    </div>

                    {/* Quote */}

                    <div className="space-y-2 mb-5">

                        <div className="h-2 bg-slate-700 rounded w-full"></div>

                        <div className="h-2 bg-slate-700 rounded w-5/6"></div>

                        <div className="h-2 bg-slate-700 rounded w-4/6"></div>

                    </div>

                    {/* Author */}

                    <div className="flex items-center gap-3">

                        <div className="w-8 h-8 rounded-full bg-slate-600"></div>

                        <div className="flex-1">

                            <div className="h-2 bg-slate-600 rounded w-24 mb-2"></div>

                            <div className="h-2 bg-slate-700 rounded w-16"></div>

                        </div>

                    </div>

                </div>

            </div>

        </div>
    );
}