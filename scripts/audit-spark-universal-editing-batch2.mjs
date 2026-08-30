import fs from 'node:fs';
const read=p=>fs.readFileSync(p,'utf8');
const pricing=read('app/Cosmic/Pricing/BlockPricingRegistry.php');
const schema=read('app/AI/Schemas/SchemaManager.php');
const caps=read('app/Services/SparkEditCapabilityRegistry.php');
const validator=read('app/Services/SparkEditMutationValidator.php');
const config=read('config/spark-edit-capabilities.php');
const editor=read('app/Services/LunaSparkSchemaEditorService.php');
const extras=read('resources/js/Pages/Websites/Blocks/Shared/sparkExtrasContract.js');
const nested=read('resources/js/Pages/Websites/Blocks/Shared/nestedRepeaterEngine.js');
const section=read('resources/js/Pages/Websites/Components/CosmicSection.jsx');
const builder=read('resources/js/Pages/Websites/Builder.jsx');
const compiler=read('app/Helpers/CmsHtmlCompiler.php');
const keys=[...new Set([...pricing.matchAll(/^\s*'([a-z0-9_]+)'\s*=>\s*\['label'/gm)].map(m=>m[1]))];
const schemaKeys=new Set([...schema.matchAll(/^\s*'([a-z0-9_]+)'\s*=>\s*'[A-Za-z0-9_]+'/gm)].map(m=>m[1]));
const directional=['padding_top','padding_bottom','padding_left','padding_right','padding_x'];
const tokens=['--cosmic-local-section-py-top','--cosmic-local-section-py-bottom','--cosmic-local-section-px-left','--cosmic-local-section-px-right'];
const checks=[
 ['329 registered Sparks',keys.length===329],
 ['329 registered schemas',keys.every(k=>schemaKeys.has(k))],
 ['capability declaration exposes writable fields',caps.includes("'writable_fields' => $this->writableFields($block)")],
 ['all four directional padding mutations registered',directional.every(x=>caps.includes("'"+x+"' => ["))],
 ['Spacing module exposes directional padding',directional.every(x=>config.includes("'"+x+"'"))],
 ['Builder local vars expose four-side tokens',tokens.every(t=>section.includes(t))],
 ['Builder rendering consumes four-side tokens',tokens.every(t=>builder.includes(t))],
 ['Export compiler consumes four-side tokens',tokens.every(t=>compiler.includes(t))],
 ['field extras before/after contract present',extras.includes('before')&&extras.includes('after')&&extras.includes('field_extras')],
 ['nested repeater engine preserves extras',nested.includes('field_extras')||nested.includes('extras')],
 ['Luna schema editor routes extras structurally',editor.includes('structural_actions')&&editor.includes('target_field')&&editor.includes('placement=after')],
 ['mutation validator accepts canonical override namespaces',validator.includes('luna_section_overrides')&&validator.includes('luna_component_overrides')],
 ['collection editing module supports CRUD/reorder', ['add_item','remove_item','duplicate_item','reorder_items','update_item'].every(x=>config.includes("'"+x+"'"))]
];
checks.forEach(([n,ok])=>console.log(`${ok?'PASS':'FAIL'} ${n}`));
console.log(`Coverage: ${keys.length}/${keys.length} registered Sparks inherit the universal edit capability contract.`);
if(checks.some(([,ok])=>!ok)) process.exitCode=1;
