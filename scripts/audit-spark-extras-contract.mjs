import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import {
    FIELD_EXTRAS_STORAGE_KEY,
    SPARK_EXTRA_PLACEMENTS,
    SPARK_EXTRA_TYPES,
    cloneSparkFieldExtrasWithFreshIds,
    getSparkFieldExtras,
    normalizeBlockFieldExtras,
    normalizeBlockRegistry,
    normalizeSparkExtraItem,
    normalizeSparkExtraTargetPath,
    normalizeSparkFieldExtrasState,
    normalizeSparkSchema,
} from '../resources/js/Pages/Websites/Blocks/Shared/sparkExtrasContract.js';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

assert.deepEqual(SPARK_EXTRA_PLACEMENTS, ['before', 'after']);
assert.deepEqual(SPARK_EXTRA_TYPES, ['text', 'heading', 'image', 'button', 'icon', 'badge', 'video', 'divider', 'spacer']);
assert.equal(FIELD_EXTRAS_STORAGE_KEY, 'field_extras');

const normalizedSchema = normalizeSparkSchema({
    type: 'qa_spark',
    defaults: { heading: 'Hello' },
    fields: [
        { key: 'heading', type: 'text' },
        {
            key: 'cards',
            type: 'repeater',
            fields: [
                { key: 'title', type: 'text' },
                { key: 'image_url', type: 'image' },
            ],
        },
    ],
});

assert.deepEqual(normalizedSchema.fields[0].extras, { before: [], after: [] });
assert.deepEqual(normalizedSchema.fields[1].extras, { before: [], after: [] });
assert.deepEqual(normalizedSchema.fields[1].fields[0].extras, { before: [], after: [] });
assert.deepEqual(normalizedSchema.fields[1].fields[1].extras, { before: [], after: [] });
assert.deepEqual(normalizedSchema.defaults.field_extras, {});
assert.equal(normalizedSchema.extras_contract_version, 1);

const registry = normalizeBlockRegistry({
    qa_spark: { component: () => null, schema: normalizedSchema },
});
assert.equal(registry.qa_spark.schema.extras_contract_version, 1);

assert.equal(normalizeSparkExtraTargetPath('plans[0].title'), 'plans.0.title');
assert.equal(normalizeSparkExtraTargetPath('rows.@row_1.columns.@col_2.heading'), 'rows.@row_1.columns.@col_2.heading');
assert.equal(normalizeSparkExtraTargetPath('__proto__.heading'), null);

const image = normalizeSparkExtraItem({
    id: 'image_1',
    type: 'image',
    data: { src: '/storage/cms-images/example.jpg', alt: 'Example', unknown: 'drop me' },
});
assert.equal(image.id, 'image_1');
assert.equal(image.type, 'image');
assert.equal(image.data.src, '/storage/cms-images/example.jpg');
assert.equal(image.data.unknown, undefined);

const unsafeButton = normalizeSparkExtraItem({
    type: 'button',
    data: { label: 'Run', url: 'javascript:alert(1)' },
});
assert.equal(unsafeButton.data.url, '');

const state = normalizeSparkFieldExtrasState({
    'heading': {
        before: [{ id: 'badge_1', type: 'badge', data: { text: 'Premium' } }],
        after: [{ id: 'image_1', type: 'image', data: { src: '/image.jpg' } }],
    },
    'plans[0].title': {
        after: [{ id: 'cta_1', type: 'button', data: { label: 'Choose', url: '#checkout' } }],
    },
    '__proto__.polluted': {
        after: [{ id: 'bad_1', type: 'text', data: { text: 'nope' } }],
    },
});
assert.equal(Object.keys(state).length, 2);
assert.equal(state['plans.0.title'].after[0].data.url, '#checkout');

const legacy = normalizeBlockFieldExtras({ type: 'qa_spark', heading: 'Legacy block' });
assert.deepEqual(legacy.field_extras, {});
assert.equal(legacy.heading, 'Legacy block');

const hydrated = normalizeBlockFieldExtras({ type: 'qa_spark', heading: 'Hello', field_extras: state });
assert.equal(getSparkFieldExtras(hydrated, 'heading').after[0].type, 'image');

const duplicatedExtras = cloneSparkFieldExtrasWithFreshIds(hydrated.field_extras);
const flatten = (extras) => Object.values(extras).flatMap((slots) => [...slots.before, ...slots.after]);
assert.deepEqual(flatten(duplicatedExtras).map((item) => item.data), flatten(hydrated.field_extras).map((item) => item.data));
assert.notDeepEqual(flatten(duplicatedExtras).map((item) => item.id), flatten(hydrated.field_extras).map((item) => item.id));

const blockRegistrySource = fs.readFileSync(path.join(root, 'resources/js/Pages/Websites/BlockRegistry.jsx'), 'utf8');
assert.match(blockRegistrySource, /const RawBlockRegistry = \{/);
assert.match(blockRegistrySource, /const NormalizedBlockRegistry = normalizeBlockRegistry\(RawBlockRegistry\);/);
assert.match(blockRegistrySource, /export const BlockRegistry = Object\.fromEntries/);
const entryCount = (blockRegistrySource.match(/^\s{4}[A-Za-z0-9_]+:\s*\{\s*component:/gm) || []).length;
assert.equal(entryCount, 329, `Expected 329 registered Builder Sparks, found ${entryCount}`);

const builderSource = fs.readFileSync(path.join(root, 'resources/js/Pages/Websites/Builder.jsx'), 'utf8');
assert.match(builderSource, /normalizeBlockFieldExtras\(block\)/);
assert.match(builderSource, /cloneSparkFieldExtrasWithFreshIds\(duplicated\.field_extras\)/);
assert.match(builderSource, /field_extras: cloneBuilderEditValue\(currentBlock\.field_extras \|\| \{\}\)/);

console.log(`PASS spark extras contract v1 — ${entryCount} registered Sparks are registry-normalized`);
