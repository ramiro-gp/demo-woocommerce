/** H6.2 — native Woo shipping, isolated guest carts, no checkout POST or payment. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
process.env.PLAYWRIGHT_BROWSERS_PATH ||= 'C:\\Users\\ramiro\\AppData\\Local\\ms-playwright';
const playwright = require('playwright');
const base = (process.env.H62_BASE_URL || 'http://localhost:8080').replace(/\/$/, '');
const output = path.resolve(__dirname, '../.local/local/h6-2/browser');
const widths = [320, 375, 390, 430, 768, 1024, 1366, 1440, 1920, 2560, 3440];
const coupon = 'h6-2-qa-10';
const ar = { first_name: 'Prueba', last_name: 'H6.2', address_1: 'Dirección de prueba 123', city: 'Buenos Aires', state: 'C', postcode: '1001', country: 'AR' };
const cl = { ...ar, city: 'Santiago', state: 'CL-RM', postcode: '8320000', country: 'CL' };
const report = { started: new Date().toISOString(), base, widths, checks: [], errors: [], assetErrors: [], dependencyErrors: [], nativeCheckoutRecalculations: [], nativeCheckoutResponses: [], forbiddenCheckoutRequests: [], captures: [], cleanup: [] };
let active = '';
fs.mkdirSync(output, { recursive: true });
function cancelled() { return fs.existsSync(path.join(output, 'cancel-' + (process.env.H62_REPORT || 'qa.json'))); }
function save() {
  report.passed = report.checks.filter(c => c.result === 'passed').length;
  report.failed = report.checks.filter(c => c.result === 'failed').length;
  fs.writeFileSync(path.join(output, process.env.H62_REPORT || 'qa.json'), JSON.stringify(report, null, 2) + '\n');
}
async function check(name, task, page) {
  if (cancelled()) throw new Error('QA cancellation requested; close and clean this owned guest context.');
  if (process.env.H62_CHECK && !new RegExp(process.env.H62_CHECK).test(name)) return;
  active = name;
  try { report.checks.push({ name, result: 'passed', evidence: await task() }); }
  catch (error) {
    console.log('FAIL', name, error.message.slice(0, 450));
    const diagnostic = page && !page.isClosed() ? await page.evaluate(() => {
      const data = window.wc?.wcBlocksData;
      const checkout = data && window.wp?.data?.select(data.checkoutStore);
      return { calculating: checkout?.isCalculating(), checkoutStatus: checkout?.getCheckoutStatus(), nativeErrors: [...document.querySelectorAll('.wc-block-components-notice-banner,.wc-block-components-validation-error')].map(n => n.textContent.trim()), footer: [...document.querySelectorAll('.wc-block-components-totals-footer-item')].map(n => ({ text: n.textContent.trim(), amounts: n.querySelectorAll('.wc-block-formatted-money-amount').length, skeletons: n.querySelectorAll('.wc-block-components-skeleton__element').length })), delivery: [...document.querySelectorAll('.wc-block-checkout__shipping-method-option')].map(n => ({ label: n.textContent.trim(), checked: n.getAttribute('aria-checked') })) };
    }).catch(() => null) : null;
    report.checks.push({ name, result: 'failed', error: error.message, stack: error.stack, diagnostic, pendingNativeRequests: [...(page?.nativeNetwork?.pending || [])].map(r => ({ url: r.url(), method: r.method(), body: r.postData() })), lastNativeWrites: (page?.nativeNetwork?.writes || []).slice(-6) });
    if (page && !page.isClosed()) await capture(page, 'failure-' + name.replace(/[^a-z0-9-]/gi, '-')).catch(() => {});
    if (/Native .*did not settle|QA cancellation requested/.test(error.message)) {
      save();
      if (page && !page.isClosed()) await page.close().catch(() => {});
      throw error;
    }
  }
  save();
}
function watch(page, engine) {
  page.setDefaultTimeout(15000); page.setDefaultNavigationTimeout(30000);
  const net = page.nativeNetwork = { pending: new Set(), last: Date.now(), writes: [] };
  page.on('request', r => {
    if (r.url().startsWith(base) && ['xhr', 'fetch', 'script'].includes(r.resourceType())) { net.pending.add(r); net.last = Date.now(); }
    if (r.method() === 'POST' && /(?:\/|%2F)wc(?:\/|%2F)store(?:\/|%2F)v1/.test(r.url())) net.writes.push({ url: r.url(), body: r.postData() });
    if (r.method() === 'POST' && /(?:\/|%2F)checkout(?:\?|&|$)/.test(r.url())) {
      const evidence = { engine, check: active, url: r.url(), body: r.postData() };
      // Blocks recalculates billing/pickup taxes through this official endpoint;
      // no final checkout activation is performed by this suite.
      if (new URL(r.url()).searchParams.get('__experimental_calc_totals') === 'true') report.nativeCheckoutRecalculations.push(evidence);
      else report.forbiddenCheckoutRequests.push(evidence);
    }
  });
  for (const event of ['requestfinished', 'requestfailed']) page.on(event, r => { if (net.pending.delete(r)) net.last = Date.now(); });
  page.on('requestfailed', r => { if (/melidata|mercadopago/i.test(r.url())) report.dependencyErrors.push({ engine, check: active, url: r.url(), error: r.failure()?.errorText }); });
  page.on('pageerror', e => report.errors.push({ engine, check: active, url: page.url(), error: e.message }));
  page.on('response', r => { if (r.url().startsWith(base) && r.status() >= 400 && /\.(?:css|js|webp|woff2)(?:\?|$)/.test(r.url())) report.assetErrors.push({ engine, url: r.url(), status: r.status() }); });
  page.on('response', r => { if (r.status() >= 400 && /melidata|mercadopago/i.test(r.url())) report.dependencyErrors.push({ engine, check: active, url: r.url(), status: r.status() }); });
  page.on('response', async r => {
    if (r.request().method() === 'POST' && new URL(r.url()).searchParams.get('__experimental_calc_totals') === 'true') {
      const body = await r.json().catch(() => ({}));
      report.nativeCheckoutResponses.push({ engine, check: active, status: r.status(), orderId: body.order_id, orderStatus: body.status, paymentMethod: body.payment_method, billingEmail: body.billing_address?.email });
    }
  });
}
async function settle(page) {
  const net = page.nativeNetwork, deadline = Date.now() + 22000;
  while (net && (net.pending.size || Date.now() - net.last < 400)) {
    if (cancelled()) throw new Error('QA cancellation requested; clean owned context.');
    assert.ok(Date.now() < deadline, 'Native requests did not settle: ' + [...net.pending].map(r => r.url()).join(', '));
    await page.waitForTimeout(70);
  }
  await page.waitForFunction(() => {
    const cart = window.wc?.wcBlocksData?.cartStore;
    const s = cart && window.wp?.data?.select(cart);
    return !s || (!s.getItemsPendingQuantityUpdate().length && !s.getItemsPendingDelete().length && !s.getProductsPendingAdd().length);
  });
}
async function go(page, kind) {
  if (page.url().startsWith(base)) await settle(page);
  const query = { cart: '?page_id=8', checkout: '?page_id=9' }[kind] ?? kind;
  await page.goto(base + '/' + query, { waitUntil: 'domcontentloaded' });
  await page.locator('main').waitFor(); await page.evaluate(() => document.fonts.ready); await settle(page);
  if (kind === 'cart') {
    await page.locator('.wc-block-cart-items__row input[type="number"]').first().waitFor();
    await page.locator('.wc-block-components-totals-footer-item .wc-block-formatted-money-amount').waitFor();
  }
  if (kind === 'checkout') {
    await page.locator('#email').waitFor();
    await page.locator('.wc-block-components-order-summary-item .wc-block-components-product-name').first().waitFor();
    await page.waitForFunction(() => {
      const store = window.wc?.wcBlocksData?.checkoutStore;
      const s = store && window.wp?.data?.select(store);
      return !s || !s.isCalculating();
    });
  }
  await settle(page);
}
async function capture(page, name) {
  await page.evaluate(async () => {
    for (let y = 0; y < document.body.scrollHeight; y += 850) { window.scrollTo({ top: y, behavior: 'instant' }); await new Promise(r => setTimeout(r, 20)); }
    await Promise.race([Promise.all([...document.images].map(i => i.decode().catch(() => {}))), new Promise(r => setTimeout(r, 1000))]);
    document.activeElement?.blur(); window.scrollTo({ top: 0, behavior: 'instant' });
  });
  const file = (process.env.H62_CAPTURE_PREFIX || '') + name + '.png';
  await page.screenshot({ path: path.join(output, file), fullPage: true }); report.captures.push(file);
}
function cartAPI(context) {
  let token;
  const url = route => base + '/index.php?rest_route=/wc/store/v1/' + route;
  return {
    async call(method = 'get', route = 'cart', data, success = true) {
      if (method !== 'get') await this.call();
      const r = await context.request[method](url(route), { timeout: 15000, headers: token && method !== 'get' ? { 'Cart-Token': token } : {}, ...(data ? { data } : {}) });
      token = r.headers()['cart-token'] || token; const body = await r.json();
      if (success) assert.ok(r.ok(), method + ' ' + route + ': ' + JSON.stringify(body));
      return { response: r, body };
    },
    async data() { return (await this.call()).body; },
    async clear() {
      const current = await this.data();
      for (const c of current.coupons) await this.call('post', 'cart/remove-coupon', { code: c.code });
      await this.call('delete', 'cart/items');
      assert.equal((await this.data()).items_count, 0, 'Isolated guest cart cleaned');
    }
  };
}
const rates = cart => cart.shipping_rates.flatMap(p => p.shipping_rates);
const deliveryRates = cart => rates(cart).filter(r => !['pickup_location', 'local_pickup'].includes(r.method_id));
const compact = cart => ({ items: cart.items.map(i => ({ id: i.id, name: i.name, quantity: i.quantity })), destination: cart.shipping_address, totals: cart.totals, rates: rates(cart).map(r => ({ id: r.rate_id, method: r.method_id, name: r.name, price: r.price, selected: r.selected })) });
function assertShipping(cart, method, subtotal) {
  const native = deliveryRates(cart);
  if (subtotal !== undefined) assert.equal(Number(cart.totals.total_items), subtotal * 100, 'Real product subtotal');
  assert.equal(native.length, method === 'none' ? 0 : 1, 'Only commercially relevant delivery method');
  if (method !== 'none') {
    assert.equal(native[0].method_id, method);
    assert.equal(Number(native[0].price), method === 'flat_rate' ? 450000 : 0);
    assert.ok(native[0].selected, 'Available native delivery method selected');
  }
  if (method === 'free_shipping') assert.ok(!rates(cart).some(r => r.method_id === 'flat_rate'), 'Core hides paid delivery once free is available');
}
async function waitCart(page, api, predicate, label) {
  await settle(page); const deadline = Date.now() + 16000; let cart;
  do { cart = await api.data(); if (predicate(cart)) return cart; await page.waitForTimeout(120); } while (Date.now() < deadline);
  assert.fail(label + ': ' + JSON.stringify(compact(cart)));
}
async function setCart(api, products, items, address = ar) {
  await api.clear();
  for (const [slug, quantity] of items) { assert.ok(products[slug], 'Fixture product exists: ' + slug); await api.call('post', 'cart/add-item', { id: products[slug].id, quantity }); }
  await api.call('post', 'cart/update-customer', { shipping_address: address, billing_address: { ...ar, email: 'h6-2-qa@example.test' } });
  return api.data();
}
async function initialize(page, api) {
  // Cart-Token alone does not set a navigable Woo cookie. Bootstrap through a
  // real native add, then keep only this BrowserContext's guest session.
  await go(page, '?product=bolsa-diario');
  await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.locator('.single_add_to_cart_button').click()]);
  await settle(page); await api.clear();
}
async function chooseMode(page, pickup) {
  const block = page.locator('.wp-block-woocommerce-checkout-shipping-method-block');
  const candidates = block.getByRole('radio', { name: pickup ? /retiro|recoger|pickup/i : /enviar|env[ií]o|shipping/i });
  let target = candidates.first();
  if (!await candidates.count()) target = block.getByRole('button', { name: pickup ? /retiro|recoger|pickup/i : /enviar|env[ií]o|shipping/i }).first();
  await target.waitFor(); await target.focus(); await page.keyboard.press('Space'); await settle(page);
  if (await target.getAttribute('role') === 'radio') assert.equal(await target.getAttribute('aria-checked'), 'true', 'Requested native delivery/pickup mode is selected');
  try {
    await page.waitForFunction(() => {
      const s = window.wp?.data?.select(window.wc?.wcBlocksData?.checkoutStore);
      return !s || !s.isCalculating();
    }, undefined, { timeout: 15000 });
    // Woo's ShowSkeleton keeps the placeholder for at least 2000ms, even after
    // store/network loading flags clear. Wait for the actual native amount.
    await page.locator('.wc-block-components-totals-footer-item .wc-block-formatted-money-amount').first().waitFor();
    await settle(page);
  } catch (error) { throw new Error('Native checkout calculation did not settle after delivery-mode change: ' + error.message); }
}
async function pickup(page, api) {
  await chooseMode(page, true);
  const locations = page.locator('.wp-block-woocommerce-checkout-pickup-options-block input[type="radio"]');
  if (await locations.count()) { await locations.first().focus(); await page.keyboard.press('Space'); await settle(page); }
  const cart = await waitCart(page, api, c => rates(c).some(r => r.method_id === 'pickup_location' && r.selected), 'Modern pickup selected');
  assert.equal(Number(cart.totals.total_shipping), 0, 'Pickup shipping is free');
  assert.ok(!await page.locator('#shipping-address_1').isVisible(), 'Pickup hides unnecessary delivery street');
  assert.match(await page.locator('.wp-block-woocommerce-checkout-pickup-options-block').innerText(), /demo/i, 'Pickup is explicitly fictitious');
  return cart;
}
async function fillAddress(page, address) {
  if (!await page.locator('#shipping-address_1').isVisible()) {
    await page.locator('#shipping-fields').getByRole('button', { name: /editar/i }).click();
    await page.locator('#shipping-address_1').waitFor();
  }
  const country = page.locator('#shipping-country');
  await country.selectOption(address.country); await settle(page);
  for (const [key, value] of Object.entries(address)) {
    if (key === 'country') continue;
    const field = page.locator('#shipping-' + key);
    if (!await field.count() || !await field.isVisible()) continue;
    if (await field.evaluate(n => n.tagName === 'SELECT')) await field.selectOption(value); else await field.fill(value);
  }
  await page.locator('#shipping-postcode').press('Tab'); await settle(page);
}
async function geometry(page, kind, state) {
  const evidence = await page.evaluate(({ kind, state }) => {
    const root = document.querySelector(kind === 'cart' ? '.wp-block-woocommerce-cart' : '.wp-block-woocommerce-checkout');
    const visible = n => !!n.getClientRects().length && getComputedStyle(n).visibility !== 'hidden' && !n.closest('[hidden],[aria-hidden="true"],.screen-reader-text');
    const nodes = [...root.querySelectorAll('.wc-block-components-radio-control__option,.wc-block-components-shipping-methods__option,.wc-block-checkout__shipping-method-option,.wc-block-components-radio-control-accordion-option')].filter(visible);
    const options = nodes.map(n => {
      const r = n.getBoundingClientRect();
      const radio = n.querySelector('input[type="radio"]');
      const price = n.querySelector('.wc-block-checkout__shipping-method-option-price,.wc-block-components-radio-control__secondary-label,.wc-block-formatted-money-amount');
      const pr = price?.getBoundingClientRect();
      return { text: n.innerText.trim(), rect: r.toJSON(), role: n.getAttribute('role'), pressed: n.getAttribute('aria-pressed'), checked: n.getAttribute('aria-checked'), radio: radio ? { width: radio.getBoundingClientRect().width, height: radio.getBoundingClientRect().height, checked: radio.checked, label: [...radio.labels || []].map(l => l.innerText.trim()).join(' '), describedby: radio.getAttribute('aria-describedby') } : null, price: pr?.toJSON(), overflow: n.scrollWidth > n.clientWidth + 2 };
    });
    const live = [...root.querySelectorAll('[aria-live],[role="status"],[role="alert"]')].map(n => ({ role: n.getAttribute('role'), live: n.getAttribute('aria-live'), text: n.textContent.trim().slice(0, 180) }));
    const addressEdit = [...root.querySelectorAll('.wc-block-components-address-card__edit')].filter(visible).map(n => ({ text: n.textContent.trim(), rect: n.getBoundingClientRect().toJSON(), whiteSpace: getComputedStyle(n).whiteSpace, font: parseFloat(getComputedStyle(n).fontSize) }));
    const summaryRows = [...root.querySelectorAll('.wc-block-components-totals-item')].filter(visible).map(n => n.innerText.trim());
    const finalTotal = [...root.querySelectorAll('.wc-block-components-totals-footer-item')].filter(visible).map(n => n.innerText.trim()).join(' ');
    const subtotalNode = root.querySelector('.wp-block-woocommerce-cart-order-summary-subtotal-block');
    const nativeSubtotal = subtotalNode ? { text: subtotalNode.textContent.trim(), children: subtotalNode.children.length, display: getComputedStyle(subtotalNode).display } : null;
    return { width: innerWidth, scroll: document.documentElement.scrollWidth, kind, state, theme: document.documentElement.dataset.theme, options, addressEdit, live, summaryRows, finalTotal, nativeSubtotal, shipping: [...root.querySelectorAll('[class*="shipping"],[class*="pickup"]')].filter(n => visible(n) && n.children.length < 2).map(n => n.textContent.trim()).filter(Boolean).slice(-12) };
  }, { kind, state });
  assert.ok(evidence.scroll <= evidence.width + 1, 'No page horizontal overflow: ' + JSON.stringify({ width: evidence.width, scroll: evidence.scroll }));
  for (const option of evidence.options) {
    assert.ok(option.rect.x >= -1 && option.rect.right <= evidence.width + 1, 'Shipping option inside viewport: ' + option.text);
    assert.ok(!option.overflow, 'Shipping option has no clipped overflow: ' + option.text);
    assert.ok(option.rect.height >= 43.5, 'Shipping touch target >=44: ' + option.text);
    if (option.radio) { assert.ok(option.radio.width >= 16 && option.radio.height >= 16, 'Visible radio: ' + option.text); assert.ok(option.radio.label, 'Radio has native label'); }
    if (option.price) assert.ok(option.price.right <= evidence.width + 1 && option.price.left >= -1, 'Price visible: ' + option.text);
  }
  if (kind === 'checkout' && state !== 'uncovered') assert.ok(evidence.options.length, 'Native shipping or pickup controls present');
  for (const edit of evidence.addressEdit) {
    assert.equal(edit.whiteSpace, 'nowrap', 'Address edit label stays in one piece');
    assert.ok(edit.rect.height >= 43.5 && edit.rect.width >= 43.5, 'Address edit touch target >=44');
    assert.ok(edit.rect.left >= -1 && edit.rect.right <= evidence.width + 1, 'Address edit remains inside viewport');
  }
  return evidence;
}
async function couponForm(page) {
  const panel = page.locator('.wc-block-components-totals-coupon'); const input = panel.locator('input'); await panel.waitFor();
  if (!await input.isVisible()) { await panel.locator('.wc-block-components-panel__button').focus(); await page.keyboard.press('Enter'); }
  await input.waitFor(); return { input, submit: panel.locator('.wc-block-components-totals-coupon__button') };
}

(async () => {
  for (const engine of (process.env.H62_ENGINES || 'chromium,firefox,webkit').split(',')) {
    let browser, context, page, api;
    try {
      browser = await playwright[engine].launch({ headless: true });
      context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, colorScheme: 'light' });
      page = await context.newPage(); watch(page, engine); api = cartAPI(context); await initialize(page, api);
      const catalog = await (await context.request.get(base + '/index.php?rest_route=/wc/store/v1/products&per_page=50')).json();
      const products = Object.fromEntries(catalog.map(p => [p.slug, p]));
      const low = [['bolsa-diario', 1]], exact = [['bolso-transito', 2], ['panuelo-pliegue', 2]];
      if (process.env.H62_SCOPE !== 'matrix') {
        await check(`${engine}/native-subtotal-below-exact-above`, async () => {
          const evidence = [];
          for (const [items, subtotal, method] of [[[['bolso-transito', 1], ['gorro-punto', 3]], 99000, 'flat_rate'], [exact, 100000, 'free_shipping'], [[['bolso-transito', 3]], 108000, 'free_shipping']]) {
            const cart = await setCart(api, products, items); assertShipping(cart, method, subtotal); await go(page, 'checkout');
            assert.match(await page.locator('#shipping-option').innerText(), method === 'flat_rate' ? /Env[ií]o est[aá]ndar/ : /Env[ií]o gratis/);
            evidence.push(compact(cart));
          }
          return evidence;
        }, page);
        await check(`${engine}/coupon-after-discount-native-threshold`, async () => {
          await setCart(api, products, exact); await go(page, 'cart'); const form = await couponForm(page);
          await form.input.fill(coupon); await form.submit.focus(); await page.keyboard.press('Enter');
          const discounted = await waitCart(page, api, c => c.coupons.some(x => x.code === coupon), 'Temporary owned coupon applied');
          assertShipping(discounted, 'flat_rate', 100000); assert.equal(Number(discounted.totals.total_discount), 1000000);
          const chip = page.locator('.wc-block-components-totals-discount__coupon-list').filter({ hasText: coupon });
          await chip.locator('button').first().focus(); await page.keyboard.press('Enter');
          const restored = await waitCart(page, api, c => !c.coupons.length, 'Coupon removed'); assertShipping(restored, 'free_shipping', 100000);
          return { afterDiscount: compact(discounted), removed: compact(restored), minimumAppliesAfterCoupon: true };
        }, page);
        await check(`${engine}/modern-pickup-keyboard-delivery-return`, async () => {
          await setCart(api, products, low); await go(page, 'checkout'); const initial = await api.data();
          const selected = await pickup(page, api);
          assert.equal(Number(initial.totals.total_price) - Number(selected.totals.total_price), 450000, 'Native selection changes total by shipping price');
          await chooseMode(page, false); await page.locator('#shipping-fields').waitFor();
          const restored = await waitCart(page, api, c => rates(c).some(r => r.method_id === 'flat_rate' && r.selected), 'Delivery restored'); assertShipping(restored, 'flat_rate', 19000);
          const input = page.locator('#shipping-option input[type="radio"]').first(); await input.focus(); await page.keyboard.press('Tab'); await page.keyboard.press('Shift+Tab');
          const focus = await input.evaluate(n => { const s = getComputedStyle(n); return { active: document.activeElement === n, outline: s.outlineStyle, width: s.outlineWidth, shadow: s.boxShadow, visible: n.matches(':focus-visible') }; });
          assert.ok(focus.active && focus.visible, 'Shipping radio receives keyboard visible focus');
          assert.ok((focus.outline !== 'none' && parseFloat(focus.width) > 0) || focus.shadow !== 'none', 'Shipping radio focus has visible indicator');
          return { delivery: compact(initial), pickup: compact(selected), restored: compact(restored), focus };
        }, page);
        await check(`${engine}/address-country-province-postcode-native-recalculation`, async () => {
          await setCart(api, products, low); await go(page, 'checkout'); await fillAddress(page, ar);
          const before = page.nativeNetwork.writes.length;
          await page.locator('#shipping-address_1').fill('Otra dirección de prueba 456'); await page.locator('#shipping-state').selectOption('B'); await page.locator('#shipping-postcode').fill('1900'); await page.locator('#shipping-city').fill('La Plata'); await page.locator('#shipping-postcode').press('Tab');
          const province = await waitCart(page, api, c => c.shipping_address.address_1 === 'Otra dirección de prueba 456' && c.shipping_address.state === 'B' && c.shipping_address.postcode === '1900', 'Native address/province/postcode persisted'); assertShipping(province, 'flat_rate');
          await fillAddress(page, cl); const uncovered = await waitCart(page, api, c => c.shipping_address.country === 'CL' && deliveryRates(c).length === 0, 'Country outside Argentina has no delivery'); assertShipping(uncovered, 'none');
          const notice = page.locator('#shipping-option'); await notice.waitFor(); const text = await notice.innerText();
          assert.match(text, /(?:no .{0,90}(?:env[ií]o|entrega)|sin .{0,40}(?:env[ií]o|cobertura)|no se .{0,80}opciones)/i, 'Visible native uncovered Spanish message');
          assert.doesNotMatch(text, /No shipping|There are no|enter.*address/i);
          const a11y = await notice.evaluate(n => [...n.querySelectorAll('[role="alert"],[role="status"],[aria-live]')].map(x => ({ role: x.getAttribute('role'), live: x.getAttribute('aria-live'), text: x.textContent.trim() })));
          await fillAddress(page, ar); const restored = await waitCart(page, api, c => c.shipping_address.country === 'AR' && deliveryRates(c).some(r => r.method_id === 'flat_rate'), 'Restoring Argentina recalculates rates'); assertShipping(restored, 'flat_rate');
          return { province: compact(province), uncovered: compact(uncovered), text, a11y, restored: compact(restored), nativeWrites: page.nativeNetwork.writes.slice(before).map(r => ({ url: r.url, body: r.body?.slice(0, 700) })) };
        }, page);
        await check(`${engine}/quantity-subtotal-loading-native-a11y`, async () => {
          await setCart(api, products, [['bolso-transito', 2]]); await go(page, 'cart');
          const quantity = page.locator('.wc-block-components-quantity-selector__input').first();
          let loading = [];
          const routePattern = /(?:batch|update-item)/;
          await page.route(routePattern, async route => { if (route.request().method() === 'POST') await new Promise(r => setTimeout(r, 750)); await route.continue(); });
          try {
            await quantity.fill('3'); await quantity.press('Tab');
            await page.waitForFunction(() => [...document.querySelectorAll('[aria-live],[role="status"],[aria-busy="true"]')].some(n => /actualiz|cargando/i.test(n.getAttribute('aria-label') || n.textContent) || n.getAttribute('aria-busy') === 'true'), undefined, { timeout: 5000 });
            loading = await page.locator('[aria-live],[role="status"],[aria-busy="true"],.wc-block-components-skeleton__element').evaluateAll(nodes => nodes.filter(n => n.getClientRects().length).map(n => ({ role: n.getAttribute('role'), live: n.getAttribute('aria-live'), busy: n.getAttribute('aria-busy'), text: (n.getAttribute('aria-label') || n.textContent).trim().slice(0, 150) })));
            const high = await waitCart(page, api, c => c.items_count === 3 && deliveryRates(c).some(r => r.method_id === 'free_shipping'), 'Quantity crosses free threshold'); assertShipping(high, 'free_shipping', 108000);
            await quantity.fill('2'); await quantity.press('Tab'); const lowCart = await waitCart(page, api, c => c.items_count === 2 && deliveryRates(c).some(r => r.method_id === 'flat_rate'), 'Lower quantity restores flat'); assertShipping(lowCart, 'flat_rate', 72000);
            assert.ok(loading.some(n => /actualiz|cargando/i.test(n.text) || n.busy === 'true'), 'Native/cart update feedback is available during delayed authoritative response');
            return { high: compact(high), low: compact(lowCart), loading, delayedNativeResponseOnly: true };
          } finally { await page.unroute(routePattern); }
        }, page);
        await check(`${engine}/h61-mini-cross-sells-return-exit-regression`, async () => {
          await setCart(api, products, low); await go(page, '?post_type=product');
          const trigger = page.locator('.wc-block-mini-cart__button'); await trigger.click(); const drawer = page.locator('.wc-block-mini-cart__drawer[aria-hidden="false"]');
          const input = drawer.locator('.wc-block-components-quantity-selector__input'); await input.waitFor(); await input.fill('2'); await input.press('Tab');
          await waitCart(page, api, c => c.items_count === 2, 'Mini-cart quantity persists'); await page.keyboard.press('Escape');
          await drawer.waitFor({ state: 'hidden' }); assert.ok(await trigger.evaluate(n => n === document.activeElement), 'Mini-cart restores focus');
          await go(page, 'cart'); const cross = page.locator('.cb-cart-cross-sells,.wp-block-woocommerce-cart-cross-sells-block').first(); await cross.waitFor(); assert.ok(await cross.locator('a[href*="product="]').count());
          await go(page, 'checkout'); const count = (await api.data()).items_count;
          assert.equal(await page.locator('[data-cb-exit-intent]').count(), 0, 'Checkout excludes exit intent');
          await page.locator('.wc-block-components-checkout-return-to-cart-button').click(); await page.waitForURL(u => u.searchParams.get('page_id') === '8'); await settle(page); assert.equal((await api.data()).items_count, count);
          await page.waitForTimeout(16000); await page.keyboard.press('Tab'); await page.mouse.move(400, 150); await page.mouse.move(400, -5);
          // Gecko/WebKit automation can retain the pointer at the viewport edge;
          // use the same browser mouseout event already covered by H6.1 there.
          if (engine !== 'chromium') await page.evaluate(() => document.dispatchEvent(new MouseEvent('mouseout', { bubbles: true, relatedTarget: null, clientX: 400, clientY: -1 })));
          await page.locator('[data-cb-exit-intent][open]').waitFor(); await page.keyboard.press('Escape'); await page.locator('[data-cb-exit-intent]').waitFor({ state: 'hidden' });
          return { miniQuantity: true, miniFocus: true, crossSellLinks: await cross.locator('a[href*="product="]').count(), returnPreservedCart: true, exitIntentDismissed: true, checkoutExcluded: true };
        }, page);
        await check(`${engine}/h5-h51-catalog-product-stock-home-lookbook`, async () => {
          await go(page, '?post_type=product'); assert.ok(await page.locator('.cb-product-card').count());
          await go(page, '?product=remera-base'); await page.getByRole('radio', { name: 'Natural', exact: true }).click(); await page.getByRole('radio', { name: 'S', exact: true }).click(); await page.waitForFunction(() => Number(document.querySelector('input.variation_id')?.value) > 0);
          await page.locator('.single_add_to_cart_button:not(.disabled)').waitFor();
          await go(page, '?product=pantalon-linea'); assert.match(await page.locator('main').innerText(), /agotad|sin stock/i);
          await go(page, ''); const lookbook = page.locator('.slb-enhanced'); await lookbook.waitFor();
          const hotspot = page.locator('.slb-hotspot').first(); await hotspot.focus(); await page.waitForTimeout(150); assert.equal(await hotspot.getAttribute('aria-expanded'), 'true'); assert.match(await hotspot.getAttribute('href'), /product=/);
          return { catalog: true, variableAvailable: true, exhaustedProduct: true, homeLookbook: true, keyboardHotspot: true };
        }, page);
      }
      if (process.env.H62_SCOPE !== 'flows') {
        const matrixWidths = process.env.H62_WIDTHS ? process.env.H62_WIDTHS.split(',').map(Number) : engine === 'chromium' ? widths : [390, 1440];
        for (const state of ['flat', 'free', 'pickup', 'uncovered']) {
          await setCart(api, products, state === 'free' ? [['bolso-transito', 3]] : low, state === 'uncovered' ? cl : ar);
          await go(page, 'checkout');
          if (state === 'pickup') await pickup(page, api);
          else await chooseMode(page, false);
          for (const theme of ['light', 'dark']) for (const width of matrixWidths) for (const kind of ['cart', 'checkout']) await check(`${engine}/${state}/${theme}/${width}/${kind}`, async () => {
            await page.emulateMedia({ colorScheme: theme }); await page.setViewportSize({ width, height: width >= 1920 ? 1080 : 900 }); await go(page, kind);
            // A previously selected pickup can survive Cart navigation. Re-enter
            // delivery explicitly and await Core before inspecting no coverage.
            if (state === 'uncovered' && kind === 'checkout') {
              await chooseMode(page, false);
              assert.match(await page.locator('#shipping-option').innerText(), /No hay opciones de env[ií]o disponibles para esta direcci[oó]n/i, 'Delivery has a visible Spanish uncovered notice');
            }
            const evidence = await geometry(page, kind, state); assert.equal(evidence.theme, theme);
            const native = await api.data();
            const totalText = (Number(native.totals.total_price) / 100).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            assert.ok(evidence.finalTotal.replace(/\s/g, '').includes(totalText), 'Visible final total matches authoritative Woo totals: ' + evidence.finalTotal + ' vs ' + totalText);
            if (state === 'pickup') {
              assert.ok(rates(native).some(r => r.method_id === 'pickup_location' && r.selected), 'Modern pickup survives cart/checkout navigation');
              assert.equal(Number(native.totals.total_shipping), 0);
            } else assertShipping(native, state === 'flat' ? 'flat_rate' : state === 'free' ? 'free_shipping' : 'none');
            if (state === 'uncovered') assert.equal(native.shipping_address.country, 'CL', 'Outside-zone destination is retained when delivery has no rate');
            evidence.nativeShipping = { amount: native.totals.total_shipping, selected: rates(native).filter(r => r.selected).map(r => r.rate_id), destination: native.shipping_address.country };
            if (engine === 'chromium' && [320, 390, 1440].includes(width)) await capture(page, `${state}-${kind}-${theme}-${width}`);
            return evidence;
          }, page);
        }
      }
    } catch (error) { report.checks.push({ name: engine + '/setup-or-unhandled', result: 'failed', error: error.stack }); console.log('FAIL', engine, error.message.slice(0, 500)); }
    finally {
      if (api) await api.clear().then(async () => report.cleanup.push({ engine, remainingItems: (await api.data()).items_count, remainingCoupons: (await api.data()).coupons.length })).catch(e => report.cleanup.push({ engine, error: e.message }));
      if (context) await context.close(); if (browser) await browser.close(); save();
    }
  }
  report.finished = new Date().toISOString(); save();
  console.log(JSON.stringify({ passed: report.passed, failed: report.failed, errors: report.errors, assets: report.assetErrors, checkoutPOSTs: report.forbiddenCheckoutRequests, cleanup: report.cleanup }));
  if (report.failed || report.errors.length || report.assetErrors.length || report.forbiddenCheckoutRequests.length || report.cleanup.some(c => c.error || c.remainingItems || c.remainingCoupons)) process.exitCode = 1;
})();
