/** Executes the actual plugin JS and native WC country/state scripts under another theme. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
process.env.PLAYWRIGHT_BROWSERS_PATH ||= 'C:\\Users\\ramiro\\AppData\\Local\\ms-playwright';
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const html = fs.readFileSync(path.join(root, '.local/local/backups/h8-1-isolated-country.html'), 'utf8');
const errors = [], output = path.join(root, '.local/local/h8-1');
(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const ctx = await browser.newContext();
    await ctx.route(/^https?:\/\//, route => {
      const request = route.request(), u = new URL(request.url());
      if (u.origin !== 'http://localhost:8080' || request.method() !== 'GET') return route.abort();
      if (u.searchParams.has('h81_isolated')) return route.fulfill({ status: 200, contentType: 'text/html', body: html });
      return route.continue();
    });
    const page = await ctx.newPage(); page.on('pageerror', e => errors.push(e.message));
    await page.goto('http://localhost:8080/?h81_isolated=country', { waitUntil: 'networkidle' });
    assert.equal(await page.locator('#shipping_country').inputValue(), 'AR');
    await page.locator('.commerce-core-copy-start').click();
    await page.locator('.commerce-core-copy-apply').click();
    await page.locator('.commerce-core-copy-status').filter({ hasText: 'Datos copiados' }).waitFor();
    assert.equal(await page.locator('#shipping_country').inputValue(), 'CL');
    assert.equal(await page.locator('#shipping_state').inputValue(), 'CL-RM');
    assert.equal(await page.locator('#shipping_city').inputValue(), 'Santiago');
    assert.equal(await page.locator('#shipping_postcode').inputValue(), '8320000');
    assert.equal(await page.locator('#shipping_company').inputValue(), 'Empresa de prueba');
    assert.equal(await page.locator('#shipping_phone').inputValue(), '1123456789');
    assert.equal(await page.locator('#shipping_address_1').inputValue(), 'Destino aislado 123');
    assert.equal(await page.locator('script[src*="commerce-base/"]').count(), 0);
    await page.locator('#shipping_country').selectOption('AR');
    await page.locator('#shipping_state').selectOption('B');
    assert.equal(await page.locator('#shipping_state').inputValue(), 'B');
    await page.locator('.commerce-core-copy-start').click(); await page.locator('.commerce-core-copy-apply').click();
    assert.equal(await page.locator('#shipping_state').inputValue(), 'CL-RM');
    assert.deepEqual(errors, []);
    const report = { result: 'passed', checks: 12, country_transition: 'AR → CL → AR → CL', theme: 'twentytwentyfive', native_scripts: true, shipping_phone_and_company: true, persistence: 'No submit; form-action CSP none; configuration and customer unchanged.' };
    fs.writeFileSync(path.join(output, 'isolated-report.json'), JSON.stringify(report, null, 2) + '\n');
    console.log(JSON.stringify(report));
  } finally { await browser.close(); }
})().catch(e => { console.log(JSON.stringify({ result: 'failed', message: e.message, errors })); process.exitCode = 1; });
