export default function FeatureRightPreview() {
    return (
        <div className="flex h-40 overflow-hidden rounded-xl border border-slate-800 bg-slate-950 p-3">
            <div className="flex w-1/2 flex-col justify-center px-4">
                <div className="h-1.5 w-10 rounded bg-violet-400/70" />
                <div className="mt-3 h-3 w-full rounded bg-slate-200" />
                <div className="mt-1.5 h-3 w-4/5 rounded bg-slate-200" />
                <div className="mt-3 h-1.5 w-full rounded bg-slate-600" />
                <div className="mt-1.5 h-1.5 w-2/3 rounded bg-slate-700" />
                <div className="mt-4 h-1.5 w-12 rounded bg-slate-300" />
            </div>
            <div className="w-1/2 rounded-lg bg-gradient-to-br from-slate-700 via-slate-800 to-slate-900" />
        </div>
    );
}
