import { useMemo, useState } from "react";

const periodOptions = [
    { key: "7d", days: 7, label: "7 days" },
    { key: "30d", days: 30, label: "30 days" },
    { key: "90d", days: 90, label: "90 days" },
];

const metricTones = {
    visitors: "emerald",
    page_views: "blue",
    sessions: "violet",
    conversion_rate: "amber",
};

const iconPaths = {
    visitors: <><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></>,
    page_views: <><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></>,
    sessions: <><path d="M3 3v18h18"/><path d="m7 16 4-5 3 3 5-7"/></>,
    conversion_rate: <><circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.2 2.2 4.8-5"/></>,
};

function formatNumber(value) {
    return new Intl.NumberFormat().format(Number(value || 0));
}

function MetricCard({ metricKey, label, value, helper }) {
    const tone = metricTones[metricKey] || "emerald";
    return (
        <article className={`cosmic-performance-metric cosmic-performance-metric--${tone}`}>
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="cosmic-performance-label">{label}</p>
                    <p className="cosmic-performance-value">{value}</p>
                    <p className="cosmic-performance-helper">{helper}</p>
                </div>
                <span className="cosmic-performance-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round">{iconPaths[metricKey]}</svg>
                </span>
            </div>
        </article>
    );
}

function TrafficChart({ series }) {
    const width = 900;
    const height = 250;
    const padX = 12;
    const padY = 18;
    const values = series.map((row) => Number(row.page_views || 0));
    const max = Math.max(...values, 1);
    const points = series.map((row, index) => {
        const x = series.length <= 1 ? width / 2 : padX + (index / (series.length - 1)) * (width - padX * 2);
        const y = height - padY - (Number(row.page_views || 0) / max) * (height - padY * 2);
        return [x, y];
    });
    const line = points.map(([x, y]) => `${x.toFixed(1)},${y.toFixed(1)}`).join(" ");
    const area = points.length ? `${padX},${height - padY} ${line} ${width - padX},${height - padY}` : "";

    if (!series.some((row) => Number(row.page_views || 0) > 0)) {
        return (
            <div className="cosmic-chart-empty">
                <span className="cosmic-chart-empty-icon">↗</span>
                <p className="font-semibold">Analytics are ready</p>
                <p className="mt-1 max-w-md text-xs">Traffic will appear here as your published websites receive tracked visits.</p>
            </div>
        );
    }

    return (
        <div className="cosmic-traffic-chart" aria-label="Website page views over time">
            <svg viewBox={`0 0 ${width} ${height}`} role="img" preserveAspectRatio="none">
                <defs>
                    <linearGradient id="cosmicTrafficFill" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor="rgb(var(--dash-primary-rgb))" stopOpacity=".22" />
                        <stop offset="100%" stopColor="rgb(var(--dash-primary-rgb))" stopOpacity="0" />
                    </linearGradient>
                </defs>
                {[0.25, 0.5, 0.75].map((ratio) => <line key={ratio} x1="0" x2={width} y1={height * ratio} y2={height * ratio} className="cosmic-chart-grid" />)}
                {area && <polygon points={area} fill="url(#cosmicTrafficFill)" />}
                {line && <polyline points={line} className="cosmic-chart-line" fill="none" vectorEffect="non-scaling-stroke" />}
            </svg>
            <div className="cosmic-chart-axis">
                <span>{series[0]?.date || ""}</span>
                <span>{series[Math.floor(series.length / 2)]?.date || ""}</span>
                <span>{series[series.length - 1]?.date || ""}</span>
            </div>
        </div>
    );
}

export default function OverviewPerformance({ analytics = {}, onOpenInsights }) {
    const [period, setPeriod] = useState("30d");
    const selectedDays = periodOptions.find((option) => option.key === period)?.days || 30;
    const allSeries = Array.isArray(analytics.series) ? analytics.series : [];
    const series = useMemo(() => allSeries.slice(-selectedDays), [allSeries, selectedDays]);
    const summary = useMemo(() => {
        const result = series.reduce((acc, row) => {
            acc.page_views += Number(row.page_views || 0);
            acc.visitors += Number(row.visitors || 0);
            acc.sessions += Number(row.sessions || 0);
            acc.conversions += Number(row.conversions || 0);
            return acc;
        }, { page_views: 0, visitors: 0, sessions: 0, conversions: 0 });
        result.conversion_rate = result.sessions ? (result.conversions / result.sessions) * 100 : 0;
        return result;
    }, [series]);

    const topWebsites = Array.isArray(analytics.top_websites) ? analytics.top_websites.slice(0, 5) : [];

    return (
        <section className="space-y-4" aria-labelledby="performance-heading">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p className="cosmic-section-kicker">Performance</p>
                    <h2 id="performance-heading" className="cosmic-section-title">Your websites at a glance</h2>
                </div>
                <div className="cosmic-period-switch" aria-label="Analytics period">
                    {periodOptions.map((option) => (
                        <button key={option.key} type="button" onClick={() => setPeriod(option.key)} className={period === option.key ? "is-active" : ""}>{option.label}</button>
                    ))}
                </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <MetricCard metricKey="visitors" label="Visitors" value={formatNumber(summary.visitors)} helper={`Last ${selectedDays} days`} />
                <MetricCard metricKey="page_views" label="Page Views" value={formatNumber(summary.page_views)} helper={`Across ${formatNumber(analytics.top_websites?.length || 0)} tracked sites`} />
                <MetricCard metricKey="sessions" label="Sessions" value={formatNumber(summary.sessions)} helper="Tracked browsing sessions" />
                <MetricCard metricKey="conversion_rate" label="Conversion Rate" value={`${summary.conversion_rate.toFixed(1)}%`} helper={`${formatNumber(summary.conversions)} tracked conversions`} />
            </div>

            <div className="grid gap-4 xl:grid-cols-[minmax(0,1.55fr)_minmax(300px,.75fr)]">
                <article className="cosmic-analytics-panel">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <p className="cosmic-panel-title">Website traffic</p>
                            <p className="cosmic-panel-subtitle">Page views across your tracked websites</p>
                        </div>
                        <span className="cosmic-live-pill"><span /> Live data</span>
                    </div>
                    <TrafficChart series={series} />
                </article>

                <article className="cosmic-analytics-panel">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <p className="cosmic-panel-title">Top websites</p>
                            <p className="cosmic-panel-subtitle">Ranked by page views</p>
                        </div>
                        {onOpenInsights && <button type="button" onClick={onOpenInsights} className="cosmic-panel-link">Insights</button>}
                    </div>
                    {topWebsites.length ? (
                        <div className="mt-5 space-y-2.5">
                            {topWebsites.map((site, index) => {
                                const maximum = Math.max(...topWebsites.map((item) => Number(item.page_views || 0)), 1);
                                return (
                                    <div key={site.website_id} className="cosmic-top-site-row">
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center justify-between gap-3">
                                                <span className="truncate text-sm font-semibold cosmic-text-strong">{site.website_name}</span>
                                                <span className="text-xs font-semibold cosmic-text-muted">{formatNumber(site.page_views)}</span>
                                            </div>
                                            <div className="cosmic-top-site-track"><span style={{ width: `${Math.max(5, (Number(site.page_views || 0) / maximum) * 100)}%` }} /></div>
                                        </div>
                                        <span className="cosmic-rank-badge">{String(index + 1).padStart(2, "0")}</span>
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        <div className="cosmic-mini-empty"><span>◎</span><p>Top websites appear after traffic is recorded.</p></div>
                    )}
                </article>
            </div>
        </section>
    );
}
