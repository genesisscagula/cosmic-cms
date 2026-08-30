import fs from 'node:fs';

const controller = fs.readFileSync('app/Http/Controllers/CustomSparkController.php', 'utf8');
const service = fs.readFileSync('app/Services/LunaSectionImageService.php', 'utf8');
const resolver = fs.readFileSync('app/Services/ImageSlotResolver.php', 'utf8');

const checks = [
  ['premade Start Blank invokes hydration', controller.includes('$sectionImages->hydrateMissingImages(')],
  ['hydration happens only on premade create branch', controller.includes('if ($premadeCreateIntent && $sectionImages->mode($prompt) === \'unsplash\')')],
  ['hydration is applied before compliance validation', controller.indexOf('hydrateMissingImages(') < controller.indexOf('$sparkRequestCompliance->validate($prompt, $replacement, $selectedKey)')],
  ['response exposes hydration telemetry', controller.includes("'image_hydration' => $imageHydration ?")],
  ['service scans ImageSlotResolver slots', service.includes('$this->imageSlots->slots($block)')],
  ['service preserves nonblank real images', service.includes('private function shouldAutoHydrateValue') && service.includes("if ($url === '') return true")],
  ['generic bundled placeholders are hydratable', service.includes("'/cms-images/default/'") && service.includes("'/cosmic-images/cosmic-fallback.svg'")],
  ['each slot gets contextual search query', service.includes('slotContextQuery(') && service.includes("'services' => 'professional service work editorial photography'")],
  ['duplicate photo retry exists', service.includes('Different image-grid slots should not silently collapse') && service.includes('isset($usedUrls[$url])')],
  ['Unsplash failure falls back locally', service.includes('$this->smartImages->localFallback($industry)')],
  ['nested image primitives with src are discoverable', resolver.includes("strtolower((string) ($value['type'] ?? '')) === 'image'") && resolver.includes("$path.'.src'")],
  ['testimonial avatar protection remains', resolver.includes("str_contains($normalizedType, 'testimonial')")],
];

let passed = 0;
for (const [name, ok] of checks) {
  console.log(`${ok ? 'PASS' : 'FAIL'} - ${name}`);
  if (ok) passed++;
}
console.log(`\n${passed}/${checks.length} PASS`);
if (passed !== checks.length) process.exit(1);
