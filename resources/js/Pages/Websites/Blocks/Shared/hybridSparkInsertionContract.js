import {
    createSparkExtraId,
    getSparkFieldExtras,
    normalizeSparkExtraTargetPath,
} from './sparkExtrasContract.js';

/**
 * Cosmic Hybrid Spark insertion contract v2.
 *
 * Existing Sparks keep their authored component/schema. Builder-only insertion
 * zones are projected around safe schema-backed leaf fields and persist through
 * the existing `field_extras` map, so legacy Sparks and Marketplace design kits
 * do not need to be rebuilt.
 */
export const HYBRID_SPARK_INSERTION_VERSION = 2;
export const HYBRID_SPARK_PLACEMENTS = Object.freeze(['before', 'after']);
export const HYBRID_SPARK_SAFE_TYPES = Object.freeze([
    'heading', 'text', 'button', 'image', 'icon', 'badge', 'video', 'divider', 'spacer', 'list', 'quote', 'stat',
]);

const SAFE_EXTRA_SET = new Set(HYBRID_SPARK_SAFE_TYPES);
const LEAF_FIELD_TYPES = new Set(['text', 'textarea', 'heading', 'label', 'url', 'email', 'tel', 'image']);
const STRUCTURAL_FIELD_TYPES = new Set(['repeater', 'group', 'object', 'array']);
const isObject = (value) => Boolean(value) && typeof value === 'object' && !Array.isArray(value);

const cleanPath = (parts) => normalizeSparkExtraTargetPath(parts.join('.'));

const stableItemSegment = (item, index) => {
    const candidate = String(item?._cosmic_id ?? item?.id ?? item?._id ?? item?.uuid ?? item?.key ?? '').trim();
    return /^[A-Za-z0-9][A-Za-z0-9_-]{0,127}$/.test(candidate) ? `@${candidate}` : String(index);
};

const leafIsUseful = (field, value) => {
    const type = String(field?.type || '').toLowerCase();
    if (STRUCTURAL_FIELD_TYPES.has(type) || type === 'boolean' || type === 'number') return false;
    if (LEAF_FIELD_TYPES.has(type)) return typeof value === 'string' || typeof value === 'number';
    return typeof value === 'string' && String(value).trim() !== '';
};

const walkFields = (fields, valueRoot, path, anchors) => {
    (Array.isArray(fields) ? fields : []).forEach((field) => {
        if (!isObject(field) || !field.key) return;
        const key = String(field.key);
        const nextPath = [...path, key];
        const value = isObject(valueRoot) || Array.isArray(valueRoot) ? valueRoot?.[key] : undefined;
        const type = String(field.type || '').toLowerCase();

        if ((type === 'repeater' || type === 'array') && Array.isArray(value) && Array.isArray(field.fields)) {
            value.forEach((item, index) => walkFields(field.fields, item, [...nextPath, stableItemSegment(item, index)], anchors));
            return;
        }

        if ((type === 'group' || type === 'object') && isObject(value) && Array.isArray(field.fields)) {
            walkFields(field.fields, value, nextPath, anchors);
            return;
        }

        if (!leafIsUseful(field, value)) return;
        const target = cleanPath(nextPath);
        if (!target) return;
        anchors.push({
            target,
            key,
            label: String(field.label || key),
            fieldType: type,
            value,
            slots: getSparkFieldExtras(valueRoot?.__cosmic_root_block || null, target),
        });
    });
};

