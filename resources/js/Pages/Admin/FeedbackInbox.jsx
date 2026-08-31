import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const formatDate = (value) => value ? new Date(value).toLocaleString() : '—';
const CATEGORY_STYLE = {
    bug: 'bg-rose-50 text-rose-700 ring-rose-200',
    suggestion: 'bg-violet-50 text-violet-700 ring-violet-200',
    feedback: 'bg-sky-50 text-sky-700 ring-sky-200',
};
const STATUS_STYLE = {
    new: 'bg-amber-50 text-amber-700',
    reviewing: 'bg-blue-50 text-blue-700',
    resolved: 'bg-emerald-50 text-emerald-700',
    closed: 'bg-slate-100 text-slate-600',
};

export default function FeedbackInbox({ reports, filters, unreadCount }) {
    const [q, setQ] = useState(filters.q || '');
    const apply = (patch = {}) => router.get(route('admin.feedback.index'), {
        ...filters,
        q,
        ...patch,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });

    return (
        <AuthenticatedLayout header={(
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-black text-slate-900">Feedback Inbox</h1>
                    <p className="mt-1 text-sm text-slate-500">Bug reports, suggestions, and product feedback · {unreadCount} unread</p>
                </div>
                <Link href={route('admin.chat.index')} className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Customer chats
                </Link>
            </div>
        )}>
            <Head title="Feedback Inbox" />
            <div className="mx-auto max-w-7xl p-4 sm:p-6">
                <div className="mb-4 grid gap-3 rounded-2xl bg-white p-4 shadow-sm lg:grid-cols-[1fr_190px_190px]">
                    <form onSubmit={(event) => { event.preventDefault(); apply(); }} className="flex gap-2">
                        <input
                            value={q}
                            onChange={(event) => setQ(event.target.value)}
                            placeholder="Search description, email, website or page"
                            className="w-full rounded-xl border-slate-200 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                        />
                        <button className="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Search</button>
                    </form>
                    <select value={filters.category} onChange={(event) => apply({ category: event.target.value })} className="rounded-xl border-slate-200 text-sm">
                        <option value="all">All categories</option>
                        <option value="bug">Bugs</option>
                        <option value="suggestion">Suggestions</option>
                        <option value="feedback">General feedback</option>
                    </select>
                    <select value={filters.status} onChange={(event) => apply({ status: event.target.value })} className="rounded-xl border-slate-200 text-sm">
                        <option value="all">All statuses</option>
                        <option value="new">New</option>
                        <option value="reviewing">Reviewing</option>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>

                <div className="overflow-hidden rounded-2xl bg-white shadow-sm">
                    {reports.data.length === 0 ? (
                        <div className="p-12 text-center">
                            <div className="text-3xl">✓</div>
                            <p className="mt-3 text-sm font-semibold text-slate-700">No feedback reports found.</p>
                            <p className="mt-1 text-xs text-slate-500">New Builder submissions will appear here.</p>
                        </div>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {reports.data.map((report) => (
                                <Link
                                    key={report.id}
                                    href={route('admin.feedback.show', report.id)}
                                    className="grid gap-3 p-4 transition hover:bg-slate-50 sm:grid-cols-[minmax(0,1fr)_190px_150px] sm:items-center"
                                >
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            {report.unread && <span className="h-2.5 w-2.5 rounded-full bg-emerald-500" title="Unread" />}
                                            <span className={`rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide ring-1 ring-inset ${CATEGORY_STYLE[report.category] || CATEGORY_STYLE.feedback}`}>
                                                {report.category}
                                            </span>
                                            <span className={`rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide ${STATUS_STYLE[report.status] || STATUS_STYLE.new}`}>
                                                {report.status}
                                            </span>
                                            {report.has_screenshot && <span className="text-xs text-slate-500">📎 Screenshot</span>}
                                        </div>
                                        <p className={`mt-2 truncate text-sm ${report.unread ? 'font-bold text-slate-950' : 'font-semibold text-slate-800'}`}>{report.description}</p>
                                        <p className="mt-1 truncate text-xs text-slate-500">
                                            {report.reporter_email || report.reporter_name || 'Anonymous trial user'}
                                        </p>
                                    </div>
                                    <div className="min-w-0 text-xs text-slate-500">
                                        <div className="truncate font-semibold text-slate-700">{report.website_name || 'Unknown website'}</div>
                                        <div className="mt-1 truncate">{report.page_title || 'Unknown page'}</div>
                                    </div>
                                    <div className="text-xs text-slate-500 sm:text-right">{formatDate(report.created_at)}</div>
                                </Link>
                            ))}
                        </div>
                    )}
                </div>

                {reports.links?.length > 3 && (
                    <div className="mt-4 flex flex-wrap gap-2">
                        {reports.links.map((link, index) => link.url ? (
                            <Link key={index} href={link.url} preserveScroll className={`rounded-lg px-3 py-2 text-xs ${link.active ? 'bg-emerald-600 text-white' : 'bg-white text-slate-600 shadow-sm'}`} dangerouslySetInnerHTML={{ __html: link.label }} />
                        ) : (
                            <span key={index} className="rounded-lg bg-slate-100 px-3 py-2 text-xs text-slate-400" dangerouslySetInnerHTML={{ __html: link.label }} />
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
