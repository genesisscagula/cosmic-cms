import fs from 'node:fs';

const file = 'resources/js/Pages/Websites/Builder.jsx';
const src = fs.readFileSync(file, 'utf8');
const checks = [
  ['isolated draft state exists', /const \[editSessionDraft, setEditSessionDraft\] = useState\(null\)/],
  ['popup state resolver prefers draft', /const currentPopupEditState = \(\) => editSession\?\.open && editSessionDraft/],
  ['popup draft updater exists', /const updatePopupDraft = \(updater\) =>/],
  ['begin session snapshots builder into draft', /setEditSessionDraft\(cloneBuilderEditValue\(original\)\)/],
  ['cancel discards draft without restoring builder', /const cancelEditSession = \(\) => \{[\s\S]*?setEditSessionDraft\(null\);[\s\S]*?setEditSession\(null\);/],
  ['apply is only bridge back to builder blocks', /const applyEditSession = \(\) => \{[\s\S]*?setData\('blocks', normalizeRenderKeys\(draft\.blocks \|\| \[\]\)\)/],
  ['apply commits draft header', /setData\('global_header', cloneBuilderEditValue\(draft\.global_header \|\| \{\}\)\)/],
  ['apply commits draft footer', /setData\('global_footer', cloneBuilderEditValue\(draft\.global_footer \|\| \{\}\)\)/],
  ['section layout writes popup draft', /const previewSectionSparkLayout[\s\S]*?setPopupDraftBlocks\(blocks\)/],
  ['selected media writes popup draft', /const replaceFirstSelectedMedia[\s\S]*?if\(editSession\?\.open\) setPopupDraftBlocks\(nextBlocks\)/],
  ['selected scalar writes popup draft', /const replaceSelectedScalar[\s\S]*?if\(editSession\?\.open\) setPopupDraftBlocks\(nextBlocks\)/],
  ['contextual AI request sends popup draft', /const requestEditState = lunaAssistantSurface === 'contextual_popup' && editSession\?\.open[\s\S]*?currentPopupEditState\(\)/],
  ['contextual AI response writes popup draft', /if \(lunaAssistantSurface === 'contextual_popup' && editSession\?\.open\) \{[\s\S]*?setPopupDraftBlocks\(normalizedResponseBlocks\)/],
  ['contextual header writes draft only', /requestTargetScope === 'header'\) \{[\s\S]*?setPopupDraftHeader\(response\.header\)/],
  ['contextual footer writes draft only', /requestTargetScope === 'footer'\) \{[\s\S]*?setPopupDraftFooter\(response\.footer\)/],
  ['contextual AI cannot directly change theme key', /response\.theme_key && lunaAssistantSurface !== 'contextual_popup'/],
  ['contextual AI cannot directly change typography', /lunaAssistantSurface !== 'contextual_popup' && response\.typography_settings/],
  ['popup copy states changes remain isolated', /changes stay inside this popup until you Apply/],
];
let failures = 0;
for (const [label, pattern] of checks) {
  const ok = pattern.test(src);
  console.log(`${ok ? 'PASS' : 'FAIL'} ${label}`);
  if (!ok) failures++;
}
console.log(`\n${checks.length - failures}/${checks.length} checks passed.`);
if (failures) process.exit(1);
