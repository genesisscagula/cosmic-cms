import { useMemo, useState } from 'react';
import axios from 'axios';
import { showCosmicNotification, confirmCosmicAction } from '@/Components/CosmicNotification';

const slugify = (value = '') => value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');

const emptyEntry = (type) => ({
    id: null,
    title: '',
    slug: '',
    excerpt: '',
    content: '',
    status: 'draft',
    category: '',
    tags: [],
    featured_image_url: '',
    gallery: [],
    custom_fields: Object.fromEntries((type?.schema || []).map((field) => [field.key, ''])),
    seo_title: '',
    seo_description: '',
    og_image_url: '',
    is_featured: false,
});

function Field({ field, value, onChange }) {
    const common = 'mt-1 w-full rounded-xl border border-white/10 bg-white/[0.045] px-3 py-2 text-sm text-white outline-none transition focus:border-violet-400/50 focus:ring-2 focus:ring-violet-400/10';
    if (field.type === 'boolean') {
        return <label className="mt-2 flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" checked={Boolean(value)} onChange={(e) => onChange(e.target.checked)} className="rounded border-white/20 bg-white/5" /> Enabled</label>;
    }
    if (field.type === 'textarea') {
        return <textarea rows={3} className={common} value={value || ''} onChange={(e) => onChange(e.target.value)} placeholder={field.placeholder || ''} />;
    }
    return <input type={['date','datetime-local','url','number'].includes(field.type) ? (field.type === 'datetime' ? 'datetime-local' : field.type) : 'text'} className={common} value={value || ''} onChange={(e) => onChange(e.target.value)} placeholder={field.placeholder || ''} />;
}

