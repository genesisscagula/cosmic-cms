import { router } from "@inertiajs/react";
import { useEffect, useState } from "react";
import Navigation from "./Components/Navigation";
import Home from "./Tabs/Home";
import Websites from "./Tabs/Websites";
import Templates from "./Tabs/Templates";
import Sparks from "./Tabs/Sparks";
import AIStudio from "./Tabs/AIStudio";
import Publish from "./Tabs/Publish";
import Settings from "./Tabs/Settings";
import Insights from "./Tabs/Insights";
import Team from "./Tabs/Team";

const tabs = {
    home: Home,
    websites: Websites,
    templates: Templates,
    sparks: Sparks,
    aiStudio: AIStudio,
    insights: Insights,
    team: Team,
    publish: Publish,
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
        // Browser Back can restore an older Inertia history snapshot. Refresh
        // only the real website collection so a newly created site is visible.
        router.reload({ only: ["websites", "dashboard"], preserveScroll: true, preserveState: true });
    }, []);

    return (
        <div className="min-h-screen bg-[#0a0a0b] text-slate-100 md:flex">
            <Navigation activeTab={activeTab} onTabChange={changeTab} dashboard={dashboard} />

            <main className="min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-10 lg:py-10">
                <div className="mx-auto max-w-7xl">
                    <ActiveTab websites={websites} dashboard={dashboard} onTabChange={changeTab} />
                </div>
            </main>
        </div>
    );
}
