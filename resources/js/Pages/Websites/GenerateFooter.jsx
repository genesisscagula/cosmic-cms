import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { EditableText } from "./Blocks/Shared/EditableText";
import themeCatalog from "../../../theme/theme-families.json";
import { logoFilterForImage } from '@/Branding/logoFilters';

export const themeConfig = themeCatalog.legacyFooterFamilies;

const DEFAULT_COLUMNS = [
    { title: 'Company', items: [{ label: 'About us', url: '#about' }, { label: 'Careers', url: '#careers' }, { label: 'Contact', url: '#contact' }] },
    { title: 'Services', items: [{ label: 'What we do', url: '#services' }, { label: 'Solutions', url: '#solutions' }, { label: 'Pricing', url: '#pricing' }] },
    { title: 'Resources', items: [{ label: 'Insights', url: '#insights' }, { label: 'Guides', url: '#guides' }, { label: 'Updates', url: '#updates' }] },
];

const normalizeColumns = (columns) => {
    const source = Array.isArray(columns) && columns.length ? columns : DEFAULT_COLUMNS;
    return source.slice(0, 4).map((column, columnIndex) => ({
        title: column?.title || `Column ${columnIndex + 1}`,
        items: (Array.isArray(column?.items) && column.items.length ? column.items : [
            { label: 'Menu item', url: '#' },
            { label: 'Menu item', url: '#' },
            { label: 'Menu item', url: '#' },
        ]).slice(0, 6).map((item) => ({ label: item?.label || 'Menu item', url: item?.url || '#' })),
    }));
};

const moveItem = (items, from, to) => {
    if (to < 0 || to >= items.length) return items;
    const next = [...items];
    const [item] = next.splice(from, 1);
    next.splice(to, 0, item);
    return next;
};

const IconButton = ({ children, title, onClick, danger = false, disabled = false }) => (
    <button
        type="button"
        title={title}
        aria-label={title}
        disabled={disabled}
        onClick={onClick}
        className={`inline-flex h-7 w-7 items-center justify-center rounded-md border text-[11px] transition disabled:cursor-not-allowed disabled:opacity-30 ${danger ? 'border-slate-300/40 text-slate-400 hover:border-rose-300 hover:bg-rose-50 hover:text-rose-600' : 'border-slate-300/40 text-slate-400 hover:bg-white hover:text-slate-800'}`}
    >
        {children}
    </button>
);

function FooterLogo({ block, dark = false, mega = false, forceWhite = false }) {
    const logoImageUrl = typeof block.logo_image_url === 'string' ? block.logo_image_url.trim() : '';
    const baseHeight = Number(block.logo_height || 36);
    const logoHeight = mega ? Math.min(64, Math.max(44, baseHeight + 10)) : Math.min(56, Math.max(24, baseHeight));
    if (logoImageUrl) {
        return (
            <img
                src={logoImageUrl}
                alt={block.logo_text || 'Website logo'}
                style={{
                    height: `${logoHeight}px`,
                    maxHeight: mega ? '64px' : '56px',
                    filter: forceWhite ? 'brightness(0) invert(1)' : logoFilterForImage(logoImageUrl, block.logo_filter_key || block.theme || 'midnight', block.logo_filter),
                }}
                className={`w-auto object-contain ${mega ? "max-w-[300px]" : "max-w-[250px]"}`}
            />
        );
    }
    return <span className={`text-lg font-bold ${dark ? 'text-white' : 'text-slate-900'}`}>{block.logo_text || 'Your Logo'}</span>;
}

