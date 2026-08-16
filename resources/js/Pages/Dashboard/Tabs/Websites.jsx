import { router } from "@inertiajs/react";
import { useEffect, useMemo, useState } from "react";
import axios from "axios";
import WebsiteCard from "../Components/WebsiteCard";
import WebsiteEmptyState from "../Components/WebsiteEmptyState";
import WebsiteToolbar from "../Components/WebsiteToolbar";
import NewWebsiteModal from "../Components/NewWebsiteModal";
import UpgradePlanModal from "../Components/UpgradePlanModal";
import TransferOwnershipModal from "../Components/TransferOwnershipModal";
import { confirmCosmicAction, showCosmicNotification } from "../../../Components/CosmicNotification";

const accents = ["from-violet-500 to-indigo-600", "from-emerald-500 to-teal-600", "from-sky-500 to-blue-700", "from-orange-400 to-rose-600"];
const readFilter = (key, fallback) => typeof window === "undefined" ? fallback : new URLSearchParams(window.location.search).get(key) || fallback;
const formatLastEdited = (value, label) => { if (label) return label; if (!value) return "Not yet edited"; const date = new Date(value); return Number.isNaN(date.getTime()) ? "Recently updated" : date.toLocaleDateString(undefined, { month: "short", day: "numeric", year: "numeric" }); };
const mapWebsiteForCard = (website, index) => {
    const themeSettings = website.theme_settings && typeof website.theme_settings === "object" ? website.theme_settings : {};
    const published = website.status ? website.status === "Published" : Number(website.published_pages_count || 0) > 0;
    const theme = website.theme || themeSettings.primary || themeSettings.primary_color || "midnight";
    return { id: website.id, name: website.name || "Untitled Website", domain: website.domain || "No domain connected", industry: website.industry || "Uncategorized", location: website.location || "", status: published ? "Published" : "Draft", liveConnected: Boolean(website.deployment_verified_at), deploymentStatus: website.deployment_status || (website.last_deployed_at ? "Deployed" : (website.deployment_verified_at ? "Connected" : "Not connected")), pagesCount: Number(website.pages_count || 0), theme: `${theme.charAt(0).toUpperCase()}${theme.slice(1)}`, lastEdited: formatLastEdited(website.updated_at, website.updated_label), updatedAt: website.updated_at, createdAt: website.created_at, logoUrl: website.logo_url || null, previewUrl: website.preview_url || null, previewReady: Boolean(website.preview_ready || website.preview_url), canTransferOwnership: Boolean(website.can_transfer_ownership), canManageAccess: Boolean(website.can_manage_access), accessRole: website.access_role || null, ownerEmail: website.owner_email || null, accent: accents[(website.accent_index ?? index) % accents.length] };
};

