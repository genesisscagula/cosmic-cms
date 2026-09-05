/**
 * Cosmic Spark Extras Contract v1
 *
 * Batch 1 foundation for AI Flex structural additions.
 *
 * Registered Spark schemas expose `extras.before[]` / `extras.after[]` on every
 * field, while saved block values live under the top-level `field_extras` map.
 * Missing state is treated as an empty map so legacy blocks remain valid.
 */

export const SPARK_EXTRAS_CONTRACT_VERSION = 1;
export const FIELD_EXTRAS_STORAGE_KEY = 'field_extras';

export const SPARK_EXTRA_PLACEMENTS = Object.freeze(['before', 'after']);
export const SPARK_EXTRA_TYPES = Object.freeze([
    'text',
    'heading',
    'image',
    'button',
    'icon',
    'badge',
    'video',
    'divider',
    'spacer',
    'list',
    'quote',
    'stat',
]);

const EXTRA_TYPE_SET = new Set(SPARK_EXTRA_TYPES);
const DANGEROUS_PATH_SEGMENTS = new Set(['__proto__', 'prototype', 'constructor']);
const MAX_TEXT_LENGTH = 5000;
const MAX_URL_LENGTH = 2048;
const MAX_TARGET_PATH_LENGTH = 320;
const MAX_EXTRA_ITEMS_PER_PLACEMENT = 40;

const isPlainObject = (value) => Boolean(value)
    && typeof value === 'object'
    && !Array.isArray(value)
    && (Object.getPrototypeOf(value) === Object.prototype || Object.getPrototypeOf(value) === null);

const cleanString = (value, maxLength = MAX_TEXT_LENGTH) => String(value ?? '')
    .replace(/\u0000/g, '')
    .trim()
    .slice(0, maxLength);

const cleanBoolean = (value, fallback = false) => {
    if (typeof value === 'boolean') return value;
    if (value === 1 || value === '1' || value === 'true') return true;
    if (value === 0 || value === '0' || value === 'false') return false;
    return fallback;
};

const cleanEnum = (value, allowed, fallback = '') => {
    const normalized = cleanString(value, 80).toLowerCase();
    return allowed.includes(normalized) ? normalized : fallback;
};

