import React, { useEffect, useState } from 'react';
import { getEffectiveTheme } from '../../theme/Theme';
import { logoStyleForTone } from '@/Branding/logoFilters';
import { colorFamilies } from '../../theme/colorFamilies';
import { resolveSemanticPalette } from '../../theme/semanticPalette';
import { headerVariantById, resolveShellSurface } from './Components/headerFooterVariantConfig';
import { EditableText as SharedEditableText } from './Blocks/Shared/EditableText';

const EditableText = SharedEditableText;

function overlayThemeVisuals(globalTheme) {
    const selection = typeof globalTheme === 'string' ? globalTheme : (globalTheme?.primary || 'midnight');
    const family = colorFamilies[selection] || colorFamilies.midnight;
    const gradient = family?.gradient || {};
    const palette = family?.palette || {};
    const from = gradient.from || palette.primary || '#0f766e';
    const via = gradient.via || palette.primary || from;
    const to = gradient.to || palette.secondary || via;
    const angle = Number(gradient.angle || 120);
    return {
        cta: `linear-gradient(${angle}deg, ${from}, ${via} 52%, ${to})`,
        border: `color-mix(in srgb, ${from} 50%, transparent)`,
        primary: palette.primary || from,
    };
}


function HeaderLogoEditor({ imageUrl, alt, imageStyle, imageClassName="", onManual, onAi }) {
    return (
        <div data-cosmic-shell-element="logo" data-cosmic-shell-path="header.logo_image_url" className="group/header-logo relative shrink-0">
            <img src={imageUrl} alt={alt} style={imageStyle} className={imageClassName} />
            {onAi && <div className="pointer-events-none absolute -right-2 -top-2 z-[620] flex gap-1 opacity-0 transition group-hover/header-logo:opacity-100 group-focus-within/header-logo:opacity-100">
                <button type="button" onClick={(e)=>{e.preventDefault();e.stopPropagation();onAi();}} className="pointer-events-auto inline-flex h-7 w-7 items-center justify-center rounded-full border border-violet-300/30 bg-violet-600 text-xs font-bold text-white shadow-lg hover:bg-violet-500" aria-label="Ask Luna about this logo">✦</button>
            </div>}
        </div>
    );
}

function HeaderCtaEditor({ block, className="", style, textClass="", onUpdate, onAi }) {
    const [open,setOpen]=useState(false);
    const [label,setLabel]=useState(block.cta_label || 'Get Started');
    const [url,setUrl]=useState(block.cta_url || '#');
    const openEditor=()=>{setLabel(block.cta_label || 'Get Started');setUrl(block.cta_url || '#');setOpen(true);};
    return <>
        <div data-cosmic-shell-element="cta" data-cosmic-shell-path="header.cta" className={`group/header-cta-edit relative ${className}`} style={style}>
            <button type="button" onClick={openEditor} className={`block w-full ${textClass}`}>{block.cta_label || 'Get Started'}</button>
            {onAi && <button type="button" onClick={(e)=>{e.preventDefault();e.stopPropagation();onAi();}} className="absolute -right-2 -top-2 hidden h-7 w-7 items-center justify-center rounded-full border border-violet-300/30 bg-violet-600 text-xs font-bold text-white shadow-lg hover:bg-violet-500 group-hover/header-cta-edit:inline-flex" aria-label="Ask Luna about this CTA">✦</button>}
        </div>
        {open && <div className="fixed inset-0 z-[9999] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm" onMouseDown={()=>setOpen(false)}>
            <div className="w-full max-w-md rounded-2xl border border-white/10 bg-[#18181d] p-5 text-white shadow-2xl" onMouseDown={(e)=>e.stopPropagation()}>
                <div className="flex items-start justify-between gap-4">
                    <div><p className="text-[10px] font-semibold uppercase tracking-[.18em] text-violet-300">Header CTA</p><h3 className="mt-1 text-lg font-semibold">Edit button</h3></div>
                    <button type="button" onClick={()=>setOpen(false)} className="rounded-md px-2 py-1 text-slate-400 hover:bg-white/5 hover:text-white">×</button>
                </div>
                <div className="mt-4 space-y-3">
                    <label className="block text-xs font-medium text-slate-300">Label<input autoFocus value={label} onChange={(e)=>setLabel(e.target.value)} className="mt-1.5 block w-full rounded-lg border border-white/10 bg-black/20 px-3 py-2.5 text-sm text-white outline-none focus:border-violet-400"/></label>
                    <label className="block text-xs font-medium text-slate-300">URL<input value={url} onChange={(e)=>setUrl(e.target.value)} placeholder="/contact or https://..." className="mt-1.5 block w-full rounded-lg border border-white/10 bg-black/20 px-3 py-2.5 text-sm text-white outline-none focus:border-violet-400"/></label>
                </div>
                <div className="mt-5 flex justify-end gap-2">
                    <button type="button" onClick={()=>setOpen(false)} className="rounded-lg px-3 py-2 text-sm text-slate-300 hover:bg-white/5">Cancel</button>
                    <button type="button" onClick={()=>{onUpdate({cta_label:label.trim()||'Get Started',cta_url:url.trim()||'#'});setOpen(false);}} className="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-slate-200">Save · 0 credits</button>
                </div>
            </div>
        </div>}
    </>;
}

