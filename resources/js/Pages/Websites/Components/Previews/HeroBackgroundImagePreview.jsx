export default function HeroBackgroundImagePreview() {
    return (
        <div className="relative h-40 rounded-xl overflow-hidden border border-slate-700 bg-slate-800">

            {/* Background */}
            <div className="absolute inset-0 bg-slate-700"></div>

            {/* Overlay */}
            <div className="absolute inset-0 bg-black/50"></div>

            {/* Content */}
            <div className="relative z-10 h-full flex flex-col items-center justify-center px-4 text-center">

                <div className="h-2 w-16 bg-slate-400 rounded-full mb-2"></div>

                <div className="h-3 w-32 bg-white rounded-full mb-2"></div>

                <div className="h-2 w-24 bg-slate-300 rounded-full mb-4"></div>

                <div className="h-6 w-20 rounded-full bg-emerald-500"></div>

            </div>

        </div>
    );
}