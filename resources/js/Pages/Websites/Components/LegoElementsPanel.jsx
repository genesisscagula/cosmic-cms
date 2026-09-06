import { useMemo, useState } from 'react';

const GROUPS = [
    { label: 'Layout', items: [
        { type: 'row_1', label: '1 Column', icon: '▭' },
        { type: 'row_2', label: '2 Columns', icon: '▥' },
        { type: 'row_3', label: '3 Columns', icon: '▦' },
        { type: 'row_4', label: '4 Columns', icon: '▦' },
        { type: 'row_5', label: '5 Columns', icon: '▦' },
        { type: 'row_6', label: '6 Columns', icon: '▦' },
    ]},
    { label: 'Content', items: [
        { type: 'heading', label: 'Heading', icon: 'H' },
        { type: 'text', label: 'Rich Text', icon: '¶' },
        { type: 'button', label: 'Button', icon: '↗' },
        { type: 'badge', label: 'Badge', icon: '●' },
        { type: 'list', label: 'List', icon: '☷' },
        { type: 'quote', label: 'Quote', icon: '“' },
        { type: 'icon', label: 'Icon', icon: '✦' },
        { type: 'stat', label: 'Stat', icon: '#' },
    ]},
    { label: 'Media', items: [
        { type: 'image', label: 'Image', icon: '◇' },
        { type: 'video', label: 'Video', icon: '▶' },
    ]},
    { label: 'Cards & Grids', items: [
        { type: 'basic_card', label: 'Basic Card', icon: '▢' },
        { type: 'icon_card', label: 'Icon Card', icon: '✦' },
        { type: 'image_card', label: 'Image Card', icon: '▣' },
        { type: 'cards_grid', label: 'Cards Grid', icon: '▦' },
        { type: 'services_grid', label: 'Services Grid', icon: '⌘' },
        { type: 'stats_grid', label: 'Stats Grid', icon: '#' },
    ]},
    { label: 'Content Blocks', items: [
        { type: 'content_stack', label: 'Content Stack', icon: '☰' },
        { type: 'image_content', label: 'Image + Content', icon: '◧' },
        { type: 'cta_block', label: 'CTA Block', icon: '→' },
        { type: 'team_grid', label: 'Team Grid', icon: '♙' },
        { type: 'testimonials_grid', label: 'Testimonials', icon: '“' },
        { type: 'pricing_grid', label: 'Pricing Grid', icon: '$' },
    ]},
    { label: 'Interactive & Media', items: [
        { type: 'accordion', label: 'Accordion', icon: '≡' },
        { type: 'tabs', label: 'Tabs', icon: '▤' },
        { type: 'logo_carousel', label: 'Logo Carousel', icon: '◫' },
        { type: 'gallery', label: 'Gallery', icon: '▦' },
        { type: 'lightbox_gallery', label: 'Lightbox Gallery', icon: '⌗' },
        { type: 'image_carousel', label: 'Image Carousel', icon: '◁' },
    ]},
    { label: 'Utility', items: [
        { type: 'divider', label: 'Divider', icon: '—' },
        { type: 'spacer', label: 'Spacer', icon: '↕' },
    ]},
];

export default function LegoElementsPanel({ open, onClose, onInsert, onAddSection, onAddRow, activeBlockIndex = null, hybridMode = false }) {
    const [query, setQuery] = useState('');
    const groups = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return hybridMode ? GROUPS : GROUPS.filter((group) => group.label !== 'Layout');
        const source = hybridMode ? GROUPS : GROUPS.filter((group) => group.label !== 'Layout');
        return source.map((group) => ({ ...group, items: group.items.filter((item) => item.label.toLowerCase().includes(q)) })).filter((group) => group.items.length);
    }, [query, hybridMode]);

    if (!open) return null;
    return <aside className="cosmic-lego-panel" aria-label="Build Your Own elements">
        <div className="cosmic-lego-panel__head">
            <div><div className="cosmic-lego-panel__eyebrow">Build Your Own</div><strong>Elements</strong></div>
            <button type="button" onClick={onClose} aria-label="Close elements panel">×</button>
        </div>
        {!hybridMode ? <div className="cosmic-lego-panel__structure">
            <button type="button" className="is-primary" onClick={() => onAddSection?.(activeBlockIndex)}><span aria-hidden="true">＋</span><strong>Add Section</strong><small>New Build Your Own section</small></button>
            <button type="button" onClick={() => onAddRow?.(1, activeBlockIndex)}><span aria-hidden="true">▭</span><strong>Add Row</strong><small>Start with 1 column</small></button>
        </div> : null}
        {!hybridMode ? <div className="cosmic-lego-panel__layouts"><div className="cosmic-lego-panel__layouts-label">Row layout</div><div className="cosmic-lego-panel__layouts-grid">{[1,2,3,4,5,6].map((count)=><button key={count} type="button" onClick={() => onAddRow?.(count, activeBlockIndex)} title={`Add ${count}-column row`}><span aria-hidden="true">{count}</span><small>{count} Column{count>1?'s':''}</small></button>)}</div></div> : null}
        <div className="cosmic-lego-panel__search"><input value={query} onChange={(e)=>setQuery(e.target.value)} placeholder="Search elements…" aria-label="Search elements" /></div>
        <div className="cosmic-lego-panel__hint">{hybridMode ? 'Drag core elements into the dotted Spark insertion zones. Click adds to the selected/best safe slot.' : 'Drag elements into the selected section. Layout and section controls stay above the library.'}</div>
        <div className="cosmic-lego-panel__body">
            {groups.map((group) => <section key={group.label}>
                <h3>{group.label}</h3>
                <div className="cosmic-lego-panel__grid">
                    {group.items.map((item) => <button
                        key={item.type}
                        type="button"
                        draggable
                        onDragStart={(event) => {
                            event.dataTransfer.effectAllowed = 'copy';
                            event.dataTransfer.setData('application/x-cosmic-lego', JSON.stringify({ kind: 'new', type: item.type }));
                            event.dataTransfer.setData('text/plain', item.type);
                        }}
                        onClick={() => onInsert?.(item.type, activeBlockIndex)}
                        title={`Drag ${item.label} into the page`}
                    ><span aria-hidden="true">{item.icon}</span><small>{item.label}</small></button>)}
                </div>
            </section>)}
        </div>
    </aside>;
}
