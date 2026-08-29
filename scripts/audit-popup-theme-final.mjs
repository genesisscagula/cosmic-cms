import fs from 'node:fs';
const files = {
  builder: fs.readFileSync('resources/js/Pages/Websites/Builder.jsx','utf8'),
  selector: fs.readFileSync('resources/js/Pages/Websites/Theme/ThemeSelector.jsx','utf8'),
  modal: fs.readFileSync('resources/js/Pages/Websites/Theme/ThemeModal.jsx','utf8'),
};
const checks = [
 ['Theme selector owns popup-local draft', files.selector.includes('draftTheme') && files.selector.includes('draftGeneratedTheme')],
 ['Preset selection does not call Builder onChange immediately', files.selector.includes('onSelect={(theme) => { setDraftGeneratedTheme(null); setDraftTheme(theme); }}')],
 ['Save Theme is commit bridge', files.selector.includes('onApplySession?.({ selectedTheme: draftTheme, generatedTheme: draftGeneratedTheme })')],
 ['Theme Luna response is returned to popup', files.builder.includes('return response;') && files.builder.includes("targetScope: 'theme'")],
 ['Theme generated candidate is local', files.modal.includes('generatedThemes') && files.modal.includes('brand_color_family')],
 ['Generated theme card lives in Luna history', files.modal.includes('message.themeKey') && files.modal.includes('Click to select · Save Theme to apply')],
 ['Generated card has exact active state', files.modal.includes('selectedTheme === generatedTheme.key')],
 ['Builder remains unchanged wording', files.modal.includes('The Builder stays unchanged until you click Save Theme')],
 ['Theme apply installs generated theme only on Save', files.builder.includes('if (generatedTheme?.palette)') && files.builder.includes("primary: 'my-brand'")],
 ['Manual Page Style remains present', files.builder.includes('<PageStyleSelector')],
 ['Overlay Header manual control remains present', files.builder.includes('Overlay Header')],
 ['Mega Footer manual control remains present', files.builder.includes('Mega Footer')],
];
let failed=0;
checks.forEach(([name, ok],i)=>{ console.log(`${ok?'PASS':'FAIL'} ${i+1}/${checks.length} ${name}`); if(!ok) failed++; });
if(failed) process.exit(1);
console.log(`\n${checks.length}/${checks.length} PASS`);
