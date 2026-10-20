/** H8.1: real native account forms, owned synthetic customers, no payment POST. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
process.env.PLAYWRIGHT_BROWSERS_PATH ||= 'C:\\Users\\ramiro\\AppData\\Local\\ms-playwright';
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
assert.equal(root.toLowerCase(), 'c:\\codigo\\demo-woocommerce');
const output = path.join(root, '.local/local/h8-1');
fs.mkdirSync(output, { recursive: true });
const fixture = JSON.parse(fs.readFileSync(path.join(root, '.local/local/backups/h8-1-qa-fixtures.json'), 'utf8'));
const base = new URL(fixture.account).origin;
assert.equal(base, 'http://localhost:8080');
const widths = [320, 390, 768, 1366, 1920];
const report = { checks: [], errors: [], assetErrors: [], blockedTelemetryErrors: [], blockedExternalHosts: [], screenshots: [], matrix: [], copyRequests: [], paymentPosts: 0 };
const privateValues = Object.values(fixture.customers).flatMap(c => [c.username, c.password]);
const redact = value => privateValues.reduce((s, v) => s.split(v).join('[private]'), String(value));
const url = (endpoint, value = '') => { const u = new URL(fixture.account); if (endpoint) u.searchParams.set(endpoint, value); return u.href; };
function wp(args) {
  assert.ok(args.every(value => /^(plugin|activate|deactivate|commerce-core)$/.test(value)));
  const command = "& '" + path.join(root, 'scripts/dev.ps1').replaceAll("'", "''") + "' wp -WpArgs @(" + args.map(value => "'" + value + "'").join(',') + ')';
  const result = spawnSync('pwsh', ['-NoProfile', '-Command', command], { cwd: root, encoding: 'utf8', timeout: 120000, windowsHide: true });
  assert.equal(result.status, 0, 'Local WP-CLI operation must succeed; private output withheld.');
  return result.stdout;
}
async function check(name, task) {
  try { await task(); report.checks.push({ name, result: 'passed' }); }
  catch (e) { report.checks.push({ name, result: 'failed', error: redact(e.message) }); console.log('FAIL', name, redact(e.message).slice(0, 500)); throw e; }
}
async function go(page, destination) { await page.goto(destination, { waitUntil: 'networkidle' }); }
async function login(context, key) {
  const page = await context.newPage(); await go(page, fixture.account);
  await page.locator('#username').fill(fixture.customers[key].username);
  await page.locator('#password').fill(fixture.customers[key].password);
  await Promise.all([page.waitForNavigation(), page.locator('[name=login]').click()]);
  assert.equal(await page.locator('#username').count(), 0);
  return page;
}
async function save(page) {
  await Promise.all([page.waitForNavigation(), page.locator('[name=save_address]').click()]);
  assert.equal(await page.locator('.woocommerce-error,.wc-block-components-notice-banner.is-error').count(), 0, 'Native address validation passes.');
  assert.equal(await page.locator('.woocommerce-message,.wc-block-components-notice-banner.is-success').count(), 1);
}
async function context(browser, theme = 'light', extra = {}) {
  const ctx = await browser.newContext({ viewport: { width: 1366, height: 1050 }, colorScheme: theme, reducedMotion: 'reduce', serviceWorkers: 'block', ...extra });
  await ctx.addInitScript(mode => { if (location.origin === 'http://localhost:8080') localStorage.setItem('commerce-base-color-mode', mode); }, theme);
  await ctx.route(/^https?:\/\//, route => {
    const request = route.request(), u = new URL(request.url());
    if (u.origin !== base) {
      if (!report.blockedExternalHosts.includes(u.hostname)) report.blockedExternalHosts.push(u.hostname);
      return route.abort('blockedbyclient');
    }
    if (request.method() === 'POST' && /\/checkout(?:\/|$)|rest_route=.*checkout/.test(u.pathname + u.search)) {
      report.paymentPosts++; return route.abort('blockedbyclient');
    }
    return route.continue();
  });
  ctx.on('page', p => {
    p.on('pageerror', e => {
      if (e.message === 'Unable to load melidata script on page' && report.blockedExternalHosts.some(host => /mercadolibre|mercadopago/.test(host))) report.blockedTelemetryErrors.push(e.message);
      else report.errors.push(redact(e.message));
    });
    p.on('response', response => {
      const u = new URL(response.url());
      if (u.origin === base && response.status() >= 400 && /\.(js|css|woff2?|svg|webp|png)$/.test(u.pathname)) report.assetErrors.push({ path: u.pathname, status: response.status() });
    });
  });
  return ctx;
}
async function copy(page, expected, replacement = true) {
  const writes = [];
  const listener = request => writes.push(request.method());
  page.on('request', listener);
  await page.locator('.commerce-core-copy-start').click();
  if (replacement) {
    await page.locator('.commerce-core-copy-apply').waitFor({ state: 'visible' });
    await page.locator('.commerce-core-copy-apply').click();
  }
  await page.locator('.commerce-core-copy-status').filter({ hasText: 'Datos copiados' }).waitFor();
  assert.equal(await page.locator('#shipping_address_1').inputValue(), expected);
  await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
  page.off('request', listener);
  assert.deepEqual(writes, [], 'Copy itself does not send HTTP or persist anything.');
  report.copyRequests.push(writes.length);
}
async function matrix(page, theme) {
  for (const width of widths) {
    await page.setViewportSize({ width, height: 1050 });
    for (const expanded of [false, true]) {
      if (expanded) await page.locator('.commerce-core-copy-start').click();
      const m = await page.locator('.commerce-core-address-copy').evaluate(section => {
        const r = section.getBoundingClientRect();
        return { width: innerWidth, scroll: document.documentElement.scrollWidth, client: document.documentElement.clientWidth, x: r.x, right: r.right, buttons: [...section.querySelectorAll('button')].filter(b => b.getClientRects().length).map(b => ({ height: b.getBoundingClientRect().height, width: b.getBoundingClientRect().width, name: b.textContent.trim() })) };
      });
      assert.ok(m.scroll <= m.client + 1, `${theme}/${width}/${expanded}: no overflow`);
      for (const b of m.buttons) assert.ok(b.height >= 44 && b.width >= 44, 'Accessible action target.');
      report.matrix.push({ theme, width, expanded, ...m });
      if ([320, 390, 1366].includes(width) && expanded) {
        const name = `shipping-${theme}-${width}.png`;
        await page.screenshot({ path: path.join(output, name), fullPage: true }); report.screenshots.push(name);
      }
      if (expanded) await page.locator('.commerce-core-copy-cancel').click();
    }
  }
}
(async () => {
  const mailBefore = (await (await fetch('http://localhost:8025/api/v1/messages?limit=1')).json()).total;
  const browser = await chromium.launch({ headless: true });
  try {
    const guestContext = await context(browser), guest = await guestContext.newPage();
    await check('guest-login-registration-and-protection', async () => {
      await go(guest, url('edit-address', 'shipping'));
      assert.equal(await guest.locator('#username').count(), 1);
      assert.equal(await guest.locator('#reg_email').count(), 1);
      assert.equal(await guest.locator('.commerce-core-copy-source,.commerce-core-copy-start').count(), 0);
    });
    const memberContext = await context(browser), page = await login(memberContext, 'complete');
    await go(page, url('edit-address', 'shipping'));
    await check('explicit-copy-cancel-and-unsaved-reload', async () => {
      assert.equal(await page.locator('#shipping_address_1').inputValue(), fixture.customers.complete.shipping.address_1);
      await page.locator('.commerce-core-copy-start').click();
      assert.equal(await page.locator('#shipping_address_1').inputValue(), fixture.customers.complete.shipping.address_1);
      await page.keyboard.press('Escape');
      assert.equal(await page.locator('#commerce-core-copy-confirm').isHidden(), true);
      await copy(page, fixture.customers.complete.billing.address_1);
      await page.reload({ waitUntil: 'networkidle' });
      assert.equal(await page.locator('#shipping_address_1').inputValue(), fixture.customers.complete.shipping.address_1, 'Copy does not save.');
    });
    await check('native-save-edit-reload-and-mapped-fields', async () => {
      await copy(page, fixture.customers.complete.billing.address_1);
      for (const field of ['first_name', 'last_name', 'company', 'country', 'state', 'city', 'postcode', 'address_1', 'address_2']) {
        const element = page.locator('#shipping_' + field);
        if (await element.count()) assert.equal(await element.inputValue(), fixture.customers.complete.billing[field]);
      }
      const source = JSON.parse(await page.locator('.commerce-core-copy-source').textContent());
      assert.equal(source.email, undefined);
      if (await page.locator('#shipping_phone').count()) assert.equal(await page.locator('#shipping_phone').inputValue(), fixture.customers.complete.billing.phone);
      await page.locator('#shipping_address_2').fill('Revisado antes de guardar');
      await save(page); await go(page, url('edit-address', 'shipping'));
      assert.equal(await page.locator('#shipping_address_1').inputValue(), fixture.customers.complete.billing.address_1);
      assert.equal(await page.locator('#shipping_address_2').inputValue(), 'Revisado antes de guardar');
    });
    await check('native-required-validation', async () => {
      await page.locator('#shipping_address_1').fill('');
      await Promise.all([page.waitForNavigation(), page.locator('[name=save_address]').click()]);
      assert.ok(await page.locator('.woocommerce-error,.wc-block-components-notice-banner.is-error').count() > 0);
      await go(page, url('edit-address', 'shipping'));
      assert.equal(await page.locator('#shipping_address_1').inputValue(), fixture.customers.complete.billing.address_1);
    });
    await check('csrf-and-foreign-user-identifiers', async () => {
      const formData = await page.locator('form:has([name=save_address])').evaluate(form => Object.fromEntries(new FormData(form)));
      await memberContext.request.post(url('edit-address', 'shipping'), { form: { ...formData, 'woocommerce-edit-address-nonce': 'invalid', shipping_address_1: 'CSRF no autorizado' } });
      await go(page, url('edit-address', 'shipping'));
      assert.equal(await page.locator('#shipping_address_1').inputValue(), fixture.customers.complete.billing.address_1);
      await go(page, url('edit-address', 'shipping') + '&user_id=' + fixture.customers.different.id + '&customer_id=' + fixture.customers.different.id);
      assert.equal(JSON.parse(await page.locator('.commerce-core-copy-source').textContent()).address_1, fixture.customers.complete.billing.address_1);
      const ownNonce = await page.locator('[name=woocommerce-edit-address-nonce]').inputValue();
      await memberContext.request.post(url('edit-address', 'shipping'), { form: { ...formData, 'woocommerce-edit-address-nonce': ownNonce, user_id: String(fixture.customers.different.id), customer_id: String(fixture.customers.different.id), shipping_address_1: 'Sólo mi propio envío' } });
      await go(page, url('edit-address', 'shipping')); assert.equal(await page.locator('#shipping_address_1').inputValue(), 'Sólo mi propio envío');
      await copy(page, fixture.customers.complete.billing.address_1); await save(page);
    });
    await check('billing-edit-does-not-sync-shipping', async () => {
      await go(page, url('edit-address', 'billing'));
      assert.equal(await page.locator('.commerce-core-copy-start,.commerce-core-copy-source').count(), 0);
      await page.locator('#billing_address_1').fill('Facturación modificada 456'); await save(page);
      await go(page, url('edit-address', 'shipping'));
      assert.equal(await page.locator('#shipping_address_1').inputValue(), fixture.customers.complete.billing.address_1);
      assert.equal(JSON.parse(await page.locator('.commerce-core-copy-source').textContent()).address_1, 'Facturación modificada 456');
    });
    await check('native-province-copy-and-reload', async () => {
      await go(page, url('edit-address', 'billing'));
      assert.equal(await page.locator('#billing_country').inputValue(), 'AR');
      await page.locator('#billing_state').selectOption('B');
      await page.locator('#billing_city').fill('La Plata'); await page.locator('#billing_postcode').fill('1900');
      await save(page); await go(page, url('edit-address', 'shipping'));
      await copy(page, 'Facturación modificada 456');
      assert.equal(await page.locator('#shipping_country').inputValue(), 'AR');
      assert.equal(await page.locator('#shipping_state').inputValue(), 'B');
      await save(page); await go(page, url('edit-address', 'shipping'));
      assert.equal(await page.locator('#shipping_state').inputValue(), 'B');
      // Country-list changes are tested in an isolated native form; this store
      // deliberately allows only Argentina. Never broaden merchant settings.
      await go(page, url('edit-address', 'billing'));
      await page.locator('#billing_state').selectOption('C');
      await page.locator('#billing_city').fill('Buenos Aires'); await page.locator('#billing_postcode').fill('1001'); await save(page);
      await go(page, url('edit-address', 'shipping')); assert.equal(await page.locator('#shipping_state').inputValue(), 'B');
    });
    await check('lifecycle-preserves-native-addresses', async () => {
      wp(['plugin', 'deactivate', 'commerce-core']);
      try {
        await go(page, url('edit-address', 'shipping'));
        assert.equal(await page.locator('.commerce-core-address-copy').count(), 0);
        assert.equal(await page.locator('#shipping_state').inputValue(), 'B');
      } finally { wp(['plugin', 'activate', 'commerce-core']); }
      await go(page, url('edit-address', 'shipping'));
      assert.equal(await page.locator('.commerce-core-copy-start').isVisible(), true);
      assert.equal(await page.locator('#shipping_state').inputValue(), 'B');
    });
    await check('keyboard-focus-and-responsive-light', async () => {
      await page.locator('.commerce-core-copy-start').focus(); await page.keyboard.press('Tab'); await page.keyboard.press('Shift+Tab');
      assert.ok(await page.locator('.commerce-core-copy-start').evaluate(e => e.matches(':focus-visible') && parseFloat(getComputedStyle(e).outlineWidth) >= 2));
      await page.keyboard.press('Enter'); assert.ok(await page.locator('.commerce-core-copy-apply').evaluate(e => e === document.activeElement));
      await page.keyboard.press('Tab'); assert.ok(await page.locator('.commerce-core-copy-cancel').evaluate(e => e === document.activeElement));
      await page.keyboard.press('Space'); assert.ok(await page.locator('.commerce-core-copy-start').evaluate(e => e === document.activeElement));
      await matrix(page, 'light');
    });
    const darkContext = await context(browser, 'dark'), dark = await login(darkContext, 'complete');
    await go(dark, url('edit-address', 'shipping'));
    await check('responsive-dark', async () => matrix(dark, 'dark'));
    const blankContext = await context(browser), blank = await login(blankContext, 'blank');
    await check('missing-billing-explained', async () => {
      await go(blank, url('edit-address', 'shipping'));
      assert.equal(await blank.locator('.commerce-core-copy-start,.commerce-core-copy-source').count(), 0);
      assert.match(await blank.locator('.commerce-core-address-copy').innerText(), /primero completala/);
      await blank.getByRole('link', { name: 'Revisar mi dirección de facturación' }).click();
      await blank.locator('#billing_address_1').waitFor();
      assert.equal(await blank.locator('#billing_address_1').count(), 1, 'WooCommerce supplies the localized billing endpoint.');
    });
    const differentContext = await context(browser), different = await login(differentContext, 'different');
    await check('different-user-empty-shipping-and-isolation', async () => {
      await go(different, url('edit-address', 'shipping') + '&user_id=' + fixture.customers.complete.id);
      assert.equal(await different.locator('#shipping_address_1').inputValue(), '', 'Foreign user ID in POST did not change this customer.');
      assert.equal(JSON.parse(await different.locator('.commerce-core-copy-source').textContent()).address_1, fixture.customers.different.billing.address_1);
      await copy(different, fixture.customers.different.billing.address_1, false); await save(different);
      await go(different, url('edit-address', 'shipping')); assert.equal(await different.locator('#shipping_address_1').inputValue(), fixture.customers.different.billing.address_1);
    });
    await check('account-orders-and-account-details-regression', async () => {
      await go(page, url('orders')); assert.ok(await page.locator('.woocommerce-MyAccount-content').count());
      await go(page, url('edit-account')); assert.equal(await page.locator('#account_email').count(), 1);
      assert.equal(await page.locator('script[src*="commerce-core/assets/js"]').count(), 0);
    });
    await check('no-js-native-form-remains-usable', async () => {
      const nojs = await browser.newContext({ javaScriptEnabled: false, storageState: await memberContext.storageState() });
      const p = await nojs.newPage(); await p.goto(url('edit-address', 'shipping'));
      assert.equal(await p.locator('.commerce-core-copy-start').isVisible(), false);
      assert.match(await p.locator('noscript').allTextContents().then(a => a.join(' ')), /completar y guardar este formulario manualmente/);
      assert.equal(await p.locator('[name=save_address]').isVisible(), true); await nojs.close();
    });
    await check('guest-and-authenticated-cart-checkout-regression', async () => {
      for (const p of [guest, page]) {
        await go(p, fixture.product.url);
        await Promise.all([p.waitForNavigation(), p.locator('[name=add-to-cart]').click()]);
        await go(p, fixture.cart); assert.equal(await p.locator('.wp-block-woocommerce-cart').count(), 1);
        await go(p, fixture.checkout); assert.equal(await p.locator('.wp-block-woocommerce-checkout').count(), 1);
        assert.equal(await p.locator('.commerce-core-address-copy,script[src*="commerce-core/assets/js"]').count(), 0);
        const text = await p.locator('.wp-block-woocommerce-checkout').innerText();
        assert.match(text, /Transferencia bancaria/); assert.doesNotMatch(text, /Checkout Pro/);
      }
    });
    await check('logged-out-post-cannot-save-address', async () => {
      await guestContext.request.post(url('edit-address', 'shipping'), { form: { action: 'edit_address', shipping_country: 'AR', shipping_address_1: 'No autorizado', user_id: String(fixture.customers.complete.id) } });
      await go(guest, url('edit-address', 'shipping')); assert.equal(await guest.locator('#username').count(), 1);
      assert.equal(await guest.locator('.commerce-core-copy-source').count(), 0);
    });
    await check('no-browser-errors-payments-or-additional-emails', async () => {
      assert.deepEqual(report.errors, []); assert.deepEqual(report.assetErrors, []); assert.equal(report.paymentPosts, 0);
      assert.equal((await (await fetch('http://localhost:8025/api/v1/messages?limit=1')).json()).total, mailBefore);
    });
  } finally { await browser.close(); }
})().catch(e => { report.errors.push(redact(e.message)); process.exitCode = 1; }).finally(() => {
  report.result = report.errors.length || report.checks.some(c => c.result === 'failed') ? 'failed' : 'passed';
  fs.writeFileSync(path.join(output, 'browser-report.json'), JSON.stringify(report, null, 2) + '\n');
  console.log(JSON.stringify({ result: report.result, passed: report.checks.filter(c => c.result === 'passed').length, failed: report.checks.filter(c => c.result === 'failed').length, matrix: report.matrix.length, errors: report.errors }));
});
