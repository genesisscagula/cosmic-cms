import { Link } from '@inertiajs/react';
import { useCreditBalance } from '@/Hooks/useCreditBalance';
import CosmicBrandMark from '../../../Components/CosmicBrandMark';

const Icon = ({ name, className = '' }) => {
    const common = {
        className: `h-[18px] w-[18px] ${className}`,
        viewBox: '0 0 24 24',
        fill: 'none',
        stroke: 'currentColor',
        strokeWidth: 1.8,
        strokeLinecap: 'round',
        strokeLinejoin: 'round',
        'aria-hidden': true,
    };

    const paths = {
        home: <><path d="M3 10.5 12 3l9 7.5" /><path d="M5.5 9.5V21h13V9.5" /><path d="M9.5 21v-6h5v6" /></>,
        websites: <><rect x="3" y="4" width="18" height="16" rx="3" /><path d="M3 9h18" /><path d="M7 6.5h.01M10 6.5h.01" /></>,
        insights: <><path d="M4 19V9" /><path d="M10 19V5" /><path d="M16 19v-7" /><path d="M22 19V3" /></>,
        team: <><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /></>,
        branding: <><path d="M12 3 4 7v6c0 4.4 3.1 7.7 8 8 4.9-.3 8-3.6 8-8V7l-8-4Z" /><path d="m9 12 2 2 4-5" /></>,
        health: <><path d="M12 2v20M2 12h20" /><circle cx="12" cy="12" r="8.5" /></>,
        media: <><rect x="3" y="3" width="18" height="18" rx="3" /><circle cx="8.5" cy="8.5" r="1.5" /><path d="m21 15-5-5L5 21" /></>,
        templates: <><rect x="3" y="4" width="8" height="16" rx="2" /><rect x="13" y="4" width="8" height="7" rx="2" /><rect x="13" y="13" width="8" height="7" rx="2" /></>,
        sparks: <><path d="m12 3 1.45 4.1L17.5 8.5l-4.05 1.4L12 14l-1.45-4.1L6.5 8.5l4.05-1.4L12 3Z" /><path d="m18.5 14 .8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8.8-2.2Z" /></>,
        aiStudio: <><path d="M12 3 13.7 8.3 19 10l-5.3 1.7L12 17l-1.7-5.3L5 10l5.3-1.7L12 3Z" /><path d="M19 3v4M17 5h4" /></>,
        settings: <><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06-2.83 2.83-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21h-4v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06-2.83-2.83.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3v-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06 2.83-2.83.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3h4v.09A1.65 1.65 0 0 0 15 4.6a1.65 1.65 0 0 0 1.82-.33l.06-.06 2.83 2.83-.06.06A1.65 1.65 0 0 0 19.32 9c.12.37.48.62.87.62H21v4h-.09c-.39 0-.75.25-.87.62-.13.39-.34.68-.64.76Z" /></>,
        logout: <><path d="M10 17l5-5-5-5" /><path d="M15 12H3" /><path d="M21 19V5a2 2 0 0 0-2-2h-6" /></>,
    };

    return <svg {...common}>{paths[name] || paths.sparks}</svg>;
};

const baseNavigationItems = [
    { id: 'home', label: 'Overview', icon: 'home', tone: 'emerald' },
    { id: 'websites', label: 'Websites', icon: 'websites', tone: 'blue' },
    { id: 'health', label: 'Health', icon: 'health', tone: 'rose' },
    { id: 'media', label: 'Media', icon: 'media', tone: 'indigo' },
    { id: 'templates', label: 'Starter Kits', icon: 'templates', tone: 'amber' },
    { id: 'sparks', label: 'Sparks', icon: 'sparks', tone: 'violet' },
    { id: 'aiStudio', label: 'AI Studio', icon: 'aiStudio', tone: 'emerald' },
];

const secondaryItems = [
    { id: 'settings', label: 'Settings', icon: 'settings', tone: 'slate' },
];

