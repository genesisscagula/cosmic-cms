import React, { useState } from 'react';
import { getEffectiveTheme } from '../../theme/Theme';
import { logoFilterForImage } from '@/Branding/logoFilters';

function EditableText({ value, onSave, className }) {
    const [isEditing, setIsEditing] = useState(false);
    const [currentValue, setCurrentValue] = useState(value || '');

    return (
        <>
            <div className="relative group/text cursor-pointer" onClick={() => setIsEditing(true)}>
                <span className={className}>{value || 'Click to add text'}</span>
                <span className="absolute -top-2 -right-6 hidden group-hover/text:inline-block bg-indigo-600 text-white text-[9px] px-1 rounded shadow">✏️</span>
            </div>
            {isEditing && (
                <div className="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-[9999] p-4">
                    <div className="bg-slate-900 p-6 rounded-2xl w-full max-w-md border border-slate-800 text-slate-100 font-sans space-y-4">
                        <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider">✏️ Update Nav Element</h3>
                        <input 
                            type="text"
                            className="w-full bg-slate-950 text-white p-3 text-sm rounded-xl border border-slate-700 focus:border-emerald-500 focus:outline-none"
                            value={currentValue}
                            onChange={(e) => setCurrentValue(e.target.value)}
                            autoFocus
                            onKeyDown={(e) => e.key === 'Enter' && (onSave(currentValue), setIsEditing(false))}
                        />
                        <div className="flex justify-end gap-2 text-xs">
                            <button type="button" onClick={() => setIsEditing(false)} className="px-3 py-1.5 bg-slate-800 rounded">Cancel</button>
                            <button type="button" onClick={() => { onSave(currentValue); setIsEditing(false); }} className="px-4 py-1.5 bg-emerald-600 rounded font-bold">Save</button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

export function DarkCyanHeader({ block, onUpdate, pageTargets = [], onLogoClick = null }) {
    const menuItems = block.menu || [{ label: 'Home', url: '#' }, { label: 'About', url: '#' }, { label: 'Services', url: '#' }];

    // Theme Config for Light Mode
    const theme = block.theme === 'white' ? 'bg-white border-slate-200' : 'bg-[#f8fafc] border-slate-200';
    const textColor = block.theme === 'white' ? 'text-slate-900' : 'text-slate-800';
    const subColor = 'text-slate-500';
    const accent = 'text-emerald-600';

    const logoImageUrl = typeof block.logo_image_url === 'string' ? block.logo_image_url.trim() : '';
    const logoHeight = Math.min(60, Math.max(24, Number(block.logo_height || 40)));

    return (
        <header className={`w-full ${theme} flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4 sm:px-6 sm:py-5 lg:flex-nowrap transition-colors duration-500`}>
            {logoImageUrl ? (
                <button type="button" onClick={onLogoClick || undefined} className={`shrink-0 rounded-lg ${onLogoClick ? "cursor-pointer focus:outline-none focus:ring-2 focus:ring-violet-400" : "cursor-default"}`} aria-label={onLogoClick ? "Adjust logo size" : undefined}><img src={logoImageUrl} alt={block.logo_text || 'Website logo'} style={{ height: `${logoHeight}px`, maxHeight: "60px", filter: logoFilterForImage(logoImageUrl, block.logo_filter_key || block.theme || 'midnight', block.logo_filter) }} className="w-auto max-w-[250px] object-contain" /></button>
            ) : (
                <EditableText 
                    value={block.logo_text || 'Your Website'} 
                    className={`text-2xl font-bold ${accent} cursor-pointer`}
                    onSave={(val) => onUpdate({ logo_text: val })}
                />
            )}
            <nav className="w-full lg:w-auto">
                <ul className="flex flex-wrap list-none items-center gap-x-3 gap-y-2 whitespace-nowrap sm:gap-x-5 lg:flex-nowrap">
                    <HeaderNavigation
                        items={menuItems}
                        textClass={`${textColor} text-base hover:text-emerald-600`}
                        onUpdate={(menu) => onUpdate({ menu })}
                        pageTargets={pageTargets}
                    />
                </ul>
            </nav>
        </header>
    );
}

export function GlassmorphismHeader({ block, onUpdate, globalTheme, pageTargets = [], onLogoClick = null }) {
    const menuItems = block.menu || [
        { label: 'Home', url: '#' }, 
        { label: 'About', url: '#' }, 
        { label: 'Services', url: '#' }, 
        { label: 'Blog', url: '#' }
    ];

    // Theme Config for Light/Neutral
    const theme = block.theme === 'white' ? 'bg-white' : 'bg-[#f8fafc]';
    const textColor = 'text-slate-900';
    const subColor = 'text-slate-500';

    const primaryTheme = getEffectiveTheme('primary', globalTheme);

    const logoImageUrl = typeof block.logo_image_url === 'string' ? block.logo_image_url.trim() : '';
    const logoHeight = Math.min(60, Math.max(24, Number(block.logo_height || 40)));

    return (
        <header className={`w-full ${theme} flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:px-6 sm:py-5 lg:flex-nowrap`}>
            {logoImageUrl ? (
                <button type="button" onClick={onLogoClick || undefined} className={`shrink-0 rounded-lg ${onLogoClick ? "cursor-pointer focus:outline-none focus:ring-2 focus:ring-violet-400" : "cursor-default"}`} aria-label={onLogoClick ? "Adjust logo size" : undefined}><img src={logoImageUrl} alt={block.logo_text || 'Website logo'} style={{ height: `${logoHeight}px`, maxHeight: "60px", filter: logoFilterForImage(logoImageUrl, block.logo_filter_key || block.theme || 'midnight', block.logo_filter) }} className="w-auto max-w-[250px] object-contain" /></button>
            ) : (
                <EditableText 
                    value={block.logo_text || 'Your Website'} 
                    className={`text-xl font-extrabold tracking-wide ${textColor} cursor-pointer`}
                    onSave={(val) => onUpdate({ logo_text: val })}
                />
            )}
            <nav className="flex w-full items-center justify-between gap-4 lg:w-auto lg:justify-start lg:gap-6">
                <ul className="flex flex-wrap list-none gap-x-3 gap-y-2 whitespace-nowrap sm:gap-x-5 lg:flex-nowrap">
                    <HeaderNavigation
                        items={menuItems}
                        textClass={`${subColor} text-[15px] font-medium hover:text-slate-900`}
                        onUpdate={(menu) => onUpdate({ menu })}
                        pageTargets={pageTargets}
                    />
                </ul>
                <div
                    className={`cosmic-header-cta
                        ${primaryTheme.bg}
                        ${primaryTheme.text}
                        px-7
                        py-3
                        shrink-0
                        rounded-full
                        text-sm
                        font-semibold
                        cursor-pointer
                        hover:opacity-90
                        transition
                    `}
                >
                    <EditableText 
                        value={block.cta_label || 'Get Started'} 
                        className="text-white font-bold"
                        onSave={(val) => onUpdate({ cta_label: val })}
                    />
                </div>
            </nav>
        </header>
    );
}

function HeaderNavigation({ items, textClass, onUpdate, pageTargets = [] }) {
    const updateAtPath = (path, changes) => {
        const next = JSON.parse(JSON.stringify(items || []));
        let collection = next;
        path.forEach((index, depth) => {
            if (depth === path.length - 1) {
                collection[index] = { ...collection[index], ...changes };
                return;
            }
            collection = collection[index].children || [];
        });
        onUpdate(next);
    };

    const normalizeTarget = (value) => String(value || '')
        .trim()
        .replace(/^\/+|\/+$/g, '')
        .toLowerCase();

    const findPageTarget = (url) => {
        const normalized = normalizeTarget(url);
        if (!normalized || normalized === '#' || normalized.startsWith('http') || normalized.startsWith('mailto:') || normalized.startsWith('tel:')) return null;
        return pageTargets.find((pageTarget) => normalizeTarget(pageTarget.slug) === normalized) || null;
    };

    const navigationGroups = [
        {
            wrapper: 'group/header-root',
            reveal: 'group-hover/header-root:visible group-hover/header-root:opacity-100 group-focus-within/header-root:visible group-focus-within/header-root:opacity-100',
        },
        {
            wrapper: 'group/header-sub',
            reveal: 'group-hover/header-sub:visible group-hover/header-sub:opacity-100 group-focus-within/header-sub:visible group-focus-within/header-sub:opacity-100',
        },
        {
            wrapper: 'group/header-deep',
            reveal: 'group-hover/header-deep:visible group-hover/header-deep:opacity-100 group-focus-within/header-deep:visible group-focus-within/header-deep:opacity-100',
        },
    ];

    const renderItems = (menu, parentPath = [], depth = 0) => menu.map((item, index) => {
        const path = [...parentPath, index];
        const children = Array.isArray(item.children) ? item.children : [];
        const targetListId = `header-page-targets-${path.join('-')}`;
        const navigationGroup = navigationGroups[Math.min(depth, navigationGroups.length - 1)];
        const nested = depth > 0;

        const linkedPage = findPageTarget(item.url);

        return <li
            key={path.join('-')}
            className={`${navigationGroup.wrapper} relative z-[520]`}
            onMouseEnter={() => document.documentElement.classList.add('cosmic-header-menu-active')}
            onMouseLeave={() => document.documentElement.classList.remove('cosmic-header-menu-active')}
        >
            <HeaderMenuItemEditor
                item={item}
                textClass={textClass}
                targetListId={targetListId}
                pageTargets={pageTargets}
                hasChildren={children.length > 0}
                linkedPage={linkedPage}
                onSave={(changes) => updateAtPath(path, changes)}
            />
            {children.length > 0 && (
                <div className={`${nested ? 'left-full top-0 pl-2' : 'left-0 top-full pt-2'} ${navigationGroup.reveal} invisible absolute z-[530] min-w-52 opacity-0 transition duration-150`}>
                    <ul className={`list-none rounded-xl border bg-white p-2 shadow-xl ring-1 ring-slate-950/5 ${nested ? 'border-slate-300' : 'border-slate-200'}`}>
                        {renderItems(children, path, depth + 1)}
                    </ul>
                </div>
            )}
        </li>;
    });

    return <>{renderItems(items || [])}</>;
}

function HeaderMenuItemEditor({ item, textClass, targetListId, pageTargets, hasChildren, linkedPage, onSave }) {
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
            <div className="group/menu-edit flex max-w-full items-center gap-0.5 rounded-md hover:bg-slate-950/5">
                <button
                    type="button"
                    onClick={openEditor}
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
                        onClick={(event) => {
                            event.preventDefault();
                            event.stopPropagation();
                            window.location.assign(`/pages/${linkedPage.id}/builder`);
                        }}
                        className="mr-1 hidden h-6 w-6 shrink-0 items-center justify-center rounded-md text-slate-400 transition hover:bg-violet-500/10 hover:text-violet-600 group-hover/menu-edit:flex group-focus-within/menu-edit:flex"
                        title={`Open ${linkedPage.title || item.label || 'page'} in Builder`}
                        aria-label={`Open ${linkedPage.title || item.label || 'page'} in Builder`}
                    >
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.7" className="h-3.5 w-3.5" aria-hidden="true">
                            <path d="M4 16h3.25L16 7.25 12.75 4 4 12.75V16Z" />
                            <path d="m11.75 5 3.25 3.25" />
                        </svg>
                    </button>
                )}
            </div>

            {isEditing && (
                <div className="fixed inset-0 z-[9999] flex items-center justify-center overflow-y-auto bg-black/70 p-4 backdrop-blur-sm" onMouseDown={() => setIsEditing(false)}>
                    <div className="box-border my-auto w-full min-w-0 max-w-[min(100%,28rem)] rounded-2xl border border-white/10 bg-[#18181d] p-5 text-slate-100 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby={`${targetListId}-title`} onMouseDown={(event) => event.stopPropagation()}>
                        <div className="mb-4 flex items-start justify-between gap-4">
                            <div>
                                <p className="text-[10px] font-semibold uppercase tracking-[0.18em] text-violet-300">Navigation item</p>
                                <h3 id={`${targetListId}-title`} className="mt-1 text-lg font-semibold text-white">Edit menu link</h3>
                            </div>
                            <button type="button" onClick={() => setIsEditing(false)} className="rounded-md px-2 py-1 text-slate-400 transition hover:bg-white/5 hover:text-white" aria-label="Close menu link editor">×</button>
                        </div>
                        <div className="space-y-3">
                            <label className="block min-w-0 text-xs font-medium text-slate-300">
                                Menu label
                                <input autoFocus value={label} onChange={(event) => setLabel(event.target.value)} className="box-border mt-1.5 block w-full min-w-0 max-w-full rounded-lg border border-white/10 bg-black/20 px-3 py-2.5 text-sm text-white outline-none transition placeholder:text-slate-600 focus:border-violet-400 focus:ring-2 focus:ring-violet-400/20" />
                            </label>
                            <label className="block min-w-0 text-xs font-medium text-slate-300">
                                Link target
                                <input value={url} list={targetListId} onChange={(event) => setUrl(event.target.value)} placeholder="Choose a page or enter a URL" className="box-border mt-1.5 block w-full min-w-0 max-w-full rounded-lg border border-white/10 bg-black/20 px-3 py-2.5 text-sm text-white outline-none transition placeholder:text-slate-600 focus:border-violet-400 focus:ring-2 focus:ring-violet-400/20" />
                                <datalist id={targetListId}>
                                    {pageTargets.map((pageTarget) => <option key={pageTarget.slug} value={pageTarget.slug}>{pageTarget.title}</option>)}
                                </datalist>
                                <span className="mt-1.5 block text-[11px] text-slate-500">Select a published page, or enter an external URL or #section anchor.</span>
                            </label>
                        </div>
                        <div className="mt-5 flex justify-end gap-2">
                            <button type="button" onClick={() => setIsEditing(false)} className="rounded-lg px-3 py-2 text-sm font-medium text-slate-300 transition hover:bg-white/5 hover:text-white">Cancel</button>
                            <button type="button" onClick={() => { onSave({ label: label.trim() || 'Menu item', url: url.trim() || '#' }); setIsEditing(false); }} className="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-slate-200">Save link</button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
