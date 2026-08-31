import { Link } from "@inertiajs/react";
import CreditBalanceBadge from "../../../Components/CosmicCredits/CreditBalanceBadge";

export default function WebsiteWorkspaceHeader({ website, pageCount, inquiryCount = 0, themeSummary, onNewPage, onLiveAction, liveConnected = false, pushingLive, checkingLive = false, onOpenInquiries, onOpenProfile, onOpenSettings, creditBalance }) {
    return (
        <header id="cosmic-workspace-header" className="cosmic-workspace-header flex flex-col gap-5 lg:grid lg:grid-cols-[minmax(260px,1fr)_auto_auto] lg:items-end">
            <div>
                <Link href={route("dashboard")} className="cosmic-workspace-back text-xs font-semibold transition focus:outline-none focus:ring-2 focus:ring-violet-400">
                    &larr; Back to Websites
                </Link>
                <div className="mt-3 flex flex-wrap items-center gap-2">
                    <h1 className="cosmic-workspace-title text-3xl font-black tracking-tight sm:text-4xl">{website.name || "Untitled Website"}</h1>
                    <span className="cosmic-workspace-pagecount rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide">
                        {pageCount} {pageCount === 1 ? "page" : "pages"}
                    </span>
                </div>
                <p className="cosmic-workspace-meta mt-2 text-sm">
                    {website.domain || "No domain connected"}{themeSummary ? ` · ${themeSummary} theme` : ""}
                </p>
            </div>

            <div className="cosmic-workspace-nav flex flex-wrap items-center gap-2 self-start text-sm lg:justify-self-center lg:self-auto">
                <button
                    type="button"
                    onClick={onOpenInquiries}
                    className="cosmic-workspace-navlink font-medium transition focus:outline-none focus:ring-2 focus:ring-violet-400"
                >
                    Inquiries {inquiryCount > 0 ? (
                        <span className="ml-1 inline-flex items-center gap-1 text-emerald-300">
                            <span aria-hidden="true" className="h-1.5 w-1.5 rounded-full bg-emerald-400" />
                            {inquiryCount}
                        </span>
                    ) : null}
                </button>
                <span aria-hidden="true" className="text-slate-700">|</span>
                <Link href={route("sparks.index")} className="cosmic-workspace-navlink is-accent font-medium transition focus:outline-none focus:ring-2 focus:ring-violet-400">Sparks</Link>
                <span aria-hidden="true" className="text-slate-700">|</span>
                <button type="button" onClick={onOpenProfile} className="cosmic-workspace-navlink font-medium transition focus:outline-none focus:ring-2 focus:ring-violet-400">Profile</button>
                <span aria-hidden="true" className="text-slate-700">|</span>
                <button type="button" onClick={onOpenSettings} className="cosmic-workspace-navlink font-medium transition focus:outline-none focus:ring-2 focus:ring-violet-400">Settings</button>
            </div>

            <div className="flex flex-nowrap items-center gap-2 lg:justify-self-end">
                <CreditBalanceBadge balance={creditBalance} className="h-10" />
                <Link method="post" as="button" href={route("logout")} className="cosmic-workspace-secondary inline-flex h-10 items-center justify-center rounded-xl px-3 text-sm font-semibold transition">Log out</Link>
                <button type="button" onClick={onLiveAction} disabled={pushingLive || checkingLive} className="cosmic-workspace-live inline-flex h-10 items-center justify-center rounded-xl px-4 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-emerald-300 disabled:cursor-not-allowed disabled:opacity-50">
                    {checkingLive ? "Checking..." : pushingLive ? "Pushing live..." : liveConnected ? "Push to live" : "Connect to live"}
                </button>
                <button type="button" onClick={onNewPage} id="cosmic-new-page-button" className="cosmic-new-page-button inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-xl px-4 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-violet-400">
                    <span aria-hidden="true">+</span><span className="whitespace-nowrap">New Page</span>
                </button>
            </div>
        </header>
    );
}
