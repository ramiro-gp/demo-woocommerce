/** Real H7.2 local browser QA. New owned BACS orders and customer only.
 * All keys, credentials, raw HTML and screenshots remain in ignored .local.
 * H72_RESUME=1 reuses this runner's private fixture after a diagnostic failure.
 */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
process.env.PLAYWRIGHT_BROWSERS_PATH ||= 'C:\\Users\\ramiro\\AppData\\Local\\ms-playwright';
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..'), base = 'http://localhost:8080', mailpit = 'http://localhost:8025';
assert.equal(root.toLowerCase(), 'c:\\codigo\\demo-woocommerce');
const output = path.join(root, '.local/local/h7-2/browser');
fs.mkdirSync(output, { recursive: true });
const fixturePath = path.join(output, 'fixture-private.json'), ordersPath = path.join(output, 'orders-private.json');
const accountMailOnly = process.env.H72_SCOPE === 'account-mail';
const mailIndexOnly = process.env.H72_SCOPE === 'mail-index';
assert.ok(!process.env.H72_SCOPE || accountMailOnly || mailIndexOnly, 'Use the full suite, account-mail, or mail-index scope.');
const reportName = accountMailOnly ? 'qa-account-mail.json' : mailIndexOnly ? 'qa-mail-index.json' : 'qa.json';
const report = { started: new Date().toISOString(), base, checks: [], errors: [], assetErrors: [], blockedTelemetryErrors: [], blockedExternalHosts: [], captures: [], externalPaymentOperations: false, historicalOrdersDeleted: false };
let fixture, privateOrders = {}, browser, active = '', secrets = [];
const safe = value => secrets.reduce((s, secret) => secret ? s.split(secret).join('[private]') : s, String(value)).replace(/([?&](?:key|order_key|token|nonce)=)[^&#\s"']+/gi, '$1[private]').replace(/wc_order_[a-z0-9]+/gi, '[private-order-key]');
function save() { report.passed = report.checks.filter(c => c.result === 'passed').length; report.failed = report.checks.filter(c => c.result === 'failed').length; fs.writeFileSync(path.join(output, reportName), JSON.stringify(report, null, 2) + '\n'); fs.writeFileSync(ordersPath, JSON.stringify(privateOrders, null, 2) + '\n'); }
function php(mode, ...args) {
  assert.ok(['prepare', 'inspect', 'adopt', 'links', 'cleanup'].includes(mode));
  assert.ok(args.every(a => /^[a-f0-9]{32}$|^[0-9]+$/.test(String(a))));
  const wp = ['eval-file', '/opt/local-tools/h7-2-browser.php', mode, ...args.map(String), '--quiet'].map(a => "'" + a + "'").join(',');
  const command = "& '" + path.join(root, 'scripts/dev.ps1').replaceAll('\\', '/') + "' wp -Instance local -WpArgs @(" + wp + ')';
  const result = spawnSync('powershell.exe', ['-NoProfile', '-ExecutionPolicy', 'Bypass', '-Command', command], { cwd: root, encoding: 'utf8', timeout: 150000, windowsHide: true });
  assert.equal(result.status, 0, 'Private owned-fixture CLI failed in mode ' + mode + '; raw output withheld.');
  const start = result.stdout.indexOf('{'); assert.ok(start >= 0, 'Fixture CLI must return JSON.');
  const data = JSON.parse(result.stdout.slice(start)); assert.ok(['ready', 'passed'].includes(data.result) || mode === 'links', 'Owned-fixture guard failed.'); return data;
}
function inspect() { const value = php('inspect', fixture.run); assert.ok(value.history_unchanged && value.catalog_unchanged && value.sensitive_unchanged, 'Historical orders/catalog/payment configuration must remain unchanged.'); return value; }
const orderById = (snapshot, id) => snapshot.orders.find(o => Number(o.id) === Number(id));
const normalize = value => String(value).replace(/<[^>]+>/g, ' ').replace(/&nbsp;|&#160;/g, ' ').replace(/&(?:amp);/g, '&').replace(/\s+/g, ' ').trim();
const address = { first_name: 'Prueba', last_name: 'H7.2 Browser', address_1: 'Dirección sintética 123', city: 'Buenos Aires', state: 'C', postcode: '1001', country: 'AR' };
async function check(name, task, page) {
  active = name;
  try { const evidence = await task(); report.checks.push({ name, result: 'passed', evidence }); }
  catch (error) { report.checks.push({ name, result: 'failed', error: safe(error.stack) }); if (page && !page.isClosed() && !/wp-login|customer-login/.test(name)) await capture(page, 'failure-' + name.replace(/[^a-z0-9-]/gi, '-')).catch(() => {}); save(); throw error; }
  save();
}
async function context(viewport = { width: 1366, height: 1000 }) {
  const context = await browser.newContext({ viewport, colorScheme: 'light', serviceWorkers: 'block' });
  assert.equal((await context.cookies()).length, 0, 'Fresh browser contexts do not inherit real users.');
  await context.route(/^https?:\/\//, async route => {
    const request = route.request(), url = new URL(request.url());
    if (url.origin !== base) { if (!report.blockedExternalHosts.includes(url.host)) report.blockedExternalHosts.push(url.host); return route.abort('blockedbyclient'); }
    if (isFinal(request)) assert.equal(request.postDataJSON().payment_method, 'bacs', 'Only BACS can be submitted.');
    return route.fallback();
  });
  const page = await context.newPage(); page.setDefaultTimeout(20000); page.setDefaultNavigationTimeout(45000);
  page.on('pageerror', error => { const entry = { check: active, error: safe(error.message) }; (/Unable to load melidata script/i.test(error.message) ? report.blockedTelemetryErrors : report.errors).push(entry); });
  page.on('response', response => { if (response.url().startsWith(base) && response.status() >= 400 && /\.(css|js|webp|woff2)(\?|$)/.test(response.url())) report.assetErrors.push({ path: new URL(response.url()).pathname, status: response.status() }); });
  return { context, page, api: cartAPI(context) };
}
function isFinal(request) { return request.method() === 'POST' && /(?:\/|%2F)wc(?:\/|%2F)store(?:\/|%2F)v1(?:\/|%2F)checkout(?:\?|&|$)/.test(request.url()) && new URL(request.url()).searchParams.get('__experimental_calc_totals') !== 'true'; }
function cartAPI(context) {
  let token;
  return { async call(method = 'get', route = 'cart', data) { if (method !== 'get') await this.call(); const response = await context.request[method](base + '/index.php?rest_route=/wc/store/v1/' + route, { timeout: 30000, headers: token && method !== 'get' ? { 'Cart-Token': token } : {}, ...(data ? { data } : {}) }); token = response.headers()['cart-token'] || token; assert.ok(response.ok(), 'Owned native cart request failed: ' + route); return response.json(); }, async clear() { const cart = await this.call(); for (const coupon of cart.coupons) await this.call('post', 'cart/remove-coupon', { code: coupon.code }); await this.call('delete', 'cart/items'); assert.equal((await this.call()).items_count, 0); } };
}
async function settle(page) {
  await page.waitForLoadState('domcontentloaded');
  await page.waitForFunction(() => { const wc = window.wc?.wcBlocksData, data = window.wp?.data, checkout = wc?.checkoutStore && data?.select(wc.checkoutStore), cart = wc?.cartStore && data?.select(wc.cartStore); return (!checkout || !checkout.isCalculating()) && (!cart || (!cart.getItemsPendingQuantityUpdate().length && !cart.getItemsPendingDelete().length && !cart.getProductsPendingAdd().length)); });
  await page.waitForTimeout(400);
}
async function capture(page, name) { await page.evaluate(async () => { await document.fonts.ready; document.activeElement?.blur(); scrollTo({ top: 0, behavior: 'instant' }); }); const file = name + '.png'; await page.screenshot({ path: path.join(output, file), fullPage: true }); report.captures.push(file); }
async function geometry(page) { return page.evaluate(() => { const root = document.documentElement, main = document.querySelector('main') || document.body; return { width: innerWidth, documentWidth: root.scrollWidth, overflow: root.scrollWidth > innerWidth + 2, text: main.innerText.slice(0, 180) }; }); }
async function go(page, url) { assert.equal(new URL(url).origin, base); await page.goto(url, { waitUntil: 'domcontentloaded' }); await page.locator('main').waitFor(); await settle(page); }
async function prepareCart(session, mixed, email) {
  await go(session.page, fixture.simple_url);
  await Promise.all([session.page.waitForNavigation({ waitUntil: 'domcontentloaded' }), session.page.locator('.single_add_to_cart_button').click()]);
  await settle(session.page); await session.api.clear();
  await session.api.call('post', 'cart/add-item', { id: fixture.products.simple, quantity: 1 });
  if (mixed) { await session.api.call('post', 'cart/add-item', { id: fixture.products.variation, quantity: 1 }); await session.api.call('post', 'cart/apply-coupon', { code: fixture.coupon }); }
  await session.api.call('post', 'cart/update-customer', { shipping_address: address, billing_address: { ...address, email, phone: '1100000000' } });
  await go(session.page, fixture.checkout); await session.page.locator('#email').waitFor();
  await session.page.locator('#email').fill(email);
  for (const prefix of ['shipping', 'billing']) {
    const edit = session.page.locator('#' + prefix + '-fields').getByRole('button', { name: /editar/i }); if (await edit.isVisible().catch(() => false)) await edit.click();
    const country = session.page.locator('#' + prefix + '-country'); if (await country.isVisible().catch(() => false)) { await country.selectOption('AR'); await settle(session.page); }
    for (const [key, value] of Object.entries(address)) { if (key === 'country') continue; const input = session.page.locator('#' + prefix + '-' + key); if (!await input.isVisible().catch(() => false)) continue; if (await input.evaluate(n => n.tagName === 'SELECT')) await input.selectOption(value); else await input.fill(value); }
  }
  await session.page.locator('#email').press('Tab'); await settle(session.page);
  const bacs = session.page.locator('input[type="radio"][value="bacs"]'); await bacs.waitFor(); await bacs.focus(); await session.page.keyboard.press('Space'); await settle(session.page); assert.ok(await bacs.isChecked());
  assert.equal(await session.page.locator('input[type="radio"][value^="woo-mercado-pago"]').count(), 0, 'MP must stay unavailable.');
  const cart = await session.api.call(); assert.equal(cart.totals.currency_code, 'ARS'); assert.equal(cart.items_count, mixed ? 2 : 1); assert.equal(Number(cart.totals.total_shipping), 450000); assert.equal(Number(cart.totals.total_price), mixed ? 3150000 : 1650000); if (mixed) assert.equal(Number(cart.totals.total_discount), 300000); return cart;
}
async function submit(session, kind) {
  let count = 0; const responsePromise = session.page.waitForResponse(response => isFinal(response.request())); const listener = request => { if (isFinal(request)) count++; }; session.page.on('request', listener);
  await session.page.locator('.wc-block-components-checkout-place-order-button').click();
  const response = await responsePromise, result = await response.json(); assert.ok(response.ok()); assert.equal(result.status, 'on-hold'); assert.equal(result.payment_method, 'bacs');
  await session.page.waitForURL(url => url.searchParams.has('order-received') || /\/order-received\//.test(url.pathname)); await settle(session.page); session.page.off('request', listener); assert.equal(count, 1);
  privateOrders[kind] = { id: Number(result.order_id), key: result.order_key, url: session.page.url() }; secrets.push(result.order_key); save(); php('adopt', fixture.run, result.order_id);
  const links = php('links', fixture.run); fs.writeFileSync(path.join(output, 'review-links-private.json'), JSON.stringify(links, null, 2) + '\n');
  return privateOrders[kind];
}
async function receipt(page, record) { await go(page, record.url); const text = await page.locator('main').innerText(); assert.match(text, /pedido recibido/i); return text; }
async function mailList() { const all = []; let start = 0; while (true) { const response = await fetch(mailpit + '/api/v1/messages?start=' + start + '&limit=500'); assert.ok(response.ok); const data = await response.json(); all.push(...data.messages); start += data.messages.length; if (!data.messages.length || start >= data.total) break; } return all; }
async function mailFor(id) {
  const list = await mailList(); const messages = [];
  for (const item of list.filter(item => String(item.Subject).includes(String(id)))) { const response = await fetch(mailpit + '/api/v1/message/' + item.ID), detail = await response.json(); const recipients = [...(detail.To || []), ...(detail.Cc || []), ...(detail.Bcc || [])].map(r => r.Address); if (!recipients.length || recipients.some(address => !address.endsWith('@example.test'))) continue; if (!String(detail.HTML).includes(String(id)) && !String(detail.Text).includes(String(id))) continue; messages.push(detail); }
  return messages;
}
function assertMailContent(customer, type) {
  assert.ok(customer?.HTML && customer?.Text, 'Actual email has HTML and plain alternatives.');
  for (const text of [normalize(customer.HTML), normalize(customer.Text)]) {
    assert.match(text, /pendiente/i); assert.match(text, /No realizar transferencias reales/i); assert.doesNotMatch(text, /recibimos tu pago|pago confirmado/i); assert.match(text, /Prueba browser H7\.2/);
    assert.ok(text.includes(type === 'guest' ? '31.500,00' : '16.500,00'), 'Actual checkout total is preserved in both alternatives.'); assert.ok(text.includes('4.500,00'), 'Actual shipping is preserved in both alternatives.');
    if (type === 'guest') { assert.match(text, /Natural/); assert.match(text, /M/); assert.ok(text.includes('3.000,00'), 'Actual coupon discount is preserved.'); }
  }
  assert.equal((normalize(customer.Text).match(/×\s*1/g) || []).length, type === 'guest' ? 2 : 1, 'Each actual line quantity is preserved in plain text.');
}
async function verifyMail(record, type) {
  let messages = [], customer;
  for (let attempt = 0; attempt < 20; attempt++) { messages = await mailFor(record.id); customer = messages.find(m => m.To.some(r => r.Address === (type === 'guest' ? fixture.guest_email : fixture.email)) && /Recibimos tu pedido/i.test(m.Subject)); if (customer) break; await new Promise(r => setTimeout(r, 300)); }
  assert.ok(customer, 'Native on-hold customer email captured.'); assert.ok(messages.some(m => /nuevo pedido/i.test(m.Subject)), 'Native administrator new-order email captured.');
  assertMailContent(customer, type);
  fs.writeFileSync(path.join(output, type + '-on-hold.html'), customer.HTML); fs.writeFileSync(path.join(output, type + '-on-hold.txt'), customer.Text);
  const preview = await context({ width: 680, height: 1000 });
  try { for (const width of [390, 680]) { await preview.page.setViewportSize({ width, height: 1000 }); await preview.page.setContent(customer.HTML, { waitUntil: 'domcontentloaded' }); const layout = await geometry(preview.page); assert.ok(!layout.overflow, 'Email fits mobile/desktop preview.'); await capture(preview.page, type + '-email-' + width); } } finally { await preview.context.close(); }
  return { orderId: record.id, messages: messages.length, customerSubject: customer.Subject, htmlAndPlain: true, localPreviewOnly: true, mailpitUrl: mailpit + '/view/' + customer.ID };
}
async function customerLogin(session) { await go(session.page, fixture.account); await session.page.locator('#username').fill(fixture.username); await session.page.locator('#password').fill(fixture.password); await Promise.all([session.page.waitForNavigation({ waitUntil: 'domcontentloaded' }), session.page.locator('button[name="login"]').click()]); await settle(session.page); assert.equal(await session.page.locator('#username').count(), 0); }
function accountURL(endpoint, id) { const url = new URL(fixture.account); url.searchParams.set(endpoint, String(id ?? '')); return url.href; }
async function mailForCustomer() {
  const messages = [];
  for (const item of await mailList()) {
    if (!item.To?.some(recipient => recipient.Address === fixture.email)) continue;
    const response = await fetch(mailpit + '/api/v1/message/' + item.ID), detail = await response.json();
    const recipients = [...(detail.To || []), ...(detail.Cc || []), ...(detail.Bcc || [])];
    assert.ok(recipients.length && recipients.every(recipient => recipient.Address.endsWith('@example.test')));
    messages.push(detail);
  }
  return messages;
}
async function writeMailIndex() {
  const index = { run: fixture.run, orders: [], account: [] };
  for (const [kind, record] of Object.entries(privateOrders)) { const messages = await mailFor(record.id); const received = messages.find(message => message.To.some(recipient => recipient.Address === (kind === 'guest' ? fixture.guest_email : fixture.email)) && /Recibimos tu pedido/i.test(message.Subject)); assertMailContent(received, kind); index.orders.push({ kind, orderId: record.id, htmlPlainAmountShippingQuantityVerified: true, messages: messages.map(message => ({ subject: message.Subject, url: mailpit + '/view/' + message.ID })) }); }
  for (const message of await mailForCustomer()) { if (/cuenta|contrase[ñn]a|bienvenid/i.test(message.Subject)) index.account.push({ subject: message.Subject, url: mailpit + '/view/' + message.ID }); }
  fs.writeFileSync(path.join(output, 'mailpit-links.json'), JSON.stringify(index, null, 2) + '\n');
  report.mailpit = index;
  return { ownedOrders: index.orders.map(order => order.orderId), orderEmails: index.orders.reduce((total, order) => total + order.messages.length, 0), accountEmails: index.account.length, privateIndex: '.local/local/h7-2/browser/mailpit-links.json' };
}
async function accountMail(session) {
  await check('account-mail/native-new-customer-email', async () => {
    const messages = await mailForCustomer(), welcome = messages.find(message => /cuenta|bienvenid/i.test(message.Subject) && !/contrase[ñn]a/i.test(message.Subject));
    assert.ok(welcome && welcome.HTML && welcome.Text, 'Native new-customer HTML/plain email is captured.');
    assert.match(normalize(welcome.Text), /cuenta|bienvenid/i); assert.ok(!welcome.HTML.includes(fixture.password) && !welcome.Text.includes(fixture.password), 'Account password is never emailed.');
    fs.writeFileSync(path.join(output, 'new-account.html'), welcome.HTML); fs.writeFileSync(path.join(output, 'new-account.txt'), welcome.Text);
    const links = [...welcome.HTML.matchAll(/href=["']([^"']+)["']/gi)].map(match => match[1].replaceAll('&amp;', '&'));
    assert.ok(links.some(link => { try { const url = new URL(link); return url.origin === base && (url.searchParams.has('page_id') || /cuenta|account/i.test(url.pathname)); } catch { return false; } }));
    return { nativeCustomerCreation: true, controlledRecipient: true, htmlAndPlain: true, spanish: true, passwordNotEmailed: true };
  });
  await check('account-mail/native-password-reset-request-only', async () => {
    const before = new Set((await mailForCustomer()).map(message => message.ID)), initial = inspect();
    await go(session.page, accountURL('lost-password', '')); const form = session.page.locator('form.lost_reset_password'); await form.locator('#user_login').fill(fixture.email);
    await Promise.all([session.page.waitForNavigation({ waitUntil: 'domcontentloaded' }), form.locator('button[type="submit"]').click()]); await settle(session.page);
    assert.match(await session.page.locator('main').innerText(), /correo|enviad|contrase[ñn]a/i); await capture(session.page, 'password-reset-requested');
    let message;
    for (let attempt = 0; attempt < 20; attempt++) { message = (await mailForCustomer()).find(message => !before.has(message.ID) && /contrase[ñn]a/i.test(message.Subject)); if (message) break; await new Promise(resolve => setTimeout(resolve, 300)); }
    assert.ok(message && message.HTML && message.Text, 'Native password-reset HTML/plain email captured.'); assert.match(normalize(message.Text), /contrase[ñn]a/i); assert.ok(!message.HTML.includes(fixture.password) && !message.Text.includes(fixture.password));
    fs.writeFileSync(path.join(output, 'password-reset.html'), message.HTML); fs.writeFileSync(path.join(output, 'password-reset.txt'), message.Text);
    const links = [...message.HTML.matchAll(/href=["']([^"']+)["']/gi)].map(match => match[1].replaceAll('&amp;', '&'));
    const reset = links.find(link => { try { const url = new URL(link); return url.origin === base && url.searchParams.has('key') && (url.searchParams.has('lost-password') || /lost-password/.test(url.pathname)); } catch { return false; } });
    assert.ok(reset, 'Password reset preserves the native protected WooCommerce endpoint.');
    fs.writeFileSync(path.join(output, 'password-reset-link-private.json'), JSON.stringify({ url: reset }, null, 2) + '\n');
    const after = inspect(); assert.deepEqual(after.stock, initial.stock); assert.deepEqual(after.orders, initial.orders);
    return { controlledRecipient: true, htmlAndPlain: true, spanish: true, nativeProtectedLink: true, resetLinkNotFollowed: true, passwordUnchanged: true, ordersAndStockUnchanged: true };
  }, session.page);
}
async function accountOrder(session, record, status, captureName) {
  await go(session.page, accountURL('orders', '')); const row = session.page.locator('.woocommerce-orders-table__row').filter({ hasText: '#' + record.id }); assert.equal(await row.count(), 1, 'Customer sees its exact owned order.'); assert.match(await row.innerText(), status);
  await row.locator('a.view').click(); await settle(session.page); const text = await session.page.locator('main').innerText(); assert.ok(text.includes(String(record.id))); assert.match(text, status); if (/espera/i.test(status.source)) { assert.match(text, /No realizar transferencias reales/); }
  if (captureName) { for (const width of [390, 1366]) { await session.page.setViewportSize({ width, height: 1000 }); assert.ok(!(await geometry(session.page)).overflow); await capture(session.page, captureName + '-' + width); } }
  return { orderId: record.id, statusVisible: true, nativeViewPermission: true };
}
async function adminSession() {
  const config = fs.readFileSync(path.join(root, '.local/local/.env'), 'utf8'), user = config.match(/^LOCAL_ADMIN_USER=(.+)$/m)?.[1].trim(), password = fs.readFileSync(path.join(root, '.local/local/secrets/wp-admin-password.txt'), 'utf8').trim(); assert.ok(user && password); secrets.push(password);
  const admin = await context(); await admin.page.goto(base + '/wp-login.php'); await admin.page.locator('#user_login').fill(user); await admin.page.locator('#user_pass').fill(password); await Promise.all([admin.page.waitForNavigation({ waitUntil: 'domcontentloaded' }), admin.page.locator('#wp-submit').click()]); return admin;
}
async function changeStatus(admin, record, target) {
  const before = inspect(), order = orderById(before, record.id); assert.ok(order && order.customer_id === fixture.customer_id && order.method === 'bacs'); assert.ok(['on-hold', 'processing', 'completed'].includes(order.status));
  await admin.page.goto(base + '/wp-admin/admin.php?page=wc-orders&action=edit&id=' + record.id, { waitUntil: 'domcontentloaded' }); const status = admin.page.locator('#order_status'); await status.waitFor(); await status.selectOption('wc-' + target);
  const update = admin.page.locator('button.save_order,button[name="save"],input[name="save"]').first(); await Promise.all([admin.page.waitForNavigation({ waitUntil: 'domcontentloaded' }), update.click()]);
  let after = inspect(); assert.equal(orderById(after, record.id).status, target); assert.deepEqual(after.stock, before.stock); assert.ok(orderById(after, record.id).is_paid && orderById(after, record.id).has_date_paid); await capture(admin.page, 'admin-' + target);
  const count = (await mailFor(record.id)).length; await Promise.all([admin.page.waitForNavigation({ waitUntil: 'domcontentloaded' }), update.click()]); after = inspect(); assert.equal(orderById(after, record.id).status, target); assert.deepEqual(after.stock, before.stock); assert.equal((await mailFor(record.id)).length, count, 'Repeated native status save has no accidental email duplicate.');
  const messages = await mailFor(record.id), pattern = target === 'processing' ? /prepar|proces|confirm/i : /finaliz|complet/i; const email = messages.find(m => m.To.some(r => r.Address === fixture.email) && pattern.test(m.Subject)); assert.ok(email && email.HTML && email.Text, 'Actual native status email captured.'); fs.writeFileSync(path.join(output, target + '.html'), email.HTML); fs.writeFileSync(path.join(output, target + '.txt'), email.Text);
  return { orderId: record.id, target, nativeAdmin: true, unchangedStock: true, repeatedSaveNoEmail: true, subject: email.Subject, mailpitUrl: mailpit + '/view/' + email.ID, moneyTransferred: false };
}

(async () => {
  const sessions = [];
  try {
    if (process.env.H72_RESUME === '1' || accountMailOnly || mailIndexOnly) { fixture = JSON.parse(fs.readFileSync(fixturePath, 'utf8')); privateOrders = fs.existsSync(ordersPath) ? JSON.parse(fs.readFileSync(ordersPath, 'utf8')) : {}; }
    else { fixture = php('prepare'); fs.writeFileSync(fixturePath, JSON.stringify(fixture, null, 2) + '\n'); }
    secrets.push(fixture.password, ...Object.values(privateOrders).map(o => o.key)); report.run = fixture.run;
    if (mailIndexOnly) { await check('evidence/mailpit-index-owned-orders', () => writeMailIndex()); return; }
    browser = await chromium.launch({ headless: true });
    if (accountMailOnly) { const account = await context(); sessions.push(account); await accountMail(account); return; }
    const guest = await context(); sessions.push(guest);
    await check('guest/native-bacs-simple-variation-coupon-shipping', async () => {
      if (!privateOrders.guest) { const before = inspect(), cart = await prepareCart(guest, true, fixture.guest_email), record = await submit(guest, 'guest'), after = inspect(), order = orderById(after, record.id); assert.equal(order.customer_id, 0); assert.equal(order.status, 'on-hold'); assert.equal(order.is_paid, false); assert.equal(order.total, 31500); assert.equal(order.discount, 3000); assert.equal(order.shipping, 4500); assert.match(order.data_store, /OrdersTableDataStore/); assert.equal(after.stock.simple, before.stock.simple - 1); assert.equal(after.stock.variation, before.stock.variation - 1); assert.equal(cart.items.length, 2); }
      const record = privateOrders.guest, before = inspect(); for (const width of [390, 1366]) { await guest.page.setViewportSize({ width, height: 1000 }); const text = await receipt(guest.page, record); assert.match(text, /pendiente/i); assert.match(text, /No realizar transferencias reales/); assert.doesNotMatch(text, /recibimos tu pago|pago confirmado|confirmamos el pago/i); assert.ok(!(await geometry(guest.page)).overflow); await capture(guest.page, 'guest-received-' + width); }
      await guest.page.reload({ waitUntil: 'domcontentloaded' }); await settle(guest.page); assert.deepEqual(inspect().stock, before.stock); assert.equal(orderById(inspect(), record.id).status, 'on-hold'); return { orderId: record.id, customerId: 0, total: 31500, shipping: 4500, discount: 3000, refreshStockUnchanged: true };
    }, guest.page);
    await check('guest/mailpit-html-plain-and-preview', () => verifyMail(privateOrders.guest, 'guest'));
    const outsider = await context(); sessions.push(outsider);
    await check('security/invalid-key-and-account-permissions', async () => { const before = inspect(), bad = new URL(privateOrders.guest.url); bad.searchParams.set('key', 'wc_order_invalid_h72_browser'); await go(outsider.page, bad.href); const text = await outsider.page.locator('main').innerText(); assert.ok(!text.includes('Dirección sintética 123') && !text.includes('Prueba browser H7.2') && !text.includes(fixture.guest_email)); await capture(outsider.page, 'invalid-guest-key'); assert.deepEqual(inspect().stock, before.stock); return { invalidKeyHidesDetails: true, stockUnchanged: true }; }, outsider.page);
    const customer = await context(); sessions.push(customer); await check('customer-login/fresh-controlled-account', async () => { await customerLogin(customer); return { customerId: fixture.customer_id, role: inspect().customer_role ? 'customer' : 'unexpected' }; }, customer.page);
    await check('customer/native-bacs-order', async () => { if (!privateOrders.customer) { const before = inspect(); await prepareCart(customer, false, fixture.email); await submit(customer, 'customer'); const after = inspect(), order = orderById(after, privateOrders.customer.id); assert.equal(order.customer_id, fixture.customer_id); assert.equal(order.total, 16500); assert.equal(after.stock.simple, before.stock.simple - 1); assert.equal(after.stock.variation, before.stock.variation); } return { orderId: privateOrders.customer.id, customerId: fixture.customer_id, total: 16500 }; }, customer.page);
    await check('customer/mailpit-html-plain-and-preview', () => verifyMail(privateOrders.customer, 'customer'));
    await check('account/on-hold-order-and-native-bacs-instructions', () => accountOrder(customer, privateOrders.customer, /en espera/i, 'account-on-hold'), customer.page);
    await check('security/customer-cannot-view-guest-order', async () => { await go(customer.page, accountURL('view-order', privateOrders.guest.id)); const text = await customer.page.locator('main').innerText(); assert.match(text, /pedido no v[aá]lido|no tienes permiso|no tenés permiso|invalid order/i); assert.ok(!text.includes('Dirección sintética 123')); await capture(customer.page, 'customer-other-order-denied'); return { nativePermissionDenied: true }; }, customer.page);
    const admin = await adminSession(); sessions.push(admin);
    await check('admin/native-processing-and-repeat-save', () => changeStatus(admin, privateOrders.customer, 'processing'), admin.page);
    await check('account/processing-status', () => accountOrder(customer, privateOrders.customer, /procesando/i, 'account-processing'), customer.page);
    await check('admin/native-completed-and-repeat-save', () => changeStatus(admin, privateOrders.customer, 'completed'), admin.page);
    await check('account/completed-status', () => accountOrder(customer, privateOrders.customer, /completado/i, 'account-completed'), customer.page);
    await check('cleanup/owned-coupon-and-preservation', async () => { for (const session of [guest, customer]) await session.api.clear(); const final = php('cleanup', fixture.run); assert.ok(final.history_unchanged && final.catalog_unchanged && final.sensitive_unchanged && final.coupon_retired); report.final = final; return { historicalOrdersUnchanged: true, historicalCatalogUnchanged: true, sensitiveConfigurationUnchanged: true, couponRetired: true, testOrdersPreserved: true, cartsCleared: true }; });
  } catch (error) { if (!report.failed) report.checks.push({ name: 'setup-or-unhandled', result: 'failed', error: safe(error.stack) }); console.log('H7.2 browser failed:', safe(error.message).slice(0, 200)); }
  finally { for (const session of sessions) await session.context.close().catch(() => {}); if (browser) await browser.close(); report.finished = new Date().toISOString(); save(); console.log(JSON.stringify({ passed: report.passed, failed: report.failed, jsErrors: report.errors.length, assetErrors: report.assetErrors.length, captures: report.captures.length, report: '.local/local/h7-2/browser/' + reportName, externalPayments: false })); if (report.failed || report.errors.length || report.assetErrors.length) process.exitCode = 1; }
})();
