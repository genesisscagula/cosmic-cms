import {
    COSMIC_ITEM_ID_KEY,
    createCosmicRepeaterItemId,
    getNestedValue,
    mutateNestedRepeater,
    stableRepeaterItemId,
    updateNestedValue,
} from './nestedRepeaterEngine.js';

/**
 * AI Flex Rows -> Columns -> Extras contract v1.
 *
 * Persisted storage intentionally remains the Universal Elements tree so the
 * existing React renderer and PHP exporter stay authoritative:
 *   rows[]                  => block.elements[]
 *   rows[n].columns[]       => block.elements[n].children[]
 *   ...columns[n].extras[]  => column.children[]
 *
 * Luna and the Builder can use the logical collection names while mutations
 * are translated to the existing export-safe storage paths.
 */
export const AI_FLEX_STRUCTURE_VERSION = 1;
export const AI_FLEX_STRUCTURE_CONTRACT = 'rows_columns_extras_v1';
export const AI_FLEX_STORAGE_KEY = 'elements';
export const AI_FLEX_ROW_COLLECTION = 'rows';
export const AI_FLEX_COLUMN_COLLECTION = 'columns';
export const AI_FLEX_EXTRA_COLLECTION = 'extras';

const isObject = (value) => Boolean(value) && typeof value === 'object' && !Array.isArray(value);
const clone = (value) => {
    if (typeof structuredClone === 'function') {
        try { return structuredClone(value); } catch (_) { /* JSON fallback */ }
    }
    return JSON.parse(JSON.stringify(value));
};

const idPrefix = (role) => role === 'row' ? 'row' : role === 'column' ? 'column' : 'extra';
const freshId = (role) => `${idPrefix(role)}_${createCosmicRepeaterItemId().replace(/^item_/, '')}`;
const validId = (value) => /^[A-Za-z0-9][A-Za-z0-9_-]{0,127}$/.test(String(value || ''));

const withStableIds = (nodes, role = 'extra', used = new Set()) => (Array.isArray(nodes) ? nodes : []).map((raw) => {
    const node = isObject(raw) ? clone(raw) : {};
    let id = String(node[COSMIC_ITEM_ID_KEY] || '').trim();
    if (!validId(id) || used.has(id)) id = freshId(role);
    used.add(id);
    node[COSMIC_ITEM_ID_KEY] = id;
    if (Array.isArray(node.children)) node.children = withStableIds(node.children, 'extra', used);
    return node;
});

const makeColumn = (children = [], width = null) => ({
    type: 'column',
    [COSMIC_ITEM_ID_KEY]: freshId('column'),
    ...(width ? { style: { width } } : {}),
    children,
});

export const createAiFlexRow = () => ({
    type: 'row',
    [COSMIC_ITEM_ID_KEY]: freshId('row'),
    style: { gap: 24 },
    children: [makeColumn([], 100)],
});

export const createAiFlexColumn = (width = 100) => makeColumn([], width);

export const createAiFlexExtra = (type = 'text') => {
    const safe = ['heading','text','button','image','icon','badge','list','divider','stat','spacer','form','video','group','grid','stack','card','background_image','background_video','overlay','slider','slide','button_group','media_group'].includes(String(type)) ? String(type) : 'text';
    const base = { type: safe, [COSMIC_ITEM_ID_KEY]: freshId('extra') };
    if (safe === 'heading') return { ...base, text: 'New heading' };
    if (safe === 'button') return { ...base, label: 'Learn more', url: '#' };
    if (safe === 'image') return { ...base, src: '', alt: '' };
    if (safe === 'video') return { ...base, src: '', poster: '', controls: true };
    if (safe === 'background_image') return { ...base, src: '', alt: '', style: { min_height: 420, object_fit: 'cover', object_position: 'center' }, children: [] };
    if (safe === 'background_video') return { ...base, src: '', poster: '', controls: false, autoplay: true, muted: true, loop: true, plays_inline: true, style: { min_height: 420, object_fit: 'cover', object_position: 'center' }, children: [] };
    if (safe === 'overlay') return { ...base, style: { background: '#0f172a', opacity: 0.48, padding: 32 }, children: [] };
    if (safe === 'slider') return { ...base, autoplay: true, interval: 5000, loop: true, show_arrows: true, show_dots: true, show_counter: false, transition: 'fade', style: { min_height: 420, overflow: 'hidden' }, children: [{ type: 'slide', [COSMIC_ITEM_ID_KEY]: freshId('extra'), children: [] }, { type: 'slide', [COSMIC_ITEM_ID_KEY]: freshId('extra'), children: [] }] };
    if (safe === 'slide') return { ...base, style: { min_height: 420 }, children: [] };
    if (safe === 'button_group') return { ...base, style: { gap: 12, justify: 'start', align: 'center' }, children: [{ type: 'button', [COSMIC_ITEM_ID_KEY]: freshId('extra'), label: 'Learn more', url: '#' }] };
    if (safe === 'media_group') return { ...base, style: { columns: 2, gap: 16, tablet_columns: 2, mobile_columns: 1 }, children: [{ type: 'image', [COSMIC_ITEM_ID_KEY]: freshId('extra'), src: '', alt: '' }, { type: 'image', [COSMIC_ITEM_ID_KEY]: freshId('extra'), src: '', alt: '' }] };
    if (['group','grid','stack','card'].includes(safe)) return { ...base, children: [] };
    return { ...base, text: 'Add your content here.' };
};

