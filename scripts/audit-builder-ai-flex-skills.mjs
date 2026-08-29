import fs from 'node:fs';

const read = (p) => fs.readFileSync(p, 'utf8');
const policy = read('app/Services/LunaAiFlexEditPolicyService.php');
const editor = read('app/Services/LunaSparkSchemaEditorService.php');
const pkg = JSON.parse(read('package.json'));

const checks = [
  ['AI Flex policy service exists', policy.includes('final class LunaAiFlexEditPolicyService')],
  ['Skill directive covers micro styling', policy.includes('Micro styling: border width/color, border radius, shadow')],
  ['Skill directive covers spacing/geometry', policy.includes('Spacing/geometry: padding, margin, gap')],
  ['Skill directive covers responsive edits', policy.includes('Responsive: desktop/tablet/mobile')],
  ['Skill directive covers per-item targeting', policy.includes('Per-item targeting: singular requests')],
  ['Skill directive covers backgrounds/media', policy.includes('Background/media presentation:')],
  ['Skill directive covers effects', policy.includes('Effects: hover/state utilities')],
  ['Minimal mutation rule is explicit', policy.includes('Apply the smallest mutation that satisfies the request')],
  ['Content-only changes reject styling blast radius', policy.includes('content_only_tailwind_mutation')],
  ['Design-only changes reject copy mutation', policy.includes('design_only_content_mutation')],
  ['Micro edit blast radius guard exists', policy.includes('micro_edit_blast_radius_exceeded')],
  ['Background media URLs remain visual-editable', policy.includes("Str::contains($leaf, ['image_url','video_url','media_url','background_image','background_video','poster_url'])")],
  ['Selected-Spark editor receives policy service', editor.includes('private readonly LunaAiFlexEditPolicyService $editPolicy')],
  ['Selected-Spark prompt appends skill contract', editor.includes('$this->editPolicy->directive()')],
  ['Policy guard runs before schema expansion', editor.indexOf('$this->editPolicy->guard(') > -1 && editor.indexOf('$this->editPolicy->guard(') < editor.indexOf('$this->expandPatchResponse(')],
  ['Policy rejections are logged and fail closed', editor.includes("'[LunaRouter] API 4 policy rejected'") && editor.includes("'reason'=>$policyGuard['reason']")],
  ['Existing full schema validator remains after policy', editor.includes('$this->validateResult(')],
  ['Batch 5 audit script is registered', pkg.scripts?.['audit:builder-ai-flex-skills'] === 'node scripts/audit-builder-ai-flex-skills.mjs'],
];

let pass = 0;
for (const [name, ok] of checks) {
  console.log(`${ok ? 'PASS' : 'FAIL'} - ${name}`);
  if (ok) pass++;
}
console.log(`\n${pass}/${checks.length} checks passed`);
if (pass !== checks.length) process.exit(1);
