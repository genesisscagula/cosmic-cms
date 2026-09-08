import fs from 'node:fs';
import path from 'node:path';
const root=process.cwd();
const read=(p)=>fs.readFileSync(path.join(root,p),'utf8');
const modal=read('resources/js/Pages/Dashboard/Components/NewWebsiteModal.jsx');
const index=read('resources/js/Pages/Websites/Index.jsx');
const luna=read('resources/js/Components/GlobalLuna.jsx');
const controller=read('app/Http/Controllers/WebsiteController.php');
const checks=[
 ['Luna-specific brief field exists', modal.includes('What should Luna create?') && modal.includes("luna_prompt")],
 ['Luna brief explains confirmation before credits', modal.includes('asks for confirmation before spending AI credits')],
 ['backend validates Luna prompt', controller.includes("'luna_prompt' => 'nullable|string|max:6000'")],
 ['prompt only persists for Luna mode', controller.includes('WebsiteCreationMode::LUNA_AI') && controller.includes("['prompt' => trim((string) $request->input('luna_prompt'))]")],
 ['Luna first run is derived from saved creation mode', index.includes("savedCreationMode === 'luna_ai'")],
 ['Luna first run auto-opens only from creation handoff', index.includes("requestedMode !== 'luna_ai'") && index.includes('cosmic-luna-first-run:')],
 ['Luna first-run handoff includes saved prompt', index.includes("mode: 'luna_ai'") && index.includes('prompt: lunaCreationPrompt')],
 ['Luna mode has dedicated workspace empty state', index.includes('Let Luna compose the first complete website draft') && index.includes('Open Luna Build')],
 ['generic Starter Pages button hidden during Luna first run', index.includes('&& !lunaFirstRun')],
 ['Global Luna accepts creation mode and prompt', luna.includes("const mode=String(detail.mode||'starter_pages')") && luna.includes("const prompt=String(detail.prompt||'').trim()")],
 ['Luna automatically plans with saved prompt', luna.includes('planStarterSite(prompt,websiteId,mode)')],
 ['Luna plan has website-specific confirmation label', luna.includes("lunaWebsiteMode?'Build Website':'Install Starter Pages'")],
 ['Luna build panel is labeled distinctly', luna.includes("'Build with Luna AI'") && luna.includes("'Website Build'")],
 ['existing generic Starter Pages flow remains available', luna.includes("mode||'starter_pages'") && luna.includes('Starter Pages Ready')],
 ['no migration dependency introduced', !fs.readdirSync(path.join(root,'database/migrations')).some(f=>/luna.*prompt|creation.*mode/i.test(f))],
];
let pass=0;
for(const [label,ok] of checks){console.log(`${ok?'PASS':'FAIL'} ${label}`); if(ok) pass++;}
console.log(`\n${pass}/${checks.length} checks passed.`);
if(pass!==checks.length) process.exit(1);
