import React, { useEffect, useMemo, useRef, useState } from 'react';
import { resolveSemanticPalette } from '../../../theme/semanticPalette';
import { logoStyleForTone } from '@/Branding/logoFilters';
import {
    FOOTER_VARIANTS,
    HEADER_VARIANTS,
    LIGHT_LOGO_FOOTER_VARIANTS,
    LIGHT_LOGO_HEADER_VARIANTS,
    footerVariantById,
    headerVariantById,
    resolveShellSurface,
} from './headerFooterVariantConfig';

const normalizeMenuLabels = (header) => {
    const labels = (header?.menu || []).map((item) => String(item?.label || '').trim()).filter(Boolean);
    return labels.length ? labels.slice(0, 6) : ['Home', 'About', 'Services', 'Work', 'Contact'];
};

const safeColumns = (footer) => {
    const columns = footer?.mega_footer?.columns;
    if (Array.isArray(columns) && columns.length) return columns.slice(0, 4);
    return [
        { title: 'Company', items: [{ label: 'About' }, { label: 'Contact' }] },
        { title: 'Services', items: [{ label: 'What we do' }, { label: 'Solutions' }] },
        { title: 'Resources', items: [{ label: 'Insights' }, { label: 'Updates' }] },
    ];
};

function PreviewBrand({ branding, websiteName, tone = 'dark', allowLightLogoFilter = true, large = false }) {
    const resolved = logoStyleForTone({ ...branding, allow_light_logo_filter: allowLightLogoFilter }, tone, branding?.logo_filter_key || 'midnight');
    const logo = resolved.url;
    if (logo) {
        return <img src={logo} alt="" className={`${large ? 'max-h-9 max-w-[150px]' : 'max-h-7 max-w-[116px]'} object-contain`} style={{ filter: resolved.filter }} />;
    }
    return <span className={`${large ? 'text-[13px]' : 'text-[11px]'} truncate font-extrabold tracking-tight`}>{branding?.logo_text || websiteName || 'Your Website'}</span>;
}

function PreviewNav({ labels, color, split = false }) {
    const items = labels.slice(0, 4);
    if (!split) return <div className="flex items-center gap-2.5">{items.map((label) => <span key={label} className="text-[7px] font-semibold" style={{ color, opacity: .86 }}>{label}</span>)}</div>;
    const half = Math.ceil(items.length / 2);
    return <>{items.slice(0, half).map((label) => <span key={`l-${label}`} className="text-[7px] font-semibold" style={{ color, opacity: .86 }}>{label}</span>)}</>;
}

