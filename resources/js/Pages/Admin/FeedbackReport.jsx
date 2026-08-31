import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const formatDate = (value) => value ? new Date(value).toLocaleString() : '—';
const formatBytes = (value) => {
    const bytes = Number(value || 0);
    if (!bytes) return '—';
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
};

export default function FeedbackReport({ report }) {
    const form = useForm({
        status: report.status,
        admin_notes: report.admin_notes || '',
    });
    const submit = (event) => {
        event.preventDefault();
        form.patch(route('admin.feedback.update', report.id), { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout header={(
            <div>
                <Link href={route('admin.feedback.index')} className="text-sm font-semibold text-emerald-700 hover:text-emerald-600">← Feedback Inbox</Link>
                <div className="mt-2 flex flex-wrap items-center gap-3">
                    <h1 className="text-xl font-black text-slate-900">Report #{report.id}</h1>
                    <span className="rounded-full bg-slate-900 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-white">{report.category}</span>
                    <span className="rounded-full bg-emerald-50 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-emerald-700">{report.status}</span>
                </div>
            </div>
        )}>
            <Head title={`Feedback #${report.id}`} />
            <div className="mx-auto grid max-w-7xl gap-5 p-4 sm:p-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                <div className="space-y-5">
                    <section className="rounded-2xl bg-white p-5 shadow-sm sm:p-6">
                        <p className="text-xs font-bold uppercase tracking-[.16em] text-slate-400">User description</p>
                        <p className="mt-4 whitespace-pre-wrap text-sm leading-7 text-slate-800">{report.description}</p>
                    </section>

                    {report.has_screenshot && (
                        <section className="rounded-2xl bg-white p-5 shadow-sm sm:p-6">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p className="text-xs font-bold uppercase tracking-[.16em] text-slate-400">Screenshot</p>
                                    <p className="mt-1 text-xs text-slate-500">{report.screenshot_name || 'Attached screenshot'} · {formatBytes(report.screenshot_size)}</p>
                                </div>
                                <a href={report.screenshot_url} target="_blank" rel="noreferrer" className="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Open original</a>
                            </div>
                            <a href={report.screenshot_url} target="_blank" rel="noreferrer" className="mt-4 block overflow-hidden rounded-xl border border-slate-200 bg-slate-100">
                                <img src={report.screenshot_url} alt={`Screenshot for feedback report ${report.id}`} className="max-h-[720px] w-full object-contain" />
                            </a>
                        </section>
                    )}

                    <section className="rounded-2xl bg-white p-5 shadow-sm sm:p-6">
                        <p className="text-xs font-bold uppercase tracking-[.16em] text-slate-400">Technical context</p>
                        <dl className="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                            {Object.entries(report.context || {}).map(([key, value]) => (
                                <div key={key} className="rounded-xl bg-slate-50 p-3">
                                    <dt className="text-[10px] font-bold uppercase tracking-wide text-slate-400">{key.replaceAll('_', ' ')}</dt>
                                    <dd className="mt-1 break-words font-medium text-slate-700">{String(value)}</dd>
                                </div>
                            ))}
                        </dl>
                        {report.user_agent && <p className="mt-4 break-words text-xs leading-5 text-slate-500">{report.user_agent}</p>}
                    </section>
                </div>

                <aside className="space-y-5">
                    <section className="rounded-2xl bg-white p-5 shadow-sm">
                        <p className="text-xs font-bold uppercase tracking-[.16em] text-slate-400">Report details</p>
                        <dl className="mt-4 space-y-4 text-sm">
                            <div><dt className="text-xs text-slate-400">Reporter</dt><dd className="mt-1 font-semibold text-slate-800">{report.reporter_name || 'Anonymous'}</dd><dd className="text-xs text-slate-500">{report.reporter_email || 'No email captured'}</dd></div>
                            <div><dt className="text-xs text-slate-400">Website</dt><dd className="mt-1 font-semibold text-slate-800">{report.website?.name || 'Unknown'}</dd></div>
                            <div><dt className="text-xs text-slate-400">Page</dt><dd className="mt-1 font-semibold text-slate-800">{report.page?.title || 'Unknown'}</dd></div>
                            <div><dt className="text-xs text-slate-400">Received</dt><dd className="mt-1 text-slate-700">{formatDate(report.created_at)}</dd></div>
                        </dl>
                        {report.source_url && <a href={report.source_url} target="_blank" rel="noreferrer" className="mt-5 inline-flex rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white hover:bg-slate-700">Open reported page</a>}
                    </section>

                    <form onSubmit={submit} className="rounded-2xl bg-white p-5 shadow-sm">
                        <p className="text-xs font-bold uppercase tracking-[.16em] text-slate-400">Triage</p>
                        <label className="mt-4 block text-xs font-semibold text-slate-600">Status</label>
                        <select value={form.data.status} onChange={(event) => form.setData('status', event.target.value)} className="mt-2 w-full rounded-xl border-slate-200 text-sm">
                            <option value="new">New</option>
                            <option value="reviewing">Reviewing</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                        </select>
                        <label className="mt-4 block text-xs font-semibold text-slate-600">Internal notes</label>
                        <textarea value={form.data.admin_notes} onChange={(event) => form.setData('admin_notes', event.target.value)} rows={8} maxLength={5000} placeholder="Investigation notes, fix, or follow-up…" className="mt-2 w-full resize-y rounded-xl border-slate-200 text-sm leading-6" />
                        {form.errors.admin_notes && <p className="mt-2 text-xs text-rose-600">{form.errors.admin_notes}</p>}
                        <button disabled={form.processing} className="mt-4 w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-500 disabled:opacity-50">{form.processing ? 'Saving…' : 'Save update'}</button>
                    </form>
                </aside>
            </div>
        </AuthenticatedLayout>
    );
}
