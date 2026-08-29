import fs from 'node:fs';
const src=fs.readFileSync('resources/js/Pages/Websites/Builder.jsx','utf8');
const checks=[
 ['isolated popup draft exists', src.includes('editSessionDraft') && src.includes('updatePopupDraft')],
 ['apply is only builder bridge', src.includes("setData('blocks', normalizeRenderKeys(draft.blocks || []))")],
 ['text fields stage directly', src.includes('onChange={e=>stageDirectElementText(e.target.value)}')],
 ['button url stages directly', src.includes('onChange={e=>stageDirectElementLink(e.target.value)}')],
 ['text popup has no update-preview button', !/saveLunaDirectElement[^\n]{0,250}>Update preview</.test(src)],
 ['image popup has no update-preview button', !/previewSelectedMediaUrl[^\n]{0,250}>Update preview</.test(src)],
 ['image source is popup local field', src.includes('src={editSessionMediaUrl || lunaElementTarget.currentValue}')],
 ['image URL commits to popup draft', src.includes('onBlur={previewSelectedMediaUrl}')],
 ['media replacement uses popup draft', src.includes("if(editSession?.open) setPopupDraftBlocks(nextBlocks)")],
 ['logo replacement uses popup draft', src.includes('if(editSession?.open) setPopupDraftHeader(nextHeader)') && src.includes('if(editSession?.open) setPopupDraftFooter(nextFooter)')],
 ['Luna scalar mutation syncs visible field', src.includes('syncPopupElementFieldsFromAiMutation') && src.includes('setLunaDirectText(contentDiff.after)')],
 ['Luna image mutation syncs popup image', src.includes('setEditSessionMediaUrl(imageDiff.after)')],
 ['header Luna rewrite syncs field', src.includes("String(lunaElementTarget?.fieldPath||'')==='header.cta'")],
 ['footer Luna rewrite syncs field', src.includes("footerFieldPath.startsWith('footer.')")],
 ['cancel discards draft', src.includes('setEditSessionDraft(null);')],
 ['apply button remains explicit', src.includes('onClick={applyEditSession}') && src.includes('>Apply</button>')],
];
let passed=0;
for(const [name,ok] of checks){console.log(`${ok?'PASS':'FAIL'} ${name}`);if(ok)passed++;}
console.log(`\n${passed}/${checks.length} checks passed`);
if(passed!==checks.length)process.exit(1);
