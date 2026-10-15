/** Read-only H3 browser QA. NODE_PATH must point to the bundled node_modules. */
'use strict';

const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
process.env.PLAYWRIGHT_BROWSERS_PATH ||= 'C:\\Users\\ramiro\\AppData\\Local\\ms-playwright';
const { chromium } = require('playwright');

const base = new URL(process.env.H3_BASE_URL || 'http://localhost:8080');
const showcase = new URL('/?commerce-base-showcase=1', base).href;
const instance = process.env.H3_INSTANCE || 'local';
assert.match(instance, /^[a-z][a-z0-9-]{0,29}$/);
const output = path.resolve(__dirname, `../.local/${instance}/h3`);
fs.mkdirSync(output, { recursive: true });
const report = { startedAt: new Date().toISOString(), baseUrl: base.href, browser: 'Chromium', checks: [], screenshots: [], pageErrors: [], assetErrors: [], reflowTechnique: '1280px desktop at 200%/400% approximated by 640px/320px CSS viewports, with deviceScaleFactor 2/4. This checks reflow; it does not exercise browser chrome zoom.' };

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
		if (new URL(request.url()).origin === base.origin && ['script', 'stylesheet', 'font', 'image'].includes(request.resourceType()) && !request.failure()?.errorText.includes('ERR_ABORTED')) {
			report.assetErrors.push({ url: request.url(), error: request.failure()?.errorText });
		}
	});
	page.on('response', response => {
		if (new URL(response.url()).origin === base.origin && response.status() >= 400 && ['script', 'stylesheet', 'font', 'image'].includes(response.request().resourceType())) {
			report.assetErrors.push({ url: response.url(), status: response.status() });
		}
	});
}

async function ready(page) {
	await page.waitForSelector('[data-cb-header]');
	await page.evaluate(async () => { await document.fonts.ready; await Promise.all([document.fonts.load('400 18px Manrope'), document.fonts.load('400 32px Newsreader')]); });
	await frame(page);
}
async function frame(page) { await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))); }
async function scrollTo(page, top) { await page.evaluate(y => window.scrollTo({ top: y, behavior: 'instant' }), top); await frame(page); }
async function settledScroll(page) {
	return page.evaluate(() => new Promise((resolve, reject) => {
		let previous = window.scrollY, stable = 0;
		const started = performance.now();
		function tick() {
			const current = window.scrollY;
			stable = Math.abs(current - previous) < 0.5 ? stable + 1 : 0;
			previous = current;
			if (stable >= 8) resolve(current);
			else if (performance.now() - started > 5000) reject(new Error('Scroll did not settle in five seconds'));
			else requestAnimationFrame(tick);
		}
		requestAnimationFrame(tick);
	}));
}
async function screenshot(page, name, fullPage = false) {
	await page.screenshot({ path: path.join(output, name), fullPage, animations: 'disabled' });
	report.screenshots.push(name);
}
async function bounds(page) {
	return page.evaluate(() => {
		const width = document.documentElement.clientWidth;
		const visible = node => node.getClientRects().length > 0 && getComputedStyle(node).visibility !== 'hidden';
		const outside = [...document.querySelectorAll('main *, [data-cb-header] *')].filter(node => visible(node) && !node.closest('dialog, .screen-reader-text, .cb-skip-link')).filter(node => {
			const rect = node.getBoundingClientRect();
			return rect.left < -1 || rect.right > width + 1;
		}).slice(0, 12).map(node => ({ tag: node.tagName, class: node.className, text: node.textContent.trim().slice(0, 60), left: node.getBoundingClientRect().left, right: node.getBoundingClientRect().right }));
		return { width, scrollWidth: document.documentElement.scrollWidth, bodyFont: parseFloat(getComputedStyle(document.body).fontSize), outside };
	});
}
function assertBounds(result) {
	assert.ok(result.scrollWidth <= result.width + 1, `Document overflow: ${JSON.stringify(result)}`);
	assert.deepEqual(result.outside, [], `Visible content beyond viewport: ${JSON.stringify(result.outside)}`);
	assert.ok(result.bodyFont >= 16, `Body font is ${result.bodyFont}px`);
}
async function dialogState(page, selector, state) { await page.waitForFunction(({ selector, state }) => document.querySelector(selector).dataset.state === state, { selector, state }); }
async function closed(page, selector) { await page.waitForFunction(selector => !document.querySelector(selector).open, selector); }

