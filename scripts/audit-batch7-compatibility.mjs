import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import {
    cloneSparkFieldExtrasWithFreshIds,
    normalizeBlockFieldExtras,
    normalizeSparkFieldExtrasState,
} from '../resources/js/Pages/Websites/Blocks/Shared/sparkExtrasContract.js';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const builder = read('resources/js/Pages/Websites/Builder.jsx');
const registry = read('resources/js/Pages/Websites/BlockRegistry.jsx');
const pageController = read('app/Http/Controllers/PageController.php');
const structural = read('app/Services/LunaStructuralActionService.php');

// Legacy hydration is additive and idempotent; unrelated saved data survives.
const legacy = { type: 'hero', heading: 'Legacy', private_flag: { keep: true } };
const once = normalizeBlockFieldExtras(legacy);
const twice = normalizeBlockFieldExtras(once);
assert.deepEqual(once, twice);
assert.deepEqual(once.field_extras, {});
assert.deepEqual(once.private_flag, { keep: true });

// Corrupt/unknown additions are rejected without damaging ordinary fields.
const sanitized = normalizeBlockFieldExtras({
    ...legacy,
    field_extras: {
        heading: { after: [
            { id: 'safe', type: 'badge', data: { text: 'Safe' } },
            { id: 'unsafe', type: 'script', data: { text: 'No' } },
        ] },
        '__proto__.polluted': { after: [{ id: 'bad', type: 'text', data: { text: 'No' } }] },
    },
});
assert.equal(sanitized.field_extras.heading.after.length, 1);
assert.equal(Object.hasOwn(sanitized.field_extras, '__proto__.polluted'), false);

// A duplicated Spark preserves exact content/placement while refreshing IDs.
const duplicate = cloneSparkFieldExtrasWithFreshIds(sanitized.field_extras);
assert.deepEqual(normalizeSparkFieldExtrasState(duplicate).heading.after[0].data, sanitized.field_extras.heading.after[0].data);
assert.notEqual(duplicate.heading.after[0].id, sanitized.field_extras.heading.after[0].id);

// Representative families are still present in the normalized 329-Spark registry.
const representativeTypes = [
    'hero', 'services', 'features', 'testimonials', 'pricing', 'faq',
    'contact', 'cta', 'gallery', 'process', 'team', 'about',
];
for (const family of representativeTypes) {
    assert.match(registry, new RegExp(`\\b${family}[A-Za-z0-9_]*:\\s*\\{\\s*component:`), `missing representative ${family} Spark`);
}
assert.equal((registry.match(/^\s{4}[A-Za-z0-9_]+:\s*\{\s*component:/gm) || []).length, 329);

// Save boundaries, duplicate/layout paths, popup isolation and 3-column safety.
assert.ok((pageController.match(/SparkExtrasContract::normalizeBlocks/g) || []).length >= 2, 'all page-save boundaries must normalize extras');
assert.match(builder, /cloneSparkFieldExtrasWithFreshIds\(duplicated\.field_extras\)/);
assert.ok((builder.match(/field_extras: cloneBuilderEditValue\(currentBlock\.field_extras \|\| \{\}\)/g) || []).length >= 2);
assert.match(builder, /setPopupDraftBlocks\(normalizedResponseBlocks\)/);
assert.match(builder, /setData\('blocks', normalizeRenderKeys\(draft\.blocks \|\| \[\]\)\)/);
assert.match(structural, /private function rebalanceColumns/);
assert.match(structural, /round\(100 \/ count\(\$columns\), 2\)/);

console.log('PASS Batch 7 compatibility — legacy migration, sanitization, 12 Spark families, duplicate/layout preservation, popup isolation, balanced AI Flex columns');
