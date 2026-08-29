import assert from 'node:assert/strict';
import fs from 'node:fs';
import {
    AI_FLEX_STRUCTURE_CONTRACT,
    aiFlexLogicalPathToStoragePath,
    aiFlexStoragePathToLogicalPath,
    canonicalizeAiFlexElements,
    getAiFlexCollection,
    isCanonicalAiFlexElements,
    mutateAiFlexStructure,
    normalizeAiFlexBlock,
} from '../resources/js/Pages/Websites/Blocks/Shared/aiFlexStructureContract.js';

const legacy = [
    { type: 'heading', text: 'Build better' },
    { type: 'text', text: 'A legacy root-level composition.' },
];
const canonical = canonicalizeAiFlexElements(legacy);
assert.equal(canonical.length, 1, 'legacy primitives should become one row');
assert.equal(canonical[0].type, 'row');
assert.equal(canonical[0].children.length, 1);
assert.equal(canonical[0].children[0].type, 'column');
assert.equal(canonical[0].children[0].children.length, 2);
assert.equal(canonical[0].children[0].children[0].type, 'heading');
assert.ok(isCanonicalAiFlexElements(canonical));

const ids = [];
const walk = (nodes) => (nodes || []).forEach((node) => {
    ids.push(node._cosmic_id);
    walk(node.children);
});
walk(canonical);
assert.equal(ids.length, new Set(ids).size, 'AI Flex node ids must be unique');
assert.ok(ids.every(Boolean), 'every row/column/extra must have a stable id');

assert.deepEqual(aiFlexLogicalPathToStoragePath('rows'), ['elements']);
assert.deepEqual(aiFlexLogicalPathToStoragePath('rows.0.columns'), ['elements','0','children']);
assert.deepEqual(aiFlexLogicalPathToStoragePath('rows.0.columns.1.extras'), ['elements','0','children','1','children']);
assert.deepEqual(aiFlexStoragePathToLogicalPath('elements.0.children.1.children'), ['rows','0','columns','1','extras']);

let block = normalizeAiFlexBlock({
    type: 'luna_custom_section',
    elements: canonical,
    ai_flex: { source: 'sol' },
}, { canonicalize: true });
assert.equal(block.ai_flex.structure_contract, AI_FLEX_STRUCTURE_CONTRACT);
assert.equal(getAiFlexCollection(block, 'rows').length, 1);
assert.equal(getAiFlexCollection(block, 'rows.0.columns').length, 1);
assert.equal(getAiFlexCollection(block, 'rows.0.columns.0.extras').length, 2);

let result = mutateAiFlexStructure(block, { path: 'rows.0.columns.0.extras', action: 'add', extraType: 'image' });
assert.equal(result.changed, true);
assert.equal(result.collectionKey, 'extras');
assert.equal(getAiFlexCollection(result.block, 'rows.0.columns.0.extras').length, 3);
block = result.block;

result = mutateAiFlexStructure(block, { path: 'rows.0.columns', action: 'duplicate', itemIndex: 0 });
assert.equal(result.changed, true);
assert.equal(getAiFlexCollection(result.block, 'rows.0.columns').length, 2);
assert.notEqual(
    getAiFlexCollection(result.block, 'rows.0.columns')[0]._cosmic_id,
    getAiFlexCollection(result.block, 'rows.0.columns')[1]._cosmic_id,
    'duplicated columns must receive a fresh identity',
);
block = result.block;

result = mutateAiFlexStructure(block, { path: 'rows', action: 'add' });
assert.equal(result.changed, true);
assert.equal(getAiFlexCollection(result.block, 'rows').length, 2);
block = result.block;

const empty = normalizeAiFlexBlock({
    type: 'luna_custom_section',
    elements: [{ type:'row', children:[{ type:'column', children:[] }] }],
    ai_flex: { structure_contract: AI_FLEX_STRUCTURE_CONTRACT },
}, { canonicalize: true });
result = mutateAiFlexStructure(empty, { path: 'rows.0.columns.0.extras', action: 'add', extraType: 'video' });
assert.equal(result.changed, true, 'empty Extras repeater must accept its first item');
assert.equal(getAiFlexCollection(result.block, 'rows.0.columns.0.extras')[0].type, 'video');

const read = (file) => fs.readFileSync(file, 'utf8');
const service = read('app/Services/LunaAiFlexSparkService.php');
const phpContract = read('app/Support/AiFlexStructureContract.php');
const renderer = read('resources/js/Pages/Websites/Blocks/General/LunaCustomSectionBlock.jsx');
const builder = read('resources/js/Pages/Websites/Builder.jsx');
const compiler = read('app/Helpers/CmsHtmlCompiler.php');
const extrasContract = read('app/Support/SparkExtrasContract.php');
const nestedPhp = read('app/Services/NestedRepeaterMutationService.php');

for (const token of [
    'AI FLEX UNIVERSAL ELEMENTS (v5)',
    'Root elements are rows',
    'AiFlexStructureContract::canonicalizeElements',
    "'structure_contract'",
    "'video'",
]) assert.ok(service.includes(token), `AI Flex service missing ${token}`);

for (const token of [
    'rows_columns_extras_v1',
    'canonicalizeElements',
    'logicalToStoragePath',
    "'elements'",
]) assert.ok(phpContract.includes(token), `PHP structure contract missing ${token}`);

for (const token of [
    'data-cosmic-ai-flex-role',
    'data-cosmic-ai-flex-collection-path',
    'data-cosmic-ai-flex-logical-collection-path',
    'data-cosmic-ai-flex-structure="rows-columns-extras"',
    "case'video'",
]) assert.ok(renderer.includes(token), `Builder renderer missing ${token}`);

assert.ok(builder.includes('Prefer this deterministic metadata over visual-card heuristics.'), 'Builder must prefer explicit AI Flex target metadata');
assert.ok(builder.includes("getNestedValue(block,collectionPath)"), 'Builder must resolve the physical AI Flex collection path');
assert.ok(compiler.includes("elseif($type==='video')"), 'static compiler must render AI Flex videos');
assert.ok(extrasContract.includes('AiFlexStructureContract::normalizePersistedBlock'), 'page-save normalization must preserve AI Flex identities');
assert.ok(nestedPhp.includes('AiFlexStructureContract::logicalToStoragePath'), 'server nested mutation engine must understand logical AI Flex paths');

console.log('AI Flex Batch 4 PASS');
console.log('Rows -> Columns -> Extras canonical structure, logical-path adapter, stable IDs, empty repeater add, Builder targeting, video/export parity PASS');
