import fs from 'node:fs';

const file = new URL('../resources/js/Pages/Websites/Builder.jsx', import.meta.url);
const source = fs.readFileSync(file, 'utf8');

const checks = [
  ['edit session state exists', 'const [editSession, setEditSession] = useState(null);'],
  ['atomic snapshot includes blocks', 'blocks: cloneBuilderEditValue(data.blocks || [])'],
  ['atomic snapshot includes header', 'global_header: cloneBuilderEditValue(data.global_header || {})'],
  ['atomic snapshot includes footer', 'global_footer: cloneBuilderEditValue(data.global_footer || {})'],
  ['cancel discards isolated draft', 'setEditSessionDraft(null);'],
  ['cancel never commits blocks', 'Builder was never mutated by the popup'],
  ['apply commits the draft atomically', "setData('blocks', normalizeRenderKeys(draft.blocks || []));"],
  ['local hover target opens edit session', "beginEditSession({ scope:isSection?'section':'element', blockIndex, label, target });"],
  ['inline target event opens edit session', "beginEditSession({ scope:'element', blockIndex, label:targetLabel, target });"],
  ['global luna suppressed during popup', 'lunaChatOpen && !editSession?.open'],
  ['global luna always opens whole page', "openLunaChat({type:'page',blockIndex:null,label:'Whole Page'})"],
  ['popup apply action exists', 'onClick={applyEditSession}'],
  ['popup cancel action exists', 'onClick={cancelEditSession}'],
];

let failed = 0;
for (const [label, needle] of checks) {
  const ok = source.includes(needle);
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}`);
  if (!ok) failed += 1;
}

if (failed) {
  console.error(`\n${failed} Builder edit-session contract check(s) failed.`);
  process.exit(1);
}
console.log(`\n${checks.length}/${checks.length} Builder edit-session contract checks passed.`);
