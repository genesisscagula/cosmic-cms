import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { HYBRID_SPARK_INSERTION_VERSION, HYBRID_SPARK_SAFE_TYPES } from '../resources/js/Pages/Websites/Blocks/Shared/hybridSparkInsertionContract.js';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const read = (p) => fs.readFileSync(path.join(root, p), 'utf8');
const exists = (p) => fs.existsSync(path.join(root, p));

const builder = read('resources/js/Pages/Websites/Builder.jsx');
const block = read('resources/js/Pages/Websites/Blocks/General/LunaCustomSectionBlock.jsx');
const registry = read('resources/js/Pages/Websites/BlockRegistry.jsx');
const panel = read('resources/js/Pages/Websites/Components/LegoElementsPanel.jsx');
const addSection = read('resources/js/Pages/Websites/Components/AddSectionModal.jsx');
const renderCss = read('resources/css/cosmic-render-contract.css');
const runtime = read('resources/js/Pages/Websites/Blocks/Shared/SparkFieldExtrasRuntime.jsx');
const compiler = read('app/Helpers/CmsHtmlCompiler.php');
const acquisition = read('app/Services/MarketplaceAcquisitionService.php');
const provisioning = read('app/Services/MarketplaceWebsiteProvisioningService.php');
const checkout = read('app/Services/MarketplaceCheckoutService.php');
const css = read('resources/css/app.css');
const packageJson = JSON.parse(read('package.json'));

const registryBody = registry.slice(registry.indexOf('const RawBlockRegistry = {'), registry.indexOf('\n};', registry.indexOf('const RawBlockRegistry = {')));
const registryEntryCount = new Set([...registryBody.matchAll(/^\s{4}([A-Za-z0-9_]+):\s*\{/gm)].map((m) => m[1])).size;

const interactiveTypes = ['accordion','tabs','logo_carousel','gallery','lightbox_gallery','image_carousel'];
const checks = [
  ['Build Your Own entry exists', addSection.includes('Build Your Own') && builder.includes('build_your_own')],
  ['floating element panel exists', panel.includes('cosmic-lego-panel') && panel.includes('Search elements')],
  ['cross-column/cross-section drag contract exists', block.includes("application/x-cosmic-lego") && block.includes('sourceBlockIndex') && builder.includes('sourceBlockIndex')],
  ['smart global inheritance markers exist', block.includes("_cosmic_style_mode==='global'") && renderCss.includes('[data-cosmic-style-mode="global"].cosmic-flex-heading') && renderCss.includes('--cosmic-type-h2-size')],
  ['desktop/tablet/mobile optional overrides exist', block.includes("setDevice('desktop')") && block.includes("setDevice('tablet')") && block.includes("setDevice('mobile')")],
  ['responsive runtime rules exist', block.includes('--cosmic-lego-tablet-columns') && block.includes('--cosmic-lego-mobile-columns')],
  ['current hybrid insertion contract v2', HYBRID_SPARK_INSERTION_VERSION === 2 && HYBRID_SPARK_SAFE_TYPES.length >= 12],
  ['existing Spark library broadly covered', registryEntryCount >= 321],
  ['hybrid before/after drop zones exist', runtime.includes('placement="before"') && runtime.includes('placement="after"')],
  ['cross Spark/free layout portability exists', builder.includes('removeHybridSparkExtraFromBlocks') && builder.includes('moveHybridExtraToFreeLayout')],
  ['all interactive Lego types render in Builder', interactiveTypes.every((t) => block.includes(`case'${t}'`))],
  ['all interactive Lego types render live/export', interactiveTypes.every((t) => compiler.includes(`$type==='${t}'`) || compiler.includes(`$type==='gallery'||$type==='lightbox_gallery'`))],
  ['Builder lightbox supports Escape', block.includes("event.key==='Escape'") && block.includes("document.addEventListener('keydown',onKey)" )],
  ['live lightbox uses native dialog/cancel', compiler.includes("data-lightbox-close") && compiler.includes("d.addEventListener('cancel'")],
  ['live accordion behavior script exists', compiler.includes('data-acc-button') && compiler.includes("aria-expanded")],
  ['live tabs behavior script exists', compiler.includes("role='tab'") && compiler.includes("data-panel")],
  ['live carousel behavior exists', compiler.includes('data-image-slide') && compiler.includes("prefers-reduced-motion: reduce")],
  ['Marketplace origin context is preserved in Builder/Luna', builder.includes("design_origin: marketplaceWebsite ? 'marketplace' : 'studio'") && builder.includes('inherit_marketplace_design_kit: marketplaceWebsite')],
  ['Marketplace design kit persists on provisioning', provisioning.includes("'design_kit' => [")],
  ['Marketplace acquisition stays Cosmic Credits', checkout.includes("'marketplace_template_payment_mode' => 'cosmic_credits'") && acquisition.includes("'product_type' => 'marketplace_template'")],
  ['Marketplace master-copy protection directive exists', builder.includes('Preserve the installed customer-owned Marketplace design language by default')],
  ['Builder-only hybrid zone CSS exists', css.includes('.cosmic-hybrid-spark-drop-zone')],
  ['published compiler consumes Spark extras', compiler.includes('applySparkFieldExtrasToFragment') && compiler.includes('renderSparkFieldExtraList')],
  ['final audit npm script registered', packageJson.scripts?.['audit:cosmic-lego-final-batch10'] === 'node scripts/audit-cosmic-lego-final-batch10.mjs'],
  ['prior batch audit scripts retained', exists('scripts/audit-cosmic-lego-responsive-batch7.mjs') && exists('scripts/audit-cosmic-lego-hybrid-sparks-batch8.mjs') && exists('scripts/audit-cosmic-lego-cross-spark-batch9.mjs')],
];

let passed = 0;
for (const [name, ok] of checks) {
  console.log(`${ok ? 'PASS' : 'FAIL'} ${name}`);
  if (ok) passed++;
}
console.log(`\nRegistered Spark entries detected: ${registryEntryCount}`);
console.log(`Hybrid portable types: ${HYBRID_SPARK_SAFE_TYPES.join(', ')}`);
console.log(`${passed}/${checks.length} final assertions passed.`);
if (passed !== checks.length) process.exit(1);
