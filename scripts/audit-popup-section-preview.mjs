import fs from 'node:fs';

const file = 'resources/js/Pages/Websites/Builder.jsx';
const source = fs.readFileSync(file, 'utf8');
const checks = [
  ['popup draft exists', /const \[editSessionDraft, setEditSessionDraft\] = useState\(null\)/],
  ['popup state preferred over builder', /const currentPopupEditState = \(\) => editSession\?\.open && editSessionDraft/],
  ['section preview marker', /data-popup-section-preview="true"/],
  ['draft block rendered in popup', /renderBlock\(activeBlock, editSession\.blockIndex\)/],
  ['preview interactions disabled', /pointer-events-none min-w-0/],
  ['related layouts use popup draft', /const popupState = currentPopupEditState\(\)/],
  ['layout mutation goes to popup', /setPopupDraftBlocks\(blocks\)/],
  ['spark content memory retained', /_spark_content_memory/],
  ['overflow preservation retained', /_spark_overflow_preserved/],
  ['contextual Luna surface', /assistantSurface:'contextual_popup'/],
  ['Luna contextual response enters popup blocks', /setPopupDraftBlocks\(normalizedResponseBlocks\)/],
  ['Builder commit happens on Apply', /const applyEditSession = \(\) =>[\s\S]*setData\('blocks'/],
  ['Cancel discards draft', /const cancelEditSession = \(\) =>[\s\S]*setEditSessionDraft\(null\)/],
  ['popup isolation copy present', /changes stay inside this popup until you Apply/],
  ['unapplied-state copy present', /This popup has changes that are not applied yet/],
  ['draft preview is explicitly marked in DOM', /data-popup-section-preview="true"/],
];
let failed = 0;
for (const [name, pattern] of checks) {
  const pass = pattern.test(source);
  console.log(`${pass ? 'PASS' : 'FAIL'}: ${name}`);
  if (!pass) failed++;
}
console.log(`\nPopup section preview audit: ${checks.length - failed}/${checks.length} PASS`);
if (failed) process.exit(1);
