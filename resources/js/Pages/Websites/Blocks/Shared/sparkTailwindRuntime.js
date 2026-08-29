const STORAGE_KEY = 'luna_tailwind_schema';
const SCHEMA_VERSION = 2;

const normalizeSlot = (slot) => String(slot || '')
    .trim()
    .toLowerCase()
    .replace(/[\s-]+/g, '_');

const normalizeClasses = (classes) => {
    if (Array.isArray(classes)) {
        return classes.filter((value) => typeof value === 'string' && value.trim() !== '').join(' ').trim();
    }
    return typeof classes === 'string' ? classes.trim() : '';
};

// Tailwind arbitrary values can legally contain spaces inside [] blocks, so a
// plain split(/\s+/) is not safe enough for persisted schema patches.
const tokenizeClasses = (classes) => {
    const input = normalizeClasses(classes);
    if (!input) return [];

    const tokens = [];
    let buffer = '';
    let depth = 0;
    let escaped = false;

    for (const char of input) {
        if (escaped) {
            buffer += char;
            escaped = false;
            continue;
        }
        if (char === '\\') {
            buffer += char;
            escaped = true;
            continue;
        }
        if (char === '[') depth += 1;
        if (char === ']' && depth > 0) depth -= 1;

        if (/\s/.test(char) && depth === 0) {
            if (buffer) tokens.push(buffer);
            buffer = '';
            continue;
        }
        buffer += char;
    }
    if (buffer) tokens.push(buffer);

    return [...new Set(tokens.map((token) => token.trim()).filter(Boolean))];
};

const normalizeScopePath = (path) => {
    const raw = Array.isArray(path)
        ? [...path]
        : (typeof path === 'string' ? path.replace(/^\.+|\.+$/g, '').split('.').filter(Boolean) : []);
    if (!raw.length || raw.length % 2 !== 0) return [];

    const normalized = [];
    for (let index = 0; index < raw.length; index += 1) {
        const part = raw[index];
        if (index % 2 === 0) {
            const collection = normalizeSlot(part);
            if (!collection) return [];
            normalized.push(collection);
            continue;
        }

        if (Number.isInteger(part) || (/^\d+$/.test(String(part)))) {
            normalized.push(Number(part));
            continue;
        }

        const selector = String(part ?? '').trim();
        if (!selector) return [];
        normalized.push(selector);
    }

    return normalized;
};

const findCollectionItem = (items, selector) => {
    if (!Array.isArray(items)) return null;
    if (Number.isInteger(selector) && items[selector] && typeof items[selector] === 'object') {
        return items[selector];
    }

    const needle = String(selector ?? '').trim();
    if (!needle) return null;
    return items.find((item) => item && typeof item === 'object' && String(item.key ?? '') === needle) || null;
};

const scopeAtPath = (schema, path) => {
    const normalized = normalizeScopePath(path);
    if (!normalized.length) return null;

    let collections = schema?.collections && typeof schema.collections === 'object' && !Array.isArray(schema.collections)
        ? schema.collections
        : {};
    let scope = null;

    for (let index = 0; index < normalized.length; index += 2) {
        const collection = normalized[index];
        const selector = normalized[index + 1];
        scope = findCollectionItem(collections?.[collection], selector);
        if (!scope) return null;
        collections = scope?.collections && typeof scope.collections === 'object' && !Array.isArray(scope.collections)
            ? scope.collections
            : {};
    }

    return scope;
};

const resolveDefinition = (definition, fallback = '') => {
    const fallbackTokens = tokenizeClasses(fallback);
    if (definition == null) return fallbackTokens.join(' ');

    if (typeof definition === 'string' || Array.isArray(definition)) {
        return tokenizeClasses(definition).join(' ');
    }
    if (typeof definition !== 'object') return '';

    const base = tokenizeClasses(definition.base);
    const protectedTokens = tokenizeClasses(definition.protected);
    const invariants = [...new Set([...base, ...protectedTokens])];
    const isPatch = !Object.prototype.hasOwnProperty.call(definition, 'classes')
        && (Object.prototype.hasOwnProperty.call(definition, 'add') || Object.prototype.hasOwnProperty.call(definition, 'remove'));

    let tokens;
    if (isPatch) {
        const remove = new Set(tokenizeClasses(definition.remove));
        tokens = fallbackTokens.filter((token) => !remove.has(token));
        tokenizeClasses(definition.add).forEach((token) => {
            if (!tokens.includes(token)) tokens.push(token);
        });
    } else {
        tokens = tokenizeClasses(definition.classes);
    }

    // `base` is a renderer invariant bucket. Even if an editable patch removes
    // the same token, runtime restores it before returning the class contract.
    [...invariants].reverse().forEach((token) => {
        tokens = tokens.filter((existing) => existing !== token);
        tokens.unshift(token);
    });

    return tokens.join(' ');
};

export function getSparkTailwindSchema(block = {}) {
    const raw = block?.[STORAGE_KEY];
    if (!raw || typeof raw !== 'object' || Array.isArray(raw)) {
        return {
            version: SCHEMA_VERSION,
            spark_type: String(block?.type || ''),
            styles: {},
            collections: {},
            slots: {},
        };
    }

    return {
        ...raw,
        version: Number(raw.version || SCHEMA_VERSION),
        spark_type: String(raw.spark_type || block?.type || ''),
        styles: raw.styles && typeof raw.styles === 'object' && !Array.isArray(raw.styles) ? raw.styles : {},
        collections: raw.collections && typeof raw.collections === 'object' && !Array.isArray(raw.collections) ? raw.collections : {},
        slots: raw.slots && typeof raw.slots === 'object' && !Array.isArray(raw.slots) ? raw.slots : {},
    };
}