const normalizeRow = (rawRow) => {
    const row = isObject(rawRow) ? clone(rawRow) : { type: 'row' };
    row.type = 'row';
    const rawChildren = Array.isArray(row.children) ? row.children : [];
    if (!rawChildren.length) {
        row.children = [createAiFlexColumn(100)];
        return row;
    }
    const width = Math.max(10, Math.round((100 / rawChildren.length) * 100) / 100);
    row.children = rawChildren.map((child) => {
        if (isObject(child) && child.type === 'column') return clone(child);
        const childWidth = Number(child?.style?.width);
        return makeColumn([clone(child)], Number.isFinite(childWidth) ? childWidth : width);
    });
    row.children = row.children.map((column) => ({ ...column, type: 'column', children: Array.isArray(column.children) ? column.children : [] }));
    return row;
};

/** Canonicalize NEW/opted-in AI Flex blocks without creating a second storage tree. */
export const canonicalizeAiFlexElements = (elements) => {
    const source = Array.isArray(elements) ? elements.filter(isObject) : [];
    if (!source.length) return [];
    const rows = [];
    let pending = [];
    const flush = () => {
        if (!pending.length) return;
        rows.push({ type: 'row', children: [makeColumn(pending, 100)] });
        pending = [];
    };
    source.forEach((node) => {
        if (node.type === 'row') {
            flush();
            rows.push(normalizeRow(node));
        } else if (node.type === 'column') {
            flush();
            rows.push({ type: 'row', children: [{ ...clone(node), type: 'column', children: Array.isArray(node.children) ? clone(node.children) : [] }] });
        } else {
            pending.push(clone(node));
        }
    });
    flush();

    const used = new Set();
    return rows.map((row) => {
        const [withId] = withStableIds([row], 'row', used);
        withId.children = (Array.isArray(withId.children) ? withId.children : []).map((column) => {
            let id = String(column?.[COSMIC_ITEM_ID_KEY] || '').trim();
            if (!validId(id) || used.has(id)) id = freshId('column');
            used.add(id);
            const next = { ...column, type: 'column', [COSMIC_ITEM_ID_KEY]: id };
            next.children = (Array.isArray(column.children) ? column.children : []).map((extra) => {
                let eid = String(extra?.[COSMIC_ITEM_ID_KEY] || '').trim();
                if (!validId(eid) || used.has(eid)) eid = freshId('extra');
                used.add(eid);
                return { ...extra, [COSMIC_ITEM_ID_KEY]: eid };
            });
            return next;
        });
        return withId;
    });
};

export const isCanonicalAiFlexElements = (elements) => Array.isArray(elements)
    && elements.length > 0
    && elements.every((row) => isObject(row) && row.type === 'row'
        && Array.isArray(row.children)
        && row.children.length > 0
        && row.children.every((column) => isObject(column) && column.type === 'column' && Array.isArray(column.children)));

export const normalizeAiFlexBlock = (block, { canonicalize = false } = {}) => {
    if (!isObject(block) || block.type !== 'luna_custom_section') return block;
    const elements = Array.isArray(block.elements) ? block.elements : [];
    if (!elements.length) return block;
    const shouldCanonicalize = canonicalize
        || block.ai_flex?.structure_contract === AI_FLEX_STRUCTURE_CONTRACT
        || isCanonicalAiFlexElements(elements);
    if (!shouldCanonicalize) return { ...block, elements: withStableIds(elements) };
    return {
        ...block,
        elements: canonicalizeAiFlexElements(elements),
        ai_flex: {
            ...(isObject(block.ai_flex) ? block.ai_flex : {}),
            structure_contract: AI_FLEX_STRUCTURE_CONTRACT,
            structure_version: AI_FLEX_STRUCTURE_VERSION,
            structure_storage: AI_FLEX_STORAGE_KEY,
        },
    };
};

