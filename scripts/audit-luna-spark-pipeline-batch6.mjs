import fs from 'node:fs';
const read = p => fs.readFileSync(new URL(`../${p}`, import.meta.url), 'utf8');
const matcher = read('app/Services/SparkIntentMatcherService.php');
const planner = read('app/Services/LunaSparkSelectionPlannerService.php');
const bridge = read('app/Services/LunaSparkCapabilityBridgeService.php');
const controller = read('app/Http/Controllers/CustomSparkController.php');
const builder = read('resources/js/Pages/Websites/Builder.jsx');
const command = read('app/Console/Commands/AuditLunaSparkPipelineBatch6.php');
const pkg = JSON.parse(read('package.json'));
const checks = [
 ['local full-catalog matcher stays AI-free', matcher.includes('foreach (SparkCatalog::all() as $spark)') && !matcher.includes('OpenAI::')],
 ['AI receives compact shortlist only', planner.includes('array_slice($shortlist, 0, 8)') && planner.includes("'candidates' => $candidates")],
 ['planner rejects invented Spark ids', planner.includes('Luna selected an unsupported Spark id')],
 ['capability bridge rejects unsupported mutations', bridge.includes("'unsupported_mutation' => 'reject'") && bridge.includes("'no_arbitrary_component_tree' => true")],
 ['structural changes route to schema editor', bridge.includes("$mode = 'schema_editor'") && bridge.includes("'complex_structure' => 'schema_editor_only'")],
 ['controller carries Luna plan and alternates', controller.includes("'luna_spark_plan'") && controller.includes("'alternate_spark_ids'")],
 ['builder supports alternate layout preview', builder.includes('alternate_spark_ids') && builder.includes('Try another layout')],
 ['batch6 runtime torture command exists', command.includes('cosmic:audit-luna-spark-pipeline') && command.includes('zero AI calls')],
 ['torture matrix covers 15 intents', (command.match(/\['[^']+',\s*'[^']+',\s*(?:'[^']+'|null)\]/g) || []).length >= 15],
 ['negative mismatch guards included', command.includes('mismatch guard:')],
 ['npm audit entry registered', pkg.scripts?.['audit:luna-spark-pipeline'] === 'node scripts/audit-luna-spark-pipeline-batch6.mjs'],
];
let failed = 0;
for (const [name, ok] of checks) { console.log(`${ok ? 'PASS' : 'FAIL'} ${name}`); if (!ok) failed++; }
console.log(`\nBatch 6 static pipeline audit: ${checks.length-failed}/${checks.length} PASS`);
if (failed) process.exit(1);
