import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve('resources/js/Pages/Websites/Blocks/Expansion');
const files = [
  ['ImageRichExpansionBatch1.jsx','x'],
  ['ImageRichExpansionBatch2.jsx','b2'],
  ['ImageRichExpansionBatch3.jsx','b3'],
  ['IndustryExpansionBatch4.jsx','b4'],
  ['PremiumExpansionBatch5.jsx','b5'],
  ['DistinctiveExpansionBatch6.jsx','b6'],
];
let failed = 0;
let schemas = 0;
const checks = [];
const check = (ok, label) => { checks.push([ok,label]); if (!ok) failed++; };
for (const [file,prefix] of files) {
  const text = fs.readFileSync(path.join(root,file),'utf8');
  const count = (text.match(/Schema\s*=/g) || []).length;
  schemas += count;
  check(/container-type\s*:\s*inline-size/.test(text), `${file}: container context`);
  check(/@container/.test(text), `${file}: container breakpoints`);
  check(/min-width\s*:\s*0/.test(text), `${file}: shrink-safe children`);
  check(/overflow-wrap\s*:\s*anywhere/.test(text), `${file}: long text wrapping`);
  if (prefix !== 'b6') {
    check(text.includes('--cosmic-local-on-primary'), `${file}: local primary foreground token`);
    check(text.includes('--cosmic-local-bg-primary-surface'), `${file}: local primary surface token`);
    check(text.includes('--cosmic-local-on-surface'), `${file}: local surface foreground token`);
  }
}
const b1 = fs.readFileSync(path.join(root,'ImageRichExpansionBatch1.jsx'),'utf8');
check(/\.x-img img\{[^}]*display:block[^}]*width:100%[^}]*height:100%[^}]*object-fit:cover/.test(b1), 'Batch1 image sizing contract');
const b6 = fs.readFileSync(path.join(root,'DistinctiveExpansionBatch6.jsx'),'utf8');
check(!/repeat\([23],1fr\)/.test(b6), 'Batch6 no raw fractional responsive grids');
check(/b6-orbit-core\{grid-column:1\/-1/.test(b6), 'Services Orbit medium-canvas core spans full row');
check(/b6-chapters\{grid-template-columns:1fr/.test(b6), 'Chapter Index collapses in constrained canvas');
for (const [ok,label] of checks) console.log(`${ok?'PASS':'FAIL'}  ${label}`);
console.log(`\nSchemas covered: ${schemas}`);
console.log(`Checks: ${checks.length-failed}/${checks.length} PASS`);
process.exit(failed ? 1 : 0);
