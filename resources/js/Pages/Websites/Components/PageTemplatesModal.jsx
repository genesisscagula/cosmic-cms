import axios from 'axios';
import { useEffect, useMemo, useState } from 'react';
import { showCosmicNotification } from '../../../Components/CosmicNotification';
import { useCreditBalance } from '@/Hooks/useCreditBalance';
import { BlockRegistry } from '../BlockRegistry';
import ThemeSelector from '../Theme/ThemeSelector';

const clone = (value) => typeof structuredClone === 'function' ? structuredClone(value) : JSON.parse(JSON.stringify(value));

function buildBlocks(template) {
    return (template.sections || []).map((type) => ({
        ...(clone(BlockRegistry[type]?.schema?.defaults || {})),
        type,
        theme: 'auto',
        resolvedTheme: 'auto',
    })).filter((block) => BlockRegistry[block.type]);
}

function TemplateMiniPreview({ template, websiteTheme }) {
    const blocks = buildBlocks(template).slice(0, 6);
    return <div className="h-52 overflow-hidden rounded-xl bg-white text-slate-900">
        <div className="origin-top-left w-[400%]" style={{ transform: 'scale(.25)' }}>
            {blocks.map((block, index) => {
                const Component = BlockRegistry[block.type]?.component;
                return Component ? <Component key={`${block.type}-${index}`} block={block} blockIndex={index} globalTheme={websiteTheme} onUpdate={() => {}} blogPosts={[]} /> : null;
            })}
        </div>
    </div>;
}

