const navigationItems = [
    { id: "home", label: "Overview", icon: "⌂" },
    { id: "websites", label: "Websites", icon: "◈" },
    { id: "templates", label: "Templates", icon: "▧" },
    { id: "blocks", label: "Blocks", icon: "◫" },
    { id: "aiStudio", label: "AI Studio", icon: "✦" },
];

const secondaryItems = [
    { id: "publish", label: "Publish", icon: "↗" },
    { id: "settings", label: "Settings", icon: "⚙" },
];

function NavigationItem({ item, activeTab, onTabChange }) {
    const isActive = item.id === activeTab;

    return (
        <button
            type="button"
            onClick={() => onTabChange(item.id)}
            className={`flex min-w-max items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition md:min-w-0 md:w-full ${
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

export default function Navigation({ activeTab, onTabChange }) {
    return (
        <aside className="border-b border-white/10 bg-[#111113] md:sticky md:top-0 md:flex md:h-screen md:w-64 md:flex-col md:border-b-0 md:border-r">
            <div className="flex items-center justify-between px-5 py-4 md:px-6 md:py-6">
                <button type="button" onClick={() => onTabChange("home")} className="flex items-center gap-2">
                    <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-violet-500 to-cyan-400 text-sm font-black text-slate-950">
                        C
                    </span>
                    <span className="font-semibold tracking-tight text-white">Cosmic</span>
                </button>
                <span className="rounded-full border border-white/10 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                    CMS
                </span>
            </div>

            <nav className="flex gap-2 overflow-x-auto px-4 pb-4 md:flex-1 md:flex-col md:overflow-visible md:px-3">
                {navigationItems.map((item) => (
                    <NavigationItem key={item.id} item={item} activeTab={activeTab} onTabChange={onTabChange} />
                ))}

                <div className="hidden flex-1 md:block" />

                {secondaryItems.map((item) => (
                    <NavigationItem key={item.id} item={item} activeTab={activeTab} onTabChange={onTabChange} />
                ))}
            </nav>
        </aside>
    );
}
