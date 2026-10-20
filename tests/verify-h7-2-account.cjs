/** Focused post-H7.2 account QA. Uses an existing synthetic customer; creates no orders or emails. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
process.env.PLAYWRIGHT_BROWSERS_PATH ||= 'C:\\Users\\ramiro\\AppData\\Local\\ms-playwright';
const { chromium } = require('playwright');
const output = path.resolve(__dirname, '../.local/local/h7-2/post-review');
const credentials = JSON.parse(fs.readFileSync(path.resolve(__dirname, '../.local/local/h7-2/manual-review/customer-credentials.json'), 'utf8'));
const account = new URL(credentials.login_url);
const base = account.origin;
assert.equal(base, 'http://localhost:8080', 'Run only against the existing local store.');
const widths = [320, 375, 390, 430, 768, 1024, 1366, 1920, 2560, 3440];
const report = { checks: [], errors: [], screenshots: [], isolated: true, profile_values_changed: false, unchanged_native_save: process.env.H72_ACCOUNT_SAVE === '1' };
const url = (endpoint, value = '') => { const u = new URL(account); if (endpoint) u.searchParams.set(endpoint, value); return u.href; };
const redact = text => [credentials.password, credentials.email, credentials.username].reduce((s, secret) => secret ? s.split(secret).join('[private]') : s, String(text));
async function check(name, task) {
  try { report.checks.push({ name, result: 'passed', evidence: await task() }); }
  catch (e) { report.checks.push({ name, result: 'failed', error: redact(e.message) }); console.log('FAIL', name, redact(e.message).slice(0, 450)); }
}
async function go(page, destination) { await page.goto(destination, { waitUntil: 'domcontentloaded' }); await page.evaluate(() => document.fonts.ready); }
async function capture(page, name) { await page.screenshot({ path: path.join(output, name + '.png'), fullPage: true }); report.screenshots.push(name + '.png'); }
async function makeContext(browser, theme) {
  const context = await browser.newContext({ viewport: { width: 1366, height: 1000 }, serviceWorkers: 'block', colorScheme: theme, reducedMotion: 'reduce' });
  await context.addInitScript(mode => localStorage.setItem('commerce-base-color-mode', mode), theme);
  await context.route(/^https?:\/\//, route => {
    const request = route.request(), u = new URL(request.url());
    if (u.origin !== base) return route.abort('blockedbyclient');
    assert.ok(!/checkout|refund|payment/.test(u.pathname + u.search) || request.method() === 'GET', 'No payment or order mutation is permitted.');
    return route.continue();
  });
  const page = await context.newPage();
  page.on('pageerror', e => report.errors.push(redact(e.message)));
  return { context, page };
}
async function login(page) {
  await go(page, account.href);
  await page.locator('#username').fill(credentials.username);
  await page.locator('#password').fill(credentials.password);
  await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.locator('button[name="login"]').click()]);
  assert.equal(await page.locator('#username').count(), 0, 'Existing QA credentials still work.');
}
async function matrix(page, name, theme) {
  const measurements = [];
  for (const width of widths) {
    await page.setViewportSize({ width, height: 1000 });
    await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
    const m = await page.evaluate(() => {
      const main = document.querySelector('main'), r = main.getBoundingClientRect();
      const rect = selector => { const e = document.querySelector(selector); if (!e) return null; const b = e.getBoundingClientRect(); return { x: b.x, y: b.y, width: b.width, height: b.height, float: getComputedStyle(e).cssFloat }; };
      const canvas = document.body.getBoundingClientRect();
      return { width: innerWidth, client: canvas.width, scroll: document.documentElement.scrollWidth, centerError: Math.abs(r.x - canvas.x - (canvas.width - r.width) / 2), mainWidth: r.width, heading: getComputedStyle(main.querySelector('h1')).fontSize, account: rect('.cb-header-account'), login: rect('.u-column1'), register: rect('.u-column2'), navigation: rect('.woocommerce-MyAccount-navigation'), content: rect('.woocommerce-MyAccount-content'), links: [...document.querySelectorAll('.woocommerce-MyAccount-navigation a')].map(a => ({ height: a.getBoundingClientRect().height, width: a.getBoundingClientRect().width })) };
    });
    assert.ok(m.scroll <= m.client + 1, `${name}/${theme}/${width}: horizontal overflow ${m.scroll - m.client}px`);
    assert.ok(m.centerError <= 1, `${name}/${theme}/${width}: container must be centered ${JSON.stringify(m)}`);
    assert.ok(parseFloat(m.heading) <= 64, 'Account heading stays within the utility scale.');
    assert.ok(m.account.width >= 44 && m.account.height >= 44, 'Header account target at least 44px.');
    for (const link of m.links) assert.ok(link.height >= 44 && link.width >= 44, 'Visible account navigation targets.');
    if (name === 'login' && width < 640) assert.ok(m.register.y > m.login.y + m.login.height - 1, 'Authentication columns stack.');
    if (name === 'login' && width >= 640) assert.ok(Math.abs(m.register.width - m.login.width) < 1 && Math.abs(m.register.y - m.login.y) < 1, 'Balanced authentication columns.');
    if (m.navigation && width >= 896) assert.ok(m.content.x > m.navigation.x + m.navigation.width, 'Sidebar and content occupy distinct columns.');
    if (name === 'orders') for (const number of await page.locator('.woocommerce-orders-table__cell-order-number a').all()) assert.ok(await number.isVisible(), 'Order identifiers remain visible in the responsive table.');
    measurements.push(m);
    if ([390, 1920].includes(width) || (name === 'login' && [320, 3440].includes(width))) await capture(page, `${name}-${theme}-${width}`);
  }
  return measurements;
}
function contrast(a, b) {
  const lum = hex => { const rgb = hex.trim().replace('#', '').match(/../g).map(v => parseInt(v, 16) / 255).map(v => v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4); return .2126 * rgb[0] + .7152 * rgb[1] + .0722 * rgb[2]; };
  const x = lum(a), y = lum(b); return (Math.max(x, y) + .05) / (Math.min(x, y) + .05);
}
(async () => {
  fs.mkdirSync(output, { recursive: true });
  const mailBefore = await (await fetch('http://localhost:8025/api/v1/messages?limit=1')).json();
  const browser = await chromium.launch({ headless: true });
  try {
    for (const theme of ['light', 'dark']) {
      const guest = await makeContext(browser, theme), page = guest.page;
      await check(`guest/header-login-register/${theme}`, async () => {
        await go(page, base + '/');
        const icon = page.getByRole('link', { name: 'Mi cuenta', exact: true });
        assert.equal(await icon.getAttribute('href'), account.href);
        await icon.click(); await page.locator('#username').waitFor();
        assert.equal(await page.locator('#reg_email').count(), 1);
        assert.equal((await page.locator('main h1').innerText()).trim(), 'Mi cuenta');
        assert.match(await page.locator('.woocommerce-privacy-policy-text').innerText(), /^Usamos tus datos personales/);
        assert.equal(await page.locator('.woocommerce-privacy-policy-text a.woocommerce-privacy-policy-link').count(), 0, 'The current draft policy must not produce a broken public link; publication remains in H8.');
        const actions = await page.locator('.cb-header-actions > *').evaluateAll(elements => elements.filter(e => e.matches('.cb-header-search,.cb-header-account,.cb-header-cart,.cb-menu-trigger')).map(e => e.className));
        assert.match(actions.join('|'), /cb-header-search.*cb-header-account.*cb-header-cart.*cb-menu-trigger/);
        return matrix(page, 'login', theme);
      });
      await check(`guest/keyboard-password-validation/${theme}`, async () => {
        await page.setViewportSize({ width: 390, height: 1000 });
        await page.locator('#password').fill('temporary-visible-test');
        const toggle = page.locator('.show-password-input'); await toggle.focus(); await page.keyboard.press('Space');
        assert.equal(await page.locator('#password').getAttribute('type'), 'text');
        await toggle.focus(); await page.keyboard.press('Space'); await page.waitForFunction(() => document.querySelector('#password').type === 'password'); await page.locator('#password').fill('');
        const icon = page.locator('.cb-header-account'); await icon.focus();
        assert.ok(await icon.evaluate(e => e.matches(':focus-visible') && parseFloat(getComputedStyle(e).outlineWidth) >= 2));
        await page.locator('[name=login]').click();
        assert.ok(await page.locator('#username').evaluate(e => !e.validity.valid) || await page.locator('.woocommerce-error').count() > 0, 'Native required-field validation is retained.');
      });
      await check(`guest/recovery-and-protected-titles/${theme}`, async () => {
        await go(page, url('lost-password')); assert.equal(await page.locator('form.lost_reset_password #user_login').count(), 1);
        assert.doesNotMatch(await page.locator('main h1').innerText(), /Mi cuenta/);
        await capture(page, `recovery-${theme}-390`);
        for (const endpoint of ['downloads', 'orders', 'edit-address', 'edit-account', 'view-order']) {
          await go(page, url(endpoint, endpoint === 'view-order' ? '454' : ''));
          assert.equal((await page.locator('main h1').innerText()).trim(), 'Mi cuenta');
          assert.equal(await page.locator('#username').count(), 1);
          assert.equal(await page.locator('.woocommerce-order-details').count(), 0);
        }
      });
      await guest.context.close();
      const member = await makeContext(browser, theme), p = member.page;
      await login(p);
      await check(`member/header-dashboard/${theme}`, async () => { await p.locator('.cb-header-account').click(); await p.locator('.woocommerce-MyAccount-navigation').waitFor(); assert.equal(await p.locator('.woocommerce-MyAccount-navigation-link--dashboard.is-active').count(), 1); assert.equal(await p.locator('.woocommerce-MyAccount-navigation-link--downloads').count(), 0); return matrix(p, 'dashboard', theme); });
      await check(`member/owned-orders/${theme}`, async () => {
        await go(p, url('orders'));
        const ids = (await p.locator('.woocommerce-orders-table__cell-order-number a').allTextContents()).map(s => Number(s.replace(/\D/g, ''))).sort((a, b) => a - b);
        assert.deepEqual(ids, [425, 454, 455, 456]);
        for (const [id, status] of [[454, 'En espera'], [455, 'Procesando'], [456, 'Completado']]) assert.ok((await p.locator('.woocommerce-orders-table__row').filter({ hasText: '#' + id }).innerText()).includes(status));
        return matrix(p, 'orders', theme);
      });
      await check(`member/order-details-permissions/${theme}`, async () => {
        await go(p, url('view-order', '454')); assert.equal(await p.locator('.woocommerce-order-details').count(), 1);
        const detail = await p.locator('main').innerText(); assert.match(detail, /Natural/); assert.match(detail, /55.500/); assert.match(detail, /En espera/);
        const evidence = await matrix(p, 'order', theme);
        await go(p, url('view-order', '424')); assert.match(await p.locator('main').innerText(), /pedido no v[aá]lido|no tienes permiso|no tenés permiso/i); assert.equal(await p.locator('.woocommerce-order-details,.woocommerce-customer-details').count(), 0);
        return evidence;
      });
      for (const [name, endpoint, value] of [['addresses', 'edit-address', ''], ['billing', 'edit-address', 'billing'], ['shipping', 'edit-address', 'shipping'], ['account', 'edit-account', '']]) {
        await check(`member/${name}/${theme}`, async () => {
          await go(p, url(endpoint, value));
          if (name === 'addresses') {
            assert.equal(await p.locator('.woocommerce-Address-title a.edit').count(), 2);
            for (const a of await p.locator('.woocommerce-Address-title a.edit').all()) assert.equal(await a.evaluate(e => getComputedStyle(e).cssFloat), 'none');
          } else assert.ok(await p.locator('form :is(input,select)').count() > 0);
          return matrix(p, name, theme);
        });
      }
      await check(`member/download-endpoint-preserved/${theme}`, async () => { await go(p, url('downloads')); assert.equal(await p.locator('#username').count(), 0); assert.match(await p.locator('main h1').innerText(), /Descargas/); assert.match(await p.locator('.woocommerce-MyAccount-content').innerText(), /descargas/i); });
      await check(`palette/contrast/${theme}`, async () => {
        const colors = await p.evaluate(() => Object.fromEntries(['background', 'surface', 'text', 'text-muted', 'border', 'accent', 'accent-contrast', 'focus'].map(n => [n, getComputedStyle(document.documentElement).getPropertyValue('--cb-' + n).trim()])));
        const ratios = { text: contrast(colors.text, colors.background), muted: contrast(colors['text-muted'], colors.background), input: contrast(colors.text, colors.surface), button: contrast(colors['accent-contrast'], colors.accent), focus: contrast(colors.focus, colors.background), border: contrast(colors.border, colors.background) };
        for (const key of ['text', 'muted', 'input', 'button']) assert.ok(ratios[key] >= 4.5, key + ' meets AA');
        for (const key of ['focus', 'border']) assert.ok(ratios[key] >= 3, key + ' is distinguishable');
        return { colors, ratios };
      });
      // Submit unchanged values only, once per form; never alter a password/email
      // or create a new customer/order. Preservation is checked separately in PHP.
      if (theme === 'light' && process.env.H72_ACCOUNT_SAVE === '1') {
        await check('member/native-save-unchanged', async () => {
          await p.setViewportSize({ width: 1366, height: 1000 });
          for (const [endpoint, value, button] of [['edit-account', '', 'save_account_details'], ['edit-address', 'billing', 'save_address'], ['edit-address', 'shipping', 'save_address']]) {
            await go(p, url(endpoint, value));
            await Promise.all([p.waitForNavigation({ waitUntil: 'domcontentloaded' }), p.locator(`button[name="${button}"]`).click()]);
            assert.equal(await p.locator('.woocommerce-error,.wc-block-components-notice-banner.is-error').count(), 0, endpoint + '/' + value + ': unchanged native form saves successfully.');
            assert.equal(await p.locator('.woocommerce-message,.wc-block-components-notice-banner.is-success').count(), 1, endpoint + '/' + value + ': native confirmation is visible.');
          }
        });
      }
      await member.context.close();
    }
  } finally { await browser.close(); }
  const mailAfter = await (await fetch('http://localhost:8025/api/v1/messages?limit=1')).json();
  await check('preservation/no-new-emails', async () => { assert.equal(mailAfter.total, mailBefore.total); return { before: mailBefore.total, after: mailAfter.total }; });
  assert.equal(report.errors.length, 0, 'No browser script errors.');
})().catch(e => { report.errors.push(redact(e.message)); process.exitCode = 1; }).finally(() => {
  report.result = report.errors.length || report.checks.some(c => c.result === 'failed') ? 'failed' : 'passed';
  fs.writeFileSync(path.join(output, 'qa-account.json'), JSON.stringify(report, null, 2) + '\n');
  console.log(JSON.stringify({ result: report.result, passed: report.checks.filter(c => c.result === 'passed').length, failed: report.checks.filter(c => c.result === 'failed').length, errors: report.errors, captures: report.screenshots.length }));
  if (report.result === 'failed') process.exitCode = 1;
});
