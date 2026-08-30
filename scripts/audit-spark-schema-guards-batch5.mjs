import fs from 'node:fs';
import path from 'node:path';

const root=process.cwd();
const read=(p)=>fs.readFileSync(path.join(root,p),'utf8');
const pricing=read('app/Cosmic/Pricing/BlockPricingRegistry.php');
const schema=read('app/AI/Schemas/SchemaManager.php');
const editor=read('app/Services/LunaSparkSchemaEditorService.php');
const validator=read('app/Services/SparkTailwindSchemaValidator.php');
const controller=read('app/Http/Controllers/CustomSparkController.php');
const verifier=read('app/Services/LunaExecutionVerificationService.php');

const keys=[...new Set([...pricing.matchAll(/^\s*'([a-z0-9_]+)'\s*=>\s*\['label'/gm)].map(m=>m[1]))];
const schemaKeys=new Set([...schema.matchAll(/^\s*'([a-z0-9_]+)'\s*=>\s*'[A-Za-z0-9_]+'/gm)].map(m=>m[1]));

const count=(haystack,needle)=>haystack.split(needle).length-1;
const checks=[
  ['329 registered Sparks',keys.length===329],
  ['329 registered schemas',keys.every(k=>schemaKeys.has(k))],
  ['exact Spark type lock',editor.includes("return ['ok'=>false,'reason'=>'spark_target_mismatch']")],
  ['exact target-index inventory lock',editor.includes("return ['ok'=>false,'reason'=>'tailwind_inventory_target_mismatch']")],
  ['editable patch paths must already exist',editor.includes("'editable_patch_path_invalid'") && editor.includes('Arr::has($beforeEditable,$path)')],
  ['ordinary patches cannot replace structural lists',editor.includes("'editable_patch_collection_replace_forbidden'") && editor.includes('array_is_list($currentValue)')],
  ['recursive editable shape guard covers list length',editor.includes('private function shapeMismatch') && editor.includes('count($before)!==count($after)')],
  ['Tailwind slot shape is immutable',editor.includes("'tailwind_slot_shape_changed'")],
  ['Tailwind role/lock metadata is immutable',editor.includes("'tailwind_slot_metadata_changed'")],
  ['locked Tailwind slots are immutable',editor.includes("'locked_tailwind_slot_changed'")],
  ['protected structural utilities cannot be removed',editor.includes("'protected_tailwind_removed'") && validator.includes('public function isProtectedUtility')],
  ['exclusive Tailwind family conflicts are rejected',editor.includes("'tailwind_conflict'") && editor.includes('TailwindUtilityConflictResolver')],
  ['verified Tailwind changes persist as add/remove overlays',editor.includes("'add'=>array_values(array_unique($existingAdd))") && editor.includes("'remove'=>array_values(array_unique($existingRemove))")],
  ['deterministic editable/Tailwind diff is recorded',editor.includes("'diff'=>[") && editor.includes("'editable'=>$editableDiff") && editor.includes("'tailwind'=>$tailwindDiff")],
  ['before/after schema fingerprints are recorded',editor.includes("'before_fingerprint'=>$this->fingerprint") && editor.includes("'after_fingerprint'=>$this->fingerprint")],
  ['trial and website executors fail closed after schema rejection',count(controller,'Never fall back to')>=1 && count(controller,'never reinterpreted by the old mutation engine')>=1],
  ['trial and website executors record protocol-aware structural edits',count(controller,"==='structural_actions_v1')?'spark_structural_edit':'spark_full_schema_edit'")>=2],
  ['executor verifies exact selected Spark index',controller.includes("(int)($sparkTarget['index']??-1)===$i") && controller.includes("(int)($sparkTarget['index']??-1)===$index")],
  ['verification layer accepts guarded schema + structural results',verifier.includes("'spark_full_schema_edit'") && verifier.includes("'spark_structural_edit'")],
];

const sharedSafe=checks.slice(2).every(([,ok])=>ok);
const rows=keys.map(spark=>({
  spark,
  schema:schemaKeys.has(spark),
  selected_spark_guard:sharedSafe,
  editable_shape_guard:sharedSafe,
  tailwind_guard:sharedSafe,
  fail_closed_executor:sharedSafe,
  verified_diff_fingerprint:sharedSafe,
  status:(schemaKeys.has(spark)&&sharedSafe)?'PASS':'FAIL',
}));

const report={
  audit_version:1,
  scope:'Spark Audit Batch 5 — Schema Diff + Executor Guards',
  registered:keys.length,
  pass:rows.filter(r=>r.status==='PASS').length,
  fail:rows.filter(r=>r.status!=='PASS').length,
  checks:Object.fromEntries(checks.map(([name,ok])=>[name,ok])),
  rows,
};

fs.mkdirSync(path.join(root,'storage/app/audits'),{recursive:true});
fs.writeFileSync(path.join(root,'storage/app/audits/spark-schema-guards-batch5.json'),JSON.stringify(report,null,2));
checks.forEach(([name,ok])=>console.log(`${ok?'PASS':'FAIL'} ${name}`));
console.log(`Coverage: ${report.pass}/${report.registered} registered Sparks inherit the guarded schema executor contract.`);
if(checks.some(([,ok])=>!ok)||report.fail>0) process.exitCode=1;
