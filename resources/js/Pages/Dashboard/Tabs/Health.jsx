import { useEffect, useMemo, useState } from "react";
import axios from "axios";

const categoryMeta = {
    content: { label: "Content", icon: "▤", description: "Published content and entry readiness" },
    links: { label: "Links", icon: "↗", description: "CTA and destination checks" },
    seo: { label: "SEO", icon: "◎", description: "Search metadata on dynamic content" },
    forms: { label: "Forms", icon: "✉", description: "Inquiry routing and form readiness" },
    media: { label: "Media", icon: "▧", description: "Image accessibility and file weight" },
    commerce: { label: "Commerce", icon: "◇", description: "Store, payments, products and shipping" },
    publishing: { label: "Publishing", icon: "↑", description: "Drafts, preview and deployment status" },
};

const severityMeta = {
    critical: { label: "Critical", dot: "bg-rose-400", badge: "border-rose-400/20 bg-rose-400/10 text-rose-200", card: "border-rose-400/20 bg-rose-400/[0.055]" },
    warning: { label: "Warning", dot: "bg-amber-300", badge: "border-amber-300/20 bg-amber-300/10 text-amber-100", card: "border-amber-300/15 bg-amber-300/[0.045]" },
    info: { label: "Info", dot: "bg-sky-300", badge: "border-sky-300/20 bg-sky-300/10 text-sky-100", card: "border-sky-300/15 bg-sky-300/[0.04]" },
    passed: { label: "Passed", dot: "bg-emerald-300", badge: "border-emerald-300/20 bg-emerald-300/10 text-emerald-100", card: "border-white/10 bg-white/[0.025]" },
};

const scoreTone = (score) => {
    if (score >= 90) return "text-emerald-300";
    if (score >= 75) return "text-cyan-300";
    if (score >= 55) return "text-amber-200";
    return "text-rose-300";
};

const statusCopy = (health) => {
    if (!health) return { title: "Not scanned yet", detail: "Choose a website to run a deterministic health check." };
    if (health.status === "needs_attention") return { title: "Needs attention", detail: "Resolve critical issues before relying on this website for launch or sales." };
    if (health.status === "ready_with_warnings") return { title: "Ready with warnings", detail: "No critical blockers were found. Review the warnings before launch." };
    return { title: "Ready to publish", detail: "No critical issues or warnings were found in the current website data." };
};

function SummaryPill({ label, value, tone }) {
    return <div className={`rounded-2xl border px-4 py-3 ${tone}`}><p className="text-[10px] font-bold uppercase tracking-[0.16em] opacity-70">{label}</p><p className="mt-1 text-xl font-semibold">{value}</p></div>;
}

