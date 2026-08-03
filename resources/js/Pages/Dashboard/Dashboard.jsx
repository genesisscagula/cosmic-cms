import { router } from "@inertiajs/react";
import { useEffect, useState } from "react";
import Navigation from "./Components/Navigation";
import Home from "./Tabs/Home";
import Websites from "./Tabs/Websites";
import Templates from "./Tabs/Templates";
import Blocks from "./Tabs/Blocks";
import AIStudio from "./Tabs/AIStudio";
import Publish from "./Tabs/Publish";
import Settings from "./Tabs/Settings";

const tabs = {
    home: Home,
    websites: Websites,
    templates: Templates,
    blocks: Blocks,
    aiStudio: AIStudio,
    publish: Publish,
    settings: Settings,
};

export default function Dashboard({ websites, dashboard }) {
    const [activeTab, setActiveTab] = useState("home");
    const ActiveTab = tabs[activeTab];

    useEffect(() => {
        // Browser Back can restore an older Inertia history snapshot. Refresh
        // only the real website collection so a newly created site is visible.
        router.reload({ only: ["websites", "dashboard"], preserveScroll: true, preserveState: true });
    }, []);

    return (
        <div className="min-h-screen bg-[#0a0a0b] text-slate-100 md:flex">
            <Navigation activeTab={activeTab} onTabChange={setActiveTab} />

            <main className="min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-10 lg:py-10">
                <div className="mx-auto max-w-7xl">
                    <ActiveTab websites={websites} dashboard={dashboard} onTabChange={setActiveTab} />
                </div>
            </main>
        </div>
    );
}