const pathSegments = (value) => String(value || '').replace(/\[([0-9]+)\]/g, '.$1').replace(/^\.+|\.+$/g, '').split('.').filter(Boolean);

export const aiFlexLogicalPathToStoragePath = (logicalPath) => {
    const p = pathSegments(logicalPath);
    if (!p.length || p[0] !== AI_FLEX_ROW_COLLECTION) return null;
    const out = [AI_FLEX_STORAGE_KEY];
    if (p.length === 1) return out;
    out.push(p[1]);
    if (p.length === 2) return out;
    if (p[2] !== AI_FLEX_COLUMN_COLLECTION) return null;
    out.push('children');
    if (p.length === 3) return out;
    out.push(p[3]);
    if (p.length === 4) return out;
    if (p[4] !== AI_FLEX_EXTRA_COLLECTION) return null;
    out.push('children');
    if (p.length === 5) return out;
    out.push(...p.slice(5));
    return out;
};

export const aiFlexStoragePathToLogicalPath = (storagePath) => {
    const p = pathSegments(Array.isArray(storagePath) ? storagePath.join('.') : storagePath);
    if (!p.length || p[0] !== AI_FLEX_STORAGE_KEY) return null;
    const out = [AI_FLEX_ROW_COLLECTION];
    if (p.length === 1) return out;
    out.push(p[1]);
    if (p.length === 2) return out;
    if (p[2] !== 'children') return null;
    out.push(AI_FLEX_COLUMN_COLLECTION);
    if (p.length === 3) return out;
    out.push(p[3]);
    if (p.length === 4) return out;
    if (p[4] !== 'children') return null;
    out.push(AI_FLEX_EXTRA_COLLECTION, ...p.slice(5));
    return out;
};

export const getAiFlexCollection = (block, logicalCollectionPath) => {
    const storage = aiFlexLogicalPathToStoragePath(logicalCollectionPath);
    return storage ? getNestedValue(block, storage) : undefined;
};

/**
 * Mutation adapter used by the next Luna action batch. Handles empty repeaters
 * as well as existing arrays and returns both logical + physical paths.
 */
export const mutateAiFlexStructure = (block, {
    path,
    action,
    itemIndex = null,
    itemSelector = null,
    toIndex = null,
    extraType = 'text',
} = {}) => {
    const storagePath = aiFlexLogicalPathToStoragePath(path);
    if (!storagePath) return { block, changed: false, reason: 'invalid_ai_flex_path' };
    const current = getNestedValue(block, storagePath);

    if (action === 'add' && Array.isArray(current)) {
        const logical = pathSegments(path);
        const collection = logical.at(-1);
        const item = collection === AI_FLEX_ROW_COLLECTION
            ? createAiFlexRow()
            : collection === AI_FLEX_COLUMN_COLLECTION
                ? createAiFlexColumn(100)
                : createAiFlexExtra(extraType);
        const insertAt = current.length;
        const next = updateNestedValue(block, storagePath, (items) => [...(Array.isArray(items) ? items : []), item]);
        return {
            block: normalizeAiFlexBlock(next, { canonicalize: true }),
            changed: true,
            collectionKey: collection,
            logicalPath: logical.join('.'),
            path: storagePath,
            pathString: storagePath.join('.'),
            itemIndex: insertAt,
            stableId: stableRepeaterItemId(item),
            mutation: { action: 'add', index: insertAt },
        };
    }

    const result = mutateNestedRepeater(block, {
        path: storagePath,
        action,
        itemIndex,
        itemSelector,
        toIndex,
        minimum: pathSegments(path).at(-1) === AI_FLEX_EXTRA_COLLECTION ? 0 : 1,
    });
    return {
        ...result,
        block: result.changed ? normalizeAiFlexBlock(result.block, { canonicalize: true }) : result.block,
        collectionKey: pathSegments(path).at(-1) || result.collectionKey,
        logicalPath: pathSegments(path).join('.'),
        storagePath,
    };
};