export function DarkCyanHeader({ block, overlay = false, overlayTone = 'light', overlayLogoLight = false, globalTheme, onUpdate, pageTargets = [], onLogoClick = null, onLogoManual = null, onNavAi = null, navManualOpenSignal = 0 }) {
    const menuItems = block.menu || [{ label: 'Home', url: '#' }, { label: 'About', url: '#' }, { label: 'Services', url: '#' }];

    // Theme Config for Light Mode
    const theme = overlay ? 'bg-transparent border-transparent' : (block.theme === 'white' ? 'bg-white border-slate-200' : 'bg-[#f8fafc] border-slate-200');
    const overlayDarkText = overlay && overlayTone === 'dark';
    const textColor = overlay ? (overlayDarkText ? 'text-slate-950' : 'text-white') : (block.theme === 'white' ? 'text-slate-900' : 'text-slate-800');
    const subColor = overlay ? (overlayDarkText ? 'text-slate-800' : 'text-white/85') : 'text-slate-500';
    const accent = overlay ? (overlayDarkText ? 'text-slate-950' : 'text-white') : 'text-emerald-600';

    const overlayVisuals = overlayThemeVisuals(globalTheme);
    const resolvedLogo = logoStyleForTone(block, overlay && !overlayDarkText ? 'light' : 'dark', block.logo_filter_key || block.theme || 'midnight');
    const logoImageUrl = resolvedLogo.url;
    const logoHeight = Math.min(60, Math.max(44, Number(block.logo_height || 48)));
    const logoMaxWidth = Math.min(300, Math.max(180, Number(block.logo_max_width || 240)));

    const overlayStyle = overlay ? {
        backgroundImage: 'none',
        backgroundColor: 'transparent',
        backdropFilter: 'blur(3px)',
        WebkitBackdropFilter: 'blur(3px)',
    } : undefined;

    return (
        <header id={overlay ? 'cosmic-overlay-header' : undefined} style={overlayStyle} className={`w-full ${theme} flex flex-wrap items-center justify-between gap-3 ${overlay ? 'border-0 px-[3.5rem] pt-[3.25rem] pb-[2.5rem]' : 'border-b px-5 py-5 sm:px-6 sm:py-6'} lg:flex-nowrap transition-colors duration-500`}>
            {logoImageUrl ? (
                <HeaderLogoEditor
                    imageUrl={logoImageUrl}
                    alt={block.logo_text || 'Website logo'}
                    imageStyle={{ height: `${logoHeight}px`, maxHeight: "64px", maxWidth: `${logoMaxWidth}px`, filter: resolvedLogo.filter }}
                    imageClassName="w-auto object-contain"
                    onManual={onLogoManual}
                    onAi={onLogoClick}
                />
            ) : (
                <EditableText 
                    value={block.logo_text || 'Your Website'} 
                    className={`text-2xl font-bold ${overlay ? 'text-white' : accent} cursor-pointer`}
                    onSave={(val) => onUpdate({ logo_text: val })}
                />
            )}
            <nav className="w-full lg:w-auto">
                <HeaderNavigation
                    items={menuItems}
                    textClass={`${textColor} text-base hover:text-emerald-600`}
                    onUpdate={(menu) => onUpdate({ menu })}
                    pageTargets={pageTargets}
                    onAi={onNavAi}
                    manualOpenSignal={navManualOpenSignal}
                />
            </nav>
        </header>
    );
}

