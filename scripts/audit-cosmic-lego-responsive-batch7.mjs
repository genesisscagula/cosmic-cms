import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const read = (p) => fs.readFileSync(path.join(root, p), 'utf8');
const builder = read('resources/js/Pages/Websites/Builder.jsx');
const block = read('resources/js/Pages/Websites/Blocks/General/LunaCustomSectionBlock.jsx');
const contract = read('resources/js/Pages/Websites/Blocks/Shared/aiFlexStructureContract.js');
const appCss = read('resources/css/app.css');
const exportCss = read('resources/css/cosmic-render-contract.css');
const compiler = read('app/Helpers/CmsHtmlCompiler.php');
const checks = [
['device tabs', block.includes('cosmic-lego-device-tabs') && block.includes("setDevice('tablet')") && block.includes("setDevice('mobile')")],
['responsive mode metadata', contract.includes("_cosmic_responsive_mode: 'auto'")],
['responsive width overrides', compiler.includes('--af-tablet-width') && compiler.includes('--af-mobile-width')],
['responsive font overrides', compiler.includes('--af-tablet-font-size') && compiler.includes('--af-mobile-font-size')],
['responsive text alignment', compiler.includes('--af-tablet-text-align') && compiler.includes('--af-mobile-text-align')],
['responsive self alignment', compiler.includes('--af-tablet-self-align') && compiler.includes('--af-mobile-self-align')],
['optional device visibility', block.includes('hide_tablet') && block.includes('hide_mobile') && compiler.includes('--af-tablet-display:none') && compiler.includes('--af-mobile-display:none')],
['smart row vars', block.includes('--cosmic-lego-tablet-columns') && block.includes('--cosmic-lego-mobile-columns')],
['compiler smart row vars', compiler.includes('--cosmic-lego-tablet-columns') && compiler.includes('--cosmic-lego-mobile-columns')],
['builder tablet row CSS', appCss.includes('repeat(var(--cosmic-lego-tablet-columns,1)')],
['builder mobile row CSS', appCss.includes('repeat(var(--cosmic-lego-mobile-columns,1)')],
['export tablet row CSS', exportCss.includes('repeat(var(--cosmic-lego-tablet-columns,1)')],
['export mobile row CSS', exportCss.includes('repeat(var(--cosmic-lego-mobile-columns,1)')],
['reset device override', block.includes('const resetDevice=')],
['auto responsive reset', block.includes("_cosmic_responsive_mode:'auto'")],
['builder responsive strategy', builder.includes("responsive_strategy: 'smart_auto'") && builder.includes('responsive_version: 1')],
['builder design origin', builder.includes("design_origin: marketplaceWebsite ? 'marketplace' : 'studio'")],
['marketplace kit inheritance', builder.includes('inherit_marketplace_design_kit: marketplaceWebsite')],
['Luna builder context', builder.includes('builderDesignContextForLuna')],
['Luna marketplace context', builder.includes('marketplaceContextForLuna')],
['Luna Lego target context', builder.includes('requestElementContext.lego_builder')],
['Marketplace preservation directive', builder.includes('Preserve the installed customer-owned Marketplace design language by default')],
['export/live parity marker', exportCss.includes('Cosmic Lego Builder Batch 7') && compiler.includes('cosmic-lego-row-runtime')],
];
let pass=0;for(const [name,ok] of checks){console.log(`${ok?'PASS':'FAIL'} ${name}`);if(ok)pass++;}console.log(`\n${pass}/${checks.length} assertions passed.`);if(pass!==checks.length)process.exit(1);
