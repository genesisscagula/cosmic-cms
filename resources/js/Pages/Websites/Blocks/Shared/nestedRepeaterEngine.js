import {
    FIELD_EXTRAS_STORAGE_KEY,
    createSparkExtraId,
    normalizeSparkFieldExtrasState,
    normalizeSparkExtraTargetPath,
} from './sparkExtrasContract.js';

/**
 * Cosmic Nested Repeater Engine v1
 *
 * Generic immutable mutations for arrays of object rows at any block depth.
 * Paths may use numeric selectors or stable `@id` selectors. Stable selectors
 * resolve against _cosmic_id/id/_id/uuid/key so future Rows -> Columns -> Extras
 * structures do not depend on fragile array indexes.
 */

export const NESTED_REPEATER_ENGINE_VERSION = 1;
export const COSMIC_ITEM_ID_KEY = '_cosmic_id';
export const MAX_NESTED_REPEATER_DEPTH = 8;
export const MIN_REPEATER_ITEMS = 1;

const DANGEROUS = new Set(['__proto__', 'prototype', 'constructor']);
const SKIP_COLLECTION_KEYS = new Set([
    FIELD_EXTRAS_STORAGE_KEY,
    'luna_tailwind_schema',
    'tailwind_schema',
    'images',
    'gallery_images',
    'media',
]);

const isPlainObject = (value) => Boolean(value)
    && typeof value === 'object'
    && !Array.isArray(value)
    && (Object.getPrototypeOf(value) === Object.prototype || Object.getPrototypeOf(value) === null);

const deepClone = (value) => {
    if (typeof structuredClone === 'function') {
        try { return structuredClone(value); } catch (_) { /* JSON fallback */ }
    }
    return JSON.parse(JSON.stringify(value));
};

export const createCosmicRepeaterItemId = () => {
    if (typeof globalThis.crypto?.randomUUID === 'function') return `item_${globalThis.crypto.randomUUID()}`;
    return `item_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 12)}`;
};

export const stableRepeaterItemId = (item) => {
    if (!isPlainObject(item)) return '';
    return String(item[COSMIC_ITEM_ID_KEY] ?? item.id ?? item._id ?? item.uuid ?? item.key ?? '').trim();
};

export const normalizeNestedPath = (value) => {
    const normalized = normalizeSparkExtraTargetPath(Array.isArray(value) ? value.join('.') : value);
    return normalized ? normalized.split('.') : [];
};

const resolveArraySelector = (items, selector) => {
    if (!Array.isArray(items)) return -1;
    const raw = String(selector ?? '');
    if (/^\d+$/.test(raw)) {
        const index = Number(raw);
        return index >= 0 && index < items.length ? index : -1;
    }
    if (raw.startsWith('@')) {
        const id = raw.slice(1);
        return items.findIndex((item) => stableRepeaterItemId(item) === id);
    }
    return -1;
};

export const getNestedValue = (source, path) => {
    const segments = normalizeNestedPath(path);
    if (!segments.length) return source;
    let cursor = source;
    for (const segment of segments) {
        if (cursor == null) return undefined;
        if (Array.isArray(cursor)) {
            const index = resolveArraySelector(cursor, segment);
            if (index < 0) return undefined;
            cursor = cursor[index];
            continue;
        }
        if (!isPlainObject(cursor) || DANGEROUS.has(segment)) return undefined;
        cursor = cursor[segment];
    }
    return cursor;
};

export const updateNestedValue = (source, path, updater) => {
    const segments = normalizeNestedPath(path);
    if (!segments.length) return updater(source);

    const walk = (cursor, offset) => {
        const segment = segments[offset];
        const last = offset === segments.length - 1;

        if (Array.isArray(cursor)) {
            const index = resolveArraySelector(cursor, segment);
            if (index < 0) return cursor;
            const next = [...cursor];
            next[index] = last ? updater(cursor[index]) : walk(cursor[index], offset + 1);
            return next;
        }

        if (!isPlainObject(cursor) || DANGEROUS.has(segment)) return cursor;
        const child = cursor[segment];
        return {
            ...cursor,
            [segment]: last ? updater(child) : walk(child, offset + 1),
        };
    };

    return walk(source, 0);
};

