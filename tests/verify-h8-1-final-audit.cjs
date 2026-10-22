/** Five focused H8.1 acceptance scenarios; only the marked QA customer's addresses change. */
'use strict';
const fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
process.env.PLAYWRIGHT_BROWSERS_PATH ||= 'C:\\Users\\ramiro\\AppData\\Local\\ms-playwright';
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
assert.equal(root.toLowerCase(), 'c:\\codigo\\demo-woocommerce');
const folder = path.join(root, '.local/local/h8-1-final-20261021');
fs.mkdirSync(folder, { recursive: true });
const fixture = JSON.parse(fs.readFileSync(path.join(root, '.local/local/backups/h8-1-qa-fixtures.json'), 'utf8'));
const customer = fixture.customers.complete;
assert.ok(customer.username.startsWith('h81_complete_'));
const base = new URL(fixture.account).origin;
assert.equal(base, 'http://localhost:8080');
const addressURL = type => fixture.account + '&edit-address=' + type;
const report = { scenarios: [], errors: [], assetErrors: [], matrix: [], evidence: [], copy_network_requests: [], address_save_posts: 0, forbidden_requests: [], original_customer_used: false };
const sanitize = value => [customer.username, customer.password].reduce((s, secret) => s.split(secret).join('[private]'), String(value));
async function check(number, name, task) {
  try { const evidence = await task(); report.scenarios.push({ number, name, result: 'PASS', evidence }); }
  catch (e) { report.scenarios.push({ number, name, result: 'FAIL', error: sanitize(e.message) }); throw e; }
}
async function go(page, url) { await page.goto(url, { waitUntil: 'networkidle' }); }
async function login(ctx) {
  const page = await ctx.newPage(); await go(page, fixture.account);
  await page.locator('#username').fill(customer.username); await page.locator('#password').fill(customer.password);
  await Promise.all([page.waitForNavigation(), page.locator('[name=login]').click()]);
  await go(page, addressURL('shipping'));
  await page.locator('.commerce-core-copy-start').waitFor({ state: 'visible' }); return page;
}
async function context(browser, mode) {
  const ctx = await browser.newContext({ viewport: { width: 1366, height: 1050 }, colorScheme: mode, reducedMotion: 'reduce', serviceWorkers: 'block' });
  await ctx.addInitScript(value => { if (location.origin === 'http://localhost:8080') localStorage.setItem('commerce-base-color-mode', value); }, mode);
  await ctx.route(/^https?:\/\//, route => {
    const request = route.request(), u = new URL(request.url());
    if (u.origin !== base) return route.abort('blockedbyclient');
    if (request.method() !== 'GET') {
      const allowed = request.method() === 'POST' && u.searchParams.get('page_id') === '10' && (!u.searchParams.has('edit-address') || ['shipping', 'billing'].includes(u.searchParams.get('edit-address')));
      if (!allowed) { report.forbidden_requests.push({ method: request.method(), path: u.pathname }); return route.abort('blockedbyclient'); }
      if (u.searchParams.has('edit-address')) report.address_save_posts++;
    }
    return route.continue();
  });
  ctx.on('page', p => {
    p.on('pageerror', e => report.errors.push(sanitize(e.message)));
    p.on('response', r => { const u = new URL(r.url()); if (u.origin === base && r.status() >= 400 && /\.(js|css|woff2?|svg|png|webp)$/.test(u.pathname)) report.assetErrors.push({ path: u.pathname, status: r.status() }); });
  });
  return ctx;
}
async function fields(page) {
  return page.locator('form:has([name=save_address])').evaluate(form => Object.fromEntries([...form.elements].filter(e => e.name && e.name.startsWith('shipping_')).map(e => [e.name.substring(9), e.value])));
}
async function source(page) { return JSON.parse(await page.locator('.commerce-core-copy-source').textContent()); }
async function mapped(page, expected) {
  const actual = await fields(page);
  assert.ok(!('email' in expected));
  for (const [key, value] of Object.entries(expected)) if (key in actual) assert.equal(actual[key], value, 'Compatible field: ' + key);
  return Object.keys(expected).filter(key => key in actual);
}
async function copy(page) {
  const requests = [], listener = request => requests.push(request.method()); page.on('request', listener);
  await page.locator('.commerce-core-copy-start').click();
  if (await page.locator('#commerce-core-copy-confirm').isVisible()) await page.locator('.commerce-core-copy-apply').click();
  await page.locator('.commerce-core-copy-status').filter({ hasText: 'Datos copiados' }).waitFor();
  await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
  page.off('request', listener); report.copy_network_requests.push(requests.length);
  assert.deepEqual(requests, [], 'Copy sends no HTTP.');
}
async function save(page) {
  await Promise.all([page.waitForNavigation(), page.locator('[name=save_address]').click()]);
  assert.equal(await page.locator('.woocommerce-error,.wc-block-components-notice-banner.is-error').count(), 0);
  assert.equal(await page.locator('.woocommerce-message,.wc-block-components-notice-banner.is-success').count(), 1);
}
async function capture(page, name) {
  await page.screenshot({ path: path.join(folder, name + '.png'), fullPage: true }); report.evidence.push(name + '.png');
}
(async () => {
  const mailBefore = (await (await fetch('http://localhost:8025/api/v1/messages?limit=1')).json()).total;
  const browser = await chromium.launch({ headless: true });
  try {
    const ctx = await context(browser, 'light'), page = await login(ctx);
    let expected = await source(page), original = await fields(page);
    assert.notEqual(original.address_1, expected.address_1, 'Own QA fixture has a different existing shipping address.');
    await check(1, 'Copiar todos los campos compatibles', async () => {
      await copy(page); const copied = await mapped(page, expected); await capture(page, '01-copied-desktop-light');
      return { copied_fields: copied, email_excluded: true, copy_http: 0 };
    });
    await check(2, 'Persistencia únicamente después de guardar', async () => {
      await page.reload({ waitUntil: 'networkidle' }); assert.deepEqual(await fields(page), original);
      await capture(page, '02-reload-without-save'); await copy(page); await save(page);
      await go(page, addressURL('shipping')); await page.reload({ waitUntil: 'networkidle' }); await mapped(page, expected);
      await capture(page, '02-reload-after-save');
      return { unsaved_reload_preserves_original: true, native_save_then_reload_matches_billing: true };
    });
    await check(3, 'Confirmar reemplazo y cancelar sin cambios', async () => {
      await page.locator('#shipping_address_1').fill('Envío QA diferente 987'); await save(page); await go(page, addressURL('shipping'));
      const existing = await fields(page); await page.locator('.commerce-core-copy-start').click();
      assert.equal(await page.locator('#commerce-core-copy-confirm').isVisible(), true);
      assert.deepEqual(await fields(page), existing); await capture(page, '03-replacement-confirmation');
      await page.locator('.commerce-core-copy-cancel').click(); assert.deepEqual(await fields(page), existing);
      await page.reload({ waitUntil: 'networkidle' }); assert.deepEqual(await fields(page), existing);
      await copy(page); await mapped(page, expected); await save(page); await go(page, addressURL('shipping')); await mapped(page, expected);
      return { existing_address_changed_only_in_owned_qa_user: true, confirmation_required: true, cancel_preserves_all_fields: true, confirmed_copy_saved: true };
    });
    await check(4, 'Sin sincronización automática', async () => {
      const shippingBefore = await fields(page); await go(page, addressURL('billing'));
      assert.equal(await page.locator('.commerce-core-address-copy').count(), 0);
      await page.locator('#billing_address_1').fill('Facturación QA posterior 654'); await save(page);
      await go(page, addressURL('shipping')); await page.reload({ waitUntil: 'networkidle' }); assert.deepEqual(await fields(page), shippingBefore);
      assert.equal((await source(page)).address_1, 'Facturación QA posterior 654'); await capture(page, '04-shipping-unchanged-after-billing');
      return { billing_change_saved: true, saved_shipping_all_fields_unchanged: true, next_copy_source_reflects_new_billing: true };
    });
    await check(5, 'Desktop/mobile, claro/oscuro y teclado', async () => {
      for (const mode of ['light', 'dark']) {
        const visualContext = await context(browser, mode), p = await login(visualContext);
        for (const width of [320, 390, 1366]) {
          await p.setViewportSize({ width, height: 1050 });
          for (const expanded of [false, true]) {
            if (expanded) await p.locator('.commerce-core-copy-start').click();
            const m = await p.locator('.commerce-core-address-copy').evaluate(section => {
              const form = section.closest('form'), first = form.querySelector('.woocommerce-address-fields__field-wrapper');
              const r = section.getBoundingClientRect(), f = first.getBoundingClientRect();
              return { scroll: document.documentElement.scrollWidth, client: document.documentElement.clientWidth, left_alignment_error: Math.abs(r.x - f.x), right_alignment_error: Math.abs(r.right - f.right), targets: [...section.querySelectorAll('button')].filter(e => e.getClientRects().length).map(e => ({ width: e.getBoundingClientRect().width, height: e.getBoundingClientRect().height })) };
            });
            assert.ok(m.scroll <= m.client + 1, 'No overflow.'); assert.ok(m.left_alignment_error <= 1 && m.right_alignment_error <= 1, 'Control aligns with the native form.');
            for (const target of m.targets) assert.ok(target.width >= 44 && target.height >= 44);
            report.matrix.push({ mode, width, expanded, ...m });
            if (expanded && [390, 1366].includes(width)) await capture(p, '05-' + mode + '-' + width);
            if (expanded) await p.locator('.commerce-core-copy-cancel').click();
          }
        }
        await p.locator('.commerce-core-copy-start').focus(); await p.keyboard.press('Tab'); await p.keyboard.press('Shift+Tab');
        assert.ok(await p.locator('.commerce-core-copy-start').evaluate(e => e === document.activeElement && e.matches(':focus-visible') && parseFloat(getComputedStyle(e).outlineWidth) >= 2));
        await p.keyboard.press('Enter'); assert.ok(await p.locator('.commerce-core-copy-apply').evaluate(e => e === document.activeElement));
        await p.keyboard.press('Tab'); assert.ok(await p.locator('.commerce-core-copy-cancel').evaluate(e => e === document.activeElement));
        await p.keyboard.press('Space'); assert.equal(await p.locator('#commerce-core-copy-confirm').isHidden(), true);
        await p.keyboard.press('Enter'); await p.keyboard.press('Escape'); assert.ok(await p.locator('.commerce-core-copy-start').evaluate(e => e === document.activeElement));
        await visualContext.close();
      }
      assert.deepEqual(report.errors, []); assert.deepEqual(report.assetErrors, []); assert.deepEqual(report.forbidden_requests, []);
      return { combinations: report.matrix.length, full_keyboard: true, focus_visible: true, js_errors: 0, asset_errors: 0, alignment_and_overflow: 'PASS' };
    });
    assert.equal((await (await fetch('http://localhost:8025/api/v1/messages?limit=1')).json()).total, mailBefore);
    report.mailpit_unchanged = true;
  } finally { await browser.close(); }
})().catch(e => { report.errors.push(sanitize(e.message)); process.exitCode = 1; }).finally(() => {
  report.result = report.errors.length || report.scenarios.some(s => s.result === 'FAIL') ? 'FAIL' : 'PASS';
  fs.writeFileSync(path.join(folder, 'five-scenarios-report.json'), JSON.stringify(report, null, 2) + '\n');
  console.log(JSON.stringify({ result: report.result, scenarios: report.scenarios.map(({ number, name, result }) => ({ number, name, result })), responsive_combinations: report.matrix.length, errors: report.errors }));
});
