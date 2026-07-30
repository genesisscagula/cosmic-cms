export default function CaseStudiesGridPreview() {
    return (
        <div className="h-full rounded-xl border border-slate-700 bg-slate-950 p-3">
            <div className="mb-3 h-2 w-16 rounded bg-violet-400" />
            <div className="mb-4 h-3 w-3/5 rounded bg-slate-100" />
            <div className="grid grid-cols-2 gap-2">
                <div className="col-span-2 h-10 rounded-lg bg-slate-700" />
                <div className="h-8 rounded-lg bg-slate-800" />
                <div className="h-8 rounded-lg bg-slate-800" />
            </div>
        </div>
    );
}
