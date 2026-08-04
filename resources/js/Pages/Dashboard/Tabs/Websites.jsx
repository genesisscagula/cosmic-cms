import { router } from "@inertiajs/react";
import { useMemo, useState } from "react";
import axios from "axios";
import WebsiteCard from "../Components/WebsiteCard";
import WebsiteEmptyState from "../Components/WebsiteEmptyState";
import WebsiteToolbar from "../Components/WebsiteToolbar";
import NewWebsiteModal from "../Components/NewWebsiteModal";
import UpgradePlanModal from "../Components/UpgradePlanModal";
import { confirmCosmicAction, showCosmicNotification } from "../../../Components/CosmicNotification";

const accents = ["from-violet-500 to-indigo-600", "from-emerald-500 to-teal-600", "from-sky-500 to-blue-700", "from-orange-400 to-rose-600"];
const formatLastEdited = (value) => { if (!value) return "Not yet edited"; const date = new Date(value); return Number.isNaN(date.getTime()) ? "Recently updated" : `Edited ${date.toLocaleDateString(undefined, { month: "short", day: "numeric" })}`; };
const mapWebsiteForCard = (website, index) => { const themeSettings = website.theme_settings && typeof website.theme_settings === "object" ? website.theme_settings : {}; const theme = themeSettings.primary || themeSettings.primary_color || "Default"; return { id: website.id, name: website.name || "Untitled Website", domain: website.domain || "No domain connected", status: website.status === "published" || website.status === "Published" ? "Published" : "Draft", theme: `${theme.charAt(0).toUpperCase()}${theme.slice(1)}`, lastEdited: formatLastEdited(website.updated_at), accent: accents[index % accents.length] }; };

export default function Websites({ websites = [], dashboard = {} }) {
    const [query, setQuery] = useState("");
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [isUpgradeModalOpen, setIsUpgradeModalOpen] = useState(false);
    const capabilities = dashboard.plan_capabilities || {};
    const locked = capabilities.can_add_sites === false;
    const websiteCards = useMemo(() => websites.map(mapWebsiteForCard), [websites]);
    const filteredWebsites = useMemo(() => { const normalizedQuery = query.trim().toLowerCase(); return normalizedQuery ? websiteCards.filter((website) => [website.name, website.domain, website.status, website.theme].join(" ").toLowerCase().includes(normalizedQuery)) : websiteCards; }, [query, websiteCards]);

    const openCreate = () => {
        if (locked) {
            setIsUpgradeModalOpen(true);
            return;
        }
        setIsCreateModalOpen(true);
    };

    const deleteWebsite = async (website) => { if (!await confirmCosmicAction({ title: `Delete ${website.name}?`, message: "This permanently removes the website and all of its pages from Cosmic CMS.", confirmLabel: "Delete website", tone: "error" })) return; router.delete(`/websites/${website.id}`, { preserveScroll: true }); };
    const downloadConnector = (website) => window.location.assign(route("websites.deployment-connector.download", website.id));
    const connectLiveSite = async (website) => { try { const response = await axios.post(route("websites.deployment-connector.verify", website.id)); showCosmicNotification({ title: "Live site connected", message: response.data.message, tone: "success" }); router.reload({ only: ["websites"], preserveScroll: true, preserveState: true }); } catch (error) { showCosmicNotification({ title: "Connection failed", message: error.response?.data?.message || "The live site connector could not be verified.", tone: "error" }); } };
    const pushLiveUpdate = async (website) => { if (!await confirmCosmicAction({ title: "Push live update?", message: `All published pages for ${website.name} will be sent to its connected live site.`, confirmLabel: "Push update", tone: "info" })) return; try { const response = await axios.post(route("websites.deployment-connector.push", website.id)); showCosmicNotification({ title: "Live site updated", message: response.data.message, tone: "success" }); router.reload({ only: ["websites"], preserveScroll: true, preserveState: true }); } catch (error) { showCosmicNotification({ title: "Live update failed", message: error.response?.data?.message || "The live update could not be pushed.", tone: "error" }); } };

    return <section>
        <p className="text-sm font-medium text-violet-300">Workspace</p>
        <div className="mt-2"><h1 className="text-3xl font-semibold tracking-tight text-white">Websites</h1><p className="mt-2 text-sm text-slate-400">Create, organize, and launch your websites.</p></div>
        <div className="mt-8"><WebsiteToolbar query={query} onQueryChange={setQuery} onCreate={openCreate} planCapabilities={capabilities} /></div>
        {locked && <div className="mt-4 flex flex-col gap-3 rounded-2xl border border-amber-300/15 bg-amber-300/[0.06] p-4 sm:flex-row sm:items-center sm:justify-between"><div><p className="text-sm font-semibold text-amber-100">{capabilities.site_count || websites.length} of {capabilities.max_sites_label || 1} website used</p><p className="mt-1 text-xs text-amber-100/65">{capabilities.upgrade_message}</p></div><button type="button" onClick={() => setIsUpgradeModalOpen(true)} className="rounded-xl border border-amber-200/20 bg-amber-200/10 px-3 py-2 text-xs font-semibold text-amber-50 transition hover:bg-amber-200/15 focus:outline-none focus:ring-2 focus:ring-amber-200/30">View upgrade options</button></div>}
        <div className="mt-6 grid gap-4 xl:grid-cols-2">{filteredWebsites.map((website) => <WebsiteCard key={website.id} website={website} onEdit={(item) => router.visit(route("pages.index", item.id))} onDuplicate={(item) => console.info("Duplicate website", item.id)} onDelete={deleteWebsite} onDownloadConnector={downloadConnector} onConnectLiveSite={connectLiveSite} onPushLiveUpdate={pushLiveUpdate} />)}</div>
        {!filteredWebsites.length && <div className="mt-6"><WebsiteEmptyState query={query} /></div>}
        <NewWebsiteModal open={isCreateModalOpen} onClose={() => setIsCreateModalOpen(false)} />
        <UpgradePlanModal open={isUpgradeModalOpen} onClose={() => setIsUpgradeModalOpen(false)} capabilities={capabilities} />
    </section>;
}
