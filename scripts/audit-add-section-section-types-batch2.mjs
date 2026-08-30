import fs from 'node:fs';
const file='resources/js/Pages/Websites/Components/AddSectionModal.jsx';
const css='resources/css/app.css';
const s=fs.readFileSync(file,'utf8');
const c=fs.readFileSync(css,'utf8');
const checks=[
 ['premium section types exist', ['Hero','About / Content','Services','Features','Testimonials','Pricing','Process','Stats','Team','FAQ','Contact','Gallery','CTA','Ecommerce'].every(x=>s.includes(`"${x}"`))],
 ['section cards use normalized semantic category', s.includes('categoryFor(item.key, item.category)')],
 ['legacy Proof bucket removed from preferred UI', !s.includes('"Proof", "Testimonials"')],
 ['legacy Mini Heroes bucket removed from preferred UI', !s.includes('"Mini Heroes", "Other"')],
 ['Luna copy is Spark-first', s.includes('Luna will choose the closest premade Spark') && s.includes('Luna picks the closest Spark')],
 ['AI Flex marketing copy removed from picker', !s.includes('brand-new AI Flex section') && !s.includes('Build a new AI Flex section from scratch')],
 ['section type descriptions replace Spark counts', s.includes('{meta.description}')],
 ['gallery icon exists', s.includes('icon === "image"')],
 ['process icon exists', s.includes('icon === "route"')],
 ['stats icon exists', s.includes('icon === "chart"')],
 ['commerce icon exists', s.includes('icon === "bag"')],
 ['colored tone CSS exists', ['blue','indigo','emerald','fuchsia','orange','purple','teal','sky','amber','rose','cyan','lime','slate'].every(x=>c.includes(`data-tone="${x}"`))],
 ['icon color contract exists', c.includes('.cosmic-section-type-icon') && c.includes('color: rgb(var(--type-rgb)) !important')],
];
let pass=0; for(const [n,ok] of checks){ console.log(`${ok?'PASS':'FAIL'} ${n}`); if(ok)pass++; }
console.log(`\n${pass}/${checks.length} PASS`); if(pass!==checks.length)process.exit(1);
