import ActivityFeed from "../Components/ActivityFeed";
import OverviewStatCard from "../Components/OverviewStatCard";
import RecentWebsiteList from "../Components/RecentWebsiteList";
import WorkspaceProgress from "../Components/WorkspaceProgress";

const stats = [
    { label: "Total Websites", value: "8", detail: "+2 this month", accent: "bg-violet-400/10 text-violet-300" },
    { label: "Published", value: "5", detail: "2 updated this week", accent: "bg-emerald-400/10 text-emerald-300" },
    { label: "Drafts", value: "3", detail: "Ready for your review", accent: "bg-amber-300/10 text-amber-200" },
    { label: "Recent Deployments", value: "12", detail: "Across the last 30 days", accent: "bg-cyan-400/10 text-cyan-300" },
];

const recentWebsites = [
    { id: "northstar", name: "Northstar Studio", domain: "northstar-studio.com", status: "Published", accent: "from-violet-500 to-indigo-600" },
    { id: "harbor", name: "Harbor & Pine", domain: "harborandpine.com", status: "Draft", accent: "from-emerald-500 to-teal-600" },
    { id: "arc", name: "Arc Dental", domain: "arcdental.co", status: "Published", accent: "from-sky-500 to-blue-700" },
];

const activity = [
    { id: 1, type: "edit", actor: "You", action: "edited", target: "Northstar Studio", time: "12 minutes ago" },
    { id: 2, type: "publish", actor: "You", action: "published the Services page for", target: "Arc Dental", time: "2 hours ago" },
    { id: 3, type: "theme", actor: "You", action: "changed the theme for", target: "Harbor & Pine", time: "Yesterday" },
    { id: 4, type: "ai", actor: "Cosmic AI", action: "generated content for", target: "Ember Coffee", time: "Jul 21" },
];

export default function Home() {
    return <section className="space-y-7"><header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p className="text-sm font-medium text-violet-300">Cosmic workspace</p><h1 className="mt-2 text-3xl font-semibold tracking-tight text-white sm:text-4xl">Overview</h1><p className="mt-2 text-sm text-slate-400">Your websites, releases, and next steps in one place.</p></div><span className="text-xs text-slate-500">Updated just now</span></header><div className="grid gap-3 sm:grid-cols-3"><button type="button" onClick={() => console.info("Create website")} className="rounded-xl border border-white/10 bg-white/[0.035] px-4 py-3 text-left transition hover:border-violet-400/35 hover:bg-white/[0.06] focus:outline-none focus:ring-2 focus:ring-violet-400"><span className="text-sm font-semibold text-white">+ New Website</span><span className="mt-1 block text-xs text-slate-500">Start from a blank canvas</span></button><button type="button" onClick={() => console.info("Browse templates")} className="rounded-xl border border-white/10 bg-white/[0.035] px-4 py-3 text-left transition hover:border-violet-400/35 hover:bg-white/[0.06] focus:outline-none focus:ring-2 focus:ring-violet-400"><span className="text-sm font-semibold text-white">Browse Templates</span><span className="mt-1 block text-xs text-slate-500">Explore starting points</span></button><button type="button" onClick={() => console.info("Open AI Studio")} className="rounded-xl border border-white/10 bg-white/[0.035] px-4 py-3 text-left transition hover:border-violet-400/35 hover:bg-white/[0.06] focus:outline-none focus:ring-2 focus:ring-violet-400"><span className="text-sm font-semibold text-white">✦ Open AI Studio</span><span className="mt-1 block text-xs text-slate-500">Generate content faster</span></button></div><div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">{stats.map((stat) => <OverviewStatCard key={stat.label} {...stat} />)}</div><div className="grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(300px,0.75fr)]"><div><div className="mb-3 flex items-center justify-between"><h2 className="text-sm font-semibold text-white">Recent websites</h2><button type="button" onClick={() => console.info("View all websites")} className="text-xs font-semibold text-violet-300 transition hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400">View all</button></div><RecentWebsiteList websites={recentWebsites} onEdit={(website) => console.info("Edit website", website.id)} /></div><div className="space-y-6"><WorkspaceProgress completed={2} total={4} /><div><h2 className="mb-3 text-sm font-semibold text-white">Recent activity</h2><ActivityFeed activity={activity} /></div></div></div></section>;
}
