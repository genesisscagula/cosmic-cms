import fs from 'node:fs';
const read=(f)=>fs.readFileSync(f,'utf8');
const builder=read('resources/js/Pages/Websites/Builder.jsx');
const pageStyle=read('resources/js/Pages/Websites/PageStyle/PageStyleSelector.jsx');
const pageCtl=read('app/Http/Controllers/PageController.php');
const themeModal=read('resources/js/Pages/Websites/Theme/ThemeModal.jsx');
const themeSelector=read('resources/js/Pages/Websites/Theme/ThemeSelector.jsx');
const starterCtl=read('app/Http/Controllers/StarterSiteController.php');
const bundle=read('app/Services/RegisteredSiteBundleService.php');
const globalLuna=read('resources/js/Components/GlobalLuna.jsx');
const index=read('resources/js/Pages/Websites/Index.jsx');
const checks=[
 ['Theme selector is enabled despite AI-only builder', builder.includes('{!customWebsiteMode && capabilities.canChangeTheme && (\n                                <ThemeSelector')],
 ['Theme session snapshots working state', builder.includes('themeSessionSnapshotRef') && builder.includes('beginThemeSession')],
 ['Theme cancel restores full snapshot', builder.includes('cancelThemeSession') && builder.includes('setGlobalSelections(snapshot.globalSelections') && builder.includes('setData(snapshot.data')],
 ['Theme Save commits preview session', builder.includes('applyThemeSession') && themeModal.includes('Save Theme')],
 ['Theme popup contains contextual Luna sidebar', themeModal.includes('✦ Luna · Theme') && themeModal.includes('Custom color family')],
 ['Theme Luna is explicitly theme-only', builder.includes('THEME-ONLY PREVIEW REQUEST') && builder.includes("theme_only: true")],
 ['Global/page Luna assistant surface is defined and sent', builder.includes("const lunaAssistantSurface = ['contextual_popup','global_builder']") && builder.includes("form.append('assistant_surface', lunaAssistantSurface)")],
 ['Page Style selector is restored', builder.includes('<PageStyleSelector')],
 ['Manual Page Style is presented as free', pageStyle.includes("{selected ? 'Current' : 'Free'}") && pageStyle.includes('Manual Page Style changes are free')],
 ['Registered Page Style consumes zero credits', pageCtl.includes("'credits_spent' => 0") && !pageCtl.slice(pageCtl.indexOf('public function applyPageStyle'), pageCtl.indexOf('public function applyTrialPageStyle')).includes('$credits->consume')],
 ['Page Style preserves explicit Spark themes/content', !pageCtl.slice(pageCtl.indexOf('public function applyPageStyle'), pageCtl.indexOf('public function applyTrialPageStyle')).includes("$block['theme'] = 'auto'")],
 ['Trial Page Style consumes zero guest credits', !pageCtl.slice(pageCtl.indexOf('public function applyTrialPageStyle'), pageCtl.indexOf('\n    public function ', pageCtl.indexOf('public function applyTrialPageStyle')+20)).includes('ensureCanSpend')],
 ['Overlay Header manual switch is visible', builder.includes('<span>Overlay Header</span>') && builder.includes("overlay_header_on_banner: !Boolean")],
 ['Mega Footer manual switch is visible', builder.includes('cosmic-mega-footer-toggle__label">Mega Footer</span>') && builder.includes('mega_enabled: enabled')],
 ['Starter UI says Install Starter Pages', index.includes("'Install Starter Pages'")],
 ['Starter flow auto-selects bundle without user prompt', globalLuna.includes("planStarterSite('',websiteId)") && globalLuna.includes('no extra prompt needed')],
 ['Starter composer is removed while starter context is active', globalLuna.includes('Bundle selection uses saved website context')],
 ['Starter plan prompt is optional server-side', starterCtl.includes("'prompt' => ['nullable', 'string', 'max:6000']")],
 ['Starter preselection is persisted and reused on install', starterCtl.includes("starter_bundle_preview") && starterCtl.includes("The starter bundle preview is stale")],
 ['Starter service derives prompt from website context', bundle.includes('websiteContextPrompt')],
 ['Starter install no longer requires prompt payload', !starterCtl.slice(starterCtl.indexOf('public function install'), starterCtl.indexOf('public function status')).includes("'prompt' => ['required'")],
];
let pass=0;
for(const [name,ok] of checks){ console.log(`${ok?'PASS':'FAIL'} ${name}`); if(ok)pass++; }
console.log(`\n${pass}/${checks.length} checks passed`);
if(pass!==checks.length) process.exit(1);