export function GlassmorphismHeader({ block, overlay = false, overlayTone = 'light', overlayLogoLight = false, overlayCtaTreatment = 'primary', onUpdate, globalTheme, pageTargets = [], onLogoClick = null, onLogoManual = null, onCtaAi = null, onNavAi = null, navManualOpenSignal = 0 }) {
    const menuItems = block.menu || [
        { label: 'Home', url: '#' }, 
        { label: 'About', url: '#' }, 
        { label: 'Services', url: '#' }, 
        { label: 'Blog', url: '#' }
    ];

    // Theme Config for Light/Neutral
    const theme = overlay ? 'bg-transparent' : (block.theme === 'white' ? 'bg-white' : 'bg-[#f8fafc]');
    const overlayDarkText = overlay && overlayTone === 'dark';
    const textColor = overlay ? (overlayDarkText ? 'text-slate-950' : 'text-white') : 'text-slate-900';
    const subColor = overlay ? (overlayDarkText ? 'text-slate-800' : 'text-white/85') : 'text-slate-500';

    const primaryTheme = getEffectiveTheme('primary', globalTheme);
    const overlayVisuals = overlayThemeVisuals(globalTheme);
    const customShell = Boolean(block.custom_shell_mode);
    const customStyle = customShell && block.custom_style ? block.custom_style : {};
    const overlayStyle = overlay ? {
        backgroundImage: 'none',
        backgroundColor: customShell ? (customStyle.background_color || 'transparent') : 'transparent',
        backdropFilter: customShell ? 'none' : 'blur(3px)',
        WebkitBackdropFilter: customShell ? 'none' : 'blur(3px)',
        minHeight: customShell ? `${Number(customStyle.height || 78)}px` : undefined,
        paddingLeft: customShell ? `${Number(customStyle.padding_x || 56)}px` : undefined,
        paddingRight: customShell ? `${Number(customStyle.padding_x || 56)}px` : undefined,
        '--cosmic-custom-nav': customStyle.nav_color || customStyle.text_color || '#ffffff',
        '--cosmic-custom-text': customStyle.text_color || '#ffffff',
        '--cosmic-custom-cta-bg': customStyle.cta_background || '#2F80FF',
        '--cosmic-custom-cta-color': customStyle.cta_color || '#ffffff',
        '--cosmic-custom-cta-radius': `${Number(customStyle.cta_radius || 8)}px`,
    } : (customShell ? {
        backgroundColor: customStyle.background_color || '#ffffff',
        minHeight: `${Number(customStyle.height || 78)}px`,
        paddingLeft: `${Number(customStyle.padding_x || 56)}px`,
        paddingRight: `${Number(customStyle.padding_x || 56)}px`,
        '--cosmic-custom-nav': customStyle.nav_color || customStyle.text_color || '#1f2937',
        '--cosmic-custom-text': customStyle.text_color || '#1f2937',
        '--cosmic-custom-cta-bg': customStyle.cta_background || '#2F80FF',
        '--cosmic-custom-cta-color': customStyle.cta_color || '#ffffff',
        '--cosmic-custom-cta-radius': `${Number(customStyle.cta_radius || 8)}px`,
    } : undefined);
    const overlayCtaStyle = customShell ? {
        background: customStyle.cta_background || '#2F80FF',
        backgroundColor: customStyle.cta_background || '#2F80FF',
        color: customStyle.cta_color || '#ffffff',
        WebkitTextFillColor: customStyle.cta_color || '#ffffff',
        borderRadius: `${Number(customStyle.cta_radius || 8)}px`,
        border: '0',
        boxShadow: 'none',
    } : (overlay && overlayCtaTreatment === 'gradient' ? {
        // Overlay headers still use the active brand primary for the CTA.
        // Header contrast may change nav/logo tone, but it must not wash the CTA to white.
        background: overlayVisuals.primary,
        backgroundColor: overlayVisuals.primary,
        color: '#ffffff',
        WebkitTextFillColor: '#ffffff',
        border: `1px solid ${overlayVisuals.primary}`,
        boxShadow: 'none',
        '--cosmic-overlay-cta-primary': overlayVisuals.primary,
        '--cosmic-overlay-cta-border': overlayVisuals.primary,
    } : undefined);

    const resolvedLogo = logoStyleForTone(block, (customShell && customStyle.logo_tone === 'light') || (overlay && !overlayDarkText) ? 'light' : 'dark', block.logo_filter_key || block.theme || 'midnight');
    const logoImageUrl = resolvedLogo.url;
    const logoHeight = Math.min(60, Math.max(44, Number(block.logo_height || 48)));
    const logoMaxWidth = Math.min(300, Math.max(180, Number(block.logo_max_width || 240)));

    return (
        <header id={overlay ? 'cosmic-overlay-header' : undefined} style={overlayStyle} className={`w-full ${theme} flex flex-wrap items-center justify-between gap-3 ${overlay ? 'border-0' : 'border-b'} ${customShell ? 'border-transparent py-3' : (overlay ? 'px-[3.5rem] pt-[3.25rem] pb-[2.5rem]' : 'border-slate-200 px-5 py-5 sm:px-6 sm:py-6')} lg:flex-nowrap`}>
            {logoImageUrl ? (
                <HeaderLogoEditor
                    imageUrl={logoImageUrl}
                    alt={block.logo_text || 'Website logo'}
                    imageStyle={{ height: `${logoHeight}px`, maxHeight: "64px", maxWidth: `${logoMaxWidth}px`, filter: resolvedLogo.filter }}
                    imageClassName="w-auto object-contain"
                    onManual={onLogoManual}
                    onAi={onLogoClick}
                />
            ) : (
                <EditableText 
                    value={block.logo_text || 'Your Website'} 
                    className={`text-xl font-extrabold tracking-wide ${customShell ? 'text-[color:var(--cosmic-custom-text)]' : (overlay ? 'text-white' : textColor)} cursor-pointer`}
                    onSave={(val) => onUpdate({ logo_text: val })}
                />
            )}
            <nav className="flex w-full items-center justify-between gap-4 lg:w-auto lg:justify-start lg:gap-6">
                <HeaderNavigation
                    items={menuItems}
                    textClass={`${customShell ? 'text-[color:var(--cosmic-custom-nav)]' : subColor} font-medium ${overlay ? (overlayDarkText ? 'hover:text-slate-950' : 'hover:text-white') : 'hover:text-slate-900'}`}
                    textStyle={customShell ? {fontSize:`${Number(customStyle.nav_size || 14)}px`} : undefined}
                    onUpdate={(menu) => onUpdate({ menu })}
                    pageTargets={pageTargets}
                    onAi={onNavAi}
                    manualOpenSignal={navManualOpenSignal}
                />
                {block.phone_enabled && String(block.phone_text || '').trim() ? (
                    <span className={`hidden shrink-0 items-center gap-2 text-sm font-semibold lg:inline-flex ${customShell ? 'text-[color:var(--cosmic-custom-nav)]' : subColor}`}>
                        <span aria-hidden="true">☎</span>{block.phone_text}
                    </span>
                ) : null}
                <HeaderCtaEditor
                    block={block}
                    onUpdate={onUpdate}
                    onAi={onCtaAi}
                    style={overlayCtaStyle}
                    className={`cosmic-header-cta ${overlayCtaTreatment === 'gradient' ? 'cosmic-overlay-gradient-cta' : 'cosmic-header-cta-primary'} ${overlayCtaTreatment === 'gradient' ? '' : `${primaryTheme.bg} ${primaryTheme.text}`} px-7 py-3 shrink-0 rounded-full text-sm font-semibold cursor-pointer hover:opacity-90 transition`}
                    textClass='text-white font-bold'
                />
            </nav>
        </header>
    );
}


