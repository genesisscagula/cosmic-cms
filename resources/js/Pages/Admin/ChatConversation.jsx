import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';

const fmt = (value) => value ? new Date(value).toLocaleString() : '—';

export default function ChatConversation({ conversation }) {
    const replyForm = useForm({ message: '' });

    const update = (payload) => router.patch(route('admin.chat.update', conversation.id), payload, {
        preserveScroll: true,
    });

    const toggleTakeover = () => router.patch(
        route('admin.chat.takeover', conversation.id),
        { ai_paused: !conversation.ai_paused },
        { preserveScroll: true }
    );

    const sendReply = (e) => {
        e.preventDefault();
        if (!replyForm.data.message.trim()) return;
        replyForm.post(route('admin.chat.reply', conversation.id), {
            preserveScroll: true,
            onSuccess: () => replyForm.reset('message'),
        });
    };

    return (
        <AuthenticatedLayout
            header={<div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <Link href={route('admin.chat.index')} className="text-sm font-medium text-emerald-700">← Chat Inbox</Link>
                    <div className="mt-1 flex flex-wrap items-center gap-2">
                        <h1 className="text-xl font-black text-slate-900">
                            {conversation.visitor_name || conversation.visitor_email || 'Anonymous visitor'}
                        </h1>
                        <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${conversation.ai_paused ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'}`}>
                            {conversation.ai_paused ? 'Human takeover' : 'AI active'}
                        </span>
                    </div>
                </div>
                <div className="flex flex-wrap gap-2">
                    <button onClick={toggleTakeover}
                        className={`rounded-xl px-4 py-2 text-sm font-semibold text-white ${conversation.ai_paused ? 'bg-emerald-600' : 'bg-amber-600'}`}>
                        {conversation.ai_paused ? 'Resume AI' : 'Take Over'}
                    </button>
                    <select value={conversation.lead_status} onChange={(e) => update({ lead_status: e.target.value })}
                        className="rounded-xl border-slate-200 text-sm">
                        <option value="new">New</option>
                        <option value="qualified">Qualified</option>
                        <option value="follow_up">Follow up</option>
                        <option value="closed">Closed</option>
                    </select>
                    <button onClick={() => update({ archived: !conversation.archived_at })}
                        className="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                        {conversation.archived_at ? 'Restore' : 'Archive'}
                    </button>
                </div>
            </div>}
        >
            <Head title="Chat Conversation" />
            <div className="mx-auto grid max-w-7xl gap-5 p-4 sm:p-6 lg:grid-cols-[minmax(0,1fr)_300px]">
                <div className="min-w-0">
                    <section className="rounded-2xl bg-white p-4 shadow-sm sm:p-6">
                        <div className="space-y-4">
                            {conversation.messages.map((message) => {
                                const visitor = message.role === 'user';
                                const human = message.role === 'admin';
                                return (
                                    <div key={message.id} className={`flex ${visitor ? 'justify-start' : 'justify-end'}`}>
                                        <div className={`max-w-[85%] rounded-2xl px-4 py-3 ${
                                            visitor ? 'bg-slate-100 text-slate-900'
                                                : human ? 'bg-slate-900 text-white'
                                                : 'bg-emerald-600 text-white'
                                        }`}>
                                            <div className="mb-1 text-[11px] font-semibold uppercase tracking-wide opacity-70">
                                                {visitor ? 'Visitor' : human ? 'You · Cosmic Team' : 'Cosmic AI'}
                                            </div>
                                            <div className="whitespace-pre-wrap text-sm leading-6">{message.content}</div>
                                            <div className="mt-2 text-[10px] opacity-60">{fmt(message.created_at)}</div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </section>

                    <form onSubmit={sendReply} className="mt-4 rounded-2xl bg-white p-4 shadow-sm">
                        <label className="text-sm font-semibold text-slate-900">Reply as Cosmic CMS team</label>
                        <p className="mt-1 text-xs text-slate-500">Sending a reply automatically pauses AI for this conversation.</p>
                        <textarea
                            value={replyForm.data.message}
                            onChange={(e) => replyForm.setData('message', e.target.value.slice(0, 2000))}
                            rows={4}
                            placeholder="Type your reply to the visitor…"
                            className="mt-3 w-full rounded-xl border-slate-200 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                        />
                        {replyForm.errors.message && <p className="mt-2 text-xs text-rose-600">{replyForm.errors.message}</p>}
                        <div className="mt-3 flex justify-end">
                            <button disabled={replyForm.processing || !replyForm.data.message.trim()}
                                className="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">
                                {replyForm.processing ? 'Sending…' : 'Send Reply'}
                            </button>
                        </div>
                    </form>
                </div>

                <aside className="h-fit rounded-2xl bg-white p-5 shadow-sm">
                    <h2 className="text-sm font-semibold text-slate-900">Visitor context</h2>
                    <dl className="mt-4 space-y-4 text-sm">
                        <div><dt className="text-xs text-slate-500">Name</dt><dd className="mt-1 text-slate-800">{conversation.visitor_name || 'Not captured'}</dd></div>
                        <div><dt className="text-xs text-slate-500">Email</dt><dd className="mt-1 break-all text-slate-800">{conversation.visitor_email || 'Not captured'}</dd></div>
                        <div><dt className="text-xs text-slate-500">Lead captured</dt><dd className="mt-1 text-slate-800">{fmt(conversation.lead_captured_at)}</dd></div>
                        <div><dt className="text-xs text-slate-500">Started page</dt><dd className="mt-1 break-all text-slate-800">{conversation.started_page || 'Unknown'}</dd></div>
                        <div><dt className="text-xs text-slate-500">Last page</dt><dd className="mt-1 break-all text-slate-800">{conversation.last_page || 'Unknown'}</dd></div>
                        <div><dt className="text-xs text-slate-500">Started</dt><dd className="mt-1 text-slate-800">{fmt(conversation.created_at)}</dd></div>
                        <div><dt className="text-xs text-slate-500">Last message</dt><dd className="mt-1 text-slate-800">{fmt(conversation.last_message_at)}</dd></div>
                        <div><dt className="text-xs text-slate-500">Takeover</dt><dd className="mt-1 text-slate-800">{conversation.ai_paused ? `Active since ${fmt(conversation.taken_over_at)}` : 'AI is handling replies'}</dd></div>
                    </dl>
                </aside>
            </div>
        </AuthenticatedLayout>
    );
}
