import fs from 'node:fs';
const css=fs.readFileSync('resources/css/app.css','utf8');
const checks=[
 ['batch marker',css.includes('Font Audit Batch 2 — public/dashboard compositing cleanup')],
 ['dashboard sidebar no backdrop blur',/\.cosmic-dashboard-sidebar,[\s\S]*?-webkit-backdrop-filter: none !important;/.test(css)],
 ['new page overlay protected',/#cosmic-new-page-overlay[\s\S]*?backdrop-filter: none !important;/.test(css)],
 ['welcome typography filter guard',/\.cosmic-welcome-page :where\(h1,h2,h3,h4,h5,h6,p,span,a,button,label,input,textarea,select,small,strong\)/.test(css)],
 ['guest typography filter guard',/\.cosmic-guest-light :where\(h1,h2,h3,h4,h5,h6,p,span,a,button,label,input,textarea,select,small,strong\)/.test(css)],
 ['auth typography filter guard',/\.cosmic-authenticated-shell :where\(h1,h2,h3,h4,h5,h6,p,span,a,button,label,input,textarea,select,small,strong\)/.test(css)],
 ['dashboard typography filter guard',/\.cosmic-dashboard-shell :where\(h1,h2,h3,h4,h5,h6,p,span,a,button,label,input,textarea,select,small,strong\)/.test(css)],
 ['dashboard article hover no transform',/\.cosmic-dashboard-main article:hover,[\s\S]*?transform: none !important;/.test(css)],
 ['spark render shell untouched by batch selector',!css.slice(css.indexOf('Font Audit Batch 2 — public/dashboard compositing cleanup')).includes('.cosmic-render-shell')],
 ['doc exists',fs.existsSync('BATCH2-PUBLIC-DASHBOARD-COMPOSITING-CLEANUP.md')],
];
let pass=0;
for(const [name,ok] of checks){console.log(`${ok?'PASS':'FAIL'} ${name}`); if(ok) pass++;}
console.log(`\n${pass}/${checks.length} PASS`);
process.exit(pass===checks.length?0:1);
