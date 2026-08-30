import fs from 'node:fs';
import path from 'node:path';

const root=process.cwd();
const read=(p)=>fs.readFileSync(path.join(root,p),'utf8');
const pricing=read('app/Cosmic/Pricing/BlockPricingRegistry.php');
const schema=read('app/AI/Schemas/SchemaManager.php');
const market=read('resources/js/Pages/Websites/Components/SparkRegistry.jsx');
const builder=read('resources/js/Pages/Websites/BlockRegistry.jsx');
const add=read('resources/js/Pages/Websites/Components/AddSectionModal.jsx');

const keys=[...new Set([...pricing.matchAll(/^\s*'([a-z0-9_]+)'\s*=>\s*\['label'/gm)].map(m=>m[1]))];
const schemaKeys=new Set([...schema.matchAll(/^\s*'([a-z0-9_]+)'\s*=>\s*'[A-Za-z0-9_]+'/gm)].map(m=>m[1]));
const curatedKeys=new Set([...market.matchAll(/\btype:\s*["']([a-z0-9_]+)["']/g)].map(m=>m[1]));
const builderKeys=new Set(keys.filter(key => builder.includes(key+':') || builder.includes("'"+key+"':") || builder.includes('"'+key+'":')));
const fallbackKeys=keys.filter(key=>!curatedKeys.has(key) && builderKeys.has(key));
const missingBuilder=keys.filter(key=>!builderKeys.has(key));
const missingSchema=keys.filter(key=>!schemaKeys.has(key));
const visibleKeys=keys.filter(key=>curatedKeys.has(key) || builderKeys.has(key));

const checks=[
 ['329 registered Sparks',keys.length===329],
 ['all registered Sparks have schemas',missingSchema.length===0],
 ['all registered Sparks have Builder renderers',missingBuilder.length===0],
 ['curated preview inventory remains available',curatedKeys.size>=229],
 ['Builder fallback bridges every missing curated preview',visibleKeys.length===329 && fallbackKeys.length===keys.length-[...curatedKeys].filter(k=>keys.includes(k)).length],
 ['fallback is sourced from Builder registry',add.includes('const createBuilderRegistryFallback = (spark) =>') && add.includes('BuilderBlockRegistry[spark?.key]')],
 ['fallback uses Builder schema defaults',add.includes('payload: structuredClone(builderEntry.schema.defaults || {})')],
 ['curated preview wins before fallback',add.includes('registry.get(spark.key) || createBuilderRegistryFallback(spark)')],
 ['eligibility requires Builder component and schema',add.includes('if (!builderEntry?.component)') && add.includes('if (!builderEntry?.schema || typeof builderEntry.schema !== "object")')],
 ['preview uses actual Builder component',add.includes('const Component = registryItem?.component') && add.includes('<Component')],
 ['Quick Add persists Builder defaults',add.includes('const schemaDefaults = BuilderBlockRegistry[selected.key]?.schema?.defaults || {}')],
];

const report={
 audit_version:1,
 scope:'Spark Audit Batch 3 — Add Section 329/329 coverage',
 registered:keys.length,
 curated_preview_count:[...curatedKeys].filter(k=>keys.includes(k)).length,
 builder_fallback_count:fallbackKeys.length,
 add_section_visible_count:visibleKeys.length,
 missing_builder:missingBuilder,
 missing_schema:missingSchema,
 checks:Object.fromEntries(checks.map(([name,ok])=>[name,ok])),
};

fs.mkdirSync(path.join(root,'storage/app/audits'),{recursive:true});
fs.writeFileSync(path.join(root,'storage/app/audits/spark-add-section-batch3.json'),JSON.stringify(report,null,2));
checks.forEach(([name,ok])=>console.log(`${ok?'PASS':'FAIL'} ${name}`));
console.log(`Coverage: ${visibleKeys.length}/${keys.length} registered Sparks visible in Add Section (${report.curated_preview_count} curated + ${fallbackKeys.length} Builder-derived).`);
if(checks.some(([,ok])=>!ok)) process.exitCode=1;
