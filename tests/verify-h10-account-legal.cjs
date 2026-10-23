/** Native account/address and legal-flow QA, synthetic users in isolated ORIGEN only. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { randomBytes } = require('node:crypto');
process.env.PLAYWRIGHT_BROWSERS_PATH ||= path.join(process.env.LOCALAPPDATA || 'C:\\Users\\ramiro\\AppData\\Local', 'ms-playwright');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const privateDir = path.join(root, '.local/origen-casa/backups');
const fixture = JSON.parse(fs.readFileSync(path.join(privateDir, 'h10-private.json'), 'utf8'));
const checkout = JSON.parse(fs.readFileSync(path.join(privateDir, 'h10-checkout.json'), 'utf8'));
const base = 'http://localhost:8082';
const out = path.join(root, 'docs/evidence/h10');
fs.mkdirSync(out, { recursive: true });
const resumeManager = process.env.H10_ACCOUNT_RESUME === 'manager';
const report = resumeManager ? JSON.parse(fs.readFileSync(path.join(out, 'account-legal.json'), 'utf8')) : { result: 'running', checks: [], errors: [], synthetic: true, realMail: false, requests: [] };
if (resumeManager) {
  assert.ok(report.error === 'Order association requires verification' && report.registration?.native && report.account?.nativeAddressSaved && report.withdrawal?.code, 'Manager resume only after the known late assertion wording mismatch and all earlier flows passed');
  fs.copyFileSync(path.join(out, 'account-legal.json'), path.join(privateDir, 'h10-account-before-manager-resume.json'));
  report.resumedAfter = 'Native registration, recovery, guest receipt, customer order/address and permission checks already passed. Resumed only manager checks after correcting the QA denial-message matcher to match current H9 hardening.';
  delete report.error;
  report.result = 'running';
}
const run = randomBytes(5).toString('hex');
const users = fixture.users;
assert.ok(users?.customer?.username && users?.manager?.username, 'Prepared synthetic customer/manager required');
const secrets = Object.values(users).flatMap(u => [u.username, u.password, u.email]).filter(Boolean);
const registrationEmail = 'h10.register.' + run + '@example.test';
const registrationPassword = randomBytes(24).toString('hex');
secrets.push(registrationEmail, registrationPassword);
const redact = s => secrets.reduce((v, secret) => v.split(secret).join('[private]'), String(s));
const check = (name, pass) => { assert.ok(pass, name); report.checks.push(name); };
const idOf = value => Number(typeof value === 'object' ? value?.id || value?.ID : value);
const orderId = Number(checkout.order_id || checkout.order?.id || checkout.id);
assert.ok(orderId > 0, 'Synthetic BACS order from checkout required');
let browser;
async function go(p, url) { const r = await p.goto(url, { waitUntil: 'networkidle' }); assert.ok(r.status() < 400, 'Local navigation'); return r; }
async function context(js = true) {
  const c = await browser.newContext({ viewport: { width: 1366, height: 1000 }, javaScriptEnabled: js, serviceWorkers: 'block', reducedMotion: 'reduce' });
  await c.route(/^https?:\/\//, r => new URL(r.request().url()).origin === base ? r.continue() : r.abort());
  c.on('page', p => p.on('pageerror', e => report.errors.push(redact(e.message))));
  return c;
}
async function login(c, u, account) {
  const p = await c.newPage();
  await go(p, account || base + '/wp-login.php');
  await p.locator(account ? '#username' : '#user_login').fill(u.username);
  await p.locator(account ? '#password' : '#user_pass').fill(u.password);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle' }), p.locator(account ? '[name="login"]' : '#wp-submit').click()]);
  check(account ? 'Synthetic customer login uses native My Account' : 'Synthetic manager login uses native WordPress', await p.locator(account ? '.woocommerce-MyAccount-navigation' : '#wpadminbar').count() > 0);
  return p;
}
async function pages(c) {
  const r = await c.request.get(base + '/?rest_route=/wp/v2/pages&per_page=100');
  assert.ok(r.ok());
  const all = await r.json();
  const find = (keys, fallback) => all.find(p => keys.includes(p.slug)) || all.find(p => fallback.test(p.content?.rendered || ''));
  const found = {
    account: find(['mi-cuenta', 'my-account'], /woocommerce_my_account/),
    withdrawal: all.find(p => p.id === idOf(fixture.pages?.withdrawal)) || find(['boton-de-arrepentimiento', 'arrepentimiento'], /cc-withdrawal-form/)
  };
  for (const p of Object.values(found)) assert.ok(p?.link && new URL(p.link).origin === base, 'Independent page URL');
  return found;
}
function save() { fs.writeFileSync(path.join(out, 'account-legal.json'), JSON.stringify(report, null, 2) + '\n'); }
async function registration(urls) {
  async function ownMail() {
    const r = await fetch('http://localhost:8027/api/v1/messages?limit=500');
    assert.ok(r.ok, 'Isolated local Mailpit available');
    return (await r.json()).messages.filter(m => m.To?.some(to => to.Address === registrationEmail));
  }
  const c = await context();
  const p = await c.newPage();
  await go(p, urls.account.link);
  check('Native public registration is available', await p.locator('#reg_email').count() === 1);
  await p.locator('#reg_email').fill(registrationEmail);
  if (await p.locator('#reg_password').count()) await p.locator('#reg_password').fill(registrationPassword);
  if (await p.locator('#reg_username').count()) {
    const login = 'h10_register_' + run;
    secrets.push(login);
    await p.locator('#reg_username').fill(login);
  }
  for (const box of await p.locator('.woocommerce-form-register input[type="checkbox"][required]').all()) await box.check();
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle' }), p.locator('[name="register"]').click()]);
  check('Native public registration has no validation errors', await p.locator('.woocommerce-error,.wc-block-components-notice-banner.is-error').count() === 0);
  check('Native public registration signs in new isolated customer', await p.locator('.woocommerce-MyAccount-navigation').count() === 1);
  let messages = await ownMail();
  check('Native welcome email captured in isolated Mailpit', messages.length === 1);
  await c.close();
  const recovery = await context();
  const rp = await recovery.newPage();
  await go(rp, urls.account.link);
  const recoveryLink = await rp.locator('a[href*="lost-password"]').first().getAttribute('href');
  await go(rp, recoveryLink);
  await rp.locator('#user_login').fill(registrationEmail);
  await Promise.all([rp.waitForNavigation({ waitUntil: 'networkidle' }), rp.locator('button[type="submit"]').click()]);
  check('Native password recovery accepts synthetic customer', await rp.locator('.woocommerce-error,.wc-block-components-notice-banner.is-error').count() === 0);
  messages = await ownMail();
  check('Native recovery email captured only in Mailpit', messages.length === 2);
  for (const message of messages) {
    const detail = await (await fetch('http://localhost:8027/api/v1/message/' + message.ID)).json();
    check('Account mail uses native multipart content', Boolean(detail.HTML && detail.Text));
    check('Account mail contains this store identity and links', detail.HTML.includes('ORIGEN CASA') && detail.HTML.includes(base) && !/\bTRAMA\b/.test(detail.HTML));
    check('Account mail does not disclose supplied plaintext password', !detail.HTML.includes(registrationPassword) && !detail.Text.includes(registrationPassword));
  }
  fs.writeFileSync(path.join(privateDir, 'h10-registration-private.json'), JSON.stringify({ email: registrationEmail, nativeRegistration: true, recoveryRequested: true, resetLinkFollowed: false }, null, 2) + '\n');
  report.registration = { native: true, authenticated: true, recoveryRequested: true, localMultipartEmails: messages.length, resetLinkFollowed: false, realMail: false };
  await recovery.close();
}
(async () => {
  browser = await chromium.launch({ headless: true });
  try {
    const guest = await context(false);
    const urls = await pages(guest);
    let code = report.withdrawal?.code;
    if (!resumeManager) {
    await registration(urls);
    const consumer = await guest.newPage();
    await go(consumer, urls.withdrawal.link);
    check('Withdrawal available to guest without JavaScript', await consumer.locator('.cc-withdrawal-form').isVisible());
    check('Withdrawal does not require order reference', !await consumer.locator('#cc-reference').getAttribute('required'));
    check('Withdrawal does not require reason', !await consumer.locator('[name="cc_message"]').getAttribute('required'));
    await consumer.locator('#cc-name').fill('Persona DEMO H10');
    await consumer.locator('#cc-email').fill('h10.withdrawal.' + run + '@example.test');
    await Promise.all([consumer.waitForNavigation({ waitUntil: 'networkidle' }), consumer.locator('.cc-withdrawal-form button[type="submit"]').click()]);
    code = (await consumer.locator('.cc-receipt-code').innerText()).trim();
    check('Guest receives persisted withdrawal receipt code', /^ARR-\d{8}-[A-F0-9]{20}$/.test(code));
    check('Receipt distinguishes request from cancellation/refund', /No significa que esté aprobada/.test(await consumer.locator('.cc-receipt').innerText()));
    await consumer.locator('.cc-receipt').screenshot({ path: path.join(out, 'origen-withdrawal-receipt.png') });
    report.withdrawal = { code, submittedWithoutAccount: true, submittedWithoutOrderReference: true, submittedWithoutReason: true, javaScript: false };
    await guest.close();

    const customerContext = await context();
    const customer = await login(customerContext, users.customer, urls.account.link);
    const ordersLink = await customer.locator('.woocommerce-MyAccount-navigation-link--orders a').getAttribute('href');
    await go(customer, ordersLink);
    check('Native orders list contains synthetic BACS order', await customer.locator('.woocommerce-orders-table__cell-order-number a').filter({ hasText: String(orderId) }).count() === 1);
    const ownOrderLink = customer.locator('.woocommerce-orders-table__cell-order-number a').filter({ hasText: String(orderId) });
    await ownOrderLink.click();
    await customer.waitForLoadState('networkidle');
    check('Native account can view its own order', await customer.locator('.woocommerce-order-details').count() === 1);
    check('Own order uses ORIGEN products', (await customer.locator('.woocommerce-order-details').innerText()).includes('DEMO') || !/Remera|Bolso Tránsito|Pantalón/.test(await customer.locator('.woocommerce-order-details').innerText()));
    check('Account order shows bank transfer', /Transferencia bancaria/.test(await customer.locator('.woocommerce-order-details').innerText()));
    await customer.locator('.woocommerce-order-details').screenshot({ path: path.join(out, 'origen-account-own-order.png') });
    const addressesLink = await customer.locator('.woocommerce-MyAccount-navigation-link--edit-address a').getAttribute('href');
    await go(customer, addressesLink);
    const billingLink = await customer.locator('.woocommerce-Address').first().locator('a').first().getAttribute('href');
    await go(customer, billingLink);
    const address = {
      billing_first_name: 'Persona DEMO', billing_last_name: 'H10', billing_company: 'Ensayo ficticio',
      billing_address_1: 'Calle DEMO 1234', billing_address_2: 'Prueba local', billing_city: 'Ciudad Autónoma de Buenos Aires',
      billing_postcode: 'C1000', billing_phone: '1100000000', billing_email: users.customer.email
    };
    for (const [name, value] of Object.entries(address)) if (await customer.locator('[name="' + name + '"]').count()) await customer.locator('[name="' + name + '"]').fill(value);
    if (await customer.locator('#billing_country').count()) await customer.locator('#billing_country').selectOption('AR', { force: true });
    if (await customer.locator('#billing_state').count()) await customer.locator('#billing_state').selectOption('C', { force: true });
    await Promise.all([customer.waitForNavigation({ waitUntil: 'networkidle' }), customer.locator('[name="save_address"]').click()]);
    check('Native account address save succeeds', await customer.locator('.woocommerce-error').count() === 0);
    await go(customer, billingLink);
    check('Native billing address persisted independently', await customer.locator('#billing_address_1').inputValue() === address.billing_address_1 && await customer.locator('#billing_city').inputValue() === address.billing_city);
    report.account = { order_id: orderId, nativeAddressSaved: true, authenticated: true };
    const adminDenied = await customerContext.request.get(base + '/wp-admin/admin.php?page=commerce-legal');
    check('Customer cannot read legal administration', adminDenied.status() === 403 || !(await adminDenied.text()).includes('<h1>Solicitudes de arrepentimiento</h1>'));
    const mutateDenied = await customerContext.request.post(base + '/wp-admin/admin-post.php', { form: { action: 'commerce_legal_manage', case: '1', status: 'resolved' } });
    check('Customer cannot mutate legal cases', mutateDenied.status() === 403);
    await customerContext.close();
    } else await guest.close();

    const managerContext = await context();
    const manager = await login(managerContext, users.manager);
    await go(manager, base + '/wp-admin/admin.php?page=commerce-legal');
    const caseLink = manager.getByRole('link', { name: code, exact: true });
    check('Manager sees only locally created receipt in this test', await caseLink.count() === 1);
    await caseLink.click();
    await manager.waitForLoadState('networkidle');
    const form = manager.locator('form:has([name="operation"])');
    const data = await form.evaluate(f => Object.fromEntries(new FormData(f)));
    const caseId = Number(data.case);
    assert.ok(caseId > 0);
    const withoutVerification = await managerContext.request.post(base + '/wp-admin/admin-post.php', { form: { ...data, operation: 'update', status: 'review', order_id: String(orderId), note: 'Prueba DEMO H10 de asociación sin verificar.' } });
    check('Order association requires verification', withoutVerification.status() === 403 && /Verificá identidad.*pedido/.test(await withoutVerification.text()));
    const invalidNonce = await managerContext.request.post(base + '/wp-admin/admin-post.php', { form: { ...data, _wpnonce: 'invalid', operation: 'update', status: 'review', note: 'No debe persistir.' } });
    check('Legal administration rejects invalid nonce', invalidNonce.status() === 403);
    const note = 'DEMO H10: identidad y relación con pedido sintético verificadas mediante fixture privado controlado. No hay compra real ni operación financiera.';
    const update = await managerContext.request.post(base + '/wp-admin/admin-post.php', { form: { ...data, operation: 'update', status: 'review', order_id: String(orderId), verified: '1', note } });
    check('Authorized manager saves synthetic case review', update.ok());
    await go(manager, base + '/wp-admin/admin.php?page=commerce-legal&case=' + caseId);
    check('Legal review state persists', await manager.locator('[name="status"]').inputValue() === 'review');
    check('Legal note audit persists', (await manager.locator('.wrap').innerText()).includes(note));
    check('Legal order association uses native HPOS link', await manager.locator('.wrap a[href*="wc-orders"]').count() === 1);
    report.legal = { request_id: caseId, code, order_id: orderId, status: 'review', managerAuthorized: true };
    await managerContext.close();
    check('No uncaught own browser errors', report.errors.length === 0);
    report.result = 'passed';
    save();
    console.log(JSON.stringify({ result: report.result, checks: report.checks.length, order: orderId, legalCode: code }));
  } finally { await browser.close(); }
})().catch(e => { report.result = 'failed'; report.error = redact(e.message); save(); console.error(redact(e.message)); process.exitCode = 1; });
