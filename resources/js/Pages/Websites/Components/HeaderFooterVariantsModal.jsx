import React, { useEffect, useMemo, useState } from 'react';
import { resolveSemanticPalette } from '../../../theme/semanticPalette';
import { logoStyleForTone } from '@/Branding/logoFilters';

const HEADER_VARIANTS = [
    { id: 'classic_header', name: 'Classic', description: 'Balanced logo, navigation and CTA.' },
    { id: 'centered_header', name: 'Centered', description: 'Navigation centered between brand and CTA.' },
    { id: 'split_navigation_header', name: 'Split Navigation', description: 'Brand centered with navigation split left and right.' },
    { id: 'floating_glass_header', name: 'Floating Glass', description: 'Elevated glass shell with soft blur.' },
    { id: 'overlay_hero_header', name: 'Overlay Hero', description: 'Transparent header designed to sit over the first hero.' },
    { id: 'minimal_header', name: 'Minimal', description: 'Restrained navigation with compact visual weight.' },
];

const FOOTER_VARIANTS = [
    { id: 'classic', name: 'Mega Classic', description: 'Balanced brand area with multi-column navigation.' },
    { id: 'cta', name: 'Mega CTA', description: 'Prominent conversion call-to-action before footer links.' },
    { id: 'brand', name: 'Mega Brand', description: 'Large brand statement with spacious supporting links.' },
    { id: 'contact', name: 'Mega Contact', description: 'Contact-first footer for service businesses.' },
    { id: 'newsletter', name: 'Mega Newsletter', description: 'Email signup focus with supporting navigation.' },
];

const normalizeMenuLabels = (header) => {
    const labels = (header?.menu || []).map((item) => String(item?.label || '').trim()).filter(Boolean);
    return labels.length ? labels.slice(0, 5) : ['Home', 'About', 'Services', 'Contact'];
};

const safeColumns = (footer) => {
    const columns = footer?.mega_footer?.columns;
    if (Array.isArray(columns) && columns.length) return columns.slice(0, 3);
    return [
        { title: 'Company', items: [{ label: 'About' }, { label: 'Contact' }] },
        { title: 'Services', items: [{ label: 'What we do' }, { label: 'Solutions' }] },
        { title: 'Resources', items: [{ label: 'Insights' }, { label: 'Updates' }] },
    ];
};

function PreviewBrand({ header, websiteName, dark = false }) {
    const resolved = logoStyleForTone(header, dark ? 'light' : 'dark', header?.logo_filter_key || 'midnight');
    const logo = resolved.url;
    if (logo && !logo.includes('your-logo.png')) {
        return <img src={logo} alt="" className="max-h-7 max-w-[112px] object-contain" style={{ filter: resolved.filter }} />;
    }
    return <span className="truncate text-[11px] font-extrabold tracking-tight">{header?.logo_text || websiteName || 'Your Website'}</span>;
}

