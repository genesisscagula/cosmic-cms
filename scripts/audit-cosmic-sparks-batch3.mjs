import fs from 'node:fs';
import path from 'node:path';
const root=process.cwd();
const read=(p)=>fs.readFileSync(path.join(root,p),'utf8');
const modal=read('resources/js/Pages/Dashboard/Components/NewWebsiteModal.jsx');
const controller=read('app/Http/Controllers/WebsiteController.php');
const builder=read('resources/js/Pages/Websites/Builder.jsx');
const addSection=read('resources/js/Pages/Websites/Components/AddSectionModal.jsx');
const modeSupport=read('app/Support/WebsiteCreationMode.php');
const checks=[
 ['Sparks remains a first-class creation mode', modeSupport.includes("public const SPARKS = 'sparks'") && modeSupport.includes('self::SPARKS')],
 ['Create Website card has dedicated Sparks action', modal.includes('Build with Sparks') && modal.includes('Create & Choose Sparks')],
 ['Sparks details explain focused workspace', modal.includes('Sparks workspace') && modal.includes('opens the Sparks section library automatically')],
 ['Sparks mode does not add Luna-specific prompt UI', modal.includes("const sparksMode=mode==='sparks'") && modal.includes("const lunaMode=mode==='luna_ai'")],
 ['Backend seeds a blank Home only for Sparks/custom blank flow', controller.includes('WebsiteCreationMode::SPARKS, WebsiteCreationMode::PAGE_BUILDER') && controller.includes("'title' => 'Home', 'slug' => 'home', 'blocks' => []")],
 ['Sparks creation redirects straight to Builder', controller.includes('WebsiteCreationMode::SPARKS, WebsiteCreationMode::PAGE_BUILDER') && controller.includes("'open_sparks' => 1") && controller.includes("route('pages.builder'")],
 ['Builder derives Sparks mode from saved website settings', builder.includes("const sparksCreationMode = websiteCreationMode === 'sparks'")],
 ['Fresh Sparks Builder auto-opens Add Section once', builder.includes('sparksFirstRunOpenedRef') && builder.includes("params.get('open_sparks') !== '1'") && builder.includes('setIsModalOpen(true)')],
 ['Dedicated Sparks Add Section hides Luna blank card', builder.includes('showLunaBlank={!sparksCreationMode}') && addSection.includes('showLunaBlank = true')],
 ['Dedicated Sparks Add Section hides Build Your Own card', builder.includes('showBuildOwn={!sparksCreationMode}') && addSection.includes('showBuildOwn = true')],
 ['Dedicated Sparks path enables direct install', builder.includes('directInstall={sparksCreationMode}') && addSection.includes('directInstall = false')],
 ['Owned Spark direct install bypasses pre-install customize popup', addSection.includes('if (onCustomize && !directInstall)') && addSection.includes("directInstall ? 'Install Section' : 'Customize Section'")],
 ['Marketplace-owned Spark cards also direct-install in Sparks mode', addSection.includes('if (directInstall) addPickerSparkQuick(spark)')],
 ['Preview action respects direct install', addSection.includes('isMarketplaceContext && !directInstall') && addSection.includes("isMarketplaceContext || directInstall ? 'Install Spark' : 'Customize Section'")],
 ['Normal Spark editing stays available after insertion', builder.includes('onCustomize={beginNewSectionEdit}')],
 ['No new database migration is required', !fs.readdirSync(path.join(root,'database/migrations')).some(f=>/creation.*spark|spark.*creation/i.test(f))],
];
let pass=0;
for(const [label,ok] of checks){console.log(`${ok?'PASS':'FAIL'} ${label}`); if(ok) pass++;}
console.log(`\n${pass}/${checks.length} checks passed.`);
if(pass!==checks.length) process.exit(1);