(async () => {
	const browser = await chromium.launch({ headless: true });
	try {
		for (const mode of ['light', 'dark']) {
			const context = await browser.newContext({ viewport: { width: 390, height: 844 }, colorScheme: mode, reducedMotion: 'no-preference' });
			await context.addInitScript(value => localStorage.setItem('commerce-base-color-mode', value), mode);
			const page = await context.newPage(); observe(page);
			await page.goto(showcase, { waitUntil: 'networkidle' }); await ready(page);
			for (const width of [320, 375, 390, 430, 768, 1024, 1366, 1440, 1920, 2560, 3440, 3840]) {
				await check(`Responsive ${mode} ${width}px`, async () => {
					await page.setViewportSize({ width, height: width < 768 ? 844 : 1000 });
					await scrollTo(page, 0);
					const result = await bounds(page); assertBounds(result);
					assert.equal(await page.locator('html').getAttribute('data-theme'), mode);
					if (width === 390 || width === 1440) {
						await screenshot(page, `showcase-${width}-${mode}-top.png`);
						await screenshot(page, `showcase-${width}-${mode}-full.png`, true);
					}
					return result;
				});
			}
			await check(`Local fonts and accessible controls ${mode}`, async () => {
				const result = await page.evaluate(() => {
					const fonts = [...document.fonts].filter(font => /Manrope|Newsreader/.test(font.family)).map(font => ({ family: font.family, status: font.status }));
					const unnamed = [...document.querySelectorAll('button')].filter(button => !button.getAttribute('aria-label') && !button.getAttribute('aria-labelledby') && !button.textContent.trim()).map(button => button.outerHTML);
					const unlabeled = [...document.querySelectorAll('input:not([type="hidden"]), select, textarea')].filter(input => !input.labels?.length && !input.getAttribute('aria-label') && !input.getAttribute('aria-labelledby')).map(input => input.id || input.outerHTML);
					const smallButtons = [...document.querySelectorAll('.cb-icon-button, .cb-button')].filter(button => button.getClientRects().length && !button.closest('dialog')).filter(button => { const rect = button.getBoundingClientRect(); return rect.width < 43.9 || rect.height < 43.9; }).map(button => ({ text: button.textContent.trim(), class: button.className, rect: button.getBoundingClientRect().toJSON() }));
					return { fonts, unnamed, unlabeled, smallButtons };
				});
				assert.ok(result.fonts.some(font => /Manrope/.test(font.family) && font.status === 'loaded'), 'Manrope not loaded');
				assert.ok(result.fonts.some(font => /Newsreader/.test(font.family) && font.status === 'loaded'), 'Newsreader not loaded');
				assert.deepEqual(result.unnamed, []); assert.deepEqual(result.unlabeled, []); assert.deepEqual(result.smallButtons, []);
				return result;
			});
			await context.close();
		}

		const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, colorScheme: 'dark', reducedMotion: 'no-preference' });
		const page = await context.newPage(); observe(page);
		await page.goto(showcase, { waitUntil: 'networkidle' }); await ready(page);
		await check('System theme, manual persistence and storage synchronization', async () => {
			assert.equal(await page.locator('html').getAttribute('data-color-mode'), 'system');
			assert.equal(await page.locator('html').getAttribute('data-theme'), 'dark');
			await page.emulateMedia({ colorScheme: 'light' }); await page.waitForFunction(() => document.documentElement.dataset.theme === 'light');
			await page.locator('[data-cb-theme-select]').first().selectOption('dark');
			await page.reload({ waitUntil: 'networkidle' }); await ready(page);
			assert.equal(await page.locator('html').getAttribute('data-color-mode'), 'dark');
			await page.emulateMedia({ colorScheme: 'light' }); assert.equal(await page.locator('html').getAttribute('data-theme'), 'dark');
			assert.ok((await page.locator('[data-cb-theme-select]').evaluateAll(selects => selects.map(select => select.value))).every(value => value === 'dark'));
			const second = await context.newPage(); observe(second); await second.goto(showcase); await ready(second);
			await second.locator('[data-cb-theme-select]').first().selectOption('light');
			await page.waitForFunction(() => document.documentElement.dataset.colorMode === 'light');
			await second.close();
			return { initialSystem: 'dark', systemChange: 'light', persistedManual: 'dark', synchronizedTab: 'light' };
		});
		await check('Header scroll progression 0 / 0.5 / 1', async () => {
			const values = [];
			for (const y of [0, 70, 140, 280]) { await scrollTo(page, y); values.push(await page.evaluate(() => Number(document.documentElement.style.getPropertyValue('--cb-header-progress')))); }
			[0, 0.5, 1, 1].forEach((expected, index) => assert.ok(Math.abs(values[index] - expected) < 0.015, `Progress ${values[index]} at sample ${index}`));
			return values;
		});

		for (const width of [1440, 390]) {
			await check(`Fullscreen menu lifecycle and keyboard ${width}px`, async () => {
				await page.setViewportSize({ width, height: width === 390 ? 844 : 1000 });
				await page.locator('[data-cb-theme-select]').first().selectOption(width === 390 ? 'dark' : 'light');
				await scrollTo(page, 350);
				const opener = page.locator('[data-cb-menu-open]:not([data-cb-menu-search-open])').first();
				await opener.click(); await dialogState(page, '[data-cb-menu]', 'open');
				await page.waitForFunction(() => Math.abs(new DOMMatrix(getComputedStyle(document.querySelector('[data-cb-menu-panel]')).transform).m42) < 1);
				assert.equal(await page.locator('[data-cb-menu-close]').evaluate(node => node === document.activeElement), true);
				await screenshot(page, `menu-${width}.png`);
				await page.evaluate(() => {
					const dialog = document.querySelector('[data-cb-menu]');
					const nodes = [...dialog.querySelectorAll('a[href],button,input,select,textarea,[tabindex]')].filter(node => !node.disabled && node.tabIndex >= 0 && node.getClientRects().length);
					nodes[nodes.length - 1].focus();
				});
				await page.keyboard.press('Tab'); assert.equal(await page.locator('[data-cb-menu-close]').evaluate(node => node === document.activeElement), true);
				await page.keyboard.press('Shift+Tab');
				assert.equal(await page.locator('[data-cb-menu]').evaluate(dialog => { const nodes = [...dialog.querySelectorAll('a[href],button,input,select,textarea,[tabindex]')].filter(node => !node.disabled && node.tabIndex >= 0 && node.getClientRects().length); return document.activeElement === nodes[nodes.length - 1]; }), true);
				await page.keyboard.press('Tab');
				assert.equal(await page.locator('[data-cb-menu-close]').evaluate(node => getComputedStyle(node).outlineStyle !== 'none'), true, 'Keyboard focus indicator absent');
				const bodyTop = await page.locator('body').evaluate(body => body.style.top);
				await page.mouse.wheel(0, 500); await frame(page);
				assert.equal(await page.locator('body').evaluate(body => body.style.top), bodyTop, 'Document lock moved');
				const inverse = await page.evaluate(() => { document.querySelector('[data-cb-menu-close]').click(); const dialog = document.querySelector('[data-cb-menu]'); return { open: dialog.open, state: dialog.dataset.state, transition: getComputedStyle(dialog).transitionDuration }; });
				assert.equal(inverse.open, true); assert.equal(inverse.state, 'closing');
				await closed(page, '[data-cb-menu]');
				assert.equal(await opener.evaluate(node => document.activeElement === node), true);
				assert.ok(Math.abs(await page.evaluate(() => window.scrollY) - 350) <= 1, 'Scroll position not restored');
				await opener.click(); await dialogState(page, '[data-cb-menu]', 'open'); await page.keyboard.press('Escape'); await closed(page, '[data-cb-menu]');
				assert.equal(await opener.getAttribute('aria-expanded'), 'false');
				return { inverse, focusReturned: true, scrollRestored: true, nativeTabTrap: true, escape: true };
			});
		}
		await check('Mobile menu keeps an opaque stationary masthead through opening and closing', async () => {
			await page.setViewportSize({ width: 390, height: 844 });
			for (const mode of ['light', 'dark']) {
				await page.locator('[data-cb-theme-select]').first().selectOption(mode);
				await scrollTo(page, 0);
				await page.locator('.cb-menu-trigger').click();
				await dialogState(page, '[data-cb-menu]', 'open');
				await page.waitForFunction(() => Math.abs(new DOMMatrix(getComputedStyle(document.querySelector('[data-cb-menu-panel]')).transform).m42) < 1);
				const samples = await page.evaluate(async () => {
					document.querySelector('[data-cb-menu-close]').click();
					const values = [];
					while (document.querySelector('[data-cb-menu]').open) {
						const masthead = document.querySelector('.cb-menu-masthead');
						values.push({ top: masthead.getBoundingClientRect().top, height: masthead.getBoundingClientRect().height, background: getComputedStyle(masthead).backgroundColor, opacity: Number(getComputedStyle(document.querySelector('[data-cb-header]'), '::before').opacity), y: new DOMMatrix(getComputedStyle(document.querySelector('[data-cb-menu-panel]')).transform).m42 });
						await new Promise(requestAnimationFrame);
					}
					return values;
				});
				assert.ok(samples.length > 2 && samples.some(sample => sample.y < -1));
				assert.ok(samples.every(sample => sample.top === 0 && sample.height >= 80 && sample.opacity === 1 && sample.background !== 'rgba(0, 0, 0, 0)'), JSON.stringify(samples));
				await page.waitForTimeout(280);
				assert.equal(await page.locator('[data-cb-header]').evaluate(node => Number(getComputedStyle(node, '::before').opacity)), 0);
			}
		});
		await check('Reopen during closing and search focus', async () => {
			await page.setViewportSize({ width: 1440, height: 1000 });
			await page.locator('[data-cb-menu-search-open]').click(); await dialogState(page, '[data-cb-menu]', 'open');
			assert.equal(await page.locator('#cb-menu-search').evaluate(node => document.activeElement === node), true);
			await page.evaluate(() => { document.querySelector('[data-cb-menu-close]').click(); document.querySelector('[data-cb-menu-open]:not([data-cb-menu-search-open])').click(); });
			await dialogState(page, '[data-cb-menu]', 'open');
			await page.waitForTimeout(500);
			assert.equal(await page.locator('[data-cb-menu]').evaluate(dialog => dialog.open), true);
			await page.keyboard.press('Escape'); await closed(page, '[data-cb-menu]');
		});
		await check('Filter drawer inverse, Escape, focus and scroll restoration', async () => {
			await page.setViewportSize({ width: 390, height: 844 });
			const trigger = page.locator('[data-cb-filter-open]'); await trigger.scrollIntoViewIfNeeded(); await settledScroll(page);
			const start = await page.evaluate(() => window.scrollY);
			await trigger.click(); await dialogState(page, '[data-cb-filter-dialog]', 'open');
			assert.equal(await page.locator('[data-cb-filter-close]').evaluate(node => document.activeElement === node), true);
			const inverse = await page.evaluate(() => { document.querySelector('[data-cb-filter-close]').click(); const dialog = document.querySelector('[data-cb-filter-dialog]'); return { open: dialog.open, state: dialog.dataset.state }; });
			assert.equal(inverse.open, true); assert.equal(inverse.state, 'closing'); await closed(page, '[data-cb-filter-dialog]');
			assert.equal(await trigger.evaluate(node => document.activeElement === node), true);
			assert.ok(Math.abs(await page.evaluate(() => window.scrollY) - start) <= 1);
			await trigger.click(); await dialogState(page, '[data-cb-filter-dialog]', 'open'); await page.keyboard.press('Escape'); await closed(page, '[data-cb-filter-dialog]');
			return { inverse, scrollRestored: true, focusReturned: true };
		});
		await check('Smooth own-page anchor, hash, destination focus and header offset', async () => {
			await scrollTo(page, 0);
			const samples = await page.evaluate(async () => {
				document.querySelector('.cb-hero-actions [data-cb-anchor]').click();
				const samples = [];
				for (let index = 0; index < 5; index++) { await new Promise(requestAnimationFrame); samples.push(window.scrollY); }
				return samples;
			});
			await settledScroll(page);
			assert.equal(new URL(page.url()).hash, '#productos'); assert.equal(await page.locator('#productos').evaluate(node => node === document.activeElement), true);
			const final = await page.evaluate(() => window.scrollY);
			assert.ok(samples.some(y => y > 0 && y < final - 2), `No intermediate native smooth scroll: ${samples}; final ${final}`);
			const positions = await page.evaluate(() => ({ targetTop: document.querySelector('#productos').getBoundingClientRect().top, headerBottom: document.querySelector('[data-cb-header]').getBoundingClientRect().bottom }));
			assert.ok(positions.targetTop >= positions.headerBottom - 1, `Anchor obscured by header: ${JSON.stringify(positions)}`);
			return { samples, final, ...positions };
		});
		await check('Same-page menu anchor waits for inverse close', async () => {
			await page.locator('[data-cb-menu-open]:not([data-cb-menu-search-open])').first().click(); await dialogState(page, '[data-cb-menu]', 'open');
			const immediate = await page.evaluate(() => {
				const link = document.createElement('a'); link.href = '#formularios'; link.textContent = 'Anchor QA'; link.id = 'cb-qa-anchor'; document.querySelector('.cb-menu-nav').append(link); link.click();
				return { hash: location.hash, state: document.querySelector('[data-cb-menu]').dataset.state, open: document.querySelector('[data-cb-menu]').open };
			});
			assert.equal(immediate.hash, '#productos'); assert.equal(immediate.state, 'closing'); assert.equal(immediate.open, true);
			await page.waitForFunction(() => location.hash === '#formularios' && !document.querySelector('[data-cb-menu]').open); await settledScroll(page);
			assert.equal(await page.locator('#formularios').evaluate(node => document.activeElement === node), true);
			await page.locator('#cb-qa-anchor').evaluate(node => node.remove());
			return { immediate, technique: 'Temporary DOM navigation link; no persisted WordPress changes.' };
		});
		await check('Native demo quantity, loading and feedback', async () => {
			assert.equal(await page.locator('[data-cb-quantity-minus]').isDisabled(), true);
			await page.locator('[data-cb-quantity-plus]').click(); assert.equal(await page.locator('#cb-quantity').inputValue(), '2');
			await page.locator('[data-cb-quantity-minus]').click(); assert.equal(await page.locator('#cb-quantity').inputValue(), '1');
			await page.locator('[data-cb-feedback-trigger]').click(); assert.equal(await page.locator('[data-cb-demo-feedback]').isVisible(), true);
			await page.locator('[data-cb-feedback-trigger]').click(); assert.equal(await page.locator('[data-cb-demo-feedback]').isVisible(), false);
			const busy = await page.locator('[data-cb-loading-demo]').evaluate(button => { button.click(); return { disabled: button.disabled, busy: button.getAttribute('aria-busy') }; });
			assert.deepEqual(busy, { disabled: true, busy: 'true' }); await page.waitForFunction(() => !document.querySelector('[data-cb-loading-demo]').disabled);
		});
		await check('Nonhero page has solid header and unobscured title', async () => {
			const nonheroUrl = await page.locator('.cb-footer-column a').filter({ hasText: /^Carrito$/ }).getAttribute('href');
			assert.ok(nonheroUrl, 'Native cart link missing');
			await page.goto(nonheroUrl, { waitUntil: 'networkidle' }); await ready(page); await scrollTo(page, 0);
			const result = await page.evaluate(() => { const heading = document.querySelector('main h1, .wp-block-post-title'); const main = document.querySelector('main'); return { hasHero: document.body.classList.contains('has-hero'), progress: Number(document.documentElement.style.getPropertyValue('--cb-header-progress')), headingTop: heading?.getBoundingClientRect().top, headerBottom: document.querySelector('[data-cb-header]').getBoundingClientRect().bottom, mainClass: main?.className, mainPadding: main ? getComputedStyle(main).paddingBlockStart : null }; });
			assert.equal(result.hasHero, false); assert.equal(result.progress, 1); assert.ok(Number.isFinite(result.headingTop), 'Nonhero title missing'); assert.ok(result.headingTop >= result.headerBottom - 1, `Header overlap: ${JSON.stringify(result)}`);
			return result;
		});
		await context.close();

		for (const factor of [2, 4]) {
			await check(`Reflow equivalent to ${factor * 100}% desktop zoom`, async () => {
				const zoomContext = await browser.newContext({ viewport: { width: 1280 / factor, height: 900 / factor }, deviceScaleFactor: factor });
				const zoomPage = await zoomContext.newPage(); observe(zoomPage); await zoomPage.goto(showcase); await ready(zoomPage);
				try {
					const result = await bounds(zoomPage); assertBounds(result);
					await zoomPage.locator('[data-cb-menu-open]:not([data-cb-menu-search-open])').first().click(); await dialogState(zoomPage, '[data-cb-menu]', 'open');
					await zoomPage.waitForFunction(() => Math.abs(new DOMMatrix(getComputedStyle(document.querySelector('[data-cb-menu]')).transform).m42) < 1);
					const closeRect = await zoomPage.locator('[data-cb-menu-close]').boundingBox(); assert.ok(closeRect && closeRect.y >= 0 && closeRect.y + closeRect.height <= 900 / factor + 1, 'Close control inaccessible at short reflow viewport');
					await zoomPage.keyboard.press('Escape'); await closed(zoomPage, '[data-cb-menu]');
					return { ...result, factor, technique: report.reflowTechnique };
				} finally { await zoomContext.close(); }
			});
		}
		await check('Reduced motion uses instant anchors and short inverse close', async () => {
			const reduced = await browser.newContext({ viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' }); const reducedPage = await reduced.newPage(); observe(reducedPage);
			try {
				await reducedPage.goto(showcase); await ready(reducedPage);
				const result = await reducedPage.evaluate(() => {
					const original = Element.prototype.scrollIntoView; Element.prototype.scrollIntoView = function (options) { window.cbQaScrollBehavior = options.behavior; return original.call(this, options); };
					document.querySelector('.cb-hero-actions [data-cb-anchor]').click();
					return { scrollCss: getComputedStyle(document.documentElement).scrollBehavior, duration: getComputedStyle(document.querySelector('[data-cb-menu]')).transitionDuration };
				});
				await reducedPage.waitForFunction(() => window.cbQaScrollBehavior === 'instant'); assert.equal(result.scrollCss, 'auto'); assert.ok(parseFloat(result.duration) < 0.02);
				await reducedPage.locator('[data-cb-menu-open]:not([data-cb-menu-search-open])').first().click(); await dialogState(reducedPage, '[data-cb-menu]', 'open'); await reducedPage.keyboard.press('Escape'); await closed(reducedPage, '[data-cb-menu]');
				return result;
			} finally { await reduced.close(); }
		});
		await check('Storage denied preserves session choice without page errors', async () => {
			const denied = await browser.newContext({ colorScheme: 'dark' });
			await denied.addInitScript(() => Object.defineProperty(window, 'localStorage', { get() { throw new DOMException('QA storage denied', 'SecurityError'); } }));
			const deniedPage = await denied.newPage(); observe(deniedPage);
			try {
				await deniedPage.goto(showcase); await ready(deniedPage); assert.equal(await deniedPage.locator('html').getAttribute('data-theme'), 'dark');
				await deniedPage.locator('[data-cb-theme-select]').first().selectOption('light'); assert.equal(await deniedPage.locator('html').getAttribute('data-theme'), 'light');
				await deniedPage.reload(); await ready(deniedPage); assert.equal(await deniedPage.locator('html').getAttribute('data-theme'), 'dark');
			} finally { await denied.close(); }
		});
		await check('No-JavaScript navigation and dark system fallback', async () => {
			const noJs = await browser.newContext({ javaScriptEnabled: false, colorScheme: 'dark', viewport: { width: 390, height: 844 } }); const noJsPage = await noJs.newPage(); observe(noJsPage);
			try {
				await noJsPage.goto(showcase, { waitUntil: 'networkidle' });
				assert.equal(await noJsPage.locator('.cb-no-js-nav').isVisible(), true); assert.ok(await noJsPage.locator('.cb-no-js-nav a').count() > 0);
				const result = await noJsPage.evaluate(() => ({ theme: document.documentElement.dataset.theme || null, background: getComputedStyle(document.body).backgroundColor, heading: document.querySelector('h1').textContent.trim() }));
				assert.equal(result.theme, null); assert.equal(result.background, 'rgb(23, 31, 27)'); assert.ok(result.heading.length > 0); return result;
			} finally { await noJs.close(); }
		});
		await check('No uncaught JavaScript or same-origin asset failures', async () => { assert.deepEqual(report.pageErrors, []); assert.deepEqual(report.assetErrors, []); });
	} finally {
		await browser.close();
		report.finishedAt = new Date().toISOString(); report.passed = report.checks.filter(check => check.pass).length; report.failed = report.checks.filter(check => !check.pass).length;
		fs.writeFileSync(path.join(output, 'browser-qa.json'), JSON.stringify(report, null, 2) + '\n');
		console.log(`H3 browser QA: ${report.passed} passed, ${report.failed} failed. ${path.join(output, 'browser-qa.json')}`);
		if (report.failed) process.exitCode = 1;
	}
})().catch(error => { console.error(error.stack); process.exitCode = 1; });
