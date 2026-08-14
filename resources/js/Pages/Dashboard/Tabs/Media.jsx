import axios from "axios";
import { useEffect, useMemo, useRef, useState } from "react";
import { confirmCosmicAction, showCosmicNotification } from "../../../Components/CosmicNotification";

const SOURCE_LABELS = { upload: "Upload", ai: "AI Generated", unsplash: "Unsplash", import: "Imported" };
const SORT_OPTIONS = [
    ["newest", "Newest first"], ["oldest", "Oldest first"], ["name_asc", "Name A–Z"],
    ["name_desc", "Name Z–A"], ["size_desc", "Largest first"], ["size_asc", "Smallest first"],
];

function FolderIcon({ open = false, compact = false }) {
    return <span aria-hidden="true" className={`relative inline-block shrink-0 ${compact ? "h-4 w-5" : "h-5 w-6"}`}>
        <span className="absolute left-0 top-[2px] h-[5px] w-[10px] rounded-t-[3px] bg-gradient-to-r from-violet-400 to-fuchsia-400" />
        <span className={`absolute inset-x-0 bottom-0 rounded-[5px] border border-violet-300/20 bg-gradient-to-br from-violet-400/90 to-indigo-500/90 shadow-sm shadow-violet-950/30 ${open ? "h-[14px] -skew-x-3" : "h-[15px]"}`} />
        <span className="absolute inset-x-[3px] bottom-[3px] h-[6px] rounded-sm bg-white/15" />
    </span>;
}

function buildFolderTree(folders) {
    const map = new Map(folders.map((folder) => [folder.id, { ...folder, children: [] }]));
    const roots = [];
    map.forEach((folder) => {
        if (folder.parent_id && map.has(folder.parent_id)) map.get(folder.parent_id).children.push(folder);
        else roots.push(folder);
    });
    const sort = (items) => items.sort((a, b) => (a.sort_order - b.sort_order) || a.name.localeCompare(b.name)).map((item) => ({ ...item, children: sort(item.children) }));
    return sort(roots);
}

function formatBytes(bytes) {
    const value = Number(bytes || 0);
    if (!value) return "0 KB";
    const units = ["B", "KB", "MB", "GB"];
    const index = Math.min(Math.floor(Math.log(value) / Math.log(1024)), units.length - 1);
    const amount = value / (1024 ** index);
    return `${amount >= 10 || index === 0 ? amount.toFixed(0) : amount.toFixed(1)} ${units[index]}`;
}

function FolderDialog({ open, mode = "create", initialName = "", parentName = null, onClose, onSubmit, pending }) {
    const [name, setName] = useState(initialName);
    useEffect(() => { if (open) setName(initialName); }, [open, initialName]);
    if (!open) return null;
    return <div className="fixed inset-0 z-[10020] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
        <form onSubmit={(event) => { event.preventDefault(); if (name.trim()) onSubmit(name.trim()); }} className="cosmic-dialog-panel w-full max-w-sm rounded-3xl border border-white/10 bg-[#151518] p-5 shadow-2xl shadow-black/60">
            <div className="flex items-center gap-3"><span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-violet-400/10"><FolderIcon /></span><div><h2 className="font-semibold text-white">{mode === "rename" ? "Rename folder" : "New folder"}</h2><p className="mt-0.5 text-xs text-slate-500">{parentName ? `Inside ${parentName}` : "Organize your website media"}</p></div></div>
            <input autoFocus value={name} onChange={(event) => setName(event.target.value)} maxLength={120} placeholder="Folder name" className="mt-5 w-full rounded-xl border border-white/10 bg-white/[0.04] px-3 py-2.5 text-sm text-white placeholder:text-slate-600 focus:border-violet-400/40 focus:ring-violet-400/20" />
            <div className="mt-5 flex justify-end gap-2"><button type="button" onClick={onClose} className="rounded-xl px-4 py-2 text-xs font-semibold text-slate-400 hover:bg-white/5 hover:text-white">Cancel</button><button disabled={pending || !name.trim()} className="cosmic-solid-action rounded-xl bg-violet-600 px-4 py-2 text-xs font-semibold text-white shadow-lg shadow-violet-950/30 disabled:opacity-50">{pending ? "Saving…" : mode === "rename" ? "Save name" : "Create folder"}</button></div>
        </form>
    </div>;
}

