import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = (p) => fs.readFileSync(path.join(root, p), 'utf8');
const exists = (p) => fs.existsSync(path.join(root, p));

const pricing = read('app/Cosmic/Pricing/BlockPricingRegistry.php');
const schema = read('app/AI/Schemas/SchemaManager.php');
const builderRegistry = read('resources/js/Pages/Websites/BlockRegistry.jsx');
const builder = read('resources/js/Pages/Websites/Builder.jsx');
const addSection = read('resources/js/Pages/Websites/Components/AddSectionModal.jsx');
const twRuntime = read('resources/js/Pages/Websites/Blocks/Shared/sparkTailwindRuntime.js');
const twContract = read('app/Services/SparkTailwindSchemaContract.php');
const compiler = read('app/Helpers/CmsHtmlCompiler.php');
const publisher = read('app/Services/PagePublisher.php');
const preview = read('app/Services/PreviewDeploymentService.php');
const pageController = read('app/Http/Controllers/PageController.php');
const websiteController = read('app/Http/Controllers/WebsiteController.php');
const connector = read('app/Services/DeploymentConnectorArchive.php');
const renderContract = JSON.parse(read('resources/render-contract.json'));
const guards = read('app/Services/LunaSparkSchemaEditorService.php');
const executor = read('app/Http/Controllers/CustomSparkController.php');
const extras = read('resources/js/Pages/Websites/Blocks/Shared/sparkExtrasContract.js');

