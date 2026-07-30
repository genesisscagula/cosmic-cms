import { useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';
import axios from 'axios';

const formatDate = (value) => value
    ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
    : 'Just now';

export default function InquiryInboxModal({ website, submissions = [], onClose, onCountChange }) {
    const [items, setItems] = useState(submissions);
    const [processingId, setProcessingId] = useState(null);

    useEffect(() => {
        const onKeyDown = (event) => event.key === 'Escape' && onClose();
        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, [onClose]);

    const updateSubmission = async (submission, status) => {
        setProcessingId(submission.id);
        try {
            const response = await axios.patch(route('websites.inquiries.update', [website.id, submission.id]), { status });
            const next = response.data.submission;
            setItems((current) => status === 'archived'
                ? current.filter((item) => item.id !== submission.id)
                : current.map((item) => item.id === submission.id ? next : item));
            if (status === 'archived') onCountChange?.(-1);
        } finally {
            setProcessingId(null);
        }
    };

    return <div className="fixed inset-0 z-[160] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="inquiry-inbox-title" onMouseDown={onClose}>
        <div className="flex max-h-[86vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-white/10 bg-[#17181c] shadow-2xl" onMouseDown={(event) => event.stopPropagation()}>
            <div className="flex items-start justify-between border-b border-white/10 px-5 py-4 sm:px-6"><div><p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-violet-300">Website inbox</p><h2 id="inquiry-inbox-title" className="mt-1 text-lg font-semibold text-white">Recent inquiries</h2><p className="mt-1 text-sm text-slate-400">Messages from {website.name}'s published forms.</p></div><button type="button" onClick={onClose} className="rounded-lg p-2 text-slate-400 transition hover:bg-white/5 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400" aria-label="Close inquiries">×</button></div>
            <div className="min-h-0 flex-1 overflow-y-auto p-4 sm:p-5">{items.length === 0 ? <div className="rounded-xl border border-dashed border-white/15 bg-white/[0.025] px-5 py-12 text-center"><p className="font-semibold text-white">No inquiries yet</p><p className="mt-2 text-sm leading-6 text-slate-400">New messages from your published contact form will appear here.</p></div> : <div className="space-y-3">{items.map((submission) => { const processing = processingId === submission.id; return <article key={submission.id} className={`rounded-xl border p-4 transition ${submission.status === 'unread' ? 'border-violet-400/35 bg-violet-500/[0.06]' : 'border-white/10 bg-white/[0.025]'}`}><div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div className="min-w-0"><div className="flex flex-wrap items-center gap-2"><h3 className="font-semibold text-white">{submission.name}</h3>{submission.status === 'unread' ? <span className="rounded-full bg-violet-400/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-violet-200">New</span> : null}</div><a href={`mailto:${submission.email}`} className="mt-1 inline-block text-sm text-violet-300 hover:text-violet-200 focus:outline-none focus:ring-2 focus:ring-violet-400">{submission.email}</a></div><time className="shrink-0 text-xs text-slate-500">{formatDate(submission.received_at)}</time></div><p className="mt-3 line-clamp-3 whitespace-pre-wrap text-sm leading-6 text-slate-300">{submission.message}</p><div className="mt-4 flex flex-wrap items-center gap-2">{submission.status === 'unread' ? <button type="button" disabled={processing} onClick={() => updateSubmission(submission, 'read')} className="rounded-lg border border-white/10 px-3 py-1.5 text-xs font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white disabled:opacity-50">Mark read</button> : <button type="button" disabled={processing} onClick={() => updateSubmission(submission, 'unread')} className="rounded-lg border border-white/10 px-3 py-1.5 text-xs font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white disabled:opacity-50">Mark unread</button>}<button type="button" disabled={processing} onClick={() => updateSubmission(submission, 'archived')} className="rounded-lg px-3 py-1.5 text-xs font-semibold text-slate-500 transition hover:bg-rose-500/10 hover:text-rose-200 disabled:opacity-50">Archive</button></div></article>; })}</div>}</div>
            <div className="flex items-center justify-between border-t border-white/10 px-5 py-4 sm:px-6"><Link href={route('websites.inquiries.index', website.id)} className="text-sm font-semibold text-violet-300 transition hover:text-violet-200 focus:outline-none focus:ring-2 focus:ring-violet-400">Open full inbox →</Link><button type="button" onClick={onClose} className="rounded-lg bg-white px-4 py-2 text-sm font-bold text-slate-950 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-400">Done</button></div>
        </div>
    </div>;
}
