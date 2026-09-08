import { createAiFlexExtra } from './aiFlexStructureContract';

const fieldsByType = {
    heading: ['text'], text: ['text'], button: ['label', 'url'],
    image: ['src', 'alt'], background_image: ['src', 'alt'],
    video: ['src', 'poster'], background_video: ['src', 'poster'],
    icon: ['icon', 'label'], badge: ['label'], quote: ['text', 'cite'],
    stat: ['value', 'label'], form: ['title', 'button_label', 'note'],
};
const labels = { text: 'Text', label: 'Label', url: 'Link URL', src: 'Media URL', alt: 'Alt text', poster: 'Poster URL', icon: 'Icon name', cite: 'Citation', value: 'Value', title: 'Title', caption: 'Caption', button_label: 'Button label', note: 'Note', placeholder: 'Placeholder', name: 'Field name' };
const itemTypes = ['list', 'accordion', 'tabs', 'gallery', 'lightbox_gallery', 'image_carousel', 'logo_carousel'];
const itemDefaults = { accordion: { title: '', text: '' }, tabs: { label: '', title: '', text: '' }, gallery: { src: '', alt: '' }, lightbox_gallery: { src: '', alt: '' }, image_carousel: { src: '', alt: '', caption: '' }, logo_carousel: { src: '', alt: '' } };

// Copy only the edited branch; preserve IDs, styles, and all unrelated content.
export function replaceLegoEntry(node, key, index, entry) {
    return { ...node, [key]: node[key].map((value, i) => i === index ? entry : value) };
}

function duplicateEntry(entry) {
    if (!entry || typeof entry !== 'object') return entry;
    if (Array.isArray(entry)) return entry.map(duplicateEntry);
    return Object.fromEntries(Object.entries(entry)
        .filter(([key]) => key !== '_cosmic_id' && key !== 'key')
        .map(([key, value]) => [key, duplicateEntry(value)]));
}

export default function LegoContentFields({ node, onChange }) {
    if (!node || typeof node !== 'object') return null;
    const keys = [...new Set([...(fieldsByType[node.type] || []), ...Object.keys(labels).filter(key => typeof node[key] === 'string' || typeof node[key] === 'number')])];
    const collections = ['children', 'items', 'fields'].filter(key => Array.isArray(node[key]) || (key === 'items' && itemTypes.includes(node.type)));
    return <div className="cosmic-lego-inspector__fields">
        {keys.map(key => <label key={key}><span>{key === 'text' && node.type === 'heading' ? 'Heading' : labels[key]}</span>
            {['text', 'note'].includes(key) && node.type !== 'heading'
                ? <textarea rows={3} value={node[key] ?? ''} onChange={event => onChange({ ...node, [key]: event.target.value })} />
                : <input value={node[key] ?? (key === 'label' ? node.text : '') ?? ''} onChange={event => onChange({ ...node, [key]: event.target.value })} />}
        </label>)}
        {collections.map(key => {
            const entries = node[key] || [];
            const label = key === 'fields' ? 'Field' : key === 'children' ? (node.type === 'grid' ? 'Card' : 'Element') : 'Item';
            return <div key={key} className="cosmic-lego-collection">
                <strong>{label}s ({entries.length})</strong>
                {entries.map((entry, index) => <details key={entry?._cosmic_id || index} className="cosmic-lego-collection__entry">
                    <summary>{label} {index + 1}{entry?.type ? ` · ${entry.type.replaceAll('_', ' ')}` : ''}</summary>
                    {entry && typeof entry === 'object'
                        ? <LegoContentFields node={{ ...(key === 'items' ? itemDefaults[node.type] : {}), ...entry }} onChange={value => onChange(replaceLegoEntry({ ...node, [key]: entries }, key, index, value))} />
                        : <label><span>{label} {index + 1}</span><input value={entry ?? ''} onChange={event => onChange(replaceLegoEntry({ ...node, [key]: entries }, key, index, event.target.value))} /></label>}
                    <button type="button" onClick={() => onChange({ ...node, [key]: entries.filter((_, i) => i !== index) })}>Remove {label.toLowerCase()}</button>
                </details>)}
                <button type="button" disabled={entries.length >= 12} onClick={() => {
                    const entry = entries.length ? duplicateEntry(entries[entries.length - 1])
                        : key === 'children' ? createAiFlexExtra(node.type === 'grid' ? 'basic_card' : 'text')
                        : key === 'fields' ? { type: 'text', label: 'New field', name: 'new_field', placeholder: '' }
                        : node.type === 'list' ? 'New item' : { ...(itemDefaults[node.type] || {}), ...(node.type === 'accordion' || node.type === 'tabs' ? { title: 'New item', text: 'Add your content here.' } : {}) };
                    onChange({ ...node, [key]: [...entries, entry] });
                }}>+ Add {label.toLowerCase()}</button>
            </div>;
        })}
        {!keys.length && !collections.length ? <p className="cosmic-lego-inspector__note">This element has no text or media content. Use Style or Layout to adjust its appearance.</p> : null}
    </div>;
}
