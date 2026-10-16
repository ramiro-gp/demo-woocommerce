/** Targeted contrast checks, in addition to keyboard/semantics in browser QA. */
const { chromium } = require('playwright');
const fs = require('node:fs'); const path = require('node:path');
process.env.PLAYWRIGHT_BROWSERS_PATH ||= 'C:\\Users\\ramiro\\AppData\\Local\\ms-playwright';
const output = path.resolve(__dirname, '../.local/local/h5');
(async () => {
    const browser = await chromium.launch(); const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    const checks = [];
    for (const theme of ['light', 'dark']) {
        await page.emulateMedia({ colorScheme: theme });
        for (const route of ['?post_type=product', '?product=buzo-pulso', '?product=pantalon-linea']) {
            await page.goto('http://localhost:8080/' + route, { waitUntil: 'domcontentloaded' });
            await page.locator('main h1').waitFor();
            if (route.includes('buzo-pulso')) { await page.getByRole('radio', { name: 'Gris', exact: true }).click(); await page.getByRole('radio', { name: 'S', exact: true }).click(); }
            const result = await page.evaluate(() => {
                const parse = c => { const n = c.match(/[\d.]+/g)?.map(Number) || [0, 0, 0, 0]; return [n[0], n[1], n[2], n[3] ?? 1]; };
                const blend = (fg, bg) => fg.slice(0, 3).map((v, i) => v * fg[3] + bg[i] * (1 - fg[3]));
                const lum = c => c.map(v => { v /= 255; return v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4; }).reduce((a, v, i) => a + v * [.2126, .7152, .0722][i], 0);
                const selectors = ['main h1', '.cb-product-title', '.cb-product-price', '.cb-product-price del', '.cb-badge', '.cb-filters h3', '.wc-block-product-filter-checkbox-list__text', '.wc-block-product-filter-checkbox-list__count', 'select.orderby', '.cb-product-purchase .wp-block-woocommerce-product-price del', '.cb-product-purchase .wp-block-post-excerpt', '.stock', '.cb-variation-option[aria-checked="true"]', '.cb-size-guide summary'];
                const items = selectors.flatMap(selector => {
                    const n = [...document.querySelectorAll(selector)].find(x => x.getClientRects().length);
                    if (!n) return [];
                    let bg = [255, 255, 255], opacity = 1;
                    const chain = []; for (let p = n; p; p = p.parentElement) chain.unshift(p);
                    chain.forEach(p => { const s = getComputedStyle(p); bg = blend(parse(s.backgroundColor), bg); opacity *= Number(s.opacity); });
                    const s = getComputedStyle(n), fg = parse(s.color); fg[3] *= opacity;
                    const a = lum(blend(fg, bg)), b = lum(bg), ratio = (Math.max(a, b) + .05) / (Math.min(a, b) + .05);
                    const large = parseFloat(s.fontSize) >= 24 || (parseFloat(s.fontSize) >= 18.66 && Number(s.fontWeight) >= 700);
                    return [{ selector, ratio: Number(ratio.toFixed(2)), required: large ? 3 : 4.5, passed: ratio >= (large ? 3 : 4.5) }];
                });
                const checkbox = document.querySelector('input.wc-block-product-filter-checkbox-list__input');
                if (checkbox && checkbox.getClientRects().length) {
                    const s = getComputedStyle(checkbox), bg = parse(s.backgroundColor).slice(0, 3);
                    const a = lum(blend(parse(s.borderColor), bg)), b = lum(bg), ratio = (Math.max(a, b) + .05) / (Math.min(a, b) + .05);
                    items.push({ selector: 'checkbox boundary', ratio: Number(ratio.toFixed(2)), required: 3, passed: ratio >= 3 });
                }
                return items;
            });
            checks.push(...result.map(c => ({ theme, route, ...c })));
        }
    }
    await browser.close();
    const failures = checks.filter(c => !c.passed);
    fs.writeFileSync(path.join(output, 'contrast-qa.json'), JSON.stringify({ checks, failures, scope: 'Targeted solid surfaces; not a complete WCAG certification.' }, null, 2) + '\n');
    console.log(JSON.stringify({ checks: checks.length, failures }, null, 2));
    if (failures.length) process.exitCode = 1;
})().catch(e => { console.error(e.message); process.exitCode = 1; });