export const isObjectRepeater = (value) => Array.isArray(value)
    && value.length > 0
    && value.every((item) => isPlainObject(item));

export const listNestedRepeaters = (source, {
    maxDepth = MAX_NESTED_REPEATER_DEPTH,
    includeSkipped = false,
} = {}) => {
    const repeaters = [];
    const seen = new WeakSet();

    const walk = (value, path = [], depth = 0) => {
        if (depth > maxDepth || value == null || typeof value !== 'object') return;
        if (typeof value === 'object') {
            if (seen.has(value)) return;
            seen.add(value);
        }

        if (isObjectRepeater(value)) {
            repeaters.push({
                path,
                pathString: path.join('.'),
                collectionKey: String(path.at(-1) || ''),
                items: value,
                depth: Math.floor(path.length / 2),
            });
            value.forEach((item, index) => walk(item, [...path, index], depth + 1));
            return;
        }

        if (!isPlainObject(value)) return;
        Object.entries(value).forEach(([key, child]) => {
            if (!includeSkipped && SKIP_COLLECTION_KEYS.has(String(key).toLowerCase())) return;
            walk(child, [...path, key], depth + 1);
        });
    };

    walk(source);
    return repeaters;
};

export const findPrimaryNestedRepeater = (source) => listNestedRepeaters(source)[0] || null;

/**
 * Finds the deepest repeater that contains a field path. Example:
 * rows.0.columns.1.heading -> rows.0.columns.
 */
export const findRepeaterForFieldPath = (source, fieldPath, collectionHint = '') => {
    const target = normalizeNestedPath(fieldPath);
    const repeaters = listNestedRepeaters(source);
    if (!repeaters.length) return null;

    const hint = String(collectionHint || '').trim();
    const candidates = repeaters.filter((entry) => {
        if (hint && entry.collectionKey !== hint) return false;
        if (!target.length) return true;
        if (entry.path.length >= target.length) return false;
        return entry.path.every((segment, index) => String(segment) === String(target[index]));
    });

    return candidates.sort((a, b) => b.path.length - a.path.length)[0]
        || (hint ? repeaters.find((entry) => entry.collectionKey === hint) : null)
        || repeaters[0];
};

const freshClone = (source, { duplicate = false } = {}) => {
    const clone = isPlainObject(source) ? deepClone(source) : {};
    // These keys are usually identity rather than content. A duplicate/new item
    // must never inherit an identity used for stable routing.
    ['id', '_id', 'uuid', 'key', '_key', 'slug', COSMIC_ITEM_ID_KEY].forEach((key) => delete clone[key]);
    clone[COSMIC_ITEM_ID_KEY] = createCosmicRepeaterItemId();

    Object.keys(clone).forEach((key) => {
        if (!Array.isArray(clone[key])) return;
        clone[key] = clone[key].map((child) => isPlainObject(child)
            ? freshClone(child, { duplicate })
            : child);
    });

    if (!duplicate) {
        Object.keys(clone).forEach((key) => {
            if (typeof clone[key] === 'string' && /(title|heading|name|label)$/i.test(key)) clone[key] = 'New item';
            if (typeof clone[key] === 'string' && /(description|body|content|text)$/i.test(key)) clone[key] = 'Add your content here.';
        });
    }
    return clone;
};

const resolveStablePathToNumeric = (source, path) => {
    const segments = normalizeNestedPath(path);
    const resolved = [];
    let cursor = source;
    for (const segment of segments) {
        if (Array.isArray(cursor)) {
            const index = resolveArraySelector(cursor, segment);
            if (index < 0) return segments;
            resolved.push(String(index));
            cursor = cursor[index];
            continue;
        }
        if (!isPlainObject(cursor) || DANGEROUS.has(String(segment)) || !(segment in cursor)) return segments;
        resolved.push(String(segment));
        cursor = cursor[segment];
    }
    return resolved;
};

