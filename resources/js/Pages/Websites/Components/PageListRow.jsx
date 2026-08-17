import { Link } from "@inertiajs/react";

const formatLastEdited = (value) => { if (!value) return "Not yet edited"; const date = new Date(value); return Number.isNaN(date.getTime()) ? "Recently updated" : `Edited ${date.toLocaleDateString(undefined, { month: "short", day: "numeric" })}`; };

const IconButton = ({ label, onClick, children, tone = '' }) => (
    <button type="button" onClick={onClick} title={label} aria-label={label} className={`cosmic-page-icon-action ${tone} inline-flex h-8 w-8 items-center justify-center rounded-lg transition focus:outline-none focus:ring-2 focus:ring-emerald-400/40`}>
        {children}
    </button>
);

export default function PageListRow({ page, depth = 0, onDelete, onAddChild, onEditTitle, onClone }) {
    const status = page.status === "published" || page.status === "Published" ? "Published" : "Draft";
    const pageType = page.page_type === "blog" ? "Posts & updates" : "Standard page";
    const childAction = depth === 0 ? 'Add child page' : 'Add nested page';
    return (
        <article className="cosmic-page-row group flex flex-col gap-3 px-4 py-3.5 transition sm:flex-row sm:items-center">
            <div className={`cosmic-page-row__copy min-w-0 flex-1 ${depth ? 'border-l border-violet-400/35 pl-3' : ''}`} style={depth ? { marginLeft: `${Math.min(depth, 2) * 20}px` } : undefined}>
                <div className="flex flex-wrap items-center gap-2">
                    <span className="cosmic-page-type-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M6 3.75h8.5L18 7.25v13H6z"/><path d="M14.5 3.75v3.5H18"/><path d="M9 12h6M9 15.5h4.5"/></svg></span>
                    <h3 className="cosmic-page-title truncate text-sm font-semibold">{page.title || "Untitled Page"}</h3>
                    <span className={`rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${status === "Published" ? "bg-emerald-400/10 text-emerald-300" : "bg-amber-300/10 text-amber-200"}`}>{status}</span>
                    <span className="rounded-full bg-violet-400/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-violet-200">{pageType}</span>
                    {depth > 0 && <span className="text-[10px] font-medium uppercase tracking-wide text-slate-500">Level {depth + 1}</span>}
                </div>
                <div className="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-500"><span>/{page.slug || "untitled"}</span><span>{formatLastEdited(page.updated_at)}</span></div>
            </div>
            <div className="flex shrink-0 flex-wrap items-center gap-1.5">
                {depth < 2 && <button type="button" onClick={() => onAddChild(page)} className="cosmic-page-action cosmic-page-action--child inline-flex h-8 items-center justify-center rounded-lg px-2.5 text-xs font-semibold transition focus:outline-none focus:ring-2 focus:ring-violet-400">{childAction}</button>}
                <div className="cosmic-page-utility-actions flex items-center gap-1">
                    <IconButton label={`Edit title for ${page.title || 'page'}`} onClick={() => onEditTitle(page)}>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" className="h-4 w-4"><path d="M4 20h4l10.5-10.5a2.8 2.8 0 0 0-4-4L4 16v4Z"/><path d="m13 7 4 4"/></svg>
                    </IconButton>
                    <IconButton label={`Clone ${page.title || 'page'}`} onClick={() => onClone(page)}>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" className="h-4 w-4"><rect x="8" y="8" width="11" height="11" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/></svg>
                    </IconButton>
                </div>
                <button type="button" onClick={() => onDelete(page)} className="cosmic-page-action cosmic-page-action--delete inline-flex h-8 items-center justify-center rounded-lg px-2.5 text-xs font-semibold transition focus:outline-none focus:ring-2 focus:ring-rose-400">Delete</button>
                <Link href={route("pages.builder", page.id)} className="cosmic-page-action cosmic-page-action--builder inline-flex h-8 items-center justify-center rounded-lg px-3 text-xs font-semibold transition focus:outline-none focus:ring-2 focus:ring-violet-400">Open Builder</Link>
            </div>
        </article>
    );
}
