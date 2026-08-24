import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const root = process.cwd();
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const unique = (values) => [...new Set(values)].sort();
const matches = (source, pattern) => unique([...source.matchAll(pattern)].map((match) => match[1]));

const runtimeSource = read('resources/js/Pages/Websites/BlockRegistry.jsx');
const marketplaceSource = read('resources/js/Pages/Websites/Components/SparkRegistry.jsx');
const compilerSource = read('app/Helpers/CmsHtmlCompiler.php');
const schemaManagerSource = read('app/AI/Schemas/SchemaManager.php');
const builderSource = read('resources/js/Pages/Websites/Builder.jsx');
const featureComparisonSource = read('resources/js/Pages/Websites/Blocks/Services/ServicesFeatureComparisonBlock.jsx');
const motionHeroSource = read('resources/js/Pages/Websites/Blocks/Hero/AnimatedHeroPremiumPatch2Blocks.jsx');
const themeSource = read('resources/js/theme/Theme.js');
const contract = JSON.parse(read('resources/render-contract.json'));

const internalRuntimeSparks = ['luna_custom_section'];
const runtimeAll = matches(runtimeSource, /^\s*([A-Za-z0-9_]+):\s*\{\s*component:/gm);
const runtime = runtimeAll.filter((value) => !internalRuntimeSparks.includes(value));
const registered = matches(schemaManagerSource, /['"]([A-Za-z0-9_]+)['"]\s*=>\s*['"][A-Za-z0-9_]+Schema['"]/g);
const marketplace = matches(marketplaceSource, /^\s*type:\s*["']([A-Za-z0-9_]+)["']/gm);
const compiler = matches(compilerSource, /case\s+["']([A-Za-z0-9_]+)["']/g);

const difference = (left, right) => left.filter((value) => !right.includes(value));
const failures = [];
const reportDifference = (label, values) => {
    if (values.length === 0) return;
    failures.push(`${label}: ${values.join(', ')}`);
};

reportDifference('Registered Sparks missing from runtime registry', difference(registered, runtime));
reportDifference('Runtime public Sparks missing from SchemaManager', difference(runtime, registered));
reportDifference('Registered Sparks missing from export compiler', difference(registered, compiler));
reportDifference('Marketplace Sparks missing from runtime registry', difference(marketplace, runtime));
reportDifference('Marketplace Sparks missing from export compiler', difference(marketplace, compiler));

if (!builderSource.includes('data-cosmic-render-contract={renderContract.version}')) {
    failures.push('Builder is not reading the centralized render contract');
}
if (!compilerSource.includes('self::renderContractVersion()')) {
    failures.push('Export compiler is not reading the centralized render contract');
}
if (!compilerSource.includes("$block['resolvedTheme'] = $blockTheme;")) {
    failures.push('Export compiler is not passing the resolved page theme into Spark renderers');
}
if (/b3-spotlight[^\n]+grid-row:1 \/ span 3/.test(compilerSource)) {
    failures.push('Batch 3 featured-card grid differs from the Builder span-2 contract');
}
if (!compilerSource.includes("'one' => ['Projects', '3', 'Unlimited', 'Unlimited']")
    || !compilerSource.includes("'six' => ['Best for', 'Individuals', 'Growing teams', 'Agencies & scale']")) {
    failures.push('Pricing comparison export defaults differ from the Builder schema');
}
if (!compilerSource.includes("data-cosmic-contrast-surface='brand'")
    || !featureComparisonSource.includes('data-cosmic-contrast-surface={option.featured && !isPrimary ? "brand" : undefined}')) {
    failures.push('Feature comparison nested brand contrast is not shared by Builder and export');
}
if (compilerSource.includes('.cosmic-motion2-video{position:absolute;inset:0;overflow:hidden;background:#0f172a}')
    || !compilerSource.includes('.cosmic-motion2-video video{position:absolute;inset:0;display:block;width:100%;height:100%;object-fit:cover}')) {
    failures.push('Motion video export still permits theme leakage or uncovered media space');
}
if (motionHeroSource.includes('bg-slate-950 text-white') || motionHeroSource.includes('overflow-hidden bg-slate-900')) {
    failures.push('Motion hero Builder still hard-codes a slate background instead of the active theme');
}
if (/bg:\s*getSectionBackgroundClass\(/.test(themeSource)) {
    failures.push('Effective Builder themes still inject gradients that export/live do not render');
}

console.log(`Spark parity audit: ${registered.length} registered, ${runtime.length} public runtime, ${marketplace.length} marketplace, ${compiler.length} export cases, ${internalRuntimeSparks.length} internal runtime.`);
console.log(`Shared render contract: ${contract.version}`);

if (process.argv.includes('--write')) {
    const manifest = {
        version: contract.version,
        builder_registry_count: runtime.length,
        live_compiler_coverage_count: registered.filter((value) => compiler.includes(value)).length,
        unsupported_registered_sparks: difference(registered, compiler),
        internal_runtime_sparks: internalRuntimeSparks,
        registered_sparks: registered,
        note: 'Static renderer coverage is necessary but not sufficient for pixel/runtime parity. Representative Sparks must still be compared in Builder and published Live output.',
    };
    fs.writeFileSync(path.join(root, 'resources/luna/render_parity.json'), `${JSON.stringify(manifest, null, 2)}\n`);
    console.log('Updated resources/luna/render_parity.json from active registries.');
}

if (failures.length > 0) {
    for (const failure of failures) console.error(`FAIL: ${failure}`);
    process.exitCode = 1;
} else {
    console.log('PASS: Builder, marketplace, export and live registries are structurally aligned.');
}
