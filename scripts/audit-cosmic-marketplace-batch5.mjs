import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const modal = read('resources/js/Pages/Dashboard/Components/NewWebsiteModal.jsx');
const checkout = read('resources/js/Pages/Marketplace/Checkout.jsx');
const builder = read('resources/js/Pages/Websites/Builder.jsx');
const provisioning = read('app/Services/MarketplaceWebsiteProvisioningService.php');
const acquisition = read('app/Services/MarketplaceAcquisitionService.php');
const mode = read('app/Support/WebsiteCreationMode.php');

const checks = [
  ['canonical marketplace creation mode remains defined', mode.includes("public const MARKETPLACE = 'marketplace';")],
  ['create chooser labels Marketplace as complete website path', modal.includes('Browse Complete Websites') && modal.includes('complete professionally designed website')],
  ['Marketplace chooser browsing does not create a Website', modal.includes('if(mode === "marketplace")') && modal.includes('window.location.assign(destination)')],
  ['checkout remains Cosmic Credits based', checkout.includes("purchaseMode = 'cosmic_credits'") && checkout.includes('Cosmic Credits')],
  ['checkout CTA clearly installs a website', checkout.includes('Install Website — ${formatCredits(requiredCredits)} Credits')],
  ['checkout explains design kit retention', checkout.includes('Installed design kit stays attached for future pages')],
  ['shared Agency website limit is checked before new debit', acquisition.indexOf('website_limit_reached') < acquisition.indexOf('$this->wallet->debit(')],
  ['Marketplace debit remains idempotent', acquisition.includes("$reference = 'marketplace-install:'") && acquisition.includes("->where('reference', $reference)")],
  ['post-install handoff prefers Home page Builder', provisioning.includes("->where('slug', 'home')") && provisioning.includes("route('pages.builder'")],
  ['post-install handoff identifies Marketplace creation mode', provisioning.includes("'creation_mode' => WebsiteCreationMode::MARKETPLACE") || provisioning.includes("'creation_mode' => 'marketplace'") && provisioning.includes("'marketplace_setup' => 1")],
  ['post-install Luna prompt preserves design kit', provisioning.includes('Preserve the installed Marketplace design kit and visual language by default.')],
  ['Builder recognizes Marketplace origin', builder.includes("const marketplaceCreationMode = websiteCreationMode === 'marketplace' || marketplaceWebsite;")],
  ['Builder shows Marketplace install handoff', builder.includes('data-cosmic-marketplace-onboarding') && builder.includes('Marketplace website installed')],
  ['Builder has explicit Luna personalization action', builder.includes('Personalize with Luna') && builder.includes('Preserve the installed design kit and visual language by default.')],
  ['generic Theme switch is hidden for Marketplace websites', builder.includes('capabilities.canChangeTheme && !marketplaceWebsite && (')],
  ['Marketplace origin badge is visible in Builder chrome', builder.includes('data-cosmic-creation-origin') && builder.includes('Marketplace')],
  ['Marketplace design kit context remains wired to Luna', builder.includes('preserve_marketplace_design_kit: marketplaceWebsite') && builder.includes('marketplace_context')],
];

let pass = 0;
for (const [label, ok] of checks) {
  console.log(`${ok ? 'PASS' : 'FAIL'} ${label}`);
  if (ok) pass++;
}
console.log(`\n${pass}/${checks.length} checks passed.`);
if (pass !== checks.length) process.exit(1);
