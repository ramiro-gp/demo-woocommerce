/** Disposable H11 release QA. Reports contain booleans/IDs only; private transport data stays ignored. */
'use strict';
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
process.env.PLAYWRIGHT_BROWSERS_PATH ||= path.join(process.env.LOCALAPPDATA||'C:\\Users\\ramiro\\AppData\\Local','ms-playwright');
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'..'),privateDir=path.resolve(process.env.H11_PRIVATE_DIR||path.join(root,'.local/h11/private'));
const out=path.join(root,'docs/evidence/h11'); fs.mkdirSync(out,{recursive:true});
const base=new URL(process.env.H11_URL||'http://localhost:8184').origin;
const mail=new URL(process.env.H11_MAIL_URL||'http://localhost:8186').origin;
const restore=process.env.H11_RESTORE_SMOKE==='1',emailOnly=process.env.H11_EMAIL_ONLY==='1';
assert.ok(new URL(base).hostname==='localhost'&&new URL(base).protocol==='http:'&&new URL(base).port===(restore?'8185':'8184'),'Only the explicit H11 lab origin is accepted');
assert.equal(mail,'http://localhost:8186','Only the isolated H11 Mailpit is accepted');
const fixture=JSON.parse(fs.readFileSync(path.join(privateDir,'h11-fixture.json'),'utf8'));
assert.equal(fixture.installation,'h11-production-simulation');
const report={result:'running',mode:emailOnly?'captured-email':restore?'restore-authenticated-read':'source-native-flow',origin:base,checks:[],pageErrorCount:0,failedResourceCount:0,externalRequestCount:0,externalRequestBlockedCount:0,externalOrigins:[],synthetic:true,externalDelivery:false,realPayments:false};
let lastCheck='initialization',browser;
function check(name,condition) { lastCheck=name; const passed=Boolean(condition); report.checks.push({name,passed}); assert.ok(passed,name); }
function save() { fs.writeFileSync(path.join(out,emailOnly?'emails.json':restore?'restore-browser.json':'browser.json'),JSON.stringify(report,null,2)+'\n'); }
function privateWrite(name,value) { fs.writeFileSync(path.join(privateDir,name),JSON.stringify(value,null,2)+'\n',{mode:0o600}); }
const stateFile=path.join(privateDir,'h11-browser-state.json');
const state=fs.existsSync(stateFile)?JSON.parse(fs.readFileSync(stateFile,'utf8')):{};
function pageUrl(key) { return base+'/?page_id='+fixture.pages[key]; }
async function context(js=true) {
  const c=await browser.newContext({viewport:{width:1366,height:1000},reducedMotion:'reduce',javaScriptEnabled:js,serviceWorkers:'block'});
  await c.route(/^https?:\/\//,async r=>{const origin=new URL(r.request().url()).origin;if(origin===base)return r.continue();report.externalRequestCount++;if(!report.externalOrigins.includes(origin))report.externalOrigins.push(origin);await r.abort();report.externalRequestBlockedCount++;});
  c.on('page',p=>{p.on('pageerror',()=>report.pageErrorCount++);p.on('response',r=>{if(r.status()>=400&&new URL(r.url()).origin===base)report.failedResourceCount++;});});
  return c;
}
async function go(p,url) { const r=await p.goto(url,{waitUntil:'networkidle',timeout:45000}); check('Local navigation returns a page',r&&r.status()<400); return r; }
async function login(c,user,account=false) {
  const p=await c.newPage(); await go(p,account?pageUrl('myaccount'):base+'/wp-login.php');
  await p.locator(account?'#username':'#user_login').fill(user.username); await p.locator(account?'#password':'#user_pass').fill(user.password);
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}),p.locator(account?'[name="login"]':'#wp-submit').click()]);
  check(account?'Native customer account authentication':'Native manager WordPress authentication',await p.locator(account?'.woocommerce-MyAccount-navigation':'#wpadminbar').count()>0);
  return p;
}
async function publicSmoke() {
  const c=await context(); const p=await c.newPage();
  const probe=await c.request.get(base+'/h11-sapi-probe.php'); check('Temporary HTTP SAPI probe responds',probe.ok()); const sapi=await probe.json();
  check('Probe runs through web PHP SAPI',sapi.php_sapi!=='cli'); check('Web PHP hides displayed errors and logs securely',sapi.display_errors===false&&sapi.log_errors===true&&sapi.debug_display_off===true);
  check('Web PHP memory and upload limits are compatible',sapi.memory_limit_bytes>=256*1024*1024&&sapi.upload_max_filesize_bytes>=32*1024*1024&&sapi.post_max_size_bytes>sapi.upload_max_filesize_bytes&&sapi.max_execution_time>=120);
  check('Web PHP enables OPcache',sapi.opcache_enabled===true); check('Web process can write uploads',sapi.uploads_writable===true); check('Web process cannot write config/code files or code directories',sapi.config_and_code_files_readonly===true&&sapi.code_directories_readonly===true); check('Native file editor is disabled',sapi.file_editor_disabled===true); report.webSapi=sapi;
  const api=await c.request.get(base+'/?rest_route=/wc/store/v1/products&per_page=20'); check('Store API is available',api.ok()); const products=await api.json();
  check('Only the two synthetic H11 products are published',products.length===2&&products.every(x=>/^objeto-(simple|variable)-h11$/.test(x.slug)));
  const variable=products.find(x=>x.id===fixture.products.variable); check('Variable product is discoverable',Boolean(variable));
  const routes=[['home',base+'/'],['catalog',pageUrl('shop')],['variable',variable.permalink],['lookbook',pageUrl('lookbook')],['privacy',pageUrl('privacy')],['withdrawal',pageUrl('withdrawal')]];
  for(const [key,url] of routes) {
    const response=await go(p,url); check(key+' has noindex header',/noindex/.test(response.headers()['x-robots-tag']||''));
    const facts=await p.evaluate(()=>({brand:document.querySelector('.cb-header .cb-brand')?.textContent.includes('H11 QA DEMO'),oldBrand:/\bTRAMA\b|ORIGEN CASA/.test(document.body.innerText),brokenImages:[...document.images].some(e=>e.complete&&e.naturalWidth===0&&new URL(e.currentSrc||e.src).origin===location.origin),oneMain:document.querySelectorAll('main').length===1,overflow:document.documentElement.scrollWidth>innerWidth+1}));
    check(key+' uses the generic H11 identity',facts.brand&&!facts.oldBrand); check(key+' images load',!facts.brokenImages); check(key+' has one main',facts.oneMain);
    if(['home','catalog','variable','lookbook'].includes(key)) { check(key+' stays inside desktop viewport',!facts.overflow); if(!restore)await p.screenshot({path:path.join(out,'h11-'+key+'.png'),fullPage:true}); }
    if(key==='lookbook')check('Installed Lookbook resolves both product hotspots',await p.locator('.slb-hotspot').count()===2);
    if(key==='withdrawal')check('Installed legal form renders',await p.locator('.cc-withdrawal-form').isVisible());
  }
  if(!restore) { await p.setViewportSize({width:390,height:844}); await go(p,base+'/'); check('Home fits mobile viewport',await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)); await p.screenshot({path:path.join(out,'h11-home-mobile.png'),fullPage:true}); }
  await c.close(); return variable;
}
async function checkout(variable) {
  const c=await context(); const p=await login(c,fixture.users.customer,true);
  {
    await go(p,variable.permalink);
    check('Color swatches render from native attributes',await p.locator('.cb-variation-options [role="radio"]').count()>=2);
    const selects=p.locator('.variations select'); check('Native variable select remains available',await selects.count()===1);
    const value=await selects.first().locator('option').evaluateAll(xs=>xs.find(x=>x.value&&!x.disabled)?.value); await selects.first().selectOption(value,{force:true});
    await p.locator('.single_add_to_cart_button:not(.disabled)').waitFor(); const variationId=Number(await p.locator('input.variation_id').inputValue()); check('Selected variation resolves',fixture.products.variations.includes(variationId));
    await p.locator('.single_add_to_cart_button').click(); await p.waitForLoadState('networkidle');
    await p.locator('.wc-block-mini-cart__button').click(); await p.locator('.wc-block-mini-cart__drawer[aria-hidden="false"]').waitFor(); check('Mini-Cart opens with product',await p.locator('.wc-block-mini-cart__drawer').innerText().then(t=>/Objeto variable H11 DEMO/.test(t))); await p.keyboard.press('Escape');
    const cartResponse=await c.request.get(base+'/?rest_route=/wc/store/v1/cart'); check('Cart Store API accepts the current session',cartResponse.ok()); const cartToken=cartResponse.headers()['cart-token']; check('Cart session token is supplied privately',Boolean(cartToken));
    const call=async(route,data)=>{const r=await c.request.post(base+'/?rest_route=/wc/store/v1/'+route,{headers:{'Cart-Token':cartToken},data});check('Store API '+route+' succeeds',r.ok());return r.json();};
    let cart=await call('cart/add-item',{id:fixture.products.simple,quantity:1}); check('Cart has both simple and variable items',cart.items.length===2);
    const address={first_name:'Persona QA',last_name:'H11 DEMO',address_1:'Calle ficticia 123',address_2:'',city:'Ciudad Autónoma de Buenos Aires',state:'C',postcode:'1414',country:'AR',phone:'1100000000'};
    cart=await call('cart/update-customer',{billing_address:{...address,email:fixture.users.customer.email},shipping_address:address});
    const group=cart.shipping_rates[0],rate=group?.shipping_rates.find(x=>x.rate_id.startsWith('flat_rate')); check('Local fictitious shipping rate is available',Boolean(rate)); await call('cart/select-shipping-rate',{package_id:group.package_id,rate_id:rate.rate_id});
    await go(p,pageUrl('cart')); check('Native Cart Block renders both products',await p.locator('.wc-block-cart-items__row').count()===2);
    await go(p,pageUrl('checkout')); check('Native Checkout Block renders',await p.locator('.wc-block-checkout').count()>0); check('Fictitious BACS is available',await p.getByText('Transferencia bancaria · DEMO',{exact:true}).first().isVisible()); check('Mercado Pago is absent from checkout',!(await p.locator('body').innerText()).includes('Mercado Pago'));
    for(const box of await p.locator('.wc-block-checkout__terms input[type="checkbox"]').all())await box.check();
    if(!state.orderId) {
    const native=p.waitForResponse(r=>r.request().method()==='POST'&&/wc\/store\/v1\/checkout(?:&|\?|$|\/)/.test(decodeURIComponent(r.url())),{timeout:45000});
    await p.locator('.wc-block-components-checkout-place-order-button').click(); const placed=await native; check('Native Place Order request succeeds',placed.ok()); const order=await placed.json(); check('Native BACS creates an on-hold order',order.status==='on-hold'&&order.payment_method==='bacs'&&order.order_id>0);
    privateWrite('h11-checkout.json',order); state.orderId=order.order_id; privateWrite('h11-browser-state.json',state);
    check('Native confirmation redirect remains in this lab',new URL(order.payment_result.redirect_url).origin===base); await go(p,order.payment_result.redirect_url); check('Order confirmation displays fictitious payment instructions',/No realices|no hay una cuenta/.test(await p.locator('body').innerText()));
    } else {
      const previous=JSON.parse(fs.readFileSync(path.join(privateDir,'h11-checkout.json'),'utf8'));
      check('Captured native checkout confirms the existing own BACS order',previous.order_id===state.orderId&&previous.status==='on-hold'&&previous.payment_method==='bacs');
      await go(p,previous.payment_result.redirect_url); check('Existing native confirmation displays fictitious payment instructions',/No realices|no hay una cuenta/.test(await p.locator('body').innerText()));
      report.checkoutSubmissionReused=true;
    }
    fs.writeFileSync(path.join(out,'browser-native-checkout.json'),JSON.stringify({result:'passed',scope:'Source native variation/Mini-Cart/Cart/Checkout/BACS completed before the account and administration checks.',orderId:state.orderId,checks:report.checks.filter(x=>x.passed),externalDelivery:false,realPayments:false},null,2)+'\n');
  }
  await go(p,pageUrl('myaccount')); const orderLink=await p.locator('.woocommerce-MyAccount-navigation-link--orders a').getAttribute('href'); await go(p,orderLink);
  const own=p.locator('.woocommerce-orders-table__cell-order-number a').filter({hasText:String(state.orderId)}); check('My Account lists its own BACS order',await own.count()===1); await own.click(); await p.waitForLoadState('networkidle'); check('My Account opens its own order',await p.locator('.woocommerce-order-details').count()===1);
  const addresses=await p.locator('.woocommerce-MyAccount-navigation-link--edit-address a').getAttribute('href'); await go(p,addresses); const billing=await p.locator('.woocommerce-Address').first().locator('a').first().getAttribute('href'); await go(p,billing);
  const fields={billing_first_name:'Persona QA',billing_last_name:'H11 DEMO',billing_address_1:'Calle ficticia 456',billing_city:'Ciudad Autónoma de Buenos Aires',billing_postcode:'1414',billing_phone:'1100000000',billing_email:fixture.users.customer.email};
  for(const [name,value]of Object.entries(fields))if(await p.locator('[name="'+name+'"]').count())await p.locator('[name="'+name+'"]').fill(value);
  if(await p.locator('#billing_country').count())await p.locator('#billing_country').selectOption('AR',{force:true}); if(await p.locator('#billing_state').count())await p.locator('#billing_state').selectOption('C',{force:true});
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}),p.locator('[name="save_address"]').click()]); check('Native address save has no errors',await p.locator('.woocommerce-error').count()===0); await go(p,billing); check('Native address change persists',await p.locator('#billing_address_1').inputValue()===fields.billing_address_1);
  const denied=await c.request.get(base+'/wp-admin/admin.php?page=commerce-legal'); check('Customer cannot access legal administration',denied.status()===403||!(await denied.text()).includes('<h1>Solicitudes de arrepentimiento</h1>')); await c.close();
  report.orderId=state.orderId;
}
async function legalAndAdmin() {
  if(!state.legalCode) {
    const c=await context(false),p=await c.newPage(); await go(p,pageUrl('withdrawal')); check('Withdrawal works for a guest without JavaScript',await p.locator('.cc-withdrawal-form').isVisible());
    await p.locator('#cc-name').fill('Persona QA H11 DEMO'); await p.locator('#cc-email').fill('browser-receipt@h11.example.test'); await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}),p.locator('.cc-withdrawal-form button[type="submit"]').click()]);
    const code=(await p.locator('.cc-receipt-code').innerText()).trim(); check('Browser legal submission returns a persisted receipt',/^ARR-\d{8}-[A-F0-9]{20}$/.test(code)); check('Receipt does not promise cancellation or refund',/No significa que esté aprobada/.test(await p.locator('.cc-receipt').innerText())); state.legalCode=code; privateWrite('h11-browser-state.json',state); await c.close();
  }
  const c=await context(),p=await login(c,fixture.users.manager);
  await go(p,base+'/wp-admin/admin.php?page=wc-orders'); check('Manager can open native HPOS administration',await p.locator('body').innerText().then(t=>/Orders|Pedidos/.test(t)));
  await go(p,base+'/wp-admin/admin.php?page=commerce-legal'); const link=p.getByRole('link',{name:state.legalCode,exact:true}); check('Manager sees the browser legal request',await link.count()===1); await link.click(); await p.waitForLoadState('networkidle');
  const form=p.locator('form:has([name="operation"])'),data=await form.evaluate(f=>Object.fromEntries(new FormData(f))); state.legalId=Number(data.case);
  const update=await c.request.post(base+'/wp-admin/admin-post.php',{form:{...data,operation:'update',status:'review',order_id:String(state.orderId),verified:'1',note:'H11 QA DEMO: identidad y pedido sintéticos verificados en fixture privado. Sin operación financiera.'}}); check('Authorized legal management succeeds',update.ok());
  await go(p,base+'/wp-admin/admin.php?page=commerce-legal&case='+state.legalId); check('Legal management state persists',await p.locator('[name="status"]').inputValue()==='review'); check('Legal association uses native HPOS link',await p.locator('.wrap a[href*="wc-orders"]').count()===1);
  privateWrite('h11-browser-state.json',state); report.legalRequestId=state.legalId; await c.close();
}
async function restoreAuthenticatedSmoke() {
  assert.ok(restore,'Authenticated restoration QA runs only on :8185');
  const integrity=JSON.parse(fs.readFileSync(path.join(out,'restore.json'),'utf8'));
  check('Exact restoration integrity comparison preceded authenticated smoke',integrity.result==='passed'&&integrity.mode==='compare'&&integrity.origin===base&&integrity.checks.restore_same_business_digest===true);
  report.integrityComparison={beforeAuthenticatedSmoke:true,businessDigestSha256:integrity.business_digest_sha256,limitation:'Native logins legitimately update session metadata after the exact database comparison. This smoke reads restored business data without saving forms or submitting new orders/legal requests.'};
  const customerContext=await context(),customer=await login(customerContext,fixture.users.customer,true);
  const orders=await customer.locator('.woocommerce-MyAccount-navigation-link--orders a').getAttribute('href'); await go(customer,orders);
  const own=customer.locator('.woocommerce-orders-table__cell-order-number a').filter({hasText:String(state.orderId)}); check('Restored customer sees its original BACS order',await own.count()===1);
  await own.click(); await customer.waitForLoadState('networkidle'); check('Restored customer opens native order details',await customer.locator('.woocommerce-order-details').count()===1);
  check('Restored order details retain both synthetic products',await customer.locator('.woocommerce-order-details').innerText().then(t=>t.includes('Objeto simple H11 DEMO')&&t.includes('Objeto variable H11 DEMO')));
  const addresses=await customer.locator('.woocommerce-MyAccount-navigation-link--edit-address a').getAttribute('href'); await go(customer,addresses);
  const billing=await customer.locator('.woocommerce-Address').first().locator('a').first().getAttribute('href'); await go(customer,billing);
  check('Restored billing address opens with the saved synthetic value',await customer.locator('#billing_address_1').inputValue()==='Calle ficticia 456');
  check('Restored billing identity belongs to the fixture customer',await customer.locator('#billing_email').inputValue()===fixture.users.customer.email);
  await customerContext.close();
  const managerContext=await context(),manager=await login(managerContext,fixture.users.manager);
  await go(manager,base+'/wp-admin/admin.php?page=wc-orders');
  check('Restored manager sees the native HPOS order list',await manager.locator('a[href]').evaluateAll((links,id)=>links.some(a=>{const u=new URL(a.href,location.href);return u.searchParams.get('page')==='wc-orders'&&u.searchParams.get('id')===String(id);}),state.orderId));
  await go(manager,base+'/wp-admin/admin.php?page=wc-orders&action=edit&id='+state.orderId);
  check('Restored manager opens the native HPOS order editor',await manager.locator('#order_data').count()===1);
  check('Restored native order still has on-hold status',(await manager.locator('#order_status').inputValue()).endsWith('on-hold'));
  await go(manager,base+'/wp-admin/admin.php?page=commerce-legal'); const legalLink=manager.getByRole('link',{name:state.legalCode,exact:true}); check('Restored legal list contains the original browser request',await legalLink.count()===1);
  await legalLink.click(); await manager.waitForLoadState('networkidle'); check('Restored legal request keeps its management state',await manager.locator('[name="status"]').inputValue()==='review');
  check('Restored legal request keeps its verified order association',Number(await manager.locator('[name="order_id"]').inputValue())===state.orderId);
  check('Restored legal request displays its event audit',await manager.locator('.wrap ol li').count()>=4);
  await managerContext.close(); report.orderId=state.orderId; report.legalRequestId=state.legalId; report.restoredCustomerAndManagerAccess=true; report.businessFormsSaved=false;
}
async function capturedEmail() {
  const r=await fetch(mail+'/api/v1/messages?limit=500'); check('Isolated Mailpit API responds',r.ok); const list=(await r.json()).messages; check('Native messages were captured',Array.isArray(list)&&list.length>=10);
  const messages=await Promise.all(list.map(async m=>{const [r,h]=await Promise.all([fetch(mail+'/api/v1/message/'+m.ID),fetch(mail+'/api/v1/message/'+m.ID+'/headers')]);assert.ok(r.ok&&h.ok);const message=await r.json();message.Headers=await h.json();return message;}));
  check('Every captured recipient is synthetic',messages.every(m=>[...(m.To||[]),...(m.Cc||[]),...(m.Bcc||[])].every(x=>/@[^@]+\.test$/i.test(x.Address))));
  check('Every sender is synthetic',messages.every(m=>/@[^@]+\.test$/i.test(m.From?.Address||'')));
  const type=m=>{const key=Object.keys(m.Headers||{}).find(k=>k.toLowerCase()==='x-h11-qa-type');return key?[m.Headers[key]].flat().map(x=>String(x).toLowerCase()):[];};
  const required=['new_order','customer_on_hold_order','customer_processing_order','customer_completed_order','cancelled_order','customer_cancelled_order','customer_refunded_order'];
  for(const id of required)check('Native '+id+' email captured',messages.some(m=>type(m).includes(id)));
  const native=messages.filter(m=>required.some(id=>type(m).includes(id)));
  check('Native order emails are multipart',native.every(m=>Boolean(m.HTML&&m.Text)));
  check('Native password recovery captured',messages.some(m=>/password|contrase[nñ]a/i.test(m.Subject||'')));
  check('Consumer withdrawal receipt captured',messages.some(m=>/Constancia de recepci[oó]n/i.test(m.Subject||'')&&/ARR-\d{8}-[A-F0-9]{20}/.test(m.Text||'')));
  check('Internal withdrawal notification captured',messages.some(m=>/Nueva solicitud de arrepentimiento/i.test(m.Subject||'')));
  check('BACS email explains fictitious bank details',native.some(m=>/no hay una cuenta|No realices|No realizar/.test(m.Text||'')));
  const secretValues=Object.values(fixture.users).map(u=>u.password); check('Emails never disclose fixture plaintext passwords',messages.every(m=>secretValues.every(s=>!String(m.HTML||'').includes(s)&&!String(m.Text||'').includes(s))));
  check('Mail does not link to the older DEMOs',messages.every(m=>!/localhost:(8080|8082)/.test(String(m.HTML||'')+String(m.Text||''))));
  report.messagesCaptured=messages.length; report.requiredNativeEmailTypes=required; report.realSmtpConfigured=false;
}
(async()=>{
  try {
    if(emailOnly) await capturedEmail();
    else { browser=await chromium.launch({headless:true}); const variable=await publicSmoke(); if(restore)await restoreAuthenticatedSmoke();else { await checkout(variable); await legalAndAdmin(); } check('No uncaught browser errors',report.pageErrorCount===0); check('No failed local browser resources',report.failedResourceCount===0); check('Every external resource request is blocked in the lab',report.externalRequestBlockedCount===report.externalRequestCount); check('No Mercado Pago resources requested',!report.externalOrigins.some(x=>/mercadopago|mercadolibre|mlstatic/i.test(x))); report.externalResourceLimit='Native WordPress emoji CDN requests, when present, are recorded by origin and blocked. This lab does not certify external services.'; }
    report.result='passed'; save(); console.log(JSON.stringify({result:report.result,mode:report.mode,checks:report.checks.length,orderId:report.orderId,legalRequestId:report.legalRequestId,messagesCaptured:report.messagesCaptured}));
  } finally { if(browser)await browser.close(); }
})().catch(error=>{report.result='failed';report.failedAt=lastCheck;save();privateWrite('h11-browser-failure-private.json',{failedAt:lastCheck,message:error.message,stack:error.stack});console.error(JSON.stringify({result:'failed',mode:report.mode,failedAt:lastCheck}));process.exitCode=1;});
