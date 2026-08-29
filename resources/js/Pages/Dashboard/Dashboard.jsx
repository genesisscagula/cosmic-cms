import { router } from "@inertiajs/react";
import { useEffect, useState } from "react";
import Navigation from "./Components/Navigation";
import Home from "./Tabs/Home";
import Websites from "./Tabs/Websites";
import Health from "./Tabs/Health";
import Templates from "./Tabs/Templates";
import Media from "./Tabs/Media";
import Sparks from "./Tabs/Sparks";
import AIStudio from "./Tabs/AIStudio";
import Settings from "./Tabs/Settings";
import Insights from "./Tabs/Insights";
import Team from "./Tabs/Team";
import Branding from "./Tabs/Branding";
import AppearanceSwitch from "../../Appearance/AppearanceSwitch";

const tabs = {
    home: Home,
    websites: Websites,
    health: Health,
    media: Media,
    templates: Templates,
    sparks: Sparks,
    aiStudio: AIStudio,
    insights: Insights,
    team: Team,
    branding: Branding,
    settings: Settings,
};

export default function Dashboard({ websites, dashboard }) {
    const [activeTab, setActiveTab] = useState(() => {
        if (typeof window === "undefined") return "home";

        const requestedTab = new URLSearchParams(window.location.search).get("tab");
        if (requestedTab === "blocks") return "sparks";

        return tabs[requestedTab] ? requestedTab : "home";
    });
    const ActiveTab = tabs[activeTab] ?? Home;

    const changeTab = (tab) => {
        const normalizedTab = tab === "blocks" ? "sparks" : tab;
        setActiveTab(tabs[normalizedTab] ? normalizedTab : "home");

        if (typeof window !== "undefined") {
            const url = new URL(window.location.href);
            if (normalizedTab === "home") url.searchParams.delete("tab");
            else url.searchParams.set("tab", normalizedTab);
            window.history.replaceState({}, "", url);
        }
    };

    useEffect(() => {
        // Do not reload on every normal Dashboard mount. An Inertia reload can
        // remount this page, which would create a reload -> remount loop.
        // Only refresh when the browser restores this page from the back/forward
        // cache, where the snapshot can genuinely be stale.
        const handlePageShow = (event) => {
            if (!event.persisted) return;

            router.reload({
                only: ["websites", "dashboard"],
                preserveScroll: true,
                preserveState: true,
            });
        };

        window.addEventListener("pageshow", handlePageShow);
        return () => window.removeEventListener("pageshow", handlePageShow);
    }, []);

    return (
        <div id="cosmic-dashboard" data-cosmic-dashboard className="cosmic-app-shell cosmic-dashboard-shell min-h-screen font-dashboard md:flex">
            <Navigation activeTab={activeTab} onTabChange={changeTab} dashboard={dashboard} />

            <main id="cosmic-dashboard-main" className="cosmic-dashboard-main relative min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-10 lg:py-10">
                <div className="fixed right-4 top-4 z-50"><AppearanceSwitch compact /></div>
                <div
                    className={`cosmic-dashboard-tab-frame mx-auto ${activeTab === "media" ? "max-w-[1600px]" : "max-w-7xl"}`}
                    data-dashboard-tab={activeTab}
                >
                    <ActiveTab websites={websites} dashboard={dashboard} onTabChange={changeTab} />
                </div>
            </main>
        </div>
    );
}
