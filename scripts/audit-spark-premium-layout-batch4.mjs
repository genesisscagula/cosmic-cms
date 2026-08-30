import fs from 'node:fs';
import path from 'node:path';
const root=process.cwd();
const read=(p)=>fs.readFileSync(path.join(root,p),'utf8');
const pricing=read('app/Cosmic/Pricing/BlockPricingRegistry.php');
const builderRegistry=read('resources/js/Pages/Websites/BlockRegistry.jsx');
const builder=read('resources/js/Pages/Websites/Builder.jsx');
const addSection=read('resources/js/Pages/Websites/Components/AddSectionModal.jsx');
const appCss=read('resources/css/app.css');
const compiler=read('app/Helpers/CmsHtmlCompiler.php');
const keys=[...new Set([...pricing.matchAll(/^\s*'([a-z0-9_]+)'\s*=>\s*\['label'/gm)].map(m=>m[1]))];
const builderKeys=new Set(keys.filter(key => builderRegistry.includes(`${key}:`) || builderRegistry.includes(`'${key}':`) || builderRegistry.includes(`"${key}":`)));
const compilerKeys=new Set([...compiler.matchAll(/case\s+["']([a-z0-9_]+)["']\s*:/g)].map(m=>m[1]));
const layoutTokens=['--cosmic-local-section-py-top','--cosmic-local-section-py-bottom','--cosmic-local-section-px-left','--cosmic-local-section-px-right'];
const safetyTokens=['overflow-x: clip',':is(img, video, iframe, svg, canvas)','overflow-wrap: anywhere','[data-cosmic-layout="grid"] > *',':is(input, select, textarea, button)'];
const compilerSafety=["overflow-x:clip",":is(img,video,iframe,svg,canvas){max-width:100%}","overflow-wrap:anywhere",":is(.grid,[data-cosmic-layout='grid'])>*{min-width:0}",":is(input,select,textarea,button){max-width:100%}","section[data-cosmic-spark='1'] table{display:block"];
const sharedHostBuilder=builder.includes('cosmic-builder-spark cosmic-spark-layout-host');
const sharedHostPreview=addSection.includes('cosmic-spark-preview-content cosmic-spark-layout-host') && addSection.includes('cosmic-preview-isolation cosmic-spark-layout-host w-full');
const sharedHostCss=appCss.includes('.cosmic-spark-layout-host') && layoutTokens.every(token=>appCss.includes(token)) && safetyTokens.every(token=>appCss.includes(token)) && appCss.includes('@media (min-width: 768px)') && appCss.includes('@media (min-width: 1025px)') && appCss.includes('@media (max-width: 767px)') && appCss.includes(':not(.cosmic-tw-own-section-y)');
const publishedContract=compiler.includes("data-cosmic-layout-contract='premium-v1'") && layoutTokens.every(token=>compiler.includes(token)) && compilerSafety.every(token=>compiler.includes(token)) && compiler.includes(':not(.cosmic-tw-own-section-y):not([data-cosmic-preserve-spacing])');
const checks=[
 ['329 registered Sparks',keys.length===329],
 ['329 Builder renderers',builderKeys.size===329],
 ['329 export compiler routes',keys.every(key=>compilerKeys.has(key))],
 ['Builder uses shared premium layout host',sharedHostBuilder],
 ['Add Section curated + Builder previews use shared premium layout host',sharedHostPreview],
 ['shared host has four-side local spacing tokens',layoutTokens.every(token=>appCss.includes(token))],
 ['shared host has mobile/tablet/desktop rhythm',appCss.includes('@media (min-width: 768px)')&&appCss.includes('@media (min-width: 1025px)')&&appCss.includes('@media (max-width: 767px)')],
 ['shared host preserves Tailwind-owned section spacing',appCss.includes(':not(.cosmic-tw-own-section-y)')],
 ['shared host prevents horizontal overflow/media/grid/form breakage',safetyTokens.every(token=>appCss.includes(token))],
 ['published roots carry premium layout contract marker',compiler.includes("data-cosmic-layout-contract='premium-v1'")],
 ['published roots mirror layout safety',compilerSafety.every(token=>compiler.includes(token))],
 ['published spacing preserves Tailwind/explicit ownership',compiler.includes(':not(.cosmic-tw-own-section-y):not([data-cosmic-preserve-spacing])')],
 ['export tags only the first root section per Spark fragment',compiler.includes("preg_replace(\n                    '/<section(?![^>]*data-cosmic-spark)/i'")&&compiler.includes("$fragment,\n                    1\n                );")],
];
const rows=keys.map((key)=>({spark:key,builder_renderer:builderKeys.has(key),export_route:compilerKeys.has(key),builder_layout_contract:sharedHostBuilder&&sharedHostCss,add_section_layout_contract:sharedHostPreview&&sharedHostCss,export_layout_contract:publishedContract,status:(builderKeys.has(key)&&compilerKeys.has(key)&&sharedHostBuilder&&sharedHostPreview&&sharedHostCss&&publishedContract)?'PASS':'FAIL'}));
const report={audit_version:1,scope:'Spark Audit Batch 4 — Premium Layout + Responsive Safety',registered:keys.length,pass:rows.filter(r=>r.status==='PASS').length,fail:rows.filter(r=>r.status!=='PASS').length,defaults:{desktop_section_y:'100px',tablet_section_y:'80px',mobile_section_y:'56px',desktop_section_x:'28px',tablet_section_x:'24px',mobile_section_x:'20px'},checks:Object.fromEntries(checks.map(([name,ok])=>[name,ok])),rows};
fs.mkdirSync(path.join(root,'storage/app/audits'),{recursive:true});
fs.writeFileSync(path.join(root,'storage/app/audits/spark-premium-layout-batch4.json'),JSON.stringify(report,null,2));
checks.forEach(([name,ok])=>console.log(`${ok?'PASS':'FAIL'} ${name}`));
console.log(`Coverage: ${report.pass}/${report.registered} registered Sparks inherit the premium layout contract.`);
if(checks.some(([,ok])=>!ok)||report.fail>0) process.exitCode=1;
