import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname), '..');
const css = fs.readFileSync(path.join(root, 'resources/css/app.css'), 'utf8');
const builder = fs.readFileSync(path.join(root, 'resources/js/Pages/Websites/Builder.jsx'), 'utf8');

const checks = [];
const check = (name, ok) => checks.push({ name, ok: Boolean(ok) });

check('Builder shell has native 1:1 zoom contract', /\.cosmic-builder-shell\s*\{[\s\S]*?zoom:\s*1\s*!important/.test(css));
check('Builder header backdrop blur is disabled', /\[data-cosmic-builder-header\]\s*\{[\s\S]*?backdrop-filter:\s*none\s*!important/.test(css));
check('Builder canvas stays unscaled', /\.cosmic-builder-canvas,[\s\S]*?\.cosmic-preview-isolation\s*\{[\s\S]*?zoom:\s*1\s*!important[\s\S]*?transform:\s*none\s*!important/.test(css));
check('Builder chrome controls have native text-rendering contract', /\[data-cosmic-builder-header\][\s\S]*?text-rendering:\s*auto/.test(css));
check('Edit-session typography included in chrome guard', /\.cosmic-edit-session-dialog\s+:where\(/.test(css));
check('Render shell remains explicitly separate', /data-cosmic-render-shell="1"/.test(builder) && /data-cosmic-preview-isolation="true"/.test(builder));
check('Builder JSX exposes preview isolation boundary', /data-cosmic-preview-isolation="true"/.test(builder));
check('Builder JSX exposes render-shell boundary', /data-cosmic-render-shell="1"/.test(builder));
check('Builder JSX has named Builder header boundary', /data-cosmic-builder-header/.test(builder));
const zoomValues = [...css.matchAll(/zoom:\s*([^;!}]+)/gi)].map((m) => m[1].trim());
check('No CSS zoom other than native 1 in app.css', zoomValues.every((value) => value === '1'));
check('No app root scale transform introduced by Batch 3', !/\.cosmic-builder-shell\s*\{[^}]*transform:\s*scale/i.test(css));
check('Spark image/visual transforms remain available in source', /transform:scale\(/.test(builder) || fs.readdirSync(path.join(root,'resources/js/Pages/Websites/Blocks/Expansion')).length > 0);

let failed = 0;
for (const {name, ok} of checks) {
  console.log(`${ok ? 'PASS' : 'FAIL'} — ${name}`);
  if (!ok) failed++;
}
console.log(`\n${checks.length - failed}/${checks.length} PASS`);
if (failed) process.exit(1);