function HeaderPreview({ variant, header, websiteName, palette, allowLightLogoFilter }) {
    const config = headerVariantById(variant);
    const menu = normalizeMenuLabels(header);
    const primary = palette.primary || '#7C3AED';
    const isOverlay = config.background === 'overlay';
    const surface = isOverlay ? { background: 'transparent', text: '#FFFFFF', muted: 'rgba(255,255,255,.82)', border: 'rgba(255,255,255,.22)', tone: 'light' } : resolveShellSurface(config.background, palette);
    const logoTone = config.tone === 'auto' ? surface.tone : config.tone;
    const button = config.cta === 'white'
        ? { background: '#FFFFFF', color: primary, border: '#FFFFFF' }
        : { background: primary, color: palette.onPrimary || palette.on_primary || '#FFFFFF', border: primary };
    const half = Math.ceil(menu.length / 2);

    const shell = config.layout === 'split'
        ? <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-2 px-3 py-3" style={{ color: surface.text, borderColor: surface.border }}>
            <div className="flex items-center gap-2">{menu.slice(0, half).slice(0, 2).map((label) => <span key={label} className="text-[7px] font-semibold opacity-80">{label}</span>)}</div>
            <PreviewBrand branding={header} websiteName={websiteName} tone={logoTone} allowLightLogoFilter={allowLightLogoFilter} />
            <div className="flex items-center justify-end gap-2">{menu.slice(half).slice(0, 2).map((label) => <span key={label} className="text-[7px] font-semibold opacity-80">{label}</span>)}{config.cta !== 'none' && <span className="max-w-[70px] truncate rounded-full border px-2.5 py-1 text-[7px] font-bold" style={button}>{header?.cta_label || 'Get Started'}</span>}</div>
        </div>
        : <div className="flex items-center gap-3 px-3 py-3" style={{ color: surface.text, borderColor: surface.border }}>
            <PreviewBrand branding={header} websiteName={websiteName} tone={logoTone} allowLightLogoFilter={allowLightLogoFilter} />
            <div className="ml-auto"><PreviewNav labels={menu} color={surface.text} /></div>
            {config.cta !== 'none' && <span className="max-w-[82px] truncate rounded-full border px-2.5 py-1 text-[7px] font-bold" style={button}>{header?.cta_label || 'Get Started'}</span>}
        </div>;

    if (isOverlay) {
        return <div className="relative h-[150px] overflow-hidden rounded-2xl" style={{ background: `linear-gradient(135deg, ${palette.shellSecondary || palette.secondary || primary}, ${primary})` }}>
            <div className="absolute inset-0 opacity-25" style={{ backgroundImage: 'radial-gradient(circle at 72% 20%, white 0, transparent 34%)' }} />
            <div className="relative m-3 border-b" style={{ borderColor: surface.border }}>{shell}</div>
            <div className="absolute bottom-5 left-6 right-8"><div className="h-2 w-16 rounded-full bg-white/40"/><div className="mt-2 h-4 w-2/3 rounded-full bg-white/85"/></div>
        </div>;
    }

    return <div className="h-[150px] overflow-hidden rounded-2xl border p-3" style={{ background: config.background === 'white' ? (palette.page || '#F8FAFC') : surface.background, borderColor: surface.border }}>
        <div className="rounded-xl border" style={{ background: surface.background, color: surface.text, borderColor: surface.border }}>{shell}</div>
        <div className="mx-auto mt-7 h-3 w-3/5 rounded-full" style={{ background: `${primary}20` }} />
        <div className="mx-auto mt-2 h-2 w-2/5 rounded-full" style={{ background: `${surface.text}18` }} />
    </div>;
}

