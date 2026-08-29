import fs from 'node:fs';
const s=fs.readFileSync('resources/js/Pages/Websites/Builder.jsx','utf8');
const checks=[
 ['removed header helper copy', !s.includes('Changes stay inside this popup until you apply them.')],
 ['removed Manual editor heading', !s.includes('Manual editor')],
 ['removed Original state badge', !s.includes('Original state')],
 ['removed Section preview heading', !s.includes('Section preview')],
 ['removed section preview explainer', !s.includes('Layout choices and Luna custom-design changes render only in this popup')],
 ['removed bottom preview explainer', !s.includes('The rendered section above is the only preview')],
 ['related Spark buttons remain', s.includes('previewSectionSparkLayout(editSession.blockIndex,layout)')],
 ['Spark buttons appear before rendered preview', s.indexOf('previewSectionSparkLayout(editSession.blockIndex,layout)') < s.indexOf('data-popup-section-preview="true"', s.indexOf('previewSectionSparkLayout(editSession.blockIndex,layout)'))],
 ['section preview remains rendered', s.includes('activeBlock ? renderBlock(activeBlock, editSession.blockIndex) : null')],
 ['section preview height expanded', s.includes('max-h-[76dvh]')],
 ['section main wrapper flattened', s.includes("editSession.scope==='section'?'':'rounded-2xl border border-slate-200 bg-slate-50 p-5'")],
 ['Apply remains explicit', s.includes('applyEditSession')],
];
let fail=0;checks.forEach(([n,ok],i)=>{console.log(`${ok?'PASS':'FAIL'} ${i+1}/${checks.length} ${n}`); if(!ok) fail++;});
if(fail) process.exit(1); console.log(`\n${checks.length}/${checks.length} PASS`);
