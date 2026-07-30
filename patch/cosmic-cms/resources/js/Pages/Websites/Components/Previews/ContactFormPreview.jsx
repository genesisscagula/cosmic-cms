export default function ContactFormPreview() {
    return (
        <div className="flex h-40 gap-4 overflow-hidden rounded-xl border border-slate-800 bg-slate-950 p-4">
            <div className="flex w-[42%] flex-col justify-center"><div className="h-2 w-16 rounded bg-violet-400/80" /><div className="mt-3 h-4 w-full rounded bg-slate-100" /><div className="mt-2 h-4 w-4/5 rounded bg-slate-100" /><div className="mt-3 h-2 w-full rounded bg-slate-600" /><div className="mt-1 h-2 w-3/4 rounded bg-slate-600" /></div>
            <div className="flex flex-1 flex-col gap-2 rounded-lg border border-slate-700 bg-slate-900 p-3"><div className="grid grid-cols-2 gap-2"><div className="h-5 rounded bg-slate-700" /><div className="h-5 rounded bg-slate-700" /></div><div className="h-5 rounded bg-slate-700" /><div className="h-9 rounded bg-slate-700" /><div className="mt-auto h-6 rounded bg-violet-500" /></div>
        </div>
    );
}