function AssetDialog({ asset, onClose, onSave, pending }) {
    const [name, setName] = useState(asset?.original_name || "");
    const [alt, setAlt] = useState(asset?.alt_text || "");
    const [caption, setCaption] = useState(asset?.caption || "");
    useEffect(() => { setName(asset?.original_name || ""); setAlt(asset?.alt_text || ""); setCaption(asset?.caption || ""); }, [asset]);
    if (!asset) return null;
    return <div className="fixed inset-0 z-[10020] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
        <form onSubmit={(event) => { event.preventDefault(); onSave({ original_name: name.trim(), alt_text: alt.trim() || null, caption: caption.trim() || null }); }} className="cosmic-dialog-panel grid w-full max-w-2xl overflow-hidden rounded-3xl border border-white/10 bg-[#151518] shadow-2xl shadow-black/60 md:grid-cols-[230px_1fr]">
            <div className="flex min-h-56 items-center justify-center bg-black/20 p-4"><img src={asset.url} alt="" className="max-h-64 max-w-full rounded-xl object-contain shadow-lg" /></div>
            <div className="p-5"><div className="flex items-start justify-between gap-3"><div><h2 className="font-semibold text-white">Media details</h2><p className="mt-1 text-xs text-slate-500">{asset.width && asset.height ? `${asset.width} × ${asset.height} · ` : ""}{formatBytes(asset.size_bytes)}</p></div><button type="button" onClick={onClose} className="rounded-lg p-1.5 text-slate-500 hover:bg-white/5 hover:text-white">×</button></div>
                <label className="mt-4 block text-xs font-semibold text-slate-400">File name<input value={name} onChange={(e) => setName(e.target.value)} required maxLength={255} className="mt-1.5 w-full rounded-xl border border-white/10 bg-white/[0.04] px-3 py-2.5 text-sm text-white" /></label>
                <label className="mt-3 block text-xs font-semibold text-slate-400">Alt text<input value={alt} onChange={(e) => setAlt(e.target.value)} maxLength={255} placeholder="Describe the image for accessibility" className="mt-1.5 w-full rounded-xl border border-white/10 bg-white/[0.04] px-3 py-2.5 text-sm text-white" /></label>
                <label className="mt-3 block text-xs font-semibold text-slate-400">Caption<textarea value={caption} onChange={(e) => setCaption(e.target.value)} rows={3} maxLength={2000} className="mt-1.5 w-full resize-none rounded-xl border border-white/10 bg-white/[0.04] px-3 py-2.5 text-sm text-white" /></label>
                <div className="mt-5 flex justify-end gap-2"><button type="button" onClick={onClose} className="rounded-xl px-4 py-2 text-xs font-semibold text-slate-400 hover:text-white">Cancel</button><button disabled={pending || !name.trim()} className="cosmic-solid-action rounded-xl bg-violet-600 px-4 py-2 text-xs font-semibold text-white disabled:opacity-50">{pending ? "Saving…" : "Save details"}</button></div>
            </div>
        </form>
    </div>;
}

function FolderRow({ folder, depth, activeId, expanded, setExpanded, onOpen, onContext, onDropAssets }) {
    const hasChildren = folder.children.length > 0;
    const isExpanded = expanded.has(folder.id);
    const active = activeId === folder.id;
    return <div>
        <div className="group relative flex items-center" style={{ paddingLeft: `${depth * 14}px` }} onDragOver={(event) => { event.preventDefault(); event.dataTransfer.dropEffect = "move"; }} onDrop={(event) => { event.preventDefault(); onDropAssets(folder.id, event); }}>
            <button type="button" onClick={() => hasChildren && setExpanded((old) => { const next = new Set(old); next.has(folder.id) ? next.delete(folder.id) : next.add(folder.id); return next; })} className={`mr-0.5 flex h-7 w-5 items-center justify-center text-[10px] transition ${hasChildren ? "text-slate-500 hover:text-white" : "text-transparent"}`}>{isExpanded ? "▼" : "▶"}</button>
            <button type="button" onClick={() => onOpen(folder.id)} onContextMenu={(event) => { event.preventDefault(); onContext(event, folder); }} className={`flex min-w-0 flex-1 items-center gap-2 rounded-xl px-2 py-2 text-left text-xs transition ${active ? "bg-violet-400/[0.12] text-violet-100 ring-1 ring-violet-400/20" : "text-slate-400 hover:bg-white/[0.04] hover:text-white"}`}>
                <FolderIcon open={active} compact /><span className="min-w-0 flex-1 truncate font-medium">{folder.name}</span><span className="text-[10px] tabular-nums text-slate-600">{folder.assets_count || ""}</span>
            </button>
            <button type="button" onClick={(event) => onContext(event, folder)} className="absolute right-1 rounded-lg px-1.5 py-1 text-slate-600 opacity-0 transition hover:bg-white/5 hover:text-white group-hover:opacity-100" aria-label={`Folder actions for ${folder.name}`}>•••</button>
        </div>
        {hasChildren && isExpanded && <div>{folder.children.map((child) => <FolderRow key={child.id} folder={child} depth={depth + 1} activeId={activeId} expanded={expanded} setExpanded={setExpanded} onOpen={onOpen} onContext={onContext} onDropAssets={onDropAssets} />)}</div>}
    </div>;
}

