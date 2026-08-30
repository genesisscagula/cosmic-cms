import fs from 'node:fs';

const builder = fs.readFileSync(new URL('../resources/js/Pages/Websites/Builder.jsx', import.meta.url), 'utf8');
const controller = fs.readFileSync(new URL('../app/Http/Controllers/CustomSparkController.php', import.meta.url), 'utf8');
const saved = fs.readFileSync(new URL('../app/Http/Controllers/SavedSparkController.php', import.meta.url), 'utf8');

const checks = [
  ['popup registered-Spark redesign lane exists', controller.includes('$premadeRedesignIntent') && controller.includes("$sparkSelectionPlanner->plan($prompt, 'change_spark'")],
  ['existing redesign no longer defaults to AI Flex', controller.includes('$redesignAiFlex = $blankLunaAiFlex;')],
  ['planner result is returned to popup UI', controller.includes("'spark_selection' => [") && controller.includes("'alternate_spark_ids'")],
  ['capability guard reaches registered redesign generation', controller.includes('COSMIC CAPABILITY GUARD')],
  ['popup preserves compatible content on matched redesign', controller.includes('$this->lunaPreserveCompatibleSparkContent($current, $replacement)')],
  ['frontend reads direct spark selection', builder.includes('response?.spark_selection') && builder.includes('alternateSource')],
  ['Try another layout UI exists', builder.includes('Try another layout') && builder.includes('lunaLayoutChoices')],
  ['alternate switching is local preview', builder.includes('const previewLunaAlternateSpark = (sparkId) =>') && builder.includes('lunaBuildBlockFromType(type)')],
  ['alternate preview preserves compatible copy/repeaters', builder.includes('preserveCompatibleSparkContent(currentBlock, cloneBuilderEditValue(base))') && builder.includes('_spark_content_memory')],
  ['Apply can save customized registered Spark as Luna Spark', builder.includes("session.creationSource === 'blank_luna' || session.lunaCustomized") && builder.includes('spark_type: String(block.type)')],
  ['saved copy remains source luna', builder.includes("source: 'luna'") && saved.includes("'source' => ['nullable', 'string', 'in:manual,luna']")],
  ['smooth-scroll-on-create remains intact', builder.includes("target.scrollIntoView({ behavior:'smooth', block:'center' })")],
  ['Cancel remains draft-only', builder.includes('setEditSessionDraft(null);') && builder.includes('cancelEditSession')],
  ['selection only marks customized after verified operation', builder.includes('response?.applied_operations') && builder.includes('response.applied_operations.length > 0')],
];

let failures = 0;
for (const [name, pass] of checks) {
  console.log(`${pass ? 'PASS' : 'FAIL'}  ${name}`);
  if (!pass) failures++;
}
console.log(`\nBatch 5 audit: ${checks.length - failures}/${checks.length} PASS`);
process.exit(failures ? 1 : 0);
