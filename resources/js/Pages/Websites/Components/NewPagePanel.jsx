import CreditBalanceBadge from '../../../Components/CosmicCredits/CreditBalanceBadge';

export default function NewPagePanel({ open, onClose, data, setData, errors, processing, onSubmit, parentPage = null, creditBalance = null }) {
    if (!open) return null;

    return (
        <div
            id="cosmic-new-page-overlay" className="cosmic-new-page-overlay fixed inset-0 isolate z-[100] flex items-end p-4 sm:items-center sm:justify-center"
            style={{
                backgroundColor: 'rgba(15, 23, 42, 0.42)',
                backdropFilter: 'blur(8px)',
                WebkitBackdropFilter: 'blur(8px)',
            }}
            role="dialog"
            aria-modal="true"
            aria-labelledby="new-page-title"
        >
            <div id="cosmic-new-page-dialog" className="cosmic-new-page-dialog relative z-10 w-full max-w-md rounded-2xl border p-5 shadow-2xl">
                <div className="flex items-start justify-between gap-4">
                    <div><h2 id="new-page-title" className="text-lg font-semibold text-white">{parentPage ? 'Create a child page' : 'Create a page'}</h2><p className="mt-1 text-sm text-slate-400">{parentPage ? <>This page will appear under <span className="font-medium text-slate-200">{parentPage.title}</span>.</> : 'Create a new standard page for this website.'}</p></div>
                    <div className="flex items-center gap-2">
                        {creditBalance !== null && <CreditBalanceBadge balance={creditBalance} />}
                        <button type="button" onClick={onClose} className="rounded-lg p-1 text-slate-400 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400" aria-label="Close new page panel">×</button>
                    </div>
                </div>
                <form onSubmit={onSubmit} className="mt-5 space-y-4">
                    <div><label htmlFor="page-title" className="text-sm font-medium text-slate-200">Page title</label><input id="page-title" autoFocus required value={data.title} onChange={(event) => setData("title", event.target.value)} placeholder="e.g. About Us or Services" className="mt-2 h-10 w-full rounded-xl border border-white/10 bg-white/[0.04] px-3 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-violet-400/70 focus:ring-2 focus:ring-violet-400/15" />{errors.title && <p className="mt-2 text-xs text-red-300">{errors.title}</p>}</div>
                    <div className="flex justify-end gap-2"><button type="button" onClick={onClose} className="rounded-lg px-3 py-2 text-sm font-medium text-slate-400 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400">Cancel</button><button type="submit" disabled={processing} className="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-slate-200 disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-violet-400">{processing ? "Creating..." : "Create Page"}</button></div>
                </form>
            </div>
        </div>
    );
}
