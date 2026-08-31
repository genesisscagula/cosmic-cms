import { router } from "@inertiajs/react";
import ActivityFeed from "../Components/ActivityFeed";
import OverviewPerformance from "../Components/OverviewPerformance";
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
    const analytics = dashboard.overview_analytics || {};

    const openWebsite = (website) => router.visit(route("pages.index", website.id));

    return (
        <section className="cosmic-overview space-y-8">
            <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p className="text-sm font-medium">Cosmic workspace</p>
                    <h1 className="mt-2 text-3xl font-black tracking-tight cosmic-text-strong sm:text-4xl">Overview</h1>
                    <p className="mt-2 text-sm cosmic-text-muted">Your websites, performance, releases, and next steps in one place.</p>
                </div>
                <span className="cosmic-updated-pill">
                    <span className="cosmic-updated-dot" />
                    {dashboard.last_updated ? `Updated ${dashboard.last_updated}` : "Workspace ready"}
                </span>
            </header>

            <div className="grid gap-3 sm:grid-cols-3">
                <button type="button" onClick={() => onTabChange?.("websites")} className="cosmic-quick-action cosmic-quick-action--emerald">
                    <span className="cosmic-quick-action-icon">＋</span>
                    <span><strong>New Website</strong><small>Start from a blank canvas</small></span>
                    <span className="cosmic-quick-arrow">↗</span>
                </button>
                <button type="button" onClick={() => onTabChange?.("templates")} className="cosmic-quick-action cosmic-quick-action--blue">
                    <span className="cosmic-quick-action-icon">▦</span>
                    <span><strong>Browse Starter Kits</strong><small>Explore prebuilt starting points</small></span>
                    <span className="cosmic-quick-arrow">↗</span>
                </button>
                <button type="button" onClick={() => onTabChange?.("aiStudio")} className="cosmic-quick-action cosmic-quick-action--violet">
                    <span className="cosmic-quick-action-icon">✦</span>
                    <span><strong>Open AI Studio</strong><small>Generate content faster</small></span>
                    <span className="cosmic-quick-arrow">↗</span>
                </button>
            </div>

            <OverviewPerformance analytics={analytics} onOpenInsights={() => onTabChange?.("insights")} />

            <div className="space-y-4">
                <div>
                    <p className="cosmic-section-kicker">Workspace</p>
                    <h2 className="cosmic-section-title">Account & publishing</h2>
                </div>
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {stats.map((stat) => <OverviewStatCard key={stat.label} {...stat} />)}
                </div>
            </div>

            <WorkspaceInformation workspace={workspace} onViewAll={() => onTabChange?.("websites")} />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(300px,0.75fr)]">
                <div>
                    <div className="mb-3 flex items-center justify-between">
                        <div>
                            <p className="cosmic-section-kicker">Recently edited</p>
                            <h2 className="cosmic-section-title text-base">Websites</h2>
                        </div>
                        <button type="button" onClick={() => onTabChange?.("websites")} className="cosmic-panel-link">View all</button>
                    </div>
                    <RecentWebsiteList websites={recentWebsites} onEdit={openWebsite} />
                </div>
                <div className="space-y-6">
                    <WorkspaceProgress completed={progress.completed || 0} total={progress.total || 4} onContinue={() => onTabChange?.("websites")} />
                    <div>
                        <h2 className="mb-3 text-sm font-semibold cosmic-text-strong">Recent activity</h2>
                        <ActivityFeed activity={activity} />
                    </div>
                </div>
            </div>
        </section>
    );
}
