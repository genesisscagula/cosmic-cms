import { useState } from "react";

const statusStyles = {
    Published: "border-emerald-400/20 bg-emerald-400/10 text-emerald-300",
    Draft: "border-amber-300/20 bg-amber-300/10 text-amber-200",
};

export default function WebsiteCard({ website, viewMode = "grid", onEdit, onDuplicate, onDelete, onDownloadConnector, onConnectLiveSite, onPushLiveUpdate, onTransferOwnership }) {
    const [menuOpen, setMenuOpen] = useState(false);
    const compact = viewMode === "list";

    return (
        <article className={`group relative rounded-2xl border border-white/10 bg-white/[0.035] p-4 transition duration-200 hover:-translate-y-0.5 hover:border-violet-400/35 hover:bg-white/[0.055] hover:shadow-xl hover:shadow-black/20 focus-within:border-violet-400/55 ${menuOpen ? "z-30" : "z-0"}`}>
            <div className={compact ? "flex flex-col gap-4 lg:flex-row lg:items-center" : ""}>
                <div className="flex min-w-0 flex-1 items-start gap-3">
                    {website.logoUrl ? <img src={website.logoUrl} alt="" className="h-10 w-10 shrink-0 rounded-xl object-cover" /> : <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br ${website.accent} text-base font-bold text-white shadow-lg shadow-black/20`}>{website.name.charAt(0)}</div>}
                    <div className="min-w-0 flex-1 pt-0.5">
                        <div className="flex flex-wrap items-center gap-2"><h2 className="truncate font-semibold tracking-tight text-white">{website.name}</h2><span className={`rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${statusStyles[website.status] || statusStyles.Draft}`}>{website.status}</span></div>
                        <p className="mt-1 truncate text-sm text-slate-400">{website.domain}</p>
                        <p className="mt-1 text-xs text-slate-500">{website.industry}</p>
                    </div>
                </div>

                <dl className={`${compact ? "grid flex-[1.25] grid-cols-2 gap-3 sm:grid-cols-4 lg:border-0 lg:pt-0" : "mt-4 grid grid-cols-2 gap-4 border-t border-white/10 pt-3 sm:grid-cols-4"} text-sm`}>
                    <div><dt className="text-[10px] font-medium uppercase tracking-[0.14em] text-slate-500">Theme</dt><dd className="mt-1 text-sm text-slate-300">{website.theme}</dd></div>
                    <div><dt className="text-[10px] font-medium uppercase tracking-[0.14em] text-slate-500">Pages</dt><dd className="mt-1 text-sm text-slate-300">{website.pagesCount}</dd></div>
                    <div><dt className="text-[10px] font-medium uppercase tracking-[0.14em] text-slate-500">Live status</dt><dd className="mt-1 text-sm text-slate-300">{website.deploymentStatus}</dd></div>
                    <div><dt className="text-[10px] font-medium uppercase tracking-[0.14em] text-slate-500">Last edited</dt><dd className="mt-1 text-sm text-slate-300">{website.lastEdited}</dd></div>
                </dl>

                <div className={`${compact ? "flex shrink-0 items-center gap-1" : "absolute right-4 top-4 flex shrink-0 items-center gap-1"}`}>
                    <button type="button" onClick={() => onEdit(website)} className="rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-slate-950 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-400">Open Builder</button>
                    <button type="button" aria-label={`More actions for ${website.name}`} aria-expanded={menuOpen} onClick={() => setMenuOpen((open) => !open)} className="flex h-8 w-8 items-center justify-center rounded-lg pr-[3px] leading-none tracking-[-0.3em] text-slate-400 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400">•••</button>
                </div>
            </div>

            {menuOpen && <div className="absolute right-4 top-14 z-40 w-48 rounded-xl border border-white/10 bg-[#1a1a1d] p-1 shadow-2xl shadow-black/40"><button type="button" onClick={() => { onDownloadConnector(website); setMenuOpen(false); }} className="w-full rounded-lg px-3 py-2 text-left text-xs font-medium text-slate-200 transition hover:bg-white/10">Download connector</button><button type="button" onClick={() => { onConnectLiveSite(website); setMenuOpen(false); }} className="w-full rounded-lg px-3 py-2 text-left text-xs font-medium text-slate-200 transition hover:bg-white/10">Connect live site</button><button type="button" onClick={() => { onPushLiveUpdate(website); setMenuOpen(false); }} className="w-full rounded-lg px-3 py-2 text-left text-xs font-medium text-emerald-200 transition hover:bg-emerald-400/10">Push live update</button><button type="button" onClick={() => { onDuplicate(website); setMenuOpen(false); }} className="w-full rounded-lg px-3 py-2 text-left text-xs font-medium text-slate-200 transition hover:bg-white/10">Duplicate</button>{website.canTransferOwnership && <button type="button" onClick={() => { onTransferOwnership(website); setMenuOpen(false); }} className="w-full rounded-lg px-3 py-2 text-left text-xs font-medium text-violet-200 transition hover:bg-violet-400/10">Transfer ownership</button>}<button type="button" onClick={() => { onDelete(website); setMenuOpen(false); }} className="w-full rounded-lg px-3 py-2 text-left text-xs font-medium text-red-300 transition hover:bg-red-400/10">Delete</button></div>}
        </article>
    );
}
