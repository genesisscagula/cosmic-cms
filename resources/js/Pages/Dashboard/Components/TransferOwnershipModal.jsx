import { useEffect, useState } from 'react';

export default function TransferOwnershipModal({ open, website, onClose, onSubmit }) {
    const [email, setEmail] = useState('');
    const [pending, setPending] = useState(false);
    const [error, setError] = useState('');
    useEffect(() => { if (open) { setEmail(''); setError(''); setPending(false); } }, [open, website?.id]);
    if (!open || !website) return null;
    const submit = async (event) => {
        event.preventDefault();
        if (!email.trim()) return;
        setPending(true); setError('');
        try { await onSubmit(email.trim()); } catch (e) { setError(e?.message || 'Transfer could not be started.'); setPending(false); }
    };
    return <div className="fixed inset-0 z-[120] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm" onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
        <form onSubmit={submit} className="w-full max-w-lg rounded-3xl border border-white/10 bg-[#111114] p-6 shadow-2xl">
            <div className="flex items-start justify-between gap-4"><div><p className="text-xs font-semibold uppercase tracking-[.18em] text-violet-300">Website handoff</p><h2 className="mt-2 text-xl font-semibold text-white">Transfer {website.name}</h2><p className="mt-2 text-sm text-slate-400">Enter the Cosmic account email that should receive this website. Live deployment credentials will be cleared after acceptance.</p></div><button type="button" onClick={onClose} className="rounded-lg px-2 py-1 text-slate-400 hover:bg-white/10 hover:text-white">✕</button></div>
            <label className="mt-6 block text-xs font-semibold text-slate-300">Recipient email</label><input autoFocus type="email" value={email} onChange={(e)=>setEmail(e.target.value)} className="mt-2 w-full rounded-xl border border-white/10 bg-white/[.04] px-4 py-3 text-sm text-white outline-none focus:border-violet-400" placeholder="client@example.com" />
            {error && <p className="mt-2 text-xs text-rose-300">{error}</p>}
            <div className="mt-6 flex justify-end gap-2"><button type="button" onClick={onClose} className="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-300">Cancel</button><button disabled={pending || !email.trim()} className="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{pending ? 'Sending…' : 'Transfer ownership'}</button></div>
        </form>
    </div>;
}
