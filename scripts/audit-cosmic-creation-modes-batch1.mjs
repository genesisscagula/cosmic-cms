import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const checks = [];
const check = (label, ok) => checks.push([label, Boolean(ok)]);
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

const modal = read('resources/js/Pages/Dashboard/Components/NewWebsiteModal.jsx');
const controller = read('app/Http/Controllers/WebsiteController.php');
const marketplace = read('app/Services/MarketplaceWebsiteProvisioningService.php');
const contract = read('app/Support/WebsiteCreationMode.php');

for (const label of ['Build with Luna AI','Build with Sparks','Build with Page Builder','Choose from Marketplace']) {
  check(`chooser contains ${label}`, modal.includes(label));
}
for (const mode of ['luna_ai','sparks','page_builder','marketplace']) {
  check(`creation contract contains ${mode}`, contract.includes(`'${mode}'`));
}
check('dashboard submit sends creation_mode', modal.includes('creation_mode'));
check('website store validates creation_mode', controller.includes("'creation_mode' => 'nullable|string|in:'"));
check('website store persists settings.creation', controller.includes("'creation' => [") && controller.includes("'source' => 'dashboard'"));
check('marketplace provisioning persists creation mode', (marketplace.includes("'mode' => WebsiteCreationMode::MARKETPLACE") || marketplace.includes("'mode' => 'marketplace'")) && marketplace.includes("'source' => 'marketplace'"));
check('marketplace browsing remains direct, not website POST', modal.includes('window.location.assign(destination)'));
check('website limit service remains in store flow', controller.includes('AgencyWebsiteLimitService') && controller.includes('validationMessage($request->user())'));
check('no creation-mode migration introduced', !fs.readdirSync(path.join(root,'database/migrations')).some(name => name.includes('creation_mode')));

const failed = checks.filter(([,ok]) => !ok);
for (const [label,ok] of checks) console.log(`${ok ? 'PASS' : 'FAIL'} ${label}`);
console.log(`\n${checks.length - failed.length}/${checks.length} checks passed.`);
if (failed.length) process.exit(1);