function HeaderPreview({ variant, header, websiteName, palette }) {
    const menu = normalizeMenuLabels(header);
    const primary = palette.primary || '#7c3aed';
    const surface = palette.surface || '#ffffff';
    const heading = palette.heading || palette.on_surface || '#0f172a';
    const overlay = variant === 'overlay_hero_header';
    const floating = variant === 'floating_glass_header';
    const minimal = variant === 'minimal_header';
    const centered = variant === 'centered_header';
    const split = variant === 'split_navigation_header';
    const shellColor = overlay ? '#ffffff' : heading;
    const shellStyle = floating
        ? { background: `color-mix(in srgb, ${surface} 86%, transparent)`, color: heading, borderColor: `${palette.border || '#e2e8f0'}` }
        : { background: overlay ? 'transparent' : surface, color: shellColor, borderColor: overlay ? 'rgba(255,255,255,.22)' : (palette.border || '#e2e8f0') };

    if (overlay) {
        return <div className="relative h-[150px] overflow-hidden rounded-2xl" style={{ background: `linear-gradient(135deg, ${palette.secondary || primary}, ${primary})` }}>
            <div className="absolute inset-0 opacity-25" style={{ backgroundImage: 'radial-gradient(circle at 75% 25%, white 0, transparent 32%)' }} />
            <div className="relative m-3 flex items-center gap-3 border-b px-3 py-3 text-white" style={shellStyle}>
                <PreviewBrand header={header} websiteName={websiteName} dark />
                <div className="ml-auto hidden items-center gap-3 sm:flex">{menu.slice(0, 4).map((label) => <span key={label} className="text-[7px] font-semibold opacity-85">{label}</span>)}</div>
                <span className="rounded-full px-2.5 py-1 text-[7px] font-bold" style={{ background: primary }}>Get Started</span>
            </div>
            <div className="absolute bottom-5 left-6 right-8">
                <div className="h-2 w-16 rounded-full bg-white/45" />
                <div className="mt-2 h-4 w-2/3 rounded-full bg-white/85" />
            </div>
        </div>;
    }

    if (centered) {
        return <div className="h-[150px] rounded-2xl border p-4" style={{ background: surface, borderColor: palette.border || '#e2e8f0', color: heading }}>
            <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-2">
                <PreviewBrand header={header} websiteName={websiteName} />
                <div className="flex gap-2">{menu.slice(0, 3).map((label) => <span key={label} className="text-[7px] font-semibold opacity-65">{label}</span>)}</div>
                <span className="justify-self-end rounded-full px-2.5 py-1 text-[7px] font-bold text-white" style={{ background: primary }}>Get Started</span>
            </div>
            <div className="mx-auto mt-8 h-3 w-3/5 rounded-full" style={{ background: `${primary}22` }} />
            <div className="mx-auto mt-2 h-2 w-2/5 rounded-full" style={{ background: `${heading}18` }} />
        </div>;
    }

    if (split) {
        const half = Math.ceil(menu.length / 2);
        return <div className="h-[150px] rounded-2xl border p-4" style={{ background: surface, borderColor: palette.border || '#e2e8f0', color: heading }}>
            <div className="flex items-center justify-between gap-2">
                <div className="flex gap-2">{menu.slice(0, half).slice(0, 2).map((label) => <span key={label} className="text-[7px] font-semibold opacity-65">{label}</span>)}</div>
                <PreviewBrand header={header} websiteName={websiteName} />
                <div className="flex items-center gap-2">{menu.slice(half).slice(0, 2).map((label) => <span key={label} className="text-[7px] font-semibold opacity-65">{label}</span>)}<span className="rounded-full px-2 py-1 text-[7px] font-bold text-white" style={{ background: primary }}>CTA</span></div>
            </div>
            <div className="mx-auto mt-9 h-3 w-2/3 rounded-full" style={{ background: `${primary}1f` }} />
        </div>;
    }

    return <div className="h-[150px] rounded-2xl p-3" style={{ background: floating ? (palette.page || '#f8fafc') : surface, color: heading }}>
        <div className={`${floating ? 'rounded-xl border shadow-md' : 'border-b'} flex items-center gap-3 px-3 ${minimal ? 'py-2.5' : 'py-3'}`} style={shellStyle}>
            <PreviewBrand header={header} websiteName={websiteName} />
            <div className={`ml-auto flex items-center ${minimal ? 'gap-2' : 'gap-3'}`}>{menu.slice(0, minimal ? 3 : 4).map((label) => <span key={label} className="text-[7px] font-semibold opacity-65">{label}</span>)}</div>
            <span className={`${minimal ? 'rounded-lg' : 'rounded-full'} px-2.5 py-1 text-[7px] font-bold text-white`} style={{ background: primary }}>Get Started</span>
        </div>
        <div className="mx-auto mt-8 h-3 w-3/5 rounded-full" style={{ background: `${primary}20` }} />
        <div className="mx-auto mt-2 h-2 w-2/5 rounded-full" style={{ background: `${heading}16` }} />
    </div>;
}

