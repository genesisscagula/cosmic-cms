import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  HYBRID_SPARK_SAFE_TYPES,
  createHybridSparkExtraFromLegoType,
  getHybridSparkInsertionAnchors,
  chooseHybridSparkDefaultInsertionTarget,
} from '../resources/js/Pages/Websites/Blocks/Shared/hybridSparkInsertionContract.js';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const read = (p) => fs.readFileSync(path.join(root, p), 'utf8');
const runtime = read('resources/js/Pages/Websites/Blocks/Shared/SparkFieldExtrasRuntime.jsx');
const registry = read('resources/js/Pages/Websites/BlockRegistry.jsx');
const builder = read('resources/js/Pages/Websites/Builder.jsx');
const panel = read('resources/js/Pages/Websites/Components/LegoElementsPanel.jsx');
const css = read('resources/css/app.css');
const compiler = read('app/Helpers/CmsHtmlCompiler.php');

const syntheticSchema = {
  fields: [
    { key: 'tagline', type: 'text', label: 'Tagline' },
    { key: 'heading', type: 'text', label: 'Heading' },
    { key: 'description', type: 'textarea', label: 'Description' },
    { key: 'cards', type: 'repeater', label: 'Cards', fields: [
      { key: 'title', type: 'text', label: 'Title' },
      { key: 'desc', type: 'textarea', label: 'Description' },
    ]},
  ],
};
const syntheticBlock = {
  tagline: 'Trusted by teams', heading: 'A better section', description: 'Support copy',
  cards: [
    { _cosmic_id: 'card_a', title: 'First card', desc: 'First description' },
    { title: 'Second card', desc: 'Second description' },
  ],
  field_extras: {},
};
const anchors = getHybridSparkInsertionAnchors(syntheticBlock, syntheticSchema);
const registryBody = registry.slice(registry.indexOf('const RawBlockRegistry = {'), registry.indexOf('\n};', registry.indexOf('const RawBlockRegistry = {')));
const registryEntryCount = new Set([...registryBody.matchAll(/^\s{4}([A-Za-z0-9_]+):\s*\{/gm)].map((m) => m[1])).size;

const checks = [
  ['safe element contract', HYBRID_SPARK_SAFE_TYPES.length >= 9 && HYBRID_SPARK_SAFE_TYPES.includes('heading') && HYBRID_SPARK_SAFE_TYPES.includes('image') && HYBRID_SPARK_SAFE_TYPES.includes('list')],
  ['schema leaf anchors', anchors.some((a) => a.target === 'heading') && anchors.some((a) => a.target === 'description')],
  ['stable repeater anchors', anchors.some((a) => a.target === 'cards.@card_a.title') && anchors.some((a) => a.target === 'cards.1.title')],
  ['default anchor prefers heading', chooseHybridSparkDefaultInsertionTarget(syntheticBlock, syntheticSchema) === 'heading'],
  ['field extra factories', HYBRID_SPARK_SAFE_TYPES.every((type) => createHybridSparkExtraFromLegoType(type)?.type === type)],
  ['all registered schemas normalized', registry.includes('const NormalizedBlockRegistry = normalizeBlockRegistry(RawBlockRegistry)')],
  ['broad registry surface', registryEntryCount >= 321],
  ['provider gets hybrid props', registry.includes('builderMode={Boolean(props?.builderMode && props?.hybridSparkBuilder)}') && registry.includes('onInsertHybridExtra={props?.onInsertHybridExtra}')],
  ['builder hybrid insertion mutation', builder.includes('const insertHybridSparkExtra =') && builder.includes('block.field_extras[target] = slots')],
  ['builder marks current hybrid contract', builder.includes("insertion_contract: 'field_extras_v2'") && builder.includes('insertion_version: 2')],
  ['existing sparks expose element panel', builder.includes("'Insert Elements'") && builder.includes('hybridSparkBuilder:')],
  ['selected/best click target', builder.includes('legoHybridTarget') && builder.includes('chooseHybridSparkDefaultInsertionTarget')],
  ['drag/drop data contract', runtime.includes("getData('application/x-cosmic-lego')") && runtime.includes('data-cosmic-hybrid-drop-zone="1"')],
  ['before and after drop zones', runtime.includes('placement="before"') && runtime.includes('placement="after"')],
  ['fallback DOM anchors get zones', runtime.includes('builderMode || descriptor.slots?.before?.length') && runtime.includes('builderMode || descriptor.slots?.after?.length')],
  ['hybrid panel guidance', panel.includes('dotted Spark insertion zones') && panel.includes('hybridMode')],
  ['builder-only zone styling', css.includes('Cosmic Lego Builder — Batch 8 hybrid Spark insertion zones') && css.includes('.cosmic-hybrid-spark-drop-zone')],
  ['published compiler consumes field extras', compiler.includes('applySparkFieldExtrasToFragment') && compiler.includes('renderSparkFieldExtraList')],
  ['published extras inherit design tokens', compiler.includes('var(--cosmic-button-primary-bg') && compiler.includes('var(--cosmic-local-image-radius')],
  ['unsupported advanced block is guarded', builder.includes('Advanced Spark compatibility comes next')],
];

let pass = 0;
for (const [name, ok] of checks) {
  console.log(`${ok ? 'PASS' : 'FAIL'} ${name}`);
  if (ok) pass++;
}
console.log(`\nRegistered Spark entries detected: ${registryEntryCount}`);
console.log(`${pass}/${checks.length} assertions passed.`);
if (pass !== checks.length) process.exit(1);
