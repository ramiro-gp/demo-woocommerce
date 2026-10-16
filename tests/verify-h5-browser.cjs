/** H5 full-flow QA. Run with the bundled NODE_PATH; evidence stays in .local. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
process.env.PLAYWRIGHT_BROWSERS_PATH ||= 'C:\\Users\\ramiro\\AppData\\Local\\ms-playwright';
const { chromium } = require('playwright');
const output = path.resolve(__dirname, '../.local/local/h5');
const seed = JSON.parse(fs.readFileSync(path.join(output, 'verify.json'), 'utf8').replace(/^\uFEFF/, ''));
const product = slug => seed.products.find(p => new URL(p.url).searchParams.get('product') === slug);
const base = 'http://localhost:8080';
const widths = [320, 375, 390, 430, 768, 1024, 1366, 1440, 1920, 2560, 3440, 3840];
const routes = [
    ['shop', seed.shop], ...seed.categories.map(c => [c.name.toLowerCase(), c.url]),
    ['variable', product('remera-base').url], ['simple', product('bolsa-diario').url],
    ['soldout', product('pantalon-linea').url], ['home', base],
    ['search', base + '/?s=remera&post_type=product'],
    ['empty', base + '/?s=sin-producto-h5-9428&post_type=product'],
    ['collection', seed.collection]
];
const report = { started: new Date().toISOString(), widths, routes, checks: [], errors: [], assetErrors: [], captures: [] };
async function check(name, task) {
    try { const evidence = await task(); report.checks.push({ name, result: 'passed', evidence }); }
    catch (e) { report.checks.push({ name, result: 'failed', error: e.message }); console.log('FAIL', name, e.message.slice(0, 250)); }
}
async function settle(page) { await page.evaluate(() => document.fonts.ready); await page.waitForTimeout(100); }
async function loadAllImages(page) {
    await page.evaluate(async () => {
        for (let y = 0; y < document.body.scrollHeight; y += 650) { window.scrollTo({ top: y, behavior: 'instant' }); await new Promise(r => setTimeout(r, 45)); }
        await Promise.all([...document.images].filter(i => !i.closest('template')).map(i => i.decode().catch(() => {})));
        window.scrollTo({ top: 0, behavior: 'instant' });
        document.activeElement?.blur();
    });
    await page.waitForTimeout(150);
}
async function capture(page, name, full = true) { await loadAllImages(page); await page.screenshot({ path: path.join(output, name + '.png'), fullPage: full }); report.captures.push(name + '.png'); }
function watch(page) {
    page.on('pageerror', e => report.errors.push({ url: page.url(), error: e.message }));
    page.on('response', r => { if (r.status() >= 400 && /\.(?:css|js|webp|woff2)(?:\?|$)/.test(r.url()) && r.url().startsWith(base)) report.assetErrors.push({ url: r.url(), status: r.status() }); });
}
(async () => {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();
    page.setDefaultTimeout(15000); page.setDefaultNavigationTimeout(25000); watch(page);
    if (process.env.H5_SCOPE !== 'flows') for (const theme of ['light', 'dark']) {
        await page.emulateMedia({ colorScheme: theme });
        for (const width of widths) {
            await page.setViewportSize({ width, height: width >= 1920 ? 1080 : 900 });
            for (const [name, url] of routes) {
                await check(`${theme}/${width}/${name}`, async () => {
                    const response = await page.goto(url); assert.equal(response.status(), 200);
                    await settle(page);
                    const metrics = await page.evaluate(() => {
                        const root = document.documentElement;
                        const main = document.querySelector('main');
                        const grid = main.querySelector('.cb-catalog-collection .wc-block-product-template');
                        const cards = [...main.querySelectorAll('.cb-catalog-collection .cb-product-card')];
                        const gallery = main.querySelector('.cb-product-gallery');
                        const purchase = main.querySelector('.cb-product-purchase');
                        return { viewport: innerWidth, scroll: root.scrollWidth, theme: root.dataset.theme,
                            h1: main.querySelectorAll('h1').length, columns: grid ? getComputedStyle(grid).gridTemplateColumns.split(' ').length : null,
                            cards: cards.length, cardWidth: cards[0]?.getBoundingClientRect().width,
                            galleryFirst: !gallery || (innerWidth > 782 ? gallery.getBoundingClientRect().left < purchase.getBoundingClientRect().left : gallery.getBoundingClientRect().top < purchase.getBoundingClientRect().top),
                            imageFilters: [...main.querySelectorAll('img')].filter(i => !i.classList.contains('zoomImg')).every(i => getComputedStyle(i).filter === 'none') };
                    });
                    assert.ok(metrics.scroll <= metrics.viewport + 1, 'Horizontal overflow ' + JSON.stringify(metrics));
                    assert.equal(metrics.theme, theme); assert.equal(metrics.h1, 1); assert.ok(metrics.galleryFirst); assert.ok(metrics.imageFilters);
                    if (name === 'shop') { assert.equal(metrics.cards, 16); if (width >= 1024) assert.ok(metrics.cardWidth >= 180 && metrics.cardWidth <= 460, 'Card width'); }
                    return metrics;
                });
            }
            if ([390, 1440, 3840].includes(width)) {
                for (const name of ['shop', 'variable', 'simple', 'soldout']) {
                    await page.goto(routes.find(r => r[0] === name)[1]); await settle(page);
                    await capture(page, `${name}-${theme}-${width}`);
                }
            }
        }
        console.log(`Matrix ${theme} complete: ${report.checks.filter(c => c.result === 'passed').length} checks passed`);
    }
    await page.emulateMedia({ colorScheme: 'light' }); await page.setViewportSize({ width: 1440, height: 1000 });
    await check('Desktop native filters category + talle + color + clear', async () => {
        await page.goto(seed.shop);
        await page.getByRole('checkbox', { name: 'Remeras', exact: true }).check();
        await page.waitForURL(/categories=remeras/); await page.waitForFunction(() => document.querySelectorAll('.cb-catalog-collection .cb-product-card').length === 4);
        await page.getByRole('checkbox', { name: 'M', exact: true }).check();
        await page.waitForURL(/filter_talle=m/);
        await page.getByRole('checkbox', { name: 'Negro', exact: true }).check();
        await page.waitForURL(/filter_color=negro/);
        await page.getByRole('button', { name: 'Limpiar filtros', exact: true }).click();
        await page.waitForFunction(() => document.querySelectorAll('.cb-catalog-collection .cb-product-card').length === 16);
        assert.ok(!new URL(page.url()).searchParams.has('filter_color'));
        return { combined: true, cleared: true };
    });
    await check('Native price filter', async () => {
        await page.goto(seed.shop);
        await page.locator('.wp-block-woocommerce-product-filter-price input[type="text"].max').fill('35000');
        await page.waitForURL(/max_price=35000/); await page.waitForFunction(() => document.querySelectorAll('.cb-catalog-collection .cb-product-card').length === 8);
        return { maximum: 35000, results: 8 };
    });
    await check('Native stock filter', async () => {
        await page.goto(seed.shop); await page.getByRole('checkbox', { name: 'Agotado', exact: true }).check();
        await page.waitForFunction(() => document.querySelectorAll('.cb-catalog-collection .cb-product-card').length === 1);
        assert.equal(await page.locator('.cb-catalog-collection .cb-product-title').innerText(), 'Pantalón Línea');
        return { url: page.url(), results: 1 };
    });
    for (const order of ['price', 'price-desc', 'date', 'menu_order']) await check('Native sorting ' + order, async () => {
        await page.goto(seed.shop); await page.locator('select.orderby').selectOption(order);
        await page.waitForURL(new RegExp('orderby=' + order));
        await settle(page);
        const first = await page.locator('.cb-catalog-collection .cb-product-title').first().innerText();
        if (order === 'price') assert.equal(first, 'Medias Canal');
        if (order === 'price-desc') assert.equal(first, 'Buzo Textura');
        return { first };
    });
    await check('Empty filter state and clear recovery', async () => {
        await page.goto(seed.shop + '&min_price=999999');
        await page.locator('.cb-empty-results').waitFor({ state: 'visible' });
        assert.match(await page.locator('.cb-empty-results').innerText(), /No hay prendas/);
        await capture(page, 'empty-filters');
        await page.locator('.wc-link-clear-any-filters').click();
        await page.waitForFunction(() => document.querySelectorAll('.cb-catalog-collection .cb-product-card').length === 16);
    });
    for (const theme of ['light', 'dark']) await check('Mobile native filters + keyboard ' + theme, async () => {
        await page.emulateMedia({ colorScheme: theme }); await page.setViewportSize({ width: 390, height: 844 }); await page.goto(seed.shop);
        const trigger = page.locator('.wc-block-product-filters__open-overlay');
        await trigger.click(); await page.locator('.cb-filters.is-overlay-opened').waitFor();
        const dialog = page.locator('#cb-filter-dialog'); await page.waitForFunction(() => document.querySelector('#cb-filter-dialog')?.getAttribute('aria-modal') === 'true');
        await dialog.getByRole('checkbox', { name: 'Buzos', exact: true }).check();
        await page.waitForFunction(() => document.querySelectorAll('.cb-catalog-collection .cb-product-card').length === 3);
        await page.waitForFunction(() => document.querySelector('.cb-filter-count')?.textContent.includes('1 activo'));
        const apply = dialog.getByRole('button', { name: 'Aplicar', exact: true });
        await apply.focus(); await page.keyboard.press('Tab');
        assert.ok(await page.evaluate(() => document.activeElement.classList.contains('wc-block-product-filters__close-overlay')));
        await capture(page, 'drawer-' + theme, false);
        await apply.click(); await page.waitForFunction(() => !document.querySelector('.cb-filters').classList.contains('is-overlay-opened'));
        await trigger.click(); await page.keyboard.press('Escape');
        await page.waitForFunction(() => !document.querySelector('.cb-filters').classList.contains('is-overlay-opened'));
        assert.equal(await trigger.getAttribute('aria-expanded'), 'false');
        assert.ok(await trigger.evaluate(n => n === document.activeElement));
    });
    let unavailableId;
    await check('Official variations, stock, images, size guide and keyboard', async () => {
        await page.setViewportSize({ width: 1440, height: 1000 }); await page.emulateMedia({ colorScheme: 'light' }); await page.goto(product('remera-base').url);
        await page.locator('.cb-variation-options').first().waitFor();
        unavailableId = await page.locator('form.variations_form').evaluate(n => JSON.parse(n.dataset.product_variations).find(v => !v.is_in_stock).variation_id);
        const sizes = page.locator('select[name="attribute_pa_talle"] + .cb-variation-options');
        assert.deepEqual(await sizes.locator('button > span').allTextContents(), ['S', 'M', 'L', 'XL']);
        await page.getByRole('radio', { name: 'Natural', exact: true }).click();
        assert.ok(await sizes.getByRole('radio', { name: 'XL Agotado', exact: true }).isDisabled());
        await sizes.getByRole('radio', { name: 'S', exact: true }).click();
        await page.waitForFunction(() => Number(document.querySelector('input.variation_id').value) > 0);
        await page.getByRole('radio', { name: 'Negro', exact: true }).click();
        await page.waitForFunction(() => document.querySelector('.woocommerce-product-gallery__image.flex-active-slide img:not(.zoomImg)')?.src.includes('remera-base-neg'));
        assert.ok(!(await sizes.getByRole('radio', { name: 'XL', exact: true }).isDisabled()));
        await sizes.getByRole('radio', { name: 'S', exact: true }).focus(); await page.keyboard.press('ArrowRight');
        assert.equal(await page.locator('select[name="attribute_pa_talle"]').inputValue(), 'm');
        assert.match(await page.locator('.woocommerce-variation-availability').innerText(), /En stock/);
        const guide = page.locator('.cb-size-guide summary'); await guide.focus(); await page.keyboard.press('Enter');
        assert.ok(await page.locator('.cb-size-guide').evaluate(n => n.open)); assert.equal(await page.locator('.cb-size-guide tbody tr').count(), 4);
        await page.waitForTimeout(600);
        await capture(page, 'product-selected-guide');
    });
    await check('Native gallery thumbnails + lightbox + Escape', async () => {
        await page.goto(product('bolsa-diario').url); const thumbs = page.locator('.flex-control-thumbs img'); await thumbs.nth(1).waitFor();
        await thumbs.nth(1).focus(); await page.keyboard.press('Enter');
        await page.waitForFunction(() => document.querySelectorAll('.flex-control-thumbs img')[1].classList.contains('flex-active'));
        await page.locator('.woocommerce-product-gallery__trigger').click();
        await page.locator('.pswp--open').waitFor(); await page.keyboard.press('Escape'); await page.locator('.pswp--open').waitFor({ state: 'hidden' });
    });
    await check('Native simple + variable cart and guest checkout', async () => {
        await page.goto(product('bolsa-diario').url); await page.locator('input.qty').fill('2');
        await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.locator('.single_add_to_cart_button').click()]);
        assert.ok(await page.locator('.woocommerce-message, .wc-block-components-notice-banner.is-success').count());
        await page.goto(product('remera-base').url); await page.getByRole('radio', { name: 'Natural', exact: true }).click(); await page.getByRole('radio', { name: 'S', exact: true }).click();
        await page.waitForFunction(() => Number(document.querySelector('input.variation_id').value) > 0);
        await page.locator('.single_add_to_cart_button:not(.disabled)').waitFor({ state: 'visible' });
        await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.locator('.single_add_to_cart_button:not(.disabled)').click()]);
        await page.goto(seed.cart); await page.locator('.wc-block-cart-items__row').first().waitFor();
        assert.equal(await page.locator('.wc-block-cart-items__row').count(), 2); await capture(page, 'cart-smoke');
        const response = await context.request.get(base + '/index.php?rest_route=/wc/store/v1/cart');
        const cart = await response.json(); const token = response.headers()['cart-token'];
        assert.equal(cart.items_count, 3); assert.equal(cart.items.length, 2);
        const refused = await context.request.post(base + '/index.php?rest_route=/wc/store/v1/cart/add-item', { headers: { 'Cart-Token': token }, data: { id: unavailableId, quantity: 1 } });
        assert.ok(refused.status() >= 400); assert.match((await refused.json()).code, /stock/);
        await page.goto(seed.checkout); await page.locator('.wc-block-checkout').first().waitFor(); await page.locator('#email').waitFor();
        await capture(page, 'checkout-guest-smoke');
        await context.request.delete(base + '/index.php?rest_route=/wc/store/v1/cart/items', { headers: { 'Cart-Token': token } });
        return { simpleQuantity: 2, variableQuantity: 1, exhaustedRejected: true, checkoutGuest: true, paymentSubmitted: false };
    });
    await check('Reduced motion and no-JS native variations', async () => {
        await page.emulateMedia({ reducedMotion: 'reduce' }); await page.goto(product('remera-base').url);
        const motion = await page.locator('.cb-product-card').first().evaluate(n => getComputedStyle(n.querySelector('img')).transitionDuration);
        assert.ok(motion.split(',').every(t => parseFloat(t) <= .01));
        const noJs = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
        const np = await noJs.newPage(); await np.goto(product('remera-base').url);
        assert.ok(await np.locator('select[name="attribute_pa_talle"]').isVisible());
        assert.ok(await np.locator('select[name="attribute_pa_color"]').isVisible()); await noJs.close();
    });
    await check('Home consumes catalog and real category archives', async () => {
        await page.goto(base); assert.equal(await page.locator('.cb-home-products .cb-product-card').count(), 4);
        assert.equal(await page.locator('.cb-category-note').count(), 0);
        const links = await page.locator('.cb-category-link').evaluateAll(ns => ns.map(n => n.href));
        for (const c of seed.categories) assert.ok(links.includes(c.url));
        return { latestProducts: 4, categoriesPopulated: 4 };
    });
    await check('No JavaScript or local asset errors', async () => { assert.deepEqual(report.errors, []); assert.deepEqual(report.assetErrors, []); });
    await browser.close();
    report.finished = new Date().toISOString(); report.passed = report.checks.filter(c => c.result === 'passed').length; report.failed = report.checks.filter(c => c.result === 'failed').length;
    fs.writeFileSync(path.join(output, process.env.H5_SCOPE === 'flows' ? 'browser-qa-flows.json' : 'browser-qa.json'), JSON.stringify(report, null, 2) + '\n');
    console.log(JSON.stringify({ passed: report.passed, failed: report.failed, errors: report.errors.length, captures: report.captures.length }));
    if (report.failed) process.exitCode = 1;
})().catch(e => { console.error(e.message); process.exitCode = 1; });
