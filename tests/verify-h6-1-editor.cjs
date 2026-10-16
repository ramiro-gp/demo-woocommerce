/** H6.1 Gutenberg QA. Saves only a temporary draft; never saves the real cart. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
process.env.PLAYWRIGHT_BROWSERS_PATH ||= 'C:\\Users\\ramiro\\AppData\\Local\\ms-playwright';
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const output = path.join(root, '.local/local/h6-1');
const base = 'http://localhost:8080';
const report = { started: new Date().toISOString(), checks: [], errors: [] };

async function editorReady(page) {
    await page.waitForFunction(() => window.wp?.data?.select('core/block-editor')?.getBlocks()?.length);
    const welcome = page.getByRole('dialog', { name: 'Bienvenido al editor' });
    if (await welcome.isVisible()) await welcome.getByRole('button', { name: 'Cerrar', exact: true }).click();
    await page.waitForFunction(() => window.wc?.blocksCheckout?.applyCheckoutFilter);
}

async function inspect(page) {
    return page.evaluate(() => {
        const blocks = wp.data.select('core/block-editor').getBlocks();
        const walk = list => list.flatMap(block => [block, ...walk(block.innerBlocks)]);
        const all = walk(blocks);
        const cart = all.find(block => block.name === 'woocommerce/cart');
        const filled = all.find(block => block.name === 'woocommerce/filled-cart-block');
        const collection = filled?.innerBlocks.find(block => block.name === 'woocommerce/product-collection');
        const allowed = wc.blocksCheckout.applyCheckoutFilter({
            filterName: 'additionalCartCheckoutInnerBlockTypes', defaultValue: [], extensions: {},
            arg: { block: 'woocommerce/filled-cart-block' }, validation: value => Array.isArray(value)
        });
        return {
            cart: !!cart,
            invalid: all.filter(block => block.isValid === false).map(block => block.name),
            filledChildren: filled?.innerBlocks.map(block => block.name),
            allowed,
            collection: collection?.attributes,
            collectionChildren: collection?.innerBlocks.map(block => block.name),
            cards: all.filter(block => block.name === 'commerce-base/product-card').length,
            heading: collection?.innerBlocks.find(block => block.name === 'core/heading')?.attributes.content,
            emptyCTA: all.find(block => block.name === 'core/button')?.attributes.text
        };
    });
}

(async () => {
    fs.mkdirSync(output, { recursive: true });
    const browser = await chromium.launch();
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    page.setDefaultTimeout(20000);
    page.on('pageerror', error => report.errors.push(error.message));
    page.on('dialog', dialog => dialog.accept());
    let draftId;
    let original;
    try {
        const env = fs.readFileSync(path.join(root, '.local/local/.env'), 'utf8');
        const user = env.match(/^LOCAL_ADMIN_USER=(.+)$/m)[1].trim();
        const password = fs.readFileSync(path.join(root, '.local/local/secrets/wp-admin-password.txt'), 'utf8').trim();
        await page.goto(base + '/wp-login.php');
        await page.locator('#user_login').fill(user);
        await page.locator('#user_pass').fill(password);
        await Promise.all([page.waitForNavigation(), page.locator('#wp-submit').click()]);
        await page.goto(base + '/wp-admin/post.php?post=8&action=edit');
        await editorReady(page);
        original = await page.evaluate(() => wp.apiFetch({ path: '/wp/v2/pages/8?context=edit' }));
        const actual = await inspect(page);
        report.publishedInspection = actual;
        assert.ok(actual.cart);
        assert.deepEqual(actual.invalid, []);
        assert.ok(actual.allowed.includes('woocommerce/product-collection'));
        assert.deepEqual(actual.filledChildren, [
            'woocommerce/cart-items-block', 'woocommerce/cart-totals-block', 'woocommerce/product-collection'
        ]);
        assert.equal(actual.collection.collection, 'woocommerce/product-collection/cross-sells');
        assert.equal(actual.collection.displayLayout.type, 'carousel');
        assert.equal(actual.collection.query.perPage, 6);
        assert.ok(!actual.collection.query.woocommerceStockStatus.includes('outofstock'));
        assert.equal(actual.heading, '¿Sumamos algo más?');
        assert.ok(actual.collectionChildren.includes('woocommerce/product-gallery-large-image-next-previous'));
        assert.equal(actual.cards, 1);
        assert.equal(actual.emptyCTA, 'Explorar la tienda');
        report.checks.push('published_cart_valid_native_collection_allowed_after_totals');
        draftId = await page.evaluate(async content => {
            const draft = await wp.apiFetch({ path: '/wp/v2/pages', method: 'POST', data: {
                title: 'H6.1 temporary editor QA', status: 'draft', content
            } });
            return draft.id;
        }, original.content.raw);
        await page.goto(base + '/wp-admin/post.php?post=' + draftId + '&action=edit');
        await editorReady(page);
        // Exercise Gutenberg serialization and its native save path on the draft.
        await page.evaluate(async () => {
            const title = wp.data.select('core/editor').getEditedPostAttribute('title');
            wp.data.dispatch('core/editor').editPost({ title: title + ' · saved' });
            await wp.data.dispatch('core/editor').savePost();
        });
        await page.reload();
        await editorReady(page);
        const reopened = await inspect(page);
        assert.deepEqual(reopened, actual);
        report.checks.push('temporary_draft_save_reopen_keeps_valid_blocks_carousel_and_dom_order');
        const untouched = await page.evaluate(() => wp.apiFetch({ path: '/wp/v2/pages/8?context=edit' }));
        assert.equal(untouched.content.raw, original.content.raw);
        assert.equal(untouched.modified_gmt, original.modified_gmt);
        report.checks.push('published_cart_content_and_modified_time_preserved');
        await page.screenshot({ path: path.join(output, 'editor-cart.png'), fullPage: true });
        report.result = 'passed';
    } catch (error) {
        report.result = 'failed';
        report.error = error.message;
        await page.screenshot({ path: path.join(output, 'editor-cart-failure.png') }).catch(() => {});
    } finally {
        if (draftId) {
            await page.evaluate(id => wp.apiFetch({ path: '/wp/v2/pages/' + id + '?force=true', method: 'DELETE' }), draftId);
            report.checks.push('temporary_draft_deleted');
        }
        await browser.close();
        fs.writeFileSync(path.join(output, 'editor-qa.json'), JSON.stringify(report, null, 2) + '\n');
    }
    console.log(JSON.stringify(report));
    if (report.result !== 'passed' || report.errors.length) process.exitCode = 1;
})().catch(error => { console.error(error); process.exitCode = 1; });
