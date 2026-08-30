import fs from 'node:fs';

const registryPath = 'app/Cosmic/Pricing/BlockPricingRegistry.php';
const catalogPath = 'app/Services/SparkCatalog.php';
const configPath = 'config/cosmic-sparks.php';
const commandPath = 'app/Console/Commands/AuditSparkIntelligenceMetadata.php';

const registry = fs.readFileSync(registryPath, 'utf8');
const catalog = fs.readFileSync(catalogPath, 'utf8');
const config = fs.readFileSync(configPath, 'utf8');
const command = fs.readFileSync(commandPath, 'utf8');

const registryKeys = [...registry.matchAll(/^\s*'([a-zA-Z0-9_-]+)'\s*=>\s*\[/gm)].map(m => m[1]);
const overrideBody = config.slice(config.indexOf("'overrides' => ["));
const overrideKeys = [...overrideBody.matchAll(/^\s{8}'([a-zA-Z0-9_-]+)'\s*=>\s*\[/gm)].map(m => m[1]);
const registrySet = new Set(registryKeys);
const staleOverrides = [...new Set(overrideKeys.filter(key => !registrySet.has(key)))];

const checks = [
  ['329 unique registered Sparks', registryKeys.length === 329 && new Set(registryKeys).size === 329],
  ['all override keys resolve to registered Sparks', staleOverrides.length === 0],
  ['metadata v2 marker exists', catalog.includes("'ai_metadata_version' => 2")],
  ['semantic_type emitted', catalog.includes("'semantic_type' => $metadata['semantic_type']")],
  ['traits emitted', catalog.includes("'traits' => $metadata['traits']")],
  ['use_cases emitted', catalog.includes("'use_cases' => $metadata['use_cases']")],
  ['style_traits emitted', catalog.includes("'style_traits' => $metadata['style_traits']")],
  ['visual_traits emitted', catalog.includes("'visual_traits' => $metadata['visual_traits']")],
  ['search_terms emitted', catalog.includes("'search_terms' => $metadata['search_terms']")],
  ['scalar-or-array override normalizer exists', catalog.includes('private static function listValue(mixed $value): array')],
  ['runtime strict audit command exists', command.includes('cosmic:audit-spark-intelligence') && command.includes('AI metadata schema: v2')],
];

let failed = 0;
for (const [label, pass] of checks) {
  console.log(`${pass ? 'PASS' : 'FAIL'} ${label}`);
  if (!pass) failed++;
}
console.log(`Registered Sparks: ${registryKeys.length}`);
console.log(`Explicit metadata overrides: ${new Set(overrideKeys).size}`);
if (staleOverrides.length) console.log(`Stale overrides: ${staleOverrides.join(', ')}`);
console.log(`${checks.length - failed}/${checks.length} checks passed.`);
process.exit(failed ? 1 : 0);