const remapIndex = (index, mutation) => {
    const { action, index: targetIndex, fromIndex, toIndex } = mutation;
    if (action === 'add' || action === 'duplicate') return index >= targetIndex ? index + 1 : index;
    if (action === 'remove') {
        if (index === targetIndex) return null;
        return index > targetIndex ? index - 1 : index;
    }
    if (action === 'move') {
        if (index === fromIndex) return toIndex;
        if (fromIndex < toIndex && index > fromIndex && index <= toIndex) return index - 1;
        if (fromIndex > toIndex && index >= toIndex && index < fromIndex) return index + 1;
    }
    return index;
};

const cloneExtraSlotsWithFreshIds = (slots) => {
    const clonePlacement = (items) => (Array.isArray(items) ? items : []).map((item) => ({
        ...deepClone(item),
        id: createSparkExtraId(),
    }));
    return {
        before: clonePlacement(slots?.before),
        after: clonePlacement(slots?.after),
    };
};

export const remapFieldExtrasForRepeaterMutation = (fieldExtras, repeaterPath, mutation) => {
    const state = normalizeSparkFieldExtrasState(fieldExtras);
    const prefix = normalizeNestedPath(repeaterPath);
    if (!prefix.length) return state;

    const next = {};
    const duplicateCopies = [];
    Object.entries(state).forEach(([target, slots]) => {
        const segments = normalizeNestedPath(target);
        const matchesPrefix = prefix.every((segment, offset) => String(segment) === String(segments[offset]));
        if (!matchesPrefix || segments.length <= prefix.length) {
            next[target] = slots;
            return;
        }

        const selector = segments[prefix.length];
        if (!/^\d+$/.test(String(selector))) {
            // Stable @id targets survive index shifts unchanged.
            next[target] = slots;
            return;
        }

        const oldIndex = Number(selector);
        const mapped = remapIndex(oldIndex, mutation);
        if (mapped === null) return;

        const mappedSegments = [...segments];
        mappedSegments[prefix.length] = String(mapped);
        next[mappedSegments.join('.')] = slots;

        if (mutation.action === 'duplicate' && oldIndex === mutation.sourceIndex) {
            const copySegments = [...segments];
            copySegments[prefix.length] = String(mutation.index);
            duplicateCopies.push([copySegments.join('.'), cloneExtraSlotsWithFreshIds(slots)]);
        }
    });
    duplicateCopies.forEach(([path, slots]) => { next[path] = slots; });
    return normalizeSparkFieldExtrasState(next);
};

const scopeAtParentPath = (schema, parentPath) => {
    const segments = normalizeNestedPath(parentPath);
    if (!segments.length) return schema;
    let scope = schema;
    for (let i = 0; i < segments.length; i += 2) {
        const collection = String(segments[i] || '');
        const selector = segments[i + 1];
        const items = scope?.collections?.[collection];
        if (!Array.isArray(items)) return null;
        const index = resolveArraySelector(items, selector);
        if (index < 0) return null;
        scope = items[index];
    }
    return scope;
};

export const remapTailwindForRepeaterMutation = (block, repeaterPath, mutation) => {
    const key = 'luna_tailwind_schema';
    if (!isPlainObject(block?.[key])) return block;
    const path = normalizeNestedPath(repeaterPath);
    if (!path.length) return block;
    const collection = String(path.at(-1));
    const parentPath = path.slice(0, -1);
    const schema = deepClone(block[key]);
    const parentScope = scopeAtParentPath(schema, parentPath);
    const scopes = parentScope?.collections?.[collection];
    if (!Array.isArray(scopes)) return block;

    if (mutation.action === 'add') {
        scopes.splice(mutation.index, 0, { styles: {}, collections: {} });
    } else if (mutation.action === 'duplicate') {
        const source = scopes[mutation.sourceIndex];
        scopes.splice(mutation.index, 0, isPlainObject(source) ? deepClone(source) : { styles: {}, collections: {} });
    } else if (mutation.action === 'remove') {
        if (mutation.index < scopes.length) scopes.splice(mutation.index, 1);
    } else if (mutation.action === 'move') {
        if (mutation.fromIndex < scopes.length && mutation.toIndex < scopes.length) {
            const [scope] = scopes.splice(mutation.fromIndex, 1);
            scopes.splice(mutation.toIndex, 0, scope);
        }
    }

    return { ...block, [key]: schema };
};