export function MinimalFooter({ block = {}, onUpdate = () => {}, editorMode = false, resolvedTheme = null }) {
    const megaEnabled = Boolean(block.mega_enabled ?? block.mega_footer?.enabled ?? false);
    const mega = {
        tagline: block.mega_footer?.tagline || 'A premium information-rich footer.',
        primary_label: block.mega_footer?.primary_label || 'Get in touch',
        primary_url: block.mega_footer?.primary_url || '#contact',
        columns: normalizeColumns(block.mega_footer?.columns),
    };
    const privacyLabel = block.privacy_label || 'Privacy Policy';
    const privacyUrl = block.privacy_url || '/privacy-policy';
    const termsLabel = block.terms_label || 'Terms & Conditions';
    const termsUrl = block.terms_url || '/terms-and-conditions';
    const copy = block.copyright || `© ${new Date().getFullYear()}. All rights reserved.`;
    const megaThemeKey = block.logo_filter_key || 'midnight';
    const familyTheme = themeCatalog.families?.[megaThemeKey] || themeConfig[megaThemeKey] || themeCatalog.families?.midnight || themeConfig.midnight || themeConfig.dark;
    const requestedMegaTheme = ['auto', 'primary', 'white', 'surface'].includes(block.mega_footer?.theme) ? block.mega_footer.theme : 'auto';
    const effectiveMegaTheme = requestedMegaTheme === 'auto' ? (resolvedTheme || 'primary') : requestedMegaTheme;
    const megaTheme = effectiveMegaTheme === 'white'
        ? { bg: 'bg-white', text: 'text-slate-900', sub: 'text-slate-500', border: 'border-slate-200' }
        : effectiveMegaTheme === 'surface'
            ? { bg: familyTheme.card || 'bg-slate-100', text: familyTheme.text || 'text-slate-900', sub: familyTheme.sub || 'text-slate-500', border: familyTheme.border || 'border-slate-200' }
            : familyTheme;
    const [editTarget, setEditTarget] = useState(null);

    const openEditor = (target) => editorMode && setEditTarget(target);
    const closeEditor = () => setEditTarget(null);
    const fieldValue = (field) => {
        if (!editTarget) return '';
        if (editTarget.kind === 'tagline') return mega.tagline;
        if (editTarget.kind === 'cta') return field === 'url' ? mega.primary_url : mega.primary_label;
        if (editTarget.kind === 'column') return mega.columns[editTarget.columnIndex]?.title || '';
        if (editTarget.kind === 'menu') return field === 'url' ? (mega.columns[editTarget.columnIndex]?.items?.[editTarget.itemIndex]?.url || '') : (mega.columns[editTarget.columnIndex]?.items?.[editTarget.itemIndex]?.label || '');
        if (editTarget.kind === 'privacy') return field === 'url' ? privacyUrl : privacyLabel;
        if (editTarget.kind === 'terms') return field === 'url' ? termsUrl : termsLabel;
        if (editTarget.kind === 'copyright') return copy;
        return '';
    };
    const updateEditField = (field, value) => {
        if (!editTarget) return;
        if (editTarget.kind === 'tagline') return updateMega({ tagline: value });
        if (editTarget.kind === 'cta') return updateMega(field === 'url' ? { primary_url: value } : { primary_label: value });
        if (editTarget.kind === 'column') return updateColumn(editTarget.columnIndex, { title: value });
        if (editTarget.kind === 'menu') return updateMenu(editTarget.columnIndex, editTarget.itemIndex, field === 'url' ? { url: value } : { label: value });
        if (editTarget.kind === 'privacy') return onUpdate(field === 'url' ? { privacy_url: value } : { privacy_label: value });
        if (editTarget.kind === 'terms') return onUpdate(field === 'url' ? { terms_url: value } : { terms_label: value });
        if (editTarget.kind === 'copyright') return onUpdate({ copyright: value });
    };

    const updateMega = (patch) => onUpdate({ mega_footer: { ...(block.mega_footer || {}), ...mega, theme: requestedMegaTheme, enabled: true, ...patch } });
    const updateColumns = (columns) => updateMega({ columns: normalizeColumns(columns) });

    const addColumn = () => {
        if (mega.columns.length >= 4) return;
        updateColumns([...mega.columns, {
            title: 'More',
            items: [
                { label: 'Menu item', url: '#' },
                { label: 'Menu item', url: '#' },
                { label: 'Menu item', url: '#' },
            ],
        }]);
    };

    const updateColumn = (columnIndex, patch) => updateColumns(mega.columns.map((column, index) => index === columnIndex ? { ...column, ...patch } : column));
    const removeColumn = (columnIndex) => updateColumns(mega.columns.filter((_, index) => index !== columnIndex));
    const moveColumn = (columnIndex, direction) => updateColumns(moveItem(mega.columns, columnIndex, columnIndex + direction));
    const addMenu = (columnIndex) => {
        const column = mega.columns[columnIndex];
        if (!column || column.items.length >= 6) return;
        updateColumn(columnIndex, { items: [...column.items, { label: 'Menu item', url: '#' }] });
    };
    const updateMenu = (columnIndex, itemIndex, patch) => {
        const column = mega.columns[columnIndex];
        updateColumn(columnIndex, { items: column.items.map((item, index) => index === itemIndex ? { ...item, ...patch } : item) });
    };
    const removeMenu = (columnIndex, itemIndex) => {
        const column = mega.columns[columnIndex];
        updateColumn(columnIndex, { items: column.items.filter((_, index) => index !== itemIndex) });
    };
    const moveMenu = (columnIndex, itemIndex, direction) => {
        const column = mega.columns[columnIndex];
        updateColumn(columnIndex, { items: moveItem(column.items, itemIndex, itemIndex + direction) });
    };

    return (
        <div className="w-full">
            {megaEnabled && (
                <section className={`w-full ${megaTheme?.bg || 'bg-slate-800'} ${megaTheme?.text || 'text-white'} border-b ${megaTheme?.border || 'border-slate-700'} px-6 py-10 sm:px-8 sm:py-12`}>
                    <div className="mx-auto grid max-w-[1500px] gap-12 lg:grid-cols-[minmax(280px,.9fr)_minmax(0,1.65fr)] lg:items-start">
                        <div className="min-w-0">
                            <FooterLogo block={block} dark={effectiveMegaTheme === 'primary'} mega forceWhite={effectiveMegaTheme === 'primary'} />
                            {editorMode ? (
                                <>
                                    <div className="group/footer-field relative mt-4 max-w-sm rounded-lg py-1 pr-9">
                                        <p className={`text-sm leading-6 ${megaTheme?.sub || 'text-slate-300'}`}>{mega.tagline}</p>
                                        <button type="button" onClick={() => openEditor({ kind: 'tagline', title: 'Edit footer description' })} className="absolute right-1 top-1/2 -translate-y-1/2 rounded-md border border-white/15 bg-black/20 px-2 py-1 text-[10px] text-white/70 opacity-0 transition hover:bg-white/10 group-hover/footer-field:opacity-100 focus:opacity-100" aria-label="Edit footer description">✎</button>
                                    </div>
                                    <div className="group/footer-field relative mt-4 inline-flex items-center pr-9">
                                        <span className="text-sm font-semibold text-current">{mega.primary_label}</span>
                                        <button type="button" onClick={() => openEditor({ kind: 'cta', title: 'Edit footer call to action' })} className="absolute right-0 rounded-md border border-white/15 bg-black/20 px-2 py-1 text-[10px] text-white/70 opacity-0 transition hover:bg-white/10 group-hover/footer-field:opacity-100 focus:opacity-100" aria-label="Edit footer call to action">✎</button>
                                    </div>
                                </>
                            ) : (
                                <>
                                    <p className={`mt-4 max-w-sm text-sm leading-6 ${megaTheme?.sub || 'text-slate-300'}`}>{mega.tagline}</p>
                                    <a href={mega.primary_url || '#contact'} className="mt-5 inline-flex text-sm font-semibold text-current hover:opacity-75">{mega.primary_label}</a>
                                </>
                            )}
                        </div>

                        <div className="group/columns ml-auto w-full lg:max-w-[860px]">
                            <div className={`grid gap-x-8 gap-y-7 ${mega.columns.length >= 4 ? 'sm:grid-cols-2 xl:grid-cols-4' : mega.columns.length === 3 ? 'sm:grid-cols-2 xl:grid-cols-3' : mega.columns.length === 2 ? 'sm:grid-cols-2' : 'grid-cols-1'}`}>
                                {mega.columns.map((column, columnIndex) => (
                                    <div key={`${column.title}-${columnIndex}`} className="group/column relative min-w-0">
                                        {editorMode ? (
                                            <div className="flex items-center gap-2">
                                                <button type="button" onClick={() => openEditor({ kind: 'column', columnIndex, title: 'Edit footer column' })} className={`min-w-0 flex-1 text-left text-[13px] font-bold uppercase tracking-[0.16em] transition ${megaTheme?.sub || 'text-slate-500'} hover:opacity-75`}>{column.title}</button>
                                                <div className="flex opacity-0 transition group-hover/column:opacity-100 focus-within:opacity-100">
                                                    <IconButton title="Edit column" onClick={() => openEditor({ kind: 'column', columnIndex, title: 'Edit footer column' })}>✎</IconButton>
                                                    <IconButton title="Move column left" disabled={columnIndex === 0} onClick={() => moveColumn(columnIndex, -1)}>←</IconButton>
                                                    <IconButton title="Move column right" disabled={columnIndex === mega.columns.length - 1} onClick={() => moveColumn(columnIndex, 1)}>→</IconButton>
                                                    <IconButton title="Remove column" danger disabled={mega.columns.length <= 1} onClick={() => removeColumn(columnIndex)}>⌫</IconButton>
                                                </div>
                                            </div>
                                        ) : (
                                            <p className={`text-[13px] font-bold uppercase tracking-[0.16em] ${megaTheme?.sub || 'text-slate-300'}`}>{column.title}</p>
                                        )}

                                        <div className="mt-3 space-y-1.5">
                                            {column.items.map((item, itemIndex) => (
                                                <div key={`${item.label}-${itemIndex}`} className="group/item relative">
                                                    {editorMode ? (
                                                        <div className="flex items-center justify-between gap-2 rounded-lg px-1 py-1 hover:bg-white/5">
                                                            <button type="button" onClick={() => openEditor({ kind: 'menu', columnIndex, itemIndex, title: 'Edit footer link' })} className="min-w-0 flex-1 truncate text-left text-sm text-current transition hover:opacity-75">{item.label}</button>
                                                            <div className="flex opacity-0 transition group-hover/item:opacity-100 focus-within:opacity-100">
                                                                <IconButton title="Edit menu" onClick={() => openEditor({ kind: 'menu', columnIndex, itemIndex, title: 'Edit footer link' })}>✎</IconButton>
                                                                <IconButton title="Move menu up" disabled={itemIndex === 0} onClick={() => moveMenu(columnIndex, itemIndex, -1)}>↑</IconButton>
                                                                <IconButton title="Move menu down" disabled={itemIndex === column.items.length - 1} onClick={() => moveMenu(columnIndex, itemIndex, 1)}>↓</IconButton>
                                                                <IconButton title="Remove menu" danger onClick={() => removeMenu(columnIndex, itemIndex)}>⌫</IconButton>
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <a href={item.url || '#'} className="block py-1 text-sm text-current transition hover:opacity-70">{item.label}</a>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                        {editorMode && (
                                            <button type="button" disabled={column.items.length >= 6} onClick={() => addMenu(columnIndex)} className="mt-3 rounded-md border border-dashed border-current/45 bg-transparent px-3 py-1.5 text-[11px] font-semibold opacity-0 transition hover:border-current hover:opacity-100 group-hover/column:opacity-80 focus:opacity-100 disabled:cursor-not-allowed disabled:opacity-35">+ Add menu</button>
                                        )}
                                    </div>
                                ))}
                            </div>
                            {editorMode && (
                                <div className="mt-7 flex justify-end">
                                    <button type="button" disabled={mega.columns.length >= 4} onClick={addColumn} className="rounded-md border border-dashed border-current/45 bg-transparent px-4 py-2 text-xs font-semibold opacity-0 transition hover:border-current hover:opacity-100 group-hover/columns:opacity-80 focus:opacity-100 disabled:cursor-not-allowed disabled:opacity-35">+ Add Column</button>
                                </div>
                            )}
                        </div>
                    </div>
                </section>
            )}

            <footer className="flex w-full flex-col items-start gap-4 border-t border-slate-200 bg-white px-6 py-8 text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-8 sm:py-10">
                {megaEnabled ? (
                    <div className="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
                        {editorMode ? (
                            <>
                                <button type="button" onClick={() => openEditor({ kind: 'privacy', title: 'Edit Privacy Policy link' })} className="group/legal inline-flex items-center gap-2 rounded-md px-1.5 py-1 text-sm text-slate-600 hover:bg-slate-100 hover:text-slate-900">{privacyLabel}<span className="opacity-0 transition group-hover/legal:opacity-100">✎</span></button>
                                <button type="button" onClick={() => openEditor({ kind: 'terms', title: 'Edit Terms & Conditions link' })} className="group/legal inline-flex items-center gap-2 rounded-md px-1.5 py-1 text-sm text-slate-600 hover:bg-slate-100 hover:text-slate-900">{termsLabel}<span className="opacity-0 transition group-hover/legal:opacity-100">✎</span></button>
                            </>
                        ) : (
                            <>
                                <a href={privacyUrl} className="transition hover:text-slate-900">{privacyLabel}</a>
                                <a href={termsUrl} className="transition hover:text-slate-900">{termsLabel}</a>
                            </>
                        )}
                    </div>
                ) : (
                    <div className="w-auto flex-shrink-0"><FooterLogo block={block} /></div>
                )}
                <div className="min-w-0 text-sm sm:whitespace-nowrap">
                    {editorMode ? (
                        <button type="button" onClick={() => openEditor({ kind: 'copyright', title: 'Edit copyright text' })} className="group/legal inline-flex items-center gap-2 rounded-md px-1.5 py-1 text-sm text-slate-500 hover:bg-slate-100 hover:text-slate-900"><span>{copy}</span><span className="opacity-0 transition group-hover/legal:opacity-100">✎</span></button>
                    ) : (
                        <span>{copy}</span>
                    )}
                </div>
            </footer>

            {editorMode && editTarget && typeof document !== 'undefined' ? createPortal(
                <div className="fixed inset-0 z-[1000500] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm" onMouseDown={(event) => { if (event.target === event.currentTarget) closeEditor(); }}>
                    <section className="w-full max-w-md rounded-2xl border border-white/10 bg-[#17171c] p-5 text-slate-100 shadow-2xl">
                        <div className="flex items-start justify-between gap-4">
                            <div><p className="text-[10px] font-bold uppercase tracking-[.18em] text-violet-300">Global footer</p><h3 className="mt-1 text-lg font-semibold text-white">{editTarget.title || 'Edit footer item'}</h3></div>
                            <button type="button" onClick={closeEditor} className="rounded-lg px-2 py-1 text-slate-400 hover:bg-white/10 hover:text-white">×</button>
                        </div>
                        <div className="mt-5 space-y-4">
                            <label className="block"><span className="mb-1.5 block text-xs font-semibold text-slate-300">{editTarget.kind === 'tagline' || editTarget.kind === 'copyright' ? 'Text' : editTarget.kind === 'column' ? 'Heading' : 'Label'}</span><input autoFocus value={fieldValue('label')} onChange={(event) => updateEditField('label', event.target.value)} className="w-full rounded-xl border border-white/10 bg-black/25 px-3 py-2.5 text-sm text-white outline-none focus:border-violet-400" /></label>
                            {['cta','menu','privacy','terms'].includes(editTarget.kind) ? <label className="block"><span className="mb-1.5 block text-xs font-semibold text-slate-300">URL</span><input value={fieldValue('url')} onChange={(event) => updateEditField('url', event.target.value)} placeholder="#section or /page" className="w-full rounded-xl border border-white/10 bg-black/25 px-3 py-2.5 text-sm text-white outline-none focus:border-violet-400" /></label> : null}
                        </div>
                        <div className="mt-5 flex justify-end"><button type="button" onClick={closeEditor} className="rounded-xl bg-white px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-slate-200">Done</button></div>
                    </section>
                </div>, document.body
            ) : null}
        </div>
    );
}
