import fs from 'node:fs';
const file='resources/js/Pages/Websites/Components/AddSectionModal.jsx';
const s=fs.readFileSync(file,'utf8');
const checks=[
 ['marketplace title', /Sparks Marketplace/],
 ['tabs', /Marketplace.*Owned.*Favorites/s],
 ['tablist accessibility', /role="tablist"/],
 ['tab aria selected', /aria-selected=\{tab === id\}/],
 ['search', /placeholder="Search Sparks\.\.\."/],
 ['category chips', /categories\.map/],
 ['three column xl grid', /xl:grid-cols-3/],
 ['preview action', />Preview<\/button>/],
 ['install action', />Install<\/button>/],
 ['buy action', /Buy · ⚡/],
 ['owned badge', /✓ Owned/],
 ['free badge', />Free<\/span>/],
 ['credits badge', /Credits<\/span>/],
 ['favorite accessible label', /Remove .* from Favorites/],
 ['generic install mode', /Generic Content/],
 ['ai personalize mode', /AI Personalize/],
 ['preview modal', /GlobalSparkPreviewModal/],
 ['responsive max modal', /max-w-\[1600px\]/],
 ['adaptive marketplace height', /min-h-\[min\(720px,88vh\)\]/],
];
let fail=0;
for (const [name,re] of checks) { const ok=re.test(s); console.log(`${ok?'PASS':'FAIL'} ${name}`); if(!ok) fail++; }
console.log(`\n${checks.length-fail}/${checks.length} checks passed`);
process.exit(fail?1:0);
