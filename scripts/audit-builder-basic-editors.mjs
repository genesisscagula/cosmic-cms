import fs from 'node:fs';
const file = new URL('../resources/js/Pages/Websites/Builder.jsx', import.meta.url);
const src = fs.readFileSync(file, 'utf8');
const checks = [
  ['popup direct editor is primary', /editSession\.target[\s\S]{0,900}stageDirectElementText/],
  ['text direct editor exists', /\['heading','text','label','button'\]/],
  ['long text textarea exists', /<textarea value=\{lunaDirectText\}/],
  ['button URL editor exists', /Button URL[\s\S]{0,220}lunaDirectLink/],
  ['direct content stages immediately', /onChange=\{e=>stageDirectElementText\(e\.target\.value\)\}/],
  ['image URL working state exists', /editSessionMediaUrl/],
  ['image URL preview mutation exists', /const previewSelectedMediaUrl = \(\) =>/],
  ['media library available in popup', /setLunaMediaLibraryPurpose\('element'\)[\s\S]{0,180}Media Library/],
  ['trial upload available in popup', /logoUploadRef\.current\?\.click\?\.\(\):lunaImageUploadRef\.current\?\.click/],
  ['header CTA enters edit session', /const openLunaForHeaderCta[\s\S]{0,700}beginEditSession/],
  ['header logo enters edit session', /const openLunaForLogo[\s\S]{0,650}beginEditSession/],
  ['footer logo enters edit session', /const openLunaForFooterLogo[\s\S]{0,650}beginEditSession/],
  ['footer scalar element enters edit session', /const openLunaForFooterElement[\s\S]{0,800}beginEditSession/],
  ['popup cancel contract retained', /onClick=\{cancelEditSession\}/],
  ['popup apply contract retained', /onClick=\{applyEditSession\}/],
  ['draft messaging used during edit session', /editSession\?\.open\?'Draft updated':'Saved'/],
];
let passed=0;
for (const [name, re] of checks) {
  if (re.test(src)) { console.log(`PASS  ${name}`); passed++; }
  else { console.error(`FAIL  ${name}`); }
}
console.log(`\n${passed}/${checks.length} Builder basic-editor contract checks passed.`);
if (passed !== checks.length) process.exit(1);