export default function PageTemplatesModal({ open, onClose, onInstall, websiteContext = '', websiteId = null, websiteTheme = null, themeValue = 'midnight', onThemeChange, themeAccess, customTheme, hasLogo, brandMatchNeeded, onMatchBrandToLogo, brandMatchBusy }) {
    const { setBalance } = useCreditBalance();
    const [templates, setTemplates] = useState([]);
    const [tab, setTab] = useState('marketplace');
    const [query, setQuery] = useState('');
    const [tag, setTag] = useState('All');
    const [busy, setBusy] = useState(null);
    const [selected, setSelected] = useState(null);
    const [preview, setPreview] = useState(null);
    const [mode, setMode] = useState('generic');
    const [instruction, setInstruction] = useState('');

    useEffect(() => {
        if (!open) return;
        axios.get('/page-templates/catalog').then(({ data }) => setTemplates(data.templates || [])).catch(() => showCosmicNotification({ title: 'Could not load Templates', message: 'Please refresh and try again.', tone: 'error' }));
    }, [open]);

    const tags = useMemo(() => ['All', ...new Set(templates.flatMap((item) => item.tags || []))], [templates]);
    const visible = useMemo(() => templates.filter((item) => {
        if (tab === 'owned' && !item.owned) return false;
        if (tab === 'favorites' && !item.favorited) return false;
        if (tag !== 'All' && !(item.tags || []).includes(tag)) return false;
        return `${item.name} ${item.description} ${(item.tags || []).join(' ')}`.toLowerCase().includes(query.trim().toLowerCase());
    }), [templates, tab, tag, query]);

    if (!open) return null;

    const unlock = async (template) => {
        setBusy(template.key);
        try {
            const { data } = await axios.post(`/page-templates/${template.key}/unlock`);
            setTemplates((items) => items.map((item) => item.key === template.key ? { ...item, owned: true, purchased: true } : item));
            setBalance(data.credit_balance);
            showCosmicNotification({ title: 'Template owned', message: data.message, tone: 'success' });
        } catch (error) {
            showCosmicNotification({ title: 'Could not purchase Template', message: error.response?.data?.message || 'Please check your credits and try again.', tone: 'error' });
        } finally { setBusy(null); }
    };

    const favorite = async (template) => {
        setBusy(`fav-${template.key}`);
        try {
            const { data } = await axios.post(`/page-templates/${template.key}/favorite`);
            setTemplates((items) => items.map((item) => item.key === template.key ? { ...item, favorited: data.favorited } : item));
            if (preview?.key === template.key) setPreview((item) => ({ ...item, favorited: data.favorited }));
        } catch (error) {
            showCosmicNotification({ title: 'Could not update Favorite', message: error.response?.data?.message || 'Please try again.', tone: 'error' });
        } finally { setBusy(null); }
    };

    const install = async () => {
        if (!selected) return;
        setBusy(`install-${selected.key}`);
        try {
            let blocks = buildBlocks(selected);
            if (mode === 'personalized') {
                const prompt = [websiteContext || 'Create professional website content.', instruction.trim() || `Personalize the ${selected.name} page template for this business. Keep the selected layout and section order.`].join('\n\n');
                const { data } = await axios.post('/ai/generate-content', { prompt, sections: selected.sections, generation_type: 'template', website_id: websiteId });
                if (!data.blocks?.length) throw new Error('Cosmic AI did not return template content.');
                blocks = data.blocks;
                setBalance(data.credit_balance);
            }
            const installed = onInstall(blocks, selected);
            if (installed === false) return;
            showCosmicNotification({ title: 'Template installed', message: `${selected.name} is now on this page.`, tone: 'success' });
            setSelected(null); setInstruction(''); setMode('generic'); onClose();
        } catch (error) {
            showCosmicNotification({ title: 'Could not install Template', message: error.response?.data?.message || error.message || 'Please try again.', tone: 'error' });
        } finally { setBusy(null); }
    };

    return <div className="fixed inset-0 z-[920] flex items-center justify-center p-4 sm:p-6">
        <button type="button" className="absolute inset-0 bg-black/80 backdrop-blur-sm" onClick={onClose} aria-label="Close Templates" />
        <section className="relative z-10 flex max-h-[94vh] w-full max-w-7xl flex-col overflow-hidden rounded-3xl border border-violet-400/20 bg-[#101014] text-white shadow-2xl">
            <header className="border-b border-white/10 px-5 py-5 sm:px-7">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div><p className="text-[10px] font-bold uppercase tracking-[0.22em] text-violet-300">Cosmic Builder</p><h2 className="mt-1 text-2xl font-semibold">✦ Templates</h2><p className="mt-1 text-sm text-slate-400">Install complete premium pages built from Cosmic Sparks.</p></div>
                    <div className="flex items-center gap-2">
                        <ThemeSelector compact value={themeValue} onChange={onThemeChange} themeAccess={themeAccess} customTheme={customTheme} hasLogo={hasLogo} brandMatchNeeded={brandMatchNeeded} onMatchBrandToLogo={onMatchBrandToLogo} brandMatchBusy={brandMatchBusy} />
                        <button type="button" onClick={onClose} className="h-10 rounded-xl border border-white/10 px-4 text-sm font-semibold text-slate-300 hover:bg-white/5">Close</button>
                    </div>
                </div>
                <div className="mt-5 flex flex-wrap items-center gap-2">
                    {['marketplace','owned','favorites'].map((value) => <button key={value} onClick={() => setTab(value)} className={`rounded-full px-4 py-2 text-xs font-bold capitalize ${tab === value ? 'bg-white text-slate-950' : 'bg-white/5 text-slate-400 hover:bg-white/10'}`}>{value}</button>)}
                    <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Search templates..." className="ml-auto h-9 min-w-48 rounded-xl border border-white/10 bg-white/5 px-3 text-xs text-white outline-none focus:border-violet-400" />
                </div>
                <div className="mt-3 flex gap-2 overflow-x-auto pb-1">{tags.map((value) => <button key={value} onClick={() => setTag(value)} className={`shrink-0 rounded-full border px-3 py-1.5 text-[11px] font-semibold ${tag === value ? 'border-violet-300 bg-violet-400/15 text-violet-100' : 'border-white/10 text-slate-400'}`}>{value}</button>)}</div>
            </header>
            <div className="overflow-y-auto p-5 sm:p-7">
                <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">{visible.map((template) => <article key={template.key} className="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.025]">
                    <button type="button" onClick={() => setPreview(template)} className="block w-full p-3 text-left"><TemplateMiniPreview template={template} websiteTheme={websiteTheme} /></button>
                    <div className="p-4 pt-1"><div className="flex items-start justify-between gap-3"><div><h3 className="font-semibold">{template.name}</h3><div className="mt-1 flex flex-wrap gap-1">{(template.tags || []).slice(0,3).map((t) => <span key={t} className="rounded-full bg-white/5 px-2 py-0.5 text-[9px] font-bold text-slate-400">{t}</span>)}</div></div><button onClick={() => favorite(template)} className={`h-8 w-8 rounded-lg border ${template.favorited ? 'border-rose-300/30 text-rose-200' : 'border-white/10 text-slate-400'}`}>{template.favorited ? '♥' : '♡'}</button></div>
                    <p className="mt-3 min-h-10 text-xs leading-5 text-slate-400">{template.description}</p>
                    <div className="mt-4 flex gap-2"><button onClick={() => setPreview(template)} className="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-bold">Preview</button>{template.owned ? <button onClick={() => setSelected(template)} className="flex-1 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-950">Install</button> : <button disabled={busy === template.key} onClick={() => unlock(template)} className="flex-1 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-bold disabled:opacity-50">{busy === template.key ? 'Purchasing…' : `Buy · ⚡${template.credits}`}</button>}</div>
                    {template.owned && <p className="mt-2 text-right text-[10px] font-bold text-emerald-300">✓ Owned</p>}
                </div></article>)}</div>
                {!visible.length && <div className="py-16 text-center text-sm text-slate-500">No templates match this view.</div>}
            </div>
        </section>
        {preview && <div className="fixed inset-0 z-[940] flex items-center justify-center bg-black/85 p-4"><section className="w-full max-w-6xl rounded-3xl border border-white/10 bg-[#121217] p-5"><div className="flex items-center justify-between"><div><p className="text-xs font-bold uppercase tracking-wider text-violet-300">Live theme preview</p><h3 className="mt-1 text-xl font-semibold text-white">{preview.name}</h3></div><button onClick={() => setPreview(null)} className="rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-300">Close</button></div><div className="mt-4 max-h-[68vh] overflow-y-auto rounded-2xl bg-white"><div>{buildBlocks(preview).map((block,index) => { const Component=BlockRegistry[block.type]?.component; return Component ? <Component key={`${block.type}-${index}`} block={block} blockIndex={index} globalTheme={websiteTheme} onUpdate={()=>{}} blogPosts={[]} /> : null; })}</div></div><div className="mt-4 flex justify-end gap-2">{preview.owned ? <button onClick={() => { setSelected(preview); setPreview(null); }} className="rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-slate-950">Install</button> : <button onClick={() => unlock(preview)} className="rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-bold">Buy · ⚡{preview.credits}</button>}</div></section></div>}
        {selected && <div className="fixed inset-0 z-[950] flex items-center justify-center bg-black/85 p-4"><section className="w-full max-w-lg rounded-2xl border border-violet-400/20 bg-[#18181b] p-6"><p className="text-[10px] font-bold uppercase tracking-[0.2em] text-violet-300">Install Template</p><h3 className="mt-1 text-xl font-semibold">{selected.name}</h3><p className="mt-2 text-sm text-slate-400">Install generic content for free, or personalize the complete page with Cosmic AI for 50 Credits.</p><div className="mt-5 grid grid-cols-2 gap-3"><button onClick={() => setMode('generic')} className={`rounded-xl border p-4 text-left ${mode==='generic'?'border-emerald-400 bg-emerald-400/10':'border-white/10'}`}><b className="block text-sm">Generic</b><span className="mt-1 block text-xs text-emerald-300">FREE</span></button><button onClick={() => setMode('personalized')} className={`rounded-xl border p-4 text-left ${mode==='personalized'?'border-violet-400 bg-violet-400/10':'border-white/10'}`}><b className="block text-sm">Personalized</b><span className="mt-1 block text-xs text-violet-300">⚡ 50 Credits</span></button></div><textarea disabled={mode!=='personalized'} value={instruction} onChange={(e)=>setInstruction(e.target.value.slice(0,500))} placeholder="Optional instructions for Cosmic AI..." rows={4} className="mt-4 w-full resize-none rounded-xl border border-white/10 bg-black/25 p-3 text-sm outline-none disabled:opacity-40"/><div className="mt-5 flex justify-end gap-2"><button onClick={()=>setSelected(null)} className="rounded-xl border border-white/10 px-4 py-2 text-sm">Cancel</button><button disabled={busy===`install-${selected.key}`} onClick={install} className="rounded-xl bg-white px-5 py-2 text-sm font-bold text-slate-950 disabled:opacity-50">{busy===`install-${selected.key}`?'Installing…':mode==='personalized'?'Personalize & Install · ⚡50':'Install · FREE'}</button></div></section></div>}
    </div>;
}
