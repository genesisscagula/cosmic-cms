import fs from 'node:fs';
const react=fs.readFileSync('resources/js/Pages/Websites/Blocks/General/LunaCustomSectionBlock.jsx','utf8');
const css=fs.readFileSync('resources/css/app.css','utf8');
const php=fs.readFileSync('app/Helpers/CmsHtmlCompiler.php','utf8');
const checks=[
 ['react tree detection', react.includes('aiFlexIsFullBleedStage') && react.includes("aiFlexTreeHas(elements,['slider'])")],
 ['react media detection', react.includes("aiFlexTreeHas(elements,['background_image','background_video'])")],
 ['react stage class', react.includes('cosmic-custom-section--full-bleed') && react.includes('cosmic-flex-elements--full-bleed')],
 ['react stage removes outer padding', react.includes("padding:aiFlexFullBleed?'0':")],
 ['react mobile keeps full bleed', react.includes('.cosmic-custom-section--full-bleed{padding:0!important')],
 ['global full bleed widths', css.includes('.cosmic-flex-elements--full-bleed') && css.includes('max-width: none !important')],
 ['global stage minimum', css.includes('min-height: var(--af-hero-stage-min, 560px) !important')],
 ['php tree detection', php.includes("$treeHasType($elements, ['slider'])") && php.includes("$treeHasType($elements, ['background_image','background_video'])")],
 ['php stage class', php.includes('cosmic-flex-elements--full-bleed') && php.includes('cosmic-custom-section--full-bleed')],
 ['php export removes outer padding', php.includes("($aiFlexFullBleed?'0':\"{$padY}px {$padX}px\")")],
 ['php responsive keeps full bleed', php.includes(".cosmic-custom-section--full-bleed{padding:0!important")],
];
let pass=0;for(const [name,ok] of checks){console.log(`${ok?'PASS':'FAIL'} ${name}`);if(ok)pass++;}
console.log(`${pass}/${checks.length} PASS`);if(pass!==checks.length)process.exit(1);
