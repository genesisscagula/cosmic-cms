import { Head, Link } from '@inertiajs/react';

const formatDate = (value) => value
    ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
    : 'Just now';

const displayValue = (value) => Array.isArray(value) ? value.join(', ') : String(value ?? '');

export default function Inquiries({ website, submissions = [] }) {
    return (
        <>
            <Head title={`Inquiries · ${website.name}`} />
            <main className="min-h-screen bg-[#09090b] px-4 py-8 text-slate-100 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-5xl">
                    <Link href={route('pages.index', website.id)} className="inline-flex rounded-lg px-2 py-2 text-sm font-semibold text-slate-400 transition hover:bg-white/5 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400">
                        ← Back to Pages
                    </Link>
                    <div className="mt-6 flex flex-col gap-4 border-b border-white/10 pb-6 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-violet-300">Website inbox</p>
                            <h1 className="mt-2 text-3xl font-semibold tracking-tight text-white">Inquiries</h1>
                            <p className="mt-2 text-sm text-slate-400">Messages submitted from {website.name}'s published contact forms.</p>
                        </div>
                        <span className="rounded-full border border-white/10 bg-white/[0.04] px-3 py-1.5 text-sm font-semibold text-slate-300">{submissions.length} {submissions.length === 1 ? 'inquiry' : 'inquiries'}</span>
                    </div>

                    {submissions.length === 0 ? (
                        <section className="mt-8 rounded-2xl border border-dashed border-white/15 bg-white/[0.025] px-6 py-16 text-center">
                            <p className="text-lg font-semibold text-white">No inquiries yet</p>
                            <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-400">Once a visitor submits a published contact form, their message will appear here. Download the latest connector after enabling this inbox.</p>
                        </section>
                    ) : (
                        <div className="mt-8 space-y-4">
                            {submissions.map((submission) => (
                                <article key={submission.id} className="rounded-2xl border border-white/10 bg-white/[0.035] p-5 shadow-[0_16px_50px_rgba(0,0,0,0.14)] sm:p-6">
                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <h2 className="text-base font-semibold text-white">{submission.name}</h2>
                                            <a href={`mailto:${submission.email}`} className="mt-1 inline-block text-sm text-violet-300 hover:text-violet-200 focus:outline-none focus:ring-2 focus:ring-violet-400">{submission.email}</a>
                                            {submission.phone ? <a href={`tel:${submission.phone}`} className="ml-3 text-sm text-slate-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400">{submission.phone}</a> : null}
                                        </div>
                                        <time className="text-xs text-slate-500">{formatDate(submission.received_at)}</time>
                                    </div>
                                    <p className="mt-5 whitespace-pre-wrap text-sm leading-6 text-slate-300">{submission.message}</p>
                                    {submission.fields && Object.keys(submission.fields).filter((key) => !['name', 'email', 'phone', 'message'].includes(key)).length ? (
                                        <dl className="mt-5 grid gap-3 border-t border-white/10 pt-5 sm:grid-cols-2">
                                            {Object.entries(submission.fields).filter(([key]) => !['name', 'email', 'phone', 'message'].includes(key)).map(([key, value]) => (
                                                <div key={key} className="rounded-xl bg-black/20 px-4 py-3">
                                                    <dt className="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">{key.replace(/_/g, ' ')}</dt>
                                                    <dd className="mt-1 text-sm text-slate-200">{displayValue(value)}</dd>
                                                </div>
                                            ))}
                                        </dl>
                                    ) : null}
                                </article>
                            ))}
                        </div>
                    )}
                </div>
            </main>
        </>
    );
}
