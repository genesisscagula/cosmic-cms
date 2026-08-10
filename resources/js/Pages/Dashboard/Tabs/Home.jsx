import { router } from "@inertiajs/react";
import ActivityFeed from "../Components/ActivityFeed";
import OverviewStatCard from "../Components/OverviewStatCard";
import RecentWebsiteList from "../Components/RecentWebsiteList";
import WorkspaceProgress from "../Components/WorkspaceProgress";
import WorkspaceInformation from "../Components/WorkspaceInformation";

export default function Home({ dashboard = {}, onTabChange }) {
    const stats = Array.isArray(dashboard.stats) ? dashboard.stats : [];
    const recentWebsites = Array.isArray(dashboard.recent_websites) ? dashboard.recent_websites : [];
    const activity = Array.isArray(dashboard.recent_activity) ? dashboard.recent_activity : [];
    const progress = dashboard.workspace_progress || { completed: 0, total: 4 };
    const workspace = dashboard.workspace || {};

    const openWebsite = (website) => router.visit(route("pages.index", website.id));

    return (
        <section className="space-y-7">
            <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p className="text-sm font-medium text-violet-300">Cosmic workspace</p>
                    <h1 className="mt-2 text-3xl font-semibold tracking-tight text-white sm:text-4xl">Overview</h1>
                    <p className="mt-2 text-sm text-slate-400">Your websites, releases, and next steps in one place.</p>
                </div>
                <span className="text-xs text-slate-500">
                    {dashboard.last_updated ? `Updated ${dashboard.last_updated}` : "No workspace activity yet"}
                </span>
            </header>

            <div className="grid gap-3 sm:grid-cols-3">
                <button type="button" onClick={() => onTabChange?.("websites")} className="rounded-xl border border-white/10 bg-white/[0.035] px-4 py-3 text-left transition hover:border-violet-400/35 hover:bg-white/[0.06] focus:outline-none focus:ring-2 focus:ring-violet-400">
                    <span className="text-sm font-semibold text-white">+ New Website</span>
                    <span className="mt-1 block text-xs text-slate-500">Start from a blank canvas</span>
                </button>
                <button type="button" onClick={() => onTabChange?.("templates")} className="rounded-xl border border-white/10 bg-white/[0.035] px-4 py-3 text-left transition hover:border-violet-400/35 hover:bg-white/[0.06] focus:outline-none focus:ring-2 focus:ring-violet-400">
                    <span className="text-sm font-semibold text-white">Browse Starter Kits</span>
                    <span className="mt-1 block text-xs text-slate-500">Explore prebuilt website starting points</span>
                </button>
                <button type="button" onClick={() => onTabChange?.("aiStudio")} className="rounded-xl border border-white/10 bg-white/[0.035] px-4 py-3 text-left transition hover:border-violet-400/35 hover:bg-white/[0.06] focus:outline-none focus:ring-2 focus:ring-violet-400">
                    <span className="text-sm font-semibold text-white">✦ Open AI Studio</span>
                    <span className="mt-1 block text-xs text-slate-500">Generate content faster</span>
                </button>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {stats.map((stat) => <OverviewStatCard key={stat.label} {...stat} />)}
            </div>

            <WorkspaceInformation workspace={workspace} onViewAll={() => onTabChange?.("websites")} />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(300px,0.75fr)]">
                <div>
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="text-sm font-semibold text-white">Recent websites</h2>
                        <button type="button" onClick={() => onTabChange?.("websites")} className="text-xs font-semibold text-violet-300 transition hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400">View all</button>
                    </div>
                    <RecentWebsiteList websites={recentWebsites} onEdit={openWebsite} />
                </div>
                <div className="space-y-6">
                    <WorkspaceProgress completed={progress.completed || 0} total={progress.total || 4} onContinue={() => onTabChange?.("websites")} />
                    <div>
                        <h2 className="mb-3 text-sm font-semibold text-white">Recent activity</h2>
                        <ActivityFeed activity={activity} />
                    </div>
                </div>
            </div>
        </section>
    );
}
