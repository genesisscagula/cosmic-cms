import { useEffect, useState } from 'react';

export default function SavePageTemplateModal({ open, onClose, onSave, pageTitle = '', saving = false }) {
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');

    useEffect(() => {
        if (!open) return;
        setName(pageTitle ? `${pageTitle} Template` : 'My Page Template');
        setDescription('');
    }, [open, pageTitle]);

    if (!open) return null;

    const submit = (event) => {
        event.preventDefault();
        const cleanName = name.trim();
        if (!cleanName || saving) return;
        onSave?.({ name: cleanName, description: description.trim() });
    };

    return (
        <div className="fixed inset-0 z-[10150] flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm" onMouseDown={(e) => e.target === e.currentTarget && !saving && onClose?.()}>
            <form onSubmit={submit} className="w-full max-w-lg rounded-2xl border border-white/10 bg-[#0d0e12] p-6 text-white shadow-2xl">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <p className="text-[11px] font-extrabold uppercase tracking-[0.2em] text-violet-300">Saved Templates</p>
                        <h2 className="mt-1 text-xl font-bold">Save as Template</h2>
                        <p className="mt-1 text-sm leading-6 text-slate-400">Save this page design so you can reuse it on another page later.</p>
                    </div>
                    <button type="button" disabled={saving} onClick={onClose} className="rounded-lg p-2 text-slate-400 transition hover:bg-white/10 hover:text-white disabled:opacity-40" aria-label="Close">✕</button>
                </div>

                <label className="mt-6 block text-xs font-bold text-slate-300">Template name</label>
                <input autoFocus value={name} onChange={(e) => setName(e.target.value)} maxLength={140} className="mt-2 w-full rounded-xl border border-white/10 bg-white/[0.06] px-4 py-3 text-sm text-white outline-none transition placeholder:text-slate-600 focus:border-violet-400 focus:ring-2 focus:ring-violet-400/20" placeholder="e.g. Premium Services Page" />

                <label className="mt-4 block text-xs font-bold text-slate-300">Description <span className="font-normal text-slate-500">(optional)</span></label>
                <textarea value={description} onChange={(e) => setDescription(e.target.value)} maxLength={500} rows={3} className="mt-2 w-full resize-none rounded-xl border border-white/10 bg-white/[0.06] px-4 py-3 text-sm text-white outline-none transition placeholder:text-slate-600 focus:border-violet-400 focus:ring-2 focus:ring-violet-400/20" placeholder="What is this template best used for?" />

                <div className="mt-6 flex justify-end gap-2">
                    <button type="button" disabled={saving} onClick={onClose} className="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white disabled:opacity-40">Cancel</button>
                    <button type="submit" disabled={saving || !name.trim()} className="rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-violet-500 disabled:cursor-not-allowed disabled:opacity-50">{saving ? 'Saving…' : 'Save Template'}</button>
                </div>
            </form>
        </div>
    );
}
