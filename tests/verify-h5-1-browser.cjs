/** H5.1 real frontend, responsive geometry, touch, keyboard, degradation and smoke. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
process.env.PLAYWRIGHT_BROWSERS_PATH ||= 'C:\\Users\\ramiro\\AppData\\Local\\ms-playwright';
const { chromium, firefox, webkit } = require('playwright');
const root = path.resolve(__dirname, '..');
const output = path.join(root, '.local/local/h5-1');
const functional = JSON.parse(fs.readFileSync(path.join(output, 'functional-qa.json'), 'utf8'));
const widths = [320, 375, 390, 430, 768, 1024, 1366, 1920, 2560, 3440, 3840];
const base = 'http://localhost:8080/';
const report = { checks: [], errors: [], captures: [], widths };
async function check(name, task) {
    try { const evidence = await task(); report.checks.push({ name, result: 'passed', evidence }); }
    catch (error) { console.log('FAIL', name, error.message.slice(0, 350)); report.checks.push({ name, result: 'failed', error: error.message }); }
}
function watch(p) { p.on('pageerror', e => report.errors.push(e.message)); }
async function home(p) {
    await p.goto(base, { waitUntil: 'domcontentloaded' });
    await p.locator('.slb-enhanced').waitFor();
    await center(p);
}
async function center(p) {
    await p.locator('.slb-stage').evaluate(n => n.scrollIntoView({ behavior: 'instant', block: 'center' }));
    await p.waitForFunction(() => { const i = document.querySelector('.slb-image'); return i.complete && i.naturalWidth > 0; });
    await p.evaluate(() => document.fonts.ready);
    await p.waitForTimeout(160);
}
async function capture(p, name) {
    await p.screenshot({ path: path.join(output, name + '.png') }); report.captures.push(name + '.png');
}
async function geometry(p, index, edge = false) {
    const link = p.locator('.slb-hotspot').nth(index);
    await link.focus(); await p.waitForTimeout(160);
    const result = await link.evaluate(n => {
        const a = n.getBoundingClientRect(), s = n.closest('.slb-stage').getBoundingClientRect();
        const pill = n.querySelector('.slb-pill').getBoundingClientRect(), image = n.closest('.slb-stage').querySelector('img');
        const rgb = value => value.match(/[\d.]+/g).slice(0, 3).map(Number);
        const luminance = rgb => rgb.map(v => { v /= 255; return v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4; }).reduce((sum, v, i) => sum + v * [.2126, .7152, .0722][i], 0);
        const ps = getComputedStyle(n.querySelector('.slb-pill'));
        const bg = luminance(rgb(ps.backgroundColor)), fg = luminance(rgb(ps.color));
        return { anchor: a.toJSON(), stage: s.toJSON(), pill: pill.toJSON(),
            x: (a.x + a.width / 2 - s.x) / s.width * 100, y: (a.y + a.height / 2 - s.y) / s.height * 100,
            ratio: s.width / s.height, imageRatio: image.width / image.height,
            expanded: n.getAttribute('aria-expanded'), activeCount: n.closest('.slb-lookbook').querySelectorAll('.is-active').length,
            viewport: document.documentElement.clientWidth, overflow: document.documentElement.scrollWidth > innerWidth + 1,
            label: n.getAttribute('aria-label'), target: n.getAttribute('href'), outline: getComputedStyle(n).outlineStyle,
            contrast: (Math.max(bg, fg) + .05) / (Math.min(bg, fg) + .05) };
    });
    assert.equal(result.expanded, 'true'); assert.equal(result.activeCount, 1);
    assert.ok(!result.overflow); assert.ok(Math.abs(result.ratio - 1.5) < .005, 'uncropped 3:2 image');
    assert.equal(result.anchor.width, 44); assert.equal(result.anchor.height, 44);
    assert.ok(result.contrast >= 4.5, 'pill text contrast');
    assert.match(result.label, /^Ver .+,/); assert.match(result.target, /\?product=/);
    assert.ok(result.pill.left >= Math.max(0, result.stage.left) - 1, 'left edge');
    assert.ok(result.pill.right <= Math.min(result.viewport, result.stage.right) + 1, 'right edge');
    assert.ok(result.pill.top >= result.stage.top - 1 && result.pill.bottom <= result.stage.bottom + 1, 'vertical edges');
    if (!edge) {
        assert.ok(Math.abs(result.x - [62, 64][index]) < .05, 'proportional X');
        assert.ok(Math.abs(result.y - [19, 64][index]) < .05, 'proportional Y');
    }
    return result;
}
(async () => {
    const engines = (process.env.H51_ENGINES || 'chromium,firefox,webkit').split(',');
    for (const name of engines) {
        const browser = await ({ chromium, firefox, webkit })[name].launch();
        const context = await browser.newContext({ viewport: { width: 1366, height: 1000 } });
        const p = await context.newPage(); p.setDefaultTimeout(12000); p.setDefaultNavigationTimeout(30000); watch(p);
        try {
            const matrix = name === 'chromium' ? widths : [320, 390, 1024, 1920];
            for (const theme of ['light', 'dark']) for (const width of matrix) {
                await check(`${name}/${theme}/${width}/home-geometry`, async () => {
                    await p.emulateMedia({ colorScheme: theme }); await p.setViewportSize({ width, height: width >= 1920 ? 1080 : 1000 });
                    await home(p); const items = [];
                    for (let i = 0; i < 2; i++) items.push(await geometry(p, i));
                    if (name === 'chromium' && [320, 390, 1366, 1920, 3440].includes(width)) await capture(p, `${theme}-${width}-open`);
                    return items;
                });
                await check(`${name}/${theme}/${width}/four-corners`, async () => {
                    await p.locator('.slb-lookbook').evaluate((n, html) => { n.outerHTML = html; }, functional.edge_fixture_html);
                    await p.addScriptTag({ path: path.join(root, 'commerce-lookbook/block/view.js') }); await center(p);
                    const items = [];
                    for (let i = 0; i < 4; i++) items.push(await geometry(p, i, true));
                    assert.match(await p.locator('.slb-hotspot').nth(2).getAttribute('aria-label'), /Agotado/);
                    if (name === 'chromium' && width === 320) await capture(p, `${theme}-320-edges`);
                    return items;
                });
            }
            await check(`${name}/hover-keyboard-navigation`, async () => {
                await p.setViewportSize({ width: 1366, height: 1000 }); await home(p);
                const first = p.locator('.slb-hotspot').first(), last = p.locator('.slb-hotspot').last();
                await first.hover(); assert.equal(await first.getAttribute('aria-expanded'), 'true');
                await p.mouse.move(5, 500); assert.equal(await first.getAttribute('aria-expanded'), 'false');
                await first.focus(); await p.keyboard.press('Tab'); assert.ok(await last.evaluate(n => n === document.activeElement));
                assert.equal(await first.getAttribute('aria-expanded'), 'false'); assert.equal(await last.getAttribute('aria-expanded'), 'true');
                await p.keyboard.press('Escape'); assert.equal(await last.getAttribute('aria-expanded'), 'false');
                assert.ok(await last.evaluate(n => n === document.activeElement));
                await p.keyboard.press('Shift+Tab'); assert.equal(await first.getAttribute('aria-expanded'), 'true');
                assert.equal(await first.evaluate(n => getComputedStyle(n).outlineStyle), 'solid');
                await Promise.all([p.waitForURL(/product=remera-base/), p.keyboard.press('Enter')]);
                assert.match(await p.locator('main h1').innerText(), /Remera Base/);
                await home(p); await Promise.all([p.waitForURL(/product=pantalon-pausa/), p.locator('.slb-hotspot').last().click()]);
                assert.match(await p.locator('main h1').innerText(), /Pantalón Pausa/);
                return { hover: true, tabOrder: true, focusRing: true, escape: true, enter: true, bothProducts: true };
            });
            await check(`${name}/reduced-motion-and-header`, async () => {
                await p.emulateMedia({ reducedMotion: 'reduce' }); await home(p); await geometry(p, 0);
                assert.ok(await p.locator('.slb-pill').first().evaluate(n => getComputedStyle(n).transitionDuration.split(',').every(v => parseFloat(v) <= .001)));
                const layers = await p.evaluate(() => {
                    const header = document.querySelector('.cb-header');
                    const stage = document.querySelector('.slb-stage');
                    const a = document.querySelector('.slb-hotspot');
                    const delta = a.getBoundingClientRect().top - 30;
                    window.scrollBy({ top: delta, behavior: 'instant' });
                    const r = a.getBoundingClientRect();
                    return { header: getComputedStyle(header).zIndex, stage: getComputedStyle(stage).zIndex,
                        topHit: !!document.elementFromPoint(r.x + 22, 35)?.closest('.cb-header'), filter: getComputedStyle(stage.querySelector('img')).filter };
                });
                assert.equal(layers.filter, 'none'); assert.equal(layers.stage, '0'); assert.ok(layers.topHit, 'global header above hotspots');
                await p.emulateMedia({ reducedMotion: 'no-preference' }); return layers;
            });
            if (name === 'chromium') {
                await check('chromium/touch-first-second-other-outside', async () => {
                    const c = await browser.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true, deviceScaleFactor: 2 });
                    const touch = await c.newPage(); watch(touch);
                    try {
                        for (const theme of ['light', 'dark']) {
                            await touch.emulateMedia({ colorScheme: theme }); await home(touch);
                            const first = touch.locator('.slb-hotspot').first(), last = touch.locator('.slb-hotspot').last();
                            await first.tap(); assert.equal(touch.url(), base); assert.equal(await first.getAttribute('aria-expanded'), 'true');
                            await last.tap(); assert.equal(touch.url(), base); assert.equal(await first.getAttribute('aria-expanded'), 'false');
                            assert.equal(await last.getAttribute('aria-expanded'), 'true');
                            await touch.locator('.slb-hint').tap(); assert.equal(await last.getAttribute('aria-expanded'), 'false');
                            await first.tap(); await capture(touch, `touch-${theme}-390`);
                            await Promise.all([touch.waitForURL(/product=remera-base/), first.locator('.slb-pill').tap()]);
                        }
                        return { firstTapOpens: true, secondTapNavigates: true, otherClosesPrevious: true, outsideCloses: true, bothThemes: true };
                    } finally { await c.close(); }
                });
                await check('chromium/no-js', async () => {
                    const c = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 320, height: 900 } });
                    const nojs = await c.newPage();
                    try {
                        await nojs.goto(base); assert.equal(await nojs.locator('.slb-fallback a').count(), 2);
                        assert.ok(await nojs.locator('.slb-image').isVisible()); await nojs.locator('.slb-hotspot').first().focus();
                        await Promise.all([nojs.waitForURL(/product=remera-base/), nojs.keyboard.press('Enter')]);
                        return { image: true, realLinks: true, readableProductList: true };
                    } finally { await c.close(); }
                });
                await check('chromium/script-load-failure', async () => {
                    await p.route('**/commerce-lookbook/block/view.js*', r => r.abort()); await p.goto(base);
                    assert.equal(await p.locator('.slb-enhanced').count(), 0); const first = p.locator('.slb-hotspot').first();
                    await first.focus(); await first.locator('.slb-pill').waitFor({ state: 'visible' });
                    await Promise.all([p.waitForURL(/product=remera-base/), p.keyboard.press('Enter')]);
                    await p.unrouteAll(); return { cssFocusFallback: true, nativeLink: true };
                });
                await check('chromium/plugin-without-theme', async () => {
                    await p.setViewportSize({ width: 390, height: 900 });
                    await p.setContent(functional.edge_fixture_html);
                    await p.addStyleTag({ path: path.join(root, 'commerce-lookbook/block/style.css') });
                    await p.addScriptTag({ path: path.join(root, 'commerce-lookbook/block/view.js') });
                    await center(p);
                    for (let i = 0; i < 4; i++) await geometry(p, i, true);
                    assert.equal(await p.locator('.slb-pill').last().evaluate(n => getComputedStyle(n).backgroundColor), 'rgb(250, 249, 242)');
                    return { isolatedPluginCSSAndJS: true, noCommerceBaseStyles: true, allFourEdgesAndContrast: true };
                });
                await check('chromium/woo-smoke', async () => {
                    for (const url of ['?post_type=product', '?product=remera-base', '?product=bolsa-diario', '?page_id=8', '?page_id=9']) {
                        const response = await p.goto(base + url); assert.equal(response.status(), 200); await p.locator('main').waitFor();
                        assert.ok((await p.locator('main').innerText()).trim().length > 20);
                    }
                    await p.goto(base + '?product=bolsa-diario');
                    await Promise.all([p.waitForNavigation({ waitUntil: 'domcontentloaded' }), p.locator('.single_add_to_cart_button').click()]);
                    await p.goto(base + '?page_id=8'); await p.locator('.wc-block-cart-items__row .wc-block-components-product-name').first().waitFor();
                    await p.goto(base + '?page_id=9'); await p.locator('#email').waitFor();
                    assert.ok(await p.locator('.wc-block-components-checkout-place-order-button').isVisible());
                    await p.goto(base + '?page_id=8'); await p.locator('.wc-block-cart-item__remove-link').click();
                    await p.locator('.wc-block-cart-items__row .wc-block-components-product-name').first().waitFor({ state: 'hidden' });
                    return { routes200: 5, nativeAddToCart: true, checkoutFields: true, cartEmptied: true, noOrderOrPayment: true };
                });
            }
        } finally { await context.close(); await browser.close(); }
        console.log(name, 'complete');
    }
    report.passed = report.checks.filter(c => c.result === 'passed').length;
    report.failed = report.checks.length - report.passed;
    fs.writeFileSync(path.join(output, 'browser-qa.json'), JSON.stringify(report, null, 2) + '\n');
    console.log(JSON.stringify({ passed: report.passed, failed: report.failed, errors: report.errors }));
    if (report.failed || report.errors.length) process.exitCode = 1;
})().catch(e => { console.error(e); process.exitCode = 1; });