function FooterPreview({ variant, header, footer, websiteName, palette }) {
    const primary = palette.primary || '#7c3aed';
    const secondary = palette.secondary || '#111827';
    const columns = safeColumns(footer);
    const brand = header?.logo_text || websiteName || 'Your Website';
    const dark = variant !== 'newsletter';
    const background = dark ? secondary : (palette.surface || '#ffffff');
    const text = dark ? '#ffffff' : (palette.heading || '#0f172a');
    const muted = dark ? 'rgba(255,255,255,.58)' : (palette.muted || '#64748b');

    return <div className="relative h-[150px] overflow-hidden rounded-2xl border p-4" style={{ background, color: text, borderColor: dark ? 'rgba(255,255,255,.12)' : (palette.border || '#e2e8f0') }}>
        {variant === 'cta' && <div className="mb-3 flex items-center justify-between rounded-xl px-3 py-2" style={{ background: primary, color: '#fff' }}><div><div className="h-2 w-24 rounded bg-white/75"/><div className="mt-1 h-1.5 w-16 rounded bg-white/40"/></div><span className="rounded-full bg-white px-2 py-1 text-[6px] font-bold" style={{ color: primary }}>Get started</span></div>}
        {variant === 'newsletter' && <div className="mb-3 flex items-center justify-between gap-3 rounded-xl p-2.5" style={{ background: `${primary}12` }}><div><div className="text-[8px] font-extrabold">Stay in the loop</div><div className="mt-1 text-[6px]" style={{ color: muted }}>Useful updates from {brand}</div></div><div className="flex overflow-hidden rounded-full border" style={{ borderColor: palette.border || '#e2e8f0' }}><span className="w-16 px-2 py-1 text-[6px]" style={{ color: muted }}>Email</span><span className="px-2 py-1 text-[6px] font-bold text-white" style={{ background: primary }}>Join</span></div></div>}
        <div className={`grid gap-4 ${variant === 'brand' ? 'grid-cols-[1.3fr_1fr]' : variant === 'contact' ? 'grid-cols-[.9fr_1.5fr]' : 'grid-cols-[.85fr_1.7fr]'}`}>
            <div>
                <div className={variant === 'brand' ? 'scale-110 origin-left' : ''}><PreviewBrand header={{ ...header, ...footer }} websiteName={websiteName} dark={dark} /></div>
                <div className="mt-2 h-1.5 w-20 rounded" style={{ background: dark ? 'rgba(255,255,255,.2)' : `${primary}22` }} />
                {variant === 'contact' && <div className="mt-3 space-y-1 text-[6px]" style={{ color: muted }}><div>{footer?.contact?.email || 'hello@example.com'}</div><div>{footer?.contact?.phone || '+1 234 567 890'}</div></div>}
            </div>
            <div className="grid grid-cols-3 gap-2">{columns.map((column, index) => <div key={`${column?.title || index}-${index}`}><div className="text-[6px] font-bold uppercase tracking-wider" style={{ color: muted }}>{column?.title || `Column ${index + 1}`}</div><div className="mt-1.5 space-y-1">{(column?.items || []).slice(0, 3).map((item, itemIndex) => <div key={`${item?.label || itemIndex}-${itemIndex}`} className="truncate text-[6px] opacity-90">{item?.label || 'Link'}</div>)}</div></div>)}</div>
        </div>
        {variant === 'brand' && <div className="absolute bottom-3 left-4 right-4 h-px" style={{ background: dark ? 'rgba(255,255,255,.12)' : (palette.border || '#e2e8f0') }} />}
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

export default function HeaderFooterVariantsModal({ open, currentHeader = {}, currentFooter = {}, globalTheme = {}, websiteName = '', onClose, onApplyHeader, onApplyFooter }) {
    const [tab, setTab] = useState('header');
    const [applyingKey, setApplyingKey] = useState(null);
    const familyKey = globalTheme?.primary || 'midnight';
    const palette = useMemo(() => resolveSemanticPalette(familyKey, globalTheme || {}), [familyKey, globalTheme]);

    useEffect(() => {
        if (!open) return undefined;
        const onKey = (event) => { if (event.key === 'Escape') onClose?.(); };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [open, onClose]);

    if (!open) return null;
    const activeHeader = HEADER_VARIANTS.some((item) => item.id === currentHeader?.type) ? currentHeader.type : null;
    const footerEnabled = Boolean(currentFooter?.mega_enabled ?? currentFooter?.mega_footer?.enabled ?? false);
    const activeFooter = footerEnabled ? (currentFooter?.mega_footer?.variant || 'classic') : null;

    const applyHeader = async (id) => {
        setApplyingKey(`header:${id}`);
        try { await onApplyHeader?.(id); } finally { setApplyingKey(null); }
    };
    const applyFooter = async (id) => {
        setApplyingKey(`footer:${id}`);
        try { await onApplyFooter?.(id); } finally { setApplyingKey(null); }
    };

    return <div className="fixed inset-0 z-[9998] flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-md" onMouseDown={onClose}>
        <div className="flex max-h-[92vh] w-full max-w-[1180px] flex-col overflow-hidden rounded-[26px] border border-slate-600/70 bg-[linear-gradient(145deg,#141925,#0d1520)] text-white shadow-[0_36px_120px_rgba(0,0,0,.55)]" onMouseDown={(event) => event.stopPropagation()}>
            <div className="flex items-start justify-between gap-4 border-b border-white/10 px-6 py-5">
                <div><p className="text-[10px] font-extrabold uppercase tracking-[.2em] text-violet-300">Build · Global shell</p><h2 className="mt-1 text-xl font-extrabold tracking-tight">Header & Footer</h2><p className="mt-1 text-xs text-slate-500">Choose a layout variation. Your current branding, navigation, content and dynamic theme colors are preserved.</p></div>
                <button type="button" onClick={onClose} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-white/10 text-lg text-slate-400 hover:bg-white/5 hover:text-white" aria-label="Close">×</button>
            </div>
            <div className="flex gap-1 border-b border-white/10 px-6 pt-4">
                <button type="button" onClick={() => setTab('header')} className={`rounded-t-xl px-4 py-2.5 text-xs font-bold transition ${tab === 'header' ? 'bg-white/[.08] text-white' : 'text-slate-500 hover:text-slate-300'}`}>Header <span className="ml-1 opacity-50">6</span></button>
                <button type="button" onClick={() => setTab('footer')} className={`rounded-t-xl px-4 py-2.5 text-xs font-bold transition ${tab === 'footer' ? 'bg-white/[.08] text-white' : 'text-slate-500 hover:text-slate-300'}`}>Footer <span className="ml-1 opacity-50">5</span></button>
            </div>
            <div className="overflow-y-auto p-6">
                <div className="mb-4 flex items-center justify-between gap-3 rounded-2xl border border-white/10 bg-white/[.025] px-4 py-3"><div><p className="text-[10px] font-bold uppercase tracking-[.14em] text-slate-500">Live preview source</p><p className="mt-1 text-xs font-semibold text-slate-300">{websiteName || currentHeader?.logo_text || 'Current website'} · {globalTheme?.primary || 'Current theme'} color family</p></div><span className="rounded-full border border-emerald-300/20 bg-emerald-400/10 px-3 py-1 text-[9px] font-extrabold uppercase tracking-[.12em] text-emerald-300">Theme dynamic</span></div>
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {tab === 'header' && HEADER_VARIANTS.map((variant) => <VariantCard key={variant.id} active={activeHeader === variant.id} title={variant.name} description={variant.description} applying={applyingKey === `header:${variant.id}`} onApply={() => applyHeader(variant.id)} preview={<HeaderPreview variant={variant.id} header={currentHeader} websiteName={websiteName} palette={palette} />} />)}
                    {tab === 'footer' && FOOTER_VARIANTS.map((variant) => <VariantCard key={variant.id} active={activeFooter === variant.id} title={variant.name} description={variant.description} applying={applyingKey === `footer:${variant.id}`} onApply={() => applyFooter(variant.id)} preview={<FooterPreview variant={variant.id} header={currentHeader} footer={currentFooter} websiteName={websiteName} palette={palette} />} />)}
                </div>
            </div>
            <div className="flex items-center justify-between gap-3 border-t border-white/10 px-6 py-4"><p className="text-[10px] text-slate-500">Manual layout changes use 0 credits. Save Draft or Publish to persist them.</p><button type="button" onClick={onClose} className="rounded-xl border border-white/10 px-4 py-2 text-xs font-bold text-white hover:bg-white/5">Done</button></div>
        </div>
    </div>;
}
