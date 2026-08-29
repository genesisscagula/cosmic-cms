import fs from 'node:fs';
import path from 'node:path';

const root=process.cwd();
const read=(p)=>fs.existsSync(p)?fs.readFileSync(p,'utf8'):'';
const controller=read(path.join(root,'app/Http/Controllers/CustomSparkController.php'));
const editor=read(path.join(root,'app/Services/LunaSparkSchemaEditorService.php'));
const contract=read(path.join(root,'app/Services/SparkTailwindSchemaContract.php'));
const runtime=read(path.join(root,'resources/js/Pages/Websites/Blocks/Shared/sparkTailwindRuntime.js'));
const compiler=read(path.join(root,'app/Helpers/CmsHtmlCompiler.php'));
const parity=read(path.join(root,'scripts/audit-spark-render-parity.mjs'));

const pos=(s,n)=>s.indexOf(n);
const before=(a,b)=>pos(controller,a)>=0 && pos(controller,b)>=0 && pos(controller,a)<pos(controller,b);
const checks={
  'selected Spark ownership computed before legacy radius handler': before('$schemaOwnsSelectedSparkEdit=', '$localCardRadiusRequest='),
  'legacy radius handler gated away from selected Spark': controller.includes('$localCardRadiusRequest=!$schemaOwnsSelectedSparkEdit'),
  'legacy surface handler gated away from selected Spark': controller.includes('$localCardSurfaceRequest=!$schemaOwnsSelectedSparkEdit'),
  'background shortcut gated away from selected Spark': controller.includes('$backgroundAction=$schemaOwnsSelectedSparkEdit\n            ? null'),
  'layout shortcut gated away from selected Spark': controller.includes('$sectionLayoutAction=$schemaOwnsSelectedSparkEdit\n            ? null'),
  'typography shortcut bypasses Sparks route': controller.includes("data_get($canonicalIntent,'routing.menu_scope')==='sparks'\n            ? null"),
  'schema editor receives clicked element context': editor.includes('LOCKED ELEMENT CONTEXT') && editor.includes('lockedElementContext($elementContext)'),
  'full context in / minimal patch out': editor.includes('FULL EDITABLE SPARK SCHEMA') && editor.includes('FULL CURRENT TAILWIND SCHEMA') && editor.includes('MINIMAL PATCHES'),
  'negation and preserve constraints are binding': editor.includes('Negation/preservation is binding'),
  'unsupported visual intent fails closed': editor.includes('return empty patches rather than changing an unrelated property'),
  'multiple compatible edits allowed in one call': editor.includes('multiple compatible visual changes'),
  'specific repeater target preferred over shared slot': editor.includes('MUST be preferred for singular item requests'),
  'structural utilities protected': editor.includes('Structural renderer utilities must be preserved') && editor.includes('protected_tailwind_removed'),
  'unknown editable paths rejected': editor.includes('editable_patch_path_invalid'),
  'unknown Tailwind slots rejected': editor.includes('tailwind_patch_slot_invalid'),
  'schema shape guarded': editor.includes('editable_shape_changed'),
  'universal section media supports image/video/poster': controller.includes('universal_background_image_url') && controller.includes('universal_background_video_url') && controller.includes('universal_background_video_poster_url'),
  'Spark schema edit fails closed rather than legacy fallback': controller.includes('Fail closed for the nested Sparks path'),
  'shared + scoped runtime resolvers exist': runtime.includes('sparkTwItem') && runtime.includes('sparkTwPath'),
  'static compiler has scoped resolver': compiler.includes('sparkTwPath(') && compiler.includes('resolveScopedStyle'),
  'render parity audit remains installed': parity.includes('Unbridged className candidates') && parity.includes('compiler_scoped_resolver'),
  'legacy V1 slots remain supported': contract.includes("'slots'") || runtime.includes('luna_tailwind_schema'),
};

const scenarios=[
 ['background image in this section','universal media + selected Spark preservation'],
 ['video background in this section','universal media; no hero replacement'],
 ['make this section more premium','full-schema style patch; keep layout'],
 ['clear top, fade to white at bottom','overlay slot only'],
 ['do not change radius','negation preserved; no radius shortcut'],
 ['make only the second card dark','item-specific scoped slot'],
 ['all cards dark except second','shared style plus item override'],
 ['third title larger on mobile only','item-specific responsive Tailwind patch'],
 ['background image and square cards','multi-change single schema response where compatible'],
 ['a little more','conversation + locked target follow-up'],
 ['unsupported renderer change','empty patch / fail closed'],
];

console.log('Cosmic Luna torture-QA contract audit');
let failed=0;
for(const [name,ok] of Object.entries(checks)){
 console.log(`${ok?'PASS':'FAIL'} ${name}`); if(!ok) failed++;
}
console.log(`\nScenario contract matrix: ${scenarios.length} representative real-user requests`);
for(const [q,expected] of scenarios) console.log(`- ${q} => ${expected}`);
console.log(`\n${Object.keys(checks).length-failed}/${Object.keys(checks).length} architecture guards passed.`);
if(failed) process.exit(1);
