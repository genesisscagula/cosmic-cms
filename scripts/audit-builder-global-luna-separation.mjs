import fs from 'node:fs';
const b=fs.readFileSync('resources/js/Pages/Websites/Builder.jsx','utf8');
const c=fs.readFileSync('app/Http/Controllers/CustomSparkController.php','utf8');
const g=fs.readFileSync('app/Services/LunaIntentGateway.php','utf8');
const checks=[
 ['frontend declares assistant surface',b.includes("const lunaAssistantSurface = editSession?.open ? 'contextual_popup' : 'global_builder'")],
 ['assistant surface sent to backend',b.includes("form.append('assistant_surface', lunaAssistantSurface)")],
 ['global transport target forced to page',b.includes(": 'page';\n            form.append('target_scope', requestTargetScope)")],
 ['local element context contextual only',b.includes("lunaAssistantSurface === 'contextual_popup' && lunaElementTarget")],
 ['contextual composer explicitly routes local surface',b.includes("assistantSurface:'contextual_popup'")],
 ['global button opens whole page',b.includes("openLunaChat({type:'page',blockIndex:null,label:'Whole Page'})")],
 ['global Luna hidden during edit session',b.includes('lunaChatOpen && !editSession?.open')],
 ['controller validates assistant surface',c.includes("'assistant_surface'=>['nullable','in:global_builder,contextual_popup']")],
 ['builder normalizes global scope',c.includes("if($assistantSurface==='global_builder')") && c.includes("$scope='page';") && c.includes("$targetIndex=-1;")],
 ['route context carries assistant surface',c.includes("'assistant_surface'=>$assistantSurface")],
 ['contextual out-of-domain execution blocked',c.includes("$assistantSurface==='contextual_popup'") && c.includes("$menuScope!==$expectedMenuScope")],
 ['contextual redirect costs zero',c.includes("'credit_cost'=>0") && c.includes('Use the lower-right Luna for page-wide or site-wide changes.')],
 ['gateway isolates prior context by surface',g.includes('priorContextForSurface($siteMemory,$context)')],
 ['contextual prior action memory cleared',g.includes("if($surface==='contextual_popup')") && g.includes("'last_verified_action'=>[]")],
 ['global stale local verified target cleared',g.includes("in_array($verifiedScope,['section','item','element','spark'],true)")],
 ['global stale local routing target cleared',g.includes("in_array($routingScope,['section','item','element','spark'],true)")],
];
let pass=0; for(const [name,ok] of checks){console.log(`${ok?'PASS':'FAIL'} ${name}`); if(ok)pass++;}
console.log(`\n${pass}/${checks.length} PASS`); if(pass!==checks.length)process.exit(1);
