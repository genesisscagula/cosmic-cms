import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const css = fs.readFileSync(path.join(root, 'resources/css/app.css'), 'utf8');
const checks = [];
const check = (name, pass) => checks.push({ name, pass: Boolean(pass) });

check('welcome surface covered', css.includes('.cosmic-welcome-page'));
check('public inner pages covered', css.includes('.cosmic-public-light'));
check('guest/auth pages covered', css.includes('.cosmic-guest-light') && css.includes('.cosmic-authenticated-shell'));
check('dashboard surfaces covered', css.includes('.cosmic-app-shell') && css.includes('.cosmic-dashboard-shell'));
check('root zoom normalized', css.includes('zoom: 1 !important;'));
check('root transform normalized', css.includes('transform: none !important;'));
check('root filter normalized', css.includes('filter: none !important;'));
check('browser text-size normalized', css.includes('-webkit-text-size-adjust: 100%') && css.includes('text-size-adjust: 100%'));
check('native rasterization restored', css.includes('-webkit-font-smoothing: auto') && css.includes('text-rendering: auto'));
check('form controls inherit typography', css.includes(':where(button, input, textarea, select, option)'));
check('dashboard Manrope stack explicit', css.includes('font-family: Manrope, ui-sans-serif'));
check('spark render shell not targeted by batch selector', !/Font Rendering Batch 1[\s\S]*?:where\([^)]*cosmic-render-shell[^)]*\)/.test(css));

let failed = 0;
for (const { name, pass } of checks) {
  console.log(`${pass ? 'PASS' : 'FAIL'} ${name}`);
  if (!pass) failed++;
}
console.log(`\n${checks.length - failed}/${checks.length} PASS`);
if (failed) process.exit(1);