export default function PostsUpdatesWorkspace({ website, initialWorkspace = { types: [] } }) {
    const [types, setTypes] = useState(initialWorkspace?.types || []);
    const [activeTypeId, setActiveTypeId] = useState(initialWorkspace?.types?.[0]?.id || null);
    const [editingEntry, setEditingEntry] = useState(null);
    const [entryDraft, setEntryDraft] = useState(null);
    const [slugTouched, setSlugTouched] = useState(false);
    const [saving, setSaving] = useState(false);
    const [showTypeModal, setShowTypeModal] = useState(false);
    const [showInstallModal, setShowInstallModal] = useState(false);
    const [installing, setInstalling] = useState(false);
    const [editingType, setEditingType] = useState(null);
    const [typeDraft, setTypeDraft] = useState({ name: '', singular_name: '', slug: '', description: '', icon: '◇', schema: [] });

    const activeType = useMemo(() => types.find((type) => type.id === activeTypeId) || types[0] || null, [types, activeTypeId]);

    const replaceEntry = (typeId, entry) => setTypes((current) => current.map((type) => type.id !== typeId ? type : ({
        ...type,
        entries: type.entries.some((item) => item.id === entry.id) ? type.entries.map((item) => item.id === entry.id ? entry : item) : [entry, ...type.entries],
        entries_count: type.entries.some((item) => item.id === entry.id) ? type.entries_count : type.entries_count + 1,
    })));

    const openNewEntry = () => {
        if (!activeType) return;
        setEditingEntry(null);
        setSlugTouched(false);
        setEntryDraft(emptyEntry(activeType));
    };

    const openEntry = (entry) => {
        setEditingEntry(entry);
        setSlugTouched(true);
        setEntryDraft({ ...emptyEntry(activeType), ...entry, tags: entry.tags || [], gallery: entry.gallery || [], custom_fields: entry.custom_fields || {} });
    };

    const setEntryField = (key, value) => {
        setEntryDraft((current) => {
            const next = { ...current, [key]: value };
            if (key === 'title' && !slugTouched) next.slug = slugify(value);
            return next;
        });
    };

    const saveEntry = async () => {
        if (!activeType || !entryDraft?.title?.trim()) return;
        setSaving(true);
        try {
            const payload = { ...entryDraft, tags: Array.isArray(entryDraft.tags) ? entryDraft.tags : String(entryDraft.tags || '').split(',').map((v) => v.trim()).filter(Boolean) };
            const response = editingEntry
                ? await axios.put(route('content-entries.update', [website.id, activeType.id, editingEntry.id]), payload)
                : await axios.post(route('content-entries.store', [website.id, activeType.id]), payload);
            replaceEntry(activeType.id, response.data.entry);
            setEditingEntry(response.data.entry);
            setEntryDraft(response.data.entry);
            setSlugTouched(true);
            showCosmicNotification({ title: editingEntry ? 'Entry updated' : 'Entry created', message: `${activeType.singular_name} saved successfully.`, tone: 'success' });
        } catch (error) {
            showCosmicNotification({ title: 'Unable to save entry', message: error.response?.data?.message || 'Check the fields and try again.', tone: 'error' });
        } finally { setSaving(false); }
    };

    const duplicateEntry = async (entry) => {
        try {
            const response = await axios.post(route('content-entries.duplicate', [website.id, activeType.id, entry.id]));
            replaceEntry(activeType.id, response.data.entry);
            showCosmicNotification({ title: 'Entry duplicated', message: 'A draft copy is ready to edit.', tone: 'success' });
        } catch { showCosmicNotification({ title: 'Unable to duplicate entry', tone: 'error' }); }
    };

    const deleteEntry = async (entry) => {
        const ok = await confirmCosmicAction({ title: `Delete ${entry.title}?`, message: 'This entry will be permanently removed.', confirmLabel: 'Delete', tone: 'danger' });
        if (!ok) return;
        await axios.delete(route('content-entries.destroy', [website.id, activeType.id, entry.id]));
        setTypes((current) => current.map((type) => type.id !== activeType.id ? type : ({ ...type, entries: type.entries.filter((item) => item.id !== entry.id), entries_count: Math.max(0, type.entries_count - 1) })));
        if (editingEntry?.id === entry.id) { setEditingEntry(null); setEntryDraft(null); }
    };

    const installContent = async (withDemo) => {
        setInstalling(true);
        try {
            const response = await axios.post(route('content.install', website.id), { with_demo: withDemo, add_navigation: true });
            setTypes(response.data?.workspace?.types || types);
            setShowInstallModal(false);
            const result = response.data?.result || {};
            showCosmicNotification({ title: 'Content pages installed', message: `${result.pages || 0} pages ready${withDemo ? ` with ${result.demo || 0} demo entries` : ''}.`, tone: 'success' });
        } catch (error) {
            showCosmicNotification({ title: 'Unable to install content pages', message: error.response?.data?.message || Object.values(error.response?.data?.errors || {}).flat()[0] || 'Check page slugs and try again.', tone: 'error' });
        } finally { setInstalling(false); }
    };

    const openNewType = () => {
        setEditingType(null);
        setTypeDraft({ name: '', singular_name: '', slug: '', description: '', icon: '◇', schema: [] });
        setShowTypeModal(true);
    };

    const openEditType = () => {
        if (!activeType) return;
        setEditingType(activeType);
        setTypeDraft({ name: activeType.name, singular_name: activeType.singular_name, slug: activeType.slug, description: activeType.description || '', icon: activeType.icon || '◇', schema: activeType.schema || [] });
        setShowTypeModal(true);
    };

    const saveType = async () => {
        if (!typeDraft.name.trim()) return;
        setSaving(true);
        try {
            if (editingType) {
                const response = await axios.put(route('content-types.update', [website.id, editingType.id]), { ...typeDraft, singular_name: typeDraft.singular_name || typeDraft.name.replace(/s$/i, '') });
                setTypes((current) => current.map((type) => type.id === editingType.id ? { ...type, ...response.data.type, schema: response.data.type.schema || [] } : type));
                showCosmicNotification({ title: 'Content type updated', message: `${typeDraft.name} settings saved.`, tone: 'success' });
            } else {
                const response = await axios.post(route('content-types.store', website.id), { ...typeDraft, slug: typeDraft.slug || slugify(typeDraft.name), singular_name: typeDraft.singular_name || typeDraft.name.replace(/s$/i, '') });
                const type = { ...response.data.type, entries: [], entries_count: 0, schema: response.data.type.schema || [] };
                setTypes((current) => [...current, type]);
                setActiveTypeId(type.id);
            }
            setShowTypeModal(false);
            setEditingType(null);
            setTypeDraft({ name: '', singular_name: '', slug: '', description: '', icon: '◇', schema: [] });
        } catch (error) {
            showCosmicNotification({ title: `Unable to ${editingType ? 'update' : 'create'} content type`, message: error.response?.data?.message || 'Check the fields and try again.', tone: 'error' });
        } finally { setSaving(false); }
    };

    return <div className="space-y-4">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div><p className="text-sm font-semibold text-white">Posts / Updates</p><p className="mt-1 text-sm text-slate-400">Structured content for blogs, events, projects, news, and custom post types.</p></div>
            <div className="flex flex-wrap gap-2"><button type="button" onClick={() => setShowInstallModal(true)} className="rounded-xl border border-white/10 bg-white/[0.04] px-4 py-2 text-sm font-bold text-slate-200 hover:bg-white/[0.08]">Install content pages</button><button type="button" onClick={openNewType} className="rounded-xl bg-violet-500 px-4 py-2 text-sm font-bold text-white hover:bg-violet-400">+ Content type</button></div>
        </div>

        <div className="grid gap-3 lg:grid-cols-[250px_minmax(0,1fr)]">
            <aside className="rounded-2xl border border-white/10 bg-white/[0.025] p-2">
                {(types || []).map((type) => <button key={type.id} type="button" onClick={() => { setActiveTypeId(type.id); setEntryDraft(null); setEditingEntry(null); }} className={`mb-1 flex w-full items-center justify-between rounded-xl px-3 py-3 text-left transition ${activeType?.id === type.id ? 'bg-violet-500/15 text-violet-100 ring-1 ring-violet-400/20' : 'text-slate-300 hover:bg-white/5'}`}>
                    <span><span className="mr-2">{type.icon || '◇'}</span><span className="font-semibold">{type.name}</span></span><span className="rounded-full bg-white/5 px-2 py-0.5 text-[10px] text-slate-500">{type.entries_count || 0}</span>
                </button>)}
                {!types.length ? <p className="p-3 text-xs text-slate-500">No content types yet.</p> : null}
            </aside>

            <div className="rounded-2xl border border-white/10 bg-white/[0.025] p-4">
                {activeType ? <>
                    <div className="flex flex-wrap items-start justify-between gap-3 border-b border-white/10 pb-4">
                        <div><div className="flex items-center gap-2"><h3 className="text-base font-bold text-white">{activeType.name}</h3><span className="rounded-full bg-white/5 px-2 py-0.5 text-[10px] uppercase tracking-wider text-slate-500">/{activeType.slug}</span></div><p className="mt-1 text-sm text-slate-400">{activeType.description}</p></div>
                        <div className="flex gap-2"><button type="button" onClick={openEditType} className="rounded-xl border border-white/10 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-white/5">Edit type</button><button type="button" onClick={openNewEntry} className="rounded-xl border border-violet-400/30 bg-violet-400/10 px-3 py-2 text-xs font-bold text-violet-100 hover:bg-violet-400/15">+ Add {activeType.singular_name}</button></div>
                    </div>
                    <div className="mt-4 space-y-2">
                        {(activeType.entries || []).map((entry) => <div key={entry.id} className="flex flex-col gap-3 rounded-xl border border-white/10 bg-black/10 p-3 sm:flex-row sm:items-center sm:justify-between">
                            <button type="button" onClick={() => openEntry(entry)} className="flex min-w-0 flex-1 items-center gap-3 text-left">
                                <div className="h-14 w-16 shrink-0 overflow-hidden rounded-lg border border-white/10 bg-white/5">{entry.featured_image_url ? <img src={entry.featured_image_url} alt="" className="h-full w-full object-cover" /> : <div className="flex h-full items-center justify-center text-slate-600">◇</div>}</div>
                                <span className="min-w-0"><span className="block truncate text-sm font-bold text-white">{entry.title}</span><span className="mt-1 block text-xs text-slate-500">/{activeType.slug}/{entry.slug} · {entry.status}</span></span>
                            </button>
                            <div className="flex gap-2"><button type="button" onClick={() => openEntry(entry)} className="rounded-lg border border-white/10 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-white/5">Edit</button><button type="button" onClick={() => duplicateEntry(entry)} className="rounded-lg border border-white/10 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-white/5">Duplicate</button><button type="button" onClick={() => deleteEntry(entry)} className="rounded-lg border border-rose-400/20 px-3 py-1.5 text-xs font-semibold text-rose-300 hover:bg-rose-400/10">Delete</button></div>
                        </div>)}
                        {!activeType.entries?.length ? <div className="rounded-xl border border-dashed border-white/10 p-8 text-center"><p className="text-sm font-semibold text-slate-300">No {activeType.name.toLowerCase()} yet</p><p className="mt-1 text-xs text-slate-500">Create your first entry to populate future dynamic Sparks.</p></div> : null}
                    </div>
                </> : null}
            </div>
        </div>

        {entryDraft && activeType ? <div className="fixed inset-0 z-[150] flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-sm"><div className="max-h-[92vh] w-full max-w-5xl overflow-y-auto rounded-3xl border border-white/10 bg-[#0d0d10] shadow-2xl">
            <div className="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-white/10 bg-[#0d0d10]/95 px-5 py-4 backdrop-blur"><div><p className="text-[10px] font-bold uppercase tracking-[0.18em] text-violet-300">{activeType.singular_name}</p><h2 className="mt-1 text-xl font-bold text-white">{editingEntry ? `Edit ${entryDraft.title}` : `Add ${activeType.singular_name}`}</h2></div><button type="button" onClick={() => { setEntryDraft(null); setEditingEntry(null); }} className="rounded-lg px-3 py-2 text-slate-400 hover:bg-white/5">✕</button></div>
            <div className="grid gap-5 p-5 lg:grid-cols-[minmax(0,1.5fr)_minmax(280px,.7fr)]">
                <div className="space-y-4">
                    <div><label className="text-xs font-semibold text-slate-300">Title</label><input className="mt-1 w-full rounded-xl border border-white/10 bg-white/[0.045] px-3 py-2.5 text-sm text-white outline-none focus:border-violet-400/50" value={entryDraft.title} onChange={(e) => setEntryField('title', e.target.value)} /></div>
                    <div><label className="text-xs font-semibold text-slate-300">Slug</label><input className="mt-1 w-full rounded-xl border border-white/10 bg-white/[0.045] px-3 py-2 text-sm text-white outline-none focus:border-violet-400/50" value={entryDraft.slug} onChange={(e) => { setSlugTouched(true); setEntryField('slug', slugify(e.target.value)); }} /></div>
                    <div><label className="text-xs font-semibold text-slate-300">Excerpt</label><textarea rows={3} className="mt-1 w-full rounded-xl border border-white/10 bg-white/[0.045] px-3 py-2 text-sm text-white outline-none focus:border-violet-400/50" value={entryDraft.excerpt || ''} onChange={(e) => setEntryField('excerpt', e.target.value)} /></div>
                    <div><label className="text-xs font-semibold text-slate-300">Content</label><textarea rows={10} className="mt-1 w-full rounded-xl border border-white/10 bg-white/[0.045] px-3 py-2 text-sm text-white outline-none focus:border-violet-400/50" value={entryDraft.content || ''} onChange={(e) => setEntryField('content', e.target.value)} /></div>
                    {activeType.schema?.length ? <div className="rounded-2xl border border-white/10 bg-white/[0.02] p-4"><p className="text-xs font-bold uppercase tracking-wider text-slate-500">{activeType.name} fields</p><div className="mt-3 grid gap-3 sm:grid-cols-2">{activeType.schema.map((field) => <div key={field.key}><label className="text-xs font-semibold text-slate-300">{field.label}</label><Field field={field} value={entryDraft.custom_fields?.[field.key]} onChange={(value) => setEntryField('custom_fields', { ...(entryDraft.custom_fields || {}), [field.key]: value })} /></div>)}</div></div> : null}
                </div>
                <aside className="space-y-4">
                    <div className="rounded-2xl border border-white/10 bg-white/[0.02] p-4"><p className="text-xs font-bold uppercase tracking-wider text-slate-500">Publishing</p><select value={entryDraft.status} onChange={(e) => setEntryField('status', e.target.value)} className="mt-3 w-full rounded-xl border border-white/10 bg-[#15151a] px-3 py-2 text-sm text-white"><option value="draft">Draft</option><option value="published">Published</option></select><label className="mt-3 flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" checked={Boolean(entryDraft.is_featured)} onChange={(e) => setEntryField('is_featured', e.target.checked)} /> Featured entry</label></div>
                    <div className="rounded-2xl border border-white/10 bg-white/[0.02] p-4"><p className="text-xs font-bold uppercase tracking-wider text-slate-500">Media</p><label className="mt-3 block text-xs font-semibold text-slate-300">Featured image URL</label><input value={entryDraft.featured_image_url || ''} onChange={(e) => setEntryField('featured_image_url', e.target.value)} className="mt-1 w-full rounded-xl border border-white/10 bg-white/[0.045] px-3 py-2 text-xs text-white" placeholder="https://..." />{entryDraft.featured_image_url ? <img src={entryDraft.featured_image_url} alt="" className="mt-3 aspect-[16/10] w-full rounded-xl object-cover" /> : null}</div>
                    <div className="rounded-2xl border border-white/10 bg-white/[0.02] p-4"><p className="text-xs font-bold uppercase tracking-wider text-slate-500">Organization</p><label className="mt-3 block text-xs font-semibold text-slate-300">Category</label><input value={entryDraft.category || ''} onChange={(e) => setEntryField('category', e.target.value)} className="mt-1 w-full rounded-xl border border-white/10 bg-white/[0.045] px-3 py-2 text-sm text-white" /><label className="mt-3 block text-xs font-semibold text-slate-300">Tags</label><input value={(entryDraft.tags || []).join(', ')} onChange={(e) => setEntryField('tags', e.target.value.split(',').map((v) => v.trim()).filter(Boolean))} className="mt-1 w-full rounded-xl border border-white/10 bg-white/[0.045] px-3 py-2 text-sm text-white" placeholder="design, news, launch" /></div>
                    <div className="rounded-2xl border border-white/10 bg-white/[0.02] p-4"><p className="text-xs font-bold uppercase tracking-wider text-slate-500">SEO</p><input value={entryDraft.seo_title || ''} onChange={(e) => setEntryField('seo_title', e.target.value)} className="mt-3 w-full rounded-xl border border-white/10 bg-white/[0.045] px-3 py-2 text-sm text-white" placeholder="SEO title" /><textarea rows={3} value={entryDraft.seo_description || ''} onChange={(e) => setEntryField('seo_description', e.target.value)} className="mt-2 w-full rounded-xl border border-white/10 bg-white/[0.045] px-3 py-2 text-sm text-white" placeholder="Meta description" /></div>
                </aside>
            </div>
            <div className="sticky bottom-0 flex justify-end gap-2 border-t border-white/10 bg-[#0d0d10]/95 px-5 py-4 backdrop-blur"><button type="button" onClick={() => { setEntryDraft(null); setEditingEntry(null); }} className="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-300">Cancel</button><button type="button" onClick={saveEntry} disabled={saving || !entryDraft.title.trim()} className="rounded-xl bg-violet-500 px-5 py-2 text-sm font-bold text-white disabled:opacity-50">{saving ? 'Saving…' : 'Save entry'}</button></div>
        </div></div> : null}


        {showInstallModal ? <div className="fixed inset-0 z-[170] flex items-center justify-center bg-slate-950/80 p-4"><div className="w-full max-w-2xl rounded-3xl border border-white/10 bg-[#0d0d10] p-6 shadow-2xl"><div className="flex items-start justify-between gap-4"><div><p className="text-[10px] font-bold uppercase tracking-[0.18em] text-violet-300">Posts / Updates</p><h2 className="mt-1 text-2xl font-bold text-white">Install content pages</h2><p className="mt-2 text-sm leading-6 text-slate-400">Create editable Blog, Events and Projects pages using Mini Heroes and dynamic content Sparks. Single entries stay dynamic and inherit the same site shell.</p></div><button type="button" onClick={() => setShowInstallModal(false)} className="text-slate-400">✕</button></div><div className="mt-6 grid gap-3 sm:grid-cols-2"><button disabled={installing} type="button" onClick={() => installContent(false)} className="rounded-2xl border border-white/10 bg-white/[0.035] p-5 text-left transition hover:border-violet-400/30 hover:bg-white/[0.06] disabled:opacity-50"><span className="text-sm font-bold text-white">Install plain pages</span><span className="mt-2 block text-sm leading-6 text-slate-400">Blog, Events and Projects Standard Pages with ready-made Sparks. Keep your content library empty.</span></button><button disabled={installing} type="button" onClick={() => installContent(true)} className="rounded-2xl border border-violet-400/25 bg-violet-500/10 p-5 text-left transition hover:bg-violet-500/15 disabled:opacity-50"><span className="text-sm font-bold text-white">Install with demo content</span><span className="mt-2 block text-sm leading-6 text-slate-400">Adds starter Blog posts, Events and Projects so grids, archives and single-entry layouts are populated immediately.</span></button></div><p className="mt-4 text-xs text-slate-500">The installer is safe to rerun. It will not duplicate demo slugs and will not overwrite Standard Pages that contain unrelated custom blocks.</p></div></div> : null}
        {showTypeModal ? <div className="fixed inset-0 z-[160] flex items-center justify-center bg-slate-950/80 p-4"><div className="w-full max-w-xl rounded-3xl border border-white/10 bg-[#0d0d10] p-5 shadow-2xl"><div className="flex justify-between"><div><p className="text-[10px] font-bold uppercase tracking-[0.18em] text-violet-300">Posts / Updates</p><h2 className="mt-1 text-xl font-bold text-white">{editingType ? `Edit ${editingType.name}` : 'Add content type'}</h2></div><button onClick={() => setShowTypeModal(false)} className="text-slate-400">✕</button></div><div className="mt-5 grid gap-3 sm:grid-cols-2"><div><label className="text-xs font-semibold text-slate-300">Plural name</label><input value={typeDraft.name} onChange={(e) => setTypeDraft((d) => ({ ...d, name: e.target.value, slug: slugify(e.target.value) }))} className="mt-1 w-full rounded-xl border border-white/10 bg-white/[0.045] px-3 py-2 text-sm text-white" placeholder="Jobs" /></div><div><label className="text-xs font-semibold text-slate-300">Singular name</label><input value={typeDraft.singular_name} onChange={(e) => setTypeDraft((d) => ({ ...d, singular_name: e.target.value }))} className="mt-1 w-full rounded-xl border border-white/10 bg-white/[0.045] px-3 py-2 text-sm text-white" placeholder="Job" /></div><div className="sm:col-span-2"><label className="text-xs font-semibold text-slate-300">Slug</label><input value={typeDraft.slug} onChange={(e) => setTypeDraft((d) => ({ ...d, slug: slugify(e.target.value) }))} className="mt-1 w-full rounded-xl border border-white/10 bg-white/[0.045] px-3 py-2 text-sm text-white" /></div><div className="sm:col-span-2"><label className="text-xs font-semibold text-slate-300">Description</label><textarea rows={3} value={typeDraft.description} onChange={(e) => setTypeDraft((d) => ({ ...d, description: e.target.value }))} className="mt-1 w-full rounded-xl border border-white/10 bg-white/[0.045] px-3 py-2 text-sm text-white" /></div></div><div className="mt-5 rounded-2xl border border-white/10 bg-white/[0.02] p-4"><div className="flex items-center justify-between"><div><p className="text-xs font-bold uppercase tracking-wider text-slate-500">Custom fields</p><p className="mt-1 text-xs text-slate-500">These fields appear automatically in every {typeDraft.singular_name || 'entry'} editor.</p></div><button type="button" onClick={() => setTypeDraft((d) => ({ ...d, schema: [...(d.schema || []), { key: `field_${(d.schema || []).length + 1}`, label: 'New field', type: 'text' }] }))} className="rounded-lg border border-white/10 px-3 py-1.5 text-xs font-semibold text-slate-300">+ Field</button></div><div className="mt-3 space-y-2">{(typeDraft.schema || []).map((field, index) => <div key={`${field.key}-${index}`} className="grid gap-2 rounded-xl border border-white/10 p-3 sm:grid-cols-[1fr_1fr_130px_auto]"><input value={field.label || ''} onChange={(e) => setTypeDraft((d) => ({ ...d, schema: d.schema.map((item, i) => i === index ? { ...item, label: e.target.value, key: item.key?.startsWith('field_') ? slugify(e.target.value).replace(/-/g, '_') || item.key : item.key } : item) }))} className="rounded-lg border border-white/10 bg-white/[0.045] px-2.5 py-2 text-xs text-white" placeholder="Label" /><input value={field.key || ''} onChange={(e) => setTypeDraft((d) => ({ ...d, schema: d.schema.map((item, i) => i === index ? { ...item, key: slugify(e.target.value).replace(/-/g, '_') } : item) }))} className="rounded-lg border border-white/10 bg-white/[0.045] px-2.5 py-2 text-xs text-white" placeholder="field_key" /><select value={field.type || 'text'} onChange={(e) => setTypeDraft((d) => ({ ...d, schema: d.schema.map((item, i) => i === index ? { ...item, type: e.target.value } : item) }))} className="rounded-lg border border-white/10 bg-[#15151a] px-2 py-2 text-xs text-white"><option value="text">Text</option><option value="textarea">Textarea</option><option value="date">Date</option><option value="datetime">Date & time</option><option value="url">URL</option><option value="number">Number</option><option value="boolean">Boolean</option></select><button type="button" onClick={() => setTypeDraft((d) => ({ ...d, schema: d.schema.filter((_, i) => i !== index) }))} className="rounded-lg border border-rose-400/20 px-2.5 py-2 text-xs text-rose-300">Delete</button></div>)}</div></div><div className="mt-5 flex justify-end gap-2"><button onClick={() => setShowTypeModal(false)} className="rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-300">Cancel</button><button onClick={saveType} disabled={saving || !typeDraft.name.trim()} className="rounded-xl bg-violet-500 px-4 py-2 text-sm font-bold text-white disabled:opacity-50">Create type</button></div></div></div> : null}
    </div>;
}