export const sanitizeSparkExtraUrl = (value, { media = false } = {}) => {
    const url = cleanString(value, MAX_URL_LENGTH);
    if (!url) return '';

    // Relative/site-local links and anchors are first-class in exported sites.
    if (/^(?:\/|\.\/|\.\.\/|#)/.test(url)) return url;

    // Explicitly reject executable/browser-dangerous protocols.
    if (/^(?:javascript|vbscript|data):/i.test(url)) return '';

    try {
        const parsed = new URL(url);
        const protocol = parsed.protocol.toLowerCase();
        const allowed = media
            ? ['http:', 'https:']
            : ['http:', 'https:', 'mailto:', 'tel:'];
        return allowed.includes(protocol) ? url : '';
    } catch (_) {
        return '';
    }
};

export const createSparkExtraId = () => {
    if (typeof globalThis.crypto?.randomUUID === 'function') {
        return `extra_${globalThis.crypto.randomUUID()}`;
    }

    return `extra_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 12)}`;
};

const normalizeExtraId = (value) => {
    const candidate = cleanString(value, 128);
    if (/^[A-Za-z0-9][A-Za-z0-9_-]{0,127}$/.test(candidate)) return candidate;
    return createSparkExtraId();
};

const normalizeExtraData = (type, rawData) => {
    const data = isPlainObject(rawData) ? rawData : {};

    switch (type) {
        case 'text':
            return { text: cleanString(data.text) };

        case 'heading': {
            const level = Math.max(1, Math.min(6, Number.parseInt(data.level, 10) || 2));
            return { text: cleanString(data.text), level };
        }

        case 'image':
            return {
                src: sanitizeSparkExtraUrl(data.src, { media: true }),
                alt: cleanString(data.alt, 500),
                title: cleanString(data.title, 500),
                loading: cleanEnum(data.loading, ['lazy', 'eager'], 'lazy'),
                object_fit: cleanEnum(data.object_fit, ['cover', 'contain', 'fill', 'none', 'scale-down'], 'cover'),
            };

        case 'button':
            return {
                label: cleanString(data.label, 500),
                url: sanitizeSparkExtraUrl(data.url),
                target: cleanEnum(data.target, ['_self', '_blank'], '_self'),
                rel: cleanString(data.rel, 250),
            };

        case 'icon':
            return {
                name: cleanString(data.name, 160),
                label: cleanString(data.label, 500),
            };

        case 'badge':
            return { text: cleanString(data.text, 500) };

        case 'video':
            return {
                src: sanitizeSparkExtraUrl(data.src, { media: true }),
                poster: sanitizeSparkExtraUrl(data.poster, { media: true }),
                autoplay: cleanBoolean(data.autoplay, false),
                muted: cleanBoolean(data.muted, true),
                loop: cleanBoolean(data.loop, false),
                controls: cleanBoolean(data.controls, true),
                plays_inline: cleanBoolean(data.plays_inline, true),
            };

        case 'divider':
            return {
                orientation: cleanEnum(data.orientation, ['horizontal', 'vertical'], 'horizontal'),
            };

        case 'spacer':
            return {
                size: cleanEnum(data.size, ['xs', 'sm', 'md', 'lg', 'xl', '2xl'], 'md'),
            };

        case 'list':
            return { items: (Array.isArray(data.items) ? data.items : []).slice(0, 40).map((item) => cleanString(typeof item === 'object' ? (item.text ?? item.label ?? item.value ?? '') : item, 1000)).filter(Boolean) };

        case 'quote':
            return { text: cleanString(data.text), cite: cleanString(data.cite, 500) };

        case 'stat':
            return { value: cleanString(data.value, 500), label: cleanString(data.label, 500) };

        default:
            return {};
    }
};

export const normalizeSparkExtraItem = (item) => {
    if (!isPlainObject(item)) return null;

    const type = cleanString(item.type, 80).toLowerCase();
    if (!EXTRA_TYPE_SET.has(type)) return null;

    const style = isPlainObject(item.style) ? Object.fromEntries(Object.entries(item.style).slice(0, 64).filter(([key, value]) => /^[A-Za-z_][A-Za-z0-9_-]{0,63}$/.test(key) && ['string','number','boolean'].includes(typeof value))) : {};
    const meta = isPlainObject(item.meta) ? { style_mode: cleanEnum(item.meta.style_mode, ['global','custom'], 'global'), responsive_mode: cleanEnum(item.meta.responsive_mode, ['auto','custom'], 'auto'), auto_align: cleanBoolean(item.meta.auto_align, true) } : {};
    return {
        id: normalizeExtraId(item.id),
        type,
        data: normalizeExtraData(type, item.data),
        ...(Object.keys(style).length ? { style } : {}),
        ...(Object.keys(meta).length ? { meta } : {}),
    };
};

const normalizeExtraList = (items, usedIds = new Set()) => {
    if (!Array.isArray(items)) return [];

    return items.slice(0, MAX_EXTRA_ITEMS_PER_PLACEMENT).reduce((normalized, item) => {
        const extra = normalizeSparkExtraItem(item);
        if (!extra) return normalized;

        while (usedIds.has(extra.id)) extra.id = createSparkExtraId();
        usedIds.add(extra.id);
        normalized.push(extra);
        return normalized;
    }, []);
};

export const emptySparkFieldExtras = () => ({ before: [], after: [] });

export const normalizeSparkFieldExtraSlots = (slots, usedIds = new Set()) => {
    const raw = isPlainObject(slots) ? slots : {};
    return {
        before: normalizeExtraList(raw.before, usedIds),
        after: normalizeExtraList(raw.after, usedIds),
    };
};

/**
 * Canonical field target path.
 *
 * Both `plans[0].title` and `plans.0.title` normalize to `plans.0.title`.
 * `@stable-id` path segments are reserved for the nested repeater batches.
 */
export const normalizeSparkExtraTargetPath = (value) => {
    const raw = cleanString(value, MAX_TARGET_PATH_LENGTH)
        .replace(/\[([0-9]+)\]/g, '.$1')
        .replace(/^\.+|\.+$/g, '')
        .replace(/\.{2,}/g, '.');

    if (!raw) return null;

    const segments = raw.split('.');
    const valid = segments.every((segment) => {
        if (!segment || DANGEROUS_PATH_SEGMENTS.has(segment)) return false;
        if (/^[0-9]+$/.test(segment)) return true;
        if (/^@[A-Za-z0-9_-]{1,128}$/.test(segment)) return true;
        return /^[A-Za-z_][A-Za-z0-9_-]{0,127}$/.test(segment);
    });

    return valid ? segments.join('.') : null;
};

export const normalizeSparkFieldExtrasState = (fieldExtras) => {
    if (!isPlainObject(fieldExtras)) return {};

    const usedIds = new Set();
    const normalized = {};

    Object.entries(fieldExtras).forEach(([rawTarget, rawSlots]) => {
        const target = normalizeSparkExtraTargetPath(rawTarget);
        if (!target) return;

        const slots = normalizeSparkFieldExtraSlots(rawSlots, usedIds);
        if (slots.before.length || slots.after.length) normalized[target] = slots;
    });

    return normalized;
};

/**
 * Legacy-safe block migration. Old blocks simply gain an empty `field_extras`
 * map; existing valid extras are normalized without touching unrelated fields.
 */
export const normalizeBlockFieldExtras = (block) => {
    if (!isPlainObject(block)) return block;

    return {
        ...block,
        [FIELD_EXTRAS_STORAGE_KEY]: normalizeSparkFieldExtrasState(block[FIELD_EXTRAS_STORAGE_KEY]),
    };
};

/** Clone extras for a duplicated Spark without reusing routing identities. */
export const cloneSparkFieldExtrasWithFreshIds = (fieldExtras) => {
    const normalized = normalizeSparkFieldExtrasState(fieldExtras);

    return Object.fromEntries(Object.entries(normalized).map(([target, slots]) => [
        target,
        {
            before: slots.before.map((item) => ({ ...item, data: { ...item.data }, id: createSparkExtraId() })),
            after: slots.after.map((item) => ({ ...item, data: { ...item.data }, id: createSparkExtraId() })),
        },
    ]));
};

/**
 * Adds before/after capability metadata recursively to schema fields, including
 * child fields of repeaters/groups. No individual Spark source file needs to be
 * rewritten for the capability to exist.
 */
export const normalizeSparkSchemaField = (field) => {
    if (!isPlainObject(field)) return field;

    const normalized = {
        ...field,
        extras: emptySparkFieldExtras(),
    };

    if (Array.isArray(field.fields)) {
        normalized.fields = field.fields.map(normalizeSparkSchemaField);
    }

    return normalized;
};

export const normalizeSparkSchema = (schema) => {
    if (!isPlainObject(schema)) return schema;

    const defaults = isPlainObject(schema.defaults) ? schema.defaults : {};

    return {
        ...schema,
        extras_contract_version: SPARK_EXTRAS_CONTRACT_VERSION,
        defaults: {
            ...defaults,
            [FIELD_EXTRAS_STORAGE_KEY]: normalizeSparkFieldExtrasState(defaults[FIELD_EXTRAS_STORAGE_KEY]),
        },
        fields: Array.isArray(schema.fields)
            ? schema.fields.map(normalizeSparkSchemaField)
            : [],
    };
};

export const normalizeBlockRegistry = (registry) => {
    if (!isPlainObject(registry)) return registry;

    return Object.fromEntries(Object.entries(registry).map(([type, entry]) => [
        type,
        isPlainObject(entry)
            ? { ...entry, schema: normalizeSparkSchema(entry.schema) }
            : entry,
    ]));
};

export const getSparkFieldExtras = (block, targetPath) => {
    const target = normalizeSparkExtraTargetPath(targetPath);
    if (!target || !isPlainObject(block)) return emptySparkFieldExtras();

    const state = normalizeSparkFieldExtrasState(block[FIELD_EXTRAS_STORAGE_KEY]);
    return state[target] || emptySparkFieldExtras();
};

const stableSparkExtraItemId = (item) => {
    if (!isPlainObject(item)) return '';
    return cleanString(item._cosmic_id ?? item.id ?? item._id ?? item.uuid ?? item.key, 128);
};

/**
 * Resolve a canonical extras target path against the current block value.
 * Numeric path segments address repeater indexes. `@stable-id` segments are
 * future-safe aliases that resolve against id/_id/uuid/key on repeater items.
 */
export const getSparkExtraTargetValue = (block, targetPath) => {
    const target = normalizeSparkExtraTargetPath(targetPath);
    if (!target || !isPlainObject(block)) return undefined;

    let cursor = block;
    for (const segment of target.split('.')) {
        if (cursor === null || cursor === undefined) return undefined;

        if (Array.isArray(cursor)) {
            if (/^[0-9]+$/.test(segment)) {
                cursor = cursor[Number(segment)];
                continue;
            }

            if (segment.startsWith('@')) {
                const stableId = segment.slice(1);
                cursor = cursor.find((item) => stableSparkExtraItemId(item) === stableId);
                continue;
            }

            return undefined;
        }

        if (!isPlainObject(cursor) || DANGEROUS_PATH_SEGMENTS.has(segment)) return undefined;
        cursor = cursor[segment];
    }

    return cursor;
};

/**
 * Resolve the schema field represented by a concrete target such as
 * `plans.0.features.1.text`. Repeater index/stable-id segments are consumed
 * between parent and child field definitions.
 */
export const getSparkSchemaFieldAtTarget = (schema, targetPath) => {
    const target = normalizeSparkExtraTargetPath(targetPath);
    if (!target || !isPlainObject(schema) || !Array.isArray(schema.fields)) return null;

    const segments = target.split('.');
    let fields = schema.fields;
    let matched = null;

    for (let index = 0; index < segments.length; index += 1) {
        const segment = segments[index];
        if (/^[0-9]+$/.test(segment) || segment.startsWith('@')) continue;
        if (!Array.isArray(fields)) return null;

        matched = fields.find((field) => isPlainObject(field) && String(field.key || '') === segment) || null;
        if (!matched) return null;

        const childFields = Array.isArray(matched.fields) ? matched.fields : null;
        if (childFields) fields = childFields;
    }

    return matched;
};

const sparkExtraLeafKey = (targetPath) => {
    const target = normalizeSparkExtraTargetPath(targetPath);
    if (!target) return '';
    const segments = target.split('.').filter((segment) => !/^[0-9]+$/.test(segment) && !segment.startsWith('@'));
    return segments.at(-1) || '';
};

/**
 * Runtime-friendly inventory of only the fields that currently have extras.
 * This keeps the renderer cheap: a legacy Spark with no extras produces an
 * empty inventory and takes the exact pre-Batch-2 render path.
 */
export const getSparkFieldExtraAnchors = (block, schema = null) => {
    if (!isPlainObject(block)) return [];

    const state = normalizeSparkFieldExtrasState(block[FIELD_EXTRAS_STORAGE_KEY]);
    return Object.entries(state).map(([target, slots]) => {
        const schemaField = getSparkSchemaFieldAtTarget(schema, target);
        return {
            target,
            key: sparkExtraLeafKey(target),
            fieldType: cleanString(schemaField?.type || '', 80).toLowerCase(),
            label: cleanString(schemaField?.label || '', 500),
            value: getSparkExtraTargetValue(block, target),
            slots,
        };
    }).filter((descriptor) => descriptor.slots.before.length || descriptor.slots.after.length);
};