export const getHybridSparkInsertionAnchors = (block, schema) => {
    if (!isObject(block) || !isObject(schema) || !Array.isArray(schema.fields)) return [];
    const anchors = [];

    // Pass root block separately while keeping recursive value traversal cheap.
    const visit = (fields, valueRoot, path = []) => {
        (Array.isArray(fields) ? fields : []).forEach((field) => {
            if (!isObject(field) || !field.key) return;
            const key = String(field.key);
            const nextPath = [...path, key];
            const value = isObject(valueRoot) || Array.isArray(valueRoot) ? valueRoot?.[key] : undefined;
            const type = String(field.type || '').toLowerCase();
            if ((type === 'repeater' || type === 'array') && Array.isArray(value) && Array.isArray(field.fields)) {
                value.forEach((item, index) => visit(field.fields, item, [...nextPath, stableItemSegment(item, index)]));
                return;
            }
            if ((type === 'group' || type === 'object') && isObject(value) && Array.isArray(field.fields)) {
                visit(field.fields, value, nextPath);
                return;
            }
            if (!leafIsUseful(field, value)) return;
            const target = cleanPath(nextPath);
            if (!target) return;
            anchors.push({
                target,
                key,
                label: String(field.label || key),
                fieldType: type,
                value,
                slots: getSparkFieldExtras(block, target),
            });
        });
    };
    visit(schema.fields, block, []);
    return anchors;
};

const targetScore = (anchor) => {
    const key = String(anchor?.key || '').toLowerCase();
    const label = String(anchor?.label || '').toLowerCase();
    const haystack = `${key} ${label}`;
    let score = 0;
    if (/(heading|headline|title)/.test(haystack)) score += 100;
    if (/(description|desc|intro|summary|content|text|copy)/.test(haystack)) score += 70;
    if (/(tagline|eyebrow|kicker|label)/.test(haystack)) score += 50;
    if (/(cta|button)/.test(haystack)) score += 25;
    if (/(url|href|link)/.test(haystack)) score -= 80;
    return score;
};

export const chooseHybridSparkDefaultInsertionTarget = (block, schema) => {
    const anchors = getHybridSparkInsertionAnchors(block, schema);
    if (!anchors.length) return null;
    return [...anchors].sort((a, b) => targetScore(b) - targetScore(a))[0]?.target || null;
};

export const createHybridSparkExtraFromLegoType = (requestedType) => {
    const type = String(requestedType || '').toLowerCase();
    if (!SAFE_EXTRA_SET.has(type)) return null;
    const base = { id: createSparkExtraId(), type, data: {} };
    if (type === 'heading') return { ...base, data: { text: 'New heading', level: 3 } };
    if (type === 'text') return { ...base, data: { text: 'Add your content here.' } };
    if (type === 'button') return { ...base, data: { label: 'Learn more', url: '#', target: '_self', rel: '' } };
    if (type === 'image') return { ...base, data: { src: '', alt: '', title: '', loading: 'lazy', object_fit: 'cover' } };
    if (type === 'video') return { ...base, data: { src: '', poster: '', autoplay: false, muted: true, loop: false, controls: true, plays_inline: true } };
    if (type === 'icon') return { ...base, data: { name: '✦', label: '' } };
    if (type === 'badge') return { ...base, data: { text: 'Badge' } };
    if (type === 'divider') return { ...base, data: { orientation: 'horizontal' } };
    if (type === 'spacer') return { ...base, data: { size: 'md' } };
    if (type === 'list') return { ...base, data: { items: ['First item', 'Second item', 'Third item'] } };
    if (type === 'quote') return { ...base, data: { text: 'Add a meaningful quote here.', cite: '' } };
    if (type === 'stat') return { ...base, data: { value: '100+', label: 'Result' } };
    return null;
};

export const canInsertLegoTypeIntoHybridSpark = (type) => SAFE_EXTRA_SET.has(String(type || '').toLowerCase());


const clonePortable = (value) => {
    try { return structuredClone(value); } catch (_) { return JSON.parse(JSON.stringify(value)); }
};

