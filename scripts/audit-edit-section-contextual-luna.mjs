import fs from 'node:fs';
const builder=fs.readFileSync('resources/js/Pages/Websites/Builder.jsx','utf8');
const controller=fs.readFileSync('app/Http/Controllers/CustomSparkController.php','utf8');
const checks=[
 ['null block index guarded', builder.includes("blockIndex !== null && blockIndex !== undefined && blockIndex !== ''")],
 ['contextual popup endpoint retained', builder.includes("`/websites/${website.id}/luna-popup`")],
 ['popup rendered section inventory first', builder.includes('data-popup-section-preview="true"') && builder.includes('resolveRenderedSectionNode')],
 ['sibling mutation boundary retained', builder.includes('Never accept AI') && builder.includes('index===targetIndex')],
 ['popup local intent promotion', controller.includes('$popupLocalEditIntent') && controller.includes("'menu_scope' => 'sparks'")],
 ['popup global scope rejected', controller.includes('$popupGlobalScopeIntent') && controller.includes('popup_scope_boundary')],
 ['schema editor remains executor', controller.includes('$sparkSchemaEditor->edit(')],
];
let failed=0;
for(const [name,ok] of checks){console.log(`${ok?'PASS':'FAIL'} ${name}`); if(!ok) failed++;}
if(failed) process.exit(1);
