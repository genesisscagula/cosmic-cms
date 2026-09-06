import fs from 'node:fs';
const jsx=fs.readFileSync(new URL('../resources/js/Pages/Websites/Blocks/General/LunaCustomSectionBlock.jsx',import.meta.url),'utf8');
const css=fs.readFileSync(new URL('../resources/css/app.css',import.meta.url),'utf8');
const checks={
  dragState:jsx.includes('const [dragState,setDragState]=useState(null)'),
  exactColumn:jsx.includes("is-drop-target")&&jsx.includes('Drop in Column {ci+1}'),
  insertionIndex:jsx.includes('targetIndex=null')&&jsx.includes('column.children.splice(finalIndex,0,node)'),
  insertionLine:jsx.includes('cosmic-lego-insert-line'),
  sameColumnCorrection:jsx.includes('Number(payload.itemIndex)<insertIndex'),
  smartDefaultsPreserved:jsx.includes("_cosmic_style_mode==='global'")&&jsx.includes('_cosmic_context:{columns:columnCount}'),
  colorFamilyCue:css.includes('var(--cosmic-brand-primary,#7c3aed)')&&css.includes('var(--cosmic-color-on-primary,#fff)'),
  primarySurfaceCue:css.includes('[data-cosmic-lego-surface="primary"] .cosmic-lego-column.is-drop-target'),
  reducedMotion:css.includes('@media (prefers-reduced-motion:reduce)'),
};
for(const [k,v] of Object.entries(checks)) console.log(`${v?'PASS':'FAIL'} ${k}`);
if(Object.values(checks).some(v=>!v)) process.exit(1);
console.log(`PASS ${Object.keys(checks).length}/${Object.keys(checks).length} Batch 15 drag/drop targeting assertions`);
