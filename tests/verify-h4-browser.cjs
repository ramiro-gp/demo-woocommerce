/** Read-only H4 QA. NODE_PATH points to the bundled node_modules; artifacts stay private in .local. */
'use strict';

const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
process.env.PLAYWRIGHT_BROWSERS_PATH ||= 'C:\\Users\\ramiro\\AppData\\Local\\ms-playwright';
const { chromium } = require('playwright');

const base = new URL(process.env.H4_BASE_URL || 'http://localhost:8080');
const instance = process.env.H4_INSTANCE || 'local';
assert.match(instance, /^[a-z][a-z0-9-]{0,29}$/);
const scope = process.env.H4_SCOPE || 'full';
assert.ok(['full', 'home', 'search', 'no-js'].includes(scope), 'H4_SCOPE accepts full, home, search or no-js');
const output = path.resolve(__dirname, `../.local/${instance}/h4`);
fs.mkdirSync(output, { recursive: true });
const seedFile = path.join(output, 'content.json');
const seed = fs.existsSync(seedFile) ? JSON.parse(fs.readFileSync(seedFile, 'utf8').replace(/^\uFEFF/, '')) : {};
function seedUrl(key, fallback) {
	const value = seed.urls?.[key] || seed.pages?.[key]?.url || fallback;
	const parsed = new URL(value, base);
	assert.equal(parsed.origin, base.origin, `Seed ${key} must belong to the tested instance`);
	return parsed.href;
}
const searchUrl = seedUrl('search', '/?s=H2&post_type=product');
const emptySearchUrl = new URL(searchUrl);
emptySearchUrl.searchParams.set('s', 'sin-coincidencias-h4-83904');
emptySearchUrl.searchParams.set('post_type', 'product');
const routes = [
	{ key: 'home', url: seedUrl('home', '/'), selector: '.cb-home-hero', status: 200 },
	{ key: 'conocenos', url: seedUrl('conocenos', '/conocenos/'), selector: '.cb-page-intro', status: 200 },
	{ key: 'contacto', url: seedUrl('contacto', '/contacto/'), selector: '.cb-page-intro', status: 200 },
	{ key: 'faq', url: seedUrl('faq', '/preguntas-frecuentes/'), selector: '.cb-page-intro', status: 200 },
	{ key: 'search', url: searchUrl, selector: '.cb-search-results', status: 200 },
	{ key: 'search-empty', url: emptySearchUrl.href, selector: '.cb-search-results', status: 200 },
	{ key: '404', url: seedUrl('notFound', '/inexistente-h4/'), selector: '.cb-not-found', status: 404 },
];
const widths = [320, 375, 390, 430, 768, 1024, 1366, 1440, 1920, 2560, 3440, 3840];
const matrixRoutes = scope === 'home' ? routes.filter(route => route.key === 'home') : scope === 'search' ? routes.filter(route => route.key === 'search' || route.key === 'search-empty') : routes;
const reportName = scope === 'full' ? 'browser-qa.json' : `browser-qa-${scope}.json`;
const report = { startedAt: new Date().toISOString(), scope, baseUrl: base.href, seedFile: fs.existsSync(seedFile) ? seedFile : null, browser: 'Chromium', routes, widths, checks: [], screenshots: [], pageErrors: [], assetErrors: [], expectedNoJsScriptBlocks: [], notes: ['Product checks use the existing WooCommerce fixtures and Store API. They do not assert a final H5 catalog.', 'Reflow checks approximate 200%/400% zoom with CSS viewports and deviceScaleFactor; browser chrome zoom is not exercised.', 'Screenshots and JSON are private local evidence, not repository deliverables.'] };

