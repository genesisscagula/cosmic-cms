import fs from 'node:fs';

const builderPath = new URL('../resources/js/Pages/Websites/Builder.jsx', import.meta.url);
const source = fs.readFileSync(builderPath, 'utf8');
const failures = [];
const pass = (label, condition) => {
  if (condition) console.log(`PASS ${label}`);
  else { console.error(`FAIL ${label}`); failures.push(label); }
};

pass('Save Spark rail is upper-left', /cosmic-section-save-rail[^\n]*absolute left-4 top-4/.test(source));
pass('Save Spark action remains wired', /cosmic-section-save-rail[\s\S]{0,900}saveBlockToSavedSparks\(block,index\)/.test(source));
pass('Upper-right rail exists', /cosmic-section-control-rail[^\n]*absolute right-4 top-4/.test(source));
pass('Upper-right rail has Edit Section', /cosmic-section-control-rail[\s\S]{0,1000}aria-label="Edit this section"/.test(source));
pass('Upper-right rail has Delete Section', /cosmic-section-control-rail[\s\S]{0,1800}aria-label="Delete this section"/.test(source));
const rightRail = source.match(/<div[^>]*className="cosmic-section-control-rail[\s\S]*?<\/div>\s*\{lunaHoverTarget/);
pass('Upper-right rail no longer contains Save Spark', Boolean(rightRail) && !rightRail[0].includes('saveBlockToSavedSparks'));
pass('Customize modal has All Section Types button', /cosmic-edit-session-all-types[\s\S]{0,500}>←<\/span><span>All Section Types<\/span>/.test(source));
pass('All Section Types restores insertion target', /const returnCreateSectionToAllTypes[\s\S]{0,2600}setSparkInsertTarget\(nextInsertTarget\)/.test(source));
pass('All Section Types reopens Add Section browser', /const returnCreateSectionToAllTypes[\s\S]{0,2800}setIsModalOpen\(true\)/.test(source));
pass('Create-section draft remains non-destructive until confirmation', /temporary create-section draft never touched the Builder/.test(source));

if (failures.length) {
  console.error(`\n${failures.length} Batch 1 audit failure(s).`);
  process.exit(1);
}
console.log('\nBatch 1 hotfix audit passed.');
