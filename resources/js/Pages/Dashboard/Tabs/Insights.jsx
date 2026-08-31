import { Link, router } from "@inertiajs/react";
import { useMemo, useState } from "react";
import axios from "axios";

const formatNumber = (value) => new Intl.NumberFormat().format(Number(value || 0));
const formatPercent = (value) => `${Number(value || 0).toFixed(1)}%`;
const formatDuration = (seconds) => {
    const total = Number(seconds || 0);
    if (total < 60) return `${total}s`;
    return `${Math.floor(total / 60)}m ${total % 60}s`;
};

function MetricCard({ label, value, detail, accent = "text-white" }) {
    return <div className="rounded-2xl border border-white/10 bg-white/[0.035] p-4 sm:p-5">
        <p className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{label}</p>
        <p className={`mt-2 text-2xl font-semibold ${accent}`}>{value}</p>
        <p className="mt-1 text-xs text-slate-500">{detail}</p>
    </div>;
}

function LockedPanel({ title, message, showUpgrade = false }) {
    return <div className="rounded-2xl border border-amber-300/15 bg-amber-300/[0.06] p-5">
        <p className="text-sm font-semibold text-amber-100">{title}</p>
        <p className="mt-2 text-sm leading-6 text-amber-100/65">{message}</p>
        {showUpgrade && <Link href={route("credits.index", { family: "agency", plan: "agency_growth", source: "agency-insights" })} className="mt-4 inline-flex rounded-xl bg-white px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-slate-200">Compare Agency plans</Link>}
    </div>;
}

function ModuleTabs({ modules, activeModule, onChange }) {
    return <div className="flex gap-2 overflow-x-auto pb-1">
        {modules.map((module) => {
            const active = module.key === activeModule;
            return <button key={module.key} type="button" onClick={() => onChange(module.key)} className={`min-w-max rounded-xl border px-3 py-2 text-sm font-semibold transition ${active ? "border-violet-400/40 bg-violet-400/10 text-violet-200" : "border-white/10 bg-white/[0.025] text-slate-400 hover:text-white"}`}>
                {module.label}
                {module.status !== "active" && <span className="ml-2 text-[9px] uppercase tracking-[0.14em] text-slate-600">{module.status}</span>}
            </button>;
        })}
    </div>;
}

function AnalyticsTrend({ series = [] }) {
    const visible = series.length > 45 ? series.filter((_, index) => index % Math.ceil(series.length / 30) === 0) : series;
    const max = Math.max(1, ...visible.map((row) => Number(row.page_views || 0)));

    return <div className="mt-5 flex h-44 items-end gap-1 overflow-hidden rounded-xl border border-white/[0.07] bg-black/15 p-4">
        {visible.map((row) => <div key={row.date} className="group relative flex min-w-0 flex-1 items-end" title={`${row.date}: ${formatNumber(row.page_views)} views`}>
            <div className="w-full rounded-t bg-violet-400/55 transition group-hover:bg-violet-300/80" style={{ height: `${Math.max(3, (Number(row.page_views || 0) / max) * 100)}%` }} />
        </div>)}
    </div>;
}

function AnalyticsWorkspace({ insights, filters, setFilters, applyFilters }) {
    const analytics = insights.analytics || {};
    const summary = analytics.summary || {};
    const selectedWebsite = (insights.filters?.websites || []).find((website) => String(website.id) === String(analytics.selected_website_id));

    return <div className="mt-6 space-y-6">
        <div className="rounded-2xl border border-white/10 bg-white/[0.025] p-4 sm:p-5">
            <div className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <h2 className="text-lg font-semibold text-white">Analytics filters</h2>
                    <p className="mt-1 text-sm text-slate-500">Filter the account-wide report or drill into one client website.</p>
                </div>
                <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                    <select value={filters.website} onChange={(event) => setFilters((current) => ({ ...current, website: event.target.value }))} className="rounded-xl border border-white/10 bg-[#151518] px-3 py-2 text-sm text-slate-300 focus:border-violet-400/50 focus:outline-none">
                        <option value="all">All websites</option>
                        {(insights.filters?.websites || []).map((website) => <option key={website.id} value={website.id}>{website.label}</option>)}
                    </select>
                    <select value={filters.period} onChange={(event) => setFilters((current) => ({ ...current, period: event.target.value }))} className="rounded-xl border border-white/10 bg-[#151518] px-3 py-2 text-sm text-slate-300 focus:border-violet-400/50 focus:outline-none">
                        {(insights.period_options || []).map((option) => <option key={option.key} value={option.key}>{option.label}</option>)}
                    </select>
                    {filters.period === "custom" && <input type="date" value={filters.start} onChange={(event) => setFilters((current) => ({ ...current, start: event.target.value }))} className="rounded-xl border border-white/10 bg-[#151518] px-3 py-2 text-sm text-slate-300 focus:border-violet-400/50 focus:outline-none" />}
                    {filters.period === "custom" && <input type="date" value={filters.end} onChange={(event) => setFilters((current) => ({ ...current, end: event.target.value }))} className="rounded-xl border border-white/10 bg-[#151518] px-3 py-2 text-sm text-slate-300 focus:border-violet-400/50 focus:outline-none" />}
                    <button type="button" onClick={applyFilters} disabled={filters.period === "custom" && (!filters.start || !filters.end)} className="rounded-xl bg-violet-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-violet-400 disabled:cursor-not-allowed disabled:opacity-40">Apply filters</button>
                </div>
            </div>
            <p className="mt-4 text-xs text-slate-600">Showing {selectedWebsite?.label || "all websites"} · {analytics.starts_at} to {analytics.ends_at}</p>
        </div>

        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <MetricCard label="Page views" value={formatNumber(summary.page_views)} detail={`${formatNumber(summary.visitors)} visitors`} />
            <MetricCard label="Sessions" value={formatNumber(summary.sessions)} detail={`${formatPercent(summary.engagement_rate)} engaged`} accent="text-cyan-300" />
            <MetricCard label="Conversions" value={formatNumber(summary.conversions)} detail={`${formatPercent(summary.conversion_rate)} conversion rate`} accent="text-emerald-300" />
            <MetricCard label="Avg. session" value={formatDuration(summary.average_session_seconds)} detail={`${analytics.period_days || 0} reporting days`} accent="text-violet-300" />
        </div>

        <div className="rounded-2xl border border-white/10 bg-white/[0.025] p-5">
            <h2 className="text-lg font-semibold text-white">Traffic trend</h2>
            <p className="mt-1 text-sm text-slate-500">Daily page views for the selected website and date range.</p>
            {analytics.has_data ? <AnalyticsTrend series={analytics.series || []} /> : <p className="mt-5 rounded-xl border border-dashed border-white/10 p-8 text-center text-sm text-slate-500">No analytics events have been recorded for this filter yet.</p>}
        </div>

        <div className="rounded-2xl border border-white/10 bg-white/[0.025] p-5">
            <h2 className="text-lg font-semibold text-white">Website performance</h2>
            <p className="mt-1 text-sm text-slate-500">Compare traffic and conversions across the selected Agency websites.</p>
            <div className="mt-4 overflow-x-auto">
                <table className="min-w-full text-left text-sm">
                    <thead><tr className="border-b border-white/10 text-xs uppercase tracking-[0.12em] text-slate-600"><th className="px-3 py-3">Website</th><th className="px-3 py-3">Views</th><th className="px-3 py-3">Visitors</th><th className="px-3 py-3">Sessions</th><th className="px-3 py-3">Conversions</th><th className="px-3 py-3">Rate</th></tr></thead>
                    <tbody>{(analytics.top_websites || []).map((website) => <tr key={website.website_id} className="border-b border-white/[0.06] text-slate-300"><td className="px-3 py-4"><p className="font-semibold text-white">{website.website_name}</p><p className="mt-1 text-xs text-slate-600">{website.domain || "No domain"}</p></td><td className="px-3 py-4">{formatNumber(website.page_views)}</td><td className="px-3 py-4">{formatNumber(website.visitors)}</td><td className="px-3 py-4">{formatNumber(website.sessions)}</td><td className="px-3 py-4">{formatNumber(website.conversions)}</td><td className="px-3 py-4">{formatPercent(website.conversion_rate)}</td></tr>)}</tbody>
                </table>
                {!(analytics.top_websites || []).length && <p className="py-8 text-center text-sm text-slate-500">No website analytics match the current filters.</p>}
            </div>
        </div>
    </div>;
}


