export default function HeroHeadlinePreview() {
    return (
        <div className="flex h-40 overflow-hidden rounded-xl border border-slate-800 bg-slate-950 p-5">
            <div className="flex max-w-[78%] flex-col justify-center">
                <div className="mb-2 h-1.5 w-16 rounded-full bg-violet-400/70" />
                <div className="h-3 w-full rounded bg-slate-200" />
                <div className="mt-2 h-3 w-3/4 rounded bg-slate-200" />
                <div className="mt-3 h-1.5 w-11/12 rounded bg-slate-600" />
                <div className="mt-1.5 h-1.5 w-2/3 rounded bg-slate-700" />
                <div className="mt-4 flex gap-2">
                    <div className="h-5 w-14 rounded-full bg-violet-500" />
                    <div className="h-5 w-14 rounded-full border border-slate-600" />
                </div>
            </div>
        </div>
    );
}
