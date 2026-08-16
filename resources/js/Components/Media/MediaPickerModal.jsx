import { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { confirmCosmicAction, showCosmicNotification } from '../CosmicNotification';

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
    const [folderMenu, setFolderMenu] = useState(null);
    const [folderDialog, setFolderDialog] = useState(null);
    const [folderPending, setFolderPending] = useState(false);
    const [expandedFolders, setExpandedFolders] = useState(() => new Set());
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
    useEffect(() => { if (!open) return; const handler=(e)=>{ if(e.key==='Escape') { if (folderMenu || folderDialog) { setFolderMenu(null); setFolderDialog(null); } else onClose?.(); } }; document.addEventListener('keydown',handler); return()=>document.removeEventListener('keydown',handler); }, [open,onClose]);

    const roots = useMemo(() => folders.filter((folder) => !folder.parent_id), [folders]);
    const children = (parentId) => folders.filter((folder) => Number(folder.parent_id) === Number(parentId));
    const openFolderMenu = (event, folder) => {
        event.preventDefault();
        event.stopPropagation();
        const x = Math.min(event.clientX, window.innerWidth - 220);
        const y = Math.min(event.clientY, window.innerHeight - 180);
        setFolderMenu({ folder, x, y });
    };
    const renderFolder = (folder, depth = 0) => {
        const childFolders = children(folder.id);
        const hasChildren = childFolders.length > 0;
        const expanded = expandedFolders.has(Number(folder.id));
        return <div key={folder.id}>
            <div className="group relative flex items-center" style={{paddingLeft:`${depth*16}px`}} onDragOver={(event)=>{ if ([...event.dataTransfer.types].includes('application/x-cosmic-media')) { event.preventDefault(); event.dataTransfer.dropEffect='move'; } }} onDrop={(event)=>{ if (![...event.dataTransfer.types].includes('application/x-cosmic-media')) return; event.preventDefault(); event.stopPropagation(); const ids=parseDraggedIds(event); if (ids.length) moveAssets(ids, folder.id); }}>
                <button type="button" onClick={()=>{ if (!hasChildren) return; setExpandedFolders((current)=>{const next=new Set(current); const id=Number(folder.id); next.has(id)?next.delete(id):next.add(id); return next;}); }} className={`flex h-8 w-6 shrink-0 items-center justify-center text-[10px] ${hasChildren?'text-slate-500 hover:text-white':'text-transparent'}`} aria-label={hasChildren ? `${expanded?'Collapse':'Expand'} ${folder.name}` : undefined}>{expanded?'▼':'▶'}</button>
                <button type="button" onClick={()=>{setFolderId(folder.id);setSource('');}} onContextMenu={(event)=>openFolderMenu(event, folder)} className={`flex min-w-0 flex-1 items-center gap-2 rounded-xl px-2 py-2 pr-9 text-left text-sm transition ${Number(folderId)===Number(folder.id)?'bg-violet-500/15 text-violet-200':'text-slate-400 hover:bg-white/5 hover:text-white'}`}>
                    <span className="text-base">📁</span><span className="min-w-0 flex-1 truncate">{folder.name}</span><span className="text-[10px] text-slate-600">{folder.assets_count || 0}</span>
                </button>
                <button type="button" onClick={(event)=>openFolderMenu(event, folder)} className="absolute right-1.5 rounded-lg px-1.5 py-1 text-xs font-bold tracking-widest text-slate-600 opacity-0 transition hover:bg-white/10 hover:text-white group-hover:opacity-100" aria-label={`Folder actions for ${folder.name}`}>•••</button>
            </div>
            {hasChildren && expanded ? childFolders.map((child)=>renderFolder(child, depth+1)) : null}
        </div>;
    };


    const createFolder = async () => {
        const name = newFolderName.trim();
        if (!name || creatingFolder) return;

        setCreatingFolder(true);
        try {
            const payload = { name };
            // When a real folder is selected, create the new folder inside it.
            // Smart views (All Media / Uploads / AI / Unsplash / Uncategorized) create at root.
            if (folderId) payload.parent_id = Number(folderId);
            const response = await axios.post(`/websites/${websiteId}/media-library/folders`, payload, { headers:{ Accept:'application/json' } });
            const created = response.data?.folder;
            setNewFolderName('');
            await load();
            if (created?.id) {
                setFolderId(created.id);
                setSource('');
            }
            showCosmicNotification({ title:'Folder created', message:`${name} is ready in your Media Library.`, tone:'success' });
        } catch (error) {
            showCosmicNotification({ title:'Could not create folder', message:error.response?.data?.message || error.response?.data?.errors?.name?.[0] || 'Please try a different folder name.', tone:'error' });
        } finally {
            setCreatingFolder(false);
        }
    };

    const renameFolder = async (name) => {
        const folder = folderDialog?.folder;
        if (!folder || !name.trim()) return;
        setFolderPending(true);
        try {
            await axios.patch(`/websites/${websiteId}/media-library/folders/${folder.id}`, { name: name.trim() }, { headers:{ Accept:'application/json' } });
            setFolderDialog(null);
            await load();
            showCosmicNotification({ title:'Folder renamed', message:'The folder name was updated.', tone:'success' });
        } catch (error) {
            showCosmicNotification({ title:'Could not rename folder', message:error.response?.data?.message || error.response?.data?.errors?.name?.[0] || 'The folder could not be renamed.', tone:'error' });
        } finally { setFolderPending(false); }
    };

    const deleteFolder = async (folder) => {
        if (!folder) return;
        const confirmed = await confirmCosmicAction({ title:`Delete ${folder.name}?`, message:'Only empty folders can be deleted. Media inside the folder is protected.', confirmLabel:'Delete folder', tone:'error' });
        if (!confirmed) return;
        try {
            await axios.delete(`/websites/${websiteId}/media-library/folders/${folder.id}`, { headers:{ Accept:'application/json' } });
            const wasOpen = Number(folderId) === Number(folder.id);
            if (wasOpen) setFolderId(undefined);
            setFolderMenu(null);
            if (!wasOpen) await load();
            showCosmicNotification({ title:'Folder deleted', message:'The empty folder was removed.', tone:'success' });
        } catch (error) {
            showCosmicNotification({ title:'Folder not deleted', message:error.response?.data?.message || error.response?.data?.errors?.folder?.[0] || 'Move or delete the folder contents first.', tone:'error' });
        }
    };

    const submitFolderDialog = async (event) => {
        event.preventDefault();
        const name = folderDialog?.name?.trim();
        if (!name || folderPending) return;
        if (folderDialog.mode === 'rename') return renameFolder(name);
        setFolderPending(true);
        try {
            const payload = { name, parent_id: folderDialog.parentId };
            const response = await axios.post(`/websites/${websiteId}/media-library/folders`, payload, { headers:{ Accept:'application/json' } });
            setFolderDialog(null);
            await load();
            if (response.data?.folder?.id) { setFolderId(response.data.folder.id); setSource(''); }
            showCosmicNotification({ title:'Folder created', message:`${name} is ready in your Media Library.`, tone:'success' });
        } catch (error) {
            showCosmicNotification({ title:'Could not create folder', message:error.response?.data?.message || error.response?.data?.errors?.name?.[0] || 'Please try a different folder name.', tone:'error' });
        } finally { setFolderPending(false); }
    };

    const toggle = (asset) => {
        if (!multiple) { setSelected([asset]); return; }
        setSelected((current)=>current.some((item)=>item.uuid===asset.uuid) ? current.filter((item)=>item.uuid!==asset.uuid) : [...current, asset]);
    };

    const parseDraggedIds = (event) => {
        try {
            const parsed = JSON.parse(event.dataTransfer.getData('application/x-cosmic-media'));
            return Array.isArray(parsed) ? [...new Set(parsed.map(String).filter(Boolean))] : [];
        } catch { return []; }
    };

    const moveAssets = async (ids, targetFolderId) => {
        const target = targetFolderId ? Number(targetFolderId) : null;
        const unique = [...new Set(ids)].filter(Boolean).filter((id) => {
            const asset = assets.find((item) => String(item.uuid) === String(id));
            const current = asset?.folder_id ? Number(asset.folder_id) : null;
            return !asset || current !== target;
        });
        if (!unique.length) return;
        try {
            await Promise.all(unique.map((id) => axios.patch(`/websites/${websiteId}/media-library/assets/${id}`, { folder_id: target }, { headers:{ Accept:'application/json' } })));
            setSelected([]);
            await load();
            const targetName = target ? folders.find((folder)=>Number(folder.id)===target)?.name : 'Uncategorized';
            showCosmicNotification({ title:'Media moved', message:`${unique.length} ${unique.length === 1 ? 'image' : 'images'} moved to ${targetName || 'folder'}.`, tone:'success' });
        } catch (error) {
            showCosmicNotification({ title:'Move failed', message:error.response?.data?.message || 'The image could not be moved.', tone:'error' });
        }
    };

    const dragStart = (event, asset) => {
        const selectedIds = selected.some((item)=>item.uuid===asset.uuid) ? selected.map((item)=>item.uuid) : [asset.uuid];
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('application/x-cosmic-media', JSON.stringify(selectedIds));
        event.dataTransfer.setData('text/plain', `${selectedIds.length} Cosmic media item(s)`);
        const ghost = document.createElement('div');
        ghost.style.cssText = 'position:fixed;left:-9999px;top:-9999px;width:48px;height:40px;border-radius:10px;overflow:hidden;background:#17171b;border:1px solid rgba(255,255,255,.18);box-shadow:0 10px 24px rgba(0,0,0,.45);pointer-events:none;';
        const image = document.createElement('img');
        image.src = asset.url; image.alt = ''; image.draggable = false;
        image.style.cssText = 'width:100%;height:100%;object-fit:cover;display:block;';
        ghost.appendChild(image);
        document.body.appendChild(ghost);
        event.dataTransfer.setDragImage(ghost, 24, 20);
        requestAnimationFrame(()=>setTimeout(()=>ghost.remove(), 0));
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
    return createPortal(<div className="cosmic-media-picker-overlay fixed inset-0 z-[1000200] flex items-center justify-center bg-slate-950/85 p-3 backdrop-blur-xl" onMouseDown={(e)=>{setFolderMenu(null);if(e.target===e.currentTarget)onClose?.();}}>
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
                    <div className="mt-4 flex flex-col gap-2 sm:flex-row"><input value={search} onChange={(e)=>setSearch(e.target.value)} placeholder="Search media…" className="min-w-0 flex-1 rounded-xl border border-white/10 bg-black/25 px-3 py-2.5 text-sm text-white outline-none focus:border-violet-400"/><input ref={uploadRef} type="file" multiple accept="image/*" className="hidden" onChange={(e)=>uploadFiles(e.target.files)}/><button type="button" disabled={uploading} onClick={()=>uploadRef.current?.click()} className="rounded-xl bg-violet-500 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50">{uploading?'Uploading…':'↑ Upload'}</button></div>
                </header>
                <div className="flex-1 overflow-y-auto p-4 sm:p-5" onDragOver={(e)=>{ const types=[...e.dataTransfer.types]; if (types.includes('Files') && !types.includes('application/x-cosmic-media')) e.preventDefault(); }} onDrop={(e)=>{ const types=[...e.dataTransfer.types]; if (types.includes('application/x-cosmic-media')) { e.preventDefault(); return; } if (types.includes('Files')) { e.preventDefault(); uploadFiles(e.dataTransfer.files); } }}>
                    {loading ? <div className="grid h-full place-items-center text-sm text-slate-500">Loading media…</div> : assets.length ? <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">{assets.map((asset)=>{const active=selected.some((item)=>item.uuid===asset.uuid);return <button key={asset.uuid} type="button" draggable onDragStart={(event)=>dragStart(event, asset)} onClick={()=>toggle(asset)} onDoubleClick={()=>{onSelect?.(multiple?[asset]:asset);onClose?.();}} className={`group cursor-grab overflow-hidden rounded-2xl border text-left transition active:cursor-grabbing ${active?'border-violet-400 ring-2 ring-violet-500/20':'border-white/10 hover:border-white/25'}`}><div className="aspect-square overflow-hidden bg-black/25"><img src={asset.url} draggable={false} alt={asset.alt_text || asset.original_name || ''} className="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]"/></div><div className="p-2.5"><p className="truncate text-xs font-semibold text-white">{asset.original_name}</p><p className="mt-1 truncate text-[10px] uppercase tracking-wide text-slate-600">{sourceLabel(asset.source)}</p></div></button>;})}</div> : <div className="grid h-full min-h-64 place-items-center rounded-2xl border border-dashed border-white/10 text-center"><div><div className="text-4xl">🖼️</div><p className="mt-3 text-sm font-semibold text-white">No images here yet</p><p className="mt-1 text-xs text-slate-500">Upload or generate media and it will appear here.</p></div></div>}
                </div>
                <footer className="flex items-center justify-between gap-3 border-t border-white/10 p-4"><p className="text-xs text-slate-500">{selected.length ? `${selected.length} selected` : 'Select an image to continue'}</p><div className="flex gap-2"><button type="button" onClick={onClose} className="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-slate-300">Cancel</button><button type="button" disabled={!selected.length} onClick={()=>{onSelect?.(multiple?selected:selected[0]);onClose?.();}} className="rounded-xl bg-violet-500 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-40">Use {multiple?'Images':'Image'}</button></div></footer>
            </section>
        </div>

        {folderMenu ? <div className="fixed z-[1000210] w-48 overflow-hidden rounded-xl border border-white/10 bg-[#1a1a1e] p-1.5 shadow-2xl shadow-black/60" style={{left:folderMenu.x,top:folderMenu.y}} onMouseDown={(e)=>e.stopPropagation()}>
            <button type="button" onClick={()=>{setFolderDialog({mode:'create',parentId:folderMenu.folder.id,name:''});setFolderMenu(null);}} className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-xs text-slate-300 hover:bg-white/[0.06] hover:text-white"><span className="w-4 text-center">＋</span> New subfolder</button>
            <button type="button" onClick={()=>{setFolderDialog({mode:'rename',folder:folderMenu.folder,parentId:folderMenu.folder.parent_id,name:folderMenu.folder.name});setFolderMenu(null);}} className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-xs text-slate-300 hover:bg-white/[0.06] hover:text-white"><span className="w-4 text-center">✎</span> Rename folder</button>
            <div className="my-1 h-px bg-white/[0.06]"/>
            <button type="button" onClick={()=>deleteFolder(folderMenu.folder)} className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-xs text-rose-300 hover:bg-rose-400/[0.08]"><span className="w-4 text-center">⌫</span> Delete folder</button>
        </div> : null}

        {folderDialog ? <div className="fixed inset-0 z-[1000220] flex items-center justify-center bg-black/55 p-4 backdrop-blur-sm" onMouseDown={(e)=>{if(e.target===e.currentTarget)setFolderDialog(null);}}>
            <form onSubmit={submitFolderDialog} className="w-full max-w-sm rounded-3xl border border-white/10 bg-[#151518] p-5 shadow-2xl shadow-black/60">
                <div><h3 className="text-sm font-bold text-white">{folderDialog.mode==='rename'?'Rename folder':'New subfolder'}</h3><p className="mt-1 text-xs text-slate-500">{folderDialog.mode==='rename'?'Update the folder name.':`Inside ${folders.find((folder)=>Number(folder.id)===Number(folderDialog.parentId))?.name || 'selected folder'}`}</p></div>
                <input autoFocus value={folderDialog.name || ''} onChange={(e)=>setFolderDialog((current)=>({...current,name:e.target.value}))} maxLength={120} placeholder="Folder name" className="mt-4 w-full rounded-xl border border-white/10 bg-black/25 px-3 py-2.5 text-sm text-white outline-none focus:border-violet-400"/>
                <div className="mt-5 flex justify-end gap-2"><button type="button" onClick={()=>setFolderDialog(null)} className="rounded-xl px-4 py-2 text-xs font-semibold text-slate-400 hover:bg-white/5 hover:text-white">Cancel</button><button type="submit" disabled={folderPending || !folderDialog.name?.trim()} className="rounded-xl bg-violet-500 px-4 py-2 text-xs font-bold text-white disabled:opacity-40">{folderPending?'Saving…':folderDialog.mode==='rename'?'Save name':'Create folder'}</button></div>
            </form>
        </div> : null}
    </div>, document.body);
}