function FooterPreview({ variant, header, footer, websiteName, palette, allowLightLogoFilter }) {
    const config = footerVariantById(variant);
    const columns = safeColumns(footer);
    const surface = resolveShellSurface(config.background, palette);
    const primary = palette.primary || '#7C3AED';
    const lightLogo = surface.tone === 'light';
    const ctaStyle = config.background === 'primary' || (config.background === 'secondary' && surface.tone === 'light')
        ? { background: '#FFFFFF', color: primary }
        : { background: primary, color: palette.onPrimary || '#FFFFFF' };
    const centered = config.layout === 'centered';

    return <div className="relative h-[150px] overflow-hidden rounded-2xl border p-4" style={{ background: surface.background, color: surface.text, borderColor: surface.border }}>
        {centered ? <>
            <div className="flex flex-col items-center text-center">
                <PreviewBrand branding={{ ...header, ...footer }} websiteName={websiteName} tone={lightLogo ? 'light' : 'dark'} allowLightLogoFilter={allowLightLogoFilter} large />
                <div className="mt-2 h-1.5 w-24 rounded" style={{ background: surface.muted, opacity: .28 }} />
                {config.cta && <span className="mt-2 rounded-full px-3 py-1 text-[6px] font-bold" style={ctaStyle}>{footer?.mega_footer?.primary_label || 'Get in touch'}</span>}
            </div>
            <div className="mt-3 grid grid-cols-3 gap-3">{columns.slice(0,3).map((column,index)=><div key={`${column?.title||index}-${index}`} className="text-center"><div className="text-[6px] font-bold uppercase tracking-wider" style={{color:surface.muted}}>{column?.title||`Column ${index+1}`}</div><div className="mt-1 space-y-1">{(column?.items||[]).slice(0,2).map((item,i)=><div key={`${item?.label||i}-${i}`} className="truncate text-[6px] opacity-80">{item?.label||'Link'}</div>)}</div></div>)}</div>
        </> : <div className={`grid gap-4 ${config.layout === 'brand' ? 'grid-cols-[1.25fr_1fr]' : config.layout === 'editorial' ? 'grid-cols-[.95fr_1.4fr]' : 'grid-cols-[.85fr_1.7fr]'}`}>
            <div>
                <PreviewBrand branding={{ ...header, ...footer }} websiteName={websiteName} tone={lightLogo ? 'light' : 'dark'} allowLightLogoFilter={allowLightLogoFilter} large={config.layout==='brand'} />
                <div className="mt-2 h-1.5 w-20 rounded" style={{ background: surface.muted, opacity: .30 }} />
                {config.cta && <span className="mt-3 inline-block rounded-full px-2.5 py-1 text-[6px] font-bold" style={ctaStyle}>{footer?.mega_footer?.primary_label || 'Get in touch'}</span>}
            </div>
            <div className="grid grid-cols-3 gap-2">{columns.slice(0,3).map((column,index)=><div key={`${column?.title||index}-${index}`}><div className="text-[6px] font-bold uppercase tracking-wider" style={{color:surface.muted}}>{column?.title||`Column ${index+1}`}</div><div className="mt-1.5 space-y-1">{(column?.items||[]).slice(0,3).map((item,i)=><div key={`${item?.label||i}-${i}`} className="truncate text-[6px] opacity-85">{item?.label||'Link'}</div>)}</div></div>)}</div>
        </div>}
    </div>;
}

function VariantCard({ active, title, description, preview, onApply, applying }) {
    return <div className={`group rounded-[22px] border p-3 transition ${active ? 'border-violet-400/70 bg-violet-500/[.08] ring-1 ring-violet-400/20' : 'border-white/10 bg-white/[.025] hover:border-white/20 hover:bg-white/[.045]'}`}>
        {preview}
        <div className="flex items-start justify-between gap-3 px-1 pb-1 pt-3">
            <div className="min-w-0"><div className="flex items-center gap-2"><h4 className="text-sm font-bold text-white">{title}</h4>{active && <span className="rounded-full bg-emerald-400/15 px-2 py-0.5 text-[8px] font-black uppercase tracking-[.12em] text-emerald-300">Active</span>}</div><p className="mt-1 text-[11px] leading-4 text-slate-500">{description}</p></div>
            <button type="button" disabled={active || applying} onClick={onApply} className="shrink-0 rounded-xl bg-white px-3 py-2 text-[10px] font-extrabold text-slate-950 transition hover:bg-slate-200 disabled:cursor-default disabled:opacity-40">{active ? 'Applied' : applying ? 'Applying…' : 'Apply'}</button>
        </div>
    </div>;
}

