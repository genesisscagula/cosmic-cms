import { Link } from '@inertiajs/react';
import { useCreditBalance } from '@/Hooks/useCreditBalance';
import CosmicBrandMark from '../../../Components/CosmicBrandMark';
const baseNavigationItems = [
    { id: "home", label: "Overview", icon: "⌂" },
    { id: "websites", label: "Websites", icon: "◈" },
    { id: "media", label: "Media", icon: "▦" },
    { id: "templates", label: "Starter Kits", icon: "▧" },
    { id: "sparks", label: "Sparks", icon: "▱" },
    { id: "aiStudio", label: "AI Studio", icon: "✦" },
];

const secondaryItems = [
    { id: "settings", label: "Settings", icon: "⚙" },
];

function NavigationItem({ item, activeTab, onTabChange }) {
    const isActive = item.id === activeTab;

    return (
        <button
            type="button"
            onClick={() => onTabChange(item.id)}
            className={`cosmic-sidebar-item flex min-w-max items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition md:min-w-0 md:w-full ${
                isActive
                    ? "bg-white text-slate-950 shadow-sm"
                    : "text-slate-400 hover:bg-white/10 hover:text-white"
            }`}
        >
            <span className="w-4 text-center text-base" aria-hidden="true">
                {item.icon}
            </span>
            <span>{item.label}</span>
        </button>
    );
}

export default function Navigation({ activeTab, onTabChange, dashboard = {} }) {
    const { balance: creditBalance } = useCreditBalance();
    const dashboardBalance = Number(dashboard?.credit_balance);
    const displayedCreditBalance = Number.isFinite(Number(creditBalance))
        ? Number(creditBalance)
        : (Number.isFinite(dashboardBalance) ? dashboardBalance : null);
    const isAgency = dashboard.plan_capabilities?.plan_family === "agency";
    const navigationItems = isAgency
        ? [...baseNavigationItems.slice(0, 2), { id: "insights", label: "Agency Insights", icon: "◉" }, { id: "team", label: "Team", icon: "♙" }, { id: "branding", label: "Branding", icon: "◆" }, ...baseNavigationItems.slice(2)]
        : baseNavigationItems;

    return (
        <aside id="cosmic-dashboard-sidebar" className="cosmic-dashboard-sidebar border-b border-white/10 bg-[#111113] md:sticky md:top-0 md:flex md:h-screen md:w-64 md:flex-col md:border-b-0 md:border-r">
            <div className="flex items-center justify-between px-5 py-4 md:px-6 md:py-6">
                <button type="button" onClick={() => onTabChange("home")} className="flex items-center gap-2">
                    <CosmicBrandMark size="sm" />
                    <span className="font-semibold tracking-tight text-white">Cosmic CMS</span>
                </button>
                <span className="rounded-full border border-white/10 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                    CMS
                </span>
            </div>

            <div className="px-4 pb-4 md:px-3">
                <Link
                    href={route('credits.index')}
                    className="flex items-center justify-between rounded-xl border border-cyan-400/15 bg-cyan-400/[0.06] px-3 py-2.5 transition hover:border-cyan-400/30 hover:bg-cyan-400/10"
                >
                    <span className="text-xs font-semibold uppercase tracking-[0.16em] text-cyan-300">Cosmic Credits</span>
                    <span className="font-bold text-white">⚡ {displayedCreditBalance !== null ? displayedCreditBalance.toLocaleString() : 'Loading…'}</span>
                </Link>
            </div>

            <nav className="flex gap-2 overflow-x-auto px-4 pb-4 md:flex-1 md:flex-col md:overflow-visible md:px-3">
                {navigationItems.map((item) => (
                    <NavigationItem key={item.id} item={item} activeTab={activeTab} onTabChange={onTabChange} />
                ))}

                <div className="hidden flex-1 md:block" />

                {secondaryItems.map((item) => (
                    <NavigationItem key={item.id} item={item} activeTab={activeTab} onTabChange={onTabChange} />
                ))}

                <Link
                    href={route('logout')}
                    method="post"
                    as="button"
                    className="cosmic-sidebar-item flex min-w-max items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-400 transition hover:bg-white/10 hover:text-white md:min-w-0 md:w-full"
                >
                    <span className="w-4 text-center text-base" aria-hidden="true">↪</span>
                    <span>Log out</span>
                </Link>
            </nav>
        </aside>
    );
}
