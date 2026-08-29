import fs from 'node:fs';
const builder=fs.readFileSync('resources/js/Pages/Websites/Builder.jsx','utf8');
const controller=fs.readFileSync('app/Http/Controllers/CustomSparkController.php','utf8');
const checks=[
 ['popup preview is rendered locally', builder.includes('data-popup-section-preview="true"')],
 ['contextual inventory prefers popup preview', builder.includes('resolveRenderedSectionNode') && builder.includes('[data-popup-section-preview="true"] [data-luna-section-index=')],
 ['frozen Builder remains fallback only', builder.includes('return document.querySelector(`[data-luna-section-index="${Number(index)}"]`)')],
 ['section contextual inventory uses resolver', builder.includes('const sectionNode = resolveRenderedSectionNode(lunaScope.blockIndex)')],
 ['candidate inventory uses resolver', builder.includes('const sectionNode = resolveRenderedSectionNode(candidateIndex)')],
 ['legacy rounded-all rule removed', !builder.includes('.cosmic-luna-design-host [class*="rounded"] { border-radius: var(--luna-card-radius, revert); }')],
 ['card radius fallback is card-scoped', builder.includes('[data-luna-target="card"]') && builder.includes('var(--luna-card-radius, revert)')],
 ['button square mapping explicit', controller.includes('square/sharp means add rounded-none')],
 ['button pill mapping explicit', controller.includes('pill/fully-rounded means add rounded-full')],
 ['card radius cannot target buttons', controller.includes('Card/corner radius requests must target card slots, never button slots')],
 ['AI response still writes popup draft', builder.includes('setPopupDraftBlocks(normalizedResponseBlocks)')],
 ['Apply remains builder bridge', builder.includes('applyEditSession')],
];
let fail=0;
for(const [name,ok] of checks){console.log(`${ok?'PASS':'FAIL'} ${name}`); if(!ok) fail++;}
console.log(`\n${checks.length-fail}/${checks.length} PASS`);
if(fail) process.exit(1);
