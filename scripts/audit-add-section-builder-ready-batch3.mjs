import fs from 'node:fs';

const modal = fs.readFileSync('resources/js/Pages/Websites/Components/AddSectionModal.jsx', 'utf8');
const market = fs.readFileSync('resources/js/Pages/Websites/Components/SparkRegistry.jsx', 'utf8');
const builder = fs.readFileSync('resources/js/Pages/Websites/BlockRegistry.jsx', 'utf8');

const checks = [
  ['eligibility gate exists', modal.includes('const addSectionSparkEligibility = (spark) =>')],
  ['requires Builder component', modal.includes('!builderEntry?.component')],
  ['requires Builder schema', modal.includes('!builderEntry?.schema')],
  ['supports explicit repair opt-out', modal.includes('addSectionReady === false') && modal.includes('builderReady === false')],
  ['rejects malformed preview payload', modal.includes('invalid_preview_payload')],
  ['raw catalog separated from ready items', modal.includes('const catalogItems = useMemo') && modal.includes('const sparkReadiness = useMemo')],
  ['visible items filtered by readiness', modal.includes('catalogItems.filter((spark) => sparkReadiness.get(spark.key)?.ready)')],
  ['category chooser uses filtered items', modal.includes('const matches = items.filter((item) => (categoryFor(item.key, item.category)) === name)')],
  ['no-ready category fails safely', modal.includes('No ready layouts yet')],
  ['unsafe count diagnostic exists', modal.includes('hiddenUnsafeSparkCount')],
  ['actual preview uses Builder registry', modal.includes('const registryItem = BuilderBlockRegistry[spark.key]')],
  ['preview parity comment retained', modal.includes('Preview parity rule: render the registry block exactly like Builder does.')],
];

// Static parity: every top-level marketplace Spark type should have a Builder key.
const marketTypes = [...market.matchAll(/^\s{8}type:\s*["']([^"']+)["'],/gm)].map(m => m[1]);
const builderKeys = new Set([...builder.matchAll(/^\s*([A-Za-z0-9_]+):\s*\{/gm)].map(m => m[1]));
const missing = [...new Set(marketTypes.filter(type => !builderKeys.has(type)))];
checks.push(['marketplace types have Builder registry parity', missing.length === 0]);

let failed = 0;
for (const [name, ok] of checks) {
  console.log(`${ok ? 'PASS' : 'FAIL'} - ${name}`);
  if (!ok) failed++;
}
if (missing.length) console.log('Missing Builder types:', missing.join(', '));
console.log(`\n${checks.length - failed}/${checks.length} PASS`);
process.exit(failed ? 1 : 0);