/**
 * Generic immutable array mutation at any repeater path.
 * Returns `{ block, changed, ...metadata }` and preserves field-extras/Tailwind
 * addresses when numeric indexes shift.
 */
export const mutateNestedRepeater = (block, {
    path,
    action,
    itemIndex = null,
    itemSelector = null,
    toIndex = null,
    minimum = MIN_REPEATER_ITEMS,
} = {}) => {
    const repeaterPath = resolveStablePathToNumeric(block, path);
    const items = getNestedValue(block, repeaterPath);
    if (!isObjectRepeater(items)) return { block, changed: false, reason: 'invalid_repeater', path: repeaterPath };

    const explicitSelector = itemSelector ?? itemIndex;
    const selectedIndex = explicitSelector === null || explicitSelector === undefined
        ? null
        : resolveArraySelector(items, explicitSelector);
    let nextItems = [...items];
    let mutation = null;
    let createdItem = null;

    if (action === 'add') {
        const sourceIndex = selectedIndex >= 0 ? selectedIndex : nextItems.length - 1;
        createdItem = freshClone(nextItems[sourceIndex], { duplicate: false });
        const insertAt = nextItems.length;
        nextItems.splice(insertAt, 0, createdItem);
        mutation = { action: 'add', index: insertAt, sourceIndex };
    } else if (action === 'duplicate') {
        if (selectedIndex === null || selectedIndex < 0) return { block, changed: false, reason: 'item_unresolved', path: repeaterPath };
        createdItem = freshClone(nextItems[selectedIndex], { duplicate: true });
        const insertAt = selectedIndex + 1;
        nextItems.splice(insertAt, 0, createdItem);
        mutation = { action: 'duplicate', index: insertAt, sourceIndex: selectedIndex };
    } else if (action === 'remove') {
        if (nextItems.length <= minimum) return { block, changed: false, reason: 'minimum_items', path: repeaterPath };
        if (selectedIndex === null || selectedIndex < 0) return { block, changed: false, reason: 'item_unresolved', path: repeaterPath };
        nextItems.splice(selectedIndex, 1);
        mutation = { action: 'remove', index: selectedIndex };
    } else if (action === 'move') {
        if (selectedIndex === null || selectedIndex < 0) return { block, changed: false, reason: 'item_unresolved', path: repeaterPath };
        const destination = Math.max(0, Math.min(nextItems.length - 1, Number(toIndex)));
        if (!Number.isInteger(destination) || destination === selectedIndex) return { block, changed: false, reason: 'no_change', path: repeaterPath };
        const [moving] = nextItems.splice(selectedIndex, 1);
        nextItems.splice(destination, 0, moving);
        mutation = { action: 'move', fromIndex: selectedIndex, toIndex: destination };
    } else {
        return { block, changed: false, reason: 'unsupported_action', path: repeaterPath };
    }

    let nextBlock = updateNestedValue(block, repeaterPath, () => nextItems);
    nextBlock = {
        ...nextBlock,
        [FIELD_EXTRAS_STORAGE_KEY]: remapFieldExtrasForRepeaterMutation(
            nextBlock?.[FIELD_EXTRAS_STORAGE_KEY],
            repeaterPath,
            mutation,
        ),
    };
    nextBlock = remapTailwindForRepeaterMutation(nextBlock, repeaterPath, mutation);

    return {
        block: nextBlock,
        changed: true,
        path: repeaterPath,
        pathString: repeaterPath.join('.'),
        collectionKey: String(repeaterPath.at(-1) || ''),
        itemIndex: mutation.index ?? mutation.toIndex ?? selectedIndex,
        sourceIndex: mutation.sourceIndex ?? selectedIndex,
        stableId: stableRepeaterItemId(createdItem),
        mutation,
    };
};
