import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const mode = read('app/Support/WebsiteCreationMode.php');
const modal = read('resources/js/Pages/Dashboard/Components/NewWebsiteModal.jsx');
const controller = read('app/Http/Controllers/WebsiteController.php');
const builder = read('resources/js/Pages/Websites/Builder.jsx');
const index = read('resources/js/Pages/Websites/Index.jsx');
const provisioning = read('app/Services/MarketplaceWebsiteProvisioningService.php');

const checks = [
  ['four canonical modes remain defined', ['luna_ai','sparks','page_builder','marketplace'].every((value)=>mode.includes(`'${value}'`))],
  ['canonical mode labels exist', mode.includes("self::LUNA_AI => 'Luna AI'") && mode.includes("self::PAGE_BUILDER => 'Page Builder'")],
  ['create chooser still exposes exactly four named paths', ['Build with Luna AI','Build with Sparks','Build with Page Builder','Choose from Marketplace'].every((label)=>modal.includes(label))],
  ['dashboard modes still persist on normal Website settings', controller.includes("'creation' => [") && controller.includes("'mode' => $creationMode")],
  ['Sparks and Page Builder still seed one clean Home page', controller.includes('WebsiteCreationMode::SPARKS, WebsiteCreationMode::PAGE_BUILDER')],
  ['Marketplace provisioning uses canonical creation constant', provisioning.includes('WebsiteCreationMode::MARKETPLACE')],
  ['Builder resolves all four explicit modes from saved settings', builder.includes("const lunaCreationMode = websiteCreationMode === 'luna_ai';") && builder.includes("const sparksCreationMode = websiteCreationMode === 'sparks';") && builder.includes("const pageBuilderCreationMode = websiteCreationMode === 'page_builder';") && builder.includes("const marketplaceCreationMode = websiteCreationMode === 'marketplace' || marketplaceWebsite;")],
  ['Builder chrome shows a mode origin badge for explicit modes', builder.includes('data-cosmic-creation-origin') && builder.includes('{creationModeLabel}')],
  ['explicit Page Builder blocks page templates', builder.includes('const modeAllowsPageTemplates = !explicitCreationMode;')],
  ['Sparks mode owns the Spark library create surface', builder.includes('const modeAllowsSparkLibrary = !explicitCreationMode || sparksCreationMode;')],
  ['AI page generation is excluded from Sparks/Page Builder/Marketplace modes', builder.includes('const modeAllowsGeneratePage = !sparksCreationMode && !pageBuilderCreationMode && !marketplaceCreationMode;')],
  ['Page Builder per-section insertion no longer opens Sparks modal', builder.includes('if(pageBuilderCreationMode){addPageBuilderSection();return;}')],
  ['Page Builder footer insertion no longer opens Sparks modal', builder.includes("pageBuilderCreationMode ? 'Add Builder section above footer' : 'Add Spark above footer'")],
  ['mode-aware empty states explain the chosen starting path', builder.includes('Your Page Builder canvas is ready') && builder.includes('Your Sparks canvas is ready') && builder.includes('This page is ready for Luna')],
  ['Builder count terminology uses Sparks only for Sparks mode', builder.includes("{data.blocks.length} {sparksCreationMode ? 'Sparks' : 'Sections'}")],
  ['Sparks first-run URL handoff is consumed after opening', builder.includes("consumeCreationHandoff('open_sparks', 'creation_mode')")],
  ['Page Builder first-run URL handoff is consumed after opening', builder.includes("consumeCreationHandoff('open_page_builder', 'creation_mode')")],
  ['Luna first-run URL handoff is consumed after opening', index.includes("params.delete('creation_mode')") && index.includes("cosmic:luna-open-starter-site")],
  ['Marketplace onboarding remains preserved', builder.includes('Marketplace website installed') && builder.includes('Personalize with Luna')],
  ['Marketplace theme switching remains protected', builder.includes('capabilities.canChangeTheme && !marketplaceWebsite')],
  ['no new database migration dependency introduced', !fs.readdirSync(path.join(root, 'database', 'migrations')).some((name)=>/creation[_-]?mode/i.test(name))],
];

let pass = 0;
for (const [label, ok] of checks) {
  console.log(`${ok ? 'PASS' : 'FAIL'} ${label}`);
  if (ok) pass++;
}
console.log(`\n${pass}/${checks.length} checks passed.`);
if (pass !== checks.length) process.exit(1);
