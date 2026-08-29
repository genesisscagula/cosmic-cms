import fs from 'node:fs';
const file='resources/js/Pages/Websites/Builder.jsx';
const s=fs.readFileSync(file,'utf8');
const checks=[
 ['related layouts use registry category', s.includes("registryItem?.schema?.category === category")],
 ['section popup renders layout control', s.includes('sectionSparkLayoutsExpanded') && s.includes('getCompatibleLayouts(activeBlock?.type, activeBlock)')],
 ['embedded popup preview contract', s.includes('data-popup-section-preview="true"') && s.includes('aria-label="Section working preview"')],
 ['working-state switch handler', s.includes('const previewSectionSparkLayout = (index, layout) =>')],
 ['switch requires section edit session', s.includes("editSession.scope !== 'section'")],
 ['registered destination defaults', s.includes('BlockRegistry[layout.type]?.schema?.defaults')],
 ['content-only preservation filter', s.includes('const sparkContentKey = (key) =>')],
 ['compatible content preservation', s.includes('const preserveCompatibleSparkContent = (reference = {}, replacement = {}) =>')],
 ['repeaters copied with identity', s.includes('Repeater arrays are retained whole so stable item IDs/order/private copy survive.')],
 ['per-spark content memory', s.includes('_spark_content_memory') && s.includes('sparkContentSnapshot(currentBlock)')],
 ['layout buttons mutate preview', s.includes('onClick={()=>previewSectionSparkLayout(editSession.blockIndex,layout)}')],
 ['current layout indicated', s.includes('aria-pressed={active}') && s.includes("active?'border-violet-600")],
 ['apply remains explicit', s.includes('onClick={applyEditSession}')],
 ['cancel rollback remains intact', s.includes('Builder was never mutated by the popup') && s.includes('setEditSessionDraft(null)')],
 ['contextual Luna remains beside editor', s.includes('Luna · Contextual') && s.includes('sendContextualLunaRequest')],
];
let pass=0;
for(const [name,ok] of checks){console.log(`${ok?'PASS':'FAIL'} ${name}`);if(ok)pass++;}
console.log(`\n${pass}/${checks.length} PASS`);
if(pass!==checks.length)process.exit(1);
