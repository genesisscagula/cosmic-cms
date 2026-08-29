import assert from 'node:assert/strict';
import fs from 'node:fs';
import {
    findRepeaterForFieldPath,
    getNestedValue,
    listNestedRepeaters,
    mutateNestedRepeater,
} from '../resources/js/Pages/Websites/Blocks/Shared/nestedRepeaterEngine.js';

const block = {
    type: 'ai_flex_test',
    rows: [
        {
            id: 'row_a',
            columns: [
                {
                    id: 'col_a',
                    heading: 'Alpha',
                    extras: [{ _cosmic_id: 'nested_one', type: 'card', title: 'One' }, { _cosmic_id: 'nested_two', type: 'card', title: 'Two' }],
                },
                { id: 'col_b', heading: 'Beta', extras: [{ type: 'card', title: 'Three' }] },
            ],
        },
        { id: 'row_b', columns: [{ id: 'col_c', heading: 'Gamma', extras: [{ type: 'card', title: 'Four' }] }] },
    ],
    field_extras: {
        'rows.0.columns.0.heading': {
            before: [],
            after: [{ id: 'extra_img_1', type: 'image', data: { src: '/alpha.jpg', alt: 'Alpha' } }],
        },
        'rows.0.columns.1.heading': {
            before: [{ id: 'extra_badge_1', type: 'badge', data: { text: 'B' } }],
            after: [],
        },
    },
    luna_tailwind_schema: {
        version: 2,
        spark_type: 'ai_flex_test',
        styles: {},
        slots: {},
        collections: {
            rows: [
                {
                    key: 'row_a', styles: {}, collections: {
                        columns: [
                            { key: 'col_a', styles: { card: { add: ['rounded-xl'] } }, collections: {} },
                            { key: 'col_b', styles: { card: { add: ['shadow-lg'] } }, collections: {} },
                        ],
                    },
                },
                { key: 'row_b', styles: {}, collections: { columns: [{ key: 'col_c', styles: {}, collections: {} }] } },
            ],
        },
    },
};

const repeaters = listNestedRepeaters(block);
assert.ok(repeaters.some((r) => r.pathString === 'rows'));
assert.ok(repeaters.some((r) => r.pathString === 'rows.0.columns'));
assert.ok(repeaters.some((r) => r.pathString === 'rows.0.columns.0.extras'));
assert.equal(getNestedValue(block, 'rows.@row_a.columns.@col_b.heading'), 'Beta');
assert.equal(findRepeaterForFieldPath(block, 'rows.0.columns.1.heading')?.pathString, 'rows.0.columns');

const duplicated = mutateNestedRepeater(block, { path: 'rows.0.columns', action: 'duplicate', itemIndex: 0 });
assert.equal(duplicated.changed, true);
assert.equal(getNestedValue(duplicated.block, 'rows.0.columns').length, 3);
assert.equal(getNestedValue(duplicated.block, 'rows.0.columns.1.heading'), 'Alpha');
assert.ok(String(getNestedValue(duplicated.block, 'rows.0.columns.1._cosmic_id')).startsWith('item_'));
assert.notEqual(
    getNestedValue(duplicated.block, 'rows.0.columns.1.extras.0._cosmic_id'),
    getNestedValue(duplicated.block, 'rows.0.columns.0.extras.0._cosmic_id'),
    'duplicated descendants need fresh stable ids',
);
assert.ok(duplicated.block.field_extras['rows.0.columns.0.heading']);
assert.ok(duplicated.block.field_extras['rows.0.columns.1.heading']);
assert.notEqual(
    duplicated.block.field_extras['rows.0.columns.0.heading'].after[0].id,
    duplicated.block.field_extras['rows.0.columns.1.heading'].after[0].id,
    'duplicated extras need fresh ids',
);
assert.equal(
    duplicated.block.field_extras['rows.0.columns.2.heading'].before[0].data.text,
    'B',
    'following numeric extras paths must shift',
);
assert.deepEqual(
    duplicated.block.luna_tailwind_schema.collections.rows[0].collections.columns[1].styles.card.add,
    ['rounded-xl'],
    'duplicate must inherit source scoped Tailwind style',
);

const added = mutateNestedRepeater(block, { path: 'rows.0.columns', action: 'add' });
assert.equal(added.changed, true);
assert.equal(getNestedValue(added.block, 'rows.0.columns').length, 3);
assert.deepEqual(
    added.block.luna_tailwind_schema.collections.rows[0].collections.columns[2],
    { styles: {}, collections: {} },
    'new items must not inherit private scoped Tailwind overrides',
);

const removed = mutateNestedRepeater(block, { path: 'rows.0.columns', action: 'remove', itemIndex: 0 });
assert.equal(removed.changed, true);
assert.equal(getNestedValue(removed.block, 'rows.0.columns.0.heading'), 'Beta');
assert.ok(!removed.block.field_extras['rows.0.columns.1.heading']);
assert.equal(removed.block.field_extras['rows.0.columns.0.heading'].before[0].data.text, 'B');

const moved = mutateNestedRepeater(block, { path: 'rows.0.columns', action: 'move', itemIndex: 0, toIndex: 1 });
assert.equal(getNestedValue(moved.block, 'rows.0.columns.1.heading'), 'Alpha');
assert.ok(moved.block.field_extras['rows.0.columns.1.heading'].after.length === 1);
assert.ok(moved.block.field_extras['rows.0.columns.0.heading'].before.length === 1);

const stablePathMoved = mutateNestedRepeater(block, { path: 'rows.@row_a.columns', action: 'move', itemSelector: '@col_a', toIndex: 1 });
assert.equal(getNestedValue(stablePathMoved.block, 'rows.0.columns.1.heading'), 'Alpha');
assert.ok(stablePathMoved.block.field_extras['rows.0.columns.1.heading'].after.length === 1, 'stable collection path must remap numeric field extras');
assert.deepEqual(stablePathMoved.block.luna_tailwind_schema.collections.rows[0].collections.columns[1].styles.card.add, ['rounded-xl'], 'stable collection path must remap Tailwind scope');

const builder = fs.readFileSync('resources/js/Pages/Websites/Builder.jsx', 'utf8');
const controller = fs.readFileSync('app/Http/Controllers/CustomSparkController.php', 'utf8');
const service = fs.readFileSync('app/Services/LunaSmartSparkEditingService.php', 'utf8');
assert.ok(builder.includes('collectionPath'), 'Builder must carry exact nested collection path');
assert.ok(builder.includes("form.append('target_collection_path'"), 'Builder must post nested collection path');
assert.ok(controller.includes("'target_collection_path'=>['nullable','string','max:320']"), 'server must validate nested collection path');
assert.ok(service.includes('repeaterForContext'), 'Smart Spark routing must use nested repeater context');

console.log('Nested repeater Batch 3 PASS');
console.log('rows -> columns -> extras discovery, stable-id lookup, CRUD remapping, Tailwind/field-extras preservation PASS');
