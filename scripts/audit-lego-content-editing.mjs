import assert from 'node:assert/strict';
import { build } from 'esbuild';
import { unlink } from 'node:fs/promises';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { createAiFlexExtra } from '../resources/js/Pages/Websites/Blocks/Shared/aiFlexStructureContract.js';

const output = fileURLToPath(new URL(`.lego-content-audit-${process.pid}.mjs`, import.meta.url));
await build({ entryPoints: [fileURLToPath(new URL('../resources/js/Pages/Websites/Blocks/Shared/LegoContentFields.jsx', import.meta.url))], outfile: output, bundle: true, platform: 'node', format: 'esm', jsx: 'automatic', packages: 'external' });
try {
    const { default: Fields } = await import(pathToFileURL(output));
    function controls(element, result = []) {
        if (!element || typeof element !== 'object') return result;
        if (Array.isArray(element)) { element.forEach(child => controls(child, result)); return result; }
        if (typeof element.type === 'function') return controls(element.type(element.props), result);
        if (element.type === 'input' || element.type === 'textarea') result.push(element.props);
        controls(element.props?.children, result);
        return result;
    }
    const types = ['basic_card', 'image_card', 'cards_grid', 'content_stack', 'image_content', 'icon_card', 'cta_block', 'pricing_grid', 'services_grid', 'stats_grid', 'team_grid', 'testimonials_grid', 'heading', 'text', 'button', 'image', 'video', 'background_image', 'background_video', 'icon', 'badge', 'quote', 'stat', 'list', 'accordion', 'tabs', 'gallery', 'lightbox_gallery', 'image_carousel', 'logo_carousel', 'button_group', 'media_group'];
    for (const type of types) {
        const node = createAiFlexExtra(type);
        const before = JSON.stringify(node);
        let changed;
        const inputs = controls(Fields({ node, onChange: value => { changed = value; } }));
        assert.ok(inputs.length, `${type} exposes content inputs`);
        for (const input of inputs) {
            changed = undefined;
            input.onChange({ target: { value: 'Edited content audit' } });
            assert.ok(JSON.stringify(changed).includes('Edited content audit'), `${type} propagates edits to root`);
            assert.equal(JSON.stringify(node), before, `${type} preserves original data`);
            assert.equal(changed._cosmic_id, node._cosmic_id, `${type} preserves identity`);
        }
        console.log(`PASS ${type}: ${inputs.length} editable fields`);
    }
    const grid = createAiFlexExtra('team_grid');
    let updated;
    const inputs = controls(Fields({ node: grid, onChange: value => { updated = value; } }));
    const name = inputs.find(input => input.value === 'Jamie Lee');
    assert.ok(name, 'nested team member name is editable');
    name.onChange({ target: { value: 'Updated member' } });
    assert.deepEqual(updated.children[0], grid.children[0]);
    assert.deepEqual(updated.children[2], grid.children[2]);
    assert.ok(JSON.stringify(updated.children[1]).includes('Updated member'));
    assert.deepEqual(updated.children[1].style, grid.children[1].style);
    console.log('PASS nested edit changes only the selected member and preserves styles/siblings');
} finally {
    await unlink(output);
}