async function check(name, task) {
	try {
		const details = await task();
		report.checks.push({ name, pass: true, details });
		console.log(`PASS ${name}`);
	} catch (error) {
		report.checks.push({ name, pass: false, error: error.message });
		console.error(`FAIL ${name}: ${error.message}`);
	}
}
function observe(page) {
	page.on('pageerror', error => report.pageErrors.push({ url: page.url(), message: error.message }));
	page.on('requestfailed', request => {
		if (page.cbQaNoJs && request.resourceType() === 'script' && request.failure()?.errorText === 'csp') { report.expectedNoJsScriptBlocks.push(request.url()); return; }
		if (new URL(request.url()).origin === base.origin && ['script', 'stylesheet', 'font', 'image'].includes(request.resourceType()) && !request.failure()?.errorText.includes('ERR_ABORTED')) report.assetErrors.push({ url: request.url(), error: request.failure()?.errorText });
	});
	page.on('response', response => {
		if (new URL(response.url()).origin === base.origin && response.status() >= 400 && ['script', 'stylesheet', 'font', 'image'].includes(response.request().resourceType())) report.assetErrors.push({ url: response.url(), status: response.status() });
	});
}
async function frame(page) { await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))); }
async function ready(page) {
	await page.waitForSelector('[data-cb-header]');
	if (page.cbQaNoJs) return; // A JS-disabled document cannot reliably schedule evaluated rAF/font promise callbacks.
	await page.evaluate(async () => { await document.fonts.ready; await Promise.all([document.fonts.load('400 18px Manrope'), document.fonts.load('400 32px Newsreader')]); });
	await frame(page);
}
async function go(page, route) {
	const response = await page.goto(route.url, { waitUntil: 'networkidle' });
	assert.ok(response, `Missing navigation response for ${route.key}`);
	assert.equal(response.status(), route.status, `${route.key} returned an unexpected HTTP status`);
	await ready(page);
	assert.equal(await page.locator(route.selector).count(), 1, `${route.key} did not render its H4 composition`);
	return response;
}
async function scrollTo(page, top) { await page.evaluate(y => window.scrollTo({ top: y, behavior: 'instant' }), top); await frame(page); }
async function screenshot(page, name, fullPage = false) {
	await page.screenshot({ path: path.join(output, name), fullPage, animations: 'disabled' });
	report.screenshots.push(name);
}
async function dialogOpen(page) {
	await page.waitForFunction(() => document.querySelector('[data-cb-menu]').open && document.querySelector('[data-cb-menu]').dataset.state === 'open');
	await page.waitForFunction(() => Math.abs(new DOMMatrix(getComputedStyle(document.querySelector('[data-cb-menu-panel]')).transform).m42) < 1);
}
async function dialogClosed(page) { await page.waitForFunction(() => !document.querySelector('[data-cb-menu]').open); }
async function layout(page) {
	return page.evaluate(() => {
		const visible = node => node.getClientRects().length > 0 && getComputedStyle(node).visibility !== 'hidden' && getComputedStyle(node).display !== 'none';
		const width = document.documentElement.clientWidth;
		const outside = [...document.querySelectorAll('main *, [data-cb-header] *, .cb-footer *')].filter(node => visible(node) && !node.closest('dialog, .screen-reader-text, .cb-skip-link')).filter(node => {
			const rect = node.getBoundingClientRect();
			if (rect.left >= -1 && rect.right <= width + 1) return false;
			// Report painted overflow, allowing deliberately cropped editorial artwork.
			let left = rect.left, right = rect.right;
			for (let ancestor = node.parentElement; ancestor; ancestor = ancestor.parentElement) {
				if (['hidden', 'clip', 'scroll', 'auto'].includes(getComputedStyle(ancestor).overflowX)) { const crop = ancestor.getBoundingClientRect(); left = Math.max(left, crop.left); right = Math.min(right, crop.right); }
			}
			return left < -1 || right > width + 1;
		}).slice(0, 12).map(node => ({ tag: node.tagName, class: node.getAttribute('class'), text: node.textContent.trim().slice(0, 60), left: node.getBoundingClientRect().left, right: node.getBoundingClientRect().right }));
		const headings = [...document.querySelectorAll('main h1')].filter(visible);
		const heading = headings[0]?.getBoundingClientRect();
		const header = document.querySelector('[data-cb-header]').getBoundingClientRect();
		const main = document.querySelector('main');
		const containerAlignment = main?.classList.contains('cb-container') ? { mainLeft: main.getBoundingClientRect().left, headerContentLeft: document.querySelector('[data-cb-header] .cb-container').getBoundingClientRect().left } : null;
		let headingOpacity = headings[0] ? 1 : 0;
		for (let node = headings[0]; node; node = node.parentElement) headingOpacity *= Number(getComputedStyle(node).opacity);
		return { width, scrollWidth: document.documentElement.scrollWidth, outside, mainCount: document.querySelectorAll('main').length, h1Count: headings.length, h1: headings[0]?.textContent.trim(), headingTop: heading?.top, headerBottom: header.bottom, bodyFont: parseFloat(getComputedStyle(document.body).fontSize), headingOpacity, containerAlignment };
	});
}
function assertLayout(result) {
	assert.ok(result.scrollWidth <= result.width + 1, `Horizontal document overflow: ${JSON.stringify(result)}`);
	assert.deepEqual(result.outside, [], `Painted content exceeds viewport: ${JSON.stringify(result.outside)}`);
	assert.equal(result.mainCount, 1, 'Expected one main landmark');
	assert.equal(result.h1Count, 1, 'Expected one visible H1 within main');
	assert.ok(result.h1?.length, 'Page title is empty');
	assert.ok(result.headingTop >= result.headerBottom - 1, `Title is obscured by the header: ${JSON.stringify(result)}`);
	assert.ok(result.headingOpacity > 0, 'Page title depends on an unfinished reveal');
	assert.ok(result.bodyFont >= 16, `Body font is only ${result.bodyFont}px`);
	if (result.containerAlignment) assert.ok(Math.abs(result.containerAlignment.mainLeft - result.containerAlignment.headerContentLeft) <= 1, `Page container lost its shared gutter: ${JSON.stringify(result.containerAlignment)}`);
}
async function contentAudit(page) {
	return page.evaluate(() => {
		const visible = node => node.getClientRects().length > 0 && getComputedStyle(node).visibility !== 'hidden';
		const unnamed = [...document.querySelectorAll('button')].filter(visible).filter(node => !node.getAttribute('aria-label') && !node.getAttribute('aria-labelledby') && !node.textContent.trim()).map(node => node.outerHTML);
		const unlabeled = [...document.querySelectorAll('input:not([type="hidden"]),select,textarea')].filter(node => !node.labels?.length && !node.getAttribute('aria-label') && !node.getAttribute('aria-labelledby')).map(node => node.id || node.outerHTML);
		const missingAlt = [...document.querySelectorAll('main img')].filter(node => !node.hasAttribute('alt')).map(node => node.src);
		const headingLevels = [...document.querySelectorAll('main h1,main h2,main h3,main h4,main h5,main h6')].filter(visible).map(node => Number(node.tagName.slice(1)));
		const jumps = headingLevels.filter((level, index) => index && level > headingLevels[index - 1] + 1);
		const unsafeExternal = [], wrongInternalTarget = [], missingAnchor = [];
		for (const link of document.querySelectorAll('a[href]')) {
			const url = new URL(link.href), rel = new Set(link.rel.split(/\s+/));
			if (/^https?:$/.test(url.protocol) && url.origin !== location.origin && (link.target !== '_blank' || !rel.has('noopener') || !rel.has('noreferrer'))) unsafeExternal.push(link.outerHTML);
			if (url.origin === location.origin && link.target && link.target !== '_self') wrongInternalTarget.push(link.outerHTML);
			if (url.origin === location.origin && url.pathname === location.pathname && url.search === location.search && url.hash && !document.getElementById(decodeURIComponent(url.hash.slice(1)))) missingAnchor.push(link.href);
		}
		return { unnamed, unlabeled, missingAlt, headingLevels, jumps, unsafeExternal, wrongInternalTarget, missingAnchor, headerCount: document.querySelectorAll('header[data-cb-header]').length, footerCount: document.querySelectorAll('footer.cb-footer').length, credit: document.querySelector('.cb-footer-credit')?.textContent.trim(), currency: document.querySelector('.cb-footer-bottom')?.textContent.includes('ARS') };
	});
}
function assertContent(result) {
	for (const field of ['unnamed', 'unlabeled', 'missingAlt', 'jumps', 'unsafeExternal', 'wrongInternalTarget', 'missingAnchor']) assert.deepEqual(result[field], [], `${field}: ${JSON.stringify(result[field])}`);
	assert.equal(result.headerCount, 1); assert.equal(result.footerCount, 1);
	assert.match(result.credit || '', /Hecho con.*por Ramiro Garcia/);
	assert.equal(result.currency, true, 'ARS disclosure missing');
}
async function contrastAudit(page) {
	return page.evaluate(() => {
		const rgb = color => { const values = color.match(/[\d.]+/g)?.map(Number); return values?.length >= 3 ? [...values.slice(0, 3), values[3] ?? 1] : null; };
		const luminance = color => color.slice(0, 3).map(channel => channel / 255).map(channel => channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4).reduce((sum, channel, index) => sum + channel * [0.2126, 0.7152, 0.0722][index], 0);
		const failures = [], samples = [], skippedImageBackgrounds = [];
		for (const node of document.querySelectorAll('main h1,main h2,main h3,main p,main summary,main .cb-button,main .wp-block-button__link,main .cb-link,.cb-footer-credit a,.cb-footer-column a')) {
			if (!node.getClientRects().length || !node.textContent.trim()) continue;
			const style = getComputedStyle(node), text = rgb(style.color);
			if (!text || text[3] < 1 || style.visibility === 'hidden') continue;
			let background = null, imageBackground = false;
			for (let ancestor = node; ancestor; ancestor = ancestor.parentElement) {
				const ancestorStyle = getComputedStyle(ancestor);
				if (ancestorStyle.backgroundImage !== 'none') imageBackground = true;
				const candidate = rgb(ancestorStyle.backgroundColor);
				if (candidate?.[3] === 1) { background = candidate; break; }
				if (candidate?.[3] > 0) { imageBackground = true; break; } // Requires visual compositing; do not claim an automated ratio.
			}
			if (imageBackground) { skippedImageBackgrounds.push(node.textContent.trim().slice(0, 40)); continue; }
			if (!background) continue;
			const [high, low] = [luminance(text), luminance(background)].sort((a, b) => b - a);
			const ratio = (high + 0.05) / (low + 0.05);
			const large = parseFloat(style.fontSize) >= 24 || (parseFloat(style.fontSize) >= 18.66 && Number(style.fontWeight) >= 700);
			const sample = { text: node.textContent.trim().slice(0, 60), ratio: Number(ratio.toFixed(2)), minimum: large ? 3 : 4.5 };
			samples.push(sample);
			if (ratio + 0.01 < sample.minimum) failures.push(sample);
		}
		return { failures, sampleCount: samples.length, minimumMeasured: Math.min(...samples.map(sample => sample.ratio)), skippedImageBackgrounds };
	});
}
async function allImages(page) {
	const images = page.locator('main img');
	for (let index = 0; index < await images.count(); index++) { await images.nth(index).scrollIntoViewIfNeeded(); }
	await page.waitForFunction(() => [...document.querySelectorAll('main img')].every(image => image.complete));
	const result = await images.evaluateAll(nodes => nodes.map(node => ({ src: node.currentSrc || node.src, width: node.naturalWidth, alt: node.getAttribute('alt') })));
	assert.ok(result.every(image => image.width > 0), `Broken image: ${JSON.stringify(result)}`);
	assert.ok(result.every(image => new URL(image.src).origin === base.origin || image.src.startsWith('data:')), `Image depends on a remote URL: ${JSON.stringify(result)}`);
	await scrollTo(page, 0);
	return result;
}
async function menuLifecycle(page, capture) {
	await scrollTo(page, Math.min(350, await page.evaluate(() => document.documentElement.scrollHeight - innerHeight)));
	const scrollStart = await page.evaluate(() => scrollY);
	const opener = page.locator('[data-cb-menu-open]:not([data-cb-menu-search-open])').first();
	await opener.click(); await dialogOpen(page);
	assert.equal(await page.locator('[data-cb-menu-close]').evaluate(node => document.activeElement === node), true);
	assert.equal(await opener.getAttribute('aria-expanded'), 'true');
	await page.evaluate(() => { const nodes = [...document.querySelector('[data-cb-menu]').querySelectorAll('a[href],button,input,select,textarea,[tabindex]')].filter(node => !node.disabled && node.tabIndex >= 0 && node.getClientRects().length); nodes.at(-1).focus(); });
	await page.keyboard.press('Tab');
	assert.equal(await page.locator('[data-cb-menu-close]').evaluate(node => document.activeElement === node), true, 'Tab escaped the dialog');
	await page.keyboard.press('Shift+Tab');
	assert.equal(await page.locator('[data-cb-menu]').evaluate(dialog => { const nodes = [...dialog.querySelectorAll('a[href],button,input,select,textarea,[tabindex]')].filter(node => !node.disabled && node.tabIndex >= 0 && node.getClientRects().length); return document.activeElement === nodes.at(-1); }), true, 'Reverse Tab escaped the dialog');
	await page.keyboard.press('Tab');
	assert.notEqual(await page.locator('[data-cb-menu-close]').evaluate(node => getComputedStyle(node).outlineStyle), 'none', 'Keyboard focus indicator is absent');
	const bodyTop = await page.locator('body').evaluate(node => node.style.top);
	await page.mouse.wheel(0, 500); await frame(page);
	assert.equal(await page.locator('body').evaluate(node => node.style.top), bodyTop, 'Body lock moved');
	if (capture) await screenshot(page, capture);
	await page.keyboard.press('Escape'); await dialogClosed(page);
	assert.equal(await opener.evaluate(node => document.activeElement === node), true, 'Escape did not return focus');
	assert.equal(await opener.getAttribute('aria-expanded'), 'false');
	assert.ok(Math.abs(await page.evaluate(() => scrollY) - scrollStart) <= 1, 'Menu did not restore scroll');
	return { scrollStart, focusReturned: true, tabTrap: true, reverseTabTrap: true, escape: true };
}

