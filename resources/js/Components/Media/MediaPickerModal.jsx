import { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { showCosmicNotification } from '../CosmicNotification';

const sourceLabel = (source) => ({ upload:'Uploads', ai:'AI Generated', unsplash:'Unsplash', import:'Imported' }[source] || 'Media');

export default function MediaPickerModal({ open, websiteId, onClose, onSelect, title = 'Choose from Media Library', multiple = false, kind = 'image' }) {
    const [loading, setLoading] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [folders, setFolders] = useState([]);
    const [assets, setAssets] = useState([]);
    const [folderId, setFolderId] = useState(undefined);
    const [source, setSource] = useState('');
    const [search, setSearch] = useState('');
    const [selected, setSelected] = useState([]);
    const [creatingFolder, setCreatingFolder] = useState(false);
    const [newFolderName, setNewFolderName] = useState('');
    const uploadRef = useRef(null);

    const load = async () => {
        if (!websiteId || !open) return;
        setLoading(true);
        try {
            const params = { per_page: 120, sort: 'newest' };
            if (folderId !== undefined) params.folder_id = folderId || '';
            if (source) params.source = source;
            if (search.trim()) params.search = search.trim();
            const response = await axios.get(`/websites/${websiteId}/media-library`, { params, headers: { Accept: 'application/json' } });
            setFolders(response.data?.folders || []);
            setAssets(response.data?.assets?.data || []);
        } catch (error) {
            showCosmicNotification({ title:'Media Library unavailable', message:error.response?.data?.message || 'Could not load media.', tone:'error' });
        } finally { setLoading(false); }
    };

    useEffect(() => { if (open) { setSelected([]); load(); } }, [open, websiteId, folderId, source]);
    useEffect(() => { if (!open) return; const id=setTimeout(load, 250); return ()=>clearTimeout(id); }, [search]);
    useEffect(() => { if (!open) return; const handler=(e)=>{ if(e.key==='Escape') onClose?.(); }; document.addEventListener('keydown',handler); return()=>document.removeEventListener('keydown',handler); }, [open,onClose]);

    const roots = useMemo(() => folders.filter((folder) => !folder.parent_id), [folders]);
    const children = (parentId) => folders.filter((folder) => Number(folder.parent_id) === Number(parentId));
    const renderFolder = (folder, depth = 0) => <div key={folder.id}>
        <button type="button" onClick={()=>{setFolderId(folder.id);setSource('');}} className={`flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left text-sm transition ${Number(folderId)===Number(folder.id)?'bg-violet-500/15 text-violet-200':'text-slate-400 hover:bg-white/5 hover:text-white'}`} style={{paddingLeft:`${12 + depth*16}px`}}>
            <span className="text-base">📁</span><span className="min-w-0 flex-1 truncate">{folder.name}</span><span className="text-[10px] text-slate-600">{folder.assets_count || 0}</span>
        </button>
        {children(folder.id).map((child)=>renderFolder(child, depth+1))}
    </div>;

\n    const createFolder = async () => {\n        const name = newFolderName.trim();\n        if (!name || creatingFolder) return;\n\n        setCreatingFolder(true);\n        try {\n            const payload = { name };\n            // When a real folder is selected, create the new folder inside it.\n            // Smart views (All Media / Uploads / AI / Unsplash / Uncategorized) create at root.\n            if (folderId) payload.parent_id = Number(folderId);\n            const response = await axios.post(`/websites/${websiteId}/media-library/folders`, payload, { headers:{ Accept:'application/json' } });\n            const created = response.data?.folder;\n            setNewFolderName('');\n            await load();\n            if (created?.id) {\n                setFolderId(created.id);\n                setSource('');\n            }\n            showCosmicNotification({ title:'Folder created', message:`${name} is ready in your Media Library.`, tone:'success' });\n        } catch (error) {\n            showCosmicNotification({ title:'Could not create folder', message:error.response?.data?.message || error.response?.data?.errors?.name?.[0] || 'Please try a different folder name.', tone:'error' });\n        } finally {\n            setCreatingFolder(false);\n        }\n    };\n
    const toggle = (asset) => {
        if (!multiple) { setSelected([asset]); return; }
        setSelected((current)=>current.some((item)=>item.uuid===asset.uuid) ? current.filter((item)=>item.uuid!==asset.uuid) : [...current, asset]);
    };

    const uploadFiles = async (files) => {
        const list = Array.from(files || []).filter((file)=>file.type?.startsWith('image/'));
        if (!list.length) return;
        setUploading(true);
        try {
            let last = null;
            for (const file of list) {
                const form = new FormData();
                form.append('image', file, file.name);
                if (folderId) form.append('folder_id', String(folderId));
                form.append('source', 'upload');
                form.append('kind', kind);
                const response = await axios.post(`/websites/${websiteId}/media-library/assets`, form, { headers:{ Accept:'application/json' } });
                last = response.data?.asset || last;
            }
            await load();
            if (last && !multiple) setSelected([last]);
            showCosmicNotification({ title:'Media uploaded', message:list.length === 1 ? 'Image added to the Media Library.' : `${list.length} images added to the Media Library.`, tone:'success' });
        } catch (error) {
            showCosmicNotification({ title:'Upload failed', message:error.response?.data?.message || 'The image could not be uploaded.', tone:'error' });
        } finally { setUploading(false); if(uploadRef.current) uploadRef.current.value=''; }
    };

    if (!open || typeof document === 'undefined') return null;
    return createPortal(<div className="cosmic-media-picker-overlay fixed inset-0 z-[1000005] flex items-center justify-center bg-slate-950/85 p-3 backdrop-blur-xl" onMouseDown={(e)=>{if(e.target===e.currentTarget)onClose?.();}}>
        <div className="cosmic-media-picker-modal flex h-[88vh] w-full max-w-6xl overflow-hidden rounded-3xl border border-white/10 bg-[#0b0b0f] shadow-2xl">
            <aside className="cosmic-media-picker-sidebar hidden w-64 shrink-0 border-r border-white/10 bg-white/[0.02] p-4 md:block">
                <div className="mb-4"><p className="text-[10px] font-bold uppercase tracking-[0.18em] text-violet-300">Cosmic Media</p><p className="mt-1 text-sm text-slate-500">Choose an existing image or upload a new one.</p></div>
                <div className="space-y-1">
                    <button type="button" onClick={()=>{setFolderId(undefined);setSource('');}} className={`w-full rounded-xl px-3 py-2 text-left text-sm ${folderId===undefined && !source?'bg-violet-500/15 text-violet-200':'text-slate-400 hover:bg-white/5'}`}>▦ All Media</button>
                    {[['upload','↑ Uploads'],['ai','✦ AI Generated'],['unsplash','◉ Unsplash']].map(([value,label])=><button key={value} type="button" onClick={()=>{setSource(value);setFolderId(undefined);}} className={`w-full rounded-xl px-3 py-2 text-left text-sm ${source===value?'bg-violet-500/15 text-violet-200':'text-slate-400 hover:bg-white/5'}`}>{label}</button>)}
                    <button type="button" onClick={()=>{setFolderId(null);setSource('');}} className={`w-full rounded-xl px-3 py-2 text-left text-sm ${folderId===null && !source?'bg-violet-500/15 text-violet-200':'text-slate-400 hover:bg-white/5'}`}>◇ Uncategorized</button>
                </div>
                <div className="my-4 border-t border-white/10"/>
                <div className="mb-2 flex items-center justify-between gap-2 px-2">
                    <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-600">Folders</p>
                    <button type="button" onClick={()=>setNewFolderName((value)=>value || 'New Folder')} className="cosmic-media-picker-new-folder rounded-lg px-2 py-1 text-[11px] font-bold text-violet-300 transition hover:bg-violet-500/10 hover:text-violet-200">+ New Folder</button>
                </div>
                {newFolderName !== '' ? <div className="cosmic-media-picker-folder-create mb-3 rounded-xl border border-white/10 bg-black/20 p-2">
                    <input autoFocus value={newFolderName} onChange={(e)=>setNewFolderName(e.target.value)} onKeyDown={(e)=>{if(e.key==='Enter'){e.preventDefault();createFolder();} if(e.key==='Escape'){setNewFolderName('');}}} placeholder="Folder name" className="w-full rounded-lg border border-white/10 bg-black/25 px-2.5 py-2 text-xs text-white outline-none focus:border-violet-400"/>
                    <div className="mt-2 flex gap-1.5">
                        <button type="button" disabled={creatingFolder || !newFolderName.trim()} onClick={createFolder} className="flex-1 rounded-lg bg-violet-500 px-2 py-1.5 text-[11px] font-bold text-white disabled:opacity-40">{creatingFolder?'Creating…':'Create'}</button>
                        <button type="button" onClick={()=>setNewFolderName('')} className="rounded-lg border border-white/10 px-2.5 py-1.5 text-[11px] text-slate-400">Cancel</button>
                    </div>
                    <p className="mt-1.5 px-0.5 text-[10px] text-slate-600">{folderId ? 'Creates inside the selected folder.' : 'Creates at the Media Library root.'}</p>
                </div> : null}
                <div className="max-h-[50vh] overflow-y-auto">{roots.length ? roots.map((folder)=>renderFolder(folder)) : <p className="px-3 py-2 text-xs text-slate-600">No folders yet.</p>}</div>
            </aside>
            <section className="cosmic-media-picker-content flex min-w-0 flex-1 flex-col">
                <header className="border-b border-white/10 p-4 sm:p-5"><div className="flex items-start justify-between gap-4"><div><h2 className="text-lg font-bold text-white">{title}</h2><p className="mt-1 text-xs text-slate-500">{source ? sourceLabel(source) : folderId ? (folders.find((f)=>Number(f.id)===Number(folderId))?.name || 'Folder') : 'Media Library'}</p></div><button type="button" onClick={onClose} className="rounded-xl border border-white/10 px-3 py-2 text-slate-400 hover:bg-white/10 hover:text-white">✕</button></div>
                    <div className="mt-4 flex flex-col gap-2 sm:flex-row"><input value={search} onChange={(e)=>setSearch(e.target.value)} placeholder="Search media…" className="min-w-0 flex-1 rounded-xl border border-white/10 bg-black/25 px-3 py-2.5 text-sm text-white outline-none focus:border-violet-400"/><input ref={uploadRef} type="file" multiple={multiple} accept="image/*" className="hidden" onChange={(e)=>uploadFiles(e.target.files)}/><button type="button" disabled={uploading} onClick={()=>uploadRef.current?.click()} className="rounded-xl bg-violet-500 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50">{uploading?'Uploading…':'↑ Upload'}</button></div>
                </header>
                <div className="flex-1 overflow-y-auto p-4 sm:p-5" onDragOver={(e)=>e.preventDefault()} onDrop={(e)=>{e.preventDefault();uploadFiles(e.dataTransfer.files);}}>
                    {loading ? <div className="grid h-full place-items-center text-sm text-slate-500">Loading media…</div> : assets.length ? <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">{assets.map((asset)=>{const active=selected.some((item)=>item.uuid===asset.uuid);return <button key={asset.uuid} type="button" onClick={()=>toggle(asset)} onDoubleClick={()=>{onSelect?.(multiple?[asset]:asset);onClose?.();}} className={`group overflow-hidden rounded-2xl border text-left transition ${active?'border-violet-400 ring-2 ring-violet-500/20':'border-white/10 hover:border-white/25'}`}><div className="aspect-square overflow-hidden bg-black/25"><img src={asset.url} alt={asset.alt_text || asset.original_name || ''} className="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]"/></div><div className="p-2.5"><p className="truncate text-xs font-semibold text-white">{asset.original_name}</p><p className="mt-1 truncate text-[10px] uppercase tracking-wide text-slate-600">{sourceLabel(asset.source)}</p></div></button>;})}</div> : <div className="grid h-full min-h-64 place-items-center rounded-2xl border border-dashed border-white/10 text-center"><div><div className="text-4xl">🖼️</div><p className="mt-3 text-sm font-semibold text-white">No images here yet</p><p className="mt-1 text-xs text-slate-500">Upload or generate media and it will appear here.</p></div></div>}
                </div>
                <footer className="flex items-center justify-between gap-3 border-t border-white/10 p-4"><p className="text-xs text-slate-500">{selected.length ? `${selected.length} selected` : 'Select an image to continue'}</p><div className="flex gap-2"><button type="button" onClick={onClose} className="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-slate-300">Cancel</button><button type="button" disabled={!selected.length} onClick={()=>{onSelect?.(multiple?selected:selected[0]);onClose?.();}} className="rounded-xl bg-violet-500 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-40">Use {multiple?'Images':'Image'}</button></div></footer>
            </section>
        </div>
    </div>, document.body);
}
