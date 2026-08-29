import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import {
    getSparkExtraTargetValue,
    getSparkFieldExtraAnchors,
    getSparkSchemaFieldAtTarget,
    normalizeSparkSchema,
} from '../resources/js/Pages/Websites/Blocks/Shared/sparkExtrasContract.js';

const root = process.cwd();
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

const schema = normalizeSparkSchema({
    fields: [
        { key: 'heading', type: 'text', label: 'Heading' },
        {
            key: 'plans', type: 'repeater', label: 'Plans', fields: [
                { key: 'title', type: 'text', label: 'Plan title' },
                { key: 'image', type: 'image', label: 'Plan image' },
                { key: 'features', type: 'repeater', fields: [{ key: 'text', type: 'text', label: 'Feature' }] },
            ],
        },
    ],
});

const block = {
    heading: 'Build with confidence',
    plans: [
        { id: 'starter', title: 'Starter', image: '/starter.jpg', features: [{ text: 'Fast' }] },
        { id: 'growth', title: 'Growth', image: '/growth.jpg', features: [{ text: 'Flexible' }] },
    ],
    field_extras: {
        heading: { before: [], after: [{ id: 'extra_heading_image', type: 'image', data: { src: '/hero.jpg', alt: 'Hero' } }] },
        'plans.1.title': { before: [{ id: 'extra_badge', type: 'badge', data: { text: 'Popular' } }], after: [] },
        'plans.@growth.features.0.text': { before: [], after: [{ id: 'extra_button', type: 'button', data: { label: 'Learn more', url: '#learn' } }] },
    },
};

assert.equal(getSparkExtraTargetValue(block, 'heading'), 'Build with confidence');
assert.equal(getSparkExtraTargetValue(block, 'plans[1].title'), 'Growth');
assert.equal(getSparkExtraTargetValue(block, 'plans.@growth.features.0.text'), 'Flexible');
assert.equal(getSparkSchemaFieldAtTarget(schema, 'plans.1.title')?.key, 'title');
assert.equal(getSparkSchemaFieldAtTarget(schema, 'plans.@growth.features.0.text')?.key, 'text');

const anchors = getSparkFieldExtraAnchors(block, schema);
assert.equal(anchors.length, 3);
assert.equal(anchors.find((item) => item.target === 'heading')?.slots.after[0].type, 'image');
assert.equal(anchors.find((item) => item.target === 'plans.1.title')?.fieldType, 'text');
assert.equal(anchors.find((item) => item.target === 'plans.@growth.features.0.text')?.value, 'Flexible');

const runtime = read('resources/js/Pages/Websites/Blocks/Shared/SparkFieldExtrasRuntime.jsx');
const registry = read('resources/js/Pages/Websites/BlockRegistry.jsx');
const editableText = read('resources/js/Pages/Websites/Blocks/Shared/EditableText.jsx');
const editableButton = read('resources/js/Pages/Websites/Blocks/Shared/EditableButton.jsx');
const editableImage = read('resources/js/Pages/Websites/Blocks/Shared/EditableImage.jsx');
const builder = read('resources/js/Pages/Websites/Builder.jsx');

for (const token of [
    'SparkFieldExtrasProvider',
    'SparkFieldExtraSlots',
    'SparkFieldExtraList',
    'data-cosmic-field-extra-portal',
    'findFallbackDomAnchor',
    "type === 'image'",
    "type === 'button'",
    "type === 'video'",
    "type === 'divider'",
    "type === 'spacer'",
]) assert.ok(runtime.includes(token), `runtime missing ${token}`);

assert.ok(registry.includes('wrapSparkComponentWithFieldExtras'), 'registry must wrap every registered Spark');
assert.ok(registry.includes('<SparkFieldExtrasProvider'), 'registry wrapper must mount provider');
for (const [name, source] of [['EditableText', editableText], ['EditableButton', editableButton], ['EditableImage', editableImage]]) {
    assert.ok(source.includes('useSparkFieldExtrasAnchor'), `${name} must opt into deterministic field anchors`);
    assert.ok(source.includes('data-cosmic-field-path'), `${name} must expose resolved field path`);
    assert.ok(source.includes('SparkFieldExtraSlots'), `${name} must render before/after slots`);
}

// Contextual section preview already calls the same renderBlock path; keep this
// contract so popup drafts and the frozen Builder never diverge in Batch 2.
assert.ok(builder.includes('data-popup-section-preview="true"'), 'section popup preview contract missing');
assert.ok(builder.includes('activeBlock ? renderBlock(activeBlock, editSession.blockIndex)'), 'popup preview must render draft block');
assert.ok(builder.includes("data-cosmic-field-path"), 'Builder must preserve exact field paths on Luna targets');
assert.ok(builder.includes("fieldPath:String(fieldPath||'')"), 'hover target must carry exact Spark field path');

const registrySource = registry.slice(registry.indexOf('const RawBlockRegistry = {'), registry.indexOf('const NormalizedBlockRegistry'));
const registrationCount = (registrySource.match(/^\s{4}[A-Za-z0-9_]+:\s*\{\s*component:/gm) || []).length;
assert.equal(registrationCount, 329, `expected 329 registered Sparks, found ${registrationCount}`);

console.log(`Spark extras Batch 2 renderer PASS · ${registrationCount} registered Sparks wrapped`);
console.log('Deterministic anchors + DOM fallback + popup draft parity PASS');
