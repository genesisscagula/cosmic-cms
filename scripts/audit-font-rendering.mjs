import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const builder = read('resources/js/Pages/Websites/Builder.jsx');
const header = read('resources/js/Pages/Websites/GenerateHeader.jsx');
const css = read('resources/css/app.css');
const deployment = read('app/Services/DeploymentConnectorArchive.php');
const preview = read('app/Services/PreviewDeploymentService.php');
const appBlade = read('resources/views/app.blade.php');
const compiler = read('app/Helpers/CmsHtmlCompiler.php');

const checks = [
    ['Builder canvas has no fractional viewport width', !/cosmic-builder-canvas[^\n]+(?:\d+(?:\.\d+)?vw|scale\(|\bzoom\b)/.test(builder)],
    ['Builder canvas uses responsive native width', /cosmic-builder-canvas[^\n]+\bw-full\b[^\n]+max-w-\[1560px\]/.test(builder)],
    ['Navigation editor does not blur the site backdrop', /cosmic-nav-link-dialog-overlay/.test(header) && !/cosmic-nav-link-dialog-overlay[^\n]*backdrop-blur/.test(header)],
    ['Dashboard root is protected from whole-tree transforms', /\.cosmic-dashboard-shell,\s*\n\.cosmic-builder-canvas\s*\{[^}]*transform:\s*none\s*!important/s.test(css)],
    ['Builder canvas is protected from whole-tree filters', /\.cosmic-dashboard-shell,\s*\n\.cosmic-builder-canvas\s*\{[^}]*filter:\s*none\s*!important/s.test(css)],
    ['Navigation editor owns explicit readable colors', /\.cosmic-nav-link-dialog__title[^\n]*#0f172a/.test(css) && /\.cosmic-nav-link-dialog__save\s*\{[^}]*#ffffff/s.test(css)],
    ['Live responsive typography is re-emitted after base fallbacks', /\$responsiveTypographyCss\s*=/.test(deployment) && /\{\$responsiveTypographyCss\}<\/style>/.test(deployment)],
    ['Builder and live both load Manrope 400 through 800', /manrope:400,500,600,700,800/.test(deployment) && /manrope:400,500,600,700,800/.test(preview) && /manrope:400,500,600,700,800/.test(appBlade)],
    ['Padding-only Luna wrappers cannot reset Spark typography', /data-luna-design-typography/.test(builder) && /data-luna-design-typography/.test(compiler) && !/\.cosmic-luna-design-host h1\s*\{\s*font-size:\s*var\(--cosmic-local-h1-size,\s*revert\)/.test(builder) && !/\.cosmic-luna-design-host h1\{font-size:var\(--cosmic-local-h1-size,revert\)/.test(compiler)],
];

let failed = 0;
for (const [label, passed] of checks) {
    console.log(`${passed ? 'PASS' : 'FAIL'} ${label}`);
    if (!passed) failed += 1;
}

if (failed) {
    console.error(`\nFont rendering audit failed: ${failed}/${checks.length} checks.`);
    process.exit(1);
}

console.log(`\nFont rendering audit passed: ${checks.length}/${checks.length} checks.`);
