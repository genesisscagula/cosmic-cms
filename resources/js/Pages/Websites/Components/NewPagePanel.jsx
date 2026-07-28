export default function NewPagePanel({ open, onClose, data, setData, errors, processing, onSubmit, parentPage = null }) {
    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-end bg-black/70 p-4 backdrop-blur-sm sm:items-center sm:justify-center" role="dialog" aria-modal="true" aria-labelledby="new-page-title">
            <div className="w-full max-w-md rounded-2xl border border-white/10 bg-[#18181b] p-5 shadow-2xl shadow-black/40">
                <div className="flex items-start justify-between gap-4">
                    <div><h2 id="new-page-title" className="text-lg font-semibold text-white">{parentPage ? 'Create a child page' : 'Create a page'}</h2><p className="mt-1 text-sm text-slate-400">{parentPage ? <>This page will appear under <span className="font-medium text-slate-200">{parentPage.title}</span>.</> : 'Choose a standard page or a blog hub for updates and articles.'}</p></div>
                    <button type="button" onClick={onClose} className="rounded-lg p-1 text-slate-400 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400" aria-label="Close new page panel">×</button>
                </div>
                <form onSubmit={onSubmit} className="mt-5 space-y-4">
                    <div><label htmlFor="page-title" className="text-sm font-medium text-slate-200">Page title</label><input id="page-title" autoFocus required value={data.title} onChange={(event) => setData("title", event.target.value)} placeholder="e.g. About Us or Journal" className="mt-2 h-10 w-full rounded-xl border border-white/10 bg-white/[0.04] px-3 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-violet-400/70 focus:ring-2 focus:ring-violet-400/15" />{errors.title && <p className="mt-2 text-xs text-red-300">{errors.title}</p>}</div>
                    <fieldset><legend className="text-sm font-medium text-slate-200">Page purpose</legend><div className="mt-2 grid gap-2">
                        <label className={`cursor-pointer rounded-xl border p-3 transition ${data.page_type !== 'blog' ? 'border-violet-400/60 bg-violet-400/10' : 'border-white/10 hover:bg-white/[0.03]'}`}><input className="sr-only" type="radio" value="standard" checked={data.page_type !== 'blog'} onChange={() => setData('page_type', 'standard')} /><span className="block text-sm font-semibold text-white">Standard page</span><span className="mt-1 block text-xs text-slate-400">Build a service, about, landing, or contact page.</span></label>
                        <label className={`cursor-pointer rounded-xl border p-3 transition ${data.page_type === 'blog' ? 'border-violet-400/60 bg-violet-400/10' : 'border-white/10 hover:bg-white/[0.03]'}`}><input className="sr-only" type="radio" value="blog" checked={data.page_type === 'blog'} onChange={() => setData('page_type', 'blog')} /><span className="block text-sm font-semibold text-white">Posts / updates</span><span className="mt-1 block text-xs text-slate-400">Start with a featured article and four editable starter cards.</span></label>
                    </div></fieldset>
                    <div className="flex justify-end gap-2"><button type="button" onClick={onClose} className="rounded-lg px-3 py-2 text-sm font-medium text-slate-400 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400">Cancel</button><button type="submit" disabled={processing} className="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-slate-200 disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-violet-400">{processing ? "Creating..." : "Create Page"}</button></div>
                </form>
            </div>
        </div>
    );
}