function AssetCard({ asset, selected, viewMode, onSelect, onOpen, onContext, onDragStart }) {
    if (viewMode === "list") return <div draggable onDragStart={(event) => onDragStart(event, asset)} onContextMenu={(event) => { event.preventDefault(); onContext(event, asset); }} onDoubleClick={() => onOpen(asset)} className={`group grid min-w-[620px] cursor-default grid-cols-[34px_52px_minmax(0,1fr)_110px_110px_36px] items-center gap-3 rounded-xl border px-3 py-2 transition ${selected ? "border-violet-400/40 bg-violet-400/[0.08]" : "border-transparent hover:border-white/10 hover:bg-white/[0.03]"}`}>
        <input type="checkbox" checked={selected} onChange={(event) => onSelect(asset.uuid, event.nativeEvent)} onClick={(e) => e.stopPropagation()} className="h-4 w-4 rounded border-white/20 bg-black/20 text-violet-500 focus:ring-violet-400/30" />
        <div className="h-11 w-11 overflow-hidden rounded-lg bg-black/20"><img src={asset.url} alt="" draggable="false" className="h-full w-full object-cover" /></div>
        <div className="min-w-0"><p className="truncate text-sm font-medium text-slate-200">{asset.original_name}</p><p className="mt-0.5 text-[11px] text-slate-600">{asset.width && asset.height ? `${asset.width} × ${asset.height}` : asset.extension?.toUpperCase()}</p></div>
        <span className="truncate text-xs text-slate-500">{SOURCE_LABELS[asset.source] || asset.source}</span><span className="text-xs text-slate-500">{formatBytes(asset.size_bytes)}</span>
        <button type="button" onClick={(event) => onContext(event, asset)} className="rounded-lg px-1.5 py-1 text-slate-600 opacity-0 hover:bg-white/5 hover:text-white group-hover:opacity-100">•••</button>
    </div>;

    return <div draggable onDragStart={(event) => onDragStart(event, asset)} onContextMenu={(event) => { event.preventDefault(); onContext(event, asset); }} onDoubleClick={() => onOpen(asset)} className={`group relative overflow-hidden rounded-2xl border transition ${selected ? "border-violet-400/60 bg-violet-400/[0.08] shadow-lg shadow-violet-950/20 ring-2 ring-violet-400/20" : "border-white/[0.07] bg-white/[0.025] hover:-translate-y-0.5 hover:border-white/15 hover:bg-white/[0.04] hover:shadow-xl hover:shadow-black/20"}`}>
        <div className="relative aspect-[4/3] overflow-hidden bg-[radial-gradient(circle_at_center,rgba(255,255,255,.05),transparent_65%)]"><img src={asset.url} alt={asset.alt_text || ""} draggable="false" loading="lazy" className="h-full w-full object-cover transition duration-300 group-hover:scale-[1.025]" />
            <label className={`absolute left-2.5 top-2.5 flex h-7 w-7 cursor-pointer items-center justify-center rounded-lg border backdrop-blur-md transition ${selected ? "border-violet-300/50 bg-violet-500 text-white" : "border-white/15 bg-black/30 text-white opacity-0 group-hover:opacity-100"}`} onClick={(e) => e.stopPropagation()}><input type="checkbox" checked={selected} onChange={(event) => onSelect(asset.uuid, event.nativeEvent)} className="sr-only" /><span className="text-xs">{selected ? "✓" : ""}</span></label>
            <button type="button" onClick={(event) => onContext(event, asset)} className="absolute right-2.5 top-2.5 rounded-lg border border-white/15 bg-black/35 px-2 py-1 text-xs font-bold tracking-widest text-white opacity-0 backdrop-blur-md transition hover:bg-black/60 group-hover:opacity-100">•••</button>
            <span className="absolute bottom-2 left-2 rounded-md border border-white/10 bg-black/45 px-2 py-1 text-[9px] font-bold uppercase tracking-[0.12em] text-white/80 backdrop-blur-md">{SOURCE_LABELS[asset.source] || asset.source}</span>
        </div>
        <div className="p-3"><p className="truncate text-sm font-medium text-slate-200" title={asset.original_name}>{asset.original_name}</p><div className="mt-1.5 flex items-center justify-between gap-2 text-[11px] text-slate-600"><span>{asset.width && asset.height ? `${asset.width} × ${asset.height}` : asset.extension?.toUpperCase()}</span><span>{formatBytes(asset.size_bytes)}</span></div></div>
    </div>;
}

