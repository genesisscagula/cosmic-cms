import fs from 'node:fs';

const builder = fs.readFileSync('resources/js/Pages/Websites/Builder.jsx', 'utf8');
const modal = fs.readFileSync('resources/js/Pages/Websites/Components/AddSectionModal.jsx', 'utf8');
const controller = fs.readFileSync('app/Http/Controllers/CustomSparkController.php', 'utf8');
const saved = fs.readFileSync('app/Http/Controllers/SavedSparkController.php', 'utf8');

const checks = [];
const ok = (name, pass) => checks.push([name, Boolean(pass)]);

ok('blank card creates luna_custom_section', modal.includes("type: 'luna_custom_section'") && modal.includes('startBlankWithLuna'));
ok('blank lane marks skip_spark_match', modal.includes('skip_spark_match: true') && modal.includes("creationSource: 'blank_luna'"));
ok('insert target supports above/below', builder.includes("insertTarget.position === 'above'") && builder.includes('anchorRenderKey'));
ok('cancel is draft-only', /const cancelEditSession = \(\) => \{[\s\S]*?setEditSessionDraft\(null\);[\s\S]*?setEditSession\(null\);/.test(builder));
ok('apply auto-saves only blank Luna generated block', builder.includes("session.creationSource === 'blank_luna'") && builder.includes('appliedBlock?.ai_flex?.generated') && builder.includes('saveAppliedLunaSpark(appliedBlock)'));
ok('Luna Spark source stored', saved.includes("'source' => ['nullable', 'string', 'in:manual,luna']") && saved.includes("'_saved_spark_meta'"));
ok('Luna Sparks filter exists', builder.includes("setSavedSparksFilter('luna')") && builder.includes('Luna Sparks ✦'));
ok('reuse does not auto-overwrite source', builder.includes("creationSource: savedSpark?.source === 'luna' ? 'luna_saved' : 'saved_spark'"));
ok('reuse strips library metadata from page blocks', builder.includes('delete payload._saved_spark_meta;'));
ok('blank popup forces empty generation source', controller.includes('$generationSource = $blankLunaAiFlex ? [] : $current;'));
ok('blank popup marks Spark match skipped', controller.includes("'spark_match_skipped' => $blankLunaAiFlex"));

const popupStart = controller.indexOf('public function popupChat(');
const popupEnd = controller.indexOf('\n    public function ', popupStart + 10);
const popupBody = controller.slice(popupStart, popupEnd > popupStart ? popupEnd : undefined);
ok('zero registeredFallback calls in popup lane', !popupBody.includes('registeredFallback('));

const failures = checks.filter(([, pass]) => !pass);
for (const [name, pass] of checks) console.log(`${pass ? 'PASS' : 'FAIL'}  ${name}`);
console.log(`\n${checks.length - failures.length}/${checks.length} checks passed.`);
if (failures.length) process.exit(1);