const LEGACY_HEADER_VARIANT_ALIASES = {
    floating_glass_header: 'classic_header',
    minimal_header: 'classic_header',
};

/** Theme-aware global header variants shared by Builder preview and published shell contracts. */
export function PremiumHeaderVariant({ block, overlay = false, overlayTone = 'light', globalTheme, onUpdate, pageTargets = [], onLogoClick = null, onLogoManual = null, onCtaAi = null, onNavAi = null, navManualOpenSignal = 0 }) {
    const requestedType = LEGACY_HEADER_VARIANT_ALIASES[block.type] || block.type || 'classic_header';
    const config = headerVariantById(requestedType);
    const menuItems = block.menu || [{label:'Home',url:'#'},{label:'About',url:'#'},{label:'Services',url:'#'},{label:'Contact',url:'#'}];
    const familyKey = typeof globalTheme === 'string' ? globalTheme : (globalTheme?.primary || block.logo_filter_key || 'midnight');
    const palette = resolveSemanticPalette(familyKey, typeof globalTheme === 'object' ? globalTheme : {});
    const primary = palette.primary || '#243447';
    const forceOverlay = config.background === 'overlay';
    const baseSurface = forceOverlay
        ? { background:'transparent', text: overlayTone === 'dark' ? '#0F172A' : '#FFFFFF', muted: overlayTone === 'dark' ? '#334155' : 'rgba(255,255,255,.84)', border:'rgba(255,255,255,.22)', tone: overlayTone === 'dark' ? 'dark' : 'light' }
        : resolveShellSurface(config.background, palette);
    const logoTone = config.tone === 'auto' ? baseSurface.tone : (forceOverlay ? (overlayTone === 'dark' ? 'dark' : 'light') : config.tone);
    const resolvedLogo = logoStyleForTone(block, logoTone, familyKey);
    const logoImageUrl = resolvedLogo.url;
    const logoHeight = Math.min(60, Math.max(44, Number(block.logo_height || 48)));
    const logoMaxWidth = Math.min(300, Math.max(180, Number(block.logo_max_width || 240)));
    const logo = logoImageUrl ? <HeaderLogoEditor imageUrl={logoImageUrl} alt={block.logo_text || 'Website logo'} imageStyle={{height:`${logoHeight}px`,maxHeight:'64px',maxWidth:`${logoMaxWidth}px`,filter:resolvedLogo.filter}} imageClassName="w-auto object-contain" onManual={onLogoManual} onAi={onLogoClick}/> : <EditableText value={block.logo_text || 'Your Website'} className="cursor-pointer text-xl font-extrabold tracking-tight" onSave={(logo_text)=>onUpdate({logo_text})}/>;
    const navColor = baseSurface.text;
    const nav = (items = menuItems, onMenuUpdate = (menu)=>onUpdate({menu}), signal = navManualOpenSignal) => <HeaderNavigation items={items} textClass="font-medium transition hover:opacity-65" textStyle={{color:navColor}} onUpdate={onMenuUpdate} pageTargets={pageTargets} onAi={onNavAi} manualOpenSignal={signal}/>;
    const ctaMode = forceOverlay && config.cta !== 'none' ? 'white' : config.cta;
    const ctaStyle = ctaMode === 'white'
        ? {backgroundColor:'#FFFFFF',color:primary,border:`1px solid #FFFFFF`,borderRadius:'999px'}
        : {backgroundColor:primary,color:palette.onPrimary || palette.on_primary || '#FFFFFF',border:`1px solid ${primary}`,borderRadius:'999px'};
    const ctaTextClass = ctaMode === 'white' ? '' : 'text-white';
    const cta = config.cta === 'none' ? null : <HeaderCtaEditor block={block} onUpdate={onUpdate} onAi={onCtaAi} style={ctaStyle} className="shrink-0 px-6 py-3 text-sm font-semibold shadow-sm transition hover:opacity-90" textClass={`font-semibold ${ctaTextClass}`}/>;
    const headerStyle = {
        backgroundColor: forceOverlay ? 'transparent' : baseSurface.background,
        color: baseSurface.text,
        borderColor: forceOverlay ? 'transparent' : baseSurface.border,
        backdropFilter: forceOverlay ? 'blur(3px)' : undefined,
        WebkitBackdropFilter: forceOverlay ? 'blur(3px)' : undefined,
    };
    const headerClass = `${forceOverlay ? 'absolute inset-x-0 top-0 z-50 border-transparent' : 'relative z-30 border-b'} w-full px-5 py-4 sm:px-[50px]`;

    if (config.layout === 'split') {
        const half = Math.ceil(menuItems.length / 2);
        const left = menuItems.slice(0, half);
        const right = menuItems.slice(half);
        return <header id={forceOverlay?'cosmic-overlay-header':undefined} data-cosmic-header-variant={config.id} style={headerStyle} className={headerClass}>
            <div className="mx-auto grid max-w-[1600px] grid-cols-[1fr_auto_1fr] items-center gap-5">
                <nav className="hidden min-w-0 justify-self-start lg:block">{nav(left,(next)=>onUpdate({menu:[...next,...right]}))}</nav>
                <div className="justify-self-center">{logo}</div>
                <div className="hidden min-w-0 items-center justify-self-end gap-5 lg:flex"><nav>{nav(right,(next)=>onUpdate({menu:[...left,...next]}),0)}</nav>{cta}</div>
                <div className="col-span-3 flex items-center justify-between gap-4 lg:hidden"><nav className="min-w-0 flex-1">{nav(menuItems)}</nav>{cta}</div>
            </div>
        </header>;
    }

    return <header id={forceOverlay?'cosmic-overlay-header':undefined} data-cosmic-header-variant={config.id} style={headerStyle} className={headerClass}>
        <div className="mx-auto flex max-w-[1600px] flex-wrap items-center justify-between gap-4 lg:flex-nowrap">
            <div>{logo}</div>
            <nav className="order-3 w-full lg:order-none lg:ml-auto lg:w-auto">{nav()}</nav>
            {cta ? <div>{cta}</div> : null}
        </div>
    </header>;
}

