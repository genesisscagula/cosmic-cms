import assert from 'node:assert/strict';
import fs from 'node:fs';

const read=(path)=>fs.readFileSync(path,'utf8');
const editor=read('app/Services/LunaSparkSchemaEditorService.php');
const executor=read('app/Services/LunaStructuralActionService.php');
const builder=read('resources/js/Pages/Websites/Builder.jsx');
const extrasRuntime=read('resources/js/Pages/Websites/Blocks/Shared/SparkFieldExtrasRuntime.jsx');
const nested=read('app/Services/NestedRepeaterMutationService.php');
const verifier=read('app/Services/LunaExecutionVerificationService.php');
const leaf=JSON.parse(read('resources/luna/leaf_action_schemas.json'));
const smart=JSON.parse(read('resources/luna/smart_spark_editing.json'));

for(const token of [
  'structural_actions', 'add_extra', 'update_extra', 'remove_extra', 'move_extra', 'duplicate_extra',
  'add_row', 'add_column', 'mixed_structural_and_patch_protocol', 'structural_actions_v1',
  'target_field', 'logicalCollectionPath',
]) assert.ok(editor.includes(token),`schema editor missing ${token}`);

for(const token of [
  'field_extras','rows','columns','extras','destination_collection_path','atomic',
]) {
  if(token==='atomic') assert.ok(executor.includes('Plans are atomic'), 'executor must document atomic plans');
  else assert.ok(executor.includes(token),`structural executor missing ${token}`);
}

assert.ok(nested.includes('insertItem('),'nested repeater engine must support exact object insertion');
assert.ok(nested.includes('preserveIdentity'),'cross-collection move must preserve identity');
assert.ok(builder.includes('logicalCollectionPath'),'Builder must send AI Flex logical collection path');
assert.ok(builder.includes('stableId'),'Builder must send AI Flex stable IDs');
assert.ok(extrasRuntime.includes('data-cosmic-extra-empty-media="image"'),'empty field-extra images need a visible popup placeholder');
assert.ok(extrasRuntime.includes('data-cosmic-extra-empty-media="video"'),'empty field-extra videos need a visible popup placeholder');
assert.ok(verifier.includes('spark_structural_edit'),'execution verification must recognize structural Spark edits');

const elementOps=leaf?.domains?.element?.leaf_operations || {};
for(const action of smart.structural_action_contract.actions){
  assert.ok(elementOps[action],`leaf action catalog missing ${action}`);
}
assert.equal(smart.structural_action_contract.atomic,true,'structural contract must be atomic');
assert.equal(smart.structural_action_contract.mixed_with_editable_or_tailwind_patch,false,'mixed structural/patch protocol must be disabled');

console.log('Luna Batch 5 source wiring PASS');
console.log('API4 structural protocol, Builder logical targeting, action catalog, placeholders and verification wiring PASS');