/** Convert a free-layout Lego node into a safe Spark extra while preserving supported local overrides. */
export const createHybridSparkExtraFromLegoNode = (node) => {
    if (!isObject(node)) return null;
    const type = String(node.type || '').toLowerCase();
    if (!SAFE_EXTRA_SET.has(type)) return null;
    const extra = createHybridSparkExtraFromLegoType(type);
    if (!extra) return null;
    const data = { ...extra.data };
    if (type === 'heading') { data.text = String(node.text ?? data.text); data.level = Math.max(1, Math.min(6, Number(node.level) || 2)); }
    if (type === 'text') data.text = String(node.text ?? data.text);
    if (type === 'button') { data.label = String(node.label ?? node.text ?? data.label); data.url = String(node.url ?? '#'); data.target = String(node.target ?? '_self'); data.rel = String(node.rel ?? ''); }
    if (type === 'image') { data.src = String(node.src ?? ''); data.alt = String(node.alt ?? ''); data.title = String(node.title ?? ''); data.loading = String(node.loading ?? 'lazy'); data.object_fit = String(node.object_fit ?? node.style?.object_fit ?? 'cover'); }
    if (type === 'video') { data.src = String(node.src ?? ''); data.poster = String(node.poster ?? ''); data.autoplay = Boolean(node.autoplay); data.muted = node.muted !== false; data.loop = Boolean(node.loop); data.controls = node.controls !== false; data.plays_inline = node.plays_inline !== false; }
    if (type === 'icon') { data.name = String(node.icon ?? node.name ?? '✦'); data.label = String(node.label ?? ''); }
    if (type === 'badge') data.text = String(node.label ?? node.text ?? data.text);
    if (type === 'divider') data.orientation = String(node.orientation ?? 'horizontal');
    if (type === 'spacer') data.size = String(node.size ?? 'md');
    if (type === 'list') data.items = (Array.isArray(node.items) ? node.items : []).slice(0, 40).map((item) => String(typeof item === 'object' ? (item.text ?? item.label ?? item.value ?? '') : item));
    if (type === 'quote') { data.text = String(node.text ?? data.text); data.cite = String(node.cite ?? ''); }
    if (type === 'stat') { data.value = String(node.value ?? data.value); data.label = String(node.label ?? data.label); }
    return { ...extra, data, style: clonePortable(node.style || {}), meta: { style_mode: node._cosmic_style_mode || 'global', responsive_mode: node._cosmic_responsive_mode || 'auto', auto_align: node._cosmic_auto_align !== false } };
};

/** Convert a Spark extra back to a normal Lego node for cross-container drag/drop. */
export const hybridSparkExtraToLegoNode = (extra) => {
    if (!isObject(extra)) return null;
    const type = String(extra.type || '').toLowerCase();
    if (!SAFE_EXTRA_SET.has(type)) return null;
    const d = isObject(extra.data) ? extra.data : {};
    const node = { type, style: clonePortable(extra.style || {}), _cosmic_style_mode: extra.meta?.style_mode || 'global', _cosmic_responsive_mode: extra.meta?.responsive_mode || 'auto', _cosmic_auto_align: extra.meta?.auto_align !== false };
    if (type === 'heading') Object.assign(node, { text: d.text || '', level: d.level || 2 });
    if (type === 'text') node.text = d.text || '';
    if (type === 'button') Object.assign(node, { label: d.label || 'Learn more', url: d.url || '#', target: d.target || '_self', rel: d.rel || '' });
    if (type === 'image') Object.assign(node, { src: d.src || '', alt: d.alt || '', title: d.title || '', loading: d.loading || 'lazy', object_fit: d.object_fit || 'cover' });
    if (type === 'video') Object.assign(node, { src: d.src || '', poster: d.poster || '', autoplay: Boolean(d.autoplay), muted: d.muted !== false, loop: Boolean(d.loop), controls: d.controls !== false, plays_inline: d.plays_inline !== false });
    if (type === 'icon') Object.assign(node, { icon: d.name || '✦', label: d.label || '' });
    if (type === 'badge') node.label = d.text || 'Badge';
    if (type === 'divider') node.orientation = d.orientation || 'horizontal';
    if (type === 'spacer') node.size = d.size || 'md';
    if (type === 'list') node.items = Array.isArray(d.items) ? d.items.map((text) => ({ text })) : [];
    if (type === 'quote') Object.assign(node, { text: d.text || '', cite: d.cite || '' });
    if (type === 'stat') Object.assign(node, { value: d.value || '', label: d.label || '' });
    return node;
};
