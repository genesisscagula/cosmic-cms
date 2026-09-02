import fs from 'node:fs';
import process from 'node:process';
import { sortSparkMarketplaceItems, sparkMarketplacePriorityBucket } from '../resources/js/Utils/sparkMarketplaceSort.js';

const fail = (message) => {
    console.error(`FAIL: ${message}`);
    process.exitCode = 1;
};

const assertOrder = (label, items, expected) => {
    const actual = sortSparkMarketplaceItems(items).map((item) => item.key);
    if (actual.join('|') !== expected.join('|')) {
        fail(`${label} ordering was ${actual.join(', ')}; expected ${expected.join(', ')}`);
    }
};

assertOrder('Starter marketplace', [
    { key: 'pro-old', access_level: 'pro', locked: true, catalog_index: 4 },
    { key: 'starter-new', access_level: 'starter', locked: false, catalog_index: 20 },
    { key: 'growth-old', access_level: 'growth', locked: true, catalog_index: 3 },
    { key: 'owned-pro', access_level: 'pro', locked: true, owned: true, catalog_index: 100 },
    { key: 'starter-old', access_level: 'starter', locked: false, catalog_index: 1 },
    { key: 'pro-new', access_level: 'pro', locked: true, catalog_index: 30 },
    { key: 'growth-new', access_level: 'growth', locked: true, catalog_index: 40 },
], ['owned-pro', 'starter-old', 'starter-new', 'growth-old', 'growth-new', 'pro-old', 'pro-new']);

assertOrder('Growth marketplace', [
    { key: 'growth-new', access_level: 'growth', locked: false, catalog_index: 40 },
    { key: 'pro-old', access_level: 'pro', locked: true, catalog_index: 4 },
    { key: 'starter-old', access_level: 'starter', locked: false, catalog_index: 1 },
    { key: 'owned-pro', access_level: 'pro', locked: true, owned_permanently: true, catalog_index: 100 },
    { key: 'growth-old', access_level: 'growth', locked: false, catalog_index: 3 },
], ['owned-pro', 'starter-old', 'growth-old', 'growth-new', 'pro-old']);

assertOrder('Pro marketplace', [
    { key: 'new', access_level: 'pro', locked: false, catalog_index: 90 },
    { key: 'old', access_level: 'starter', locked: false, catalog_index: 2 },
    { key: 'owned-new', access_level: 'growth', locked: false, owned: true, catalog_index: 100 },
], ['owned-new', 'old', 'new']);

if (sparkMarketplacePriorityBucket({ owned: true, locked: true, access_level: 'pro' }) !== 0) fail('Owned Spark must always be bucket 0.');
if (sparkMarketplacePriorityBucket({ locked: false, access_level: 'starter' }) !== 1) fail('Accessible Spark must be bucket 1.');
if (sparkMarketplacePriorityBucket({ locked: true, access_level: 'growth' }) !== 2) fail('Growth lock must be bucket 2.');
if (sparkMarketplacePriorityBucket({ locked: true, access_level: 'pro' }) !== 3) fail('Pro lock must be bucket 3.');

const integrations = [
    'resources/js/Pages/Websites/Components/AddSectionModal.jsx',
    'resources/js/Pages/Sparks/Index.jsx',
    'resources/js/Pages/Dashboard/Tabs/Sparks.jsx',
];
for (const file of integrations) {
    const source = fs.readFileSync(file, 'utf8');
    if (!source.includes('compareSparkMarketplacePriority')) fail(`${file} is missing smart Marketplace ordering.`);
}

if (!process.exitCode) console.log('Spark Marketplace ordering audit PASS');
