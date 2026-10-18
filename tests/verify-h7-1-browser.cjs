/** H7.1 development QA. Fresh owned guests only; actual native BACS orders.
 * Run scripts/h7-1.ps1 backup/configure/prepare first. Never uses user cookies.
 * HTTP is restricted to localhost:8080; no Mercado Pago payment is submitted.
 * Full order keys/return URLs stay in ignored .local private-orders.json only.
 */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
process.env.PLAYWRIGHT_BROWSERS_PATH ||= 'C:\\Users\\ramiro\\AppData\\Local\\ms-playwright';
const playwright = require('playwright');
const root = path.resolve(__dirname, '..');
const base = 'http://localhost:8080';
assert.equal((process.env.H71_BASE_URL || base).replace(/\/$/, ''), base, 'QA is restricted to the main local test instance.');
const output = path.join(root, '.local/local/h7-1/browser');
const fixtureFile = path.join(root, '.local/local/h7-1/prepare.json');
const reportFile = path.join(output, process.env.H71_REPORT || 'qa.json');
const widths = (process.env.H71_WIDTHS || '320,375,390,430,768,1024,1366,1920,2560,3440').split(',').map(Number);
const email = 'h7-1-qa@example.test';
const address = { first_name: 'Prueba', last_name: 'H7.1', address_1: 'Dirección de prueba 123', city: 'Buenos Aires', state: 'C', postcode: '1001', country: 'AR' };
const report = { started: new Date().toISOString(), base, widths, category: 'Native BACS test orders; no external financial operation', checks: [], errors: [], blockedDependencyErrors: [], assetErrors: [], externalHostsBlocked: [], finalCheckoutRequests: [], captures: [], cleanup: [] };
const privateOrders = ['matrix', 'post-admin', 'response-loss'].includes(process.env.H71_SCOPE) && fs.existsSync(path.join(output, 'private-orders.json')) ? JSON.parse(fs.readFileSync(path.join(output, 'private-orders.json'), 'utf8')) : [];
let active = '', browser, matrixSession;
fs.mkdirSync(output, { recursive: true });
function safe(value) { return String(value).replace(/([?&](?:key|order_key|token|access_token|nonce)=)[^&#\s"']+/gi, '$1[private]').replace(/wc_order_[a-z0-9]+/gi, '[private-order-key]'); }
function save() {
  report.passed = report.checks.filter(c => c.result === 'passed').length;
  report.failed = report.checks.filter(c => c.result === 'failed').length;
  fs.writeFileSync(reportFile, JSON.stringify(report, null, 2) + '\n');
  fs.writeFileSync(path.join(output, 'private-orders.json'), JSON.stringify(privateOrders, null, 2) + '\n');
}
function inspect() {
  const result = spawnSync('powershell.exe', ['-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', path.join(root, 'scripts/h7-1.ps1'), 'orders'], { cwd: root, encoding: 'utf8', timeout: 120000, windowsHide: true });
  assert.equal(result.status, 0, 'Read-only Woo CRUD inspection failed; inspect private script reports.');
  const start = result.stdout.indexOf('{');
  assert.ok(start >= 0, 'Orders inspection must return JSON.');
  return JSON.parse(result.stdout.slice(start));
}
const list = snapshot => snapshot.orders || snapshot.summary?.orders || [];
const orderById = (snapshot, id) => list(snapshot).find(o => Number(o.id || o.order_id) === Number(id));
const finalOrderIds = snapshot => list(snapshot).filter(o => !['checkout-draft', 'auto-draft'].includes(o.status)).map(o => Number(o.id || o.order_id));
function orderStock(snapshot) { return snapshot.stock || snapshot.products || snapshot.fixtures || null; }
function inventory(snapshot) { return Object.fromEntries(Object.values(snapshot.fixtures || {}).filter(p => p.id && p.stock !== undefined).map(p => [p.id, Number(p.stock)])); }
async function check(name, task, page) {
  if (fs.existsSync(path.join(output, 'cancel'))) throw new Error('QA cancellation requested.');
  active = name;
  try { report.checks.push({ name, result: 'passed', evidence: await task() }); }
  catch (error) {
    const diagnostic = page && !page.isClosed() ? await page.evaluate(() => ({ text: document.querySelector('main')?.innerText.slice(-2800), notices: [...document.querySelectorAll('.wc-block-components-notice-banner,.wc-block-components-validation-error')].map(n => n.textContent.trim()) })).catch(() => null) : null;
    report.checks.push({ name, result: 'failed', error: safe(error.stack), diagnostic });
    console.log('FAIL', name, safe(error.message).slice(0, 260));
    if (page && !page.isClosed() && !page.url().includes('wp-login')) await capture(page, 'failure-' + name.replace(/[^a-z0-9-]/gi, '-')).catch(() => {});
  }
  save();
}
async function localContext() {
  const context = await browser.newContext({ viewport: { width: 1366, height: 1000 }, colorScheme: 'light', serviceWorkers: 'block' });
  assert.equal((await context.cookies()).length, 0, 'Fresh guest context has no inherited cookies.');
  await context.route(/^https?:\/\//, async route => {
    const request = route.request(), url = new URL(request.url());
    if (url.origin !== base) {
      if (!report.externalHostsBlocked.includes(url.host)) report.externalHostsBlocked.push(url.host);
      return route.abort('blockedbyclient');
    }
    if (isFinal(request)) {
      const body = request.postDataJSON();
      assert.equal(body.payment_method, 'bacs', 'Only a native BACS final submission is authorized by this QA.');
    }
    return route.fallback();
  });
  const page = await context.newPage();
  page.setDefaultTimeout(18000); page.setDefaultNavigationTimeout(35000);
  const network = page.nativeNetwork = { pending: new Set(), last: Date.now() };
  page.on('request', request => { if (request.url().startsWith(base) && ['xhr', 'fetch', 'script'].includes(request.resourceType())) { network.pending.add(request); network.last = Date.now(); } });
  for (const event of ['requestfinished', 'requestfailed']) page.on(event, request => { if (network.pending.delete(request)) network.last = Date.now(); });
  page.on('pageerror', error => {
    const entry = { check: active, error: safe(error.message) };
    if (/Unable to load melidata script/i.test(error.message)) report.blockedDependencyErrors.push({ ...entry, reason: 'External Mercado Pago telemetry was intentionally blocked by this local-only BACS suite.' });
    else report.errors.push(entry);
  });
  page.on('response', response => { if (response.url().startsWith(base) && response.status() >= 400 && /\.(?:css|js|webp|woff2)(?:\?|$)/.test(response.url())) report.assetErrors.push({ check: active, path: new URL(response.url()).pathname, status: response.status() }); });
  return { context, page, api: cartAPI(context) };
}
function isFinal(request) {
  return request.method() === 'POST' && /(?:\/|%2F)wc(?:\/|%2F)store(?:\/|%2F)v1(?:\/|%2F)checkout(?:\?|&|$)/.test(request.url()) && new URL(request.url()).searchParams.get('__experimental_calc_totals') !== 'true';
}
function cartAPI(context) {
  let token;
  return {
    async call(method = 'get', route = 'cart', data) {
      if (method !== 'get') await this.call();
      const response = await context.request[method](base + '/index.php?rest_route=/wc/store/v1/' + route, { timeout: 20000, headers: token && method !== 'get' ? { 'Cart-Token': token } : {}, ...(data ? { data } : {}) });
      token = response.headers()['cart-token'] || token;
      const body = await response.json();
      assert.ok(response.ok(), 'Native cart API failed: ' + route + ' (' + response.status() + ').');
      return body;
    },
    async clear() {
      const cart = await this.call();
      for (const coupon of cart.coupons) await this.call('post', 'cart/remove-coupon', { code: coupon.code });
      await this.call('delete', 'cart/items');
      assert.equal((await this.call()).items_count, 0, 'Only the owned guest cart is cleared.');
    }
  };
}
async function settle(page) {
  const net = page.nativeNetwork, end = Date.now() + 28000;
  while (net.pending.size || Date.now() - net.last < 350) {
    assert.ok(Date.now() < end, 'Native checkout requests did not settle.');
    await page.waitForTimeout(70);
  }
  await page.waitForFunction(() => {
    const wc = window.wc?.wcBlocksData, data = window.wp?.data;
    const checkout = wc?.checkoutStore && data?.select(wc.checkoutStore);
    const cart = wc?.cartStore && data?.select(wc.cartStore);
    return (!checkout || !checkout.isCalculating()) && (!cart || (!cart.getItemsPendingQuantityUpdate().length && !cart.getItemsPendingDelete().length && !cart.getProductsPendingAdd().length));
  });
}
async function capture(page, name) {
  await page.evaluate(async () => {
    await document.fonts.ready;
    for (let y = 0; y < document.body.scrollHeight; y += 850) { scrollTo({ top: y, behavior: 'instant' }); await new Promise(r => setTimeout(r, 15)); }
    await Promise.race([Promise.all([...document.images].map(i => i.decode().catch(() => {}))), new Promise(r => setTimeout(r, 800))]);
    document.activeElement?.blur(); scrollTo({ top: 0, behavior: 'instant' });
  });
  const file = name + '.png'; await page.screenshot({ path: path.join(output, file), fullPage: true }); report.captures.push(file);
}
async function go(page, query) { await page.goto(base + '/' + query, { waitUntil: 'domcontentloaded' }); await page.locator('main').waitFor(); await settle(page); }
async function setup(session, fixture, items, coupon) {
  // A real native add creates only this new context's navigable Woo session.
  await go(session.page, '?product=bolsa-diario');
  await Promise.all([session.page.waitForNavigation({ waitUntil: 'domcontentloaded' }), session.page.locator('.single_add_to_cart_button').click()]);
  await settle(session.page); await session.api.clear();
  for (const [product, quantity] of items) await session.api.call('post', 'cart/add-item', { id: product.id, quantity });
  await session.api.call('post', 'cart/update-customer', { shipping_address: address, billing_address: { ...address, email, phone: '1100000000' } });
  if (coupon) await session.api.call('post', 'cart/apply-coupon', { code: coupon });
  await go(session.page, '?page_id=9'); await session.page.locator('#email').waitFor();
}
async function form(page) {
  await page.locator('#email').fill(email);
  for (const prefix of ['shipping', 'billing']) {
    const fieldset = page.locator('#' + prefix + '-fields');
    const edit = fieldset.getByRole('button', { name: /editar/i });
    if (await edit.isVisible().catch(() => false)) await edit.click();
    const country = page.locator('#' + prefix + '-country');
    if (await country.isVisible().catch(() => false)) await country.selectOption('AR');
    for (const [key, value] of Object.entries({ ...address, phone: '1100000000' })) {
      if (key === 'country') continue;
      const field = page.locator('#' + prefix + '-' + key);
      if (!await field.isVisible().catch(() => false)) continue;
      if (await field.evaluate(n => n.tagName === 'SELECT')) await field.selectOption(value); else await field.fill(value);
    }
  }
  await page.locator('#email').press('Tab'); await settle(page);
  const bacs = page.locator('input[type="radio"][value="bacs"]');
  await bacs.waitFor(); await bacs.focus(); await page.keyboard.press('Space'); await settle(page);
  assert.ok(await bacs.isChecked(), 'Native BACS radio selected with keyboard.');
  const payment = page.locator('.wp-block-woocommerce-checkout-payment-block');
  assert.match(await payment.innerText(), /Transferencia bancaria/);
  assert.match(await payment.innerText(), /verificaremos el pago/i);
  assert.equal(await page.locator('input[name="createaccount"][required]').count(), 0, 'Guest does not require an account.');
  const focus = await bacs.evaluate(n => { const s = getComputedStyle(n); return { active: document.activeElement === n, visible: n.matches(':focus-visible'), outline: s.outlineStyle, outlineWidth: s.outlineWidth, shadow: s.boxShadow, labels: [...n.labels].map(l => l.textContent.trim()) }; });
  assert.ok(focus.labels.some(Boolean), 'BACS native radio has an accessible label.');
  assert.ok(focus.active && focus.visible && ((focus.outline !== 'none' && parseFloat(focus.outlineWidth) > 0) || focus.shadow !== 'none'), 'BACS has visible keyboard focus.');
  return focus;
}
async function pickup(page) {
  const block = page.locator('.wp-block-woocommerce-checkout-shipping-method-block');
  let target = block.getByRole('radio', { name: /retiro|recoger|pickup/i }).first();
  if (!await target.count()) target = block.getByRole('button', { name: /retiro|recoger|pickup/i }).first();
  await target.focus(); await page.keyboard.press('Space'); await settle(page);
  const location = page.locator('.wp-block-woocommerce-checkout-pickup-options-block input[type="radio"]').first();
  if (await location.count()) { await location.focus(); await page.keyboard.press('Space'); await settle(page); }
}
const rates = cart => cart.shipping_rates.flatMap(p => p.shipping_rates);
function cartSummary(cart) { return { items: cart.items.map(i => ({ id: i.id, name: i.name, quantity: i.quantity, variation: i.variation, prices: i.prices, totals: i.totals })), totals: cart.totals, coupons: cart.coupons, selectedRates: rates(cart).filter(r => r.selected).map(r => ({ id: r.rate_id, method: r.method_id, price: r.price })) }; }
function assertCart(cart, scenario) {
  assert.equal(cart.totals.currency_code, 'ARS');
  assert.equal(Number(cart.totals.total_price), scenario.total * 100, 'Authoritative cart total.');
  assert.equal(Number(cart.totals.total_shipping), scenario.shipping * 100, 'Shipping is charged once.');
  assert.ok(rates(cart).some(r => r.method_id === scenario.rate && r.selected), 'Correct native shipping selection.');
  assert.ok(cart.items.every(i => Number(i.prices.price) < Number(i.prices.regular_price)), 'Actual fixture sale prices survive checkout.');
}
function assertOrder(order, cart, expectedStatus = 'on-hold') {
  assert.ok(order, 'Created order exists via WooCommerce CRUD/HPOS.');
  assert.equal(order.status, expectedStatus);
  assert.equal(order.payment_method, 'bacs');
  assert.equal(order.is_paid, false, 'BACS on hold is not paid.');
  assert.ok(!order.date_paid && !order.has_date_paid, 'BACS has no payment date.');
  assert.ok(!order.has_transaction_id, 'Native BACS creates no financial transaction identifier.');
  assert.equal(order.created_via, 'store-api', 'Native Checkout Blocks creates the order.');
  assert.equal(Number(order.customer_id), 0, 'The order remains a guest purchase.');
  assert.equal(order.currency, 'ARS');
  assert.match(order.data_store, /OrdersTableDataStore/, 'Order uses the HPOS data store.');
  assert.equal(order.stock_reduced, true, 'Native BACS reduces stock while it waits for manual payment verification.');
  assert.equal(Number(order.total), Number(cart.totals.total_price) / 100);
  assert.equal(Number(order.shipping_total ?? order.shipping), Number(cart.totals.total_shipping) / 100);
  assert.equal(Number(order.subtotal), Number(cart.totals.total_items) / 100, 'Native subtotal preserves sale prices.');
  assert.equal(Number(order.discount), Number(cart.totals.total_discount) / 100, 'Order preserves the exact coupon discount.');
  const items = order.items || order.line_items;
  assert.equal(items.length, cart.items.length);
  assert.equal(items.reduce((sum, i) => sum + Number(i.quantity), 0), cart.items.reduce((sum, i) => sum + i.quantity, 0));
  for (const item of cart.items) {
    const found = items.find(row => Number(row.variation_id || row.product_id || row.id) === Number(item.id));
    assert.ok(found, 'Exact simple product or variation is preserved.');
    assert.equal(Number(found.quantity), item.quantity);
    if (found.total !== undefined) assert.equal(Number(found.total), Number(item.totals.line_total) / 100);
  }
  assert.ok(order.notes_count || (order.notes || []).length, 'Native order notes exist.');
}
async function received(page) {
  await page.waitForURL(url => url.searchParams.has('order-received') || /\/order-received\//.test(url.pathname), { timeout: 45000 });
  await page.locator('main').waitFor(); await settle(page);
  const text = await page.locator('main').innerText();
  assert.match(text, /Pedido recibido/i);
  assert.match(text, /DEMO|DEMOSTRACI[ÓO]N/i, 'Instructions unmistakably identify the demo.');
  assert.match(text, /No realizar transferencias reales/i);
  assert.doesNotMatch(text, /Gracias por tu pago|pago (?:confirmado|acreditado|recibido)/i, 'Unpaid transfer is not represented as paid.');
  return { title: await page.locator('h1').first().innerText().catch(() => ''), text };
}
async function submit(session, mode) {
  const { page } = session;
  const finalResponses = [], submitted = [];
  const responseListener = async response => { if (isFinal(response.request())) finalResponses.push(await response.json().catch(() => ({}))); };
  page.on('response', responseListener);
  const route = async route => {
    const request = route.request();
    if (!isFinal(request)) return route.fallback();
    submitted.push(request.postDataJSON().payment_method);
    if (mode === 'double') await new Promise(resolve => setTimeout(resolve, 700));
    return route.fallback();
  };
  await page.route('**/*', route);
  const cta = page.locator('.wc-block-components-checkout-place-order-button');
  try {
    if (mode === 'double') await cta.dblclick({ delay: 60, noWaitAfter: true }); else await cta.click({ noWaitAfter: true });
    const screen = await received(page);
    const end = Date.now() + 10000;
    while (!finalResponses.length && Date.now() < end) await page.waitForTimeout(70);
    assert.equal(finalResponses.length, 1, 'Exactly one successful final native checkout response.');
    const result = finalResponses[0];
    assert.equal(result.payment_method, 'bacs');
    assert.equal(result.status, 'on-hold');
    assert.ok(result.order_id);
    assert.equal(submitted.length, 1, 'Duplicate UI activation does not submit another checkout.');
    report.finalCheckoutRequests.push({ check: active, count: submitted.length, method: 'bacs', orderId: result.order_id, status: result.status });
    const privateOrder = { id: result.order_id, key: result.order_key, url: page.url() };
    privateOrders.push(privateOrder);
    return { id: result.order_id, privateOrder, screen };
  } finally { await page.unroute('**/*', route); page.off('response', responseListener); }
}
async function geometry(page, kind) {
  const evidence = await page.evaluate(kind => {
    const root = document.querySelector(kind === 'checkout' ? '.wp-block-woocommerce-checkout' : 'main');
    const visible = n => !!n.getClientRects().length && getComputedStyle(n).visibility !== 'hidden' && !n.closest('[hidden],[aria-hidden="true"],.screen-reader-text');
    const options = [...root.querySelectorAll('.wc-block-components-radio-control-accordion-option,.wc-block-components-radio-control__option')].filter(visible).map(n => ({ text: n.textContent.trim().slice(0, 220), rect: n.getBoundingClientRect().toJSON(), overflow: n.scrollWidth > n.clientWidth + 2, radios: [...n.querySelectorAll('input[type="radio"]')].map(r => ({ label: [...r.labels].map(l => l.textContent.trim()).join(' '), rect: r.getBoundingClientRect().toJSON(), checked: r.checked })) }));
    const cta = root.querySelector('.wc-block-components-checkout-place-order-button');
    return { kind, width: innerWidth, scrollWidth: document.documentElement.scrollWidth, theme: document.documentElement.dataset.theme, options, cta: cta?.getBoundingClientRect().toJSON(), notices: [...root.querySelectorAll('[aria-live],[role="alert"],[role="status"]')].map(n => ({ live: n.getAttribute('aria-live'), role: n.getAttribute('role'), text: n.textContent.trim().slice(0, 150) })) };
  }, kind);
  assert.ok(evidence.scrollWidth <= evidence.width + 1, 'No horizontal page overflow.');
  for (const option of evidence.options) {
    assert.ok(option.rect.x >= -1 && option.rect.right <= evidence.width + 1 && !option.overflow, 'Native option remains visible within viewport.');
    assert.ok(option.rect.height >= 43.5, 'Native option touch target is at least 44px.');
    for (const radio of option.radios) { assert.ok(radio.label, 'Payment radio accessible name.'); assert.ok(radio.rect.width >= 16 && radio.rect.height >= 16, 'Radio remains visible.'); }
  }
  if (evidence.cta) assert.ok(evidence.cta.x >= -1 && evidence.cta.right <= evidence.width + 1 && evidence.cta.height >= 44, 'Order CTA remains usable.');
  return evidence;
}
async function failureBeforeServer(session, mode) {
  const { page, api } = session;
  const before = inspect(), beforeIds = finalOrderIds(before), cart = await api.call();
  let intercepted = 0, release, seen;
  const requested = new Promise(resolve => { seen = resolve; });
  const hold = new Promise(resolve => { release = resolve; });
  const route = async route => {
    if (!isFinal(route.request())) return route.fallback();
    assert.equal(new URL(route.request().url()).origin, base, 'The forwarded checkout request must be local.');
    ++intercepted; seen();
    if (mode === 'refresh') await hold;
    return route.abort('internetdisconnected').catch(() => {});
  };
  await page.route('**/*', route);
  try {
    await page.locator('.wc-block-components-checkout-place-order-button').click({ noWaitAfter: true }); await requested;
    if (mode === 'refresh') { const navigation = page.reload({ waitUntil: 'domcontentloaded' }); release(); await navigation; await settle(page); }
    else {
      await page.locator('.wc-block-components-notice-banner.is-error,.wc-block-components-validation-error').first().waitFor();
      await capture(page, 'connection-error');
    }
  } finally { release(); await page.unroute('**/*', route); }
  assert.equal(intercepted, 1, 'Exactly one attempted submission was aborted before reaching PHP.');
  const after = inspect();
  assert.deepEqual(finalOrderIds(after), beforeIds, 'Connection loss before server delivery cannot create a finalized order.');
  assert.deepEqual(orderStock(after), orderStock(before), 'Aborted-before-server request does not change stock.');
  assert.equal((await api.call()).items_count, cart.items_count, 'Owned cart survives temporary connection failure/refresh.');
  if (mode !== 'refresh') { await page.reload({ waitUntil: 'domcontentloaded' }); await settle(page); }
  await form(page);
  return { attempted: intercepted, noFinalOrderBeforeRetry: true, cartPreserved: true, simulation: 'Browser abort before PHP; does not claim an ambiguous delivered request was retried safely.' };
}
async function refreshAfterServerCommit(session, fixture) {
  const { page, api } = session;
  await setup(session, fixture, [[fixture.variable, 1]]); await form(page);
  const cart = await api.call(); assertCart(cart, { total: 56500, shipping: 4500, rate: 'flat_rate' });
  const before = inspect();
  let delivered, release, body, submissions = 0;
  const committed = new Promise(resolve => { delivered = resolve; });
  const gate = new Promise(resolve => { release = resolve; });
  const route = async route => {
    if (!isFinal(route.request())) return route.fallback();
    assert.equal(new URL(route.request().url()).origin, base, 'The forwarded checkout request must be local.');
    assert.equal(route.request().postDataJSON().payment_method, 'bacs');
    ++submissions;
    // Forward this exact native request once to PHP. The returned response is
    // deliberately withheld from the page; no payment provider is contacted.
    const response = await route.fetch({ maxRedirects: 0 });
    assert.ok(response.ok(), 'Real native BACS POST reached PHP successfully.');
    body = await response.json(); delivered();
    await gate;
    return route.abort('internetdisconnected').catch(() => {});
  };
  await page.route('**/*', route);
  try {
    await page.locator('.wc-block-components-checkout-place-order-button').click({ noWaitAfter: true });
    let timeout;
    try { await Promise.race([committed, new Promise((_, reject) => { timeout = setTimeout(() => reject(new Error('Server commitment did not return in time.')), 35000); })]); }
    finally { clearTimeout(timeout); }
    assert.equal(body.payment_method, 'bacs'); assert.equal(body.status, 'on-hold'); assert.ok(body.order_id);
    const reload = page.reload({ waitUntil: 'domcontentloaded' }); release(); await reload; await settle(page);
  } finally { release(); await page.unroute('**/*', route); }
  const after = inspect(), newIds = finalOrderIds(after).filter(id => !finalOrderIds(before).includes(id));
  assert.equal(submissions, 1, 'The native request was forwarded exactly once before losing its response.');
  assert.deepEqual(newIds, [Number(body.order_id)], 'Refresh after server commitment created exactly one final order.');
  assertOrder(orderById(after, body.order_id), cart);
  const stockBefore = inventory(before), stockAfter = inventory(after);
  for (const item of cart.items) assert.equal(stockAfter[item.id], stockBefore[item.id] - item.quantity, 'Response loss/refresh cannot reduce stock twice.');
  assert.equal((await api.call()).items_count, 0, 'Native server cleared the cart despite the lost browser response.');
  assert.equal(await page.locator('.wc-block-components-checkout-place-order-button').count(), 0, 'Reload cannot resubmit the emptied native checkout.');
  const url = body.payment_result?.redirect_url;
  assert.equal(new URL(url).origin, base, 'Native BACS redirect stays local.');
  privateOrders.push({ id: body.order_id, key: body.order_key, url });
  report.finalCheckoutRequests.push({ check: active, count: submissions, method: 'bacs', orderId: body.order_id, status: body.status, responseIntentionallyLostAfterServerCommit: true });
  await page.goto(url, { waitUntil: 'domcontentloaded' }); await received(page); await capture(page, 'received-response-lost-after-server-commit');
  await page.reload({ waitUntil: 'domcontentloaded' }); await received(page);
  const repeated = inspect(); assert.deepEqual(finalOrderIds(repeated), finalOrderIds(after)); assert.deepEqual(orderStock(repeated), orderStock(after));
  return { orderId: body.order_id, order: orderById(after, body.order_id), stockBefore: orderStock(before), stockAfter: orderStock(after), serverPosts: submissions, cartAfterRefresh: 0, automaticRetry: false, simulation: 'Actual local native BACS Store API POST committed in PHP; its response was withheld and aborted while refreshing. No Mercado Pago/payment provider call.' };
}
async function adminConfirm(orderId) {
  const config = fs.readFileSync(path.join(root, '.local/local/.env'), 'utf8');
  const user = config.match(/^LOCAL_ADMIN_USER=(.+)$/m)?.[1].trim();
  const password = fs.readFileSync(path.join(root, '.local/local/secrets/wp-admin-password.txt'), 'utf8').trim();
  assert.ok(user && password, 'Local admin credentials are required, never printed.');
  const admin = await localContext();
  try {
    const initial = inspect(), order = orderById(initial, orderId);
    assert.equal(order?.payment_method, 'bacs'); assert.equal(order.status, 'on-hold');
    await admin.page.goto(base + '/wp-login.php');
    await admin.page.locator('#user_login').fill(user); await admin.page.locator('#user_pass').fill(password);
    await Promise.all([admin.page.waitForNavigation({ waitUntil: 'domcontentloaded' }), admin.page.locator('#wp-submit').click()]);
    await admin.page.goto(base + '/wp-admin/admin.php?page=wc-orders&action=edit&id=' + orderId, { waitUntil: 'domcontentloaded' });
    const status = admin.page.locator('#order_status'); await status.waitFor(); await status.selectOption('wc-processing');
    const update = admin.page.locator('button.save_order,button[name="save"],input[name="save"]').first();
    await Promise.all([admin.page.waitForNavigation({ waitUntil: 'domcontentloaded' }), update.click()]);
    let current = inspect(), confirmed = orderById(current, orderId);
    assert.equal(confirmed.status, 'processing'); assert.equal(confirmed.is_paid, true);
    assert.deepEqual(orderStock(current), orderStock(initial), 'Manual confirmation does not reduce stock again.');
    const stock = orderStock(current);
    await Promise.all([admin.page.waitForNavigation({ waitUntil: 'domcontentloaded' }), update.click()]);
    current = inspect(); confirmed = orderById(current, orderId);
    assert.equal(confirmed.status, 'processing'); assert.deepEqual(orderStock(current), stock, 'Saving an unchanged paid status is idempotent for stock.');
    await capture(admin.page, 'admin-owned-qa-order-confirmed');
    return { orderId, via: 'Native HPOS wp-admin order editor in a fresh admin context', status: confirmed.status, isPaid: confirmed.is_paid, repeatedSaveStockUnchanged: true, moneyTransferred: false };
  } finally { await admin.context.close(); }
}
async function confirmedReceipt(session, record) {
  const order = orderById(inspect(), record.id);
  assert.equal(order.status, 'processing'); assert.equal(order.is_paid, true); assert.ok(order.date_paid || order.has_date_paid);
  const matrix = [];
  for (const theme of ['light', 'dark']) for (const width of (process.env.H71_RECEIVED_WIDTHS || '390,1366').split(',').map(Number)) {
    await session.page.emulateMedia({ colorScheme: theme }); await session.page.setViewportSize({ width, height: 1000 });
    await session.page.goto(record.url, { waitUntil: 'domcontentloaded' }); await session.page.locator('main').waitFor(); await settle(session.page);
    const text = await session.page.locator('main').innerText();
    assert.match(text, /confirmamos el pago/i, 'Receipt uses the actual manually confirmed payment state.');
    assert.doesNotMatch(text, /pago.{0,50}pendiente/i, 'Instructions do not contradict the confirmed state.');
    assert.match(text, /No realizar transferencias reales/i);
    matrix.push(await geometry(session.page, 'received'));
    await capture(session.page, `received-manually-confirmed-${theme}-${width}`);
  }
  return { orderId: record.id, status: order.status, paidCopyMatchesNativeState: true, noPendingCopyContradiction: true, matrix };
}

(async () => {
  try {
    assert.ok(fs.existsSync(fixtureFile), 'Prepare H7.1 owned QA fixtures first.');
    const prepared = JSON.parse(fs.readFileSync(fixtureFile, 'utf8'));
    const fixture = prepared.fixtures; assert.ok(fixture?.simple?.id && fixture?.variable?.id && fixture?.coupon?.code);
    browser = await playwright.chromium.launch({ headless: true });
    const initial = inspect(); report.initial = { orderIds: finalOrderIds(initial), stock: orderStock(initial) };
    if (!['matrix', 'post-admin', 'response-loss'].includes(process.env.H71_SCOPE) && !process.env.H71_SCENARIO) {
      const stock = inventory(initial);
      assert.equal(stock[fixture.simple.id], 6, 'Cancel prior owned BACS QA orders natively before a full rerun; simple stock must start at six.');
      assert.ok(stock[fixture.variable.id] >= 3, 'At least three native variation units are required; restore only by cancelling prior owned QA orders.');
    }
    if (process.env.H71_SCOPE === 'post-admin') {
      const record = privateOrders.find(p => orderById(initial, p.id)?.status === 'processing' && orderById(initial, p.id)?.is_paid);
      assert.ok(record, 'A manually confirmed owned BACS test order is required.');
      const session = await localContext();
      try { await check('native-admin/confirmed-receipt-copy', () => confirmedReceipt(session, record), session.page); }
      finally { await session.context.close(); }
      return;
    }
    if (process.env.H71_SCOPE === 'response-loss') {
      const session = await localContext();
      try { await check('bacs/refresh-after-server-commit-response-lost', () => refreshAfterServerCommit(session, fixture), session.page); }
      finally { await session.api.clear(); await session.context.close(); }
      report.final = { orderIds: finalOrderIds(inspect()), stock: orderStock(inspect()) };
      return;
    }
    if (process.env.H71_SCOPE !== 'matrix') {
      const scenarios = [
        { name: 'simple-flat-double-click', items: [[fixture.simple, 1]], total: 54500, shipping: 4500, rate: 'flat_rate', mode: 'double' },
        { name: 'exact-free-shipping', items: [[fixture.simple, 2]], total: 100000, shipping: 0, rate: 'free_shipping' },
        { name: 'coupon-removes-free-shipping', items: [[fixture.simple, 2]], coupon: fixture.coupon.code, total: 94500, shipping: 4500, rate: 'flat_rate' },
        { name: 'variation-free-pickup', items: [[fixture.variable, 1]], total: 52000, shipping: 0, rate: 'pickup_location', pickup: true },
        { name: 'connection-loss-before-server-safe-retry-low-stock', items: [[fixture.simple, 1]], total: 54500, shipping: 4500, rate: 'flat_rate', failure: 'offline' },
        { name: 'refresh-before-server-safe-retry', items: [[fixture.variable, 1]], total: 56500, shipping: 4500, rate: 'flat_rate', failure: 'refresh' }
      ];
      for (const scenario of scenarios.filter(s => !process.env.H71_SCENARIO || new RegExp(process.env.H71_SCENARIO).test(s.name))) {
        const session = await localContext(); let keep = false;
        await check('bacs/' + scenario.name, async () => {
          await setup(session, fixture, scenario.items, scenario.coupon);
          if (scenario.pickup) await pickup(session.page);
          const focus = await form(session.page); const cart = await session.api.call(); assertCart(cart, scenario);
          const failures = scenario.failure ? await failureBeforeServer(session, scenario.failure) : null;
          const before = inspect();
          const result = await submit(session, scenario.mode); const after = inspect();
          const newIds = finalOrderIds(after).filter(id => !finalOrderIds(before).includes(id));
          assert.deepEqual(newIds, [Number(result.id)], 'Exactly one final native Woo order created.');
          const order = orderById(after, result.id); assertOrder(order, cart);
          const stockBefore = inventory(before), stockAfter = inventory(after);
          for (const item of cart.items) assert.equal(stockAfter[item.id], stockBefore[item.id] - item.quantity, 'The actual simple/variation stock decreases exactly once.');
          if (scenario.name.includes('low-stock')) {
            assert.equal(stockBefore[fixture.simple.id], 1, 'Low-stock checkout buys the last available unit.');
            assert.equal(stockAfter[fixture.simple.id], 0, 'The last BACS unit reduces available stock to zero while unpaid.');
          }
          await session.page.reload({ waitUntil: 'domcontentloaded' }); await received(session.page);
          const fake = new URL(result.privateOrder.url); fake.searchParams.set('collection_status', 'approved'); fake.searchParams.set('status', 'approved'); fake.searchParams.set('payment_id', '1234567890');
          await session.page.goto(fake.href, { waitUntil: 'domcontentloaded' }); await received(session.page);
          await session.page.goBack({ waitUntil: 'domcontentloaded' }); await received(session.page);
          const repeated = inspect(); assertOrder(orderById(repeated, result.id), cart);
          assert.deepEqual(finalOrderIds(repeated), finalOrderIds(after), 'Reload/falsified return parameters cannot duplicate an order.');
          assert.deepEqual(orderStock(repeated), orderStock(after), 'Reload/falsified approved parameters do not reduce stock again.');
          await capture(session.page, 'received-' + scenario.name);
          assert.equal((await session.api.call()).items_count, 0, 'Successful native BACS checkout empties only its owned cart.');
          if (scenario.name === 'simple-flat-double-click') { matrixSession = session; keep = true; matrixSession.receivedOrder = result.privateOrder; }
          return { orderId: result.id, cart: cartSummary(cart), order, stockBefore: orderStock(before), stockAfter: orderStock(after), focus, failures, refreshBackAndFalsifiedParametersPreserveUnpaidOrder: true };
        }, session.page);
        if (!keep) { await session.api.clear().catch(() => {}); await session.context.close(); }
      }
      if (matrixSession?.receivedOrder) {
        const outsider = await localContext();
        await check('security/invalid-key-and-forged-approved-in-isolated-guest', async () => {
          const before = inspect(), invalid = new URL(matrixSession.receivedOrder.url);
          invalid.searchParams.set('key', 'wc_order_invalid_h71_qa'); invalid.searchParams.set('status', 'approved'); invalid.searchParams.set('collection_status', 'approved');
          await outsider.page.goto(invalid.href, { waitUntil: 'domcontentloaded' }); await outsider.page.locator('main').waitFor(); await settle(outsider.page);
          const text = await outsider.page.locator('main').innerText();
          assert.ok(!text.includes(email) && !text.includes(address.address_1) && !text.includes('Prueba H7.1 — producto simple'), 'An invalid order key cannot reveal private order details to another guest.');
          const after = inspect();
          assert.equal(orderById(after, matrixSession.receivedOrder.id).status, 'on-hold');
          assert.equal(orderById(after, matrixSession.receivedOrder.id).is_paid, false);
          assert.deepEqual(finalOrderIds(after), finalOrderIds(before)); assert.deepEqual(orderStock(after), orderStock(before));
          await capture(outsider.page, 'invalid-order-key'); return { orderId: matrixSession.receivedOrder.id, privateDetailsHidden: true, noPaymentNoStockNoDuplicateEffect: true };
        }, outsider.page);
        await outsider.context.close();
      }
      if (!process.env.H71_SCENARIO) {
        const responseLoss = await localContext();
        await check('bacs/refresh-after-server-commit-response-lost', () => refreshAfterServerCommit(responseLoss, fixture), responseLoss.page);
        await responseLoss.api.clear().catch(() => {}); await responseLoss.context.close();
      }
    }
    if (process.env.H71_SCOPE !== 'flows') {
      const checkout = await localContext();
      await setup(checkout, fixture, [[fixture.variable, 1]]); await form(checkout.page);
      if (!matrixSession) {
        const current = inspect();
        const prior = privateOrders.find(p => orderById(current, p.id)?.status === 'on-hold');
        assert.ok(prior?.url, 'Run flows first to create a private native received-order URL.');
        matrixSession = await localContext(); matrixSession.receivedOrder = prior;
      }
      try {
        for (const theme of ['light', 'dark']) for (const width of widths) for (const kind of ['checkout', 'received']) {
          const page = kind === 'checkout' ? checkout.page : matrixSession.page;
          await check(`visual/${theme}/${width}/${kind}`, async () => {
            await page.emulateMedia({ colorScheme: theme }); await page.setViewportSize({ width, height: width >= 1920 ? 1080 : 900 });
            if (kind === 'received') { await page.goto(matrixSession.receivedOrder.url, { waitUntil: 'domcontentloaded' }); await received(page); }
            else { await go(page, '?page_id=9'); await form(page); }
            const evidence = await geometry(page, kind); assert.equal(evidence.theme, theme);
            await capture(page, `${kind}-${theme}-${width}`); return evidence;
          }, page);
        }
      } finally { await checkout.api.clear(); report.cleanup.push({ context: 'matrix-checkout', remainingItems: (await checkout.api.call()).items_count }); await checkout.context.close(); }
    }
    if (process.env.H71_SCOPE !== 'matrix' && process.env.H71_ADMIN !== '0' && matrixSession?.receivedOrder) {
      await check('native-admin/manual-confirmation-and-repeated-status-save', () => adminConfirm(matrixSession.receivedOrder.id));
      await check('native-admin/confirmed-receipt-copy', () => confirmedReceipt(matrixSession, matrixSession.receivedOrder), matrixSession.page);
    }
    report.final = { orderIds: finalOrderIds(inspect()), stock: orderStock(inspect()) };
  } catch (error) { report.checks.push({ name: 'setup-or-unhandled', result: 'failed', error: safe(error.stack) }); console.log('FAIL setup-or-unhandled', safe(error.message).slice(0, 260)); }
  finally {
    if (matrixSession) { await matrixSession.api.clear().catch(() => {}); await matrixSession.context.close(); }
    if (browser) await browser.close();
    report.finished = new Date().toISOString(); save();
    console.log(JSON.stringify({ passed: report.passed, failed: report.failed, jsErrors: report.errors.length, intentionallyBlockedTelemetryErrors: report.blockedDependencyErrors.length, assetErrors: report.assetErrors.length, finalBacsOrders: report.finalCheckoutRequests.length, externalNetworkBlocked: true, report: path.relative(root, reportFile) }));
    if (report.failed || report.errors.length || report.assetErrors.length) process.exitCode = 1;
  }
})();
