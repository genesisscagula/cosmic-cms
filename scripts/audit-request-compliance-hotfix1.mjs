import fs from 'node:fs';
const root = process.cwd();
const read = p => fs.readFileSync(`${root}/${p}`, 'utf8');
const service = read('app/Services/LunaSparkRequestComplianceService.php');
const controller = read('app/Http/Controllers/CustomSparkController.php');
const checks = [
 ['compliance service exists', service.includes('final class LunaSparkRequestComplianceService')],
 ['extracts semantic requirements', service.includes("'semantic_type' => $semantic")],
 ['extracts requested item counts', service.includes("'item_count' => $itemCount")],
 ['validates primary collection count', service.includes('primaryCollectionCount')],
 ['popup injects compliance service', controller.includes('LunaSparkRequestComplianceService $sparkRequestCompliance')],
 ['blank Luna uses Spark-first for recognizable sections', controller.includes('$premadeCreateIntent = $blankLunaAiFlex')],
 ['AI Flex is fallback for unmatched blank request', controller.includes('$redesignAiFlex = $blankLunaAiFlex && ! $premadeCreateIntent')],
 ['generated registered Spark is compliance gated', controller.includes("registered Spark failed request compliance")],
 ['failed compliance is not charged', controller.includes("'popup_action' => 'registered_spark_compliance_failed'") && controller.includes("'credit_cost' => 0")],
 ['success response exposes compliance', controller.includes("'request_compliance' => $compliance")],
 ['blank chat guidance is Spark-first', controller.includes('choose a suitable proven layout and customize it for you')],
];
let pass=0;
for (const [name, ok] of checks) { console.log(`${ok?'PASS':'FAIL'} ${name}`); if(ok) pass++; }
console.log(`\n${pass}/${checks.length} PASS`);
if(pass!==checks.length) process.exit(1);