function HeaderNavigation({ items, textClass, textStyle, onUpdate, pageTargets = [], onAi=null, manualOpenSignal=0 }) {
    const [managerOpen,setManagerOpen]=useState(false);
    const [mobileOpen,setMobileOpen]=useState(false);
    const [managerEditPath,setManagerEditPath]=useState(null);
    const [managerLabel,setManagerLabel]=useState('');
    const [managerUrl,setManagerUrl]=useState('#');
    useEffect(()=>{ if(Number(manualOpenSignal)>0) setManagerOpen(true); },[manualOpenSignal]);
    const clone=()=>JSON.parse(JSON.stringify(items || []));

    const collectionAtPath=(root,path=[])=>{
        let collection=root;
        for(const index of path){
            const item=collection[index];
            if(!item) return [];
            if(!Array.isArray(item.children)) item.children=[];
            collection=item.children;
        }
        return collection;
    };
    const updateAtPath = (path, changes) => {
        const next=clone();
        const parent=collectionAtPath(next,path.slice(0,-1));
        const index=path[path.length-1];
        if(parent[index]) parent[index]={...parent[index],...changes};
        onUpdate(next);
    };
    const removeAtPath=(path)=>{
        const next=clone();
        const parent=collectionAtPath(next,path.slice(0,-1));
        parent.splice(path[path.length-1],1);
        onUpdate(next);
    };
    const moveAtPath=(path,direction)=>{
        const next=clone();
        const parent=collectionAtPath(next,path.slice(0,-1));
        const index=path[path.length-1];
        const target=index+direction;
        if(target<0||target>=parent.length)return;
        [parent[index],parent[target]]=[parent[target],parent[index]];
        onUpdate(next);
    };
    const addAtPath=(parentPath=[])=>{
        const next=clone();
        const parent=collectionAtPath(next,parentPath);
        parent.push({label:'New item',url:'#'});
        onUpdate(next);
    };
    const addChildAtPath=(path)=>{
        const next=clone();
        const parent=collectionAtPath(next,path.slice(0,-1));
        const item=parent[path[path.length-1]];
        if(!item)return;
        item.children=Array.isArray(item.children)?item.children:[];
        item.children.push({label:'Submenu item',url:'#'});
        onUpdate(next);
    };

    const openManagerEdit=(path,item)=>{
        setManagerEditPath(path);
        setManagerLabel(item?.label||'');
        setManagerUrl(item?.url||'#');
    };
    const saveManagerEdit=()=>{
        if(!Array.isArray(managerEditPath))return;
        updateAtPath(managerEditPath,{label:managerLabel.trim()||'Menu item',url:managerUrl.trim()||'#'});
        setManagerEditPath(null);
    };

    const normalizeTarget = (value) => String(value || '').trim().replace(/^\/+|\/+$/g, '').toLowerCase();
    const findPageTarget = (url) => {
        const normalized=normalizeTarget(url);
        if(!normalized||normalized==='#'||normalized.startsWith('http')||normalized.startsWith('mailto:')||normalized.startsWith('tel:'))return null;
        return pageTargets.find((pageTarget)=>normalizeTarget(pageTarget.slug)===normalized)||null;
    };

    const navigationGroups=[
        {wrapper:'group/header-root',reveal:'group-hover/header-root:visible group-hover/header-root:opacity-100 group-focus-within/header-root:visible group-focus-within/header-root:opacity-100'},
        {wrapper:'group/header-sub',reveal:'group-hover/header-sub:visible group-hover/header-sub:opacity-100 group-focus-within/header-sub:visible group-focus-within/header-sub:opacity-100'},
        {wrapper:'group/header-deep',reveal:'group-hover/header-deep:visible group-hover/header-deep:opacity-100 group-focus-within/header-deep:visible group-focus-within/header-deep:opacity-100'},
    ];

    const renderItems=(menu,parentPath=[],depth=0)=>menu.map((item,index)=>{
        const path=[...parentPath,index];
        const children=Array.isArray(item.children)?item.children:[];
        const targetListId=`header-page-targets-${path.join('-')}`;
        const navigationGroup=navigationGroups[Math.min(depth,navigationGroups.length-1)];
        const nested=depth>0;
        const linkedPage=findPageTarget(item.url);
        return <li key={path.join('-')} className={`${navigationGroup.wrapper} relative z-[520]`}
            onMouseEnter={()=>document.documentElement.classList.add('cosmic-header-menu-active')}
            onMouseLeave={()=>document.documentElement.classList.remove('cosmic-header-menu-active')}>
            <HeaderMenuItemEditor item={item} textClass={textClass} textStyle={textStyle}
                targetListId={targetListId} pageTargets={pageTargets} hasChildren={children.length>0}
                linkedPage={linkedPage} fieldPath={`header.menu.${path.join('.')}`}
                onSave={(changes)=>updateAtPath(path,changes)}/>
            {children.length>0&&<div className={`${nested?'left-full top-0 pl-2':'left-0 top-full pt-2'} ${navigationGroup.reveal} invisible absolute z-[530] opacity-0 transition duration-150 min-w-52`}>
                <ul className={`list-none rounded-xl border bg-white p-2 shadow-xl ring-1 ring-slate-950/5 ${nested?'border-slate-300':'border-slate-200'}`}>
                    {renderItems(children,path,depth+1)}
                </ul>
            </div>}
        </li>;
    });

    const renderManagerItems=(menu,parentPath=[],depth=0)=>menu.map((item,index)=>{
        const path=[...parentPath,index];
        const children=Array.isArray(item.children)?item.children:[];
        return <div key={path.join('-')} className="space-y-2">
            <div className="flex items-center gap-2 rounded-xl border border-white/10 bg-white/[.04] p-2.5" style={{marginLeft:`${Math.min(depth,3)*16}px`}}>
                <div className="min-w-0 flex-1">
                    <div className="truncate text-sm font-semibold text-white">{item.label||'Menu item'}</div>
                    <div className="truncate text-[11px] text-slate-500">{item.url||'#'} · {`header.menu.${path.join('.')}`}</div>
                </div>
                <button type="button" onClick={()=>openManagerEdit(path,item)} className="rounded-lg px-2 py-1 text-xs font-semibold text-slate-200 hover:bg-white/10">Edit</button>
                <button type="button" onClick={()=>moveAtPath(path,-1)} disabled={index===0} className="rounded-lg px-2 py-1 text-xs text-slate-300 hover:bg-white/10 disabled:opacity-25">↑</button>
                <button type="button" onClick={()=>moveAtPath(path,1)} disabled={index===menu.length-1} className="rounded-lg px-2 py-1 text-xs text-slate-300 hover:bg-white/10 disabled:opacity-25">↓</button>
                {depth<2&&<button type="button" onClick={()=>addChildAtPath(path)} className="rounded-lg px-2 py-1 text-xs font-semibold text-violet-200 hover:bg-violet-500/10">+ Sub</button>}
                <button type="button" onClick={()=>removeAtPath(path)} className="rounded-lg px-2 py-1 text-xs text-rose-300 hover:bg-rose-500/10">Remove</button>
            </div>
            {children.length>0&&renderManagerItems(children,path,depth+1)}
        </div>;
    });

    const renderMobile=(menu,parentPath=[],depth=0)=>menu.map((item,index)=>{
        const children=Array.isArray(item.children)?item.children:[];
        return <li key={[...parentPath,index].join('-')} style={{paddingLeft:`${Math.min(depth,3)*14}px`}}>
            <div className="flex items-center gap-1">
                <a href={item.url||'#'} className="min-w-0 flex-1 rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-100">{item.label||'Menu item'}</a>
            </div>
            {children.length>0&&<ul className="list-none space-y-1">{renderMobile(children,[...parentPath,index],depth+1)}</ul>}
        </li>;
    });

    return <div data-cosmic-shell-element="navigation" data-cosmic-shell-path="header.menu" className="group/header-navigation relative">
        <div className="hidden lg:block">
            <ul className="flex list-none flex-nowrap items-center gap-x-3 gap-y-2 whitespace-nowrap sm:gap-x-5">{renderItems(items||[])}</ul>
        </div>
        <div className="lg:hidden">
            <button type="button" onClick={()=>setMobileOpen(v=>!v)} className="inline-flex h-10 items-center gap-2 rounded-full border border-current/20 px-3 text-sm font-semibold" aria-expanded={mobileOpen}>
                <span aria-hidden="true">☰</span> Menu
            </button>
            {mobileOpen&&<div className="absolute right-0 top-full z-[700] mt-2 w-[min(88vw,22rem)] rounded-2xl border border-slate-200 bg-white p-3 shadow-2xl">
                <ul className="list-none space-y-1">{renderMobile(items||[])}</ul>
            </div>}
        </div>
        {onAi&&<div className="pointer-events-none absolute -right-2 -top-8 z-[650] flex gap-1 opacity-0 transition group-hover/header-navigation:opacity-100 group-focus-within/header-navigation:opacity-100">
            <button type="button" onClick={onAi} className="pointer-events-auto inline-flex h-7 w-7 items-center justify-center rounded-full border border-violet-300/30 bg-violet-600 text-xs font-bold text-white shadow-lg" aria-label="Ask Luna about navigation">✦</button>
        </div>}

        {managerOpen&&<div className="fixed inset-0 z-[9999] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm" onMouseDown={()=>setManagerOpen(false)}>
            <div className="max-h-[85vh] w-full max-w-2xl overflow-y-auto rounded-2xl border border-white/10 bg-[#18181d] p-5 text-white shadow-2xl" onMouseDown={(e)=>e.stopPropagation()}>
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <p className="text-[10px] font-semibold uppercase tracking-[.18em] text-violet-300">Global navigation</p>
                        <h3 className="mt-1 text-lg font-semibold">Manage menu</h3>
                        <p className="mt-1 text-xs text-slate-500">Manual menu changes use 0 credits and apply site-wide after Save.</p>
                    </div>
                    <button type="button" onClick={()=>setManagerOpen(false)} className="rounded-md px-2 py-1 text-slate-400 hover:bg-white/5 hover:text-white">×</button>
                </div>
                <div className="mt-5 space-y-2">{renderManagerItems(items||[])}</div>
                {Array.isArray(managerEditPath)&&<div className="mt-4 rounded-xl border border-violet-300/20 bg-violet-500/[.06] p-4">
                    <p className="text-[10px] font-bold uppercase tracking-[.14em] text-violet-300">Edit menu item</p>
                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                        <label className="text-xs font-medium text-slate-300">Label
                            <input value={managerLabel} onChange={(e)=>setManagerLabel(e.target.value)} className="mt-1.5 block w-full rounded-lg border border-white/10 bg-black/20 px-3 py-2.5 text-sm text-white outline-none focus:border-violet-400"/>
                        </label>
                        <label className="text-xs font-medium text-slate-300">Link
                            <input value={managerUrl} list="header-manager-page-targets" onChange={(e)=>setManagerUrl(e.target.value)} className="mt-1.5 block w-full rounded-lg border border-white/10 bg-black/20 px-3 py-2.5 text-sm text-white outline-none focus:border-violet-400"/>
                            <datalist id="header-manager-page-targets">{pageTargets.map((target)=><option key={target.slug} value={target.slug}>{target.title}</option>)}</datalist>
                        </label>
                    </div>
                    <div className="mt-3 flex justify-end gap-2">
                        <button type="button" onClick={()=>setManagerEditPath(null)} className="rounded-lg px-3 py-2 text-xs font-semibold text-slate-400">Cancel</button>
                        <button type="button" onClick={saveManagerEdit} className="rounded-lg bg-violet-500 px-3 py-2 text-xs font-semibold text-white">Save item · 0 credits</button>
                    </div>
                </div>}
                <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-4">
                    <div className="flex items-center gap-3">
                        <button type="button" onClick={()=>addAtPath([])} className="rounded-lg bg-white px-3 py-2 text-sm font-semibold text-slate-950">+ Add menu item</button>
                        <span className="text-[11px] text-slate-500">Plain navigation · maximum 3 levels</span>
                    </div>
                    <button type="button" onClick={()=>setManagerOpen(false)} className="rounded-lg border border-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/5">Done</button>
                </div>
            </div>
        </div>}
    </div>;
}

