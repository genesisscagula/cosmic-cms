import fs from 'node:fs';

const builder = fs.readFileSync('resources/js/Pages/Websites/Builder.jsx','utf8');
const modal = fs.readFileSync('resources/js/Pages/Websites/Components/AddSectionModal.jsx','utf8');
const checks = [
  ['AddSectionModal exposes onCustomize', modal.includes('onCustomize = null')],
  ['owned picker routes to customizer', modal.includes('onCustomize(block, { spark, insertionContext })')],
  ['picker no longer commits immediately when customizer exists', modal.includes('if (onCustomize) {') && modal.includes('Customize Section')],
  ['Builder wires Add Section to editor', builder.includes('onCustomize={beginNewSectionEdit}')],
  ['new section draft helper exists', builder.includes('const beginNewSectionEdit = (block, meta = {}) => {')],
  ['new section is added to draft blocks', builder.includes('draftBlocks.push(newBlock)')],
  ['insert above/below supported in draft', builder.includes("insertTarget.position === 'above'") && builder.includes('draftBlocks.splice(insertionIndex, 0, newBlock)')],
  ['creation mode is explicit', builder.includes("commitMode: 'create_section'")],
  ['Builder state is not changed on open', !builder.slice(builder.indexOf('const beginNewSectionEdit'), builder.indexOf('const editSessionHasChanges')).includes("setData('blocks'")],
  ['Luna scope points to temporary draft index', builder.includes("setLunaScope({ type:'section', blockIndex:insertionIndex, label:sparkName })")],
  ['final commit writes draft blocks', builder.includes("const creatingSection = session.commitMode === 'create_section'") && builder.includes("setData('blocks', normalizeRenderKeys(draft.blocks || []))")],
  ['new section does not mutate header/footer on commit', builder.includes('if (!creatingSection) {')],
  ['cancel discards draft', builder.includes('setEditSessionDraft(null)') && builder.includes('const cancelEditSession = () => {')],
  ['new section button says Add Section', builder.includes("editSession.commitMode === 'create_section' ? 'Add Section' : 'Apply'")],
  ['popup explains frozen Builder', builder.includes('The Builder stays unchanged until you add it.')],
  ['new section editor preserves Luna contextual chat', builder.includes('setEditSessionLunaStartIndex(lunaMessages.length)')],
  ['new section can change related Spark layouts', builder.includes('previewSectionSparkLayout(editSession.blockIndex,layout)')],
  ['creation draft disables nested add-above/below toolbar', builder.includes("editSession.commitMode !== 'create_section'")],
  ['successful add smooth scrolls to committed section', builder.includes("target.scrollIntoView({ behavior:'smooth', block:'center' })")],
  ['legacy onAdd fallback remains', modal.includes('onAdd(block);')],
];
let failed = 0;
for (const [name, ok] of checks) {
  console.log(`${ok ? 'PASS' : 'FAIL'} ${name}`);
  if (!ok) failed++;
}
console.log(`Batch 8 Add Section editor contracts: ${checks.length-failed}/${checks.length} PASS`);
process.exit(failed ? 1 : 0);