(async () => {
	const browser = await chromium.launch({ headless: true });
	try {
		if (scope !== 'no-js') for (const mode of ['light', 'dark']) {
			const context = await browser.newContext({ viewport: { width: 390, height: 844 }, colorScheme: mode });
			await context.addInitScript(value => localStorage.setItem('commerce-base-color-mode', value), mode);
			const page = await context.newPage(); observe(page);
			try {
				for (const route of matrixRoutes) {
					let loaded = false;
					await check(`Route ${route.key} / ${mode}: HTTP and composition`, async () => { await go(page, route); loaded = true; return { url: page.url(), status: route.status }; });
					if (!loaded) continue;
					for (const width of widths) {
						await check(`Responsive ${route.key} / ${mode} / ${width}px`, async () => {
							await page.setViewportSize({ width, height: width < 768 ? 844 : 1000 }); await scrollTo(page, 0);
							const result = await layout(page); assertLayout(result);
							assert.equal(await page.locator('html').getAttribute('data-theme'), mode);
							if (width === 390 || width === 1440) { await allImages(page); await screenshot(page, `${route.key}-${width}-${mode}-top.png`); await screenshot(page, `${route.key}-${width}-${mode}-full.png`, true); }
							return result;
							});
						}
					await check(`Content semantics, links, contrast and local images ${route.key} / ${mode}`, async () => { const result = await contentAudit(page); assertContent(result); const contrast = await contrastAudit(page); assert.ok(contrast.sampleCount > 0); assert.deepEqual(contrast.failures, [], `Insufficient text contrast: ${JSON.stringify(contrast.failures)}`); return { ...result, contrast, images: await allImages(page) }; });
					await check(`Menu context ${route.key} / ${mode} / mobile`, async () => { await page.setViewportSize({ width: 390, height: 844 }); return menuLifecycle(page, route.key === 'home' ? `menu-390-${mode}.png` : null); });
				}
			} finally { await context.close(); }
		}

		if (scope === 'full' || scope === 'home') await check('Desktop Home hero stays compact and exposes the product section', async () => {
			const context = await browser.newContext(); const page = await context.newPage(); observe(page);
			try {
				const samples = [];
				for (const viewport of [{ width: 1366, height: 768 }, { width: 1440, height: 900 }, { width: 1920, height: 1080 }, { width: 2560, height: 1440 }, { width: 3440, height: 1440 }, { width: 3840, height: 2160 }]) {
					await page.setViewportSize(viewport); await go(page, routes[0]);
					const geometry = await page.evaluate(() => ({ hero: document.querySelector('.cb-home-hero').getBoundingClientRect().toJSON(), products: document.querySelector('.cb-home-products').getBoundingClientRect().toJSON(), viewportHeight: innerHeight }));
					const ratio = geometry.hero.height / viewport.height;
					assert.ok(ratio >= 0.64 && ratio <= 0.75, `Hero should be approximately 65–72svh: ${viewport.width}x${viewport.height}, ${(ratio * 100).toFixed(1)}svh`);
					assert.ok(geometry.products.top < viewport.height, `Products do not start in the initial desktop viewport: ${JSON.stringify(geometry)}`);
					samples.push({ ...viewport, heroHeight: geometry.hero.height, svh: Number((ratio * 100).toFixed(2)), productsTop: geometry.products.top });
				}
				return samples;
			} finally { await context.close(); }
		});
		if (scope === 'full') {
		const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, colorScheme: 'dark' });
		const page = await context.newPage(); observe(page);
		try {
			await check('Home product selection matches official WooCommerce data', async () => {
				await go(page, routes[0]);
				assert.match((await page.locator('main h1').innerText()).replace(/\s+/g, ' '), /Vestir lo cotidiano\./);
				const order = await page.locator('main').evaluate(main => { const hero = main.querySelector('.cb-home-hero'), products = main.querySelector('.cb-home-products'); return { present: Boolean(hero && products), immediatelyAfterHero: Boolean(hero && products && hero.nextElementSibling === products), categories: main.querySelectorAll('.cb-category-section').length, story: main.querySelectorAll('.cb-story').length }; });
				assert.equal(order.present, true); assert.equal(order.immediatelyAfterHero, true, 'The first section after the hero should be live products'); assert.equal(order.categories, 1); assert.equal(order.story, 1);
				const response = await context.request.get(new URL('/?rest_route=/wc/store/v1/products&per_page=100', base).href);
				assert.equal(response.status(), 200, 'Official WooCommerce Store API unavailable');
				const products = await response.json();
				assert.ok(Array.isArray(products) && products.length > 0, 'Technical fixtures unavailable');
				const cards = await page.locator('.cb-home-products .cb-product-card').evaluateAll(nodes => nodes.map(node => ({ title: node.querySelector('.cb-product-title')?.textContent.trim(), href: node.querySelector('.cb-product-title a')?.href, prices: [...node.querySelectorAll('.cb-product-price .woocommerce-Price-amount')].map(price => price.textContent.trim()), stock: node.querySelector('.cb-product-status')?.textContent.trim() })));
				assert.ok(cards.length > 0 && cards.length <= 4, 'Home should have a compact live WooCommerce selection');
				for (const card of cards) {
					const product = products.find(candidate => candidate.permalink === card.href);
					assert.ok(product, `Home product ${card.href} is not in WooCommerce`);
					assert.equal(card.title, product.name);
					assert.ok(card.prices.length > 0, `Live price missing for ${card.title}`);
					const renderedMinorAmounts = card.prices.map(text => {
						const ungrouped = product.prices.currency_thousand_separator ? text.split(product.prices.currency_thousand_separator).join('') : text;
						const decimal = ungrouped.replace(product.prices.currency_decimal_separator, '.').replace(/[^0-9.-]/g, '');
						return String(Math.round(Number(decimal) * 10 ** product.prices.currency_minor_unit));
					});
					assert.ok(renderedMinorAmounts.includes(product.prices.price), `Home price differs from WooCommerce for ${card.title}: ${renderedMinorAmounts} vs ${product.prices.price}`);
					assert.equal(card.stock, product.is_in_stock ? 'Disponible' : 'Sin stock');
				}
				return { order, cards, fixtureCount: products.length };
			});
			await check('Desktop menu keyboard, focus and scroll lifecycle', async () => { await go(page, routes[0]); return menuLifecycle(page, 'menu-1440-system.png'); });
			await check('Navigation and footer point to real institutional routes', async () => {
				await go(page, routes[0]);
				const links = await page.locator('.cb-menu-nav a, .cb-footer a').evaluateAll(nodes => nodes.map(node => ({ href: node.href, text: node.textContent.trim() })));
				for (const key of ['conocenos', 'contacto', 'faq']) assert.ok(links.some(link => link.href === routes.find(route => route.key === key).url), `${key} missing from real navigation`);
				const unique = [...new Set(links.map(link => link.href).filter(href => new URL(href).origin === base.origin && !new URL(href).hash))];
				for (const href of unique) { const response = await context.request.get(href); assert.ok(response.status() >= 200 && response.status() < 400, `Broken navigation link ${href}: ${response.status()}`); }
				return links;
			});
			await check('Header search submits official product search and empty search recovers', async () => {
				await go(page, routes[0]);
				await page.locator('[data-cb-menu-search-open]').click(); await dialogOpen(page);
				assert.equal(await page.locator('#cb-menu-search').evaluate(node => document.activeElement === node), true);
				await page.locator('#cb-menu-search').fill('H2');
				await Promise.all([page.waitForURL(url => url.searchParams.get('s') === 'H2' && url.searchParams.get('post_type') === 'product'), page.locator('#cb-menu-search').press('Enter')]);
				await ready(page); assert.ok(await page.locator('.cb-search-results .wc-block-product').count() > 0);
				assert.match(await page.locator('main h1').innerText(), /H2/);
				const input = page.locator('main form[role="search"] input[name="s"]').first();
				assert.equal(await input.count(), 1, 'Results page must allow another search');
				await input.fill(emptySearchUrl.searchParams.get('s'));
				await Promise.all([page.waitForURL(url => url.searchParams.get('s') === emptySearchUrl.searchParams.get('s')), input.press('Enter')]);
					await ready(page); assert.equal(await page.locator('.cb-search-results .wc-block-product').count(), 0);
				assert.match(await page.locator('main').innerText(), /no encontramos|sin resultados|ningún producto/i);
				assert.ok(await page.locator('main a').filter({ hasText: /catálogo|tienda/i }).count() > 0, 'Empty search has no catalog recovery');
				return { searchTerm: 'H2', productOnly: true, emptyTerm: emptySearchUrl.searchParams.get('s'), recovery: true };
			});
			await check('Mobile FAQ responds to Enter and Space with visible keyboard focus', async () => {
				await page.setViewportSize({ width: 390, height: 844 }); await go(page, routes.find(route => route.key === 'faq'));
				const summaries = page.locator('main details > summary'); assert.ok(await summaries.count() >= 7, 'Expected seven useful FAQ entries');
				const text = await page.locator('main').innerText();
				for (const term of [/talle/i, /pago/i, /env[ií]o/i, /demora|tiempo/i, /cambi/i, /cuenta/i, /pedido/i]) assert.match(text, term);
				for (let index = 0; index < await summaries.count(); index++) {
					const summary = summaries.nth(index); await summary.focus();
					const initial = await summary.evaluate(node => node.parentElement.open);
					await page.keyboard.press('Enter'); await frame(page);
					assert.equal(await summary.evaluate(node => node.parentElement.open), !initial, `Enter failed FAQ ${index + 1}`);
					assert.equal(await summary.evaluate(node => node === document.activeElement), true);
					assert.notEqual(await summary.evaluate(node => getComputedStyle(node).outlineStyle), 'none');
					await page.keyboard.press('Space'); await frame(page);
					assert.equal(await summary.evaluate(node => node.parentElement.open), initial, `Space failed FAQ ${index + 1}`);
					assert.equal(await summary.getAttribute('aria-expanded'), null, 'Native details should not carry stale custom aria-expanded');
				}
				return { count: await summaries.count(), enter: true, space: true, focus: true, semantics: 'native details/summary' };
			});
			await check('System mode follows OS on every H4 route and manual mode persists', async () => {
				for (const route of routes) {
					await go(page, route); await page.locator('[data-cb-theme-select]').first().selectOption('system');
					for (const colorScheme of ['light', 'dark']) { await page.emulateMedia({ colorScheme }); await page.waitForFunction(mode => document.documentElement.dataset.theme === mode, colorScheme); assert.equal(await page.locator('html').getAttribute('data-color-mode'), 'system'); }
				}
				await page.locator('[data-cb-theme-select]').first().selectOption('light'); await page.reload({ waitUntil: 'networkidle' }); await ready(page);
				assert.equal(await page.locator('html').getAttribute('data-color-mode'), 'light'); assert.equal(await page.locator('html').getAttribute('data-theme'), 'light');
				return { osModes: ['light', 'dark'], routes: routes.length, manualPersistence: 'light' };
			});
			await check('Shop and existing product retain the integrated header', async () => {
				const api = await context.request.get(new URL('/?rest_route=/wc/store/v1/products&per_page=1', base).href); assert.equal(api.status(), 200);
				const [product] = await api.json(); assert.ok(product?.permalink);
				const urls = [seedUrl('shop', '/?post_type=product'), product.permalink];
				for (const url of urls) { const response = await page.goto(url, { waitUntil: 'networkidle' }); assert.equal(response.status(), 200); await ready(page); await scrollTo(page, 0); assert.equal(await page.locator('[data-cb-header]').count(), 1); assert.equal(await page.locator('[data-cb-header]').isVisible(), true); await menuLifecycle(page); }
				return urls;
			});
		} finally { await context.close(); }
		}

		if (scope === 'full') await check('Reduced motion preserves every H4 page and shortens all transitions', async () => {
			const reduced = await browser.newContext({ viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' });
			const page = await reduced.newPage(); observe(page);
			try {
				for (const route of routes) {
					await go(page, route); assertLayout(await layout(page));
					const result = await page.evaluate(() => ({ scroll: getComputedStyle(document.documentElement).scrollBehavior, longMotion: [...document.querySelectorAll('main *,[data-cb-menu],[data-cb-menu-panel]')].filter(node => [getComputedStyle(node).transitionDuration, getComputedStyle(node).animationDuration].some(value => value.split(',').some(duration => parseFloat(duration) > 0.02))).map(node => node.className).slice(0, 12) }));
					assert.equal(result.scroll, 'auto'); assert.deepEqual(result.longMotion, [], `${route.key}: reduced motion ignored`);
				}
				await menuLifecycle(page);
				return { routes: routes.length, maximumSeconds: 0.02, menu: true };
			} finally { await reduced.close(); }
		});
		if (scope !== 'home') await check('No-JavaScript preserves institutional content, navigation, search and FAQ', async () => {
			const noJs = await browser.newContext({ javaScriptEnabled: false, colorScheme: 'dark', viewport: { width: 390, height: 844 } });
			const page = await noJs.newPage(); page.cbQaNoJs = true; observe(page);
			const noJsRoutes = scope === 'search' ? matrixRoutes : routes;
			try {
				for (const route of noJsRoutes) {
					await go(page, route); assertLayout(await layout(page));
					assert.equal(await page.locator('.cb-no-js-nav').isVisible(), true);
					assert.equal(await page.locator('html').evaluate(node => getComputedStyle(node).colorScheme), 'dark', 'No-JS system theme fallback failed');
					assert.ok(await page.locator('.cb-no-js-nav a').count() >= 4);
					assert.equal(await page.locator('main').evaluate(node => getComputedStyle(node).opacity), '1');
					const invisible = await page.locator('main h1, main h2, main p, main .cb-product-card').evaluateAll(nodes => nodes.filter(node => getComputedStyle(node).opacity === '0' || getComputedStyle(node).visibility === 'hidden').map(node => node.textContent.trim().slice(0, 60)));
					assert.deepEqual(invisible, [], `No-JS content hidden on ${route.key}`);
				}
				if (scope !== 'search') {
					await go(page, routes.find(route => route.key === 'faq'));
					await page.locator('main details > summary').first().focus(); await page.keyboard.press('Enter');
					assert.equal(await page.locator('main details').first().evaluate(node => node.open), true);
				}
				await go(page, routes.find(route => route.key === 'search'));
				const input = page.locator('main form[role="search"] input[name="s"]').first();
				await input.fill(emptySearchUrl.searchParams.get('s')); await Promise.all([page.waitForURL(url => url.searchParams.get('s') === emptySearchUrl.searchParams.get('s')), input.press('Enter')]);
				assert.equal(await page.locator('.cb-search-results .wc-block-product').count(), 0);
				return { routes: noJsRoutes.length, nativeFAQ: scope !== 'search', nativeSearch: true, systemBackground: await page.locator('body').evaluate(node => getComputedStyle(node).backgroundColor) };
			} finally { await noJs.close(); }
		});
		if (scope === 'full' || scope === 'search') for (const factor of [2, 4]) {
			await check(`All H4 routes reflow at equivalent ${factor * 100}% desktop zoom`, async () => {
				const zoom = await browser.newContext({ viewport: { width: 1280 / factor, height: 900 / factor }, deviceScaleFactor: factor });
				const page = await zoom.newPage(); observe(page);
				const reflowRoutes = scope === 'search' ? matrixRoutes : routes;
				try {
					for (const route of reflowRoutes) { await go(page, route); assertLayout(await layout(page)); }
					await page.locator('[data-cb-menu-open]:not([data-cb-menu-search-open])').first().click(); await dialogOpen(page);
					const close = await page.locator('[data-cb-menu-close]').boundingBox(); assert.ok(close && close.y >= 0 && close.y + close.height <= 900 / factor + 1, 'Menu close inaccessible at short reflow height');
					await page.keyboard.press('Escape'); await dialogClosed(page);
					return { routes: reflowRoutes.length, factor, cssWidth: 1280 / factor };
				} finally { await zoom.close(); }
			});
		}
		await check('No uncaught JavaScript or local asset failures', async () => { assert.deepEqual(report.pageErrors, []); assert.deepEqual(report.assetErrors, []); });
	} finally {
		await browser.close();
		report.finishedAt = new Date().toISOString(); report.passed = report.checks.filter(check => check.pass).length; report.failed = report.checks.filter(check => !check.pass).length;
		fs.writeFileSync(path.join(output, reportName), JSON.stringify(report, null, 2) + '\n');
		console.log(`H4 browser QA (${scope}): ${report.passed} passed, ${report.failed} failed. ${path.join(output, reportName)}`);
		if (report.failed) process.exitCode = 1;
	}
})().catch(error => { console.error(error.stack); process.exitCode = 1; });
