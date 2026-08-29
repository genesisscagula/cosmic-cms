import fs from 'node:fs';

const builderPath = 'resources/js/Pages/Websites/Builder.jsx';
const source = fs.readFileSync(builderPath, 'utf8');
const checks = [];
const check = (name, pass) => checks.push({ name, pass: Boolean(pass) });

check('Build dropdown exists', source.includes('menuKey="build" label="Build"'));
check('Build menu has Create group', source.includes('>Create</div>'));
check('Add Section is categorized under Build', source.includes('data-cosmic-build-action="add-section"'));
check('Add Section opens modal', source.includes('setSparkInsertTarget(null); setIsModalOpen(true);'));
check('Add Section no longer depends on !aiOnlyBuilder', !/!aiOnlyBuilder\s*&&\s*\(capabilities\.canGenerateAi \|\| trialMode\)[\s\S]{0,300}data-cosmic-build-action="add-section"/.test(source));
check('Templates are categorized under Build', source.includes('data-cosmic-build-action="templates"'));
check('Generate Page is categorized under Build', source.includes('data-cosmic-build-action="generate-page"'));
check('Saved Sparks library is categorized under Build', source.includes('data-cosmic-build-action="saved-sparks"'));
check('Saved Sparks remains authenticated-only', source.includes('!trialMode && capabilities.canManageBlocks && ('));
check('Build actions close dropdown before opening popup', /data-cosmic-build-action="add-section"[\s\S]{0,220}setBuilderToolbarMenu\(null\)/.test(source));

check('Registered preview has a first-class toolbar action', source.includes('data-cosmic-builder-preview'));
check('Registered preview is non-trial only', source.includes('{!trialMode && previewUrl && ('));
check('Preview uses safe new-tab rel', source.includes('rel="noopener noreferrer"\n                                    data-cosmic-builder-preview'));

check('Publish dropdown exists', source.includes('menuKey="publish" label="Publish"'));
check('Publish menu has Website group', source.includes('>Website</div>'));
check('Publish Website action is permission gated', /\{capabilities\.canPublish && \([\s\S]{0,250}data-cosmic-publish-action="publish"/.test(source));
check('Publish Website uses save + health pipeline', source.includes('const handlePublish = async () =>') && source.includes('const saved = await saveDraft();') && source.includes("route('websites.health.show', website.id)"));
check('Save Draft remains in Publish', source.includes('data-cosmic-publish-action="save-draft"'));
check('Save as Template remains popup based', source.includes('data-cosmic-publish-action="save-template"') && source.includes('setIsSaveTemplateOpen(true)'));
check('Publish menu has Review group', source.includes('>Review</div>'));
check('Preview is available from Publish review group', source.includes('data-cosmic-publish-action="preview"'));
check('Health action remains hidden from website_editor', /websiteAccessRole !== 'website_editor'[\s\S]{0,320}data-cosmic-publish-action="health"/.test(source));
check('Pre-publish health review popup remains intact', source.includes('id="cosmic-publish-health-review"'));

check('Trial direct Save remains available', source.includes("trialMode ? 'Save changes' : 'Save Draft'"));
check('Trial Preview staging control remains available', source.includes('cosmic-trial-preview'));
check('Trial purchase CTA remains available', source.includes('Buy This Website'));
check('Save Page Template modal remains mounted', source.includes('<SavePageTemplateModal'));
check('Add Section modal remains mounted', source.includes('<AddSectionModal'));
check('Templates modal remains mounted', source.includes('<PageTemplatesModal'));
check('Generate Page modal remains mounted', source.includes('<GeneratePageModal'));

const failed = checks.filter((item) => !item.pass);
for (const item of checks) console.log(`${item.pass ? 'PASS' : 'FAIL'}  ${item.name}`);
console.log(`\nBatch 5 builder contract: ${checks.length - failed.length}/${checks.length} PASS`);
if (failed.length) process.exit(1);
