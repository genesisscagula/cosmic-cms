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
const builderSource = read('resources/js/Pages/Websites/Builder.jsx');
const contract = JSON.parse(read('resources/render-contract.json'));

const runtime = matches(runtimeSource, /^\s*([A-Za-z0-9_]+):\s*\{\s*component:/gm);
const marketplace = matches(marketplaceSource, /^\s*type:\s*["']([A-Za-z0-9_]+)["']/gm);
const compiler = matches(compilerSource, /case\s+["']([A-Za-z0-9_]+)["']/g);

const difference = (left, right) => left.filter((value) => !right.includes(value));
const failures = [];
const reportDifference = (label, values) => {
    if (values.length === 0) return;
    failures.push(`${label}: ${values.join(', ')}`);
};

reportDifference('Runtime Sparks missing from export compiler', difference(runtime, compiler));
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

console.log(`Spark parity audit: ${runtime.length} runtime, ${marketplace.length} marketplace, ${compiler.length} export cases.`);
console.log(`Shared render contract: ${contract.version}`);

if (failures.length > 0) {
    for (const failure of failures) console.error(`FAIL: ${failure}`);
    process.exitCode = 1;
} else {
    console.log('PASS: Builder, marketplace, export and live registries are structurally aligned.');
}
