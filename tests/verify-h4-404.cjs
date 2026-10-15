/** Routing regression: unknown paths with plain permalinks must not serve Home. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
process.env.PLAYWRIGHT_BROWSERS_PATH ||= 'C:/Users/ramiro/AppData/Local/ms-playwright';
const { chromium } = require('playwright');
const base = 'http://localhost:8080';
const output = '.local/local/h4';
(async () => {
    const browser = await chromium.launch({ headless: true });
    const report = { checks: [], errors: [] };
    try {
        const context = await browser.newContext();
        for (const path of ['/no-existe', '/404', '/pagina-inexistente/', '/ruta/inexistente/', '/?p=2147483647']) {
            const response = await context.request.get(base + path, { maxRedirects: 0 });
            assert.equal(response.status(), 404, path);
            assert.match(await response.text(), /cb-not-found/);
            report.checks.push({ path, status: response.status() });
        }
        const content = JSON.parse(fs.readFileSync(`${output}/content.json`, 'utf8'));
        for (const url of [base + '/', base + '/index.php', ...['conocenos', 'contacto', 'faq', 'shop', 'search'].map(key => content.urls[key]), base + '/?product=h2-variable', base + '/?page_id=8', base + '/?page_id=9', base + '/?rest_route=/wc/store/v1/products', base + '/wp-login.php']) {
            const response = await context.request.get(url);
            assert.equal(response.status(), 200, url);
            report.checks.push({ url, status: 200 });
        }
        await context.close();
        for (const width of [320, 390, 1440]) for (const colorScheme of ['light', 'dark']) {
            const context = await browser.newContext({ viewport: { width, height: 900 }, colorScheme });
            const page = await context.newPage();
            page.on('pageerror', error => report.errors.push(error.message));
            const response = await page.goto(base + '/pagina-inexistente/', { waitUntil: 'networkidle' });
            assert.equal(response.status(), 404);
            const home = page.getByRole('link', { name: 'Volver al inicio', exact: true });
            assert.equal(await home.getAttribute('href'), base + '/');
            assert.ok((await home.boundingBox()).y < 900, 'Home CTA must appear in the initial viewport');
            assert.equal(await page.locator('main h1').count(), 1);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth));
            await page.screenshot({ path: `${output}/404-path-${width}-${colorScheme}.png`, fullPage: true });
            await Promise.all([page.waitForURL(base + '/'), home.click()]);
            assert.equal(await page.locator('.cb-home-hero').count(), 1);
            report.checks.push({ width, colorScheme, homeButton: 'passed' });
            await context.close();
        }
        assert.deepEqual(report.errors, []);
        console.log(`PASS: ${report.checks.length} routing and 404 checks; Home button navigates correctly.`);
    } finally {
        fs.writeFileSync(`${output}/404-routing.json`, JSON.stringify(report, null, 2));
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