function HeaderMenuItemEditor({ item, textClass, textStyle, targetListId, pageTargets, hasChildren, linkedPage, fieldPath, onSave }) {
    const [isEditing, setIsEditing] = useState(false);
    const [label, setLabel] = useState(item.label || 'Menu item');
    const [url, setUrl] = useState(item.url || '#');

    const openEditor = () => {
        setLabel(item.label || '');
        setUrl(item.url || '#');
        setIsEditing(true);
    };

    return (
        <>
            <div data-cosmic-shell-element="nav-item" data-cosmic-shell-path={fieldPath} className="group/menu-edit flex max-w-full items-center gap-0.5 rounded-md hover:bg-slate-950/5">
                <button
                    type="button"
                    onClick={openEditor}
                    style={textStyle}
                    className={`${textClass} flex max-w-full items-center gap-1 whitespace-nowrap rounded-md px-2 py-1.5 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500`}
                    aria-label={`Edit ${item.label || 'menu item'}`}
                >
                    <span>{item.label || 'Menu item'}</span>
                    {hasChildren && (
                        <svg aria-hidden="true" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.8" className="h-[0.35rem] w-[0.35rem] shrink-0 transition-transform duration-200">
                            <path d="m4 6 4 4 4-4" />
                        </svg>
                    )}
                </button>
                {linkedPage?.id && (
                    <button
                        type="button"
                        disabled={Boolean(linkedPage?.build_status) && linkedPage.build_status !== 'ready'}
                        onClick={(event) => {
                            event.preventDefault();
                            event.stopPropagation();
                            if (linkedPage?.build_status && linkedPage.build_status !== 'ready') return;
                            window.location.assign(linkedPage.builder_url || `/pages/${linkedPage.id}/builder`);
                        }}
                        className="mr-1 hidden h-6 w-6 shrink-0 items-center justify-center rounded-md text-slate-400 transition hover:bg-violet-500/10 hover:text-violet-600 disabled:cursor-wait disabled:opacity-35 disabled:hover:bg-transparent disabled:hover:text-slate-400 group-hover/menu-edit:flex group-focus-within/menu-edit:flex"
                        title={linkedPage?.build_status && linkedPage.build_status !== 'ready' ? `${linkedPage.title || item.label || 'Page'} is still building` : `Open ${linkedPage.title || item.label || 'page'} in Builder`}
                        aria-label={linkedPage?.build_status && linkedPage.build_status !== 'ready' ? `${linkedPage.title || item.label || 'Page'} is still building` : `Open ${linkedPage.title || item.label || 'page'} in Builder`}
                    >
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.7" className="h-3.5 w-3.5" aria-hidden="true">
                            <path d="M4 16h3.25L16 7.25 12.75 4 4 12.75V16Z" />
                            <path d="m11.75 5 3.25 3.25" />
                        </svg>
                    </button>
                )}
            </div>

            {isEditing && (
                <div className="cosmic-nav-link-dialog-overlay fixed inset-0 z-[9999] flex items-center justify-center overflow-y-auto bg-black/55 p-4" onMouseDown={() => setIsEditing(false)}>
                    <div className="cosmic-nav-link-dialog box-border my-auto w-full min-w-0 max-w-[min(100%,28rem)] rounded-2xl border border-slate-200 bg-white p-5 text-slate-900 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby={`${targetListId}-title`} onMouseDown={(event) => event.stopPropagation()}>
                        <div className="mb-4 flex items-start justify-between gap-4">
                            <div>
                                <p className="cosmic-nav-link-dialog__eyebrow text-[10px] font-semibold uppercase tracking-[0.18em]">Navigation item</p>
                                <h3 id={`${targetListId}-title`} className="cosmic-nav-link-dialog__title mt-1 text-lg font-semibold">Edit menu link</h3>
                            </div>
                            <button type="button" onClick={() => setIsEditing(false)} className="cosmic-nav-link-dialog__close rounded-md px-2 py-1 transition hover:bg-slate-100" aria-label="Close menu link editor">×</button>
                        </div>
                        <div className="space-y-3">
                            <label className="cosmic-nav-link-dialog__label block min-w-0 text-xs font-medium">
                                Menu label
                                <input autoFocus value={label} onChange={(event) => setLabel(event.target.value)} className="cosmic-nav-link-dialog__input box-border mt-1.5 block w-full min-w-0 max-w-full rounded-lg border px-3 py-2.5 text-sm outline-none transition focus:ring-2" />
                            </label>
                            <label className="cosmic-nav-link-dialog__label block min-w-0 text-xs font-medium">
                                Link target
                                <input value={url} list={targetListId} onChange={(event) => setUrl(event.target.value)} placeholder="Choose a page or enter a URL" className="cosmic-nav-link-dialog__input box-border mt-1.5 block w-full min-w-0 max-w-full rounded-lg border px-3 py-2.5 text-sm outline-none transition focus:ring-2" />
                                <datalist id={targetListId}>
                                    {pageTargets.map((pageTarget) => <option key={pageTarget.slug} value={pageTarget.slug}>{pageTarget.title}</option>)}
                                </datalist>
                                <span className="cosmic-nav-link-dialog__help mt-1.5 block text-[11px]">Select a published page, or enter an external URL or #section anchor.</span>
                            </label>
                        </div>
                        <div className="mt-5 flex justify-end gap-2">
                            <button type="button" onClick={() => setIsEditing(false)} className="cosmic-nav-link-dialog__cancel rounded-lg px-3 py-2 text-sm font-medium transition hover:bg-slate-100">Cancel</button>
                            <button type="button" onClick={() => { onSave({ label: label.trim() || 'Menu item', url: url.trim() || '#' }); setIsEditing(false); }} className="cosmic-nav-link-dialog__save rounded-lg px-4 py-2 text-sm font-semibold transition">Save link</button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