export function hasSparkTailwindSchema(block = {}) {
    const schema = getSparkTailwindSchema(block);
    return Object.keys(schema.styles || {}).length > 0
        || Object.keys(schema.collections || {}).length > 0
        || Object.keys(schema.slots || {}).length > 0;
}

export function resolveSparkTailwindSlot(block, slot, fallback = '') {
    const schema = getSparkTailwindSchema(block);
    const key = normalizeSlot(slot);

    // V2 semantic shared style aliases take precedence. Legacy slots remain a
    // compatibility bridge for all existing migrated Sparks.
    if (Object.prototype.hasOwnProperty.call(schema.styles || {}, key)) {
        return resolveDefinition(schema.styles[key], fallback);
    }
    if (Object.prototype.hasOwnProperty.call(schema.slots || {}, key)) {
        return resolveDefinition(schema.slots[key], fallback);
    }

    return tokenizeClasses(fallback).join(' ');
}

export function resolveSparkTailwindPath(block, path, slot, fallback = '') {
    const shared = resolveSparkTailwindSlot(block, slot, fallback);
    const schema = getSparkTailwindSchema(block);
    const scope = scopeAtPath(schema, path);
    const key = normalizeSlot(slot);

    if (!scope?.styles || typeof scope.styles !== 'object' || Array.isArray(scope.styles)
        || !Object.prototype.hasOwnProperty.call(scope.styles, key)) {
        return shared;
    }

    return resolveDefinition(scope.styles[key], shared);
}

const scopeMarker = (path, slot) => {
    const parts = [...normalizeScopePath(path), normalizeSlot(slot)];
    const value = parts.map((part) => String(part).replace(/[^A-Za-z0-9_-]+/g, '_') || 'x').join('__').toLowerCase();
    return `cosmic-tw-path--${value}`;
};

const ownershipMarkers = (resolved) => {
    const tokens = tokenizeClasses(resolved);
    const ownsSectionY = tokens.some((token) => /(?:^|:)(?:p|py|pt|pb)-/.test(token));
    const ownsSectionX = tokens.some((token) => /(?:^|:)(?:p|px|pl|pr)-/.test(token));
    return [
        ownsSectionY ? 'cosmic-tw-own-section-y' : '',
        ownsSectionX ? 'cosmic-tw-own-section-x' : '',
    ].filter(Boolean);
};

export function createSparkTailwindRuntime(block = {}) {
    const schema = getSparkTailwindSchema(block);
    const styles = schema.styles || {};
    const slots = schema.slots || {};

    return Object.freeze({
        version: schema.version,
        sparkType: schema.spark_type || String(block?.type || ''),
        migrationState: hasSparkTailwindSchema(block) ? 'schema_backed' : 'legacy_fallback',
        has(slot) {
            const key = normalizeSlot(slot);
            return Object.prototype.hasOwnProperty.call(styles, key)
                || Object.prototype.hasOwnProperty.call(slots, key);
        },
        hasScoped(path, slot) {
            const scope = scopeAtPath(schema, path);
            return Boolean(scope?.styles && Object.prototype.hasOwnProperty.call(scope.styles, normalizeSlot(slot)));
        },
        get(slot, fallback = '') {
            return resolveSparkTailwindSlot(block, slot, fallback);
        },
        getScoped(path, slot, fallback = '') {
            return resolveSparkTailwindPath(block, path, slot, fallback);
        },
        definition(slot) {
            const key = normalizeSlot(slot);
            return styles[key] || slots[key] || null;
        },
        scope(path) {
            return scopeAtPath(schema, path);
        },
        schema,
    });
}

export { STORAGE_KEY as SPARK_TAILWIND_STORAGE_KEY, SCHEMA_VERSION as SPARK_TAILWIND_SCHEMA_VERSION };

export function sparkTw(block, slot, fallback = '') {
    const resolved = resolveSparkTailwindSlot(block, slot, fallback);
    const marker = `cosmic-tw-slot--${normalizeSlot(slot)}`;
    return [resolved, marker, ...ownershipMarkers(resolved)].filter(Boolean).join(' ').trim();
}

/**
 * V2 scoped resolver. Future Spark migration batches can address repeaters as:
 *   sparkTwPath(block, ['items', index], 'card', '...')
 *   sparkTwPath(block, ['items', index, 'features', featureIndex], 'label', '...')
 *
 * It emits both the legacy semantic slot marker and an exact scope marker so
 * the Builder/Luna inventory can distinguish Card 1 from Card 2 in Batch 2.
 */
export function sparkTwPath(block, path, slot, fallback = '') {
    const resolved = resolveSparkTailwindPath(block, path, slot, fallback);
    const slotMarker = `cosmic-tw-slot--${normalizeSlot(slot)}`;
    return [resolved, slotMarker, scopeMarker(path, slot), ...ownershipMarkers(resolved)].filter(Boolean).join(' ').trim();
}

export function sparkTwItem(block, collection, indexOrKey, slot, fallback = '') {
    return sparkTwPath(block, [collection, indexOrKey], slot, fallback);
}

export { normalizeScopePath as normalizeSparkTailwindScopePath };
