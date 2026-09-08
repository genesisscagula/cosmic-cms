import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const modal = read('resources/js/Pages/Dashboard/Components/NewWebsiteModal.jsx');
const builder = read('resources/js/Pages/Websites/Builder.jsx');
const controller = read('app/Http/Controllers/WebsiteController.php');
const mode = read('app/Support/WebsiteCreationMode.php');

const checks = [
  ['canonical page_builder mode remains defined', mode.includes("public const PAGE_BUILDER = 'page_builder';")],
  ['dashboard action names focused handoff', modal.includes('Create & Open Page Builder')],
  ['page builder details callout exists', modal.includes('Page Builder workspace') && modal.includes('draggable rows, columns')],
  ['controller accepts page builder creation mode', controller.includes('WebsiteCreationMode::PAGE_BUILDER')],
  ['controller closure captures creation mode safely', controller.includes('$myBrandThemes, $creationMode)')],
  ['page builder receives a clean Home page', controller.includes('WebsiteCreationMode::SPARKS, WebsiteCreationMode::PAGE_BUILDER') && controller.includes("'title' => 'Home'")],
  ['page builder redirects directly to Builder', controller.includes("'open_page_builder' => 1") && controller.includes("route('pages.builder'" )],
  ['Builder recognizes dedicated page builder mode', builder.includes("const pageBuilderCreationMode = websiteCreationMode === 'page_builder';")],
  ['first-run handoff opens page builder once', builder.includes('pageBuilderFirstRunOpenedRef') && builder.includes("params.get('open_page_builder')")],
  ['first-run creates Build Your Own section', builder.includes('window.setTimeout(() => addBuildYourOwnSection(), 160)')],
  ['page builder Add Section bypasses Spark picker', builder.includes('pageBuilderCreationMode ? addPageBuilderSection()') && builder.includes("'Add Builder Section'")],
  ['templates hidden in page builder create menu', builder.includes('const modeAllowsPageTemplates = !explicitCreationMode;')],
  ['AI page generation hidden in page builder create menu', builder.includes('const modeAllowsGeneratePage = !sparksCreationMode && !pageBuilderCreationMode && !marketplaceCreationMode;')],
  ['Sparks submenu hidden in page builder create menu', builder.includes('const modeAllowsSparkLibrary = !explicitCreationMode || sparksCreationMode;')],
  ['AddSectionModal excluded from dedicated page-builder start flow', builder.includes('!pageBuilderCreationMode && (capabilities.canGenerateAi || capabilities.canManageBlocks || trialMode)')],
  ['empty-state Page Builder action starts Lego section directly', builder.includes("pageBuilderCreationMode ? 'Start Page Builder' : 'Add Section'")],
  ['builder terminology says Sections for page builder', builder.includes("{data.blocks.length} {sparksCreationMode ? 'Sparks' : 'Sections'}")],
];

let pass = 0;
for (const [label, ok] of checks) {
  console.log(`${ok ? 'PASS' : 'FAIL'} ${label}`);
  if (ok) pass++;
}
console.log(`\n${pass}/${checks.length} checks passed.`);
if (pass !== checks.length) process.exit(1);
