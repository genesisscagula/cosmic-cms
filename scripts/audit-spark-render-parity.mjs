import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const blockRoot = path.join(root, 'resources/js/Pages/Websites/Blocks');
const compilerPath = path.join(root, 'app/Helpers/CmsHtmlCompiler.php');
const previewPath = path.join(root, 'app/Services/PreviewDeploymentService.php');
const livePath = path.join(root, 'app/Services/DeploymentConnectorArchive.php');
const appBladePath = path.join(root, 'resources/views/app.blade.php');
const contractPath = path.join(root, 'resources/render-contract.json');

const read = (file) => fs.existsSync(file) ? fs.readFileSync(file, 'utf8') : '';
const walk = (dir) => fs.readdirSync(dir, {withFileTypes:true}).flatMap((entry) => {
  const full = path.join(dir, entry.name);
  return entry.isDirectory() ? walk(full) : [full];
});

const files = walk(blockRoot).filter((f) => /\.(jsx|js)$/.test(f));
let sharedBindings = 0;
let scopedBindings = 0;
let autoAliases = 0;
let unbridgedClassName = [];
const approvedBypass = new Set([
  'Shared/EditableImage.jsx','Shared/EditableText.jsx','Shared/EditableButton.jsx',
  'General/LunaCustomSectionBlock.jsx','Hero/HeroSliderFadeBlock.jsx',
  'Blog/BlogHubBlock.jsx','Content/StructuredContentBlocks.jsx','Contact/ContactFormModernBlock.jsx',
]);

for (const file of files) {
  const source = read(file);
  const rel = path.relative(blockRoot, file).replaceAll('\\','/');
  sharedBindings += (source.match(/sparkTw\(/g) || []).length;
  scopedBindings += (source.match(/sparkTw(?:Item|Path)\(/g) || []).length;
  autoAliases += (source.match(/sparkTw(?:Item|Path)?\([^\n]*?["'`]auto_\d+/g) || []).length;
  source.split(/\r?\n/).forEach((line, idx) => {
    if (!line.includes('className=')) return;
    if (/function\s+\w+\([^)]*className\s*=/.test(line)) return;
    if (/sparkTw(?:Item|Path)?\(/.test(line)) return;
    if (approvedBypass.has(rel)) return;
    // Dynamic runtime state composition is allowed only when at least one side is schema-backed.
    if (line.includes('sparkTw') || line.includes('cosmic-')) return;
    unbridgedClassName.push(`${rel}:${idx+1}`);
  });
}

const compiler = read(compilerPath);
const preview = read(previewPath);
const live = read(livePath);
const blade = read(appBladePath);
const pkg = JSON.parse(read(path.join(root,'package.json')) || '{}');
const contract = JSON.parse(read(contractPath) || '{}');

const checks = {
  compiler_shared_resolver: compiler.includes('SparkTailwindSchemaContract') && compiler.includes('sparkTw('),
  compiler_scoped_resolver: compiler.includes('resolveScopedStyle') && compiler.includes('sparkTwPath('),
  compiler_schema_marker: compiler.includes('data-cosmic-tailwind-schema'),
  builder_dynamic_tailwind_runtime: blade.includes("routeIs('pages.builder')") && blade.includes('cdn.tailwindcss.com'),
  preview_compiled_baseline: preview.includes("/cosmic/cosmic-tailwind.css"),
  preview_dynamic_tailwind_runtime: preview.includes('hasDynamicTailwindSchema') && preview.includes('cdn.tailwindcss.com'),
  live_compiled_baseline: live.includes("/cosmic/cosmic-tailwind.css"),
  live_dynamic_tailwind_runtime: live.includes('hasDynamicTailwindSchema') && live.includes('cdn.tailwindcss.com'),
  export_tailwind_config: fs.existsSync(path.join(root,'tailwind.export.config.cjs')),
  app_tailwind_config: fs.existsSync(path.join(root,'tailwind.config.js')),
  render_contract_present: typeof contract.version === 'string' && contract.version.length > 0,
  package_audit_command: String(pkg?.scripts?.['audit:sparks'] || '').includes('audit-spark-render-parity.mjs'),
};

console.log('Cosmic Spark render parity audit');
console.log(`Renderer source files: ${files.length}`);
console.log(`Shared sparkTw bindings: ${sharedBindings}`);
console.log(`Scoped item/path bindings: ${scopedBindings}`);
console.log(`Remaining auto_* aliases: ${autoAliases}`);
console.log(`Unbridged className candidates: ${unbridgedClassName.length}`);
for (const [name, ok] of Object.entries(checks)) console.log(`${ok ? 'PASS' : 'FAIL'} ${name}`);
if (unbridgedClassName.length) {
  console.log('\nFirst unbridged candidates (Batch 11 torture-QA review list):');
  unbridgedClassName.slice(0,30).forEach((row) => console.log(`- ${row}`));
}

const hardFail = Object.values(checks).some((ok) => !ok);
if (hardFail) process.exit(1);
