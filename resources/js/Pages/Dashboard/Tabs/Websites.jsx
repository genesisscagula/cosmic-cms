import { router } from "@inertiajs/react";
import { useMemo, useState } from "react";
import WebsiteCard from "../Components/WebsiteCard";
import WebsiteEmptyState from "../Components/WebsiteEmptyState";
import WebsiteToolbar from "../Components/WebsiteToolbar";
import NewWebsiteModal from "../Components/NewWebsiteModal";

const accents = [
    "from-violet-500 to-indigo-600",
    "from-emerald-500 to-teal-600",
    "from-sky-500 to-blue-700",
    "from-orange-400 to-rose-600",
];

const formatLastEdited = (value) => {
    if (!value) return "Not yet edited";

    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? "Recently updated" : `Edited ${date.toLocaleDateString(undefined, { month: "short", day: "numeric" })}`;
};

const mapWebsiteForCard = (website, index) => {
    const themeSettings = website.theme_settings && typeof website.theme_settings === "object" ? website.theme_settings : {};
    const theme = themeSettings.primary || themeSettings.primary_color || "Default";

    return {
        id: website.id,
        name: website.name || "Untitled Website",
        domain: website.domain || "No domain connected",
        status: website.status === "published" || website.status === "Published" ? "Published" : "Draft",
        theme: `${theme.charAt(0).toUpperCase()}${theme.slice(1)}`,
        lastEdited: formatLastEdited(website.updated_at),
        accent: accents[index % accents.length],
    };
};

export default function Websites({ websites = [] }) {
    const [query, setQuery] = useState("");
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const websiteCards = useMemo(() => websites.map(mapWebsiteForCard), [websites]);

    const filteredWebsites = useMemo(() => {
        const normalizedQuery = query.trim().toLowerCase();
        return normalizedQuery ? websiteCards.filter((website) => [website.name, website.domain, website.status, website.theme].join(" ").toLowerCase().includes(normalizedQuery)) : websiteCards;
    }, [query, websiteCards]);

    return <section><p className="text-sm font-medium text-violet-300">Workspace</p><div className="mt-2"><h1 className="text-3xl font-semibold tracking-tight text-white">Websites</h1><p className="mt-2 text-sm text-slate-400">Create, organize, and launch your websites.</p></div><div className="mt-8"><WebsiteToolbar query={query} onQueryChange={setQuery} onCreate={() => setIsCreateModalOpen(true)} /></div><div className="mt-6 grid gap-4 xl:grid-cols-2">{filteredWebsites.map((website) => <WebsiteCard key={website.id} website={website} onEdit={(item) => router.visit(route("pages.index", item.id))} onDuplicate={(item) => console.info("Duplicate website", item.id)} onDelete={(item) => console.info("Delete website", item.id)} />)}</div>{!filteredWebsites.length && <div className="mt-6"><WebsiteEmptyState query={query} /></div>}<NewWebsiteModal open={isCreateModalOpen} onClose={() => setIsCreateModalOpen(false)} /></section>;
}
