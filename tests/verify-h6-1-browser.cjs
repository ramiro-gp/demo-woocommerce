/** H6.1 — real guest cart/checkout, three engines and responsive evidence. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
process.env.PLAYWRIGHT_BROWSERS_PATH ||= 'C:\\Users\\ramiro\\AppData\\Local\\ms-playwright';
const playwright = require('playwright');
const base = (process.env.H61_BASE_URL || 'http://localhost:8080').replace(/\/$/, '');
const output = path.resolve(__dirname, '../.local/local/h6-1');
const reportName = process.env.H61_REPORT_NAME || 'browser-qa.json';
const widths = [320, 375, 390, 430, 1024, 1366, 1440, 1920, 2560, 3440];
const coupon = 'h6-1-qa-10';
const buyer = 'h6-1-qa@example.test';
const report = { started: new Date().toISOString(), base, widths, checks: [], errors: [], captures: [], cleanup: [] };
const badge = p => p.locator('.cb-header-cart .wc-block-mini-cart__badge');
const rows = p => p.locator('.wc-block-cart-items__row');
let activeCheck = '';

fs.mkdirSync(output, { recursive: true });
async function check(name, run, page) {
  if (process.env.H61_CHECK && !new RegExp(process.env.H61_CHECK).test(name)) return;
  activeCheck = name;
  try {
    const evidence = await run();
    report.checks.push({ name, result: 'passed', evidence });
  } catch (error) {
    console.log('FAIL', name, error.message.slice(0, 500));
    const failure = { name, result: 'failed', error: error.message, stack: error.stack };
    if (page && !page.isClosed()) {
      failure.pendingNativeRequests = [...(page.cbNativeNetwork?.pending || [])].map(r => r.url());
      failure.checkoutState = await page.evaluate(() => {
        const store = window.wc?.wcBlocksData?.checkoutStore;
        const checkout = store && window.wp?.data?.select(store);
        return checkout ? Object.fromEntries(['getCheckoutStatus', 'isCalculating', 'isProcessing', 'isBeforeProcessing', 'isAfterProcessing', 'hasError'].filter(k => typeof checkout[k] === 'function').map(k => [k, checkout[k]()])) : null;
      }).catch(() => null);
      failure.checkoutUI = await page.evaluate(() => ({ notices: [...document.querySelectorAll('.wc-block-components-notice-banner')].map(n => n.textContent.trim()), fieldErrors: [...document.querySelectorAll('.wc-block-components-validation-error')].map(n => n.textContent.trim()), invalidFields: [...document.querySelectorAll('[aria-invalid="true"]')].map(n => ({ id: n.id, value: n.value })), submit: [...document.querySelectorAll('.wc-block-components-checkout-place-order-button')].map(n => ({ disabled: n.disabled, ariaDisabled: n.getAttribute('aria-disabled'), classes: n.className })) })).catch(() => null);
      failure.checkoutAttempts = page.cbCheckoutAttempts || [];
      failure.checkoutBefore = page.cbCheckoutBefore;
      failure.checkoutUIEvents = page.cbCheckoutUIEvents;
    }
    report.checks.push(failure);
    if (page && !page.isClosed()) {
      const file = 'failure-' + name.replace(/[^a-z0-9-]/gi, '-') + '.png';
      await page.screenshot({ path: path.join(output, file), fullPage: true }).then(() => report.captures.push(file)).catch(() => {});
    }
  }
  saveReport();
}
function saveReport() {
  report.passed = report.checks.filter(c => c.result === 'passed').length;
  report.failed = report.checks.filter(c => c.result === 'failed').length;
  fs.writeFileSync(path.join(output, reportName), JSON.stringify(report, null, 2) + '\n');
}
function watch(page, engine) {
  page.setDefaultTimeout(14000);
  page.setDefaultNavigationTimeout(30000);
  const network = page.cbNativeNetwork = { pending: new Set(), last: Date.now() };
  page.on('request', request => {
    if (request.url().startsWith(base) && ['fetch', 'xhr', 'script'].includes(request.resourceType())) {
      network.pending.add(request); network.last = Date.now();
    }
  });
  for (const event of ['requestfinished', 'requestfailed']) page.on(event, request => {
    if (network.pending.delete(request)) network.last = Date.now();
  });
  page.on('pageerror', error => report.errors.push({ engine, check: activeCheck, url: page.url(), error: error.message }));
}
async function settle(page) {
  const network = page.cbNativeNetwork;
  const deadline = Date.now() + 18000;
  while (network && (network.pending.size || Date.now() - network.last < 350)) {
    assert.ok(Date.now() < deadline, 'Native network did not settle: ' + [...network.pending].map(r => r.url()).join(', '));
    await page.waitForTimeout(60);
  }
  // Quantity optimistic rendering precedes the authoritative Woo Store response.
  await page.waitForFunction(() => {
    const store = window.wc?.wcBlocksData?.cartStore;
    const cart = store && window.wp?.data?.select(store);
    return !cart || (!cart.getItemsPendingQuantityUpdate().length && !cart.getItemsPendingDelete().length && !cart.getProductsPendingAdd().length);
  });
}
async function go(page, query) {
  if (page.url().startsWith(base)) await settle(page);
  await page.goto(base + '/' + query, { waitUntil: 'domcontentloaded' });
  await page.locator('main').waitFor();
  await page.evaluate(() => document.fonts.ready);
  await settle(page);
  if (query.includes('page_id=8')) {
    // Cart's server skeleton already has rows; wait for actual shopper data.
    await page.locator('.wc-block-cart-items__row .wc-block-components-product-name').filter({ hasText: 'Bolsa Diario' }).first().waitFor();
    await page.locator('.wc-block-cart-items__row .wc-block-components-quantity-selector__input').first().waitFor();
    await settle(page);
  }
  if (query.includes('page_id=9')) {
    await page.locator('#email').waitFor();
    await page.locator('.wc-block-components-order-summary-item .wc-block-components-product-name').filter({ hasText: 'Bolsa Diario' }).first().waitFor();
    await settle(page);
  }
}
async function capture(page, name) {
  await page.evaluate(async () => {
    for (let y = 0; y < document.body.scrollHeight; y += 800) {
      window.scrollTo({ top: y, behavior: 'instant' });
      await new Promise(r => setTimeout(r, 25));
    }
    await Promise.race([
      Promise.all([...document.images].filter(i => i.src.startsWith(location.origin)).map(i => i.decode().catch(() => {}))),
      new Promise(r => setTimeout(r, 1500))
    ]);
    document.activeElement?.blur(); window.scrollTo({ top: 0, behavior: 'instant' });
  });
  await page.waitForTimeout(150);
  const file = (process.env.H61_CAPTURE_PREFIX || '') + name + '.png';
  await page.screenshot({ path: path.join(output, file), fullPage: true });
  report.captures.push(file);
}
function cartAPI(context) {
  let token;
  const url = route => base + '/index.php?rest_route=/wc/store/v1/' + route;
  return {
    async call(method = 'get', route = 'cart', data, expectSuccess = true) {
      // Native checkout may refresh a guest session; obtain the current token
      // from shared browser cookies immediately before any cart mutation.
      if (method !== 'get') await this.call();
      // Reads follow this BrowserContext's current Woo session cookies. A token
      // obtained before the first product POST can name an older empty session.
      const response = await context.request[method](url(route), { headers: token && method !== 'get' ? { 'Cart-Token': token } : {}, ...(data ? { data } : {}) });
      token = response.headers()['cart-token'] || token;
      const body = await response.json();
      if (expectSuccess) assert.ok(response.ok(), method + ' ' + route + ': ' + JSON.stringify(body));
      return { response, body };
    },
    async data() { return (await this.call()).body; },
    async clear() {
      await this.call('delete', 'cart/items');
      const remaining = await this.data();
      assert.equal(remaining.items_count, 0, 'Isolated guest cart cleanup');
    }
  };
}
async function waitCart(page, api, predicate, message) {
  await settle(page);
  const deadline = Date.now() + 14000;
  let data;
  do {
    data = await api.data();
    if (predicate(data)) return data;
    await page.waitForTimeout(120);
  } while (Date.now() < deadline);
  assert.fail(message + ': ' + JSON.stringify({ items_count: data.items_count, items: data.items.map(i => ({ name: i.name, quantity: i.quantity })), coupons: data.coupons }));
}
async function waitBadge(page, count) {
  if (!count) await badge(page).waitFor({ state: 'hidden' });
  else await page.waitForFunction(value => document.querySelector('.cb-header-cart .wc-block-mini-cart__badge')?.textContent.trim() === String(value), count);
}
async function addSimple(page, quantity = 2) {
  await go(page, '?product=bolsa-diario');
  const id = Number(await page.locator('.single_add_to_cart_button').getAttribute('value'));
  assert.ok(id > 0, 'Native simple product ID');
  await page.locator('input.qty').fill(String(quantity));
  await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.locator('.single_add_to_cart_button').click()]);
  await settle(page);
  return id;
}
async function addVariable(page) {
  await go(page, '?product=remera-base');
  await page.getByRole('radio', { name: 'Natural', exact: true }).click();
  await page.getByRole('radio', { name: 'S', exact: true }).click();
  await page.waitForFunction(() => Number(document.querySelector('input.variation_id')?.value) > 0);
  const id = Number(await page.locator('input.variation_id').inputValue());
  await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.locator('.single_add_to_cart_button:not(.disabled)').click()]);
  await settle(page);
  return id;
}
async function geometry(page, kind) {
  const result = await page.evaluate(kind => {
    const scope = document.querySelector(kind === 'cart' ? '.wp-block-woocommerce-cart' : '.wp-block-woocommerce-checkout');
    const visible = n => !!n.getClientRects().length && getComputedStyle(n).visibility !== 'hidden' && !n.closest('.screen-reader-text,.wc-block-components-address-form__address_2-hidden-input,[hidden],[inert],[aria-hidden="true"]');
    const rect = n => n ? n.getBoundingClientRect().toJSON() : null;
    const main = scope.querySelector(kind === 'cart' ? '.wc-block-cart__main' : '.wc-block-checkout__main');
    const sidebar = scope.querySelector(kind === 'cart' ? '.wc-block-cart__sidebar' : '.wc-block-checkout__sidebar');
    const controls = [...scope.querySelectorAll('input:not([type="hidden"]),select,textarea,button,a.wc-block-cart-item__remove-link,a.wc-block-components-checkout-return-to-cart-button')].filter(visible).map(n => {
      const r = n.getBoundingClientRect();
      const check = ['checkbox', 'radio'].includes(n.type);
      const target = check ? n.closest('label')?.getBoundingClientRect() || r : r;
      return { tag: n.tagName, type: n.type, text: (n.getAttribute('aria-label') || n.innerText || n.id || '').trim().slice(0, 80), width: r.width, height: r.height, targetHeight: target.height, font: parseFloat(getComputedStyle(n).fontSize), inside: !!n.closest('.is-product-collection-layout-carousel') || (r.left >= scope.getBoundingClientRect().left - 1 && r.right <= scope.getBoundingClientRect().right + 1) };
    });
    const typography = [...scope.querySelectorAll('p,span,a,button,label,input,select,textarea,li')].filter(n => visible(n) && [...n.childNodes].some(t => t.nodeType === 3 && t.textContent.trim()) && !n.closest('.screen-reader-text,[aria-hidden="true"]')).map(n => ({ text: n.textContent.trim().slice(0, 70), font: parseFloat(getComputedStyle(n).fontSize), floatingLabel: n.matches('.wc-block-components-text-input label,.wc-blocks-components-select__label,.wc-block-components-combobox label') }));
    return { width: innerWidth, pageScrollWidth: document.documentElement.scrollWidth, scope: rect(scope), main: rect(main), sidebar: rect(sidebar), layout: { display: getComputedStyle(main.parentElement).display, mainFlex: getComputedStyle(main).flex, sidebarFlex: getComputedStyle(sidebar).flex, sidebarWidth: getComputedStyle(sidebar).width }, controls, typography, theme: document.documentElement.dataset.theme, scheme: getComputedStyle(scope).colorScheme };
  }, kind);
  assert.ok(result.pageScrollWidth <= result.width + 1, 'No horizontal document overflow: ' + JSON.stringify({ width: result.width, scroll: result.pageScrollWidth }));
  assert.ok(result.main && result.sidebar, 'Both native commerce regions render');
  assert.ok(result.controls.length, 'Native commerce controls render');
  for (const control of result.controls) {
    assert.ok(control.inside, 'Control stays inside commerce container: ' + JSON.stringify(control));
    // Native quantity +/- buttons can be narrow; their height is a full touch target.
    if (['INPUT', 'SELECT', 'TEXTAREA', 'BUTTON'].includes(control.tag)) assert.ok(control.targetHeight >= 43.5, 'Control touch height >=44: ' + JSON.stringify(control));
    if (!['checkbox', 'radio'].includes(control.type)) assert.ok(control.font >= 15.9, 'Control text >=16: ' + JSON.stringify(control));
  }
  for (const text of result.typography) if (!text.floatingLabel) assert.ok(text.font >= 15.9, 'Reading text >=16: ' + JSON.stringify(text));
  if (result.scope.width >= 928) {
    assert.ok(result.sidebar.left >= result.main.right - 2, 'Desktop data and summary are side by side');
    assert.ok(result.sidebar.width >= (kind === 'checkout' ? 300 : 280), 'Summary has room for names and totals');
  } else assert.ok(result.sidebar.width >= result.scope.width * 0.9, 'Mobile summary uses available width');
  return result;
}
async function couponForm(page) {
  const panel = page.locator('.wc-block-components-totals-coupon');
  await panel.waitFor();
  const input = panel.locator('input');
  if (!(await input.isVisible())) { await panel.locator('.wc-block-components-panel__button').focus(); await page.keyboard.press('Enter'); }
  await input.waitFor();
  return { panel, input, submit: panel.locator('.wc-block-components-totals-coupon__button') };
}
async function fillAddress(page, postcode = '1001') {
  await page.locator('#email').fill(buyer);
  for (const [id, value] of Object.entries({ 'shipping-first_name': 'Prueba', 'shipping-last_name': 'H6.1', 'shipping-address_1': 'Av. Siempreviva 123', 'shipping-city': 'Buenos Aires', 'shipping-postcode': postcode })) await page.locator('#' + id).fill(value);
  await page.locator('#shipping-state').selectOption('C');
  await page.locator('#shipping-postcode').press('Tab');
  await settle(page);
}

(async () => {
  for (const engine of (process.env.H61_ENGINES || 'chromium,firefox,webkit').split(',')) {
    let browser, context, page, api;
    try {
      browser = await playwright[engine].launch({ headless: true });
      context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, colorScheme: 'light' });
      page = await context.newPage(); watch(page, engine); api = cartAPI(context);
      await api.clear();
      let simpleId;
      await check(`${engine}/native-add-simple-variable`, async () => {
        simpleId = await addSimple(page);
        const variationId = await addVariable(page);
        const cart = await waitCart(page, api, c => c.items_count === 3 && c.items.length === 2, 'Simple and selected variation added');
        await waitBadge(page, 3);
        assert.ok(cart.items.some(i => i.name.includes('Bolsa Diario') && i.quantity === 2));
        assert.ok(cart.items.some(i => i.id === variationId && i.variation.length >= 2), 'Selected attributes survive native add');
        return { simpleId, variationId, items: cart.items.map(i => ({ name: i.name, quantity: i.quantity, variation: i.variation })) };
      }, page);
      // Targeted geometry reruns still need a real guest fixture. A failed add
      // remains a reported failure even when setup is retried for later checks.
      const fixture = await api.data();
      if (fixture.items.length !== 2 || fixture.items_count !== 3) {
        await api.clear(); await addSimple(page); await addVariable(page);
      }
      await check(`${engine}/native-mini-cart-quantity-escape`, async () => {
        await go(page, '?post_type=product');
        const trigger = page.locator('.wc-block-mini-cart__button'); await trigger.click();
        const drawer = page.locator('.wc-block-mini-cart__drawer'); await drawer.waitFor();
        const quantity = drawer.locator('.wc-block-cart-items__row').filter({ hasText: 'Bolsa Diario' }).locator('.wc-block-components-quantity-selector__input');
        await quantity.fill('3'); await quantity.press('Tab'); await waitCart(page, api, c => c.items_count === 4, 'Native mini-cart quantity updates server'); await waitBadge(page, 4);
        await quantity.fill('2'); await quantity.press('Tab'); await waitCart(page, api, c => c.items_count === 3, 'Native mini-cart quantity restored'); await waitBadge(page, 3);
        await page.keyboard.press('Escape');
        await page.waitForFunction(() => document.querySelector('.wc-block-mini-cart__drawer')?.getAttribute('aria-hidden') === 'true');
        assert.ok(await trigger.evaluate(n => document.activeElement === n), 'Escape restores focus to mini-cart trigger');
        return { keyboardQuantityUpdatesServer: true, badgeSynchronized: true, escapeRestoresFocus: true };
      }, page);

      if (process.env.H61_SCOPE !== 'flows') for (const theme of ['light', 'dark']) for (const width of widths) {
        for (const [kind, query, ready] of [['cart', '?page_id=8', '.wc-block-cart-items__row'], ['checkout', '?page_id=9', '#email']]) await check(`${engine}/${theme}/${width}/${kind}-geometry`, async () => {
          await page.emulateMedia({ colorScheme: theme });
          await page.setViewportSize({ width, height: width >= 1920 ? 1080 : 900 });
          await go(page, query); await page.locator(ready).first().waitFor();
          const evidence = await geometry(page, kind);
          assert.equal(evidence.theme, theme);
          if (engine === 'chromium' && [320, 390, 1440].includes(width)) await capture(page, `${kind}-${engine}-${theme}-${width}`);
          return evidence;
        }, page);
      }
      await page.emulateMedia({ colorScheme: 'light' }); await page.setViewportSize({ width: 1440, height: 1000 });

      await check(`${engine}/cart-quantity-keyboard-stock`, async () => {
        await go(page, '?page_id=8'); await rows(page).first().waitFor();
        const simple = rows(page).filter({ hasText: 'Bolsa Diario' });
        const quantity = simple.locator('.wc-block-components-quantity-selector__input');
        const initial = await api.data(); const item = initial.items.find(i => i.name.includes('Bolsa Diario'));
        let loadingAnnounced = false;
        if (engine !== 'webkit') await page.route(/batch|update-item/, async route => {
          if (route.request().method() === 'POST') await new Promise(resolve => setTimeout(resolve, 900));
          await route.continue();
        });
        try {
          await simple.locator('.wc-block-components-quantity-selector__button--plus').click();
          if (engine !== 'webkit') {
            await page.locator('[data-cb-cart-feedback-root] [role="status"]').filter({ hasText: 'Actualizando tu carrito' }).waitFor();
            loadingAnnounced = true;
            if (engine === 'chromium') {
              await page.screenshot({ path: path.join(output, 'cart-chromium-loading.png') }); report.captures.push('cart-chromium-loading.png');
            }
          }
          await waitCart(page, api, c => c.items.find(i => i.key === item.key)?.quantity === 3, 'Plus updates server quantity'); await waitBadge(page, 4);
        } finally { if (engine !== 'webkit') await page.unrouteAll({ behavior: 'wait' }); }
        await page.waitForFunction(() => !document.querySelector('[data-cb-cart-feedback-root] [role="status"]')?.textContent.trim());
        await simple.locator('.wc-block-components-quantity-selector__button--minus').focus(); await page.keyboard.press('Enter');
        await waitCart(page, api, c => c.items.find(i => i.key === item.key)?.quantity === 2, 'Keyboard minus updates server quantity');
        await quantity.fill('1'); await quantity.press('Tab');
        await waitCart(page, api, c => c.items.find(i => i.key === item.key)?.quantity === 1, 'Typed minimum persists');
        assert.ok(await simple.locator('.wc-block-components-quantity-selector__button--minus').isDisabled(), 'Cannot decrement below minimum');
        const limits = item.quantity_limits;
        const minimum = Number(await quantity.getAttribute('min'));
        const maximum = Number(await quantity.getAttribute('max'));
        assert.equal(minimum, limits.minimum); assert.equal(maximum, limits.maximum);
        await quantity.fill(String(maximum)); await quantity.press('Tab');
        await waitCart(page, api, c => c.items.find(i => i.key === item.key)?.quantity === maximum, 'Typed maximum persists');
        assert.ok(await simple.locator('.wc-block-components-quantity-selector__button--plus').isDisabled(), 'Cannot increment above maximum');
        const refused = await api.call('post', 'cart/update-item', { key: item.key, quantity: maximum + 1 }, false);
        assert.ok(refused.response.status() >= 400, 'Server rejects quantity above stock');
        assert.match(JSON.stringify(refused.body), /stock|disponib|suficiente|quantity|cantidad/i);
        assert.equal((await api.data()).items.find(i => i.key === item.key).quantity, maximum, 'Stock refusal keeps prior quantity');
        await quantity.fill('2'); await quantity.press('Tab'); const restored = await waitCart(page, api, c => c.items.find(i => i.key === item.key)?.quantity === 2, 'Quantity restored');
        assert.equal(restored.items.find(i => i.key === item.key).quantity_limits.maximum, limits.maximum, 'Available stock limit stays unchanged');
        return { plus: true, keyboardMinus: true, keyboardInput: true, loadingAnnouncedWithDelayedNativeResponse: loadingAnnounced, unmodifiedNativeNetwork: engine === 'webkit', min: minimum, max: maximum, overStockStatus: refused.response.status(), stockChanged: false };
      }, page);

      await check(`${engine}/cart-coupon-valid-invalid-remove`, async () => {
        await go(page, '?page_id=8');
        const before = await api.data(); const form = await couponForm(page);
        await form.input.fill('h6-1-invalid-coupon'); await form.submit.focus(); await page.keyboard.press('Enter'); await settle(page);
        await page.locator('.wc-block-components-validation-error,.wc-block-components-notice-banner.is-error').filter({ hasText: /cup[oó]n/i }).first().waitFor();
        assert.equal((await api.data()).coupons.length, 0, 'Invalid coupon does not change total');
        await form.input.fill(coupon); await form.submit.focus(); await page.keyboard.press('Enter');
        const discounted = await waitCart(page, api, c => c.coupons.some(x => x.code === coupon), 'Valid coupon applied');
        assert.ok(Number(discounted.totals.total_discount) > 0);
        assert.ok(Number(discounted.totals.total_price) < Number(before.totals.total_price), 'Buyer total decreases');
        const chip = page.locator('.wc-block-components-totals-discount__coupon-list').filter({ hasText: coupon });
        await chip.locator('button').first().focus(); await page.keyboard.press('Enter');
        const restored = await waitCart(page, api, c => !c.coupons.length, 'Coupon removed');
        assert.equal(restored.totals.total_price, before.totals.total_price, 'Removing coupon restores total');
        return { invalidRejected: true, discount: discounted.totals.total_discount, amountBefore: before.totals.total_price, amountAfter: discounted.totals.total_price, restored: restored.totals.total_price };
      }, page);

      await check(`${engine}/cross-sells-native-cards-carousel-keyboard`, async () => {
        await page.setViewportSize({ width: 390, height: 844 });
        await go(page, '?page_id=8');
        const cross = page.locator('.wp-block-woocommerce-cart-cross-sells-block,.cb-cart-cross-sells').first();
        await cross.waitFor();
        const cards = cross.locator('.wc-block-product,.wc-block-grid__product,.cb-product-card');
        assert.ok(await cards.count(), 'Native recommended products render from cart');
        const links = cross.locator('a[href*="product="]');
        assert.ok(await links.count(), 'Recommended product links navigate to products');
        const carousel = cross.locator('.is-product-collection-layout-carousel');
        const evidence = { cards: await cards.count(), productLinks: await links.count(), carousel: !!(await carousel.count()) };
        if (await carousel.count()) {
          const scrollable = await carousel.evaluate(n => n.scrollWidth > n.clientWidth + 2);
          evidence.scrollable = scrollable;
          if (scrollable) {
            const buttons = cross.locator('.wc-block-next-previous-buttons button');
            assert.ok(await buttons.count() >= 2, 'Native previous and next controls');
            const next = buttons.last(); await next.focus(); await page.keyboard.press('Enter');
            await page.waitForFunction(() => document.querySelector('.cb-cart-cross-sells .is-product-collection-layout-carousel,.wp-block-woocommerce-cart-cross-sells-block .is-product-collection-layout-carousel')?.scrollLeft > 1);
            evidence.keyboardAdvanced = true;
            await buttons.first().focus(); await page.keyboard.press('Enter');
            await settle(page);
            assert.ok(await next.evaluate(n => parseFloat(getComputedStyle(n).width) >= 44 && n.getBoundingClientRect().height >= 44));
          }
        }
        return evidence;
      }, page);

      await check(`${engine}/guest-address-optional-autocomplete-return`, async () => {
        await go(page, '?page_id=9'); await page.locator('#email').waitFor();
        assert.equal(await page.locator('.wc-block-checkout__form input[type="password"]').count(), 0, 'Guest checkout has no account password requirement');
        const autocomplete = {};
        for (const id of ['email', 'shipping-first_name', 'shipping-last_name', 'shipping-address_1', 'shipping-city', 'shipping-postcode']) autocomplete[id] = await page.locator('#' + id).getAttribute('autocomplete');
        assert.match(autocomplete.email, /email/); assert.match(autocomplete['shipping-first_name'], /given-name/); assert.match(autocomplete['shipping-last_name'], /family-name/);
        assert.match(autocomplete['shipping-address_1'], /address-line1/); assert.match(autocomplete['shipping-city'], /address-level2/); assert.match(autocomplete['shipping-postcode'], /postal-code/);
        const toggle = page.locator('.wc-block-components-address-form__address_2-toggle').first();
        if (await toggle.isVisible()) { await toggle.focus(); await page.keyboard.press('Enter'); }
        await page.locator('#shipping-address_2').waitFor(); assert.ok(!(await page.locator('#shipping-address_2').getAttribute('required')));
        const same = page.locator('.wc-block-checkout__use-address-for-billing input[type="checkbox"]');
        await same.waitFor(); assert.ok(await same.isChecked()); await same.focus(); await page.keyboard.press('Space');
        await page.locator('#billing-first_name').waitFor();
        await same.focus(); await page.keyboard.press('Space'); await page.locator('#billing-first_name').waitFor({ state: 'hidden' });
        await fillAddress(page);
        const link = page.locator('.wc-block-components-checkout-return-to-cart-button'); await link.click();
        await page.waitForURL(u => u.searchParams.get('page_id') === '8'); await rows(page).first().waitFor();
        assert.equal((await api.data()).items_count, 3, 'Returning to cart preserves selected products');
        return { guest: true, optionalAddress2: true, sameAddressKeyboardToggle: true, autocomplete, nativeReturnPreservesCart: true };
      }, page);

      await check(`${engine}/checkout-validation-es-AR-no-payment`, async () => {
        await page.setViewportSize({ width: 390, height: 844 });
        await go(page, '?page_id=9'); await page.locator('#email').waitFor();
        await page.locator('#email').fill('correo-invalido');
        for (const id of ['shipping-first_name', 'shipping-last_name', 'shipping-address_1', 'shipping-city', 'shipping-postcode']) await page.locator('#' + id).fill('');
        const submit = page.locator('.wc-block-components-checkout-place-order-button'); await submit.click();
        await page.locator('.wc-block-components-text-input.has-error').first().waitFor();
        const invalidEmail = await page.locator('#email').getAttribute('aria-invalid');
        assert.equal(invalidEmail, 'true', 'Invalid email is identified to assistive technology');
        const required = await page.locator('.wc-block-components-validation-error').allTextContents();
        assert.ok(required.length >= 3, 'Mandatory email and address feedback is visible');
        assert.ok(!required.join(' ').match(/Please|valid email|required field/i), 'Field errors use Spanish');
        await fillAddress(page, '!!!'); await submit.click(); await settle(page);
        const postalErrors = await page.locator('.wc-block-components-validation-error,.wc-block-components-notice-banner').allTextContents();
        assert.match(postalErrors.join(' '), /postal/i, 'Invalid postcode is explained');
        await page.locator('#shipping-postcode').fill('1001'); await page.locator('#shipping-postcode').press('Tab'); await settle(page);
        await page.waitForFunction(() => document.querySelector('#shipping-postcode')?.getAttribute('aria-invalid') !== 'true');
        // Exercise no-payment independently of a previous rejected postal
        // submission, through a fresh native checkout state and valid address.
        await go(page, '?page_id=9'); await fillAddress(page);
        page.cbCheckoutBefore = await page.evaluate(() => ({ hasError: wp.data.select(wc.wcBlocksData.checkoutStore).hasError(), orderId: wp.data.select(wc.wcBlocksData.checkoutStore).getOrderId(), invalid: [...document.querySelectorAll('.wc-block-checkout__form input:not([type="hidden"]),.wc-block-checkout__form select')].filter(n => n.getClientRects().length && !n.closest('.wc-block-components-address-form__address_2-hidden-input')).filter(n => !n.checkValidity()).map(n => ({ id: n.id, validation: n.validationMessage })) }));
        assert.deepEqual(page.cbCheckoutBefore.invalid, [], 'Native HTML field validity is clear before no-payment test');
        const paymentEvents = [];
        page.cbCheckoutAttempts = paymentEvents;
        const observe = request => { if (/\/checkout(?:\?|$)|rest_route=.*\/checkout/.test(decodeURIComponent(request.url())) && request.method() === 'POST') paymentEvents.push({ url: request.url(), body: request.postDataJSON() }); };
        const uiEvents = page.cbCheckoutUIEvents = [];
        const observeClick = message => { if (message.text().startsWith('h61-checkout-activation:')) uiEvents.push(message.text()); };
        page.on('console', observeClick);
        await submit.evaluate(n => n.addEventListener('click', event => console.log('h61-checkout-activation:' + JSON.stringify({ trusted: event.isTrusted, type: event.type, active: document.activeElement === n })), { once: true }));
        page.on('request', observe);
        try {
          // Use real keyboard activation once the final control has focus;
          // native validation can leave a smooth scroll in flight in WebKit.
          await submit.scrollIntoViewIfNeeded();
          await submit.evaluate(n => new Promise(resolve => { let previous = n.getBoundingClientRect().top, stable = 0, start = performance.now(); function frame() { const top = n.getBoundingClientRect().top; stable = Math.abs(top - previous) < .5 ? stable + 1 : 0; previous = top; if (stable >= 8 || performance.now() - start > 2000) resolve(); else requestAnimationFrame(frame); } requestAnimationFrame(frame); }));
          if ((process.env.H61_NO_PAYMENT_ACTIVATION || 'pointer') === 'pointer') {
            await submit.click();
          } else {
            const enabled = () => { const n = document.querySelector('.wc-block-components-checkout-place-order-button'); return n && !n.disabled && n.getAttribute('aria-disabled') !== 'true'; };
            await page.waitForFunction(enabled);
            await submit.focus(); await settle(page); await page.waitForFunction(enabled);
            // A blur-triggered Woo recalculation may have disabled the button
            // during the first focus call. Refocus once after it has settled.
            if (!(await submit.evaluate(n => document.activeElement === n))) await submit.focus();
            assert.ok(await submit.evaluate(n => document.activeElement === n), 'Final CTA has keyboard focus after native recalculation');
            await page.keyboard.press('Enter');
          }
          await page.getByText('Corregí el siguiente error antes de continuar', { exact: true }).waitFor({ timeout: 25000 }); await settle(page);
        } finally { page.off('request', observe); page.off('console', observeClick); }
        const notices = await page.locator('.wc-block-components-notice-banner').allTextContents();
        assert.match(notices.join(' '), /No se facilitó ningún método de pago/);
        assert.ok(!notices.join(' ').includes('Please fix'));
        for (const event of paymentEvents) {
          assert.ok(!event.body.payment_method, 'No payment gateway is selected');
          assert.ok((event.body.payment_data || []).every(entry => entry.key === 'wc-undefined-new-payment-method' && entry.value === false), 'Only Woo native false no-payment sentinel is allowed: ' + JSON.stringify(event.body.payment_data));
        }
        const singular = await page.evaluate(() => wp.i18n._n('Please fix the following error before continuing', 'Please fix the following errors before continuing', 1, 'woocommerce'));
        assert.equal(singular, 'Corregí el siguiente error antes de continuar');
        const plural = await page.evaluate(() => wp.i18n._n('Please fix the following error before continuing', 'Please fix the following errors before continuing', 2, 'woocommerce'));
        assert.equal(plural, 'Corregí los siguientes errores antes de continuar');
        const contrast = [];
        for (const theme of ['light', 'dark']) {
          await page.emulateMedia({ colorScheme: theme });
          await page.waitForFunction(mode => document.documentElement.dataset.theme === mode && getComputedStyle(document.documentElement).colorScheme === mode, theme);
          const ratios = await page.locator('.wc-block-components-notice-banner').evaluateAll(nodes => {
            const luminance = value => value.match(/[\d.]+/g).slice(0, 3).map(Number).map(v => { v /= 255; return v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4; }).reduce((a, v, i) => a + v * [.2126, .7152, .0722][i], 0);
            return nodes.map(n => { const s = getComputedStyle(n); const a = luminance(s.color), b = luminance(s.backgroundColor); return { text: n.innerText.slice(0, 80), ratio: (Math.max(a, b) + .05) / (Math.min(a, b) + .05) }; });
          });
          assert.ok(ratios.length && ratios.every(r => r.ratio >= 4.5), theme + ' checkout error text contrast >=4.5: ' + JSON.stringify(ratios));
          contrast.push({ theme, notices: ratios });
          if (engine === 'chromium') await capture(page, `checkout-chromium-es-AR-no-payment-${theme}`);
        }
        return { required, invalidEmail, postalErrors, notices, singular, plural, contrast, checkoutBefore: page.cbCheckoutBefore, checkoutRequests: paymentEvents.length, checkoutUIEvents: uiEvents, noPaymentActivation: process.env.H61_NO_PAYMENT_ACTIVATION || 'pointer', paymentDataSent: false };
      }, page);

      await check(`${engine}/reduced-motion-cart-checkout`, async () => {
        await page.emulateMedia({ reducedMotion: 'reduce' }); await page.setViewportSize({ width: 390, height: 844 });
        await go(page, '?page_id=8');
        const collection = page.locator('.cb-cart-cross-sells'); await collection.waitFor();
        const motion = await collection.evaluate(n => { const track = n.querySelector('.is-product-collection-layout-carousel'); return { scroll: getComputedStyle(n).scrollBehavior, transitions: [...n.querySelectorAll('button,a,img')].map(x => getComputedStyle(x).transitionDuration), cards: n.querySelectorAll('.wc-block-product').length, visibleLinks: [...n.querySelectorAll('a[href*="product="]')].filter(x => x.getClientRects().length).length, trackOverflow: !!track && track.scrollWidth > track.clientWidth + 1 }; });
        assert.equal(motion.scroll, 'auto'); assert.ok(motion.transitions.every(t => t.split(',').every(d => parseFloat(d) <= .01)));
        assert.ok(motion.cards && motion.visibleLinks, 'All recommendation links remain available without motion');
        assert.ok(!motion.trackOverflow, 'Reduced motion shows all recommendations without a sliding track');
        await go(page, '?page_id=9'); await page.locator('#email').waitFor();
        const summary = await page.locator('.wc-block-checkout__sidebar').evaluate(n => getComputedStyle(n).position); assert.equal(summary, 'static');
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
        await page.emulateMedia({ reducedMotion: 'no-preference' });
        return { ...motion, summaryPosition: summary, checkoutOverflow: false };
      }, page);

      await check(`${engine}/remove-empty-badge-mini-cart`, async () => {
        await go(page, '?page_id=8');
        while (await rows(page).count()) {
          const before = (await api.data()).items.length;
          await page.locator('.wc-block-cart-item__remove-link').first().click();
          await waitCart(page, api, c => c.items.length < before, 'Remove updates native cart');
        }
        await waitBadge(page, 0);
        const empty = page.locator('.wc-block-cart__empty-cart__title,.wp-block-woocommerce-empty-cart-block').first(); await empty.waitFor();
        await go(page, '?post_type=product'); await page.locator('.wc-block-mini-cart__button').click(); await page.locator('.wc-block-mini-cart__drawer').waitFor();
        assert.equal(await page.locator('.wc-block-mini-cart__drawer .wc-block-cart-items__row').count(), 0);
        assert.match(await page.locator('.wc-block-mini-cart__drawer').innerText(), /vac[ií]o|no hay|no ten[eé]s/i);
        await page.keyboard.press('Escape');
        return { emptyState: true, badgeHidden: true, nativeMiniCartEmpty: true, itemsRemaining: (await api.data()).items_count };
      }, page);

      if (engine === 'chromium') await check('chromium/mobile-touch-native-cart-carousel', async () => {
        const touchContext = await browser.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, deviceScaleFactor: 2 });
        const touch = await touchContext.newPage(); watch(touch, engine); const touchAPI = cartAPI(touchContext);
        try {
          await touchAPI.clear(); await addSimple(touch, 1); await go(touch, '?page_id=8');
          await touch.locator('.wc-block-components-quantity-selector__button--plus').tap(); await waitCart(touch, touchAPI, c => c.items_count === 2, 'Touch plus adds one'); await waitBadge(touch, 2);
          const carousel = touch.locator('.cb-cart-cross-sells .is-product-collection-layout-carousel,.wp-block-woocommerce-cart-cross-sells-block .is-product-collection-layout-carousel').first();
          let swipe = 'not-present';
          if (await carousel.count()) {
            await carousel.scrollIntoViewIfNeeded();
            if (await carousel.evaluate(n => n.scrollWidth > n.clientWidth + 2)) {
              const box = await carousel.boundingBox(); const client = await touchContext.newCDPSession(touch);
              const x = box.x + box.width * .8, y = Math.min(800, box.y + Math.min(120, box.height / 2));
              await client.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x, y }] });
              for (let i = 1; i <= 6; i++) await client.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: x - i * box.width * .09, y }] });
              await client.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
              await touch.waitForFunction(() => document.querySelector('.cb-cart-cross-sells .is-product-collection-layout-carousel,.wp-block-woocommerce-cart-cross-sells-block .is-product-collection-layout-carousel')?.scrollLeft > 1);
              swipe = 'native-scroll-advanced';
            } else swipe = 'all-cards-fit';
          }
          assert.ok(await touch.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
          await capture(touch, 'cart-chromium-touch-390');
          return { touchPlus: true, carouselSwipe: swipe, deviceScaleFactor: 2 };
        } finally { try { await touchAPI.clear(); } finally { await touchContext.close(); } }
      }, page);
    } catch (error) {
      report.checks.push({ name: engine + '/setup-or-cleanup', result: 'failed', error: error.message, stack: error.stack });
    } finally {
      if (api) {
        if (page && !page.isClosed()) await settle(page).catch(error => report.errors.push({ engine, check: 'cleanup-network', error: error.message }));
        try { await api.clear(); report.cleanup.push({ engine, isolatedCartEmpty: true }); }
        catch (error) { report.cleanup.push({ engine, isolatedCartEmpty: false, error: error.message }); report.errors.push({ engine, check: 'cleanup', error: error.message }); }
      }
      await context?.close().catch(() => {}); await browser?.close().catch(() => {}); saveReport();
    }
    console.log(engine, 'complete');
  }
  report.finished = new Date().toISOString(); saveReport();
  console.log(JSON.stringify({ passed: report.passed, failed: report.failed, errors: report.errors.length, report: path.join(output, reportName) }));
  if (report.failed || report.errors.length) process.exitCode = 1;
})().catch(error => { report.errors.push({ check: activeCheck, error: error.message, stack: error.stack }); saveReport(); console.error(error.message); process.exitCode = 1; });
