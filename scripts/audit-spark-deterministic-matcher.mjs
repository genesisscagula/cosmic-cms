import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = (p) => fs.readFileSync(path.join(root, p), 'utf8');
const matcher = read('app/Services/SparkIntentMatcherService.php');
const search = read('app/Services/AiLibrarySearchService.php');
const registry = read('app/Cosmic/Pricing/BlockPricingRegistry.php');
const catalog = read('app/Services/SparkCatalog.php');
const command = read('app/Console/Commands/AuditSparkDeterministicMatcher.php');

const sparkKeys = [...registry.matchAll(/^\s*'([^']+)'\s*=>\s*\[/gm)].map((m) => m[1]);
const uniqueKeys = new Set(sparkKeys);

const checks = [
  ['matcher service exists', matcher.includes('class SparkIntentMatcherService')],
  ['matcher is deterministic/no OpenAI dependency', !matcher.includes('OpenAIClient') && !matcher.includes('->chat(')],
  ['full SparkCatalog scan', matcher.includes('SparkCatalog::all()')],
  ['structured request analysis', matcher.includes("'semantic'") && matcher.includes("'media'") && matcher.includes("'layout'") && matcher.includes("'industry'")],
  ['hard semantic mismatch penalty', matcher.includes("$raw -= 34") && matcher.includes('semantic mismatch')],
  ['hard media mismatch penalty', matcher.includes("$raw -= 18") && matcher.includes('media mismatch')],
  ['confidence tiers exposed', matcher.includes("'high'") && matcher.includes("'medium'") && matcher.includes("'weak'") && matcher.includes("'strong_match'")],
  ['compact AI shortlist contract', matcher.includes('shortlistForAi') && matcher.includes("'capabilities'") && matcher.includes("'confidence'")],
  ['library search uses local matcher first', search.includes("$this->sparkMatcher->shortlistForAi($prompt, 28)")],
  ['AI search cache version bumped', search.includes('ai-library-search:v2:')],
  ['metadata v2 catalog remains source', catalog.includes("'ai_metadata_version' => 2") && catalog.includes("'search_terms' =>")],
  ['runtime matcher audit command added', command.includes('cosmic:audit-spark-matcher') && command.includes('No AI calls were used')],
  ['registered Spark count unchanged', uniqueKeys.size === 329],
  ['no duplicate registered Spark keys', sparkKeys.length === uniqueKeys.size],
];

let pass = 0;
for (const [label, ok] of checks) {
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}`);
  if (ok) pass++;
}
console.log(`\n${pass}/${checks.length} checks passed; ${uniqueKeys.size} registered Sparks scanned.`);
process.exit(pass === checks.length ? 0 : 1);