export default function Media({ websites = [] }) {
    const availableWebsites = useMemo(() => (websites || []).map((website) => ({ id: Number(website.id), name: website.name || "Untitled Website" })), [websites]);
    const [websiteId, setWebsiteId] = useState(() => {
        const requested = typeof window !== "undefined" ? Number(new URLSearchParams(window.location.search).get("website") || 0) : 0;
        return requested || Number(availableWebsites[0]?.id || 0);
    });
    const [location, setLocation] = useState({ type: "all", id: null });
    const [folders, setFolders] = useState([]);
    const [assets, setAssets] = useState([]);
    const [stats, setStats] = useState({ total: 0, recent: 0, uncategorized: 0, uploads: 0, ai_generated: 0, unsplash: 0 });
    const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 });
    const [page, setPage] = useState(1);
    const [query, setQuery] = useState("");
    const [debouncedQuery, setDebouncedQuery] = useState("");
    const [sort, setSort] = useState("newest");
    const [viewMode, setViewMode] = useState(() => typeof window !== "undefined" ? localStorage.getItem("cosmic-media-view") || "grid" : "grid");
    const [loading, setLoading] = useState(false);
    const [uploading, setUploading] = useState(null);
    const [selected, setSelected] = useState([]);
    const [expanded, setExpanded] = useState(new Set());
    const [folderDialog, setFolderDialog] = useState(null);
    const [folderPending, setFolderPending] = useState(false);
    const [editingAsset, setEditingAsset] = useState(null);
    const [assetPending, setAssetPending] = useState(false);
    const [contextMenu, setContextMenu] = useState(null);
    const [dropActive, setDropActive] = useState(false);
    const fileInputRef = useRef(null);
    const lastSelectedIndex = useRef(null);

    useEffect(() => {
        if (!availableWebsites.length) return;
        if (!availableWebsites.some((website) => website.id === Number(websiteId))) {
            setWebsiteId(availableWebsites[0].id);
        }
    }, [availableWebsites, websiteId]);
    useEffect(() => {
        setLocation({ type: "all", id: null });
        setFolders([]);
        setAssets([]);
        setSelected([]);
        setExpanded(new Set());
        setContextMenu(null);
        setFolderDialog(null);
        setEditingAsset(null);
    }, [websiteId]);

    useEffect(() => { const timer = setTimeout(() => setDebouncedQuery(query.trim()), 250); return () => clearTimeout(timer); }, [query]);
    useEffect(() => { if (typeof window !== "undefined") localStorage.setItem("cosmic-media-view", viewMode); }, [viewMode]);
    useEffect(() => { setPage(1); setSelected([]); lastSelectedIndex.current = null; }, [websiteId, location.type, location.id, debouncedQuery, sort]);
    useEffect(() => { const close = () => setContextMenu(null); window.addEventListener("click", close); window.addEventListener("blur", close); return () => { window.removeEventListener("click", close); window.removeEventListener("blur", close); }; }, []);

    const fetchLibrary = async () => {
        if (!websiteId) return;
        setLoading(true);
        try {
            const params = { per_page: 48, page, sort };
            if (debouncedQuery) params.search = debouncedQuery;
            if (location.type === "folder") params.folder_id = location.id;
            if (location.type === "uncategorized") params.folder_id = 0;
            if (["upload", "ai", "unsplash"].includes(location.type)) params.source = location.type;
            if (location.type === "recent") params.recent = 1;
            const { data } = await axios.get(route("media-library.index", websiteId), { params });
            setFolders(data.folders || []); setAssets(data.assets?.data || []); setStats(data.stats || {});
            setPagination({ current_page: data.assets?.current_page || 1, last_page: data.assets?.last_page || 1, total: data.assets?.total || 0 });
        } catch (error) {
            showCosmicNotification({ title: "Media Library unavailable", message: error.response?.data?.message || "The media library could not be loaded.", tone: "error" });
        } finally { setLoading(false); }
    };
    useEffect(() => { fetchLibrary(); }, [websiteId, location.type, location.id, debouncedQuery, sort, page]);

    const folderTree = useMemo(() => buildFolderTree(folders), [folders]);
    const folderMap = useMemo(() => new Map(folders.map((folder) => [folder.id, folder])), [folders]);
    const currentFolder = location.type === "folder" ? folderMap.get(location.id) : null;
    const breadcrumb = useMemo(() => {
        if (!currentFolder) return [];
        const chain = []; let cursor = currentFolder; let guard = 0;
        while (cursor && guard++ < 40) { chain.unshift(cursor); cursor = cursor.parent_id ? folderMap.get(cursor.parent_id) : null; }
        return chain;
    }, [currentFolder, folderMap]);

    const openFolder = (id) => { setLocation({ type: "folder", id }); setExpanded((old) => { const next = new Set(old); next.add(id); return next; }); };
    const smartLocation = (type) => setLocation({ type, id: null });
    const uploadFolderId = location.type === "folder" ? location.id : null;

    const createFolder = async (name) => {
        if (!websiteId) return; setFolderPending(true);
        try {
            const parentId = folderDialog?.parentId ?? (location.type === "folder" ? location.id : null);
            await axios.post(route("media-library.folders.store", websiteId), { name, parent_id: parentId });
            setFolderDialog(null); await fetchLibrary();
            if (parentId) setExpanded((old) => new Set([...old, parentId]));
            showCosmicNotification({ title: "Folder created", message: `${name} is ready for your media.`, tone: "success" });
        } catch (error) { showCosmicNotification({ title: "Folder not created", message: error.response?.data?.message || error.response?.data?.errors?.name?.[0] || "Please try another folder name.", tone: "error" }); }
        finally { setFolderPending(false); }
    };
    const renameFolder = async (name) => {
        const folder = folderDialog?.folder; if (!folder) return; setFolderPending(true);
        try { await axios.patch(route("media-library.folders.update", [websiteId, folder.id]), { name }); setFolderDialog(null); await fetchLibrary(); }
        catch (error) { showCosmicNotification({ title: "Folder not renamed", message: error.response?.data?.message || error.response?.data?.errors?.name?.[0] || "The folder name could not be changed.", tone: "error" }); }
        finally { setFolderPending(false); }
    };
    const deleteFolder = async (folder) => {
        if (!await confirmCosmicAction({ title: `Delete ${folder.name}?`, message: "Only empty folders can be deleted. Your media files are protected from accidental folder deletion.", confirmLabel: "Delete folder", tone: "error" })) return;
        try { await axios.delete(route("media-library.folders.destroy", [websiteId, folder.id])); if (location.type === "folder" && location.id === folder.id) smartLocation("all"); else await fetchLibrary(); }
        catch (error) { showCosmicNotification({ title: "Folder not deleted", message: error.response?.data?.message || error.response?.data?.errors?.folder?.[0] || "Move or delete the folder contents first.", tone: "error" }); }
    };

    const uploadFiles = async (files) => {
        const images = [...files].filter((file) => file.type.startsWith("image/"));
        if (!images.length || !websiteId) return;
        let completed = 0; let failed = 0; setUploading({ current: 0, total: images.length });
        for (const file of images) {
            try { const form = new FormData(); form.append("image", file); if (uploadFolderId) form.append("folder_id", uploadFolderId); form.append("source", "upload"); await axios.post(route("media-library.assets.store", websiteId), form, { headers: { "Content-Type": "multipart/form-data" } }); completed += 1; }
            catch { failed += 1; }
            setUploading({ current: completed + failed, total: images.length });
        }
        setUploading(null); if (fileInputRef.current) fileInputRef.current.value = ""; await fetchLibrary();
        showCosmicNotification({ title: failed ? "Upload finished with issues" : "Upload complete", message: failed ? `${completed} uploaded · ${failed} failed. Images can be up to 12 MB each.` : `${completed} ${completed === 1 ? "image" : "images"} added to ${currentFolder?.name || "Uncategorized"}.`, tone: failed ? "error" : "success" });
    };

    const updateAsset = async (assetKey, payload, quiet = false) => {
        const { data } = await axios.patch(route("media-library.assets.update", [websiteId, assetKey]), payload);
        if (!quiet) setAssets((old) => old.map((item) => item.uuid === assetKey ? data.asset : item));
        return data.asset;
    };
    const saveAssetDetails = async (payload) => {
        if (!editingAsset) return; setAssetPending(true);
        try { await updateAsset(editingAsset.uuid, payload); setEditingAsset(null); showCosmicNotification({ title: "Media updated", message: "File details were saved.", tone: "success" }); }
        catch (error) { showCosmicNotification({ title: "Media not updated", message: error.response?.data?.message || "The file details could not be saved.", tone: "error" }); }
        finally { setAssetPending(false); }
    };
    const deleteAssets = async (ids) => {
        const unique = [...new Set(ids)]; if (!unique.length) return;
        if (!await confirmCosmicAction({ title: `Move ${unique.length} ${unique.length === 1 ? "image" : "images"} to Trash?`, message: "This is a safe soft delete. Physical files are not removed in Patch 2, so published pages remain protected.", confirmLabel: "Move to Trash", tone: "error" })) return;
        let failed = 0;
        await Promise.all(unique.map((id) => axios.delete(route("media-library.assets.destroy", [websiteId, id])).catch(() => { failed += 1; })));
        setSelected([]); await fetchLibrary();
        showCosmicNotification({ title: failed ? "Some items were not removed" : "Moved to Trash", message: failed ? `${unique.length - failed} removed · ${failed} failed.` : `${unique.length} ${unique.length === 1 ? "image" : "images"} removed from the active library.`, tone: failed ? "error" : "success" });
    };

    const moveAssets = async (ids, folderId) => {
        const targetFolderId = folderId ? Number(folderId) : null;
        const unique = [...new Set(ids)].filter(Boolean).filter((id) => {
            const asset = assets.find((item) => String(item.uuid) === String(id));
            const currentFolderId = asset?.folder_id ? Number(asset.folder_id) : null;
            return !asset || currentFolderId !== targetFolderId;
        });
        if (!unique.length) return; // Same-folder drops are no-ops, never duplicate media.
        try { await Promise.all(unique.map((id) => updateAsset(id, { folder_id: targetFolderId }, true))); setSelected([]); await fetchLibrary(); const target = targetFolderId ? folderMap.get(targetFolderId)?.name : "Uncategorized"; showCosmicNotification({ title: "Media moved", message: `${unique.length} ${unique.length === 1 ? "image" : "images"} moved to ${target}.`, tone: "success" }); }
        catch (error) { showCosmicNotification({ title: "Move failed", message: error.response?.data?.message || "One or more images could not be moved.", tone: "error" }); }
    };
    const parseDraggedIds = (event) => { try { const parsed = JSON.parse(event.dataTransfer.getData("application/x-cosmic-media")); return Array.isArray(parsed) ? parsed.map(String).filter(Boolean) : []; } catch { return []; } };
    const dropAssetsToFolder = (folderId, event) => { const ids = parseDraggedIds(event); if (ids.length) moveAssets(ids, folderId); };
    const dragStart = (event, asset) => {
        const ids = selected.includes(asset.uuid) ? selected : [asset.uuid];
        event.dataTransfer.effectAllowed = "move";
        event.dataTransfer.setData("application/x-cosmic-media", JSON.stringify(ids));
        event.dataTransfer.setData("text/plain", `${ids.length} Cosmic media item(s)`);

        // Keep the drag ghost compact so it never covers the folder tree/drop target.
        const ghost = document.createElement("div");
        ghost.style.cssText = "position:fixed;left:-9999px;top:-9999px;width:48px;height:40px;border-radius:12px;overflow:hidden;background:#17171b;border:1px solid rgba(255,255,255,.18);box-shadow:0 12px 28px rgba(0,0,0,.45);pointer-events:none;";
        const image = document.createElement("img");
        image.src = asset.url;
        image.alt = "";
        image.style.cssText = "width:100%;height:100%;object-fit:cover;display:block;";
        ghost.appendChild(image);
        if (ids.length > 1) {
            const badge = document.createElement("span");
            badge.textContent = String(ids.length);
            badge.style.cssText = "position:absolute;right:6px;bottom:6px;min-width:20px;height:20px;padding:0 5px;border-radius:999px;background:#7c3aed;color:white;font:700 11px/20px system-ui;text-align:center;";
            ghost.style.position = "fixed";
            ghost.appendChild(badge);
        }
        document.body.appendChild(ghost);
        event.dataTransfer.setDragImage(ghost, 24, 20);
        requestAnimationFrame(() => setTimeout(() => ghost.remove(), 0));
    };

    const selectAsset = (id, nativeEvent) => {
        const index = assets.findIndex((asset) => asset.uuid === id);
        if (nativeEvent?.shiftKey && lastSelectedIndex.current !== null) {
            const [start, end] = [lastSelectedIndex.current, index].sort((a, b) => a - b); const range = assets.slice(start, end + 1).map((asset) => asset.uuid); setSelected((old) => [...new Set([...old, ...range])]);
        } else { setSelected((old) => old.includes(id) ? old.filter((item) => item !== id) : [...old, id]); lastSelectedIndex.current = index; }
    };

    const copyUrl = async (asset) => { try { await navigator.clipboard.writeText(new URL(asset.url, window.location.origin).toString()); showCosmicNotification({ title: "URL copied", message: "The media URL is ready to paste.", tone: "success" }); } catch { showCosmicNotification({ title: "Could not copy URL", message: "Your browser blocked clipboard access.", tone: "error" }); } };
    const openAssetContext = (event, asset) => { event.stopPropagation(); const x = Math.min(event.clientX, window.innerWidth - 210); const y = Math.min(event.clientY, window.innerHeight - 190); setContextMenu({ type: "asset", item: asset, x, y }); };
    const openFolderContext = (event, folder) => { event.stopPropagation(); const x = Math.min(event.clientX, window.innerWidth - 220); const y = Math.min(event.clientY, window.innerHeight - 190); setContextMenu({ type: "folder", item: folder, x, y }); };

    const locationTitle = currentFolder?.name || ({ all: "All Media", uncategorized: "Uncategorized", upload: "Uploads", ai: "AI Generated", unsplash: "Unsplash", recent: "Recent" }[location.type] || "Media");
    const smartItems = [
        ["all", "All Media", "▦", stats.total], ["recent", "Recent", "◷", stats.recent], ["uncategorized", "Uncategorized", "◇", stats.uncategorized],
        ["upload", "Uploads", "↑", stats.uploads], ["ai", "AI Generated", "✦", stats.ai_generated], ["unsplash", "Unsplash", "◉", stats.unsplash],
    ];
    if (!availableWebsites.length) return <section><p className="text-sm font-medium text-violet-300">Media Library</p><h1 className="mt-2 text-3xl font-semibold text-white">Media</h1><div className="mt-8 rounded-3xl border border-dashed border-white/10 p-12 text-center text-sm text-slate-500">Create a website first to start organizing media.</div></section>;

    return <section className="media-explorer min-w-0">
        <header className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between"><div><p className="text-sm font-medium text-violet-300">Cosmic Media Explorer</p><h1 className="mt-2 text-3xl font-semibold tracking-tight text-white sm:text-4xl">Media Library</h1><p className="mt-2 max-w-2xl text-sm text-slate-400">Keep every website asset organized in folders without changing its public file location.</p></div>
            <div className="flex flex-wrap items-center gap-2"><select value={websiteId} onChange={(e) => { setWebsiteId(Number(e.target.value)); smartLocation("all"); }} className="min-w-44 rounded-xl border border-white/10 bg-[#151518] px-3 py-2.5 text-xs font-semibold text-slate-300">{availableWebsites.map((website) => <option key={website.id} value={website.id}>{website.name}</option>)}</select><button type="button" onClick={() => setFolderDialog({ mode: "create", parentId: location.type === "folder" ? location.id : null })} className="rounded-xl border border-white/10 bg-white/[0.04] px-3.5 py-2.5 text-xs font-semibold text-slate-200 hover:border-white/20 hover:bg-white/[0.07]">+ New folder</button><button type="button" onClick={() => fileInputRef.current?.click()} className="cosmic-solid-action rounded-xl bg-violet-600 px-4 py-2.5 text-xs font-semibold text-white shadow-lg shadow-violet-950/30">↑ Upload media</button><input ref={fileInputRef} type="file" multiple accept="image/jpeg,image/png,image/gif,image/webp,image/avif,image/heic,image/heif" className="hidden" onChange={(e) => uploadFiles(e.target.files || [])} /></div>
        </header>

        <div className="mt-7 grid min-h-[650px] overflow-hidden rounded-[28px] border border-white/[0.08] bg-[#111113] shadow-2xl shadow-black/20 lg:grid-cols-[250px_minmax(0,1fr)]">
            <aside className="border-b border-white/[0.07] bg-black/10 p-3 lg:border-b-0 lg:border-r">
                <div className="px-2 pb-2 pt-1"><p className="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-600">Library</p></div>
                <nav className="space-y-0.5">{smartItems.map(([type, label, icon, count]) => <button key={type} type="button" onClick={() => smartLocation(type)} onDragOver={type === "uncategorized" ? (e) => { e.preventDefault(); e.dataTransfer.dropEffect = "move"; } : undefined} onDrop={type === "uncategorized" ? (e) => { e.preventDefault(); const ids = parseDraggedIds(e); if (ids.length) moveAssets(ids, null); } : undefined} className={`flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-left text-xs transition ${location.type === type ? "bg-white/[0.08] font-semibold text-white shadow-sm" : "text-slate-400 hover:bg-white/[0.04] hover:text-white"}`}><span className={`flex h-6 w-6 items-center justify-center rounded-lg ${location.type === type ? "bg-violet-400/15 text-violet-300" : "bg-white/[0.035] text-slate-500"}`}>{icon}</span><span className="flex-1">{label}</span><span className="text-[10px] tabular-nums text-slate-600">{count || ""}</span></button>)}</nav>
                <div className="mt-5 flex items-center justify-between px-2 pb-2"><p className="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-600">Folders</p><button type="button" onClick={() => setFolderDialog({ mode: "create", parentId: null })} className="flex h-6 w-6 items-center justify-center rounded-lg text-slate-500 hover:bg-white/5 hover:text-white" title="New top-level folder">+</button></div>
                <div className="max-h-[390px] overflow-y-auto pr-1 cosmic-scrollbar">{folderTree.length ? folderTree.map((folder) => <FolderRow key={folder.id} folder={folder} depth={0} activeId={location.type === "folder" ? location.id : null} expanded={expanded} setExpanded={setExpanded} onOpen={openFolder} onContext={openFolderContext} onDropAssets={dropAssetsToFolder} />) : <div className="mx-2 rounded-xl border border-dashed border-white/[0.07] px-3 py-4 text-center text-[11px] leading-5 text-slate-600">No folders yet.<br />Create one to start organizing.</div>}</div>
                <div className="mt-4 rounded-2xl border border-white/[0.06] bg-white/[0.025] p-3"><div className="flex items-center gap-2"><span className="flex h-8 w-8 items-center justify-center rounded-xl bg-violet-400/10 text-violet-300">✦</span><div><p className="text-[11px] font-semibold text-slate-300">Drag to organize</p><p className="mt-0.5 text-[10px] leading-4 text-slate-600">Drop selected images on any folder.</p></div></div></div>
            </aside>

            <main className="min-w-0 bg-white/[0.012]">
                <div className="border-b border-white/[0.07] px-4 py-3 sm:px-5"><div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between"><div className="flex min-w-0 items-center gap-1.5 text-xs"><button type="button" onClick={() => smartLocation("all")} className="rounded-lg px-2 py-1.5 font-semibold text-slate-400 hover:bg-white/5 hover:text-white">Media</button>{breadcrumb.map((folder) => <span key={folder.id} className="flex min-w-0 items-center gap-1.5"><span className="text-slate-700">›</span><button type="button" onClick={() => openFolder(folder.id)} className={`max-w-40 truncate rounded-lg px-2 py-1.5 ${folder.id === location.id ? "bg-white/[0.05] font-semibold text-white" : "text-slate-400 hover:text-white"}`}>{folder.name}</button></span>)}{!breadcrumb.length && location.type !== "all" && <><span className="text-slate-700">›</span><span className="rounded-lg bg-white/[0.05] px-2 py-1.5 font-semibold text-white">{locationTitle}</span></>}</div>
                    <div className="flex flex-wrap items-center gap-2"><div className="relative min-w-[190px] flex-1 sm:flex-none"><span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-600">⌕</span><input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Search media…" className="w-full rounded-xl border border-white/10 bg-white/[0.035] py-2 pl-8 pr-3 text-xs text-slate-200 placeholder:text-slate-600 sm:w-56" /></div><select value={sort} onChange={(e) => setSort(e.target.value)} className="rounded-xl border border-white/10 bg-[#151518] px-3 py-2 text-xs font-semibold text-slate-400">{SORT_OPTIONS.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select><div className="flex rounded-xl border border-white/10 bg-white/[0.025] p-1"><button type="button" onClick={() => setViewMode("grid")} className={`rounded-lg px-2.5 py-1 text-xs ${viewMode === "grid" ? "bg-white/10 text-white" : "text-slate-600 hover:text-white"}`} title="Grid view">▦</button><button type="button" onClick={() => setViewMode("list")} className={`rounded-lg px-2.5 py-1 text-xs ${viewMode === "list" ? "bg-white/10 text-white" : "text-slate-600 hover:text-white"}`} title="List view">☷</button></div></div></div>
                </div>

                <div className="flex min-h-12 items-center justify-between gap-3 border-b border-white/[0.05] px-5 py-2"><div className="min-w-0"><h2 className="truncate text-sm font-semibold text-white">{locationTitle}</h2><p className="mt-0.5 text-[10px] text-slate-600">{pagination.total} {pagination.total === 1 ? "item" : "items"}{debouncedQuery ? ` matching “${debouncedQuery}”` : ""}</p></div>{selected.length > 0 && <div className="flex flex-wrap items-center justify-end gap-2"><span className="rounded-lg bg-violet-400/10 px-2.5 py-1.5 text-[11px] font-semibold text-violet-200">{selected.length} selected</span><button type="button" onClick={() => setSelected([])} className="rounded-lg px-2.5 py-1.5 text-[11px] font-semibold text-slate-500 hover:text-white">Clear</button><button type="button" onClick={() => deleteAssets(selected)} className="rounded-lg border border-rose-400/15 bg-rose-400/[0.06] px-2.5 py-1.5 text-[11px] font-semibold text-rose-300 hover:bg-rose-400/10">Move to Trash</button></div>}</div>

                <div onDragEnter={(event) => { const types=[...event.dataTransfer.types]; if (types.includes("Files") && !types.includes("application/x-cosmic-media")) { event.preventDefault(); setDropActive(true); } }} onDragOver={(event) => { const types=[...event.dataTransfer.types]; if (types.includes("Files") && !types.includes("application/x-cosmic-media")) event.preventDefault(); }} onDragLeave={(event) => { if (event.currentTarget === event.target || !event.currentTarget.contains(event.relatedTarget)) setDropActive(false); }} onDrop={(event) => { const types=[...event.dataTransfer.types]; if (types.includes("application/x-cosmic-media")) { event.preventDefault(); setDropActive(false); return; } if (types.includes("Files")) { event.preventDefault(); setDropActive(false); uploadFiles(event.dataTransfer.files); } }} className="relative min-h-[520px] p-4 sm:p-5">
                    {dropActive && <div className="pointer-events-none absolute inset-3 z-20 flex items-center justify-center rounded-3xl border-2 border-dashed border-violet-400/60 bg-violet-500/10 backdrop-blur-sm"><div className="rounded-2xl border border-violet-300/20 bg-[#17171b]/95 px-8 py-6 text-center shadow-2xl"><span className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-violet-400/15 text-xl text-violet-200">↑</span><p className="mt-3 text-sm font-semibold text-white">Drop images to upload</p><p className="mt-1 text-xs text-slate-500">Into {currentFolder?.name || "Uncategorized"}</p></div></div>}
                    {loading ? <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">{Array.from({ length: 12 }).map((_, index) => <div key={index} className="overflow-hidden rounded-2xl border border-white/[0.05]"><div className="aspect-[4/3] animate-pulse bg-white/[0.04]" /><div className="space-y-2 p-3"><div className="h-3 w-3/4 animate-pulse rounded bg-white/[0.05]" /><div className="h-2.5 w-1/2 animate-pulse rounded bg-white/[0.035]" /></div></div>)}</div> : assets.length ? <div className={viewMode === "grid" ? "grid grid-cols-2 gap-4 sm:grid-cols-3 2xl:grid-cols-4" : "space-y-1 overflow-x-auto cosmic-scrollbar"}>{assets.map((asset) => <AssetCard key={asset.id} asset={asset} selected={selected.includes(asset.uuid)} viewMode={viewMode} onSelect={selectAsset} onOpen={setEditingAsset} onContext={openAssetContext} onDragStart={dragStart} />)}</div> : <div className="flex min-h-[430px] items-center justify-center"><div className="max-w-sm text-center"><span className="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl border border-white/[0.06] bg-white/[0.025] text-2xl text-slate-500">▧</span><h3 className="mt-4 text-sm font-semibold text-slate-300">{debouncedQuery ? "No matching media" : "This space is ready"}</h3><p className="mt-1.5 text-xs leading-5 text-slate-600">{debouncedQuery ? "Try a different search term or location." : `Upload images or drag them here. New uploads will go to ${currentFolder?.name || "Uncategorized"}.`}</p>{!debouncedQuery && <button type="button" onClick={() => fileInputRef.current?.click()} className="mt-4 rounded-xl border border-white/10 bg-white/[0.04] px-4 py-2 text-xs font-semibold text-slate-300 hover:text-white">Choose images</button>}</div></div>}
                </div>
                {pagination.last_page > 1 && <div className="flex items-center justify-between border-t border-white/[0.06] px-5 py-3"><p className="text-[11px] text-slate-600">Page {pagination.current_page} of {pagination.last_page}</p><div className="flex gap-2"><button type="button" disabled={page <= 1} onClick={() => setPage((p) => Math.max(1, p - 1))} className="rounded-lg border border-white/10 px-3 py-1.5 text-xs text-slate-400 disabled:opacity-30">Previous</button><button type="button" disabled={page >= pagination.last_page} onClick={() => setPage((p) => Math.min(pagination.last_page, p + 1))} className="rounded-lg border border-white/10 px-3 py-1.5 text-xs text-slate-400 disabled:opacity-30">Next</button></div></div>}
            </main>
        </div>

        {uploading && <div className="fixed bottom-5 right-5 z-[10010] w-72 rounded-2xl border border-white/10 bg-[#17171b] p-4 shadow-2xl shadow-black/50"><div className="flex items-center justify-between"><div><p className="text-xs font-semibold text-white">Uploading media</p><p className="mt-1 text-[11px] text-slate-500">{uploading.current} of {uploading.total} complete</p></div><span className="text-violet-300">↑</span></div><div className="mt-3 h-1.5 overflow-hidden rounded-full bg-white/[0.06]"><div className="h-full rounded-full bg-violet-500 transition-all" style={{ width: `${Math.round((uploading.current / uploading.total) * 100)}%` }} /></div></div>}
        <FolderDialog open={Boolean(folderDialog)} mode={folderDialog?.mode} initialName={folderDialog?.folder?.name || ""} parentName={folderDialog?.parentId ? folderMap.get(folderDialog.parentId)?.name : null} pending={folderPending} onClose={() => setFolderDialog(null)} onSubmit={folderDialog?.mode === "rename" ? renameFolder : createFolder} />
        <AssetDialog asset={editingAsset} pending={assetPending} onClose={() => setEditingAsset(null)} onSave={saveAssetDetails} />

        {contextMenu && <div className="cosmic-dialog-panel fixed z-[10030] w-48 overflow-hidden rounded-xl border border-white/10 bg-[#1a1a1e] p-1.5 shadow-2xl shadow-black/60" style={{ left: contextMenu.x, top: contextMenu.y }} onClick={(e) => e.stopPropagation()}>
            {contextMenu.type === "asset" ? <><button type="button" onClick={() => { setEditingAsset(contextMenu.item); setContextMenu(null); }} className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-xs text-slate-300 hover:bg-white/[0.06] hover:text-white"><span className="w-4 text-center">✎</span> Edit details</button><button type="button" onClick={() => { copyUrl(contextMenu.item); setContextMenu(null); }} className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-xs text-slate-300 hover:bg-white/[0.06] hover:text-white"><span className="w-4 text-center">⌁</span> Copy URL</button><button type="button" onClick={() => { moveAssets([contextMenu.item.uuid], null); setContextMenu(null); }} className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-xs text-slate-300 hover:bg-white/[0.06] hover:text-white"><span className="w-4 text-center">◇</span> Move to Uncategorized</button><div className="my-1 h-px bg-white/[0.06]" /><button type="button" onClick={() => { deleteAssets([contextMenu.item.uuid]); setContextMenu(null); }} className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-xs text-rose-300 hover:bg-rose-400/[0.08]"><span className="w-4 text-center">⌫</span> Move to Trash</button></> : <><button type="button" onClick={() => { setFolderDialog({ mode: "create", parentId: contextMenu.item.id }); setExpanded((old) => new Set([...old, contextMenu.item.id])); setContextMenu(null); }} className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-xs text-slate-300 hover:bg-white/[0.06] hover:text-white"><span className="w-4 text-center">＋</span> New subfolder</button><button type="button" onClick={() => { setFolderDialog({ mode: "rename", folder: contextMenu.item, parentId: contextMenu.item.parent_id }); setContextMenu(null); }} className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-xs text-slate-300 hover:bg-white/[0.06] hover:text-white"><span className="w-4 text-center">✎</span> Rename folder</button><div className="my-1 h-px bg-white/[0.06]" /><button type="button" onClick={() => { deleteFolder(contextMenu.item); setContextMenu(null); }} className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-xs text-rose-300 hover:bg-rose-400/[0.08]"><span className="w-4 text-center">⌫</span> Delete folder</button></>}
        </div>}
    </section>;
}
