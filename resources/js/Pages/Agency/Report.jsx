import { Head, router } from '@inertiajs/react';

const number = new Intl.NumberFormat();
const money = new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD', maximumFractionDigits: 0 });

export default function Report({ report, filters, branding, generated_at }) {
    const summary = report.summary || {};
    const sales = report.sales || {};
    const conversions = report.conversions || {};
    const websites = report.filters?.websites || [];
    const apply = (key, value) => router.get(route('agency-reports.index'), { ...filters, [key]: value || undefined }, { preserveState: true, replace: true });

    return <>
        <Head title={`${branding.agency_name} — Client Report`} />
        <style>{`@media print{.no-print{display:none!important}body{background:#fff!important}.report-shell{box-shadow:none!important;margin:0!important;max-width:none!important}.break-avoid{break-inside:avoid}}`}</style>
        <main className="min-h-screen bg-slate-100 px-4 py-8 text-slate-900 print:bg-white print:p-0">
            <div className="no-print mx-auto mb-5 flex max-w-6xl flex-col gap-3 rounded-2xl bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                <div className="flex flex-wrap gap-3">
                    <select value={filters.website_id || ''} onChange={e=>apply('website_id', e.target.value)} className="rounded-xl border-slate-200 text-sm"><option value="">All websites</option>{websites.map(site=><option key={site.id} value={site.id}>{site.label}</option>)}</select>
                    <select value={filters.days || 30} onChange={e=>apply('days', Number(e.target.value))} className="rounded-xl border-slate-200 text-sm"><option value={7}>Last 7 days</option><option value={30}>Last 30 days</option><option value={90}>Last 90 days</option></select>
                </div>
                <div className="flex gap-2"><button onClick={()=>history.back()} className="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold">Back</button><button onClick={()=>window.print()} className="rounded-xl px-4 py-2 text-sm font-semibold text-white" style={{backgroundColor:branding.primary_color}}>Print / Save PDF</button></div>
            </div>

            <article className="report-shell mx-auto max-w-6xl overflow-hidden rounded-3xl bg-white shadow-xl print:rounded-none">
                <header className="p-8 text-white md:p-12" style={{background:`linear-gradient(135deg, ${branding.primary_color}, ${branding.accent_color})`}}>
                    <div className="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-4">{branding.logo_url?<img src={branding.logo_url} alt="" className="h-14 w-14 rounded-xl bg-white object-contain p-2"/>:<div className="flex h-14 w-14 items-center justify-center rounded-xl bg-white/20 text-xl font-bold">{branding.agency_name.slice(0,2).toUpperCase()}</div>}<div><h1 className="text-2xl font-bold md:text-3xl">{branding.agency_name}</h1>{branding.tagline&&<p className="mt-1 text-sm text-white/75">{branding.tagline}</p>}</div></div>
                        <div className="text-left sm:text-right"><p className="text-xs font-semibold uppercase tracking-[.25em] text-white/70">Performance report</p><p className="mt-2 text-sm">Generated {new Date(generated_at).toLocaleString()}</p></div>
                    </div>
                </header>

                <div className="space-y-10 p-8 md:p-12">
                    <section className="break-avoid"><h2 className="text-xl font-bold">Executive summary</h2><p className="mt-2 text-sm text-slate-500">A consolidated view of website activity, leads, sales, and conversion performance.</p><div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4"><Metric label="Websites" value={number.format(summary.websites||0)}/><Metric label="Published pages" value={number.format(summary.published_pages||0)}/><Metric label="Active leads" value={number.format(summary.leads||0)}/><Metric label="Unread leads" value={number.format(summary.unread_leads||0)}/></div></section>

                    <section className="break-avoid"><div className="flex items-end justify-between"><div><h2 className="text-xl font-bold">Sales performance</h2><p className="mt-2 text-sm text-slate-500">Revenue and transaction activity across the selected portfolio.</p></div></div><div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4"><Metric label="Revenue" value={money.format(sales.summary?.revenue||0)}/><Metric label="Orders" value={number.format(sales.summary?.orders||0)}/><Metric label="Average order" value={money.format(sales.summary?.average_order_value||0)}/><Metric label="Conversion rate" value={`${Number(conversions.summary?.conversion_rate||sales.summary?.conversion_rate||0).toFixed(1)}%`}/></div></section>

                    <section><h2 className="text-xl font-bold">Website portfolio</h2><div className="mt-5 overflow-hidden rounded-2xl border border-slate-200"><table className="w-full text-left text-sm"><thead className="bg-slate-50 text-xs uppercase tracking-wider text-slate-500"><tr><th className="px-5 py-3">Website</th><th className="px-5 py-3">Status</th><th className="px-5 py-3">Pages</th><th className="px-5 py-3">Leads</th></tr></thead><tbody className="divide-y divide-slate-100">{(report.websites||[]).map(site=><tr key={site.id}><td className="px-5 py-4"><p className="font-semibold">{site.name}</p><p className="text-xs text-slate-500">{site.domain}</p></td><td className="px-5 py-4">{site.status}</td><td className="px-5 py-4">{site.published_pages_count}/{site.pages_count}</td><td className="px-5 py-4">{site.leads_count}</td></tr>)}</tbody></table></div></section>

                    <footer className="flex flex-col gap-3 border-t border-slate-200 pt-6 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between"><div>{branding.support_email&&<span>{branding.support_email}</span>}{branding.website_url&&<span className="ml-3">{branding.website_url}</span>}</div>{!branding.remove_cosmic_branding&&<span>Built with Cosmic CMS</span>}</footer>
                </div>
            </article>
        </main>
    </>;
}
function Metric({label,value}){return <div className="rounded-2xl border border-slate-200 p-5"><p className="text-xs font-semibold uppercase tracking-wider text-slate-500">{label}</p><p className="mt-3 text-2xl font-bold">{value}</p></div>}
