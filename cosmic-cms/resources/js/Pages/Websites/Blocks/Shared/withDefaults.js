export function withDefaults(schema, block = {}) {
    return {
        ...schema.defaults,
        ...block
    };
}