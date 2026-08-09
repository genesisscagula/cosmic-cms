import { useEffect, useMemo, useRef, useState } from 'react';

const STORAGE_KEY = 'cosmic.public-chat.v1';
const CLOSED_KEY = 'cosmic.public-chat.closed';

const hiddenPrefixes = [
    '/dashboard', '/profile', '/admin', '/pages/', '/websites/', '/credits',
    '/sales', '/agency-', '/workspace', '/checkout', '/payment', '/login',
    '/register', '/forgot-password', '/reset-password', '/verify-email',
];

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

export default function CosmicPublicChat() {
    const [open, setOpen] = useState(false);
    const [session, setSession] = useState(null);
    const [messages, setMessages] = useState([]);
    const [input, setInput] = useState('');
    const [sending, setSending] = useState(false);
    const [error, setError] = useState('');
    const [aiPaused, setAiPaused] = useState(false);
    const [leadCaptured, setLeadCaptured] = useState(false);
    const [showLeadForm, setShowLeadForm] = useState(false);
    const [lead, setLead] = useState({ name: '', email: '' });
    const [leadSending, setLeadSending] = useState(false);
    const [hasNewMessage, setHasNewMessage] = useState(false);

    const scrollRef = useRef(null);
    const endRef = useRef(null);
    const nearBottomRef = useRef(true);
    const forceScrollRef = useRef(false);

    const pathname = typeof window !== 'undefined' ? window.location.pathname : '';
    const visible = useMemo(
        () => !hiddenPrefixes.some((prefix) => pathname === prefix || pathname.startsWith(prefix)),
        [pathname]
    );

    const lastMessageId = useMemo(
        () => Math.max(0, ...messages.map((m) => Number(m.id || 0))),
        [messages]
    );

    const scrollToBottom = (behavior = 'smooth') => {
        nearBottomRef.current = true;
        setHasNewMessage(false);
        window.requestAnimationFrame(() => {
            endRef.current?.scrollIntoView({ behavior, block: 'end' });
        });
    };

    const handleScroll = () => {
        const node = scrollRef.current;
        if (!node) return;
        const distance = node.scrollHeight - node.scrollTop - node.clientHeight;
        const nearBottom = distance < 90;
        nearBottomRef.current = nearBottom;
        if (nearBottom) setHasNewMessage(false);
    };

    /**
     * Merge persisted messages without duplicating optimistic user bubbles.
     * A polling response can arrive before /message finishes, so a server-side
     * user message replaces the matching pending local message in-place.
     */
    const mergeIncoming = (incoming = [], { notify = true } = {}) => {
        if (!incoming.length) return;

        const shouldFollow = forceScrollRef.current || nearBottomRef.current;

        setMessages((current) => {
            const next = [...current];

            incoming.forEach((message) => {
                if (message.id && next.some((item) => Number(item.id || 0) === Number(message.id))) {
                    return;
                }

                if (message.role === 'user') {
                    const pendingIndex = next.findIndex(
                        (item) =>
                            item.role === 'user' &&
                            item.pending === true &&
                            item.content === message.content
                    );

                    if (pendingIndex !== -1) {
                        next[pendingIndex] = { ...message, pending: false };
                        return;
                    }
                }

                next.push(message);
            });

            return next;
        });

        if (shouldFollow) {
            forceScrollRef.current = false;
            scrollToBottom('smooth');
        } else if (notify) {
            setHasNewMessage(true);
        }
    };

    const fetchHistory = async (activeSession, afterId = 0, options = {}) => {
        if (!activeSession?.conversation_id || !activeSession?.access_token) return;

        const response = await window.axios.post('/support/chat/history', {
            ...activeSession,
            after_id: afterId,
        }, { headers: { 'X-CSRF-TOKEN': csrfToken() } });

        mergeIncoming(response.data.messages || [], options);
        setAiPaused(Boolean(response.data.ai_paused));
        setLeadCaptured(Boolean(response.data.lead_captured));
    };

    useEffect(() => {
        if (!visible) return;

        try {
            const saved = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || 'null');
            if (saved?.conversation_id && saved?.access_token) {
                setSession(saved);
                forceScrollRef.current = true;
                fetchHistory(saved, 0, { notify: false }).catch(() => {});
            }
        } catch (_) {}
    }, [visible]);

    useEffect(() => {
        if (!visible || !session) return;

        const poll = window.setInterval(() => {
            fetchHistory(session, lastMessageId).catch(() => {});
        }, open ? 4000 : 12000);

        return () => window.clearInterval(poll);
    }, [visible, session, open, lastMessageId]);

    useEffect(() => {
        if (open) {
            window.requestAnimationFrame(() => handleScroll());
        }
    }, [open]);

    if (!visible) return null;

    const ensureSession = async () => {
        if (session) return session;

        const response = await window.axios.post('/support/chat/start', { page: window.location.href }, {
            headers: { 'X-CSRF-TOKEN': csrfToken() },
        });

        const next = {
            conversation_id: response.data.conversation_id,
            access_token: response.data.access_token,
        };

        setSession(next);
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
        setMessages([{ role: 'assistant', content: response.data.welcome, local: true }]);
        setAiPaused(Boolean(response.data.ai_paused));
        forceScrollRef.current = true;
        scrollToBottom('auto');

        return next;
    };

    const toggleOpen = async () => {
        const next = !open;
        setOpen(next);
        setError('');

        if (next && !session && messages.length === 0) {
            try {
                await ensureSession();
            } catch (_) {
                setError('Chat is temporarily unavailable. Please try again.');
            }
        } else if (next && session) {
            const shouldJump = messages.length === 0;
            if (shouldJump) forceScrollRef.current = true;
            fetchHistory(session, lastMessageId, { notify: !shouldJump }).catch(() => {});
        }
    };

    const send = async (event) => {
        event?.preventDefault();
        const text = input.trim();
        if (!text || sending) return;

        setInput('');
        setError('');

        const localKey = `local-${Date.now()}-${Math.random().toString(36).slice(2)}`;
        forceScrollRef.current = true;
        setMessages((current) => [
            ...current,
            { role: 'user', content: text, localKey, pending: true },
        ]);
        scrollToBottom('smooth');
        setSending(true);

        try {
            const active = await ensureSession();
            const response = await window.axios.post('/support/chat/message', {
                ...active,
                message: text,
                page: window.location.href,
            }, { headers: { 'X-CSRF-TOKEN': csrfToken() } });

            setAiPaused(Boolean(response.data.ai_paused));

            // Reconcile the optimistic bubble first, then append the reply.
            if (response.data.user_message) {
                mergeIncoming([response.data.user_message], { notify: false });
            }
            if (response.data.message) {
                forceScrollRef.current = true;
                mergeIncoming([response.data.message], { notify: false });
            }

            if (response.data.queued_for_team) {
                forceScrollRef.current = true;
                mergeIncoming([{
                    role: 'system',
                    content: 'Your message was sent to the Cosmic CMS team. You can keep this chat open and replies will appear here.',
                    local: true,
                    localKey: `system-${Date.now()}`,
                }], { notify: false });
            }
        } catch (requestError) {
            // Keep the failed user bubble visible but mark it non-pending so
            // future history polling cannot accidentally reconcile the wrong send.
            setMessages((current) => current.map((message) =>
                message.localKey === localKey ? { ...message, pending: false, failed: true } : message
            ));
            setError(requestError?.response?.data?.message || 'I could not send that message. Please try again.');
        } finally {
            setSending(false);
        }
    };

    const submitLead = async (event) => {
        event.preventDefault();
        if (!lead.email.trim() || leadSending) return;

        setLeadSending(true);
        setError('');

        try {
            const active = await ensureSession();
            const response = await window.axios.post('/support/chat/lead', {
                ...active,
                name: lead.name.trim(),
                email: lead.email.trim(),
                page: window.location.href,
            }, { headers: { 'X-CSRF-TOKEN': csrfToken() } });

            setLeadCaptured(true);
            setShowLeadForm(false);
            forceScrollRef.current = true;
            mergeIncoming([{
                role: 'system',
                content: response.data.message,
                local: true,
                localKey: `lead-${Date.now()}`,
            }], { notify: false });
        } catch (requestError) {
            setError(requestError?.response?.data?.message || 'Could not save your details. Please try again.');
        } finally {
            setLeadSending(false);
        }
    };

    const bubbleClass = (message) => {
        if (message.role === 'user') {
            return message.failed
                ? 'border border-rose-300 bg-rose-50 text-rose-800'
                : 'bg-emerald-600 text-white';
        }
        if (message.role === 'admin') return 'bg-slate-900 text-white';
        if (message.role === 'system') return 'border border-amber-200 bg-amber-50 text-amber-900';
        return 'border border-slate-200 bg-white text-slate-700';
    };

    return (
        <div className="fixed bottom-5 right-5 z-[9998] font-sans">
            {open && (
                <section className="mb-3 flex h-[min(610px,78vh)] w-[min(400px,calc(100vw-32px))] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl" aria-label="Cosmic CMS support chat">
                    <header className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                        <div className="flex items-center gap-3">
                            <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-600 text-sm font-extrabold text-white">C</div>
                            <div>
                                <div className="text-sm font-bold text-slate-900">Cosmic Assistant</div>
                                <div className="text-xs text-slate-500">
                                    {aiPaused ? 'Cosmic CMS team joined this chat' : 'AI support for Cosmic CMS'}
                                </div>
                            </div>
                        </div>
                        <button type="button" onClick={() => { setOpen(false); window.sessionStorage.setItem(CLOSED_KEY, '1'); }} className="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Close chat">×</button>
                    </header>

                    <div
                        ref={scrollRef}
                        onScroll={handleScroll}
                        className="relative flex-1 space-y-3 overflow-y-auto bg-slate-50/70 px-4 py-4"
                    >
                        {messages.length === 0 && !error && <div className="text-center text-sm text-slate-500">Starting Cosmic Assistant…</div>}

                        {messages.map((message, index) => (
                            <div key={message.id || message.localKey || `${message.role}-${index}`} className={`flex ${message.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                                <div className={`max-w-[86%] whitespace-pre-wrap rounded-2xl px-3.5 py-2.5 text-sm leading-6 ${bubbleClass(message)}`}>
                                    {message.role === 'admin' && <div className="mb-1 text-[10px] font-bold uppercase tracking-wide opacity-60">Cosmic CMS Team</div>}
                                    {message.content}
                                    {message.pending && <div className="mt-1 text-[10px] opacity-70">Sending…</div>}
                                    {message.failed && <div className="mt-1 text-[10px] font-semibold">Not sent</div>}
                                </div>
                            </div>
                        ))}

                        {sending && !aiPaused && (
                            <div className="flex justify-start">
                                <div className="rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-500">Thinking…</div>
                            </div>
                        )}

                        {error && <div className="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">{error}</div>}

                        {showLeadForm && !leadCaptured && (
                            <form onSubmit={submitLead} className="rounded-2xl border border-emerald-200 bg-white p-3 shadow-sm">
                                <div className="text-sm font-bold text-slate-900">Talk to the Cosmic CMS team</div>
                                <p className="mt-1 text-xs leading-5 text-slate-500">Leave your email so the team can identify and follow up on this conversation.</p>
                                <input
                                    value={lead.name}
                                    onChange={(e) => setLead((current) => ({ ...current, name: e.target.value.slice(0, 100) }))}
                                    placeholder="Name (optional)"
                                    className="mt-3 w-full rounded-xl border-slate-200 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                                />
                                <input
                                    type="email"
                                    required
                                    value={lead.email}
                                    onChange={(e) => setLead((current) => ({ ...current, email: e.target.value.slice(0, 190) }))}
                                    placeholder="Email address"
                                    className="mt-2 w-full rounded-xl border-slate-200 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                                />
                                <div className="mt-3 flex gap-2">
                                    <button type="submit" disabled={leadSending} className="flex-1 rounded-xl bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700 disabled:bg-slate-300 disabled:text-white">
                                        {leadSending ? 'Saving…' : 'Send details'}
                                    </button>
                                    <button type="button" onClick={() => setShowLeadForm(false)} className="rounded-xl bg-slate-100 px-3 py-2 text-xs font-bold text-slate-600">Cancel</button>
                                </div>
                            </form>
                        )}

                        <div ref={endRef} />
                    </div>

                    {hasNewMessage && (
                        <div className="pointer-events-none relative z-10 -mt-11 flex justify-center px-3">
                            <button
                                type="button"
                                onClick={() => scrollToBottom('smooth')}
                                className="pointer-events-auto rounded-full bg-slate-900 px-4 py-2 text-xs font-bold text-white shadow-lg hover:bg-slate-800"
                            >
                                New message ↓
                            </button>
                        </div>
                    )}

                    <div className="border-t border-slate-100 bg-white px-3 pt-3">
                        {!leadCaptured && !showLeadForm && (
                            <button
                                type="button"
                                onClick={() => setShowLeadForm(true)}
                                className="mb-2 w-full rounded-xl bg-emerald-600 px-3 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                            >
                                Talk to the Cosmic CMS team
                            </button>
                        )}
                        {leadCaptured && (
                            <div className="mb-2 text-center text-[11px] font-medium text-emerald-700">✓ Your contact details are attached to this chat</div>
                        )}
                    </div>

                    <form onSubmit={send} className="bg-white px-3 pb-3">
                        <div className="flex items-end gap-2">
                            <textarea
                                value={input}
                                onChange={(e) => setInput(e.target.value.slice(0, 1000))}
                                onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(e); } }}
                                rows={1}
                                placeholder={aiPaused ? 'Message the Cosmic CMS team…' : 'Ask about Cosmic CMS…'}
                                className="max-h-28 min-h-[44px] flex-1 resize-none rounded-xl border-slate-200 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                            />
                            <button
                                type="submit"
                                disabled={sending || !input.trim()}
                                className="h-11 rounded-xl bg-emerald-600 px-4 text-sm font-bold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-white"
                            >
                                Send
                            </button>
                        </div>
                        <p className="mt-2 text-center text-[10px] leading-4 text-slate-400">
                            {aiPaused ? 'A human is handling this conversation.' : 'AI answers are limited to verified Cosmic CMS information.'}
                        </p>
                    </form>
                </section>
            )}

            <div className="flex justify-end">
                <button type="button" onClick={toggleOpen} className="flex h-14 items-center gap-2 rounded-full bg-emerald-600 px-5 font-bold text-white shadow-xl transition hover:-translate-y-0.5 hover:bg-emerald-700" aria-expanded={open} aria-label="Chat with Cosmic CMS">
                    <span className="text-lg">✦</span><span className="text-sm">Ask Cosmic</span>
                </button>
            </div>
        </div>
    );
}
