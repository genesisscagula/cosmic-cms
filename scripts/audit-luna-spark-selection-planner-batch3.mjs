import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = p => fs.readFileSync(path.join(root, p), 'utf8');
const planner = read('app/Services/LunaSparkSelectionPlannerService.php');
const controller = read('app/Http/Controllers/CustomSparkController.php');
const matcher = read('app/Services/SparkIntentMatcherService.php');

const checks = [
  ['planner service exists', planner.includes('final class LunaSparkSelectionPlannerService')],
  ['local matcher injected', planner.includes('SparkIntentMatcherService $matcher')],
  ['compact shortlist only', planner.includes('shortlistForAi($prompt, 10') && planner.includes('array_slice($shortlist, 0, 8)')],
  ['Luna model used for selection', planner.includes("'model' => $this->models->luna()")],
  ['selector forbids invented ids', planner.includes('! in_array($selected, $allowed, true)')],
  ['planner never requests schema tree', planner.includes('Do not generate JSX, HTML, Tailwind or a component tree here.')],
  ['customization buckets exist', ['content','media','visual','structure'].every(x => planner.includes(`'${x}'`))],
  ['execution hint bounded', planner.includes("['local', 'schema_editor']")],
  ['alternates bounded', planner.includes('count($alternates) >= 3')],
  ['deterministic fallback exists', planner.includes('deterministicFallback') && planner.includes("'selection_source' => 'deterministic_fallback'")],
  ['trial chat injects planner', controller.includes('public function trialPageChat') && controller.includes('LunaSparkSelectionPlannerService $sparkSelectionPlanner')],
  ['page chat injects planner', controller.includes('public function pageChat') && controller.split('public function pageChat')[1].includes('LunaSparkSelectionPlannerService $sparkSelectionPlanner')],
  ['add spark uses Luna selection plan', controller.includes("$sparkSelectionPlanner->plan($prompt,'add_spark')")],
  ['change spark uses Luna selection plan', controller.includes("$sparkSelectionPlanner->plan($prompt,'change_spark'")],
  ['operation carries plan for Batch 4', controller.includes("'luna_spark_plan'=>$selection")],
  ['branch metadata exposes alternates', controller.includes("'alternate_spark_ids'=>$selection['alternate_spark_ids']??[]")],
  ['API1 chat action guard preserved', controller.includes('API 1 owns the chat | action boundary')],
  ['matcher remains model-free', !matcher.includes('OpenAI::') && !matcher.includes('OpenAIClient')],
];

let pass = 0;
for (const [name, ok] of checks) {
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}`);
  if (ok) pass++;
}
console.log(`\n${pass}/${checks.length} checks passed.`);
if (pass !== checks.length) process.exit(1);
