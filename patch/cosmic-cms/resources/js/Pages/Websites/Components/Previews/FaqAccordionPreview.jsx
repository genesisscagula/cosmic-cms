export default function FaqAccordionPreview() {
    return <div className="h-full rounded-xl border border-slate-700 bg-slate-950 p-4"><div className="mb-4 h-2 w-16 rounded bg-violet-400" /><div className="mb-4 h-3 w-3/5 rounded bg-slate-100" /><div className="space-y-2">{[1, 2, 3].map((item) => <div key={item} className="flex items-center justify-between rounded-lg border border-slate-700 px-3 py-2"><span className="h-2 w-3/5 rounded bg-slate-500" /><span className="text-xs text-violet-300">+</span></div>)}</div></div>;
}
