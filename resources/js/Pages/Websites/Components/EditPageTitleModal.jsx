import { useEffect, useRef, useState } from 'react';

export default function EditPageTitleModal({ page, saving = false, onClose, onSave }) {
    const [title, setTitle] = useState(page?.title || '');
    const inputRef = useRef(null);

    useEffect(() => {
        setTitle(page?.title || '');
        const timer = window.setTimeout(() => inputRef.current?.select(), 50);
        return () => window.clearTimeout(timer);
    }, [page?.id, page?.title]);

    useEffect(() => {
        const handleKeyDown = (event) => {
            if (event.key === 'Escape' && !saving) onClose();
        };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [saving, onClose]);

    if (!page) return null;

    const submit = (event) => {
        event.preventDefault();
        const nextTitle = title.trim();
        if (!nextTitle || saving) return;
        onSave(nextTitle);
    };

    return (
        <div className="fixed inset-0 z-[120] flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm" onMouseDown={(event) => { if (event.target === event.currentTarget && !saving) onClose(); }}>
            <form onSubmit={submit} className="cosmic-edit-page-modal w-full max-w-md rounded-2xl border p-5 shadow-2xl">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-emerald-500">Page settings</p>
                        <h2 className="mt-1 text-xl font-semibold tracking-tight">Edit page title</h2>
                        <p className="mt-1 text-sm text-slate-500">Rename the page without changing its current URL.</p>
                    </div>
                    <button type="button" onClick={onClose} disabled={saving} className="cosmic-modal-close inline-flex h-9 w-9 items-center justify-center rounded-xl text-lg transition" aria-label="Close">×</button>
                </div>

                <label className="mt-5 block text-xs font-semibold uppercase tracking-wide text-slate-500" htmlFor="cosmic-edit-page-title">Page title</label>
                <input
                    id="cosmic-edit-page-title"
                    ref={inputRef}
                    value={title}
                    onChange={(event) => setTitle(event.target.value)}
                    maxLength={255}
                    disabled={saving}
                    className="cosmic-edit-page-input mt-2 w-full rounded-xl border px-3.5 py-3 text-sm outline-none transition focus:ring-2 focus:ring-emerald-400/30"
                />
                <p className="mt-2 text-xs text-slate-500">Current URL: /{page.slug || 'untitled'}</p>

                <div className="mt-6 flex justify-end gap-2">
                    <button type="button" onClick={onClose} disabled={saving} className="cosmic-edit-page-cancel rounded-xl px-4 py-2.5 text-sm font-semibold transition">Cancel</button>
                    <button type="submit" disabled={saving || !title.trim()} className="cosmic-edit-page-save rounded-xl px-4 py-2.5 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-50">
                        {saving ? 'Saving...' : 'Save title'}
                    </button>
                </div>
            </form>
        </div>
    );
}
