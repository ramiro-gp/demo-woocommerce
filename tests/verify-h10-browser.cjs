/** H10 local, public-storefront QA. No order/account/legal submission or real payment. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
process.env.PLAYWRIGHT_BROWSERS_PATH ||= path.join(process.env.LOCALAPPDATA || 'C:\\Users\\ramiro\\AppData\\Local', 'ms-playwright');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const out = path.join(root, 'docs/evidence/h10');
fs.mkdirSync(out, { recursive: true });
const widths = [390, 768, 1366, 1920];
const report = {
  result: 'running', stores: [], matrix: [], checks: [], failures: [], pageErrors: [],
  localResourceErrors: [], consoleErrors: [], externalRequests: [], screenshots: [],
  limitations: [
    'Guest contexts: account/order/address creation, BACS submission, mail and legal administration are verified by separate H10 functional checks.',
    'External browser requests are inventoried and blocked; a blocked request does not certify provider functionality.',
    'Automated geometry/focus/axe checks and screenshots require human review; no WCAG or real-device certification.',
    'The test adds fixture products only to transient guest sessions; it does not create orders or decrement stock.'
  ]
};
const selectedKeys = process.env.H10_STORES?.split(',').map(s => s.trim()).filter(Boolean);
const origins = [
  { key: 'trama', base: process.env.H10_TRAMA_URL || 'http://localhost:8080', brand: 'TRAMA' },
  { key: 'origen', base: process.env.H10_ORIGEN_URL || 'http://localhost:8082', brand: 'ORIGEN CASA' }
];
assert.ok(!selectedKeys || selectedKeys.every(key => origins.some(s => s.key === key)), 'H10_STORES accepts trama,origen');
const targets = selectedKeys ? origins.filter(s => selectedKeys.includes(s.key)) : origins;
const headerOnly = process.env.H10_HEADER_ONLY === '1';
const reportFilename = headerOnly ? 'browser-header.json' : selectedKeys ? 'browser-' + targets.map(s => s.key).join('-') + '.json' : 'browser.json';
for (const store of origins) {
  const u = new URL(store.base);
  assert.ok(['localhost', '127.0.0.1'].includes(u.hostname), 'H10 browser QA is local only');
  store.base = u.origin;
}
assert.notEqual(origins[0].base, origins[1].base, 'Stores must have independent origins');
const mpPattern = /mercadopago|mercadolibre|mlstatic|mercado-pago/i;
const check = (name, pass, details) => {
  const row = { name, passed: Boolean(pass) };
  if (details !== undefined) row.details = details;
  report.checks.push(row);
  if (!pass) report.failures.push(row);
};
const unique = rows => [...new Set(rows)];
let browser;
let axe;
for (const candidate of [
  path.join(root, '.local/h9-tools/node_modules/axe-core/axe.min.js'),
  path.join(process.env.USERPROFILE || 'C:\\Users\\ramiro', '.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/axe-core/axe.min.js')
]) if (fs.existsSync(candidate)) { axe = fs.readFileSync(candidate, 'utf8'); break; }

async function json(c, url) {
  const r = await c.request.get(url);
  assert.ok(r.ok(), 'Public discovery endpoint ' + new URL(url).pathname + ' HTTP ' + r.status());
  return r.json();
}
async function discover(c, store) {
  const [products, pages] = await Promise.all([
    json(c, store.base + '/?rest_route=/wc/store/v1/products&per_page=100'),
    json(c, store.base + '/?rest_route=/wp/v2/pages&per_page=100')
  ]);
  assert.ok(Array.isArray(products) && products.length, 'Published products available');
  assert.ok(Array.isArray(pages), 'Published pages available');
  const bySlug = slug => pages.find(p => p.slug === slug);
  const page = (slugs, content) => slugs.map(bySlug).find(Boolean) || pages.find(p => content.test(p.content?.rendered || ''));
  const found = {
    cart: page(['carrito', 'cart'], /wp-block-woocommerce-cart\b|woocommerce_cart/),
    checkout: page(['finalizar-compra', 'checkout'], /wp-block-woocommerce-checkout\b|woocommerce_checkout/),
    account: page(['mi-cuenta', 'my-account'], /woocommerce-account|woocommerce_my_account/),
    privacy: page(['privacidad-demo', 'politica-de-privacidad', 'privacy-policy'], /Política de privacidad|Privacidad y datos personales/),
    terms: page(['terminos', 'terminos-y-condiciones'], /Términos y condiciones/),
    withdrawal: page(['boton-de-arrepentimiento', 'arrepentimiento'], /cc-withdrawal-form/)
  };
  for (const [key, value] of Object.entries(found)) assert.ok(value?.link, store.key + ' ' + key + ' public page discovered');
  const simple = products.find(p => p.slug === (store.key === 'origen' ? 'jarron-tierra' : 'bolso-transito')) || products.find(p => p.type === 'simple' && p.is_in_stock && p.is_purchasable);
  const variable = products.find(p => p.slug === (store.key === 'origen' ? 'lampara-mesa-alba' : 'remera-base')) || products.find(p => p.type === 'variable' && p.is_in_stock);
  assert.ok(simple?.id && variable?.permalink, store.key + ' simple and variable products discovered');
  const soldout = products.find(p => !p.is_in_stock);
  const category = products.flatMap(p => p.categories || []).find(t => !['uncategorized', 'sin-categorizar'].includes(t.slug));
  assert.ok(category?.slug, 'Category discovered');
  for (const u of [...Object.values(found).map(p => p.link), simple.permalink, variable.permalink]) {
    assert.equal(new URL(u).origin, store.base, 'Discovered URL belongs to current store');
  }
  store.pages = found;
  store.simple = simple;
  store.variable = variable;
  store.soldout = soldout;
  store.products = products;
  store.routes = [
    ['home', store.base + '/'], ['catalog', store.base + '/?post_type=product'],
    ['category', store.base + '/?product_cat=' + encodeURIComponent(category.slug)],
    ['simple', simple.permalink], ['variable', variable.permalink],
    ...Object.entries(found).map(([key, p]) => [key, p.link])
  ];
  if (soldout) store.routes.push(['soldout', soldout.permalink]);
  report.stores.push({
    key: store.key, origin: store.base, brand: store.brand, products: products.map(p => ({ id: p.id, slug: p.slug, type: p.type, stock: p.is_in_stock })),
    categories: unique(products.flatMap(p => p.categories || []).map(c => c.slug)),
    routes: Object.fromEntries(store.routes)
  });
  if (store.key === 'origen') {
    check('ORIGEN publishes 8–12 distinct products', products.length >= 8 && products.length <= 12, products.length);
    check('ORIGEN includes at least two variable products', products.filter(p => p.type === 'variable').length >= 2);
    check('ORIGEN includes an out-of-stock product', Boolean(soldout));
    check('ORIGEN includes a sale', products.some(p => p.on_sale));
    check('ORIGEN excludes original apparel SKUs/slugs', !products.some(p => /^(remera-|pantalon-|bolso-transito|gorro-punto|medias-canal|panuelo-pliegue)/.test(p.slug)));
  }
}
async function context(store, width, theme, js = true) {
  const c = await browser.newContext({ viewport: { width, height: 1000 }, colorScheme: theme, reducedMotion: 'reduce', javaScriptEnabled: js, serviceWorkers: 'block' });
  await c.addInitScript(mode => { try { localStorage.setItem('commerce-base-color-mode', mode); } catch (_) {} }, theme);
  await c.route(/^https?:\/\//, r => {
    const url = new URL(r.request().url());
    if (url.origin === store.base) return r.continue();
    report.externalRequests.push({ store: store.key, page: new URL(r.request().frame()?.url() || store.base).pathname, origin: url.origin, path: url.pathname, type: r.request().resourceType() });
    return r.abort();
  });
  c.on('page', p => {
    p.on('pageerror', e => report.pageErrors.push({ store: store.key, message: e.message }));
    p.on('console', m => { if (m.type() === 'error' && !/ERR_FAILED|ERR_BLOCKED|net::ERR/.test(m.text())) report.consoleErrors.push({ store: store.key, message: m.text() }); });
    p.on('response', r => { if (r.status() >= 400 && new URL(r.url()).origin === store.base) report.localResourceErrors.push({ store: store.key, status: r.status(), path: new URL(r.url()).pathname, type: r.request().resourceType() }); });
  });
  return c;
}
async function go(p, url) {
  const r = await p.goto(url, { waitUntil: 'networkidle', timeout: 45000 });
  assert.ok(r && r.status() < 400, 'Navigation ' + url);
  await p.evaluate(async () => { await document.fonts.ready; });
}
async function screenshot(p, name) {
  const filename = name + '.png';
  await p.screenshot({ path: path.join(out, filename), fullPage: true });
  report.screenshots.push(filename);
}
async function matrix(store) {
  for (const width of widths) for (const theme of ['light', 'dark']) {
    const c = await context(store, width, theme);
    const p = await c.newPage();
    await go(p, store.base + '/?add-to-cart=' + store.simple.id);
    for (const [name, url] of store.routes) {
      await go(p, url);
      if (name === 'checkout') await p.getByText(/Transferencia bancaria/).first().waitFor({ timeout: 20000 });
      const data = await p.evaluate(() => {
        const visible = e => e.getBoundingClientRect().width > 0 && e.getBoundingClientRect().height > 0;
        return {
          title: document.title, h1: [...document.querySelectorAll('h1')].map(e => e.textContent.trim()),
          theme: document.documentElement.dataset.theme, width: innerWidth, scrollWidth: document.documentElement.scrollWidth,
          main: document.querySelectorAll('main').length,
          headerBrand: document.querySelector('.cb-header .cb-brand')?.getAttribute('aria-label') || document.querySelector('.cb-brand')?.textContent?.trim(),
          bodyHasTrama: /\bTRAMA\b/i.test(document.body.innerText),
          brokenImages: [...document.images].filter(e => visible(e) && e.complete && e.naturalWidth === 0 && new URL(e.currentSrc || e.src).origin === location.origin).map(e => new URL(e.currentSrc || e.src).pathname),
          blockedExternalImages: [...document.images].filter(e => visible(e) && e.complete && e.naturalWidth === 0 && new URL(e.currentSrc || e.src).origin !== location.origin).map(e => ({ origin: new URL(e.currentSrc || e.src).origin, path: new URL(e.currentSrc || e.src).pathname })),
          missingAlt: [...document.images].filter(e => visible(e) && !e.hasAttribute('alt')).length,
          scriptOrigins: [...document.querySelectorAll('script[src],link[href]')].map(e => e.src || e.href).filter(u => /^https?:/.test(u)).map(u => ({ origin: new URL(u).origin, path: new URL(u).pathname })),
          attrs: [...document.querySelectorAll('.variations select')].map(e => ({ name: e.name, options: [...e.options].filter(o => o.value).map(o => o.text) })),
          filterLabels: [...document.querySelectorAll('.cb-filters h3')].map(e => e.textContent.trim()),
          availablePurchaseButtons: [...document.querySelectorAll('.single_add_to_cart_button')].filter(e => !e.disabled && !e.classList.contains('disabled')).length,
          stockText: [...document.querySelectorAll('.stock')].map(e => e.textContent.trim()),
          exitIntent: document.querySelectorAll('[data-cb-exit-intent]').length,
          bodyFont: getComputedStyle(document.body).fontFamily,
          background: getComputedStyle(document.body).backgroundColor,
          headerControls: [...document.querySelectorAll('.cb-header a,.cb-header button,.cb-header select')].filter(visible).map(e => { const r = e.getBoundingClientRect(); return { name: e.getAttribute('aria-label') || e.textContent.trim(), left: r.left, right: r.right, top: r.top, bottom: r.bottom, width: r.width, height: r.height }; }),
          overflowElements: [...document.querySelectorAll('main *')].filter(e => visible(e) && getComputedStyle(e).position !== 'fixed' && e.getBoundingClientRect().right > innerWidth + 2).slice(0, 8).map(e => ({ tag: e.tagName, class: String(e.className).slice(0, 100) }))
        };
      });
      const key = store.key + ' ' + name + ' ' + width + ' ' + theme;
      const expectedProduct = { simple: store.simple, variable: store.variable, soldout: store.soldout }[name];
      if (expectedProduct) {
        const expectedName = await p.evaluate(value => { const e = document.createElement('textarea'); e.innerHTML = value; return e.value; }, expectedProduct.name);
        check(key + ': product title matches public catalog', data.h1.length === 1 && data.h1[0] === expectedName, { expected: expectedName, actual: data.h1 });
        assert.ok(data.h1.length === 1 && data.h1[0] === expectedName, key + ': product page must render its own product, not a coming-soon page');
      }
      check(key + ': no horizontal document overflow', data.scrollWidth <= data.width + 1, data.overflowElements);
      check(key + ': visible header controls stay inside viewport', data.headerControls.every(r => r.left >= -1 && r.right <= data.width + 1 && r.top >= -1 && r.bottom <= 1001), data.headerControls);
      check(key + ': one H1', data.h1.length === 1, data.h1);
      check(key + ': one main', data.main === 1);
      check(key + ': correct brand', (data.headerBrand || '').includes(store.brand));
      check(key + ': correct color mode', data.theme === theme, data.theme);
      check(key + ': local visible images loaded and all visible images have alt', data.brokenImages.length === 0 && data.missingAlt === 0, { broken: data.brokenImages, missingAlt: data.missingAlt });
      if (store.key === 'origen') {
        check(key + ': no TRAMA identity in public body', !data.bodyHasTrama);
        check(key + ': no Mercado Pago assets', !data.scriptOrigins.some(u => mpPattern.test(u.origin + u.path)));
      }
      if (['checkout', 'account', 'privacy', 'terms', 'withdrawal'].includes(name)) check(key + ': no exit-intent dialog', data.exitIntent === 0);
      if (name === 'variable' && store.key === 'origen') {
        check(key + ': actual non-apparel variation attributes', data.attrs.length >= 2 && data.attrs.every(a => !/talle|size/i.test(a.name)), data.attrs);
      }
      if (name === 'catalog' && store.key === 'origen') {
        check(key + ': native catalog filters use actual client attributes', data.filterLabels.includes('Material') && data.filterLabels.includes('Acabado') && !data.filterLabels.includes('Talle'), data.filterLabels);
      }
      if (name === 'soldout') check(key + ': unavailable stock has no enabled purchase control', data.availablePurchaseButtons === 0 && data.stockText.some(t => /Agotado/.test(t)), data.stockText);
      if (axe && [390, 1366].includes(width) && ['home', 'variable', 'checkout', 'withdrawal'].includes(name)) {
        await p.addScriptTag({ content: axe });
        data.accessibility = await p.evaluate(async () => {
          const a = await axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'] } });
          return a.violations.map(v => ({ id: v.id, impact: v.impact, help: v.help, targets: v.nodes.map(n => n.target) }));
        });
        check(key + ': no serious/critical axe findings', !data.accessibility.some(v => ['serious', 'critical'].includes(v.impact)), data.accessibility);
      }
      report.matrix.push({ store: store.key, page: name, width, theme, ...data });
      if (['home', 'variable'].includes(name) || (width === 390 && theme === 'dark' && ['cart', 'checkout', 'account', 'withdrawal'].includes(name)) || (width === 1366 && theme === 'light' && ['catalog', 'category', 'privacy', 'terms'].includes(name))) {
        await screenshot(p, store.key + '-' + name + '-' + width + '-' + theme);
      }
    }
    await c.close();
  }
}
async function headerReview(store) {
  for (const width of widths) for (const theme of ['light', 'dark']) {
    const c = await context(store, width, theme);
    const p = await c.newPage();
    await go(p, store.base + '/?add-to-cart=' + store.simple.id);
    await go(p, store.base + '/');
    const data = await p.evaluate(() => {
      const visible = e => e.getBoundingClientRect().width > 0 && e.getBoundingClientRect().height > 0;
      const controls = [...document.querySelectorAll('.cb-header a,.cb-header button,.cb-header select')].filter(visible).map(e => { const r=e.getBoundingClientRect(); return { name:e.getAttribute('aria-label')||e.textContent.trim(),left:r.left,right:r.right,top:r.top,bottom:r.bottom,width:r.width,height:r.height }; });
      const legal = document.querySelector('.cb-legal-access')?.getBoundingClientRect();
      const eyebrow = document.querySelector('.oc-hero .cb-eyebrow')?.getBoundingClientRect();
      return { width: innerWidth, scrollWidth: document.documentElement.scrollWidth, controls, legalBottom:legal?.bottom, heroEyebrowTop:eyebrow?.top };
    });
    const key = store.key + ' header ' + width + ' ' + theme;
    check(key + ': all visible actionable controls inside viewport', data.controls.every(r => r.left >= -1 && r.right <= width + 1 && r.top >= -1 && r.bottom <= 1001), data.controls);
    check(key + ': no horizontal document overflow', data.scrollWidth <= width + 1);
    if (store.key === 'origen') check(key + ': hero clears legal bar', Number.isFinite(data.heroEyebrowTop) && data.heroEyebrowTop >= data.legalBottom, { legalBottom:data.legalBottom,heroEyebrowTop:data.heroEyebrowTop });
    report.matrix.push({ store: store.key, scope:'header', width, theme, ...data });
    if (width === 390) for (const [name,url] of store.routes) {
      if (!['home','variable'].includes(name) && !(theme === 'dark' && ['cart','checkout','account','withdrawal'].includes(name))) continue;
      await go(p,url);
      await screenshot(p,store.key+'-'+name+'-'+width+'-'+theme);
    }
    await c.close();
  }
}
async function keyboard(store) {
  const c = await context(store, 390, 'dark');
  const p = await c.newPage();
  await go(p, store.base + '/');
  await p.keyboard.press('Tab');
  check(store.key + ': skip link is first keyboard target', await p.evaluate(() => document.activeElement.matches('a[href="#main-content"]')));
  const focus = await p.evaluate(() => { const s = getComputedStyle(document.activeElement); return { outline: s.outlineStyle, width: s.outlineWidth, shadow: s.boxShadow, visible: document.activeElement.getBoundingClientRect().top >= 0 }; });
  check(store.key + ': first keyboard focus visible', focus.visible && ((focus.outline !== 'none' && parseFloat(focus.width) > 0) || focus.shadow !== 'none'), focus);
  await p.keyboard.press('Enter');
  check(store.key + ': skip link focuses main', await p.locator('main').evaluate(e => e === document.activeElement));
  const opener = p.locator('[aria-label="Abrir menú"]').first();
  await opener.click();
  await p.locator('#cb-menu[open]').waitFor();
  check(store.key + ': menu receives focus', await p.locator('#cb-menu').evaluate(e => e.contains(document.activeElement)));
  for (let i = 0; i < 20; i++) await p.keyboard.press('Tab');
  check(store.key + ': menu retains keyboard focus', await p.locator('#cb-menu').evaluate(e => e.contains(document.activeElement)));
  await p.keyboard.press('Escape');
  await p.locator('#cb-menu').waitFor({ state: 'hidden' });
  check(store.key + ': Escape restores menu opener focus', await opener.evaluate(e => e === document.activeElement));
  await go(p, store.variable.permalink);
  const radios = p.locator('.cb-variation-options [role="radio"]:not(:disabled)');
  check(store.key + ': generic swatches available', await radios.count() > 0);
  if (await radios.count()) {
    await radios.first().focus();
    await p.keyboard.press('ArrowRight');
    check(store.key + ': variation keyboard selects radio', await p.locator('.cb-variation-options [aria-checked="true"]').count() > 0);
  }
  const selects = p.locator('.variations select');
  for (let i = 0; i < await selects.count(); i++) {
    const select = selects.nth(i);
    const value = await select.locator('option').evaluateAll(es => es.find(e => e.value && !e.disabled)?.value);
    assert.ok(value, 'Available native variation option');
    await select.selectOption(value, { force: true });
  }
  await p.locator('.single_add_to_cart_button:not(.disabled)').waitFor();
  check(store.key + ': all native selections resolve purchasable variation', await p.locator('input.variation_id').inputValue() !== '0');
  await p.locator('.single_add_to_cart_button').click();
  await p.waitForLoadState('networkidle');
  const mini = p.locator('.wc-block-mini-cart__button');
  await mini.click();
  await p.locator('.wc-block-mini-cart__drawer[aria-hidden="false"]').waitFor();
  await p.waitForFunction(() => document.querySelector('.wc-block-mini-cart__drawer').contains(document.activeElement));
  check(store.key + ': mini-cart opens and focuses', await p.locator('.wc-block-mini-cart__drawer').evaluate(e => getComputedStyle(e).visibility === 'visible'));
  await p.keyboard.press('Escape');
  await p.locator('.wc-block-mini-cart__drawer[aria-hidden="true"]').waitFor({ state: 'attached' });
  check(store.key + ': closed mini-cart hidden', await p.locator('.wc-block-mini-cart__drawer').evaluate(e => getComputedStyle(e).visibility === 'hidden'));
  await c.close();
  const nojs = await context(store, 390, 'light', false);
  const n = await nojs.newPage();
  await go(n, store.variable.permalink);
  check(store.key + ': native variation selects available without JS', await n.locator('.variations select').first().isVisible());
  await go(n, store.pages.withdrawal.link);
  check(store.key + ': withdrawal form available without JS', await n.locator('.cc-withdrawal-form').isVisible());
  await nojs.close();
}
function save() { fs.writeFileSync(path.join(out, reportFilename), JSON.stringify(report, null, 2) + '\n'); }
(async () => {
  browser = await chromium.launch({ headless: true });
  try {
    for (const store of targets) {
      const c = await context(store, 1366, 'light');
      await discover(c, store);
      await c.close();
      if (headerOnly) await headerReview(store);
      else { await matrix(store); await keyboard(store); }
      console.log(JSON.stringify({ store: store.key, cases: report.matrix.filter(r => r.store === store.key).length, failures: report.failures.length }));
      save();
    }
    check('No storefront uncaught JavaScript errors', report.pageErrors.length === 0, report.pageErrors);
    check('No failed local resources', report.localResourceErrors.length === 0, report.localResourceErrors);
    check('No unexpected browser console errors', report.consoleErrors.length === 0, report.consoleErrors);
    check('ORIGEN never requests Mercado Pago resources', !report.externalRequests.some(r => r.store === 'origen' && mpPattern.test(r.origin + r.path)));
    check('Neither store loads resources from the other store', !report.externalRequests.some(r => origins.some(s => s.key !== r.store && s.base === r.origin)));
    report.externalRequests = [...new Map(report.externalRequests.map(r => [JSON.stringify(r), r])).values()];
    report.consoleErrors = [...new Map(report.consoleErrors.map(r => [JSON.stringify(r), r])).values()];
    report.result = report.failures.length ? 'needs-review' : 'passed';
    save();
    console.log(JSON.stringify({ result: report.result, cases: report.matrix.length, checks: report.checks.length, failures: report.failures.length, screenshots: report.screenshots.length, axeAvailable: Boolean(axe) }));
    if (report.failures.length) process.exitCode = 1;
  } finally { await browser.close(); }
})().catch(e => { report.result = 'failed'; report.error = e.message; save(); console.error(e.message); process.exitCode = 1; });
