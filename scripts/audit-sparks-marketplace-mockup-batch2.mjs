import fs from 'node:fs';
const p='resources/js/Pages/Websites/Components/AddSectionModal.jsx';
const s=fs.readFileSync(p,'utf8');
const checks=[
 ['marketplace tabs', /Marketplace.*Owned.*Favorites/s],
 ['search sparks', /placeholder="Search Sparks\.\.\."/],
 ['category chips', /categories\.map/],
 ['preview action', />Preview<\/button>/],
 ['owned install', /spark\.owned[\s\S]*?>Install<\/button>/],
 ['buy action', /Buy · ⚡/],
 ['free action', /Add Free Spark/],
 ['favorite toggle', /toggleFavorite\(spark\)/],
 ['preview modal', /GlobalSparkPreviewModal/],
 ['install modal', /Install Spark/],
 ['generic mode', /Generic Content/],
 ['ai mode', /AI Personalize/],
 ['instructions', /AI Instructions/],
 ['generic handler', /setMode\("quick"\)/],
 ['ai handler', /setMode\("ai"\)/],
 ['install handler', /onClick=\{addSpark\}/],
];
let fail=0;
for (const [name,re] of checks){const ok=re.test(s); console.log(`${ok?'PASS':'FAIL'} ${name}`); if(!ok) fail++;}
console.log(`\n${checks.length-fail}/${checks.length} checks passed`);
process.exit(fail?1:0);