function NavigationItem({ item, activeTab, onTabChange }) {
    const isActive = item.id === activeTab;

    return (
        <button
            type="button"
            onClick={() => onTabChange(item.id)}
            className={`cosmic-sidebar-item ${isActive ? 'is-active' : ''} flex min-w-max items-center gap-3 rounded-2xl px-2.5 py-2.5 text-sm font-medium transition md:min-w-0 md:w-full`}
        >
            <span className={`cosmic-sidebar-icon cosmic-sidebar-icon--${item.tone}`} aria-hidden="true">
                <Icon name={item.icon} />
            </span>
            <span className="cosmic-sidebar-label">{item.label}</span>
            {isActive && <span className="cosmic-sidebar-active-dot" aria-hidden="true" />}
        </button>
    );
}

export default function Navigation({ activeTab, onTabChange, dashboard = {} }) {
    const { balance: creditBalance } = useCreditBalance();
    const dashboardBalance = Number(dashboard?.credit_balance);
    const displayedCreditBalance = Number.isFinite(Number(creditBalance))
        ? Number(creditBalance)
        : (Number.isFinite(dashboardBalance) ? dashboardBalance : null);
    const isAgency = dashboard.plan_capabilities?.plan_family === 'agency';
    const navigationItems = isAgency
        ? [
            ...baseNavigationItems.slice(0, 2),
            { id: 'insights', label: 'Agency Insights', icon: 'insights', tone: 'violet' },
            { id: 'team', label: 'Team', icon: 'team', tone: 'cyan' },
            { id: 'branding', label: 'Branding', icon: 'branding', tone: 'amber' },
            ...baseNavigationItems.slice(2),
        ]
        : baseNavigationItems;

    return (
        <aside id="cosmic-dashboard-sidebar" className="cosmic-dashboard-sidebar md:sticky md:top-0 md:flex md:h-screen md:w-64 md:flex-col">
            <div className="cosmic-sidebar-brand flex items-center justify-between px-5 py-4 md:px-5 md:py-5">
                <button type="button" onClick={() => onTabChange('home')} className="group flex min-w-0 items-center gap-2.5">
                    <span className="cosmic-sidebar-brandmark"><CosmicBrandMark size="sm" /></span>
                    <span className="truncate font-semibold tracking-tight">Cosmic CMS</span>
                </button>
                <span className="cosmic-sidebar-cms-badge">CMS</span>
            </div>

            <div className="px-4 pb-4 md:px-3.5">
                <Link href={route('credits.index')} className="cosmic-sidebar-credits group">
                    <span>
                        <span className="block text-[10px] font-bold uppercase tracking-[0.18em]">Cosmic Credits</span>
                        <span className="mt-0.5 block text-[10px] font-medium opacity-60">Available balance</span>
                    </span>
                    <span className="cosmic-sidebar-credit-value">
                        <span className="cosmic-sidebar-bolt" aria-hidden="true">⚡</span>
                        {displayedCreditBalance !== null ? displayedCreditBalance.toLocaleString() : 'Loading…'}
                    </span>
                </Link>
            </div>

            <nav className="cosmic-sidebar-nav flex gap-2 overflow-x-auto px-4 pb-4 md:flex-1 md:flex-col md:overflow-visible md:px-3.5">
                {navigationItems.map((item) => (
                    <NavigationItem key={item.id} item={item} activeTab={activeTab} onTabChange={onTabChange} />
                ))}

                <div className="hidden flex-1 md:block" />

                <div className="hidden md:block cosmic-sidebar-divider" />

                {secondaryItems.map((item) => (
                    <NavigationItem key={item.id} item={item} activeTab={activeTab} onTabChange={onTabChange} />
                ))}

                <Link
                    href={route('logout')}
                    method="post"
                    as="button"
                    className="cosmic-sidebar-item cosmic-sidebar-logout flex min-w-max items-center gap-3 rounded-2xl px-2.5 py-2.5 text-sm font-medium transition md:min-w-0 md:w-full"
                >
                    <span className="cosmic-sidebar-icon cosmic-sidebar-icon--slate" aria-hidden="true"><Icon name="logout" /></span>
                    <span className="cosmic-sidebar-label">Log out</span>
                </Link>
            </nav>
        </aside>
    );
}
