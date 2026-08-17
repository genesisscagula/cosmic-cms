import { forwardRef, useImperativeHandle, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import MediaPickerModal from '@/Components/Media/MediaPickerModal';
import axios from 'axios';
import { usePage } from '@inertiajs/react';

export const EditableImageGallery = forwardRef(function EditableImageGallery({
    websiteId,
    images = [],
    onSave,
    title = 'Edit hero images',
    minItems = 1,
    maxItems = 12,
}, ref) {
    const { props: pageProps } = usePage();
    const trialMode = Boolean(pageProps?.trialMode);
    const trialToken = pageProps?.trialToken || null;
    const uploadRef = useRef(null);
    const [uploading, setUploading] = useState(false);
    const normalized = useMemo(() => (images || []).filter(Boolean), [images]);
    const [open, setOpen] = useState(false);
    const [pickerOpen, setPickerOpen] = useState(false);
    const [draft, setDraft] = useState(normalized);

    const openEditor = () => {
        setDraft(normalized);
        setOpen(true);
    };

    useImperativeHandle(ref, () => ({ openEditor }), [normalized]);

    const move = (index, delta) => {
        setDraft((current) => {
            const next = [...current];
            const target = index + delta;
            if (target < 0 || target >= next.length) return current;
            [next[index], next[target]] = [next[target], next[index]];
            return next;
        });
    };

    const remove = (index) => setDraft((current) => (
        current.length <= minItems ? current : current.filter((_, itemIndex) => itemIndex !== index)
    ));

    const addAssets = (assets) => {
        const urls = (Array.isArray(assets) ? assets : [assets]).map((asset) => asset?.url).filter(Boolean);
        if (!urls.length) return;
        setDraft((current) => {
            if (current.length >= maxItems) {
                return [...urls, ...current.slice(urls.length)].slice(0, maxItems);
            }
            return [...current, ...urls].slice(0, maxItems);
        });
    };


    const uploadTrialFiles = async (files) => {
        const list = Array.from(files || []).filter((file) => file.type?.startsWith('image/'));
        if (!list.length || !trialMode || !trialToken) return;
        setUploading(true);
        try {
            const urls = [];
            for (const file of list) {
                const form = new FormData();
                form.append('website_id', websiteId);
                form.append('image', file);
                const response = await axios.post(`/trials/${encodeURIComponent(trialToken)}/images/upload`, form, { headers: { Accept: 'application/json' } });
                if (response.data?.url) urls.push(response.data.url);
            }
            if (urls.length) {
                setDraft((current) => [...current, ...urls].slice(0, maxItems));
            }
        } finally {
            setUploading(false);
            if (uploadRef.current) uploadRef.current.value = '';
        }
    };

    const save = () => {
        const next = draft.filter(Boolean).slice(0, maxItems);
        if (next.length < minItems) return;
        onSave?.(next);
        setOpen(false);
    };

    return <>
        {open && typeof document !== 'undefined' ? createPortal(
            <div className="fixed inset-0 z-[1000010] flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm" onMouseDown={(event) => { if (event.target === event.currentTarget) setOpen(false); }}>
                <div className="w-full max-w-4xl overflow-hidden rounded-3xl border border-white/10 bg-[#151518] text-white shadow-2xl">
                    <header className="flex items-start justify-between gap-4 border-b border-white/10 p-5 sm:p-6">
                        <div><p className="text-xs font-bold uppercase tracking-[.22em] text-violet-300">Hero media</p><h3 className="mt-1 text-xl font-bold">{title}</h3><p className="mt-1 text-xs text-slate-500">{trialMode ? "Upload images directly from your computer, reorder them, or remove individual images." : "Upload/select multiple images, reorder them, or remove individual images."}</p></div>
                        <button type="button" onClick={() => setOpen(false)} className="rounded-xl border border-white/10 px-3 py-2 text-slate-400 hover:bg-white/10 hover:text-white">✕</button>
                    </header>
                    <div className="max-h-[62vh] overflow-y-auto p-5 sm:p-6">
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {draft.map((src, index) => <div key={`${src}-${index}`} className="overflow-hidden rounded-2xl border border-white/10 bg-black/20">
                                <img src={src} alt="" className="aspect-[4/3] w-full object-cover" />
                                <div className="flex items-center justify-between gap-2 p-3">
                                    <div className="flex gap-1.5"><button type="button" disabled={index === 0} onClick={() => move(index, -1)} className="rounded-lg border border-white/10 px-2.5 py-1.5 text-xs text-slate-300 disabled:opacity-25">←</button><button type="button" disabled={index === draft.length - 1} onClick={() => move(index, 1)} className="rounded-lg border border-white/10 px-2.5 py-1.5 text-xs text-slate-300 disabled:opacity-25">→</button></div>
                                    <button type="button" disabled={draft.length <= minItems} onClick={() => remove(index)} className="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-rose-300 hover:bg-rose-400/10 disabled:opacity-25">Remove</button>
                                </div>
                            </div>)}
                        </div>
                        {trialMode ? <>
                            <input ref={uploadRef} type="file" multiple accept="image/*" className="hidden" onChange={(event) => uploadTrialFiles(event.target.files)} />
                            <button type="button" disabled={uploading || draft.length >= maxItems} onClick={() => uploadRef.current?.click()} className="mt-5 w-full rounded-2xl border border-dashed border-violet-400/30 bg-violet-500/[.07] px-4 py-4 text-sm font-bold text-violet-200 hover:bg-violet-500/[.12] disabled:opacity-50">{uploading ? 'Uploading…' : '+ Upload images from computer'}</button>
                        </> : <button type="button" onClick={() => setPickerOpen(true)} className="mt-5 w-full rounded-2xl border border-dashed border-violet-400/30 bg-violet-500/[.07] px-4 py-4 text-sm font-bold text-violet-200 hover:bg-violet-500/[.12]">+ Add / upload images</button>}
                    </div>
                    <footer className="flex items-center justify-between gap-3 border-t border-white/10 p-5"><p className="text-xs text-slate-500">{draft.length} / {maxItems} images</p><div className="flex gap-2"><button type="button" onClick={() => setOpen(false)} className="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-slate-300">Cancel</button><button type="button" onClick={save} className="rounded-xl bg-violet-500 px-4 py-2.5 text-sm font-bold text-white">Apply images</button></div></footer>
                </div>
            </div>, document.body
        ) : null}
        {!trialMode && <MediaPickerModal open={pickerOpen} websiteId={websiteId} multiple title="Choose hero images" onClose={() => setPickerOpen(false)} onSelect={addAssets} />}
    </>;
});