function StatusBadge({ status }) {
    const classes = {
        new: "border-sky-400/20 bg-sky-400/10 text-sky-300",
        contacted: "border-violet-400/20 bg-violet-400/10 text-violet-300",
        qualified: "border-amber-400/20 bg-amber-400/10 text-amber-300",
        customer: "border-emerald-400/20 bg-emerald-400/10 text-emerald-300",
        lost: "border-rose-400/20 bg-rose-400/10 text-rose-300",
    };
    return <span className={`inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold capitalize ${classes[status] || classes.new}`}>{status || "new"}</span>;
}

function LeadsWorkspace({ insights }) {
    const dashboard = insights.leads_dashboard || {};
    const summary = dashboard.summary || {};
    const [filters, setFilters] = useState({
        website: dashboard.filters?.website || "all",
        status: dashboard.filters?.status || "all",
        source: dashboard.filters?.source || "all",
        query: dashboard.filters?.query || "",
        start: dashboard.filters?.start || "",
        end: dashboard.filters?.end || "",
    });
    const [selected, setSelected] = useState(null);
    const [saving, setSaving] = useState(false);

    const apply = () => {
        const params = { tab: "insights", insight_module: "leads" };
        if (filters.website !== "all") params.lead_website = filters.website;
        if (filters.status !== "all") params.lead_status = filters.status;
        if (filters.source !== "all") params.lead_source = filters.source;
        if (filters.query.trim()) params.lead_query = filters.query.trim();
        if (filters.start) params.lead_start = filters.start;
        if (filters.end) params.lead_end = filters.end;
        router.get(route("dashboard"), params, { only: ["dashboard"], preserveScroll: true, preserveState: true, replace: true });
    };

    const updateStatus = async (lead, leadStatus) => {
        setSaving(true);
        try {
            await axios.patch(route("agency-insights.leads.update", lead.id), { lead_status: leadStatus, notes: lead.notes || null });
            setSelected((current) => current ? { ...current, lead_status: leadStatus } : current);
            router.reload({ only: ["dashboard"], preserveScroll: true });
        } finally {
            setSaving(false);
        }
    };

    const exportUrl = route("agency-insights.leads.export", {
        website: filters.website,
        status: filters.status,
        source: filters.source,
        start: filters.start || undefined,
        end: filters.end || undefined,
    });

    return <div className="mt-6 space-y-6">
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4 xl:grid-cols-6">
            <MetricCard label="Total leads" value={formatNumber(summary.total)} detail={`${formatNumber(summary.new)} new`} />
            <MetricCard label="Contacted" value={formatNumber(summary.contacted)} detail="Follow-up started" accent="text-violet-300" />
            <MetricCard label="Qualified" value={formatNumber(summary.qualified)} detail="Sales-ready" accent="text-amber-300" />
            <MetricCard label="Customers" value={formatNumber(summary.customers)} detail={`${formatPercent(summary.conversion_rate)} converted`} accent="text-emerald-300" />
            <MetricCard label="Lost" value={formatNumber(summary.lost)} detail="Closed without sale" accent="text-rose-300" />
            <MetricCard label="Websites" value={formatNumber(insights.summary?.websites)} detail="Aggregated inbox" accent="text-cyan-300" />
        </div>

        <div className="rounded-2xl border border-white/10 bg-white/[0.025] p-4 sm:p-5">
            <div className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div><h2 className="text-lg font-semibold text-white">Lead filters</h2><p className="mt-1 text-sm text-slate-500">Search and segment inquiries across every managed website.</p></div>
                <div className="grid flex-1 gap-2 sm:grid-cols-2 xl:max-w-5xl xl:grid-cols-6">
                    <input value={filters.query} onChange={(event) => setFilters((current) => ({ ...current, query: event.target.value }))} onKeyDown={(event) => event.key === "Enter" && apply()} placeholder="Name, email, message" className="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-sm text-white placeholder:text-slate-600 focus:border-violet-400/50 focus:outline-none" />
                    <select value={filters.website} onChange={(event) => setFilters((current) => ({ ...current, website: event.target.value }))} className="rounded-xl border border-white/10 bg-[#151518] px-3 py-2 text-sm text-slate-300"><option value="all">All websites</option>{(insights.filters?.websites || []).map((website) => <option key={website.id} value={website.id}>{website.label}</option>)}</select>
                    <select value={filters.status} onChange={(event) => setFilters((current) => ({ ...current, status: event.target.value }))} className="rounded-xl border border-white/10 bg-[#151518] px-3 py-2 text-sm text-slate-300"><option value="all">All statuses</option><option value="new">New</option><option value="contacted">Contacted</option><option value="qualified">Qualified</option><option value="customer">Customer</option><option value="lost">Lost</option></select>
                    <select value={filters.source} onChange={(event) => setFilters((current) => ({ ...current, source: event.target.value }))} className="rounded-xl border border-white/10 bg-[#151518] px-3 py-2 text-sm text-slate-300"><option value="all">All sources</option>{(dashboard.sources || []).map((source) => <option key={source} value={source}>{source.replaceAll("_", " ")}</option>)}</select>
                    <input type="date" value={filters.start} onChange={(event) => setFilters((current) => ({ ...current, start: event.target.value }))} className="rounded-xl border border-white/10 bg-[#151518] px-3 py-2 text-sm text-slate-300" />
                    <input type="date" value={filters.end} onChange={(event) => setFilters((current) => ({ ...current, end: event.target.value }))} className="rounded-xl border border-white/10 bg-[#151518] px-3 py-2 text-sm text-slate-300" />
                </div>
            </div>
            <div className="mt-4 flex flex-wrap gap-2"><button type="button" onClick={apply} className="rounded-xl bg-violet-500 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-400">Apply filters</button><a href={exportUrl} className="rounded-xl border border-white/10 bg-white/[0.04] px-4 py-2 text-sm font-semibold text-slate-300 hover:text-white">Export CSV</a></div>
        </div>

        <div className="rounded-2xl border border-white/10 bg-white/[0.025] p-4 sm:p-5">
            <div><h2 className="text-lg font-semibold text-white">Agency lead inbox</h2><p className="mt-1 text-sm text-slate-500">Up to 250 newest leads matching the active filters.</p></div>
            <div className="mt-4 overflow-x-auto">
                <table className="min-w-full text-left text-sm"><thead><tr className="border-b border-white/10 text-xs uppercase tracking-[0.12em] text-slate-600"><th className="px-3 py-3">Lead</th><th className="px-3 py-3">Website</th><th className="px-3 py-3">Source</th><th className="px-3 py-3">Status</th><th className="px-3 py-3">Received</th><th className="px-3 py-3"></th></tr></thead><tbody>{(dashboard.items || []).map((lead) => <tr key={lead.id} className="border-b border-white/[0.06] text-slate-300"><td className="px-3 py-4"><p className="font-semibold text-white">{lead.name}</p><p className="mt-1 text-xs text-slate-500">{lead.email}{lead.phone ? ` · ${lead.phone}` : ""}</p></td><td className="px-3 py-4"><p>{lead.website_name}</p><p className="mt-1 text-xs text-slate-600">{lead.industry}</p></td><td className="px-3 py-4 capitalize">{(lead.source || "contact_form").replaceAll("_", " ")}</td><td className="px-3 py-4"><StatusBadge status={lead.lead_status} /></td><td className="px-3 py-4"><p>{lead.received_label}</p><p className="mt-1 text-xs text-slate-600">{lead.received_at ? new Date(lead.received_at).toLocaleDateString() : "—"}</p></td><td className="px-3 py-4"><button type="button" onClick={() => setSelected(lead)} className="rounded-lg border border-white/10 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:text-white">View</button></td></tr>)}</tbody></table>
                {!(dashboard.items || []).length && <p className="py-10 text-center text-sm text-slate-500">No leads match the current filters.</p>}
            </div>
        </div>

        {selected && <div className="fixed inset-0 z-50 flex justify-end bg-black/65" onClick={() => setSelected(null)}><aside className="h-full w-full max-w-xl overflow-y-auto border-l border-white/10 bg-[#111114] p-6 shadow-2xl" onClick={(event) => event.stopPropagation()}><div className="flex items-start justify-between gap-4"><div><p className="text-xs font-semibold uppercase tracking-[0.14em] text-violet-300">Lead details</p><h2 className="mt-2 text-2xl font-semibold text-white">{selected.name}</h2><p className="mt-1 text-sm text-slate-500">{selected.website_name} · {selected.industry}</p></div><button type="button" onClick={() => setSelected(null)} className="rounded-lg border border-white/10 px-3 py-2 text-slate-400 hover:text-white">✕</button></div><div className="mt-6 grid gap-3 sm:grid-cols-2"><div className="rounded-xl border border-white/10 bg-white/[0.03] p-4"><p className="text-xs text-slate-600">Email</p><p className="mt-1 break-all text-sm text-white">{selected.email}</p></div><div className="rounded-xl border border-white/10 bg-white/[0.03] p-4"><p className="text-xs text-slate-600">Phone</p><p className="mt-1 text-sm text-white">{selected.phone || "Not supplied"}</p></div></div><div className="mt-4 rounded-xl border border-white/10 bg-white/[0.03] p-4"><p className="text-xs text-slate-600">Message</p><p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-300">{selected.message || "No message supplied."}</p></div><div className="mt-6"><p className="text-sm font-semibold text-white">Lead status</p><div className="mt-3 flex flex-wrap gap-2">{["new", "contacted", "qualified", "customer", "lost"].map((status) => <button key={status} type="button" disabled={saving} onClick={() => updateStatus(selected, status)} className={`rounded-xl border px-3 py-2 text-sm font-semibold capitalize transition ${selected.lead_status === status ? "border-violet-400/40 bg-violet-400/10 text-violet-200" : "border-white/10 text-slate-400 hover:text-white"}`}>{status}</button>)}</div></div><div className="mt-6 border-t border-white/10 pt-5 text-xs text-slate-600"><p>Source: {(selected.source || "contact_form").replaceAll("_", " ")}</p><p className="mt-1">Received: {selected.received_at ? new Date(selected.received_at).toLocaleString() : "—"}</p></div></aside></div>}
    </div>;
}


function formatMoney(minor, currency = "USD") {
    try {
        return new Intl.NumberFormat(undefined, { style: "currency", currency }).format(Number(minor || 0) / 100);
    } catch {
        return `${currency} ${(Number(minor || 0) / 100).toFixed(2)}`;
    }
}

function SalesWorkspace({ insights }) {
    const sales = insights.sales || {};
    const summary = sales.summary || {};
    const currency = sales.currency || "USD";
    const [filters, setFilters] = useState({
        website: sales.filters?.website || "all",
        status: sales.filters?.status || "all",
        source: sales.filters?.source || "all",
        start: sales.filters?.start || "",
        end: sales.filters?.end || "",
    });
    const [showForm, setShowForm] = useState(false);
    const [saving, setSaving] = useState(false);
    const [form, setForm] = useState({ website_id: insights.filters?.websites?.[0]?.id || "", customer_name: "", customer_email: "", product: "", amount: "", currency, status: "completed", source: "manual", occurred_at: new Date().toISOString().slice(0, 10) });

    const apply = () => {
        const params = { tab: "insights", insight_module: "sales" };
        if (filters.website !== "all") params.sale_website = filters.website;
        if (filters.status !== "all") params.sale_status = filters.status;
        if (filters.source !== "all") params.sale_source = filters.source;
        if (filters.start) params.sale_start = filters.start;
        if (filters.end) params.sale_end = filters.end;
        router.get(route("dashboard"), params, { only: ["dashboard"], preserveScroll: true, preserveState: true, replace: true });
    };

    const saveSale = async (event) => {
        event.preventDefault();
        setSaving(true);
        try {
            await axios.post(route("agency-insights.sales.store"), form);
            setShowForm(false);
            setForm((current) => ({ ...current, customer_name: "", customer_email: "", product: "", amount: "" }));
            router.reload({ only: ["dashboard"], preserveScroll: true });
        } finally {
            setSaving(false);
        }
    };

    const maxDaily = Math.max(1, ...(sales.daily || []).map((row) => Number(row.revenue_minor || 0)));
    const exportUrl = route("agency-insights.sales.export", { website: filters.website, status: filters.status, source: filters.source, start: filters.start || undefined, end: filters.end || undefined });

    return <div className="mt-6 space-y-6">
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-6">
            <MetricCard label="Revenue" value={formatMoney(summary.revenue_minor, currency)} detail="Completed minus refunds" accent="text-emerald-300" />
            <MetricCard label="Completed" value={formatNumber(summary.completed_orders)} detail="Paid sales" />
            <MetricCard label="Average order" value={formatMoney(summary.average_order_minor, currency)} detail="Completed orders" accent="text-cyan-300" />
            <MetricCard label="Pending" value={formatNumber(summary.pending_orders)} detail="Awaiting completion" accent="text-amber-300" />
            <MetricCard label="Refunded" value={formatNumber(summary.refunded_orders)} detail="Returned sales" accent="text-rose-300" />
            <MetricCard label="Failed" value={formatNumber(summary.failed_orders)} detail="Unsuccessful events" accent="text-slate-300" />
        </div>

        <div className="rounded-2xl border border-white/10 bg-white/[0.025] p-4 sm:p-5">
            <div className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between"><div><h2 className="text-lg font-semibold text-white">Sales filters</h2><p className="mt-1 text-sm text-slate-500">Review revenue across managed client websites.</p></div><div className="grid flex-1 gap-2 sm:grid-cols-2 xl:max-w-4xl xl:grid-cols-5"><select value={filters.website} onChange={(e) => setFilters((v) => ({ ...v, website: e.target.value }))} className="rounded-xl border border-white/10 bg-[#151518] px-3 py-2 text-sm text-slate-300"><option value="all">All websites</option>{(insights.filters?.websites || []).map((website) => <option key={website.id} value={website.id}>{website.label}</option>)}</select><select value={filters.status} onChange={(e) => setFilters((v) => ({ ...v, status: e.target.value }))} className="rounded-xl border border-white/10 bg-[#151518] px-3 py-2 text-sm text-slate-300"><option value="all">All statuses</option><option value="completed">Completed</option><option value="pending">Pending</option><option value="refunded">Refunded</option><option value="failed">Failed</option></select><select value={filters.source} onChange={(e) => setFilters((v) => ({ ...v, source: e.target.value }))} className="rounded-xl border border-white/10 bg-[#151518] px-3 py-2 text-sm text-slate-300"><option value="all">All sources</option>{(sales.sources || []).map((source) => <option key={source} value={source}>{source.replaceAll("_", " ")}</option>)}</select><input type="date" value={filters.start} onChange={(e) => setFilters((v) => ({ ...v, start: e.target.value }))} className="rounded-xl border border-white/10 bg-[#151518] px-3 py-2 text-sm text-slate-300" /><input type="date" value={filters.end} onChange={(e) => setFilters((v) => ({ ...v, end: e.target.value }))} className="rounded-xl border border-white/10 bg-[#151518] px-3 py-2 text-sm text-slate-300" /></div></div>
            <div className="mt-4 flex flex-wrap gap-2"><button type="button" onClick={apply} className="rounded-xl bg-violet-500 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-400">Apply filters</button><button type="button" onClick={() => setShowForm(true)} className="rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-2 text-sm font-semibold text-emerald-300">Record sale</button><a href={exportUrl} className="rounded-xl border border-white/10 bg-white/[0.04] px-4 py-2 text-sm font-semibold text-slate-300 hover:text-white">Export CSV</a></div>
        </div>

        <div className="grid gap-6 xl:grid-cols-[1.5fr_1fr]">
            <div className="rounded-2xl border border-white/10 bg-white/[0.025] p-5"><h2 className="text-lg font-semibold text-white">Revenue trend</h2><p className="mt-1 text-sm text-slate-500">Completed sales in the selected range.</p><div className="mt-5 flex h-48 items-end gap-1 rounded-xl border border-white/[0.07] bg-black/15 p-4">{(sales.daily || []).map((row) => <div key={row.date} className="group flex min-w-0 flex-1 items-end" title={`${row.date}: ${formatMoney(row.revenue_minor, currency)}`}><div className="w-full rounded-t bg-emerald-400/55 transition group-hover:bg-emerald-300/80" style={{ height: `${Math.max(3, (Number(row.revenue_minor || 0) / maxDaily) * 100)}%` }} /></div>)}{!(sales.daily || []).length && <div className="m-auto text-sm text-slate-600">Revenue appears here after the first completed sale.</div>}</div></div>
            <div className="rounded-2xl border border-white/10 bg-white/[0.025] p-5"><h2 className="text-lg font-semibold text-white">Top websites</h2><p className="mt-1 text-sm text-slate-500">Ranked by completed revenue.</p><div className="mt-4 space-y-3">{(sales.by_website || []).map((row, index) => <div key={row.website_id} className="flex items-center justify-between rounded-xl border border-white/[0.07] bg-white/[0.025] p-3"><div><p className="text-sm font-semibold text-white">{index + 1}. {row.website_name}</p><p className="mt-1 text-xs text-slate-600">{row.orders} orders</p></div><p className="text-sm font-semibold text-emerald-300">{formatMoney(row.revenue_minor, currency)}</p></div>)}{!(sales.by_website || []).length && <p className="py-8 text-center text-sm text-slate-600">No website sales yet.</p>}</div></div>
        </div>

        <div className="rounded-2xl border border-white/10 bg-white/[0.025] p-4 sm:p-5"><div><h2 className="text-lg font-semibold text-white">Recent sales</h2><p className="mt-1 text-sm text-slate-500">Up to 250 newest events matching the active filters.</p></div><div className="mt-4 overflow-x-auto"><table className="min-w-full text-left text-sm"><thead><tr className="border-b border-white/10 text-xs uppercase tracking-[0.12em] text-slate-600"><th className="px-3 py-3">Customer</th><th className="px-3 py-3">Website</th><th className="px-3 py-3">Product</th><th className="px-3 py-3">Amount</th><th className="px-3 py-3">Status</th><th className="px-3 py-3">Date</th></tr></thead><tbody>{(sales.items || []).map((sale) => <tr key={sale.id} className="border-b border-white/[0.06] text-slate-300"><td className="px-3 py-4"><p className="font-semibold text-white">{sale.customer_name}</p><p className="mt-1 text-xs text-slate-600">{sale.customer_email || "No email"}</p></td><td className="px-3 py-4">{sale.website_name}</td><td className="px-3 py-4">{sale.product}</td><td className="px-3 py-4 font-semibold text-emerald-300">{formatMoney(sale.amount_minor, sale.currency)}</td><td className="px-3 py-4 capitalize">{sale.status}</td><td className="px-3 py-4"><p>{sale.occurred_label}</p><p className="mt-1 text-xs text-slate-600">{sale.occurred_at ? new Date(sale.occurred_at).toLocaleDateString() : "—"}</p></td></tr>)}</tbody></table>{!(sales.items || []).length && <p className="py-10 text-center text-sm text-slate-500">No sales match the current filters.</p>}</div></div>

        {showForm && <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" onClick={() => setShowForm(false)}><form onSubmit={saveSale} onClick={(event) => event.stopPropagation()} className="w-full max-w-2xl rounded-2xl border border-white/10 bg-[#111114] p-6"><div className="flex items-start justify-between"><div><p className="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-300">Manual event</p><h2 className="mt-2 text-2xl font-semibold text-white">Record a sale</h2></div><button type="button" onClick={() => setShowForm(false)} className="rounded-lg border border-white/10 px-3 py-2 text-slate-400">✕</button></div><div className="mt-6 grid gap-3 sm:grid-cols-2"><select required value={form.website_id} onChange={(e) => setForm((v) => ({ ...v, website_id: e.target.value }))} className="rounded-xl border border-white/10 bg-[#151518] px-3 py-3 text-sm text-slate-300"><option value="">Select website</option>{(insights.filters?.websites || []).map((website) => <option key={website.id} value={website.id}>{website.label}</option>)}</select><input required type="number" min="0" step="0.01" value={form.amount} onChange={(e) => setForm((v) => ({ ...v, amount: e.target.value }))} placeholder="Amount" className="rounded-xl border border-white/10 bg-black/20 px-3 py-3 text-sm text-white" /><input value={form.customer_name} onChange={(e) => setForm((v) => ({ ...v, customer_name: e.target.value }))} placeholder="Customer name" className="rounded-xl border border-white/10 bg-black/20 px-3 py-3 text-sm text-white" /><input type="email" value={form.customer_email} onChange={(e) => setForm((v) => ({ ...v, customer_email: e.target.value }))} placeholder="Customer email" className="rounded-xl border border-white/10 bg-black/20 px-3 py-3 text-sm text-white" /><input value={form.product} onChange={(e) => setForm((v) => ({ ...v, product: e.target.value }))} placeholder="Product or service" className="rounded-xl border border-white/10 bg-black/20 px-3 py-3 text-sm text-white" /><input required type="date" value={form.occurred_at} onChange={(e) => setForm((v) => ({ ...v, occurred_at: e.target.value }))} className="rounded-xl border border-white/10 bg-[#151518] px-3 py-3 text-sm text-slate-300" /><select value={form.status} onChange={(e) => setForm((v) => ({ ...v, status: e.target.value }))} className="rounded-xl border border-white/10 bg-[#151518] px-3 py-3 text-sm text-slate-300"><option value="completed">Completed</option><option value="pending">Pending</option><option value="refunded">Refunded</option><option value="failed">Failed</option></select><input value={form.currency} onChange={(e) => setForm((v) => ({ ...v, currency: e.target.value.toUpperCase().slice(0, 3) }))} maxLength="3" className="rounded-xl border border-white/10 bg-black/20 px-3 py-3 text-sm text-white" /></div><div className="mt-6 flex justify-end gap-2"><button type="button" onClick={() => setShowForm(false)} className="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-300">Cancel</button><button disabled={saving} className="rounded-xl bg-emerald-500 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{saving ? "Saving…" : "Save sale"}</button></div></form></div>}
    </div>;
}

function ConversionWorkspace({ insights }) {
    const report = insights.conversions || {};
    if (!report.available) return <div className="mt-6"><LockedPanel title="Pro Agency conversion reporting" message="Upgrade to Pro Agency to connect leads, customers, completed sales, revenue per lead, and website conversion performance." showUpgrade /></div>;
    const summary = report.summary || {};
    const currency = report.currency || "USD";
    const money = (minor) => new Intl.NumberFormat(undefined, { style: "currency", currency }).format(Number(minor || 0) / 100);
    const max = Math.max(1, ...(report.funnel || []).map((step) => Number(step.value || 0)));
    const apply = (event) => { event.preventDefault(); const data = new FormData(event.currentTarget); router.get(route("dashboard"), { tab: "insights", insight_module: "conversions", conversion_website: data.get("website"), conversion_start: data.get("start"), conversion_end: data.get("end") }, { only: ["dashboard"], preserveScroll: true, preserveState: true, replace: true }); };
    return <div className="mt-6 space-y-6">
        <form onSubmit={apply} className="grid gap-2 rounded-2xl border border-white/10 bg-white/[0.025] p-4 sm:grid-cols-4"><select name="website" defaultValue={report.filters?.website || "all"} className="rounded-xl border border-white/10 bg-[#151518] px-3 py-2 text-sm text-slate-300"><option value="all">All websites</option>{(insights.filters?.websites || []).map((site) => <option key={site.id} value={site.id}>{site.label}</option>)}</select><input name="start" type="date" defaultValue={report.filters?.start || ""} className="rounded-xl border border-white/10 bg-[#151518] px-3 py-2 text-sm text-slate-300"/><input name="end" type="date" defaultValue={report.filters?.end || ""} className="rounded-xl border border-white/10 bg-[#151518] px-3 py-2 text-sm text-slate-300"/><button className="rounded-xl bg-violet-500 px-4 py-2 text-sm font-semibold text-white">Apply report</button></form>
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4"><MetricCard label="Lead → sale" value={formatPercent(summary.lead_to_sale_rate)} detail={`${formatNumber(summary.sales)} completed sales`} accent="text-violet-300"/><MetricCard label="Qualified → sale" value={formatPercent(summary.qualified_to_sale_rate)} detail={`${formatNumber(summary.qualified)} qualified leads`} accent="text-cyan-300"/><MetricCard label="Revenue per lead" value={money(summary.revenue_per_lead_minor)} detail={`${formatNumber(summary.leads)} leads`} accent="text-emerald-300"/><MetricCard label="Attributed revenue" value={money(summary.revenue_minor)} detail="Completed website sales" accent="text-emerald-300"/></div>
        <div className="grid gap-6 xl:grid-cols-[0.8fr_1.2fr]"><div className="rounded-2xl border border-white/10 bg-white/[0.025] p-5"><h2 className="text-lg font-semibold text-white">Conversion funnel</h2><p className="mt-1 text-sm text-slate-500">Lead progression for the selected period.</p><div className="mt-5 space-y-4">{(report.funnel || []).map((step) => <div key={step.key}><div className="mb-2 flex justify-between text-sm"><span className="text-slate-300">{step.label}</span><span className="font-semibold text-white">{formatNumber(step.value)}</span></div><div className="h-3 overflow-hidden rounded-full bg-white/[0.06]"><div className="h-full rounded-full bg-violet-400/70" style={{width: `${Math.max(step.value ? 4 : 0, (Number(step.value || 0) / max) * 100)}%`}}/></div></div>)}</div></div><div className="rounded-2xl border border-white/10 bg-white/[0.025] p-5"><h2 className="text-lg font-semibold text-white">Website conversion ranking</h2><p className="mt-1 text-sm text-slate-500">Compare leads, sales, rate, and revenue.</p><div className="mt-4 overflow-x-auto"><table className="min-w-full text-left text-sm"><thead><tr className="border-b border-white/10 text-xs uppercase tracking-[0.12em] text-slate-600"><th className="px-3 py-3">Website</th><th className="px-3 py-3">Leads</th><th className="px-3 py-3">Sales</th><th className="px-3 py-3">Rate</th><th className="px-3 py-3">Revenue</th></tr></thead><tbody>{(report.by_website || []).map((row) => <tr key={row.website_id} className="border-b border-white/[0.06] text-slate-300"><td className="px-3 py-4 font-semibold text-white">{row.website_name}</td><td className="px-3 py-4">{row.leads}</td><td className="px-3 py-4">{row.sales}</td><td className="px-3 py-4 text-violet-300">{formatPercent(row.conversion_rate)}</td><td className="px-3 py-4 text-emerald-300">{money(row.revenue_minor)}</td></tr>)}</tbody></table>{!(report.by_website || []).length && <p className="py-8 text-center text-sm text-slate-600">No conversion data in this period.</p>}</div></div></div>
    </div>;
}

function AiInsightsWorkspace({ insights }) {
    const report = insights.ai_insights || {};
    if (!report.available) return <div className="mt-6"><LockedPanel title="Pro Agency AI insights" message="Upgrade to Pro Agency to unlock account-level recommendations powered by leads, sales, analytics, and conversion signals." showUpgrade /></div>;
    const summary = report.summary || {};
    const tone = { high: "border-rose-400/20 bg-rose-400/[0.06] text-rose-200", medium: "border-amber-300/20 bg-amber-300/[0.06] text-amber-100", positive: "border-emerald-400/20 bg-emerald-400/[0.06] text-emerald-200", info: "border-cyan-400/20 bg-cyan-400/[0.06] text-cyan-200" };
    return <div className="mt-6 space-y-6">
        <div className="rounded-2xl border border-violet-400/20 bg-violet-400/[0.06] p-5"><div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><p className="text-xs font-semibold uppercase tracking-[0.14em] text-violet-300">AI Insights Foundation</p><h2 className="mt-2 text-xl font-semibold text-white">Agency performance recommendations</h2><p className="mt-2 max-w-3xl text-sm leading-6 text-slate-400">Rule-backed recommendations generated from current account data, ready for future AI enrichment.</p></div><span className="rounded-full border border-white/10 bg-black/20 px-3 py-1 text-xs text-slate-400">{report.period_label}</span></div></div>
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4"><MetricCard label="Leads" value={formatNumber(summary.leads)} detail={summary.lead_change_percent === null ? "No previous baseline" : `${summary.lead_change_percent >= 0 ? "+" : ""}${summary.lead_change_percent}% vs previous period`} /><MetricCard label="Completed sales" value={formatNumber(summary.sales)} detail="Recorded website sales" accent="text-violet-300"/><MetricCard label="Conversion" value={formatPercent(summary.conversion_rate)} detail="Lead to completed sale" accent="text-cyan-300"/><MetricCard label="Revenue" value={money(summary.revenue_minor)} detail="Attributed website revenue" accent="text-emerald-300"/></div>
        <div className="grid gap-4 lg:grid-cols-2">{(report.recommendations || []).map((item, index) => <div key={`${item.title}-${index}`} className={`rounded-2xl border p-5 ${tone[item.priority] || tone.info}`}><p className="text-xs font-semibold uppercase tracking-[0.14em] opacity-70">{item.priority}</p><h3 className="mt-2 text-lg font-semibold text-white">{item.title}</h3><p className="mt-2 text-sm leading-6 text-slate-300">{item.message}</p><p className="mt-4 text-xs font-semibold uppercase tracking-[0.12em] opacity-80">{item.action}</p></div>)}</div>
        <div className="rounded-2xl border border-white/10 bg-white/[0.025] p-5"><h2 className="text-lg font-semibold text-white">Conversion watchlist</h2><p className="mt-1 text-sm text-slate-500">Websites with at least three leads but no completed sale in the last 30 days.</p><div className="mt-4 overflow-x-auto"><table className="min-w-full text-left text-sm"><thead><tr className="border-b border-white/10 text-xs uppercase tracking-[0.12em] text-slate-600"><th className="px-3 py-3">Website</th><th className="px-3 py-3">Leads</th><th className="px-3 py-3">Sales</th><th className="px-3 py-3">Conversion</th></tr></thead><tbody>{(report.watchlist || []).map((row) => <tr key={row.website_id} className="border-b border-white/[0.06] text-slate-300"><td className="px-3 py-4 font-semibold text-white">{row.website_name}</td><td className="px-3 py-4">{row.leads}</td><td className="px-3 py-4">{row.sales}</td><td className="px-3 py-4 text-amber-200">{formatPercent(row.conversion_rate)}</td></tr>)}</tbody></table>{!(report.watchlist || []).length && <p className="py-8 text-center text-sm text-slate-600">No websites need conversion attention right now.</p>}</div></div>
        <p className="text-xs leading-5 text-slate-600">{report.disclaimer}</p>
    </div>;
}

export default function Insights({ dashboard = {}, onTabChange }) {
    const insights = dashboard.agency_insights || {};
    const capabilities = dashboard.plan_capabilities || {};
    const modules = insights.modules || [];
    const [activeModule, setActiveModule] = useState(() => new URLSearchParams(window.location.search).get("insight_module") || "overview");
    const [query, setQuery] = useState("");
    const [status, setStatus] = useState("all");
    const [filters, setFilters] = useState({
        website: insights.analytics?.selected_website_id || "all",
        period: insights.analytics?.period_key || insights.filters?.default_period || "30d",
        start: insights.analytics?.starts_at || "",
        end: insights.analytics?.ends_at || "",
    });

    const currentModule = modules.find((module) => module.key === activeModule) || modules[0];
    const websites = useMemo(() => {
        const normalized = query.trim().toLowerCase();
        return (insights.websites || []).filter((website) => {
            const matchesQuery = !normalized || [website.name, website.domain, website.industry, website.status, website.deployment_status].join(" ").toLowerCase().includes(normalized);
            const matchesStatus = status === "all" || website.status.toLowerCase() === status || (status === "connected" && website.deployment_status !== "Not connected");
            return matchesQuery && matchesStatus;
        });
    }, [insights.websites, query, status]);

    const applyAnalyticsFilters = () => {
        const params = { tab: "insights", analytics_period: filters.period };
        if (filters.website !== "all") params.analytics_website = filters.website;
        if (filters.period === "custom") {
            params.analytics_start = filters.start;
            params.analytics_end = filters.end;
        }
        router.get(route("dashboard"), params, { only: ["dashboard"], preserveScroll: true, preserveState: true, replace: true });
    };

    if (!insights.available) {
        return <section className="cosmic-insights-page"><p className="text-sm font-medium text-violet-300">Agency workspace</p><h1 className="mt-2 text-3xl font-black tracking-tight text-white">Agency Insights</h1><p className="mt-2 max-w-2xl text-sm text-slate-400">One command center for analytics, leads, sales, and future AI recommendations.</p><div className="mt-6"><LockedPanel title="Growth Agency required" message={insights.reason || "Upgrade to Growth Agency to unlock account-wide insights."} showUpgrade /></div></section>;
    }

    const summary = insights.summary || {};
    const leadChange = summary.lead_change_percent;
    const leadDetail = leadChange === null ? "First leads recorded this period" : `${leadChange >= 0 ? "+" : ""}${leadChange}% vs previous 30 days`;

    return <section className="cosmic-insights-page">
        <div><p className="text-sm font-medium text-violet-300">{capabilities.plan_label || "Agency"}</p><h1 className="mt-2 text-3xl font-black tracking-tight text-white">Agency Insights</h1><p className="mt-2 max-w-2xl text-sm text-slate-400">Monitor delivery, inquiries, and performance across client websites from one account-level workspace.</p></div>
        <div className="mt-6"><ModuleTabs modules={modules} activeModule={activeModule} onChange={setActiveModule} /></div>

        {activeModule === "analytics" && <AnalyticsWorkspace insights={insights} filters={filters} setFilters={setFilters} applyFilters={applyAnalyticsFilters} />}
        {activeModule === "leads" && currentModule?.enabled && <LeadsWorkspace insights={insights} />}
        {activeModule === "sales" && currentModule?.enabled && <SalesWorkspace insights={insights} />}
        {activeModule === "conversions" && <ConversionWorkspace insights={insights} />}
        {activeModule === "ai" && <AiInsightsWorkspace insights={insights} />}

        {!["overview", "analytics", "leads", "sales", "conversions", "ai"].includes(activeModule) && <div className="mt-6"><LockedPanel title={`${currentModule?.label || "Module"} foundation ready`} message={currentModule?.enabled ? `The ${currentModule.label.toLowerCase()} workspace is wired into the Agency Insights shell. Its full dataset and reporting tools arrive in the matching Patch 11 module.` : "Your current plan does not include this module. Upgrade your Agency plan when you need this reporting layer."} /></div>}

        {activeModule === "overview" && <>
            <div className="mt-6 rounded-2xl border border-white/10 bg-white/[0.025] p-4 sm:p-5">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div><p className="text-xs font-semibold uppercase tracking-[0.14em] text-violet-300">Reporting readiness</p><h2 className="mt-2 text-lg font-semibold text-white">Agency Insights setup health</h2><p className="mt-1 text-sm text-slate-500">Confirm that publishing, analytics, lead capture, and sales attribution are feeding the account-level reports.</p></div>
                    <div className="rounded-2xl border border-violet-400/20 bg-violet-400/[0.08] px-5 py-4 text-center"><p className="text-3xl font-semibold text-violet-200">{formatPercent(insights.reporting_health?.score)}</p><p className="mt-1 text-xs text-violet-200/60">{insights.reporting_health?.completed_checks || 0}/{insights.reporting_health?.total_checks || 0} checks ready</p></div>
                </div>
                <div className="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-5">{(insights.reporting_health?.checks || []).map((check) => <div key={check.key} className={`rounded-xl border p-4 ${check.complete ? "border-emerald-400/20 bg-emerald-400/[0.06]" : "border-white/10 bg-black/15"}`}><div className="flex items-center gap-2"><span className={`flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold ${check.complete ? "bg-emerald-400/15 text-emerald-300" : "bg-white/[0.06] text-slate-500"}`}>{check.complete ? "✓" : "·"}</span><p className="text-sm font-semibold text-white">{check.label}</p></div><p className="mt-2 text-xs leading-5 text-slate-500">{check.detail}</p></div>)}</div>
                <p className="mt-4 text-xs text-slate-600">Last refreshed {insights.refreshed_at ? new Date(insights.refreshed_at).toLocaleString() : "just now"}</p>
            </div>
            <div className="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <MetricCard label="Websites" value={formatNumber(summary.websites)} detail={`${formatNumber(summary.published_websites)} published`} />
                <MetricCard label="Live connected" value={formatNumber(summary.connected_websites)} detail={`${formatNumber(summary.deployed_websites)} deployed`} accent="text-cyan-300" />
                <MetricCard label="Active leads" value={formatNumber(summary.leads)} detail={`${formatNumber(summary.unread_leads)} unread`} accent="text-emerald-300" />
                <MetricCard label="Leads · 30 days" value={formatNumber(summary.leads_last_30_days)} detail={leadDetail} accent="text-violet-300" />
            </div>
            <div className="mt-6 rounded-2xl border border-white/10 bg-white/[0.025] p-4 sm:p-5">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"><div><h2 className="text-lg font-semibold text-white">Website performance overview</h2><p className="mt-1 text-sm text-slate-500">Operational status, published pages, and inquiries per website.</p></div><div className="flex flex-col gap-2 sm:flex-row"><input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search websites" className="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-sm text-white placeholder:text-slate-600 focus:border-violet-400/50 focus:outline-none" /><select value={status} onChange={(event) => setStatus(event.target.value)} className="rounded-xl border border-white/10 bg-[#151518] px-3 py-2 text-sm text-slate-300 focus:border-violet-400/50 focus:outline-none"><option value="all">All statuses</option><option value="published">Published</option><option value="draft">Draft</option><option value="connected">Live connected</option></select></div></div>
                <div className="mt-4 overflow-x-auto"><table className="min-w-full text-left text-sm"><thead><tr className="border-b border-white/10 text-xs uppercase tracking-[0.12em] text-slate-600"><th className="px-3 py-3">Website</th><th className="px-3 py-3">Status</th><th className="px-3 py-3">Pages</th><th className="px-3 py-3">Leads</th><th className="px-3 py-3">Live</th></tr></thead><tbody>{websites.map((website) => <tr key={website.id} className="border-b border-white/[0.06] text-slate-300"><td className="px-3 py-4"><button type="button" onClick={() => onTabChange?.("websites")} className="font-semibold text-white hover:text-violet-300">{website.name}</button><p className="mt-1 text-xs text-slate-600">{website.domain}</p></td><td className="px-3 py-4">{website.status}</td><td className="px-3 py-4">{website.published_pages_count}/{website.pages_count}</td><td className="px-3 py-4">{website.leads_count}</td><td className="px-3 py-4">{website.deployment_status}</td></tr>)}</tbody></table>{!websites.length && <p className="py-8 text-center text-sm text-slate-500">No websites match the current filters.</p>}</div>
            </div>
        </>}
    </section>;
}
