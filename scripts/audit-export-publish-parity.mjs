import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const compiler = fs.readFileSync(path.join(root, 'app/Helpers/CmsHtmlCompiler.php'), 'utf8');
const pagePublisher = fs.readFileSync(path.join(root, 'app/Services/PagePublisher.php'), 'utf8');
const pageController = fs.readFileSync(path.join(root, 'app/Http/Controllers/PageController.php'), 'utf8');

const checks = [
  ['compiler applies field extras per fragment', compiler.includes('applySparkFieldExtrasToFragment($fragment, $block)')],
  ['compiler renders before/after markers', compiler.includes("data-cosmic-field-extra-placement='{$placement}'")],
  ['compiler emits field extras core CSS', compiler.includes('data-cosmic-field-extras-core')],
  ['compiler emits AI Flex logical paths', compiler.includes('data-cosmic-ai-flex-logical-path')],
  ['compiler emits AI Flex stable ids', compiler.includes('data-cosmic-ai-flex-stable-id')],
  ['compiler emits AI Flex structure marker', compiler.includes("data-cosmic-ai-flex-structure='rows-columns-extras'")],
  ['compiler keeps empty AI Flex image visible', compiler.includes("data-cosmic-extra-empty-media='image'")],
  ['compiler keeps empty AI Flex video visible', compiler.includes("data-cosmic-extra-empty-media='video'")],
  ['publisher uses CmsHtmlCompiler', pagePublisher.includes('CmsHtmlCompiler::compile')],
  ['page preview/export routes use CmsHtmlCompiler', pageController.includes('CmsHtmlCompiler::compile')],
];

const failed = checks.filter(([, ok]) => !ok);
if (failed.length) {
  console.error('Batch 6 source wiring audit FAILED');
  for (const [label] of failed) console.error(` - ${label}`);
  process.exit(1);
}
console.log(`Batch 6 source wiring audit PASS (${checks.length}/${checks.length})`);
