export default function HeroCenteredPreview() {
    return (
        <div className="relative flex h-40 items-center justify-center overflow-hidden rounded-xl border border-slate-800 bg-slate-950 px-6 text-center">
            <div className="absolute -left-8 -top-8 h-24 w-24 rounded-full bg-violet-500/15 blur-2xl" />
            <div className="absolute -bottom-8 -right-8 h-24 w-24 rounded-full bg-sky-500/15 blur-2xl" />
            <div className="relative flex w-44 flex-col items-center">
                <div className="mb-2 h-1.5 w-14 rounded-full bg-slate-500" />
                <div className="h-3 w-full rounded bg-slate-100" />
                <div className="mt-2 h-3 w-3/4 rounded bg-slate-100" />
                <div className="mt-3 h-1.5 w-4/5 rounded bg-slate-600" />
                <div className="mt-4 h-5 w-16 rounded-full border border-slate-500 bg-slate-900" />
            </div>
        </div>
    );
}