export default function Health({ websites = [] }) {
    const availableWebsites = useMemo(() => (websites || []).map((website) => ({ id: Number(website.id), name: website.name || "Untitled Website", domain: website.domain || null })), [websites]);
    const requestedWebsite = typeof window !== "undefined" ? Number(new URLSearchParams(window.location.search).get("website") || 0) : 0;
    const [websiteId, setWebsiteId] = useState(() => requestedWebsite || Number(availableWebsites[0]?.id || 0));
    const [payload, setPayload] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState("");
    const [category, setCategory] = useState("all");

    useEffect(() => {
        if (!availableWebsites.length) return;
        if (!availableWebsites.some((website) => website.id === Number(websiteId))) setWebsiteId(availableWebsites[0].id);
    }, [availableWebsites, websiteId]);

    useEffect(() => {
        if (!websiteId || typeof window === "undefined") return;
        const url = new URL(window.location.href);
        url.searchParams.set("tab", "health");
        url.searchParams.set("website", String(websiteId));
        window.history.replaceState(window.history.state, "", url);
    }, [websiteId]);

    const runHealthCheck = async () => {
        if (!websiteId) return;
        setLoading(true);
        setError("");
        try {
            const { data } = await axios.get(route("websites.health.show", websiteId));
            setPayload(data);
        } catch (requestError) {
            setPayload(null);
            setError(requestError.response?.data?.message || "Website health could not be scanned right now.");
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { runHealthCheck(); }, [websiteId]);

    const health = payload?.health;
    const summary = health?.summary || { critical: 0, warning: 0, info: 0, passed: 0 };
    const status = statusCopy(health);
    const categories = health?.categories || {};
    const categoryKeys = Object.keys(categoryMeta);
    const visibleCategories = category === "all" ? categoryKeys : [category];

    const fixHref = (finding) => {
        const area = finding?.fix?.area;
        const params = finding?.fix?.params || {};
        if (!area || !websiteId) return null;
        if (area === "settings") return route("dashboard", { tab: "settings", website: websiteId });
        if (area === "media") return route("dashboard", { tab: "media", website: websiteId });
        if (area === "builder" && params.page_id) return route("pages.builder", params.page_id);
        if (area === "content") return `${route("pages.index", websiteId)}?workspace=posts`;
        if (area === "commerce" || area === "commerce_settings") return `${route("pages.index", websiteId)}?workspace=shop`;
        return route("pages.index", websiteId);
    };

    if (!availableWebsites.length) {
        return <section><p className="text-sm font-medium text-violet-300">Website Health</p><h1 className="mt-2 text-3xl font-semibold tracking-tight text-white">Health Center</h1><div className="mt-6 rounded-3xl border border-white/10 bg-white/[0.03] p-8 text-center"><p className="text-lg font-semibold text-white">Create a website first</p><p className="mt-2 text-sm text-slate-400">Health checks are scoped to one website at a time.</p></div></section>;
    }

    return <section id="cosmic-website-health" data-cosmic-health-center>
        <div className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p className="text-sm font-medium text-violet-300">Website Health</p>
                <h1 className="mt-2 text-3xl font-semibold tracking-tight text-white">Health Center</h1>
                <p className="mt-2 max-w-2xl text-sm text-slate-400">Run a read-only pre-launch audit across content, links, SEO, forms, media, commerce and publishing.</p>
            </div>
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                <select value={websiteId} onChange={(event) => setWebsiteId(Number(event.target.value))} className="min-w-64 rounded-xl border border-white/10 bg-[#151518] px-3 py-2.5 text-sm font-semibold text-slate-200">
                    {availableWebsites.map((website) => <option key={website.id} value={website.id}>{website.name}</option>)}
                </select>
                <button type="button" onClick={runHealthCheck} disabled={loading} className="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-slate-100 disabled:cursor-wait disabled:opacity-60">{loading ? "Scanning…" : "Run check"}</button>
            </div>
        </div>

        {error && <div className="mt-6 rounded-2xl border border-rose-400/20 bg-rose-400/[0.06] p-4 text-sm text-rose-100">{error}</div>}

        <div className="mt-6 grid gap-4 xl:grid-cols-[1.2fr_1fr]">
            <div className="rounded-3xl border border-white/10 bg-gradient-to-br from-white/[0.055] to-white/[0.02] p-6 sm:p-7">
                <div className="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Overall health</p>
                        <div className="mt-2 flex items-end gap-2"><span className={`text-6xl font-semibold tracking-tight ${scoreTone(health?.score ?? 0)}`}>{health?.score ?? "—"}</span>{health && <span className="pb-1 text-lg text-slate-600">/100</span>}</div>
                    </div>
                    <div className="max-w-md sm:text-right"><p className="text-xl font-semibold text-white">{loading && !health ? "Scanning website…" : status.title}</p><p className="mt-1 text-sm leading-6 text-slate-400">{status.detail}</p>{health?.scanned_at && <p className="mt-2 text-[11px] uppercase tracking-[0.12em] text-slate-600">Last scanned {new Date(health.scanned_at).toLocaleString()}</p>}</div>
                </div>
            </div>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-2">
                <SummaryPill label="Critical" value={summary.critical} tone="border-rose-400/15 bg-rose-400/[0.055] text-rose-200" />
                <SummaryPill label="Warnings" value={summary.warning} tone="border-amber-300/15 bg-amber-300/[0.05] text-amber-100" />
                <SummaryPill label="Info" value={summary.info} tone="border-sky-300/15 bg-sky-300/[0.04] text-sky-100" />
                <SummaryPill label="Passed" value={summary.passed} tone="border-emerald-300/15 bg-emerald-300/[0.045] text-emerald-100" />
            </div>
        </div>

        <div className="mt-6 flex gap-2 overflow-x-auto pb-1">
            <button type="button" onClick={() => setCategory("all")} className={`min-w-max rounded-xl border px-3 py-2 text-xs font-semibold ${category === "all" ? "border-violet-400/30 bg-violet-400/15 text-violet-100" : "border-white/10 bg-white/[0.025] text-slate-400 hover:text-white"}`}>All checks</button>
            {categoryKeys.map((key) => { const meta = categoryMeta[key]; const counts = categories[key] || {}; const issues = Number(counts.critical || 0) + Number(counts.warning || 0); return <button key={key} type="button" onClick={() => setCategory(key)} className={`min-w-max rounded-xl border px-3 py-2 text-xs font-semibold ${category === key ? "border-violet-400/30 bg-violet-400/15 text-violet-100" : "border-white/10 bg-white/[0.025] text-slate-400 hover:text-white"}`}>{meta.icon} {meta.label}{issues > 0 ? ` · ${issues}` : ""}</button>; })}
        </div>

        <div className="mt-4 space-y-4">
            {visibleCategories.map((key) => {
                const meta = categoryMeta[key];
                const data = categories[key] || { critical: 0, warning: 0, info: 0, passed: 0, findings: [] };
                const findings = data.findings || [];
                return <article key={key} className="overflow-hidden rounded-3xl border border-white/10 bg-white/[0.025]">
                    <header className="flex flex-col gap-3 border-b border-white/10 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div><div className="flex items-center gap-2"><span className="text-violet-300">{meta.icon}</span><h2 className="font-semibold text-white">{meta.label}</h2></div><p className="mt-1 text-xs text-slate-500">{meta.description}</p></div>
                        <div className="flex gap-2 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500">{data.critical > 0 && <span className="text-rose-300">{data.critical} critical</span>}{data.warning > 0 && <span className="text-amber-200">{data.warning} warning</span>}{data.passed > 0 && <span className="text-emerald-300">{data.passed} passed</span>}</div>
                    </header>
                    <div className="space-y-2 p-3 sm:p-4">
                        {!health && <div className="rounded-2xl border border-white/10 bg-black/10 p-4 text-sm text-slate-500">Run a health check to inspect this category.</div>}
                        {health && !findings.length && <div className="rounded-2xl border border-white/10 bg-black/10 p-4 text-sm text-slate-500">No findings in this category.</div>}
                        {findings.map((finding) => { const severity = severityMeta[finding.status] || severityMeta.info; const href = fixHref(finding); return <div key={finding.id} className={`rounded-2xl border p-4 ${severity.card}`}>
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div className="min-w-0"><div className="flex items-center gap-2"><span className={`h-2 w-2 rounded-full ${severity.dot}`} /><span className={`rounded-full border px-2 py-0.5 text-[9px] font-bold uppercase tracking-[0.14em] ${severity.badge}`}>{severity.label}</span></div><h3 className="mt-2 text-sm font-semibold text-white">{finding.title}</h3><p className="mt-1 max-w-3xl text-xs leading-5 text-slate-400">{finding.message}</p></div>{href && <a href={href} className="min-w-max rounded-xl border border-white/10 bg-white/[0.055] px-3 py-2 text-xs font-semibold text-white transition hover:border-violet-400/30 hover:bg-violet-400/10">Fix issue →</a>}</div>
                        </div>; })}
                    </div>
                </article>;
            })}
        </div>
    </section>;
}
