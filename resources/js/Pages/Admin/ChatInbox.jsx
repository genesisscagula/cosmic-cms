import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const fmt = (value) => value ? new Date(value).toLocaleString() : '—';

export default function ChatInbox({ conversations, filters, unreadCount }) {
    const [q, setQ] = useState(filters.q || '');

    const apply = (patch = {}) => {
        router.get(route('admin.chat.index'), { ...filters, q, ...patch }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={<div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-black text-slate-900">Customer Chat Inbox</h1>
                    <p className="mt-1 text-sm text-slate-500">Platform-owner only · {unreadCount} unread</p>
                </div>
            </div>}
        >
            <Head title="Customer Chat Inbox" />
            <div className="mx-auto max-w-7xl p-4 sm:p-6">
                <div className="mb-4 grid gap-3 rounded-2xl bg-white p-4 shadow-sm sm:grid-cols-[1fr_auto_auto]">
                    <form onSubmit={(e) => { e.preventDefault(); apply(); }} className="flex gap-2">
                        <input value={q} onChange={(e) => setQ(e.target.value)}
                            placeholder="Search name, email, page or message"
                            className="w-full rounded-xl border-slate-200 text-sm focus:border-emerald-500 focus:ring-emerald-500" />
                        <button className="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Search</button>
                    </form>
                    <select value={filters.status} onChange={(e) => apply({ status: e.target.value })}
                        className="rounded-xl border-slate-200 text-sm">
                        <option value="all">All lead statuses</option>
                        <option value="new">New</option>
                        <option value="qualified">Qualified</option>
                        <option value="follow_up">Follow up</option>
                        <option value="closed">Closed</option>
                    </select>
                    <select value={filters.view} onChange={(e) => apply({ view: e.target.value })}
                        className="rounded-xl border-slate-200 text-sm">
                        <option value="active">Active</option>
                        <option value="archived">Archived</option>
                        <option value="all">All</option>
                    </select>
                </div>

                <div className="overflow-hidden rounded-2xl bg-white shadow-sm">
                    {conversations.data.length === 0 ? (
                        <div className="p-10 text-center text-sm text-slate-500">No conversations found.</div>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {conversations.data.map((item) => (
                                <Link key={item.id} href={route('admin.chat.show', item.id)}
                                    className="grid gap-2 p-4 transition hover:bg-slate-50 sm:grid-cols-[minmax(0,1fr)_180px_150px] sm:items-center">
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-2">
                                            {item.unread && <span className="h-2.5 w-2.5 rounded-full bg-emerald-500" />}
                                            <div className={`truncate text-sm ${item.unread ? 'font-bold text-slate-950' : 'font-semibold text-slate-800'}`}>
                                                {item.visitor_name || item.visitor_email || 'Anonymous visitor'}
                                            </div>
                                            <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">
                                                {item.lead_status.replace('_', ' ')}
                                            </span>
                                        </div>
                                        <div className="mt-1 truncate text-xs text-slate-500">
                                            {item.visitor_email || 'No email captured'} · {item.last_page || item.started_page || 'Unknown page'}
                                        </div>
                                    </div>
                                    <div className="text-xs text-slate-500">{item.visitor_message_count} visitor messages</div>
                                    <div className="text-xs text-slate-500 sm:text-right">{fmt(item.last_message_at)}</div>
                                </Link>
                            ))}
                        </div>
                    )}
                </div>

                {conversations.links?.length > 3 && (
                    <div className="mt-4 flex flex-wrap gap-2">
                        {conversations.links.map((link, i) => link.url ? (
                            <Link key={i} href={link.url} preserveScroll
                                className={`rounded-lg px-3 py-2 text-xs ${link.active ? 'bg-emerald-600 text-white' : 'bg-white text-slate-600 shadow-sm'}`}
                                dangerouslySetInnerHTML={{ __html: link.label }} />
                        ) : (
                            <span key={i} className="rounded-lg bg-slate-100 px-3 py-2 text-xs text-slate-400"
                                dangerouslySetInnerHTML={{ __html: link.label }} />
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
