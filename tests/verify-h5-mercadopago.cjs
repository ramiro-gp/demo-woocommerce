'use strict';
const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
process.env.PLAYWRIGHT_BROWSERS_PATH ||= 'C:\\Users\\ramiro\\AppData\\Local\\ms-playwright';
const output = path.resolve(__dirname, '../.local/local/h5');
const routes = JSON.parse(fs.readFileSync(path.join(output, 'mp-routes.json'), 'utf8').replace(/^\uFEFF/, ''));
(async () => {
    const browser = await chromium.launch();
    const context = await browser.newContext();
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    const base = new URL(routes.checkout).origin;
    if (base !== 'http://localhost:18081') throw Error('Only the preserved local H2 instance is allowed.');
    const api = base + '/index.php?rest_route=/wc/store/v1';
    let token;
    try {
        await page.goto(base + '/?add-to-cart=' + Number(routes.simple));
        await page.goto(routes.checkout);
        await page.getByText('Mercado Pago', { exact: false }).first().waitFor();
        await page.waitForTimeout(500);
        const officialScript = !!(await page.locator('script[src*="mercadopago"]').count());
        if (!officialScript) throw Error('Official MP script missing.');
        await page.screenshot({ path: path.join(output, 'mercadopago-checkout.png'), fullPage: true });
        const response = await context.request.get(api + '/cart');
        token = response.headers()['cart-token'];
        const result = { result: errors.length ? 'failed' : 'passed', gateway: routes.gateways, officialScript, errors, paymentSubmitted: false, orderSubmitted: false };
        fs.writeFileSync(path.join(output, 'mercadopago-smoke.json'), JSON.stringify(result, null, 2) + '\n');
        if (errors.length) throw Error('MP checkout had JavaScript errors.');
        console.log('PASS: Mercado Pago visible, official script loaded, no payment/order submitted.');
    } finally {
        if (!token) { const response = await context.request.get(api + '/cart'); token = response.headers()['cart-token']; }
        if (token) await context.request.delete(api + '/cart/items', { headers: { 'Cart-Token': token } });
        await browser.close();
    }
})().catch(e => { console.error(e.message); process.exitCode = 1; });
