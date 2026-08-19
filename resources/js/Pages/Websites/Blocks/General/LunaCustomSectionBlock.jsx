import { usePage } from '@inertiajs/react';
import { EditableText } from '../Shared/EditableText';
import { EditableButton } from '../Shared/EditableButton';
import { EditableImage } from '../Shared/EditableImage';
import { getEffectiveTheme } from '../../../../theme/Theme';
import { colorFamilies } from '../../../../theme/colorFamilies';

export const LunaCustomSectionSchema = {
    type: 'luna_custom_section',
    title: 'Luna Custom Section',
    category: 'Luna Custom',
    purpose: 'AI-designed category section',
    defaults: {
        category: 'content', layout: 'editorial', alignment: 'left', media_position: 'none', density: 'balanced', accent_shape: 'none', section_mood: 'auto',
        eyebrow: '', heading: 'Custom section', text: '', primary_label: '', primary_url: '#', secondary_label: '', secondary_url: '#', image_url: '', items: [], theme: 'auto',
    },
    fields: [],
};

const spacing = { airy: 'py-24 lg:py-32', balanced: 'py-18 lg:py-24', compact: 'py-14 lg:py-18' };
const align = { left: 'text-left items-start', center: 'text-center items-center', right: 'text-right items-end' };

function ItemGrid({ items, theme, onUpdate, block }) {
    if (!items?.length) return null;
    const layout = block.layout;
    const count = items.length;
    const grid = layout === 'rail'
        ? 'grid-cols-1 md:grid-cols-2 xl:grid-cols-4'
        : layout === 'bento' || layout === 'mosaic'
            ? (count <= 3 ? 'grid-cols-1 md:grid-cols-3' : count <= 8 ? 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3' : 'grid-cols-1 md:grid-cols-3 lg:grid-cols-4')
            : count <= 2 ? 'grid-cols-1 md:grid-cols-2' : count <= 6 ? 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3' : 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4';
    return <div className={`mt-10 grid ${grid} gap-4`}>
        {items.map((item, i) => (
            <article key={i} className={`rounded-2xl border ${theme.border} ${theme.card || 'bg-white/5'} p-5 shadow-sm`}>
                {(item.label || item.value) && <div className="mb-3 flex items-center justify-between gap-3 text-xs font-bold uppercase tracking-[0.14em] opacity-70"><span>{item.label}</span><span>{item.value}</span></div>}
                {item.image_url && <EditableImage websiteId={null} blockIndex={i} src={item.image_url} showOverlay={false} blockType={block.type} className="mb-4 aspect-[4/3] w-full rounded-xl object-cover" onSave={(image_url)=>{const next=[...items];next[i]={...next[i],image_url};onUpdate({items:next});}} />}
                <EditableText value={item.title || `Item ${i+1}`} className={`block text-lg font-bold ${theme.text}`} onSave={(value)=>{const next=[...items];next[i]={...next[i],title:value};onUpdate({items:next});}} />
                <EditableText value={item.text || ''} isTextArea className={`mt-2 block text-sm leading-6 ${theme.sub}`} onSave={(value)=>{const next=[...items];next[i]={...next[i],text:value};onUpdate({items:next});}} />
            </article>
        ))}
    </div>;
}

export function LunaCustomSectionBlock({ block, blockIndex, onUpdate, globalTheme }) {
    const { props } = usePage();
    const websiteId = props.page?.website_id || props.website?.id;
    const moodTheme = {
        dark: 'midnight',
        surface: 'surface',
        light: 'white',
        primary: 'primary',
        accent: 'primary',
        image_overlay: 'midnight',
    }[block.section_mood] || null;
    const theme = getEffectiveTheme(moodTheme || (block.theme === 'auto' ? 'primary' : block.theme), globalTheme);
    const primary = colorFamilies[globalTheme?.primary || 'midnight'] || colorFamilies.midnight;
    const media = block.media_position || 'none';
    const split = block.layout === 'split' || block.layout === 'showcase' || media === 'left' || media === 'right';
    const reverse = media === 'left';
    const backgroundMedia = media === 'background' && block.image_url;
    const sectionAlign = align[block.alignment] || align.left;
    const sectionSpacing = spacing[block.density] || spacing.balanced;

    const copy = <div className={`relative z-10 flex flex-col ${sectionAlign}`}>
        {block.eyebrow ? <EditableText value={block.eyebrow} className={`text-xs font-bold uppercase tracking-[0.2em] ${theme.sub}`} onSave={(eyebrow)=>onUpdate({eyebrow})}/> : null}
        <EditableText value={block.heading || 'Custom section'} className={`mt-3 block max-w-4xl text-3xl font-black leading-tight sm:text-4xl lg:text-5xl ${theme.text}`} onSave={(heading)=>onUpdate({heading})}/>
        {block.text ? <EditableText value={block.text} isTextArea className={`mt-5 block max-w-2xl text-base leading-7 ${theme.sub}`} onSave={(text)=>onUpdate({text})}/> : null}
        {(block.primary_label || block.secondary_label) && <div className="mt-7 flex flex-wrap gap-3">
            {block.primary_label ? <EditableButton label={block.primary_label} url={block.primary_url || '#'} className={`${primary.bg} ${primary.text} rounded-xl px-5 py-3 text-sm font-bold shadow-sm`} onSave={(primary_label,primary_url)=>onUpdate({primary_label,primary_url})}/> : null}
            {block.secondary_label ? <EditableButton label={block.secondary_label} url={block.secondary_url || '#'} className={`rounded-xl border ${theme.border} px-5 py-3 text-sm font-bold ${theme.text}`} onSave={(secondary_label,secondary_url)=>onUpdate({secondary_label,secondary_url})}/> : null}
        </div>}
    </div>;

    const image = block.image_url && media !== 'none' ? <div className="relative z-10 overflow-hidden rounded-[2rem] border border-white/10 shadow-2xl"><EditableImage websiteId={websiteId} blockIndex={blockIndex} src={block.image_url} showOverlay={false} isBackground={backgroundMedia} blockType={block.type} className="aspect-[4/3] h-full w-full object-cover" onSave={(image_url)=>onUpdate({image_url})}/></div> : null;

    return <section data-luna-custom-category={block.category} data-luna-layout={block.layout} className={`relative isolate overflow-hidden px-6 ${sectionSpacing} ${theme.bg}`}>
        {backgroundMedia ? <div className="absolute inset-0 opacity-30"><EditableImage websiteId={websiteId} blockIndex={blockIndex} src={block.image_url} showOverlay={false} isBackground blockType={block.type} className="h-full w-full object-cover" onSave={(image_url)=>onUpdate({image_url})}/></div> : null}
        {block.accent_shape !== 'none' ? <div aria-hidden="true" className="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-white/10 blur-3xl"/> : null}
        <div className="relative mx-auto max-w-7xl">
            {split && !backgroundMedia ? <div className={`grid items-center gap-10 lg:grid-cols-2 ${reverse ? 'lg:[&>*:first-child]:order-2' : ''}`}>{copy}{image}</div> : <>{copy}{media === 'top' ? <div className="mt-10">{image}</div> : null}</>}
            <ItemGrid items={block.items || []} theme={theme} block={block} onUpdate={onUpdate}/>
        </div>
    </section>;
}
