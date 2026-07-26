import { Link } from "@inertiajs/react";

export default function WebsiteWorkspaceHeader({ website, pageCount, inquiryCount = 0, themeSummary, onNewPage, onPushLive, pushingLive }) {
    return (
        <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <Link href={route("dashboard")} className="text-xs font-semibold text-violet-300 transition hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400">
                    ← Back to Websites
                </Link>
                <div className="mt-3 flex flex-wrap items-center gap-2">
                    <h1 className="text-3xl font-semibold tracking-tight text-white sm:text-4xl">{website.name || "Untitled Website"}</h1>
                    <span className="rounded-full border border-white/10 bg-white/[0.04] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                        {pageCount} {pageCount === 1 ? "page" : "pages"}
                    </span>
                </div>
                <p className="mt-2 text-sm text-slate-400">
                    {website.domain || "No domain connected"}{themeSummary ? ` · ${themeSummary} theme` : ""}
                </p>
            </div>
            <div className="flex flex-wrap gap-2">
                <Link href={route("websites.inquiries.index", website.id)} className="inline-flex h-10 items-center justify-center rounded-xl border border-white/10 bg-white/[0.04] px-4 text-sm font-semibold text-slate-200 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400">
                    Inquiries{inquiryCount ? ` (${inquiryCount})` : ""}
                </Link>
                <button type="button" onClick={onPushLive} disabled={pushingLive} className="inline-flex h-10 items-center justify-center rounded-xl border border-emerald-400/30 bg-emerald-400/10 px-4 text-sm font-semibold text-emerald-100 transition hover:bg-emerald-400/20 focus:outline-none focus:ring-2 focus:ring-emerald-300 disabled:cursor-not-allowed disabled:opacity-50">
                    {pushingLive ? "Pushing live…" : "Push live update"}
                </button>
                <button type="button" onClick={onNewPage} className="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-white px-4 text-sm font-semibold text-slate-950 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-400">
                    <span aria-hidden="true">+</span>New Page
                </button>
            </div>
        </header>
    );
}
