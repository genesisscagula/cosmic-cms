const STORAGE_KEY = 'luna_tailwind_schema';

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

export function getSparkTailwindSchema(block = {}) {
    const raw = block?.[STORAGE_KEY];
    if (!raw || typeof raw !== 'object' || Array.isArray(raw)) {
        return { version: 1, spark_type: String(block?.type || ''), slots: {} };
    }

    return {
        version: Number(raw.version || 1),
        spark_type: String(raw.spark_type || block?.type || ''),
        slots: raw.slots && typeof raw.slots === 'object' && !Array.isArray(raw.slots) ? raw.slots : {},
    };
}

export function resolveSparkTailwindSlot(block, slot, fallback = '') {
    const schema = getSparkTailwindSchema(block);
    const key = normalizeSlot(slot);
    const definition = schema.slots?.[key];

    if (definition == null) return normalizeClasses(fallback);

    if (definition && typeof definition === 'object' && !Array.isArray(definition)
        && !Object.prototype.hasOwnProperty.call(definition, 'classes')
        && (Object.prototype.hasOwnProperty.call(definition, 'add') || Object.prototype.hasOwnProperty.call(definition, 'remove'))) {
        const base = normalizeClasses(fallback).split(/\s+/).filter(Boolean);
        const remove = new Set(normalizeClasses(definition.remove).split(/\s+/).filter(Boolean));
        const next = base.filter((token) => !remove.has(token));
        normalizeClasses(definition.add).split(/\s+/).filter(Boolean).forEach((token) => {
            if (!next.includes(token)) next.push(token);
        });
        return next.join(' ');
    }

    const classes = definition && typeof definition === 'object' && !Array.isArray(definition)
        ? definition.classes
        : definition;
    const resolved = normalizeClasses(classes);

    // An explicitly present slot owns the full class contract. Empty classes are
    // therefore meaningful (Luna may intentionally remove every class from a slot).
    return resolved;
}

export function createSparkTailwindRuntime(block = {}) {
    const schema = getSparkTailwindSchema(block);
    const slots = schema.slots || {};

    return Object.freeze({
        version: schema.version,
        sparkType: schema.spark_type || String(block?.type || ''),
        migrationState: Object.keys(slots).length ? 'schema_backed' : 'legacy_fallback',
        has(slot) {
            return Object.prototype.hasOwnProperty.call(slots, normalizeSlot(slot));
        },
        get(slot, fallback = '') {
            return resolveSparkTailwindSlot(block, slot, fallback);
        },
        definition(slot) {
            return slots[normalizeSlot(slot)] || null;
        },
        schema,
    });
}

export { STORAGE_KEY as SPARK_TAILWIND_STORAGE_KEY };

export function sparkTw(block, slot, fallback = '') {
    const resolved = resolveSparkTailwindSlot(block, slot, fallback);
    const marker = `cosmic-tw-slot--${normalizeSlot(slot)}`;
    return `${resolved} ${marker}`.trim();
}
