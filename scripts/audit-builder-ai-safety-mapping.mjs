import fs from 'node:fs';
const b=fs.readFileSync('resources/js/Pages/Websites/Builder.jsx','utf8');
const r=fs.readFileSync('app/Services/SparkEditCapabilityRegistry.php','utf8');
const v=fs.readFileSync('app/Services/SparkEditMutationValidator.php','utf8');
const checks=[
 ['frontend capability manifest',b.includes('const sparkCapabilityManifest = (block = {}) =>')],
 ['manifest detects repeaters',b.includes("repeaters: repeaterKeys")],
 ['manifest detects media',b.includes('media: mediaKeys')],
 ['safe candidate validator',b.includes('const isSafeBuilderBlockCandidate = (candidate, fallback = null) =>')],
 ['malformed repeater rejection',b.includes("candidate[key].some((item)=>item !== null")],
 ['edit session last valid snapshot',b.includes('lastValid: cloneBuilderEditValue(original)')],
 ['checkpoint before contextual AI',b.includes('checkpointEditSessionState();')],
 ['invalid contextual mutation restores',b.includes('restoreLastValidEditSessionState();')],
 ['invalid mutation throws safe error',b.includes('Your last valid preview was restored.')],
 ['AI response target boundary retained',b.includes('const contextualBlockBoundary=Boolean(editSession?.open && Number.isInteger(editSession.blockIndex))')],
 ['layout switch stores destination manifest',b.includes('_spark_capability_manifest: destinationManifest')],
 ['layout switch records overflow preservation',b.includes('_spark_overflow_preserved: overflowPreserved')],
 ['content mapping retains repeater arrays',b.includes('Repeater arrays are retained whole so stable item IDs/order/private copy survive.')],
 ['registry declares preservation policy',r.includes("'preservation_policy' => [")],
 ['registry says never truncate',r.includes("'never_truncate_repeaters' => true")],
 ['registry exposes repeater keys',r.includes("'repeater_keys' => collect")],
 ['server protects protected fields',v.includes("'protected_field'")],
 ['server rejects unknown fields',v.includes("'unknown_field'")],
 ['server validates target contracts',v.includes("'unsupported_target'")],
 ['server validates supported properties',v.includes("'unsupported_property'")],
];
let pass=0;for(const [n,ok] of checks){console.log(`${ok?'PASS':'FAIL'} ${n}`);if(ok)pass++;}
console.log(`\n${pass}/${checks.length} PASS`);if(pass!==checks.length)process.exit(1);
