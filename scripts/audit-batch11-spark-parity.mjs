import fs from 'node:fs';

const read = (p) => fs.readFileSync(p, 'utf8');
const registry = read('resources/js/Pages/Websites/BlockRegistry.jsx');
const builder = read('resources/js/Pages/Websites/Builder.jsx');
const css = read('resources/css/app.css');
const compiler = read('app/Helpers/CmsHtmlCompiler.php');
const modal = read('resources/js/Pages/Websites/Components/GlobalStylingModal.jsx');
const preview = read('app/Services/PreviewDeploymentService.php');

const raw = registry.match(/const RawBlockRegistry = \{([\s\S]*?)\n\};\n\nconst NormalizedBlockRegistry/);
if (!raw) throw new Error('RawBlockRegistry block not found');
const keys = [...raw[1].matchAll(/^\s{4}([A-Za-z0-9_]+):\s*\{/gm)].map(m => m[1]);
const unique = new Set(keys);

const checks = [
  ['active registry is broad (>=321)', unique.size >= 321, `${unique.size} registered Spark types`],
  ['registry normalization applies to every entry', registry.includes('Object.entries(NormalizedBlockRegistry).map') && registry.includes('wrapSparkComponentWithFieldExtras')],
  ['Builder wraps registered Sparks in render shell', builder.includes('data-cosmic-render-shell="1"') && builder.includes('data-cosmic-design-system="1"')],
  ['Builder exposes layout mode for safe compatibility', builder.includes('data-cosmic-layout-mode={/fullscreen|cinematic/.test(blockType)')],
  ['desktop/tablet/mobile section tokens exist', ['--cosmic-section-py','--cosmic-section-py-tablet','--cosmic-section-py-mobile','--cosmic-section-px','--cosmic-section-px-tablet','--cosmic-section-px-mobile'].every(t => css.includes(t) && compiler.includes(t))],
  ['all four container roles are represented', ['container_narrow','container_content','container_default','container_wide'].every(k => modal.includes(k)) && ['--cosmic-container-narrow','--cosmic-container-content','--cosmic-container-default','--cosmic-container-wide'].every(t => css.includes(t) && compiler.includes(t))],
  ['wide legacy container compatibility exists', css.includes('.max-w-screen-2xl') && compiler.includes('.max-w-screen-2xl')],
  ['legacy grid gap parity exists', css.includes('var(--cosmic-local-grid-gap,var(--cosmic-space-grid') && compiler.includes('var(--cosmic-local-grid-gap,var(--cosmic-space-grid')],
  ['legacy card padding parity exists', css.includes('var(--cosmic-local-card-padding,var(--cosmic-card-padding') && compiler.includes('var(--cosmic-local-card-padding,var(--cosmic-card-padding')],
  ['legacy card radius parity exists', css.includes('var(--cosmic-local-card-radius,var(--cosmic-radius-card') && compiler.includes('var(--cosmic-local-card-radius,var(--cosmic-radius-card')],
  ['legacy card shadow parity exists', css.includes('var(--cosmic-local-card-shadow,var(--cosmic-card-shadow') && compiler.includes('var(--cosmic-local-card-shadow,var(--cosmic-card-shadow')],
  ['button responsive height/padding parity exists', ['--cosmic-button-height-tablet','--cosmic-button-height-mobile','--cosmic-button-px-tablet','--cosmic-button-px-mobile'].every(t => css.includes(t) && compiler.includes(t))],
  ['button hover shift honors local override', css.includes('--cosmic-local-button-hover-shift') && compiler.includes('--cosmic-local-button-hover-shift')],
  ['image radius/object-fit parity exists', css.includes('--cosmic-local-image-radius') && css.includes('--cosmic-local-media-object-fit') && compiler.includes('--cosmic-local-image-radius') && compiler.includes('--cosmic-local-media-object-fit')],
  ['form/input radius parity exists', css.includes('--cosmic-local-input-radius') && compiler.includes('--cosmic-local-input-radius')],
  ['intentional per-Spark ownership escape hatches exist', ['data-cosmic-preserve-button','data-cosmic-preserve-padding','data-cosmic-preserve-radius','data-cosmic-preserve-shadow'].every(x => css.includes(x) && compiler.includes(x))],
  ['Tailwind schema-backed slots remain excluded from generic overrides', css.includes('not([class*="cosmic-tw-slot--"])') && compiler.includes("not([class*='cosmic-tw-slot--'])")],
  ['preview loads expanded premium font bundle', ['plus-jakarta-sans','dm-sans','playfair-display','lora'].every(f => preview.includes(f))],
  ['published compiler emits global section settings', compiler.includes('data-cosmic-section-wrapper-settings') && compiler.includes('$globalSectionVars')],
  ['published compiler emits global component settings', compiler.includes('$globalComponentMap') && compiler.includes('data-cosmic-button-default')],
];

let failed = 0;
for (const [name, ok, detail=''] of checks) {
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? ` — ${detail}` : ''}`);
  if (!ok) failed++;
}
console.log(`\nBatch 11 parity audit: ${checks.length - failed}/${checks.length} PASS; registry=${unique.size}`);
if (failed) process.exit(1);
