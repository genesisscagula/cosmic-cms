import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(new URL('..', import.meta.url).pathname);
const controller = fs.readFileSync(path.join(root, 'app/Http/Controllers/CustomSparkController.php'), 'utf8');
const builder = fs.readFileSync(path.join(root, 'resources/js/Pages/Websites/Builder.jsx'), 'utf8');
const compliance = fs.readFileSync(path.join(root, 'app/Services/LunaSparkRequestComplianceService.php'), 'utf8');

const checks = [];
const add = (name, ok) => checks.push([name, !!ok]);
add('retry queue uses selected + alternates + shortlist', controller.includes('$candidateKeys') && controller.includes("$selection['alternate_spark_ids']") && controller.includes("$selection['shortlist']"));
add('retry queue is bounded', controller.includes('array_slice($candidateKeys, 0, 4)'));
add('each candidate gets own capability guard', controller.includes('guardPlan($selectedKey, $basePlan)'));
add('each candidate passes compliance before preview', controller.includes('$sparkRequestCompliance->validate($prompt, $replacement, $selectedKey)'));
add('failed candidate continues to alternate', controller.includes("'status' => 'compliance_failed'") && controller.includes('continue;'));
add('credits only consumed after compliant candidate', controller.indexOf("if (($compliance['ok'] ?? false) !== true)") < controller.indexOf("$credits->consume($user, $creditCost, 'Luna popup registered Spark redesign'"));
add('all failed candidates return unchanged zero-credit reply', controller.includes("'popup_action' => 'registered_spark_candidates_exhausted'") && controller.includes("'credit_cost' => 0"));
add('retry telemetry returned', controller.includes("'candidate_retry_log' => $retryLog"));
add('exact services prompt extracts item count', /item_count/.test(compliance) && /service/.test(compliance));
add('Builder declaration precedes useEffect dependency', builder.indexOf('const contextualLunaMessages =') < builder.indexOf('}, [contextualLunaMessages, pageAiBusy'));
add('Builder has exactly one contextual messages declaration', (builder.match(/const contextualLunaMessages =/g) || []).length === 1);
add('Start Blank helper uses Spark-first guidance', builder.includes('choose a suitable proven layout'));

for (const [name, ok] of checks) console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}`);
const failed = checks.filter(([, ok]) => !ok);
console.log(`\n${checks.length - failed.length}/${checks.length} PASS`);
if (failed.length) process.exit(1);
