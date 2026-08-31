import fs from 'node:fs';
import path from 'node:path';
const base = path.resolve('resources/js/Pages/Websites/Blocks/Expansion');
const files = ['ImageRichExpansionBatch1.jsx','ImageRichExpansionBatch2.jsx','ImageRichExpansionBatch3.jsx'];
let schemas=0, failed=false;
for (const file of files) {
  const source=fs.readFileSync(path.join(base,file),'utf8');
  const count=(source.match(/Schema=/g)||[]).length; schemas+=count;
  const checks={
    'container root':/container-type:inline-size/.test(source),
    '1100 canvas breakpoint':/@container\(max-width:1100px\)/.test(source),
    '900 canvas breakpoint':/@container\(max-width:900px\)/.test(source),
    '640 canvas breakpoint':/@container\(max-width:640px\)/.test(source),
    'viewport 900 preserved':/@media\(max-width:900px\)/.test(source),
    'viewport 640 preserved':/@media\(max-width:640px\)/.test(source),
    'shrink hardening':/overflow-wrap:anywhere/.test(source),
  };
  for (const [name,ok] of Object.entries(checks)) if(!ok){console.error(`FAIL ${file}: ${name}`);failed=true;}
  console.log(`PASS ${file}: ${count} schemas`);
}
if(schemas!==60){console.error(`FAIL expected 60 schemas, found ${schemas}`);failed=true;}
if(failed) process.exit(1);
console.log(`PASS ImageRich Batch 1-3 container parity contract: ${schemas} schemas`);
