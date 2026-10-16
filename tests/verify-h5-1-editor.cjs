/** Gutenberg editor QA uses a temporary draft, deleted in finally. Never saves the Home. */
'use strict';
const fs = require('node:fs'); const path = require('node:path'); const assert = require('node:assert/strict');
process.env.PLAYWRIGHT_BROWSERS_PATH ||= 'C:\\Users\\ramiro\\AppData\\Local\\ms-playwright';
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..'); const output = path.join(root, '.local/local/h5-1');
const base = 'http://localhost:8080';
const report = { checks: [], errors: [] };
async function block(p) { return p.evaluate(() => wp.data.select('core/block-editor').getBlocks().flatMap(function walk(b) { return [b, ...b.innerBlocks.flatMap(walk)]; }).find(b => b.name === 'commerce-lookbook/shoppable-lookbook')); }
async function editorReady(p) {
    await p.waitForFunction(() => window.wp?.data?.select('core/block-editor')?.getBlocks()?.length);
    const welcome = p.getByRole('dialog', { name: 'Bienvenido al editor' });
    if (await welcome.isVisible()) { await welcome.getByRole('button', { name: 'Cerrar', exact: true }).click(); await welcome.waitFor({ state: 'hidden' }); }
    const frame = p.frameLocator('iframe[name="editor-canvas"]');
    await frame.locator('.slb-editor').waitFor(); return frame;
}
(async () => {
    const browser = await chromium.launch(); const p = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    p.setDefaultTimeout(15000); p.setDefaultNavigationTimeout(30000); p.on('pageerror', e => report.errors.push(e.message)); p.on('dialog', d => d.accept());
    let draftId;
    try {
        const anon = await p.request.get(base + '/?rest_route=/commerce-lookbook/v1/products&search=Remera');
        assert.ok([401, 403].includes(anon.status())); report.checks.push('product_search_requires_auth_and_capability');
        const user = fs.readFileSync(path.join(root, '.local/local/.env'), 'utf8').match(/^LOCAL_ADMIN_USER=(.+)$/m)[1].trim();
        const password = fs.readFileSync(path.join(root, '.local/local/secrets/wp-admin-password.txt'), 'utf8').trim();
        await p.goto(base + '/wp-login.php'); await p.locator('#user_login').fill(user); await p.locator('#user_pass').fill(password);
        await Promise.all([p.waitForNavigation(), p.locator('#wp-submit').click()]);
        await p.goto(base + '/wp-admin/post.php?post=37&action=edit'); await editorReady(p);
        const original = await block(p); assert.ok(original.isValid); assert.equal(original.attributes.hotspots.length, 2);
        report.checks.push('home_saved_block_valid_in_gutenberg');
        draftId = await p.evaluate(async attrs => {
            const content = wp.blocks.serialize([wp.blocks.createBlock('commerce-lookbook/shoppable-lookbook', attrs)]);
            const page = await wp.apiFetch({ path: '/wp/v2/pages', method: 'POST', data: { title: 'H5.1 temporary editor QA', status: 'draft', content } });
            return page.id;
        }, original.attributes);
        await p.goto(base + '/wp-admin/post.php?post=' + draftId + '&action=edit'); let f = await editorReady(p);
        await f.getByRole('button', { name: 'Agregar hotspot', exact: true }).click();
        const combo = f.getByRole('combobox', { name: 'Producto WooCommerce', exact: true });
        await combo.fill('Bolsa'); await f.getByRole('option', { name: 'Bolsa Diario', exact: true }).click();
        await p.waitForFunction(() => wp.data.select('core/block-editor').getBlocks()[0].attributes.hotspots[2].productId > 0);
        report.checks.push('add_hotspot_search_by_name_select_parent_id');
        const numbers = f.locator('.slb-editor input[type="number"]');
        await numbers.nth(0).fill('88.4'); await numbers.nth(0).press('Tab');
        await numbers.nth(1).fill('44.2'); await numbers.nth(1).press('Tab');
        let current = await block(p); assert.equal(current.attributes.hotspots[2].x, 88.4); assert.equal(current.attributes.hotspots[2].y, 44.2);
        report.checks.push('xy_numeric_controls_preview');
        await f.getByRole('button', { name: 'Subir punto', exact: true }).click();
        current = await block(p); assert.equal(current.attributes.hotspots[1].x, 88.4); report.checks.push('reorder_hotspots');
        await f.getByRole('button', { name: 'Eliminar hotspot', exact: true }).click();
        assert.equal((await block(p)).attributes.hotspots.length, 2); report.checks.push('delete_hotspot');
        // Choose an image with the actual WordPress media modal.
        await f.getByRole('button', { name: 'Editar punto 1', exact: true }).click();
        await p.getByRole('button', { name: 'Cambiar imagen', exact: true }).click();
        const modal = p.getByRole('dialog'); await modal.waitFor();
        const library = modal.getByRole('tab', { name: /Biblioteca.*multimedia/ });
        if (await library.isVisible()) await library.click();
        const attachment = modal.getByRole('checkbox', { name: /Editorial · Notas de ciudad 01/ });
        if (await attachment.getAttribute('aria-checked') !== 'true') await attachment.click();
        await modal.getByRole('button', { name: 'Elegir', exact: true }).click();
        await modal.waitFor({ state: 'hidden' }); report.checks.push('native_media_library_choose_image');
        await p.evaluate(() => wp.data.dispatch('core/editor').savePost());
        await p.reload(); f = await editorReady(p); current = await block(p);
        assert.ok(current.isValid); assert.equal(current.attributes.imageId, original.attributes.imageId); assert.equal(current.attributes.hotspots.length, 2);
        assert.ok(current.attributes.hotspots.every(s => Object.keys(s).sort().join(',') === 'productId,x,y'));
        report.checks.push('draft_save_reload_valid_only_product_id_and_xy');
        // Missing-reference state is exercised in the temporary draft only.
        await p.evaluate(() => {
            const b = wp.data.select('core/block-editor').getBlocks()[0];
            wp.data.dispatch('core/block-editor').updateBlockAttributes(b.clientId, { hotspots: [{ productId: 2147483647, x: 50, y: 50 }] });
        });
        await f.getByText('Hay productos eliminados, ocultos o sin publicar.', { exact: false }).waitFor();
        report.checks.push('editor_warns_missing_reference');
        await p.screenshot({ path: path.join(output, 'editor-missing-reference.png') });
        const api = await p.evaluate(async () => {
            const normal = await wp.apiFetch({ path: '/commerce-lookbook/v1/products?search=Remera' });
            const sold = await wp.apiFetch({ path: '/commerce-lookbook/v1/products?search=Pantal%C3%B3n%20L%C3%ADnea' });
            const invalid = await wp.apiFetch({ path: '/commerce-lookbook/v1/products?include[]=2147483647' });
            return { normal: normal.products, sold: sold.products, invalid };
        });
        assert.ok(api.normal.length >= 4 && api.normal.every(x => x.name.includes('Remera')));
        assert.ok(api.sold.length === 1 && !api.sold[0].inStock); assert.deepEqual(api.invalid.missing, [2147483647]);
        report.checks.push('authenticated_search_results_soldout_and_missing_status');
        report.result = 'passed';
    } catch (error) { report.result = 'failed'; report.error = error.message; await p.screenshot({ path: path.join(output, 'editor-failure.png') }); }
    finally {
        if (draftId) {
            await p.evaluate(id => wp.apiFetch({ path: '/wp/v2/pages/' + id + '?force=true', method: 'DELETE' }), draftId);
            report.checks.push('temporary_draft_deleted');
        }
        await browser.close();
        fs.writeFileSync(path.join(output, 'editor-qa.json'), JSON.stringify(report, null, 2) + '\n');
    }
    console.log(JSON.stringify(report)); if (report.result !== 'passed' || report.errors.length) process.exitCode = 1;
})().catch(e => { console.error(e); process.exitCode = 1; });