export default function HeaderFooterVariantsModal({ open, currentHeader = {}, currentFooter = {}, globalTheme = {}, websiteName = '', onClose, onApplyHeader, onApplyFooter, onUploadLightLogo }) {
    const [tab, setTab] = useState('header');
    const [applyingKey, setApplyingKey] = useState(null);
    const [allowLightLogoFilter, setAllowLightLogoFilter] = useState(currentHeader?.allow_light_logo_filter !== false);
    const [uploadingLightLogo, setUploadingLightLogo] = useState(false);
    const lightLogoRef = useRef(null);
    const familyKey = globalTheme?.primary || 'midnight';
    const palette = useMemo(() => resolveSemanticPalette(familyKey, globalTheme || {}), [familyKey, globalTheme]);
    const hasLightLogo = Boolean(currentHeader?.logo_light_image_url || currentHeader?.logo_image_url_light || currentHeader?.light_logo_url || currentFooter?.logo_light_image_url || currentFooter?.logo_image_url_light || currentFooter?.light_logo_url);

    useEffect(() => {
        if (!open) return undefined;
        setAllowLightLogoFilter(currentHeader?.allow_light_logo_filter !== false);
        const onKey = (event) => { if (event.key === 'Escape') onClose?.(); };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [open, onClose, currentHeader?.allow_light_logo_filter]);

    if (!open) return null;
    const activeHeader = HEADER_VARIANTS.some((item) => item.id === currentHeader?.type) ? currentHeader.type : null;
    const activeFooter = FOOTER_VARIANTS.some((item)=>item.id===currentFooter?.mega_footer?.variant) ? currentFooter.mega_footer.variant : 'classic';
    const activeHeaderConfig = activeHeader ? headerVariantById(activeHeader) : null;
    const activeFooterConfig = activeFooter ? footerVariantById(activeFooter) : null;
    const selectedNeedsLightLogo = tab === 'header'
        ? Boolean(activeHeaderConfig && (LIGHT_LOGO_HEADER_VARIANTS.has(activeHeader) || resolveShellSurface(activeHeaderConfig.background, palette).tone === 'light'))
        : Boolean(activeFooterConfig && (LIGHT_LOGO_FOOTER_VARIANTS.has(activeFooter) || resolveShellSurface(activeFooterConfig.background, palette).tone === 'light'));

    const applyHeader = async (id) => {
        setApplyingKey(`header:${id}`);
        try { await onApplyHeader?.(id, { allowLightLogoFilter }); } finally { setApplyingKey(null); }
    };
    const applyFooter = async (id) => {
        setApplyingKey(`footer:${id}`);
        try { await onApplyFooter?.(id, { allowLightLogoFilter }); } finally { setApplyingKey(null); }
    };
    const uploadLightLogo = async (event) => {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file || !onUploadLightLogo) return;
        setUploadingLightLogo(true);
        try { await onUploadLightLogo(file); } finally { setUploadingLightLogo(false); }
    };

    return <div className="fixed inset-0 z-[9998] flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-md" onMouseDown={onClose}>
        <div className="flex max-h-[92vh] w-full max-w-[1180px] flex-col overflow-hidden rounded-[26px] border border-slate-600/70 bg-[linear-gradient(145deg,#141925,#0d1520)] text-white shadow-[0_36px_120px_rgba(0,0,0,.55)]" onMouseDown={(event) => event.stopPropagation()}>
            <div className="flex items-start justify-between gap-4 border-b border-white/10 px-6 py-5">
                <div><p className="text-[10px] font-extrabold uppercase tracking-[.2em] text-violet-300">Build · Global shell</p><h2 className="mt-1 text-xl font-extrabold tracking-tight">Header & Footer</h2><p className="mt-1 text-xs text-slate-500">Preview cards use your current logo, navigation and exact theme family. The selected card matches the actual shell layout.</p></div>
                <button type="button" onClick={onClose} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-white/10 text-lg text-slate-400 hover:bg-white/5 hover:text-white" aria-label="Close">×</button>
            </div>
            <div className="flex gap-1 border-b border-white/10 px-6 pt-4">
                <button type="button" onClick={() => setTab('header')} className={`rounded-t-xl px-4 py-2.5 text-xs font-bold transition ${tab === 'header' ? 'bg-white/[.08] text-white' : 'text-slate-500 hover:text-slate-300'}`}>Header <span className="ml-1 opacity-50">7</span></button>
                <button type="button" onClick={() => setTab('footer')} className={`rounded-t-xl px-4 py-2.5 text-xs font-bold transition ${tab === 'footer' ? 'bg-white/[.08] text-white' : 'text-slate-500 hover:text-slate-300'}`}>Footer <span className="ml-1 opacity-50">7</span></button>
            </div>
            <div className="overflow-y-auto p-6">
                <div className="mb-4 grid gap-3 lg:grid-cols-[1fr_auto]">
                    <div className="rounded-2xl border border-white/10 bg-white/[.025] px-4 py-3"><p className="text-[10px] font-bold uppercase tracking-[.14em] text-slate-500">Live preview source</p><p className="mt-1 text-xs font-semibold text-slate-300">{websiteName || currentHeader?.logo_text || 'Current website'} · {globalTheme?.primary || 'Current theme'} color family</p><p className="mt-1 text-[10px] text-slate-500">Secondary shell: <span className="font-semibold text-slate-300">{palette.shellSecondary || palette.shell_secondary}</span> · {palette.shellSecondaryTone || palette.shell_secondary_tone}</p></div>
                    <div className="min-w-[310px] rounded-2xl border border-white/10 bg-white/[.025] px-4 py-3">
                        <label className="flex cursor-pointer items-start gap-3"><input type="checkbox" checked={allowLightLogoFilter} onChange={(event)=>setAllowLightLogoFilter(event.target.checked)} className="mt-0.5 rounded border-white/20 bg-black/20 text-violet-500 focus:ring-violet-400"/><span><span className="block text-xs font-bold text-white">Allow automatic white logo filter</span><span className="mt-0.5 block text-[10px] leading-4 text-slate-500">Used only when a light logo is needed and no uploaded Light Logo exists.</span></span></label>
                        <div className="mt-3 flex items-center gap-2"><button type="button" disabled={!onUploadLightLogo || uploadingLightLogo} onClick={()=>lightLogoRef.current?.click()} className="rounded-lg border border-white/10 px-3 py-1.5 text-[10px] font-bold text-slate-200 hover:bg-white/5 disabled:opacity-40">{uploadingLightLogo ? 'Uploading…' : hasLightLogo ? 'Replace Light Logo' : 'Upload Light Logo'}</button>{hasLightLogo && <span className="text-[9px] font-bold uppercase tracking-[.12em] text-emerald-300">Light logo ready</span>}</div>
                        <input ref={lightLogoRef} type="file" accept=".svg,.png,.jpg,.jpeg,.webp,image/svg+xml,image/png,image/jpeg,image/webp" onChange={uploadLightLogo} className="hidden" />
                        {!allowLightLogoFilter && !hasLightLogo && selectedNeedsLightLogo && <p className="mt-2 text-[10px] leading-4 text-amber-300">This active design needs a light logo. Upload one to keep contrast without filtering your original logo.</p>}
                    </div>
                </div>
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {tab === 'header' && HEADER_VARIANTS.map((variant) => <VariantCard key={variant.id} active={activeHeader === variant.id} title={variant.name} description={variant.description} applying={applyingKey === `header:${variant.id}`} onApply={() => applyHeader(variant.id)} preview={<HeaderPreview variant={variant.id} header={{...currentHeader,allow_light_logo_filter:allowLightLogoFilter}} websiteName={websiteName} palette={palette} allowLightLogoFilter={allowLightLogoFilter} />} />)}
                    {tab === 'footer' && FOOTER_VARIANTS.map((variant) => <VariantCard key={variant.id} active={activeFooter === variant.id} title={variant.name} description={variant.description} applying={applyingKey === `footer:${variant.id}`} onApply={() => applyFooter(variant.id)} preview={<FooterPreview variant={variant.id} header={{...currentHeader,allow_light_logo_filter:allowLightLogoFilter}} footer={{...currentFooter,allow_light_logo_filter:allowLightLogoFilter}} websiteName={websiteName} palette={palette} allowLightLogoFilter={allowLightLogoFilter} />} />)}
                </div>
            </div>
            <div className="flex items-center justify-between gap-3 border-t border-white/10 px-6 py-4"><p className="text-[10px] text-slate-500">Manual layout changes use 0 credits. Save Draft or Publish to persist them.</p><button type="button" onClick={onClose} className="rounded-xl border border-white/10 px-4 py-2 text-xs font-bold text-white hover:bg-white/5">Done</button></div>
        </div>
    </div>;
}
