import { useState } from "react";

const statusStyles = { Published: "border-emerald-400/20 bg-emerald-400/10 text-emerald-300", Draft: "border-amber-300/20 bg-amber-300/10 text-amber-200" };

export default function WebsiteCard({ website, onEdit, onDuplicate, onDelete, onDownloadConnector, onConnectLiveSite, onPushLiveUpdate }) {
    const [menuOpen, setMenuOpen] = useState(false);

    return (
        <article className={`group relative rounded-2xl border border-white/10 bg-white/[0.035] p-4 transition duration-200 hover:-translate-y-0.5 hover:border-violet-400/35 hover:bg-white/[0.055] hover:shadow-xl hover:shadow-black/20 focus-within:border-violet-400/55 ${menuOpen ? "z-30" : "z-0"}`}>
            <div className="flex items-start gap-3">
                <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br ${website.accent} text-base font-bold text-white shadow-lg shadow-black/20`}>{website.name.charAt(0)}</div>
                <div className="min-w-0 flex-1 pt-0.5"><div className="flex flex-wrap items-center gap-2"><h2 className="truncate font-semibold tracking-tight text-white">{website.name}</h2><span className={`rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${statusStyles[website.status]}`}>{website.status}</span></div><p className="mt-1 truncate text-sm text-slate-400">{website.domain}</p></div>
                <div className="flex shrink-0 items-center gap-1"><button type="button" onClick={() => onEdit(website)} className="rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-slate-950 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-400">Edit</button><button type="button" aria-label={`More actions for ${website.name}`} aria-expanded={menuOpen} onClick={() => setMenuOpen((open) => !open)} className="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 leading-none tracking-[-0.3em] transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400 pr-[3px]">•••</button></div>
            </div>

            <dl className="mt-4 grid grid-cols-2 gap-4 border-t border-white/10 pt-3 text-sm"><div><dt className="text-[10px] font-medium uppercase tracking-[0.14em] text-slate-500">Theme</dt><dd className="mt-1 text-sm text-slate-300">{website.theme}</dd></div><div><dt className="text-[10px] font-medium uppercase tracking-[0.14em] text-slate-500">Last edited</dt><dd className="mt-1 text-sm text-slate-300">{website.lastEdited}</dd></div></dl>

            {menuOpen && <div className="absolute right-4 top-14 z-40 w-48 rounded-xl border border-white/10 bg-[#1a1a1d] p-1 shadow-2xl shadow-black/40"><button type="button" onClick={() => { onDownloadConnector(website); setMenuOpen(false); }} className="w-full rounded-lg px-3 py-2 text-left text-xs font-medium text-slate-200 transition hover:bg-white/10 focus:bg-white/10 focus:outline-none">Download connector</button><button type="button" onClick={() => { onConnectLiveSite(website); setMenuOpen(false); }} className="w-full rounded-lg px-3 py-2 text-left text-xs font-medium text-slate-200 transition hover:bg-white/10 focus:bg-white/10 focus:outline-none">Connect live site</button><button type="button" onClick={() => { onPushLiveUpdate(website); setMenuOpen(false); }} className="w-full rounded-lg px-3 py-2 text-left text-xs font-medium text-emerald-200 transition hover:bg-emerald-400/10 focus:bg-emerald-400/10 focus:outline-none">Push live update</button><button type="button" onClick={() => { onDuplicate(website); setMenuOpen(false); }} className="w-full rounded-lg px-3 py-2 text-left text-xs font-medium text-slate-200 transition hover:bg-white/10 focus:bg-white/10 focus:outline-none">Duplicate</button><button type="button" onClick={() => { onDelete(website); setMenuOpen(false); }} className="w-full rounded-lg px-3 py-2 text-left text-xs font-medium text-red-300 transition hover:bg-red-400/10 focus:bg-red-400/10 focus:outline-none">Delete</button></div>}
        </article>
    );
}
