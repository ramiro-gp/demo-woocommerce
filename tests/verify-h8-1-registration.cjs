/** Native registration/recovery regression; synthetic recipient, local Mailpit only. */
'use strict';
const fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
const { randomBytes } = require('node:crypto');
process.env.PLAYWRIGHT_BROWSERS_PATH ||= 'C:\\Users\\ramiro\\AppData\\Local\\ms-playwright';
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
assert.equal(root.toLowerCase(), 'c:\\codigo\\demo-woocommerce');
const account = 'http://localhost:8080/?page_id=10', mailpit = 'http://localhost:8025';
const email = 'h81.registration.' + randomBytes(8).toString('hex') + '@example.test';
const password = randomBytes(32).toString('hex');
const output = path.join(root, '.local/local/h8-1');
async function ownMail() {
  const list = await (await fetch(mailpit + '/api/v1/messages?limit=500')).json();
  return list.messages.filter(m => m.To.some(to => to.Address === email));
}
(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const context = await browser.newContext({ serviceWorkers: 'block' });
    await context.route(/^https?:\/\//, route => new URL(route.request().url()).origin === 'http://localhost:8080' ? route.continue() : route.abort());
    const page = await context.newPage(); await page.goto(account, { waitUntil: 'networkidle' });
    await page.locator('#reg_email').fill(email);
    if (await page.locator('#reg_password').count()) await page.locator('#reg_password').fill(password);
    await Promise.all([page.waitForNavigation(), page.locator('[name=register]').click()]);
    assert.equal(await page.locator('.woocommerce-error,.wc-block-components-notice-banner.is-error').count(), 0);
    assert.equal(await page.locator('.woocommerce-MyAccount-navigation').count(), 1, 'Native registration signs in the new customer.');
    const welcome = await ownMail(); assert.equal(welcome.length, 1, 'One native welcome email.');
    const recovery = await browser.newContext({ serviceWorkers: 'block' });
    await recovery.route(/^https?:\/\//, route => new URL(route.request().url()).origin === 'http://localhost:8080' ? route.continue() : route.abort());
    const p = await recovery.newPage(); await p.goto(account + '&lost-password', { waitUntil: 'networkidle' });
    await p.locator('#user_login').fill(email);
    await Promise.all([p.waitForNavigation(), p.locator('button[type=submit]').click()]);
    assert.equal(await p.locator('.woocommerce-error,.wc-block-components-notice-banner.is-error').count(), 0);
    const messages = await ownMail(); assert.equal(messages.length, 2, 'Welcome and recovery captured locally.');
    for (const message of messages) {
      const detail = await (await fetch(mailpit + '/api/v1/message/' + message.ID)).json();
      assert.ok(detail.HTML && detail.Text, 'Native account mail remains multipart.');
      assert.ok(!detail.HTML.includes(password) && !detail.Text.includes(password), 'No plaintext password.');
    }
    fs.writeFileSync(path.join(output, 'registration-private.json'), JSON.stringify({ email, login: account, note: 'Synthetic customer created through native registration. Recovery link was not followed.' }) + '\n');
    const report = { result: 'passed', registration: true, authenticated_account: true, recovery_request: true, native_multipart_emails: 2, password_reset_link_followed: false, real_mail: false };
    fs.writeFileSync(path.join(output, 'registration-report.json'), JSON.stringify(report, null, 2) + '\n');
    console.log(JSON.stringify(report));
  } finally { await browser.close(); }
})().catch(e => { console.log(JSON.stringify({ result: 'failed', error: String(e.message).split(email).join('[synthetic]').split(password).join('[private]') })); process.exitCode = 1; });