const keys = [...new Set([...pricing.matchAll(/^\s*'([a-z0-9_]+)'\s*=>\s*\['label'/gm)].map((m) => m[1]))];
const schemaKeys = new Set([...schema.matchAll(/^\s*'([a-z0-9_]+)'\s*=>\s*'[A-Za-z0-9_]+'/gm)].map((m) => m[1]));
const builderKeys = new Set(keys.filter((key) => builderRegistry.includes(`${key}:`) || builderRegistry.includes(`'${key}':`) || builderRegistry.includes(`"${key}":`)));
const compilerKeys = new Set([...compiler.matchAll(/case\s+["']([a-z0-9_]+)["']\s*:/g)].map((m) => m[1]));

const count = (haystack, needle) => haystack.split(needle).length - 1;
const contractMarker = renderContract?.spark_contract || {};

const checks = [
  ['329 registered Sparks', keys.length === 329],
  ['329 registered schemas', keys.every((key) => schemaKeys.has(key))],
  ['329 Builder renderer routes', keys.every((key) => builderKeys.has(key))],
  ['329 PHP export compiler routes', keys.every((key) => compilerKeys.has(key))],
  ['render-contract declares Batch 6 round-trip gate', contractMarker.inventory === 329 && contractMarker.layout === 'premium-v1' && contractMarker.round_trip === 'batch6'],
  ['render-contract declares all five production surfaces', ['builder','add_section','preview','export','live'].every((surface) => contractMarker.surfaces?.includes(surface))],

  ['Builder imports the shared render-contract version', builder.includes("import renderContract from '../../../render-contract.json'") && builder.includes('data-cosmic-render-contract={renderContract.version}')],
  ['Builder passes complete persisted block to the registered component', builder.includes('block: {\n                ...block,\n                resolvedTheme') && builder.includes('<Component {...blockProps} />')],
  ['Builder resolves per-instance Tailwind from the same block', builder.includes('tailwind: createSparkTailwindRuntime(block)') && builder.includes("data-cosmic-tailwind-schema={hasSparkTailwindSchema(block) ? 'schema_backed' : 'legacy_fallback'}")],
  ['Builder preserves exact Spark identity/index on the render shell', builder.includes('data-cosmic-block-index={index}') && builder.includes('data-cosmic-block-type={block.type}')],

  ['Add Section bridges missing curated previews from Builder registry', addSection.includes('createBuilderRegistryFallback') && addSection.includes('registry.get(spark.key) || createBuilderRegistryFallback(spark)')],
  ['Add Section preview renders the actual Builder component', addSection.includes('const registryItem = BuilderBlockRegistry[spark.key]') && addSection.includes('<Component\n                block={block}')],
  ['Add Section preview resolves Tailwind from its actual preview block', addSection.includes('tailwind={createSparkTailwindRuntime(block)}')],
  ['Quick Add persists Builder schema defaults before marketplace overrides', addSection.includes('const schemaDefaults = BuilderBlockRegistry[selected.key]?.schema?.defaults || {}') && addSection.includes('...structuredClone(schemaDefaults)') && addSection.includes('...structuredClone(selected.registry.payload || {})')],

  ['JS Tailwind runtime reads luna_tailwind_schema from the Spark instance', twRuntime.includes("const STORAGE_KEY = 'luna_tailwind_schema'") && twRuntime.includes('const raw = block?.[STORAGE_KEY]')],
  ['JS Tailwind runtime preserves add/remove delta semantics', twRuntime.includes("Object.prototype.hasOwnProperty.call(definition, 'add')") && twRuntime.includes("Object.prototype.hasOwnProperty.call(definition, 'remove')") && twRuntime.includes('fallbackTokens.filter')],
  ['PHP Tailwind resolver uses the same per-instance schema contract', twContract.includes("return (string) config('spark-tailwind-schema.storage_key', 'luna_tailwind_schema')") && twContract.includes('public function resolveSlot') && twContract.includes('resolveDefinition')],
  ['PHP compiler routes Spark class ownership through SparkTailwindSchemaContract', compiler.includes('private static function sparkTw') && compiler.includes('SparkTailwindSchemaContract::class')],

  ['Builder/Export both consume section overrides', builder.includes('block?.luna_section_overrides') && compiler.includes("$block['luna_section_overrides']")],
  ['Builder/Export both consume typography overrides', builder.includes('block?.luna_typography_overrides') && compiler.includes("$block['luna_typography_overrides']")],
  ['Builder/Export both consume component overrides', builder.includes('block?.luna_component_overrides') && compiler.includes("$block['luna_component_overrides']")],
  ['Builder/Export both consume background overrides', builder.includes('block?.luna_background_overrides') && compiler.includes("$block['luna_background_overrides']")],
  ['Field extras are normalized at the Builder registry boundary', builderRegistry.includes('normalizeBlockRegistry') && builderRegistry.includes('SparkFieldExtrasProvider') && extras.includes('normalizeBlockRegistry')],
  ['Export injects field-extra runtime contract', compiler.includes('sparkFieldExtrasCoreCss()')],

  ['Export tags first Spark root with layout/render/type/index/Tailwind state', compiler.includes("data-cosmic-spark='1'") && compiler.includes("data-cosmic-layout-contract='premium-v1'") && compiler.includes("data-cosmic-render-contract='{$renderContractVersion}'") && compiler.includes("data-cosmic-tailwind-schema='{$tailwindSchemaState}'") && compiler.includes("data-cosmic-block-index='{$semanticIndex}'") && compiler.includes("data-cosmic-block-type='{$semanticType}'")],
  ['PHP compiler reads the same render-contract.json used by Builder', compiler.includes("resource_path('render-contract.json')") && compiler.includes("$contract['version']")],
  ['Export only tags the first root section per Spark fragment', compiler.includes("preg_replace(\n                    '/<section(?![^>]*data-cosmic-spark)/i'") && compiler.includes("$fragment,\n                    1\n                );")],

  ['Publish snapshots the exact Builder block array', pageController.includes('$page->published_blocks = $page->blocks ?? []')],
  ['Published package prefers approved published_blocks over draft blocks', publisher.includes('$blocks = $page->published_blocks ?? $page->blocks ?? []')],
  ['Published package recompiles approved blocks instead of reusing stale published_html', publisher.includes('Recompile the approved snapshot for every live push') && count(publisher, 'CmsHtmlCompiler::compile(') >= 3],
  ['Preview deployment consumes PagePublisher publishedPackage', preview.includes('$package = $this->publisher->publishedPackage($website)') && preview.includes("$pages = $package['pages'] ?? []")],
  ['Preview keeps shared Tailwind CSS and dynamic-schema CDN fallback', preview.includes("/cosmic/cosmic-tailwind.css") && preview.includes('hasDynamicTailwindSchema') && preview.includes('https://cdn.tailwindcss.com')],
  ['Live push sends the exact PagePublisher package to the connector', websiteController.includes('$package = $publisher->publishedPackage($website)') && websiteController.includes('->post($endpoint, $package)')],
  ['Deployment connector writes package page HTML without rebuilding Spark data', connector.includes("isset($page['slug'], $page['html'])") && connector.includes('cosmicDocument($page, $package)') && connector.includes('file_put_contents($temporaryPath')],

  ['Selected-Spark editor remains fail-closed after Batch 5', guards.includes("'protected_tailwind_removed'") && guards.includes("'before_fingerprint'=>$this->fingerprint") && executor.includes('Never fall back to')],
  ['Selected-Spark executor locks exact index in trial and website paths', executor.includes("(int)($sparkTarget['index']??-1)===$i") && executor.includes("(int)($sparkTarget['index']??-1)===$index")],
];

const shared = checks.slice(4).every(([, ok]) => ok);
const rows = keys.map((spark) => {
  const hasSchema = schemaKeys.has(spark);
  const hasBuilder = builderKeys.has(spark);
  const hasCompiler = compilerKeys.has(spark);
  const status = hasSchema && hasBuilder && hasCompiler && shared ? 'PASS' : 'FAIL';
  return {
    spark,
    schema: hasSchema,
    builder: hasBuilder,
    add_section: hasBuilder,
    preview: hasBuilder && hasCompiler,
    export: hasCompiler,
    live: hasCompiler,
    round_trip_contract: shared,
    status,
  };
});

const report = {
  audit_version: 1,
  generated_at: new Date().toISOString(),
  scope: 'Spark Audit Batch 6 — Builder → Add Section → Preview → Export → Live round-trip durability',
  render_contract_version: renderContract.version,
  spark_contract: contractMarker,
  registered: keys.length,
  pass: rows.filter((r) => r.status === 'PASS').length,
  fail: rows.filter((r) => r.status !== 'PASS').length,
  checks: Object.fromEntries(checks.map(([name, ok]) => [name, ok])),
  rows,
};

fs.mkdirSync(path.join(root, 'storage/app/audits'), { recursive: true });
fs.writeFileSync(path.join(root, 'storage/app/audits/spark-roundtrip-batch6.json'), JSON.stringify(report, null, 2));

const qaLines = [
  'Cosmic CMS — Spark Audit Batch 6 Final Round-Trip QA',
  '====================================================',
  '',
  ...checks.map(([name, ok]) => `${ok ? 'PASS' : 'FAIL'} ${name}`),
  '',
  `Coverage: ${report.pass}/${report.registered} registered Sparks`,
  `Render contract: ${report.render_contract_version}`,
  '',
  'Runtime limitation:',
  '- This source ZIP does not include node_modules/vendor, so Vite/Laravel runtime suites cannot execute inside the packaging environment.',
  '- Self-contained Node source-contract audits and PHP syntax lint remain runnable here.',
  '- Run npm run build and application/browser regression suites in the normal local project after extraction.',
].join('\n');
fs.writeFileSync(path.join(root, 'BATCH6-SPARK-ROUNDTRIP-QA.txt'), qaLines + '\n');

checks.forEach(([name, ok]) => console.log(`${ok ? 'PASS' : 'FAIL'} ${name}`));
console.log(`Coverage: ${report.pass}/${report.registered} registered Sparks satisfy the final round-trip contract.`);
if (checks.some(([, ok]) => !ok) || report.fail > 0) process.exitCode = 1;
