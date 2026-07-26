import { Link } from "@inertiajs/react";

export default function WebsiteWorkspaceHeader({ website, pageCount, inquiryCount = 0, themeSummary, onNewPage, onPushLive, pushingLive, onOpenInquiries, onOpenSettings }) {
    return (
        <header className="flex flex-col gap-4 lg:grid lg:grid-cols-[1fr_auto_1fr] lg:items-end">
            <div>
                <Link href={route("dashboard")} className="text-xs font-semibold text-violet-300 transition hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400">
                    &larr; Back to Websites
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

            <div className="flex items-center gap-3 self-start text-sm lg:justify-self-center lg:self-auto">
                <button
                    type="button"
                    onClick={onOpenInquiries}
                    className="font-medium text-slate-300 transition hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400 focus:ring-offset-2 focus:ring-offset-[#0a0a0b]"
                >
                    Inquiries {inquiryCount > 0 ? (
                        <span className="ml-1 inline-flex items-center gap-1 text-emerald-300">
                            <span aria-hidden="true" className="h-1.5 w-1.5 rounded-full bg-emerald-400" />
                            {inquiryCount}
                        </span>
                    ) : null}
                </button>
                <span aria-hidden="true" className="text-slate-700">|</span>
                <button type="button" onClick={onOpenSettings} className="font-medium text-slate-500 transition hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400 focus:ring-offset-2 focus:ring-offset-[#0a0a0b]">Settings</button>
            </div>

            <div className="flex flex-wrap gap-2 lg:justify-self-end">
                <button type="button" onClick={onPushLive} disabled={pushingLive} className="inline-flex h-10 items-center justify-center rounded-xl border border-emerald-400/30 bg-emerald-400/10 px-4 text-sm font-semibold text-emerald-100 transition hover:bg-emerald-400/20 focus:outline-none focus:ring-2 focus:ring-emerald-300 disabled:cursor-not-allowed disabled:opacity-50">
                    {pushingLive ? "Pushing live..." : "Push live update"}
                </button>
                <button type="button" onClick={onNewPage} className="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-white px-4 text-sm font-semibold text-slate-950 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-400">
                    <span aria-hidden="true">+</span>New Page
                </button>
            </div>
        </header>
    );
}