export default function Websites({ websites = [], dashboard = {} }) {
    const payload = dashboard.websites_dashboard || {};
    const sourceWebsites = payload.items || websites;
    const [query, setQuery] = useState(() => readFilter("website_search", ""));
    const [status, setStatus] = useState(() => readFilter("website_status", "all"));
    const [industry, setIndustry] = useState(() => readFilter("website_industry", "all"));
    const [deployment, setDeployment] = useState(() => readFilter("website_deployment", "all"));
    const [activity, setActivity] = useState(() => readFilter("website_activity", "all"));
    const [sort, setSort] = useState(() => readFilter("website_sort", "recent"));
    const [page, setPage] = useState(1);
    const [viewMode, setViewMode] = useState(() => typeof window !== "undefined" ? localStorage.getItem("cosmic-websites-view") || "grid" : "grid");
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [isUpgradeModalOpen, setIsUpgradeModalOpen] = useState(false);
    const [selectedWebsiteIds, setSelectedWebsiteIds] = useState([]);
    const [bulkActionPending, setBulkActionPending] = useState(false);
    const [transferWebsite, setTransferWebsite] = useState(null);
    const capabilities = dashboard.plan_capabilities || {};
    const locked = capabilities.can_add_sites === false;
    const isAgency = payload.mode === "agency" || capabilities.plan_family === "agency";
    const canBulkActions = Boolean(capabilities.capabilities?.bulk_actions);
    const websiteCards = useMemo(() => sourceWebsites.map(mapWebsiteForCard), [sourceWebsites]);
    const summary = payload.summary || { websites: websiteCards.length, pages: websiteCards.reduce((sum, item) => sum + item.pagesCount, 0), published: websiteCards.filter((item) => item.status === "Published").length, draft: websiteCards.filter((item) => item.status === "Draft").length, connected: websiteCards.filter((item) => item.deploymentStatus !== "Not connected").length, credits: 0 };
    const filterMeta = payload.filters || {};
    const industries = filterMeta.industries || payload.industries || [...new Set(websiteCards.map((item) => item.industry))].sort();
    const statusOptions = filterMeta.statuses || [["all", "All"], ["published", "Published"], ["draft", "Draft"], ["connected", "Connected"]].map(([key, label]) => ({ key, label }));

    useEffect(() => { if (typeof window !== "undefined") localStorage.setItem("cosmic-websites-view", viewMode); }, [viewMode]);
    useEffect(() => { setPage(1); }, [query, status, industry, deployment, activity, sort]);
    useEffect(() => {
        if (typeof window === "undefined") return;
        const timer = window.setTimeout(() => {
            const params = new URLSearchParams(window.location.search);
            const values = { website_search: query.trim(), website_status: status, website_industry: industry, website_deployment: deployment, website_activity: activity, website_sort: sort };
            Object.entries(values).forEach(([key, value]) => value && value !== "all" && value !== "recent" ? params.set(key, value) : params.delete(key));
            const next = `${window.location.pathname}${params.toString() ? `?${params}` : ""}${window.location.hash}`;
            window.history.replaceState({}, "", next);
        }, 250);
        return () => window.clearTimeout(timer);
    }, [query, status, industry, deployment, activity, sort]);

    const filteredWebsites = useMemo(() => {
        const normalizedQuery = query.trim().toLowerCase();
        const now = Date.now();
        const activityDays = activity === "7_days" ? 7 : activity === "30_days" ? 30 : activity === "90_days" ? 90 : null;
        const result = websiteCards.filter((website) => {
            const matchesQuery = !normalizedQuery || [website.name, website.domain, website.status, website.theme, website.industry, website.location, website.deploymentStatus].join(" ").toLowerCase().includes(normalizedQuery);
            const matchesStatus = status === "all" || website.status.toLowerCase() === status || (status === "connected" && website.deploymentStatus !== "Not connected");
            const matchesIndustry = industry === "all" || website.industry === industry;
            const deploymentKey = website.deploymentStatus.toLowerCase().replaceAll(" ", "_");
            const matchesDeployment = deployment === "all" || deploymentKey === deployment || (deployment === "connected" && deploymentKey === "deployed");
            const updatedTime = new Date(website.updatedAt || 0).getTime();
            const matchesActivity = !activityDays || (updatedTime > 0 && now - updatedTime <= activityDays * 86400000);
            return matchesQuery && matchesStatus && matchesIndustry && matchesDeployment && matchesActivity;
        });
        return [...result].sort((a, b) => sort === "name" ? a.name.localeCompare(b.name) : sort === "created" ? new Date(b.createdAt || 0) - new Date(a.createdAt || 0) : sort === "industry" ? a.industry.localeCompare(b.industry) : sort === "status" ? a.status.localeCompare(b.status) : new Date(b.updatedAt || 0) - new Date(a.updatedAt || 0));
    }, [query, sort, status, industry, deployment, activity, websiteCards]);

    const hasFilters = Boolean(query.trim()) || status !== "all" || industry !== "all" || deployment !== "all" || activity !== "all" || sort !== "recent";
    const resetFilters = () => { setQuery(""); setStatus("all"); setIndustry("all"); setDeployment("all"); setActivity("all"); setSort("recent"); };
    const perPage = viewMode === "list" ? 10 : 8;
    const totalPages = Math.max(1, Math.ceil(filteredWebsites.length / perPage));
    const pagedWebsites = filteredWebsites.slice((page - 1) * perPage, page * perPage);
    const selectedCount = selectedWebsiteIds.length;
    const allVisibleSelected = pagedWebsites.length > 0 && pagedWebsites.every((website) => selectedWebsiteIds.includes(website.id));
    const toggleWebsiteSelection = (websiteId) => setSelectedWebsiteIds((current) => current.includes(websiteId) ? current.filter((id) => id !== websiteId) : [...current, websiteId]);
    const toggleVisibleSelection = () => setSelectedWebsiteIds((current) => {
        const visibleIds = pagedWebsites.map((website) => website.id);
        return allVisibleSelected ? current.filter((id) => !visibleIds.includes(id)) : [...new Set([...current, ...visibleIds])];
    });
    const runBulkAction = async (action) => {
        if (!selectedCount || bulkActionPending) return;
        const labels = { duplicate: "Duplicate selected websites?", disconnect: "Disconnect selected live sites?", delete: "Delete selected websites?" };
        const messages = { duplicate: "Cosmic will create private draft copies of every selected website until your plan limit is reached.", disconnect: "Deployment credentials will be cleared. Website content and domains will remain in Cosmic CMS.", delete: "This permanently removes every selected website and all of their pages." };
        const confirmed = await confirmCosmicAction({ title: labels[action], message: messages[action], confirmLabel: action === "delete" ? `Delete ${selectedCount}` : `${action.charAt(0).toUpperCase()}${action.slice(1)} ${selectedCount}`, tone: action === "delete" ? "error" : "info" });
        if (!confirmed) return;
        setBulkActionPending(true);
        try {
            const response = await axios.post(route("websites.bulk-action"), { action, website_ids: selectedWebsiteIds });
            const result = response.data || {};
            const partial = Number(result.failed_count || 0) > 0;
            showCosmicNotification({ title: partial ? "Bulk action partially completed" : "Bulk action completed", message: `${result.completed_count || 0} completed${partial ? ` · ${result.failed_count} failed` : ""}.`, tone: partial ? "warning" : "success" });
            setSelectedWebsiteIds([]);
            router.reload({ only: ["websites", "dashboard"], preserveScroll: true, preserveState: true });
        } catch (error) {
            showCosmicNotification({ title: "Bulk action failed", message: error.response?.data?.message || error.response?.data?.errors?.website_ids?.[0] || "The selected websites could not be updated.", tone: "error" });
        } finally {
            setBulkActionPending(false);
        }
    };
    const openCreate = () => locked ? setIsUpgradeModalOpen(true) : setIsCreateModalOpen(true);
    const deleteWebsite = async (website) => { if (!await confirmCosmicAction({ title: `Delete ${website.name}?`, message: "This permanently removes the website and all of its pages from Cosmic CMS.", confirmLabel: "Delete website", tone: "error" })) return; router.delete(route("websites.destroy", website.id), { preserveScroll: true }); };
    const duplicateWebsite = async (website) => { if (locked) { setIsUpgradeModalOpen(true); return; } if (!await confirmCosmicAction({ title: `Duplicate ${website.name}?`, message: "Cosmic will create a draft copy with its pages, content, theme, blog posts, header, and footer.", confirmLabel: "Create draft copy", tone: "info" })) return; router.post(route("websites.duplicate", website.id), {}, { preserveScroll: true, onError: (errors) => showCosmicNotification({ title: "Website could not be duplicated", message: errors.website_limit || "Please review your current plan allowance.", tone: "error" }) }); };
    const transferOwnership = (website) => setTransferWebsite(website);
    const submitTransferOwnership = async (recipientEmail) => new Promise((resolve, reject) => {
        router.post(route("websites.transfer-ownership", transferWebsite.id), { recipient_email: recipientEmail }, {
            preserveScroll: true,
            onSuccess: () => { setTransferWebsite(null); resolve(); },
            onError: (errors) => reject(new Error(errors.recipient_email || "Please verify the recipient account and plan allowance.")),
        });
    });
    const downloadConnector = (website) => window.location.assign(route("websites.deployment-connector.download", website.id));
    const connectLiveSite = async (website) => { try { const response = await axios.post(route("websites.deployment-connector.verify", website.id)); showCosmicNotification({ title: "Live site connected", message: response.data.message, tone: "success" }); router.reload({ only: ["websites", "dashboard"], preserveScroll: true, preserveState: true }); } catch (error) { showCosmicNotification({ title: "Connection failed", message: error.response?.data?.message || "The live site connector could not be verified.", tone: "error" }); } };
    const pushLiveUpdate = async (website) => { if (!await confirmCosmicAction({ title: "Push to live?", message: `All published pages for ${website.name} will be sent to its connected live site.`, confirmLabel: "Push to live", tone: "info" })) return; try { const response = await axios.post(route("websites.deployment-connector.push", website.id)); showCosmicNotification({ title: "Live site updated", message: response.data.message, tone: "success" }); router.reload({ only: ["websites", "dashboard"], preserveScroll: true, preserveState: true }); } catch (error) { showCosmicNotification({ title: "Live update failed", message: error.response?.data?.message || "The live update could not be pushed.", tone: "error" }); } };

    return <section>
        <p className="text-sm font-medium text-violet-300">{isAgency ? "Agency workspace" : "Personal workspace"}</p>
        <div className="mt-2"><h1 className="text-3xl font-semibold tracking-tight text-white">{isAgency ? "Agency Websites" : "My Website"}</h1><p className="mt-2 text-sm text-slate-400">{isAgency ? "Manage every client website from one command center." : "Build, manage, and publish your business website."}</p></div>
        <div className="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-5">{[["Websites", summary.websites], ["Pages", summary.pages], ["Published", summary.published], ["Live connected", summary.connected], ["Credits", Number(summary.credits || 0).toLocaleString()]].map(([label, value]) => <div key={label} className="rounded-2xl border border-white/10 bg-white/[0.035] p-4"><p className="text-xs font-medium uppercase tracking-[0.14em] text-slate-500">{label}</p><p className="mt-2 text-2xl font-semibold text-white">{value}</p></div>)}</div>
        <div className="mt-6"><WebsiteToolbar query={query} onQueryChange={setQuery} onCreate={openCreate} planCapabilities={capabilities} /></div>
        <div className="mt-3 flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between"><div className="flex flex-wrap gap-2">{statusOptions.map(({ key, label, count }) => <button key={key} type="button" onClick={() => setStatus(key)} className={`rounded-lg border px-3 py-1.5 text-xs font-semibold transition ${status === key ? "border-violet-400/40 bg-violet-400/15 text-violet-100" : "border-white/10 bg-white/[0.03] text-slate-400 hover:text-white"}`}>{label}{Number.isInteger(count) ? ` ${count}` : ""}</button>)}</div><div className="flex flex-wrap items-center gap-2"><select value={industry} onChange={(event) => setIndustry(event.target.value)} className="rounded-lg border border-white/10 bg-[#151518] px-3 py-2 text-xs font-semibold text-slate-300"><option value="all">All industries</option>{industries.map((item) => <option key={item} value={item}>{item}</option>)}</select><select value={deployment} onChange={(event) => setDeployment(event.target.value)} className="rounded-lg border border-white/10 bg-[#151518] px-3 py-2 text-xs font-semibold text-slate-300"><option value="all">Any connection</option><option value="deployed">Deployed</option><option value="connected">Connected</option><option value="not_connected">Not connected</option></select><select value={activity} onChange={(event) => setActivity(event.target.value)} className="rounded-lg border border-white/10 bg-[#151518] px-3 py-2 text-xs font-semibold text-slate-300"><option value="all">Any activity</option><option value="7_days">Edited in 7 days</option><option value="30_days">Edited in 30 days</option><option value="90_days">Edited in 90 days</option></select><select value={sort} onChange={(event) => setSort(event.target.value)} className="rounded-lg border border-white/10 bg-[#151518] px-3 py-2 text-xs font-semibold text-slate-300"><option value="recent">Recently edited</option><option value="name">Name A–Z</option><option value="created">Newest created</option><option value="industry">Industry</option><option value="status">Status</option></select><div className="flex rounded-lg border border-white/10 bg-[#151518] p-1"><button type="button" onClick={() => setViewMode("grid")} className={`rounded-md px-2.5 py-1 text-xs ${viewMode === "grid" ? "bg-white/10 text-white" : "text-slate-500"}`}>Grid</button><button type="button" onClick={() => setViewMode("list")} className={`rounded-md px-2.5 py-1 text-xs ${viewMode === "list" ? "bg-white/10 text-white" : "text-slate-500"}`}>List</button></div></div></div>
        <div className="mt-3 flex min-h-8 items-center justify-between gap-3"><p className="text-xs text-slate-500">{hasFilters ? `${filteredWebsites.length} matching ${filteredWebsites.length === 1 ? "website" : "websites"}` : `${websiteCards.length} total ${websiteCards.length === 1 ? "website" : "websites"}`}</p>{hasFilters && <button type="button" onClick={resetFilters} className="rounded-lg border border-white/10 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:border-white/20 hover:text-white">Clear filters</button>}</div>
        {canBulkActions && <div className="mt-3 flex flex-col gap-3 rounded-2xl border border-violet-400/15 bg-violet-400/[0.055] p-3 sm:flex-row sm:items-center sm:justify-between"><label className="flex items-center gap-2 text-xs font-semibold text-violet-100"><input type="checkbox" checked={allVisibleSelected} onChange={toggleVisibleSelection} className="cosmic-site-checkbox" /> Select visible websites <span className="font-normal text-violet-200/60">({selectedCount} selected)</span></label><div className="flex flex-wrap gap-2"><button type="button" disabled={!selectedCount || bulkActionPending} onClick={() => runBulkAction("duplicate")} className="rounded-lg border border-white/10 bg-white/[0.04] px-3 py-2 text-xs font-semibold text-slate-200 disabled:opacity-40">Duplicate</button><button type="button" disabled={!selectedCount || bulkActionPending} onClick={() => runBulkAction("disconnect")} className="rounded-lg border border-white/10 bg-white/[0.04] px-3 py-2 text-xs font-semibold text-slate-200 disabled:opacity-40">Disconnect</button><button type="button" disabled={!selectedCount || bulkActionPending} onClick={() => runBulkAction("delete")} className="rounded-lg border border-rose-400/20 bg-rose-400/[0.08] px-3 py-2 text-xs font-semibold text-rose-200 disabled:opacity-40">Delete</button>{selectedCount > 0 && <button type="button" disabled={bulkActionPending} onClick={() => setSelectedWebsiteIds([])} className="rounded-lg px-3 py-2 text-xs font-semibold text-slate-400 hover:text-white">Clear</button>}</div></div>}
        {locked && <div className="mt-4 flex flex-col gap-3 rounded-2xl border border-amber-300/15 bg-amber-300/[0.06] p-4 sm:flex-row sm:items-center sm:justify-between"><div><p className="text-sm font-semibold text-amber-100">{capabilities.site_count || websites.length} of {capabilities.max_sites_label || 1} websites used</p><p className="mt-1 text-xs text-amber-100/65">{capabilities.upgrade_message}</p></div><button type="button" onClick={() => setIsUpgradeModalOpen(true)} className="rounded-xl border border-amber-200/20 bg-amber-200/10 px-3 py-2 text-xs font-semibold text-amber-50">View upgrade options</button></div>}
        <div className={`mt-4 grid gap-4 ${viewMode === "grid" ? "xl:grid-cols-2" : "grid-cols-1"}`}>{pagedWebsites.map((website) => <div key={website.id} className="relative">{canBulkActions && <label className="cosmic-site-select absolute z-10 flex cursor-pointer items-center justify-center"><input type="checkbox" checked={selectedWebsiteIds.includes(website.id)} onChange={() => toggleWebsiteSelection(website.id)} aria-label={`Select ${website.name}`} className="cosmic-site-checkbox" /></label>}<WebsiteCard website={website} viewMode={viewMode} onEdit={(item) => router.visit(route("pages.index", item.id))} onDuplicate={duplicateWebsite} onDelete={deleteWebsite} onDownloadConnector={downloadConnector} onConnectLiveSite={connectLiveSite} onPushLiveUpdate={pushLiveUpdate} onTransferOwnership={transferOwnership} /></div>)}</div>
        {!pagedWebsites.length && <div className="mt-6"><WebsiteEmptyState query={query || (status !== "all" ? status : industry !== "all" ? industry : deployment !== "all" ? deployment : activity !== "all" ? activity : "")} /></div>}
        {filteredWebsites.length > perPage && <div className="mt-6 flex items-center justify-between"><p className="text-xs text-slate-500">Showing {(page - 1) * perPage + 1}–{Math.min(page * perPage, filteredWebsites.length)} of {filteredWebsites.length}</p><div className="flex gap-2"><button type="button" disabled={page === 1} onClick={() => setPage((value) => Math.max(1, value - 1))} className="rounded-lg border border-white/10 px-3 py-2 text-xs text-slate-300 disabled:opacity-40">Previous</button><button type="button" disabled={page === totalPages} onClick={() => setPage((value) => Math.min(totalPages, value + 1))} className="rounded-lg border border-white/10 px-3 py-2 text-xs text-slate-300 disabled:opacity-40">Next</button></div></div>}
        <NewWebsiteModal open={isCreateModalOpen} onClose={() => setIsCreateModalOpen(false)} />
        <UpgradePlanModal open={isUpgradeModalOpen} onClose={() => setIsUpgradeModalOpen(false)} capabilities={capabilities} />
        <TransferOwnershipModal open={Boolean(transferWebsite)} website={transferWebsite} onClose={() => setTransferWebsite(null)} onSubmit={submitTransferOwnership} />
    </section>;
}
