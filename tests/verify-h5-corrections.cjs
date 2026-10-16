/** Post-H5: native behavior, three browser engines and visual evidence. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
process.env.PLAYWRIGHT_BROWSERS_PATH ||= 'C:\\Users\\ramiro\\AppData\\Local\\ms-playwright';
const engines = require('playwright');
const output = path.resolve(__dirname, '../.local/local/h5');
const base = 'http://localhost:8080';
const report = { checks: [], errors: [], captures: [], started: new Date().toISOString() };
let currentCheck = '';
async function check(name, task) {
    if (process.env.H5_CORRECTIONS_CHECK && !new RegExp(process.env.H5_CORRECTIONS_CHECK).test(name)) return;
    currentCheck = name;
    try { report.checks.push({ name, result: 'passed', evidence: await task() }); }
    catch (error) { console.log('FAIL', name, error.message.slice(0, 350), error.stack?.split('\n').find(line => line.includes('verify-h5-corrections.cjs:'))); report.checks.push({ name, result: 'failed', error: error.message, stack: error.stack }); }
}
async function go(p, query) {
    // IA cart updates also synchronize the public store after the optimistic UI.
    // Let those native requests settle before forcing a test-only navigation.
    if (p.url().startsWith(base) && !/page_id=10|wp-admin/.test(p.url())) await settleNative(p);
    await p.goto(base + '/' + query, { waitUntil: query.includes('page_id=10') ? 'commit' : 'domcontentloaded' }); await p.locator('main').waitFor();
    if (query.includes('page_id=10')) await p.waitForFunction(() => getComputedStyle(document.body).getPropertyValue('--cb-space-6').trim() === '2rem');
    await p.evaluate(() => document.fonts.ready); if (!query.includes('page_id=10')) await settleNative(p); await p.waitForTimeout(250);
}
async function settleNative(p) {
    const network = p.cbNativeNetwork;
    if (!network) return;
    const deadline = Date.now() + 15000;
    while (network.pending.size || Date.now() - network.lastActivity < 500) {
        assert.ok(Date.now() < deadline, 'Native requests did not settle: ' + [...network.pending].map(r => r.url()).join(', '));
        await p.waitForTimeout(50);
    }
}
async function capture(p, name, fullPage = false) {
    if (fullPage) await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 650) { window.scrollTo({ top: y, behavior: 'instant' }); await new Promise(r => setTimeout(r, 40)); } await Promise.race([Promise.all([...document.images].filter(i => i.src.startsWith(location.origin)).map(i => i.decode().catch(() => {}))), new Promise(r => setTimeout(r, 2000))]); });
    await p.evaluate(() => { document.activeElement?.blur(); window.scrollTo({ top: 0, behavior: 'instant' }); });
    await p.waitForTimeout(500); await p.screenshot({ path: path.join(output, name + '.png'), fullPage }); report.captures.push(name + '.png');
}
async function clearCart(p) {
    const response = await p.request.get(base + '/index.php?rest_route=/wc/store/v1/cart');
    const token = response.headers()['cart-token'];
    if (token) await p.request.delete(base + '/index.php?rest_route=/wc/store/v1/cart/items', { headers: { 'Cart-Token': token } });
}
const badge = p => p.locator('.cb-header-cart .wc-block-mini-cart__badge');
const card = (p, title) => p.locator('.cb-product-card').filter({ has: p.getByRole('heading', { name: title, exact: true }) }).first();
(async () => {
    for (const engine of (process.env.H5_CORRECTIONS_ENGINES || 'chromium,firefox,webkit').split(',')) {
        const browser = await engines[engine].launch({ headless: true });
        const context = await browser.newContext(); const p = await context.newPage();
        p.cbNativeNetwork = { pending: new Set(), lastActivity: Date.now() };
        p.on('request', r => { if (r.url().startsWith(base) && ['fetch', 'xhr'].includes(r.resourceType())) { p.cbNativeNetwork.pending.add(r); p.cbNativeNetwork.lastActivity = Date.now(); } });
        for (const event of ['requestfinished', 'requestfailed']) p.on(event, r => { if (p.cbNativeNetwork.pending.delete(r)) p.cbNativeNetwork.lastActivity = Date.now(); });
        p.setDefaultTimeout(14000); p.setDefaultNavigationTimeout(25000);
        p.on('pageerror', error => { console.log('PAGEERROR', engine, currentCheck, error.stack); report.errors.push({ engine, check: currentCheck, url: p.url(), error: error.message, stack: error.stack }); });
        if (process.env.H5_CORRECTIONS_SCOPE !== 'flows') for (const theme of ['light', 'dark']) {
            await p.emulateMedia({ colorScheme: theme });
            for (const width of [320, 390, 1024, 1366, 1920, 3840]) {
                await p.setViewportSize({ width, height: width === 1920 ? 1080 : 900 });
                await check(`${engine}/${theme}/${width}/gallery-guide`, async () => {
                    await go(p, '?product=remera-base');
                    const guide = p.locator('.cb-size-guide summary');
                    assert.equal(await guide.locator('span').count(), 0);
                    await guide.hover(); await guide.focus(); await p.keyboard.press('Enter');
                    assert.ok(await p.locator('.cb-size-guide').evaluate(n => n.open));
                    await p.keyboard.press('Space'); assert.ok(!(await p.locator('.cb-size-guide').evaluate(n => n.open)));
                    const trigger = p.locator('.woocommerce-product-gallery__trigger');
                    await trigger.waitFor();
                    await p.evaluate(() => { const trigger = document.querySelector('.woocommerce-product-gallery__trigger'); const header = document.querySelector('.cb-header'); window.scrollTo({ top: scrollY + trigger.getBoundingClientRect().top - header.getBoundingClientRect().height / 2, behavior: 'instant' }); });
                    const stacking = await p.evaluate(() => { const r = document.querySelector('.woocommerce-product-gallery__trigger').getBoundingClientRect(); return !!document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2)?.closest('.cb-header'); });
                    assert.ok(stacking, 'Header must paint above gallery control');
                    await p.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
                    await trigger.click(); await p.locator('.pswp--open').waitFor();
                    assert.ok(await p.evaluate(() => !!document.elementFromPoint(innerWidth / 2, 40)?.closest('.pswp')));
                    const layers = await p.evaluate(() => ({ gallery: getComputedStyle(document.querySelector('.cb-product-gallery')).zIndex, header: getComputedStyle(document.querySelector('.cb-header')).zIndex, lightbox: getComputedStyle(document.querySelector('.pswp')).zIndex }));
                    assert.deepEqual(layers, { gallery: '0', header: '40', lightbox: '100' });
                    await p.keyboard.press('Escape'); await p.locator('.pswp--open').waitFor({ state: 'hidden' }); await p.waitForTimeout(500);
                    if (engine === 'chromium' && [320, 1920, 3840].includes(width)) await capture(p, `corrections-product-${theme}-${width}`);
                    return { headerAboveControl: true, lightboxAboveHeader: true, singleNativeMarker: true, keyboard: true };
                });
                await check(`${engine}/${theme}/${width}/catalog-native-controls`, async () => {
                    await go(p, '?post_type=product');
                    assert.equal(await p.locator('.cb-catalog-collection .cb-product-card').count(), 15);
                    assert.equal(await card(p, 'Remera Base').locator('.cb-color-swatch').count(), 2);
                    assert.match(await card(p, 'Remera Base').innerText(), /Disponible en.*Natural.*Negro/);
                    assert.equal(await card(p, 'Buzo Textura').locator('.cb-color-swatch').count(), 0);
                    assert.equal(await card(p, 'Bolsa Diario').locator('.cb-color-swatch').count(), 0);
                    assert.equal(await card(p, 'Remera Base').locator('.product_type_variable').getAttribute('href'), base + '/?product=remera-base');
                    const select = await p.locator('select.orderby').evaluate(n => ({ scheme: getComputedStyle(n).colorScheme, color: getComputedStyle(n).color, bg: getComputedStyle(n).backgroundColor, options: [...n.options].map(o => ({ color: getComputedStyle(o).color, bg: getComputedStyle(o).backgroundColor })) }));
                    assert.equal(select.scheme, theme);
                    for (const option of select.options) { assert.equal(option.color, select.color); assert.equal(option.bg, select.bg); }
                    await p.locator('select.orderby').focus(); await p.keyboard.press('Escape');
                    await Promise.all([p.waitForURL(/orderby=date/, { waitUntil: 'domcontentloaded' }), p.locator('select.orderby').selectOption('date')]);
                    await p.waitForLoadState('networkidle');
                    if (width < 783) { await p.locator('.wc-block-product-filters__open-overlay').click(); await p.locator('.wc-block-product-filters__overlay-dialog').waitFor(); }
                    const range = p.locator('.wc-block-product-filter-price-slider__range'); await range.scrollIntoViewIfNeeded();
                    const geometry = await range.evaluate(n => { const track = n.querySelector('.range-bar').getBoundingClientRect(); return [...n.querySelectorAll('input')].map(i => { const r = i.getBoundingClientRect(); return { difference: Math.abs(r.y + r.height / 2 - track.y - track.height / 2), target: r.height }; }); });
                    for (const handle of geometry) { assert.ok(handle.difference < 1, JSON.stringify(geometry)); assert.ok(handle.target >= 44); }
                    const min = range.locator('input.min'); const before = Number(await min.inputValue()); await min.focus(); await p.keyboard.press('ArrowRight');
                    await p.waitForFunction(v => Number(document.querySelector('.wc-block-product-filter-price-slider__range input.min').value) > v, before);
                    await p.waitForURL(/min_price=/, { waitUntil: 'domcontentloaded' }); await settleNative(p);
                    // Native facet bounds may change with the returned products;
                    // use its actual clear action to restore the full interval.
                    await p.getByRole('button', { name: 'Limpiar filtros', exact: true }).click();
                    await p.waitForURL(u => !u.searchParams.has('min_price'), { waitUntil: 'domcontentloaded' }); await settleNative(p);
                    if (width < 783) { await p.locator('.wc-block-product-filters__close-overlay').click(); await p.waitForFunction(() => !document.querySelector('.cb-filters').classList.contains('is-overlay-opened')); await p.waitForTimeout(450); }
                    assert.ok(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
                    if (engine === 'chromium' && [320, 1920, 3840].includes(width)) await capture(p, `corrections-catalog-${theme}-${width}`, true);
                    return { select, geometry, swatches: true, keyboardRange: true };
                });
            }
        }
        await p.emulateMedia({ colorScheme: 'dark' }); await p.setViewportSize({ width: 1440, height: 1000 });
        await check(`${engine}/range-mouse-both-handles-and-zoom`, async () => {
            const evidence = [];
            for (const theme of ['light', 'dark']) {
                await p.emulateMedia({ colorScheme: theme }); await go(p, '?post_type=product');
                const range = p.locator('.wc-block-product-filter-price-slider__range'); await range.scrollIntoViewIfNeeded();
                const r = await range.boundingBox(); const min = range.locator('input.min'); const initialMin = Number(await min.inputValue());
                await p.mouse.move(r.x, r.y + r.height / 2); await p.mouse.down(); await p.mouse.move(r.x + 40, r.y + r.height / 2, { steps: 8 }); await p.mouse.up();
                await p.waitForURL(/min_price=/, { waitUntil: 'domcontentloaded' }); await p.waitForLoadState('networkidle'); assert.ok(Number(await min.inputValue()) > initialMin);
                await range.scrollIntoViewIfNeeded(); const high = await range.boundingBox(); const max = range.locator('input.max'); const initialMax = Number(await max.inputValue());
                await p.mouse.move(high.x + high.width, high.y + high.height / 2); await p.mouse.down(); await p.mouse.move(high.x + high.width - 40, high.y + high.height / 2, { steps: 8 }); await p.mouse.up();
                await p.waitForURL(/max_price=/, { waitUntil: 'domcontentloaded' }); await p.waitForLoadState('networkidle'); assert.ok(Number(await max.inputValue()) < initialMax);
                await go(p, '?post_type=product'); await p.evaluate(() => { document.body.style.zoom = '2'; });
                const centered = await range.evaluate(n => { const t = n.querySelector('.range-bar').getBoundingClientRect(); return [...n.querySelectorAll('input')].every(i => { const r = i.getBoundingClientRect(); return Math.abs(r.y + r.height / 2 - t.y - t.height / 2) < 1; }); });
                assert.ok(centered); await p.evaluate(() => { document.body.style.zoom = ''; }); await range.scrollIntoViewIfNeeded();
                const shot = await range.boundingBox();
                await p.screenshot({ path: path.join(output, `corrections-range-${engine}-${theme}.png`), clip: { x: shot.x - 23, y: shot.y - 4, width: shot.width + 46, height: shot.height + 8 } });
                evidence.push({ theme, bothMouseDragsFilter: true, cssZoom200Centered: centered });
            }
            return evidence;
        });
        if (engine === 'chromium') await check('chromium/mobile-touch-range', async () => {
            const touchContext = await browser.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, deviceScaleFactor: 2 });
            const touch = await touchContext.newPage();
            try {
                await go(touch, '?post_type=product'); await touch.locator('.wc-block-product-filters__open-overlay').tap();
                const range = touch.locator('.wc-block-product-filter-price-slider__range'); await range.scrollIntoViewIfNeeded(); const r = await range.boundingBox();
                const client = await touchContext.newCDPSession(touch);
                await client.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: r.x, y: r.y + r.height / 2 }] });
                await client.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: r.x + 45, y: r.y + r.height / 2 }] });
                await client.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
                await touch.waitForURL(/min_price=/, { waitUntil: 'domcontentloaded' }); await touch.waitForLoadState('networkidle'); assert.ok(Number(await range.locator('input.min').inputValue()) > 11000);
                return { realTouchDrag: true, dpr: 2, width: 390 };
            } finally { await touchContext.close(); }
        });
        await check(`${engine}/native-quick-add-counter-cart`, async () => {
            await go(p, '?post_type=product'); assert.ok(!(await badge(p).isVisible()));
            // Observe loading with a delayed response in Chromium/Firefox;
            // exercise WebKit's unmodified native network path.
            if (engine !== 'webkit') await p.route('**/*batch*', async route => { await p.waitForTimeout(1000); await route.continue(); });
            const button = card(p, 'Bolsa Diario').locator('button'); const url = p.url();
            await button.focus(); await p.keyboard.press('Enter');
            if (engine !== 'webkit') {
                await p.waitForFunction(() => document.querySelector('.cb-card-purchase button[data-product_id="154"]')?.getAttribute('aria-busy') === 'true');
                assert.ok(await button.isDisabled());
            }
            assert.ok(await button.evaluate(n => document.activeElement === n)); await badge(p).filter({ hasText: /^1$/ }).waitFor();
            await p.waitForFunction(() => document.querySelector('.cb-card-purchase button[data-product_id="154"]')?.getAttribute('aria-busy') === 'false');
            await settleNative(p);
            if (engine !== 'webkit') await p.unrouteAll({ behavior: 'wait' });
            assert.equal(p.url(), url); await p.waitForFunction(() => document.querySelector('.cb-card-purchase button[data-product_id="154"]')?.innerText.includes('1 en el carrito'));
            await p.locator('.wc-block-mini-cart__button').click(); await p.locator('.wc-block-mini-cart__drawer').waitFor();
            const qty = p.locator('.wc-block-mini-cart__drawer .wc-block-components-quantity-selector__input');
            const miniUpdate = p.waitForResponse(r => r.request().method() === 'POST' && /cart\/update-item|batch/.test(decodeURIComponent(r.url())));
            await qty.fill('3'); await qty.press('Tab'); assert.ok((await miniUpdate).ok());
            await p.waitForFunction(() => document.querySelector('.cb-header-cart .wc-block-mini-cart__badge')?.textContent === '3');
            await p.waitForFunction(() => wp.data.select(wc.wcBlocksData.cartStore).getItemsPendingQuantityUpdate().length === 0);
            await p.keyboard.press('Escape');
            await go(p, '?page_id=8'); await p.locator('.wc-block-cart-items__row .wc-block-components-product-name').first().waitFor();
            assert.equal(await badge(p).innerText(), '3');
            const cartQty = p.locator('.wc-block-cart-items__row .wc-block-components-quantity-selector__input');
            const cartUpdate = p.waitForResponse(r => r.request().method() === 'POST' && /cart\/update-item|batch/.test(decodeURIComponent(r.url())));
            await cartQty.fill('2'); await cartQty.press('Tab'); assert.ok((await cartUpdate).ok());
            await p.waitForFunction(() => document.querySelector('.cb-header-cart .wc-block-mini-cart__badge')?.textContent === '2');
            await p.waitForFunction(() => wp.data.select(wc.wcBlocksData.cartStore).getItemsPendingQuantityUpdate().length === 0);
            await p.locator('.wc-block-cart-item__remove-link').click(); await badge(p).waitFor({ state: 'hidden' });
            await go(p, '?product=bolsa-diario'); await p.locator('input.qty').fill('2');
            await Promise.all([p.waitForNavigation({ waitUntil: 'domcontentloaded' }), p.locator('.single_add_to_cart_button').click()]);
            await p.waitForFunction(() => document.querySelector('.cb-header-cart .wc-block-mini-cart__badge')?.textContent === '2');
            await go(p, '?page_id=9'); await p.locator('#email').waitFor(); assert.equal(await badge(p).innerText(), '2');
            return { loadingObservedWithDelayedNativeRequest: engine !== 'webkit', realNetworkWithoutRouting: engine === 'webkit', focusPreserved: true, noReload: true, badge: '0 → 1 → 3 → 2 → empty → 2', nativeMiniCartAndCartUpdates: true, productPostUpdates: true };
        });
        await check(`${engine}/Home-native-quick-add`, async () => {
            await clearCart(p); await go(p, ''); const button = p.locator('.cb-home-products .cb-card-purchase button').first();
            await button.click(); await p.waitForFunction(() => document.querySelector('.cb-header-cart .wc-block-mini-cart__badge')?.textContent === '1');
            await p.waitForFunction(() => wp.data.select(wc.wcBlocksData.cartStore).getProductsPendingAdd().length === 0);
            await p.waitForFunction(() => document.querySelector('.cb-home-products .cb-card-purchase button')?.innerText.includes('1 en el carrito'));
            assert.match(await button.innerText(), /1 en el carrito/);
            const productUrl = await p.locator('.cb-home-products .cb-product-title a').first().getAttribute('href');
            await clearCart(p); await p.waitForLoadState('networkidle'); await p.goto(productUrl, { waitUntil: 'domcontentloaded' });
            const maximum = await p.locator('input.qty').getAttribute('max'); await p.locator('input.qty').fill(maximum);
            await Promise.all([p.waitForNavigation({ waitUntil: 'domcontentloaded' }), p.locator('.single_add_to_cart_button').click()]);
            await go(p, ''); await p.locator('.cb-home-products .cb-card-purchase button').first().click();
            await p.locator('.cb-home-products .wc-block-components-notice-banner.is-error').waitFor();
            assert.equal(await badge(p).innerText(), maximum); await clearCart(p);
            // Leave the same two units needed by the no-payment checkout test.
            await go(p, '?product=bolsa-diario'); await p.locator('input.qty').fill('2');
            await Promise.all([p.waitForNavigation({ waitUntil: 'domcontentloaded' }), p.locator('.single_add_to_cart_button').click()]);
            return { nativeProductContext: true, dynamicAdd: true, nativeStockError: true };
        });
        if (engine === 'chromium') {
            await check('checkout/native-no-payment-error-i18n', async () => {
                // Valid synthetic local buyer, no enabled gateway and no payment data.
                await go(p, '?page_id=9');
                await p.locator('#email').fill('h5-corrections@example.test');
                for (const [id, value] of Object.entries({ 'shipping-first_name': 'Prueba', 'shipping-last_name': 'H5', 'shipping-address_1': 'Av. Siempreviva 123', 'shipping-city': 'Buenos Aires', 'shipping-postcode': '1001' })) await p.locator('#' + id).fill(value);
                await p.locator('#shipping-state').selectOption('C');
                await p.locator('.wc-block-components-checkout-place-order-button').click();
                await p.getByText('Corregí el siguiente error antes de continuar', { exact: true }).waitFor();
                const notices = await p.locator('.wc-block-components-notice-banner').allTextContents();
                assert.match(notices.join(' '), /No se facilitó ningún método de pago/); assert.ok(!notices.join(' ').includes('Please fix'));
                const plural = await p.evaluate(() => wp.i18n._n('Please fix the following error before continuing', 'Please fix the following errors before continuing', 2, 'woocommerce'));
                assert.equal(plural, 'Corregí los siguientes errores antes de continuar');
                await capture(p, 'corrections-checkout-spanish', true);
                return { notices, plural, gatewayEnabled: false, paymentDataSent: false };
            });
            await check('quick-add/real-stock-error-and-recovery', async () => {
                await clearCart(p); await go(p, '?product=bolsa-diario');
                const maximum = await p.locator('input.qty').getAttribute('max'); await p.locator('input.qty').fill(maximum);
                await Promise.all([p.waitForNavigation({ waitUntil: 'domcontentloaded' }), p.locator('.single_add_to_cart_button').click()]);
                await go(p, '?post_type=product'); await card(p, 'Bolsa Diario').locator('button').click();
                await p.locator('.wc-block-components-notice-banner.is-error').first().waitFor();
                assert.match((await p.locator('.wc-block-components-notice-banner.is-error').allTextContents()).join(' '), /stock|disponib|suficiente|podés|puedes/i);
                assert.equal(await badge(p).innerText(), maximum); assert.ok(!(await card(p, 'Bolsa Diario').locator('button').isDisabled()));
                return { realNativeStockRejection: true, unchangedCount: maximum, recoveredButton: true };
            });
            await clearCart(p);
            await check('account/scoped-titles-and-action-spacing', async () => {
                const env = fs.readFileSync(path.resolve(__dirname, '../.local/local/.env'), 'utf8');
                const user = env.match(/^LOCAL_ADMIN_USER=(.+)$/m)[1].trim();
                const password = fs.readFileSync(path.resolve(__dirname, '../.local/local/secrets/wp-admin-password.txt'), 'utf8').trim();
                await go(p, '?page_id=10');
                await p.locator('.woocommerce-form-login #username').fill(user); await p.locator('.woocommerce-form-login #password').fill(password);
                await settleNative(p);
                await Promise.all([p.waitForNavigation({ waitUntil: 'commit' }), p.locator('.woocommerce-form-login button[name="login"]').click()]);
                const evidence = [];
                for (const theme of ['light', 'dark']) for (const width of [320, 390, 1024, 1366, 1920, 3840]) {
                    await p.emulateMedia({ colorScheme: theme }); await p.setViewportSize({ width, height: width === 1920 ? 1080 : 900 });
                    await go(p, '?page_id=10&edit-address');
                    const titles = await p.locator('.woocommerce-Address-title h2').evaluateAll(ns => ns.map(n => ({ text: n.textContent, font: parseFloat(getComputedStyle(n).fontSize) })));
                    assert.equal(titles.length, 2); assert.ok(titles.every(n => n.font >= 18 && n.font <= 24));
                    assert.ok(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
                    if ([320, 1920, 3840].includes(width)) await capture(p, `corrections-addresses-${theme}-${width}`, true);
                    await go(p, '?page_id=10&edit-account');
                    const gap = await p.evaluate(() => { const f = document.querySelector('.woocommerce-EditAccountForm fieldset').getBoundingClientRect(); const b = document.querySelector('button[name="save_account_details"]').getBoundingClientRect(); return b.top - f.bottom; });
                    assert.ok(gap >= 32); evidence.push({ theme, width, titles, gap });
                    if ([320, 1920].includes(width)) await capture(p, `corrections-edit-account-${theme}-${width}`, true);
                }
                return evidence;
            });
        }
        await clearCart(p); if (engine !== 'chromium') await settleNative(p); await browser.close();
        console.log(engine, 'complete');
    }
    report.finished = new Date().toISOString(); report.passed = report.checks.filter(c => c.result === 'passed').length; report.failed = report.checks.length - report.passed;
    const suffix = process.env.H5_CORRECTIONS_CHECK ? '-targeted' : process.env.H5_CORRECTIONS_SCOPE === 'flows' ? '-flows' : process.env.H5_CORRECTIONS_ENGINES ? '-' + process.env.H5_CORRECTIONS_ENGINES.replaceAll(',', '-') : '';
    fs.writeFileSync(path.join(output, 'corrections' + suffix + '-qa.json'), JSON.stringify(report, null, 2) + '\n');
    console.log(JSON.stringify({ passed: report.passed, failed: report.failed, errors: report.errors }));
    if (report.failed || report.errors.length) process.exitCode = 1;
})().catch(error => { console.error(error.message); process.exitCode = 1; });
