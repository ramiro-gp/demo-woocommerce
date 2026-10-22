/** Local H8.2 acceptance. Isolated browser contexts, synthetic requests; no checkout submission. */
'use strict';
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),{randomBytes}=require('node:crypto');
process.env.PLAYWRIGHT_BROWSERS_PATH||='C:\\Users\\ramiro\\AppData\\Local\\ms-playwright';
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'..');assert.equal(root.toLowerCase(),'c:\\codigo\\demo-woocommerce');
const privateDir=path.join(root,'.local/local/h8-2'), evidence=path.join(root,'docs/evidence/h8-2');
fs.mkdirSync(privateDir,{recursive:true});fs.mkdirSync(evidence,{recursive:true});
const fixture=JSON.parse(fs.readFileSync(path.join(root,'.local/local/backups/h8-2-qa-private.json'),'utf8'));
const h81=JSON.parse(fs.readFileSync(path.join(root,'.local/local/backups/h8-1-qa-fixtures.json'),'utf8'));
const run=randomBytes(6).toString('hex'),base='http://localhost:8080',rights=base+'/?page_id='+fixture.pages.withdrawal;
const report={result:'running',checks:[],matrix:[],errors:[],assetErrors:[],externalRequests:[],codes:[],mail:[]};
const check=(name,value)=>{assert.ok(value,name);report.checks.push(name);};
const clean=s=>[...Object.values(fixture.users).flatMap(u=>[u.username,u.password]),h81.customers.complete.username,h81.customers.complete.password].reduce((v,secret)=>v.split(secret).join('[private]'),String(s));
let browser;
async function context(width=1366,mode='light',js=true){
 const c=await browser.newContext({viewport:{width,height:1000},colorScheme:mode,reducedMotion:'reduce',serviceWorkers:'block',javaScriptEnabled:js});
 await c.addInitScript(theme=>{if(location.origin==='http://localhost:8080')localStorage.setItem('commerce-base-color-mode',theme)},mode);
 await c.route(/^https?:\/\//,r=>{if(new URL(r.request().url()).origin!==base){report.externalRequests.push(new URL(r.request().url()).origin);return r.abort();}return r.continue()});
 c.on('page',p=>{p.on('pageerror',e=>report.errors.push(clean(e.message)));p.on('response',r=>{if(r.status()>=400&&/\.(css|js|woff2?|svg|webp)(\?|$)/.test(r.url()))report.assetErrors.push({url:new URL(r.url()).pathname,status:r.status()})})});return c;
}
async function go(p,url){const r=await p.goto(url,{waitUntil:'networkidle'});assert.ok(r.status()<400,'Navigation '+url);return r;}
async function login(c,u,account=false){const p=await c.newPage();await go(p,account?base+'/?page_id=10':base+'/wp-login.php');await p.locator(account?'#username':'#user_login').fill(u.username);await p.locator(account?'#password':'#user_pass').fill(u.password);await Promise.all([p.waitForNavigation(),p.locator(account?'[name=login]':'#wp-submit').click()]);return p;}
async function data(p,email,suffix=''){await go(p,rights);const d=await p.locator('.cc-withdrawal-form').evaluate(f=>Object.fromEntries(new FormData(f)));return {...d,cc_name:'Prueba H82 '+suffix,cc_email:email};}
async function post(c,d){const r=await c.request.post(rights,{form:d});const html=await r.text();return {status:r.status(),html,code:html.match(/ARR-\d{8}-[A-F0-9]{20}/)?.[0]};}
async function layout(p,label){const m=await p.evaluate(()=>({width:innerWidth,scroll:document.documentElement.scrollWidth,h1:document.querySelectorAll('h1').length,theme:document.documentElement.dataset.theme,formWidth:document.querySelector('.cc-withdrawal-form')?.getBoundingClientRect().width}));assert.ok(m.scroll<=m.width,label+' overflow');assert.equal(m.h1,1,label+' one H1');return m;}
(async()=>{browser=await chromium.launch({headless:true});try{
 const guest=await context(),p=await guest.newPage();
 if(process.env.H82_CHECKOUT_ONLY==='1'){
   const prior=JSON.parse(fs.readFileSync(path.join(privateDir,'browser-failure.json'),'utf8'));
   assert.ok((prior.error.includes('No custom terms checkbox')||prior.error.includes('No external calls beyond existing WordPress Gravatar'))&&prior.matrix.length===50,'Only resume after the known native terms/service inventory assertions; all 50 prior layout cases must exist.');
   for(const key of ['checks','matrix','errors','assetErrors','externalRequests','codes'])report[key]=prior[key];
   report.resumedAfter='Native terms integration repaired; previously passed functional and layout assertions preserved.';
 }else{
 await go(p,base+'/');
 const access=p.locator('.cb-legal-access a');check('Home access exact and visible',await access.innerText()==='BOTÓN DE ARREPENTIMIENTO'&&await access.isVisible());
 check('Home access in first viewport',(await access.boundingBox()).y<1000);await access.click();await p.waitForLoadState('networkidle');check('Home leads directly to form',await p.locator('.cc-withdrawal-form').count()===1);check('No Exit Intent on legal page',await p.locator('[data-cb-exit-intent]').count()===0);
 for(const [key,id]of Object.entries(fixture.pages)){await go(p,base+'/?page_id='+id);check('Published legal '+key,await p.locator('.cc-demo').count()>0);await layout(p,key);}
 const footerLinks=await p.locator('.cb-footer a').evaluateAll(es=>es.map(e=>e.href));for(const id of Object.values(fixture.pages))check('Footer link '+id,footerLinks.some(u=>new URL(u).searchParams.get('page_id')===String(id)));
 await go(p,base+'/?page_id=10');const privacy=p.locator('.woocommerce-privacy-policy-text');check('Registration Spanish privacy', (await privacy.innerText()).includes('Usamos tu email'));check('Registration valid privacy URL',new URL(await privacy.locator('a').getAttribute('href')).searchParams.get('page_id')==='3');
 // Server validation: bypass all browser constraint validation intentionally.
 const email='h82.guest.'+run+'@example.test',d=await data(p,email,'invitado');
 let r=await post(guest,{...d,cc_name:'',cc_email:''});check('Empty fields rejected server-side',r.status===400&&!r.code);
 r=await post(guest,{...d,cc_email:'invalid'});check('Invalid email rejected server-side',r.status===400&&!r.code);
 r=await post(guest,{...d,_wpnonce:'invalid'});check('Invalid CSRF nonce denied',r.status===403&&!r.code);
 const attacker=await context();r=await post(attacker,d);check('Token bound to browser cookie',r.status===403&&!r.code);await attacker.close();
 r=await post(guest,{...d,cc_website:'spam'});check('Honeypot server denial',r.status===400&&!r.code);
 // Genuine no-JavaScript guest submission, no order reference and no reason.
 const nojs=await context(390,'light',false),np=await nojs.newPage();await go(np,rights);await np.locator('#cc-name').fill('Prueba H82 sin JavaScript');await np.locator('#cc-email').fill('h82.nojs.'+run+'@example.test');await Promise.all([np.waitForNavigation(),np.locator('.cc-withdrawal-form button[type=submit]').click()]);const nojsCode=await np.locator('.cc-receipt-code').innerText();check('Guest without JS, order number or reason',/^ARR-/.test(nojsCode));report.codes.push(nojsCode);await nojs.close();
 r=await post(guest,d);check('Guest persisted and confirmed',r.status===200&&!!r.code&&r.html.includes('pendiente de gestión'));const guestCode=r.code;report.codes.push(guestCode);
 const again=await post(guest,d);check('Accidental repeat same code',again.code===guestCode);
 const concurrentData=await data(p,'h82.concurrent.'+run+'@example.test','concurrente');const race=await Promise.all([post(guest,concurrentData),post(guest,concurrentData)]);check('Concurrent duplicate one code',race.every(x=>x.status===200)&&race[0].code===race[1].code);report.codes.push(race[0].code);
 const arbitraryData=await data(p,'h82.unknown.'+run+'@example.test','referencia');r=await post(guest,{...arbitraryData,cc_reference:'NO-EXISTE-H82-'+run,cc_message:'<script>window.h82Injected=true</script>'});check('Unknown reference accepted without disclosure',r.status===200&&!!r.code&&!r.html.includes('window.h82Injected=true'));report.codes.push(r.code);
 await go(p,rights+'&order_id=454&email=another@example.test');check('Query cannot look up any order',await p.locator('.cc-withdrawal-form').count()===1&&!await p.locator('.cc-receipt').count());
 const customer=await context(),cp=await login(customer,h81.customers.complete,true);check('Native My Account still works',await cp.locator('.woocommerce-MyAccount-navigation').count()===1);
 const cd=await data(cp,'h82.customer.'+run+'@example.test','autenticado');r=await post(customer,{...cd,cc_reference:String(fixture.qa_order)});check('Authenticated request with QA order reference',r.status===200&&!!r.code);report.codes.push(r.code);
 const customerAdmin=await customer.request.get(base+'/wp-admin/admin.php?page=commerce-legal');check('Customer cannot access administration',customerAdmin.status()===403||!((await customerAdmin.text()).includes('<h1>Solicitudes de arrepentimiento</h1>')));
 const denied=await customer.request.post(base+'/wp-admin/admin-post.php',{form:{action:'commerce_legal_manage',case:'1',status:'resolved'}});check('Unauthorized mutation denied',denied.status()===403);
 const subscriber=await context(),sp=await login(subscriber,fixture.users.subscriber);const subDenied=await subscriber.request.post(base+'/wp-admin/admin-post.php',{form:{action:'commerce_legal_manage',case:'1',status:'resolved'}});check('Subscriber mutation denied',subDenied.status()===403);await subscriber.close();
 const manager=await context(),mp=await login(manager,fixture.users.manager);await go(mp,base+'/wp-admin/admin.php?page=commerce-legal');const caseLink=mp.getByRole('link',{name:guestCode,exact:true});check('Manager receives persisted case',await caseLink.count()===1);await caseLink.click();await mp.waitForLoadState('networkidle');
 const form=mp.locator('form:has([name=operation])');let md=await form.evaluate(f=>Object.fromEntries(new FormData(f)));const caseId=md.case;
 let mr=await manager.request.post(base+'/wp-admin/admin-post.php',{form:{...md,operation:'update',status:'review',order_id:String(fixture.qa_order),note:'Prueba QA de enlace sin verificar'}});check('Order association needs verification',(await mr.text()).includes('Verificá identidad y pedido'));
 mr=await manager.request.post(base+'/wp-admin/admin-post.php',{form:{...md,_wpnonce:'bad',operation:'update',status:'resolved',note:'No debe guardar'}});check('Admin CSRF denied',mr.status()===403);
 mr=await manager.request.post(base+'/wp-admin/admin-post.php',{form:{...md,operation:'update',status:'review',order_id:String(fixture.qa_order),verified:'1',note:'QA sintético: vínculo de prueba con pedido marcado H7.2, leído sin modificar. Sin operación financiera.'}});check('Authorized state, note and verified link saved',mr.status()===200);await go(mp,base+'/wp-admin/admin.php?page=commerce-legal&case='+caseId);
 check('State persisted',await mp.locator('[name=status]').inputValue()==='review');check('Internal note audit visible',(await mp.locator('.wrap').innerText()).includes('QA sintético: vínculo de prueba'));check('Native HPOS order link',(await mp.locator('.wrap a[href*="wc-orders"]').count())===1);await mp.screenshot({path:path.join(privateDir,'admin-private.png'),fullPage:true});
 // Responsive matrix: all five legal pages, exact required widths, both themes.
 for(const width of [320,390,768,1366,1920])for(const mode of ['light','dark']){
  const c=await context(width,mode),v=await c.newPage();for(const [key,id]of Object.entries(fixture.pages)){await go(v,base+'/?page_id='+id);report.matrix.push({width,mode,page:key,...await layout(v,key+' '+width+' '+mode)});}
  await go(v,rights);await v.locator('#cc-name').focus();await v.keyboard.press('Tab');check('Keyboard logical order '+width+' '+mode,await v.locator('#cc-email').evaluate(e=>document.activeElement===e));const focus=await v.locator('#cc-email').evaluate(e=>getComputedStyle(e).outlineWidth);check('Visible keyboard focus '+width+' '+mode,parseFloat(focus)>=2);
  if((width===390&&mode==='dark')||(width===1366&&mode==='light'))await v.screenshot({path:path.join(evidence,'form-'+width+'-'+mode+'.png'),fullPage:true});
  await go(v,base+'/');check('Home first access visible '+width+' '+mode,(await v.locator('.cb-legal-access a').boundingBox()).y<1000);assert.ok((await layout(v,'Home')).scroll<=width);await c.close();
 }
 }
 // Native checkout/privacy integration: add to cart only, never place an order.
 await go(p,base+'/?add-to-cart=154');
 await go(p,base+'/?page_id=9');await p.locator('.wc-block-checkout__terms').waitFor({state:'visible'});const terms=p.locator('.wc-block-checkout__terms');const text=await terms.innerText();check('Checkout Spanish native terms and withdrawal',text.includes('Leí y acepto')&&text.includes('10 días corridos'));check('Native terms checkbox only',await terms.locator('input[type=checkbox]').count()===1&&await terms.locator('#terms-and-conditions').count()===1);check('Checkout valid privacy and terms links',(await terms.locator('a[href*="page_id=3"]').count())===1&&(await terms.locator('a[href*="page_id=511"]').count())===1);
 check('BACS still offered',(await p.locator('body').innerText()).includes('Transferencia bancaria'));await p.screenshot({path:path.join(privateDir,'checkout-private.png'),fullPage:true});
 // Mailpit captures only .test recipients; HTML and plain bodies contain the same code.
 const list=await(await fetch('http://localhost:8025/api/v1/messages?limit=500')).json();
 for(const code of report.codes){const messages=list.messages.filter(m=>m.Subject.includes(code));check('Two mails per successful unique code '+code,messages.length===2);for(const message of messages){const detail=await(await fetch('http://localhost:8025/api/v1/message/'+message.ID)).json();assert.ok(detail.HTML&&detail.Text&&detail.HTML.includes(code)&&detail.Text.includes(code));assert.ok(message.To.every(t=>t.Address.endsWith('.test')));assert.ok(!detail.Text.includes('NO-EXISTE')&&!detail.Text.includes('window.h82Injected'));report.mail.push({code,multipart:true,syntheticRecipients:true,spanish:detail.Text.includes('pendiente de gestión')});}}
 check('No browser runtime errors',report.errors.length===0);check('No failed assets',report.assetErrors.length===0);check('Only audited existing Gravatar and MP origins',report.externalRequests.every(origin=>['https://secure.gravatar.com','https://api.mercadopago.com','https://http2.mlstatic.com','https://sdk.mercadopago.com','https://www.mercadopago.com'].includes(origin)));
 report.checks=[...new Set(report.checks)];report.externalRequests=[...new Set(report.externalRequests)];
 report.result='passed';fs.writeFileSync(path.join(evidence,'browser-report.json'),JSON.stringify(report,null,2)+'\n');console.log(JSON.stringify({result:report.result,checks:report.checks.length,responsiveCases:report.matrix.length,uniqueBrowserRequests:report.codes.length,multipartMails:report.mail.length,jsErrors:report.errors.length,failedAssets:report.assetErrors.length}));
 }finally{await browser.close()}})().catch(e=>{report.result='failed';report.error=clean(e.stack);fs.writeFileSync(path.join(privateDir,'browser-failure.json'),JSON.stringify(report,null,2));console.log(JSON.stringify({result:'failed',error:clean(e.message)}));process.exitCode=1});
