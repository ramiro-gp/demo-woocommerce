/** Post-H3 regression: real native variations, cart and hydrated Checkout Blocks. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
process.env.PLAYWRIGHT_BROWSERS_PATH ||= 'C:\\Users\\ramiro\\AppData\\Local\\ms-playwright';
const instance = process.env.H3_INSTANCE || 'local';
assert.match(instance, /^[a-z][a-z0-9-]{0,29}$/);
const output = path.resolve(__dirname, `../.local/${instance}/h3/corrections`);
fs.mkdirSync(output, { recursive: true });
const baseline = JSON.parse(fs.readFileSync(path.resolve(output, '../baseline.json'), 'utf8')).commerce_state;
const home = baseline.home;
assert.equal(new URL(home).hostname, 'localhost');
const report = { home, checks: [], errors: [], screenshots: [] };
async function check(name, task) { try { const details = await task(); report.checks.push({ name, pass: true, details }); console.log('PASS', name); } catch (error) { report.checks.push({ name, pass: false, error: error.message }); console.error('FAIL', name, error.message); } }
async function capture(page, name, locator) { await (locator || page).screenshot({ path: path.join(output, name), ...(locator ? {} : { fullPage: true }) }); report.screenshots.push(name); }
async function noOverflow(page) { assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1), true); }
async function settle(page) { await page.waitForTimeout(400); }
(async () => {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const page = await context.newPage();
    page.on('pageerror', error => report.errors.push(error.message));
    const product = baseline.products.find(product => product.type === 'variable');
    assert.ok(product);
    // Derive permalink and variation IDs from the real page, never fixed database IDs.
    const productUrl = new URL(`/?post_type=product&p=${product.id}`, home).href;
    try {
        await check('header: one underline on hover/focus; progressive header and menu', async () => {
            await page.goto(new URL('/?commerce-base-showcase=1', home).href);
            const link = page.locator('.cb-header-catalog');
            await link.hover();
            assert.equal(await link.evaluate(x => getComputedStyle(x).textDecorationLine), 'none');
            assert.notEqual(await link.evaluate(x => getComputedStyle(x).backgroundImage), 'none');
            await link.focus();
            await page.keyboard.press('Tab');
            await page.keyboard.press('Shift+Tab');
            assert.equal(await link.evaluate(x => x.matches(':focus-visible')), true);
            assert.equal(await link.evaluate(x => getComputedStyle(x).textDecorationLine), 'none');
            await page.evaluate(() => window.scrollTo({ top: 140, behavior: 'instant' }));
            await settle(page);
            assert.equal(await page.evaluate(() => Number(getComputedStyle(document.documentElement).getPropertyValue('--cb-header-progress'))), 1);
            await page.locator('.cb-menu-trigger').click();
            await page.waitForFunction(() => document.querySelector('#cb-menu').dataset.state === 'open');
            await page.keyboard.press('Escape');
            await page.waitForFunction(() => !document.querySelector('#cb-menu').open);
        });
        await check('footer: content width, accessible signature and safe existing links', async () => {
            const credit = page.locator('.cb-footer-credit a');
            assert.equal(await credit.getAttribute('href'), 'https://ramita.dev');
            assert.equal(await credit.getAttribute('target'), '_blank');
            assert.equal(await credit.getAttribute('rel'), 'noopener noreferrer');
            const color = await credit.evaluate(x => getComputedStyle(x).color);
            assert.equal(await page.locator('.cb-credit-heart').evaluate(x => getComputedStyle(x).color), color);
            assert.equal(await page.locator('.cb-back-top').evaluate(x => x.clientWidth < 180), true);
            for (const link of await page.locator('.cb-footer a').evaluateAll(links => links.map(x => x.href))) {
                if (new URL(link).origin === new URL(home).origin) assert.ok((await context.request.get(link)).ok());
            }
            await page.locator('.cb-back-top').click();
            await page.waitForFunction(() => window.scrollY < 2);
        });
        await check('variations: real selection, exhausted M, native stock/SKU and reset', async () => {
            await page.goto(productUrl);
            await page.waitForSelector('.cb-variation-option');
            await settle(page);
            const options = page.locator('.cb-variation-option');
            assert.equal(await options.count(), 3);
            assert.equal(await page.locator('.variations select').evaluateAll(selects => selects.every(x => x.hidden)), true);
            assert.equal(await options.filter({ hasText: /^M/ }).isDisabled(), true);
            assert.match(await options.filter({ hasText: /^M/ }).innerText(), /Agotado/);
            await page.locator('.reset_variations').click();
            assert.ok(['', '0'].includes(await page.locator('input.variation_id').inputValue()));
            const size = page.getByRole('radiogroup', { name: 'Talle' }).getByRole('radio', { name: 'S', exact: true });
            await size.focus(); await page.keyboard.press('Space');
            await page.getByRole('radiogroup', { name: 'Color' }).getByRole('radio', { name: 'Negro', exact: true }).click();
            await page.waitForFunction(() => Number(document.querySelector('input.variation_id').value) > 0);
            assert.equal(await page.locator('#pa_talle').inputValue(), 's');
            assert.equal(await page.locator('#pa_color').inputValue(), 'negro');
            assert.equal(await size.getAttribute('aria-checked'), 'true');
            assert.equal(await page.locator('.qty').getAttribute('max'), '3');
            assert.match(await page.locator('.woocommerce-variation-availability').innerText(), /3/);
            assert.match(await page.locator('.sku').innerText(), /S-NEGRO/);
            await page.waitForFunction(() => !document.querySelector('.single_add_to_cart_button').classList.contains('disabled'));
            await Promise.all([page.waitForNavigation(), page.locator('.single_add_to_cart_button').click()]);
            await page.goto(baseline.pages.cart.url);
            await page.getByRole('link', { name: 'H2 — Producto variable técnico', exact: true }).first().waitFor();
            await page.locator('.wc-block-cart-items__row').first().waitFor();
            assert.match(await page.locator('.wc-block-cart-items__row').first().innerText(), /S|Negro/);
        });
        await check('checkout: labels via locale metadata, contrast, focus, radius and spacing', async () => {
            await page.goto(baseline.pages.checkout.url);
            await page.waitForSelector('#shipping-first_name');
            for (const [id, text] of [['shipping-last_name', 'Apellido'], ['shipping-country', 'País'], ['shipping-state', 'Provincia']]) {
                assert.equal(await page.locator(`label[for="${id}"]`).innerText(), text);
            }
            // Also hydrate the independent billing address.
            await page.getByLabel('Usar la misma dirección para la facturación', { exact: true }).uncheck();
            await page.waitForSelector('#billing-first_name');
            for (const [id, text] of [['billing-last_name', 'Apellido'], ['billing-country', 'País'], ['billing-state', 'Provincia']]) assert.equal(await page.locator(`label[for="${id}"]`).innerText(), text);
            await page.getByLabel('Usar la misma dirección para la facturación', { exact: true }).check();
            const radii = await page.locator('#shipping-first_name,#shipping-country,#shipping-state').evaluateAll(nodes => nodes.map(x => getComputedStyle(x).borderRadius));
            assert.deepEqual(radii, ['3px', '3px', '3px']);
            const spacing = await page.evaluate(() => {
                const country = document.querySelector('#shipping-country').closest('.wc-block-components-country-input').getBoundingClientRect();
                const next = document.querySelector('#shipping-address_1').closest('.wc-block-components-text-input').getBoundingClientRect();
                return next.top - country.bottom;
            });
            assert.ok(spacing >= 15, `country/address gap ${spacing}`);
            const stateOffset = await page.locator('#shipping-state').evaluate(node => node.getBoundingClientRect().top - node.closest('.wc-block-components-state-input').getBoundingClientRect().top);
            assert.ok(Math.abs(stateOffset) < 1, `Province has an internal top offset of ${stateOffset}px`);
            const checkbox = page.getByLabel('Crear una cuenta con TRAMA', { exact: true });
            for (const mode of ['light', 'dark']) {
                await page.locator('[data-cb-theme-select]').selectOption(mode);
                await settle(page);
                await checkbox.uncheck(); await checkbox.hover(); await checkbox.focus();
                await page.keyboard.press('Space');
                assert.equal(await checkbox.isChecked(), true);
                const colors = await checkbox.evaluate(input => {
                    const style = getComputedStyle(input), mark = getComputedStyle(input.nextElementSibling);
                    return { background: style.backgroundColor, fill: mark.fill, path: getComputedStyle(input.nextElementSibling.firstElementChild).fill, outline: style.outlineStyle };
                });
                assert.notEqual(colors.background, colors.fill);
                assert.equal(colors.fill, colors.path);
                assert.equal(colors.outline, 'solid');
                await capture(page, `checkbox-${mode}.png`, page.locator('.wc-block-checkout__create-account'));
                await checkbox.uncheck();
                // Inspect disabled presentation without persisting any setting.
                await checkbox.evaluate(x => { x.disabled = true; });
                assert.equal(await checkbox.evaluate(x => getComputedStyle(x).opacity), '0.5');
                await checkbox.evaluate(x => { x.disabled = false; });
            }
        });
        for (const mode of ['light', 'dark']) {
            for (const width of [320, 390, 768, 1024, 1366, 1920, 2560, 3840]) {
                await check(`${mode} ${width}px: footer, product related grid and checkout`, async () => {
                    await page.setViewportSize({ width, height: width < 500 ? 844 : 1000 });
                    await page.goto(new URL('/?commerce-base-showcase=1', home).href);
                    await page.locator('[data-cb-theme-select]').first().selectOption(mode); await settle(page); await noOverflow(page);
                    if ([390, 1366].includes(width)) await capture(page, `footer-${width}-${mode}.png`, page.locator('.cb-footer'));
                    await page.goto(productUrl); await page.waitForSelector('.cb-variation-option'); await settle(page); await noOverflow(page);
                    const grid = await page.locator('.wc-block-product-template').first().evaluate(ul => {
                        const child = ul.querySelector('li');
                        return { column: parseFloat(getComputedStyle(ul).gridTemplateColumns), child: child?.getBoundingClientRect().width };
                    });
                    assert.ok(grid.child > 100 && Math.abs(grid.child - grid.column) < 2, JSON.stringify(grid));
                    if ([390, 1366].includes(width)) await capture(page, `product-${width}-${mode}.png`);
                    await page.goto(baseline.pages.checkout.url); await page.waitForSelector('#shipping-first_name'); await settle(page); await noOverflow(page);
                    if ([390, 1366].includes(width)) await capture(page, `checkout-${width}-${mode}.png`);
                });
            }
        }
        await check('reduced motion: back to top; no-JS native attribute selects', async () => {
            await page.emulateMedia({ reducedMotion: 'reduce' });
            await page.locator('.cb-back-top').click();
            await page.waitForFunction(() => window.scrollY < 2);
            const noJS = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
            const plain = await noJS.newPage(); await plain.goto(productUrl);
            assert.equal(await plain.locator('.variations select').first().isVisible(), true);
            const selectBounds = await plain.locator('.variations select').first().evaluate(x => ({ height: x.getBoundingClientRect().height, width: x.getBoundingClientRect().width, scroll: x.scrollHeight, client: x.clientHeight }));
            assert.ok(selectBounds.height >= 48 && selectBounds.scroll <= selectBounds.client + 1, JSON.stringify(selectBounds));
            await noJS.close();
        });
        await check('cleanup: no public WordPress sample content', async () => {
            await page.goto(home);
            assert.doesNotMatch(await page.locator('main').innerText(), /Hello world!|Welcome to WordPress/);
            await page.goto(baseline.pages.cart.url);
            while (await page.locator('.wc-block-cart-item__remove-link').count()) { await page.locator('.wc-block-cart-item__remove-link').first().click(); await settle(page); }
        });
    } finally {
        await browser.close();
        fs.writeFileSync(path.join(output, 'browser.json'), JSON.stringify(report, null, 2));
    }
    assert.deepEqual(report.errors, []);
    assert.ok(report.checks.every(item => item.pass), 'Corrections QA has failures; see private report.');
})().catch(error => { console.error(error.message); process.exit(1); });
