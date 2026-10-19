/** Render captured native multipart emails locally; no external email service. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
process.env.PLAYWRIGHT_BROWSERS_PATH ||= 'C:\\Users\\ramiro\\AppData\\Local\\ms-playwright';
const { chromium, firefox, webkit } = require('playwright');
const root = path.resolve(__dirname, '..');
const privateRoot = path.join(root, '.local/local/h7-2');
const mail = JSON.parse(fs.readFileSync(path.join(privateRoot, 'mailpit-report.json'), 'utf8'));
assert.equal(mail.result, 'passed', 'Native Mailpit QA must pass before visual QA.');
const output = path.join(privateRoot, 'previews');
fs.mkdirSync(output, { recursive: true });
const report = { test: 'Captured native HTML, local rendering; no Gmail/Outlook delivery claim', widths: [320, 390, 600, 900], checks: [], captures: [], errors: [] };
const selected = [...new Map(mail.captured.map(m => [m.email_id, m])).values()];
// Prefer the mixed order for buyer/admin on-hold previews and dynamic data.
for (const id of ['customer_on_hold_order', 'new_order']) {
  const mixed = mail.captured.find(m => m.email_id === id && m.stage === 'main_on_hold');
  if (mixed) selected[selected.findIndex(m => m.email_id === id)] = mixed;
}
function luminance(hex) {
  const channels = hex.match(/[a-f0-9]{2}/gi).map(x => parseInt(x, 16) / 255).map(x => x <= .04045 ? x / 12.92 : ((x + .055) / 1.055) ** 2.4);
  return channels[0] * .2126 + channels[1] * .7152 + channels[2] * .0722;
}
for (const [text, background] of [['202a24','fffdf8'], ['405b35','fffdf8'], ['586157','f3f0e8']]) {
  const ratio = (Math.max(luminance(text),luminance(background))+.05)/(Math.min(luminance(text),luminance(background))+.05);
  assert.ok(ratio >= 4.5, 'Configured normal text contrast >= 4.5:1');
  report.checks.push({ name:'contrast-'+text, ratio:Number(ratio.toFixed(2)), passed:true });
}
(async () => {
  for (const [engine, launcher] of Object.entries({ chromium, firefox, webkit })) {
    const browser = await launcher.launch({ headless: true });
    try {
      const context = await browser.newContext({ colorScheme:'light', serviceWorkers:'block' });
      // Mailpit HTML is an artifact, not a navigation target. Block resource loads.
      await context.route('**/*', route => route.abort());
      const page = await context.newPage();
      page.on('pageerror', error => report.errors.push({ engine, error:error.message }));
      for (const message of selected) {
        const html = fs.readFileSync(path.join(mail.private_artifacts, message.html), 'utf8');
        assert.doesNotMatch(html, /<script\b|@import|fonts\.googleapis|tailwind/i);
        for (const width of report.widths) {
          await page.setViewportSize({ width, height:1000 });
          await page.setContent(html, { waitUntil:'load' });
          const geometry = await page.evaluate(() => ({
            width:innerWidth, scroll:document.documentElement.scrollWidth,
            bodySize:parseFloat(getComputedStyle(document.querySelector('#body_content_inner')).fontSize),
            heading:document.querySelector('h1')?.textContent,
            headingCount:document.querySelectorAll('h1').length,
            images:[...document.images].map(i => ({ alt:i.getAttribute('alt'),src:i.getAttribute('src') })),
            links:[...document.querySelectorAll('a[href]')].map(a => ({ href:a.getAttribute('href'),text:a.textContent.trim(),decoration:getComputedStyle(a).textDecorationLine })),
            hasTableHeaders:document.querySelectorAll('th[scope="col"]').length >= 3,
            bodyText:document.querySelector('#body_content_inner')?.textContent
          }));
          if (geometry.scroll > width + 1) {
            report.overflow = await page.locator('body *').evaluateAll(nodes => nodes.filter(n => n.getBoundingClientRect().right > innerWidth + 1).slice(0,25).map(n => ({tag:n.tagName,id:n.id,classes:n.className,width:n.getBoundingClientRect().width,right:n.getBoundingClientRect().right,padding:getComputedStyle(n).padding,minWidth:getComputedStyle(n).minWidth,whiteSpace:getComputedStyle(n).whiteSpace})));
            await page.screenshot({path:path.join(output,'failure-overflow.png'),fullPage:true});
          }
          assert.ok(geometry.scroll <= width + 1, `${engine}/${message.email_id}/${width}: no horizontal overflow (${geometry.scroll}px)`);
          assert.ok(geometry.bodySize >= 16, `${engine}/${message.email_id}/${width}: readable body text`);
          assert.equal(geometry.headingCount, 1, 'One clear main heading');
          assert.ok(geometry.hasTableHeaders, 'Native semantic column headers retained');
          assert.ok(geometry.images.every(i => i.alt !== null), 'Every optional image has alt');
          for (const link of geometry.links) {
            const url = new URL(link.href, 'http://localhost:8080');
            assert.ok(['http:','https:','mailto:','tel:'].includes(url.protocol));
            if (['http:','https:'].includes(url.protocol)) {
              const nativeAdminHelp = !message.email_id.startsWith('customer_') && url.protocol === 'https:' && url.hostname === 'woocommerce.com';
              assert.ok(url.origin === 'http://localhost:8080' || nativeAdminHelp, 'Order links use local store; native admin app help uses official HTTPS');
            }
          }
          report.checks.push({ engine,email:message.email_id,width,scroll:geometry.scroll,bodySize:geometry.bodySize,heading:geometry.heading,passed:true });
          if (engine === 'chromium' && [390,900].includes(width)) {
            const file = `${message.email_id}-${width}.png`;
            await page.screenshot({ path:path.join(output,file), fullPage:true });
            report.captures.push({ file,email:message.email_id,mailpit_url:message.mailpit_url });
          }
        }
      }
      await context.close();
    } finally { await browser.close(); }
  }
  assert.equal(report.errors.length,0);
  report.result = 'passed';
})().catch(error => { report.result='failed'; report.failure=error.message; process.exitCode=1; }).finally(() => {
  fs.writeFileSync(path.join(privateRoot,'email-layout-report.json'),JSON.stringify(report,null,2)+'\n');
  console.log(JSON.stringify({result:report.result,checks:report.checks.length,captures:report.captures.length,failure:report.failure}));
});
