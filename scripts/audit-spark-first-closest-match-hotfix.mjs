import fs from 'node:fs';
const s=fs.readFileSync('app/Http/Controllers/CustomSparkController.php','utf8');
const checks=[
 ['closest-match policy',s.includes('closest proven premade layout wins')],
 ['compliance non-blocking',s.includes('Compliance is advisory metadata only')],
 ['gap telemetry retained',s.includes("'status' => 'closest_match_with_gaps'")],
 ['no continue in compliance branch',!s.includes("'status' => 'compliance_failed',\n                            'failures'")],
 ['closest response',s.includes('I selected the closest proven Spark available')],
 ['registered spark remains',s.includes("'popup_action' => 'registered_spark_redesign'")],
];
let fail=0; for(const [n,ok] of checks){console.log(`${ok?'PASS':'FAIL'} ${n}`); if(!ok)fail++;}
console.log(`${checks.length-fail}/${checks.length} PASS`); process.exit(fail?1:0);
