import fs from 'node:fs';

const css = fs.readFileSync('resources/css/app.css', 'utf8');
const modal = fs.readFileSync('resources/js/Pages/Websites/Components/AddSectionModal.jsx', 'utf8');
const distinctive = fs.readFileSync('resources/js/Pages/Websites/Blocks/Expansion/DistinctiveExpansionBatch6.jsx', 'utf8');

const checks = [
  ['About / Content routing remains present', /about\)\s*\|\|\s*haystack\.includes\("content"\)|haystack\.includes\("about"\).*haystack\.includes\("content"\)/s.test(modal)],
  ['Chapter Index remains a dark/light theme-owned Spark', distinctive.includes('about_chapter_index_premium') && distinctive.includes('background:var(--b6-bg);color:var(--b6-text)')],
  ['Fallback preview has explicit isolation marker', modal.includes('className="cosmic-preview-isolation cosmic-spark-layout-host w-full" data-cosmic-preview-isolation="true"')],
  ['Builder-registry preview already has isolation marker', modal.includes('data-cosmic-add-spark-preview="true"') && modal.includes('data-cosmic-preview-isolation="true"')],
  ['Dialog heading repair skips Spark previews', css.includes('h1:not(.cosmic-preview-isolation *)') && css.includes('h2:not(.cosmic-preview-isolation *)')],
  ['Dialog text repair skips Spark previews', css.includes('p:not(.cosmic-preview-isolation *)') && css.includes('[class*="text-slate-400"]:not(.cosmic-preview-isolation *)')],
  ['Dialog background repair skips Spark previews', css.includes('[class*="bg-[#1"]:not(.cosmic-preview-isolation *)')],
  ['Dialog form repair skips Spark previews', css.includes('input:not(.cosmic-preview-isolation *):not([type="checkbox"])')],
  ['Add Section preview host no longer inherits forced text fill', !/#cosmic-add-section-modal\[data-cosmic-app-modal="add-section"\]\[data-appearance="light"\]\s*\{[^}]*-webkit-text-fill-color/s.test(css)],
  ['Add Section stage no longer inherits forced text fill', !/#cosmic-add-section-modal\[data-cosmic-app-modal="add-section"\]\[data-appearance="light"\][^\{]*\.cosmic-add-section-stage\s*\{[^}]*-webkit-text-fill-color/s.test(css)],
  ['All Section Types has stable ownership class', modal.includes('cosmic-add-section-all-types')],
  ['All Section Types has explicit light contrast', css.includes('#cosmic-add-section-modal[data-appearance="light"] .cosmic-add-section-all-types')],
  ['Strong action selector no longer mistakes hover:bg-violet for base background', !css.includes(':root[data-theme="light"] button[class*="bg-violet-"]') && css.includes('button[class*=" bg-violet-"]')],
  ['All Section Types navigation remains wired', modal.includes('aria-label="Back to all section types"') && modal.includes("setPickerStage('categories')")],
];

let failed = 0;
for (const [name, ok] of checks) {
  console.log(`${ok ? 'PASS' : 'FAIL'} ${name}`);
  if (!ok) failed++;
}

if (failed) {
  console.error(`\nBatch 2 hotfix audit failed: ${failed} check(s).`);
  process.exit(1);
}
console.log('\nBatch 2 hotfix audit passed.');
