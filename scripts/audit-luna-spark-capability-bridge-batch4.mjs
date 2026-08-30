import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = rel => fs.readFileSync(path.join(root, rel), 'utf8');
const bridge = read('app/Services/LunaSparkCapabilityBridgeService.php');
const registry = read('app/Services/SparkEditCapabilityRegistry.php');
const planner = read('app/Services/LunaSparkSelectionPlannerService.php');
const controller = read('app/Http/Controllers/CustomSparkController.php');

const checks = [
  ['bridge service exists', bridge.includes('final class LunaSparkCapabilityBridgeService')],
  ['bridge derives manifest from registry', bridge.includes('SparkEditCapabilityRegistry') && bridge.includes('manifestForSpark')],
  ['registered-schema-only guardrail', bridge.includes("'registered_schema_only' => true")],
  ['arbitrary component tree forbidden', bridge.includes("'no_arbitrary_component_tree' => true")],
  ['unsupported mutations rejected', bridge.includes("'unsupported_mutation' => 'reject'")],
  ['structure is schema-editor guarded', bridge.includes("$mode = 'schema_editor'")],
  ['planner version upgraded', planner.includes('public const VERSION = 2;')],
  ['candidate payload carries compact manifest', planner.includes("'capability_manifest' => $this->capabilityBridge->manifestForSpark")],
  ['planner guards Luna plan locally', planner.includes('$this->capabilityBridge->guardPlan($selected, $normalizedPlan)')],
  ['sanitized plan replaces advisory plan', planner.includes("$normalizedPlan = $guard['customization_plan'];")],
  ['operation carries authoritative guard', controller.includes("'luna_capability_guard'=>$selection['capability_guard']??null")],
  ['execution prompt carries guard', controller.includes('COSMIC CAPABILITY GUARD (authoritative)')],
  ['executor forbids unsupported nesting', controller.includes('Do not add unsupported schema structure or arbitrary nesting.')],
  ['base capability modules remain deterministic', registry.includes('public function inferModules') && registry.includes('BASE_MODULES')],
];

let passed = 0;
for (const [name, ok] of checks) {
  console.log(`${ok ? 'PASS' : 'FAIL'} - ${name}`);
  if (ok) passed++;
}
console.log(`\n${passed}/${checks.length} checks passed.`);
if (passed !== checks.length) process.exit(1);
