/** D1 focused public-demo QA. Only the two disposable, explicitly marked local labs. */
'use strict';
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
process.env.PLAYWRIGHT_BROWSERS_PATH ||= path.join(process.env.LOCALAPPDATA||'C:\\Users\\ramiro\\AppData\\Local','ms-playwright');
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'..'),out=path.join(root,process.env.D1_EVIDENCE_DIR||'docs/evidence/d1');
fs.mkdirSync(out,{recursive:true});
const stores=[{key:'trama',brand:'TRAMA',base:'http://localhost:8190'},{key:'origen',brand:'ORIGEN CASA',base:'http://localhost:8191'}];
const selected=(process.env.D1_STORES||'trama,origen').split(',');
assert.ok(selected.length&&selected.every(x=>stores.some(s=>s.key===x)),'D1_STORES only accepts trama,origen');
const report={result:'running',scope:'D1 public demo, disposable local installations only',checks:[],stores:[],externalRequests:[],mercadoPagoResources:[],screenshots:[],realPayments:false,externalMail:false};
let browser,lastCheck='initialization';
const mp=/mercadopago|mercado-pago|mercadolibre|mlstatic/i;
function check(name,value){lastCheck=name;report.checks.push({name,passed:Boolean(value)});assert.ok(value,name);}
function save(){fs.writeFileSync(path.join(out,'public-'+selected.join('-')+'.json'),JSON.stringify(report,null,2)+'\n');}
function api(s,route){return s.base+'/?rest_route='+route;}
function deniedBody(body){try{body=JSON.stringify(JSON.parse(body));}catch{}return /commerce_demo_disabled|tienda de demostraci[oó]n|demo p[uú]blica/i.test(body);}
async function blocked(c,s,route,method='GET',data,headers={}){
  const response=await c.request.fetch(s.base+route,{method,data,headers,maxRedirects:0});
  const body=await response.text();
  check(s.key+' '+method+' '+route+' is blocked by DEMO server policy',response.status()===403&&deniedBody(body));
  return response;
}
async function context(s,options={}){
  const c=await browser.newContext({viewport:{width:1366,height:1000},reducedMotion:'reduce',serviceWorkers:'block',...options});
  await c.route(/^https?:\/\//,async r=>{
    const u=new URL(r.request().url());
    if(mp.test(u.origin+u.pathname))report.mercadoPagoResources.push({store:s.key,origin:u.origin,path:u.pathname});
    if(stores.some(x=>x.base===u.origin))return r.continue();
    report.externalRequests.push({store:s.key,origin:u.origin,path:u.pathname});
    await r.abort();
  });
  return c;
}
async function discover(c,s){
  const pr=await c.request.get(api(s,'/wc/store/v1/products')+'&per_page=100');
  check(s.key+' Store API catalog works',pr.ok());const products=await pr.json();
  check(s.key+' has DEMO catalog',Array.isArray(products)&&products.length>=2);
  const pagesResponse=await c.request.get(api(s,'/wp/v2/pages')+'&per_page=100');
  check(s.key+' published page discovery works',pagesResponse.ok());const pages=await pagesResponse.json();
  const find=(slugs,rx)=>slugs.map(slug=>pages.find(p=>p.slug===slug)).find(Boolean)||pages.find(p=>rx.test(p.content?.rendered||''));
  s.pages={
    cart:find(['cart','carrito'],/wp-block-woocommerce-cart|woocommerce_cart/),
    checkout:find(['checkout','finalizar-compra'],/wp-block-woocommerce-checkout|woocommerce_checkout/),
    account:find(['my-account','mi-cuenta'],/woocommerce_my_account|woocommerce-account/),
    withdrawal:find(['boton-de-arrepentimiento','arrepentimiento','arrepentimiento-demo'],/commerce_legal_form|cc-withdrawal|no se reciben solicitudes/),
    lookbook:find(['lookbook'],/slb-lookbook|commerce-lookbook/)
  };
  for(const key of ['cart','checkout','account','withdrawal'])check(s.key+' '+key+' page exists',Boolean(s.pages[key]?.id));
  s.simple=products.find(p=>p.type==='simple'&&p.is_in_stock&&p.is_purchasable);
  s.variable=products.find(p=>p.type==='variable'&&p.is_in_stock);
  check(s.key+' simple and variable remain purchasable for cart demonstration',Boolean(s.simple&&s.variable&&s.variable.is_purchasable));
  s.category=products.flatMap(p=>p.categories||[]).find(c=>!['uncategorized','sin-categorizar'].includes(c.slug));
  check(s.key+' categorized catalog',Boolean(s.category));
  const image=s.simple.images?.[0]?.src;
  check(s.key+' product image belongs to this demo',Boolean(image)&&new URL(image).origin===s.base);
  const imageResponse=await c.request.head(image);
  check(s.key+' static media sends noindex independently of PHP',imageResponse.ok()&&/noindex/.test(imageResponse.headers()['x-robots-tag']||''));
  report.stores.push({key:s.key,origin:s.base,brand:s.brand,productCount:products.length,variableCount:products.filter(p=>p.type==='variable').length,productSlugs:products.map(p=>p.slug)});
}
async function browsing(c,s){
  const p=await c.newPage();let pageErrors=0;p.on('pageerror',()=>pageErrors++);
  const routes=[['home',s.base+'/'],['catalog',s.base+'/?post_type=product'],['category',s.base+'/?product_cat='+s.category.slug],['product',s.variable.permalink]];
  if(s.pages.lookbook)routes.push(['lookbook',s.pages.lookbook.link]);
  for(const [name,url]of routes){
    const response=await p.goto(url,{waitUntil:'networkidle'});
    check(s.key+' '+name+' navigation works',response.status()===200);
    check(s.key+' '+name+' sends noindex and nofollow',/noindex/.test(response.headers()['x-robots-tag']||'')&&/nofollow/.test(response.headers()['x-robots-tag']||''));
    const facts=await p.evaluate(()=>({text:document.body.innerText,overflow:document.documentElement.scrollWidth>innerWidth+1,broken:[...document.images].some(i=>i.complete&&i.naturalWidth===0&&new URL(i.currentSrc||i.src).origin===location.origin),jsonld:[...document.querySelectorAll('script[type="application/ld+json"]')].map(n=>n.textContent).join('\n'),canonical:document.querySelector('link[rel="canonical"]')?.href}));
    check(s.key+' '+name+' displays clear DEMO notice',/Tienda de demostraci[oó]n/.test(facts.text)&&/No se realizan compras reales/.test(facts.text));
    check(s.key+' '+name+' preserves brand',facts.text.includes(s.brand));
    check(s.key+' '+name+' local images load',!facts.broken);
    check(s.key+' '+name+' has no fictitious offer schema',!/@type["']?\s*:\s*["'](?:Offer|AggregateOffer|Product)/.test(facts.jsonld));
    check(s.key+' '+name+' canonical stays on this demo or is absent',!facts.canonical||new URL(facts.canonical).origin===s.base);
    if(name==='home'){
      const hotspots=p.locator('.slb-hotspot');
      check(s.key+' Lookbook retains linked product hotspots',await hotspots.count()>=2);
      const target=await hotspots.first().getAttribute('href');
      check(s.key+' Lookbook links stay inside this store',Boolean(target)&&new URL(target,s.base).origin===s.base);
      const linked=await p.goto(new URL(target,s.base).href,{waitUntil:'networkidle'});
      check(s.key+' Lookbook opens a real product',linked.ok()&&await p.locator('body.single-product h1').count()===1);
    }
    if(name==='product'){
      const selects=p.locator('.variations select');
      check(s.key+' native variation controls visible',await selects.count()>0);
      for(const select of await selects.all()){
        const value=await select.locator('option').evaluateAll(xs=>xs.find(o=>o.value&&!o.disabled)?.value);
        check(s.key+' variable attribute has options',Boolean(value));await select.selectOption(value,{force:true});
      }
      await p.locator('.single_add_to_cart_button:not(.disabled)').waitFor();
      s.variation=Number(await p.locator('input.variation_id').inputValue());
      check(s.key+' variation resolves',s.variation>0);
      await p.locator('.single_add_to_cart_button').click();await p.waitForLoadState('networkidle');
      check(s.key+' native variation add to cart succeeds',!await p.locator('.woocommerce-error').count());
      if(await p.locator('.wc-block-mini-cart__button').count()){
        await p.locator('.wc-block-mini-cart__button').click();await p.locator('.wc-block-mini-cart__drawer[aria-hidden="false"]').waitFor();
        check(s.key+' mini cart displays selected product',await p.locator('.wc-block-mini-cart__drawer').innerText().then(t=>t.includes(s.variable.name.replace(/&amp;/g,'&'))));
        await p.keyboard.press('Escape');
      }
    }
  }
  const search=await c.request.get(api(s,'/wc/store/v1/products')+'&search='+encodeURIComponent(s.simple.name));
  check(s.key+' product search works',search.ok()&&(await search.json()).some(x=>x.id===s.simple.id));
  const filtered=await c.request.get(api(s,'/wc/store/v1/products')+'&category='+s.category.id);
  check(s.key+' category filtering works',filtered.ok()&&(await filtered.json()).every(x=>x.categories.some(t=>t.id===s.category.id)));
  for(const theme of ['light','dark'])for(const width of [390,1366]){
    await p.setViewportSize({width,height:900});
    await p.addInitScript(mode=>{localStorage.setItem('commerce-base-color-mode',mode);},theme);
    const response=await p.goto(s.base+'/',{waitUntil:'networkidle'});
    check(s.key+' home '+width+' '+theme+' responds',response.ok());
    check(s.key+' appearance '+width+' '+theme+' is applied',await p.evaluate(mode=>document.documentElement.dataset.colorMode===mode&&document.documentElement.dataset.theme===mode,theme));
    check(s.key+' DEMO notice '+width+' '+theme+' fits viewport',await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
    const filename=s.key+'-home-'+width+'-'+theme+'.png';await p.screenshot({path:path.join(out,filename),fullPage:true});report.screenshots.push(filename);
  }
  check(s.key+' browsing has no uncaught JS errors',pageErrors===0);
  await p.close();
}
async function cartAndSecurity(c,s){
  let response=await c.request.get(api(s,'/wc/store/v1/cart'));
  check(s.key+' cart session remains available',response.ok());
  const token=response.headers()['cart-token'];check(s.key+' cart token issued privately',Boolean(token));
  const headers={'Cart-Token':token};
  const post=async(route,data)=>{const r=await c.request.post(api(s,'/wc/store/v1/'+route),{headers,data});check(s.key+' '+route+' works',r.ok());return r.json();};
  let cart=await post('cart/add-item',{id:s.simple.id,quantity:1});
  check(s.key+' cart includes simple and selected variable',cart.items.some(i=>i.id===s.simple.id)&&cart.items.some(i=>i.id===s.variation));
  const item=cart.items.find(i=>i.id===s.simple.id);
  cart=await post('cart/update-item',{key:item.key,quantity:3});
  check(s.key+' quantity persists',cart.items.find(i=>i.key===item.key)?.quantity===3);
  const p=await c.newPage();const cartFailures=[];
  const observe=response=>{if(response.status()>=400&&new URL(response.url()).origin===s.base)cartFailures.push({status:response.status(),path:new URL(response.url()).pathname});};
  p.on('response',observe);
  const page=await p.goto(s.pages.cart.link,{waitUntil:'networkidle'});
  check(s.key+' native Cart Block renders products',page.ok()&&await p.locator('.wc-block-cart-items__row').count()>=2);
  check(s.key+' native Cart Block loads without blocked requests',cartFailures.length===0);
  p.off('response',observe);
  const filename=s.key+'-cart.png';await p.screenshot({path:path.join(out,filename),fullPage:true});report.screenshots.push(filename);
  // A guest cart cookie must not refer to the other installation, even in the same browser context.
  const other=stores.find(x=>x.key!==s.key),isolation=await c.request.get(api(other,'/wc/store/v1/cart'));
  check(s.key+' cart is isolated from the other demo',isolation.ok()&&(await isolation.json()).items.length===0);
  await blocked(c,s,'/?page_id='+s.pages.checkout.id);
  const proceed=p.locator('.wc-block-cart__submit-button').first();
  check(s.key+' cart presents a route to the checkout restriction',await proceed.isVisible());
  const [checkout]=await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}),proceed.click()]);
  check(s.key+' checkout displays explicit restriction',checkout.status()===403&&/no permite realizar compras/i.test(await p.locator('body').innerText()));
  check(s.key+' blocked checkout requests no personal or banking form',await p.locator('input[type="email"],input[name*="billing"],.wc-block-checkout').count()===0);
  const checkoutPng=s.key+'-checkout-blocked.png';await p.screenshot({path:path.join(out,checkoutPng),fullPage:true});report.screenshots.push(checkoutPng);
  const checkoutData={payment_method:'bacs',billing_address:{first_name:'Persona',last_name:'DEMO',email:'visitor@d1.example.test',address_1:'Calle ficticia 123',city:'Ciudad DEMO',state:'C',postcode:'1414',country:'AR'}};
  for(const route of ['/wc/store/v1/checkout','/wc/store/v1/checkout/123','/wc/store/v1/order/123','/wc/v3/orders','/wc/v2/orders','/wp/v2/users','/wp/v2/settings']){
    await blocked(c,s,'/?rest_route='+route,'GET',undefined,headers);
    await blocked(c,s,'/?rest_route='+route,'POST',checkoutData,headers);
  }
  await blocked(c,s,'/wp-json/wc/store/v1/checkout','POST',checkoutData,headers);
  await blocked(c,s,'/?rest_route=/wc/store/v1/checkout&_method=POST','GET',undefined,headers);
  await blocked(c,s,'/?rest_route=/wc/store/v1/cart/update-customer','POST',checkoutData,headers);
  const checkoutPath=new URL(s.pages.checkout.link).pathname.replace(/\/$/,'');
  await blocked(c,s,checkoutPath+'/order-pay/123/?pay_for_order=true&key=invalid-demo-key');
  await blocked(c,s,checkoutPath+'/order-received/123/?key=invalid-demo-key');
  for(const payload of [
    {requests:[{method:'POST',path:'/wc/store/v1/checkout',body:checkoutData}]},
    {requests:[{method:'GET',path:'/wc/store/v1/checkout'}]},
    {requests:[{method:'POST',path:'/wc/store/v1/batch',body:{requests:[{method:'POST',path:'/wc/store/v1/checkout',body:checkoutData}]}}]},
    {requests:[{method:'POST',path:'/wc/store/v1/cart/add-item',body:{id:s.simple.id,quantity:1}},{method:'POST',path:'/wc/store/v1/checkout',body:checkoutData}]}
  ]){
    await blocked(c,s,'/?rest_route=/wc/store/v1/batch','POST',payload,headers);
    await blocked(c,s,'/?rest_route=/batch/v1','POST',payload,headers);
  }
  for(const route of ['/?wc-ajax=checkout','/?wc-ajax=update_order_review','/?wc-api=WC_Gateway_BACS','/wc-api/WC_Gateway_BACS/','/xmlrpc.php','/wp-login.php?action=register','/wp-login.php?action=lostpassword','/wp-admin/admin-post.php?action=commerce_legal_manage'])await blocked(c,s,route,'POST',{user_login:'demo@d1.example.test'});
  for(const route of ['/wp-login.php','/wp-admin/','/wp-json/wp/v2/users','/?rest_route=/wc/store/v1/order/123&key=invalid-demo-key'])await blocked(c,s,route);
  await blocked(c,s,'/?rest_route=/wc/v2/system_status/tools','OPTIONS');
  await blocked(c,s,'/?page_id='+s.pages.account.id,'POST',{register:'1',email:'visitor@d1.example.test',username:'d1-visitor'});
  await blocked(c,s,'/?page_id='+s.pages.withdrawal.id,'POST',{cc_name:'Persona DEMO',cc_email:'visitor@d1.example.test',cc_message:'Solicitud ficticia D1'});
  const legal=await c.request.get(s.pages.withdrawal.link);check(s.key+' legal page explains public demo restriction',legal.ok()&&/no se reciben solicitudes/i.test(await legal.text()));
  const account=await c.request.get(s.pages.account.link);check(s.key+' account page offers no public login/register/reset form',account.ok()&&!/<form\b[^>]*(?:woocommerce-form|lost_reset_password|register|login)/i.test(await account.text()));
  const sitemap=await c.request.get(s.base+'/wp-sitemap.xml');check(s.key+' native sitemap disabled',sitemap.status()===404);
  const invalidHost=await c.request.get(s.base+'/',{headers:{Host:'wrong-demo.invalid'}});check(s.key+' wrong Host fails closed',invalidHost.status()===503&&/commerce_demo_configuration|configuraci[oó]n/i.test(await invalidHost.text()));
  const afterAttempts=await c.request.get(api(s,'/wc/store/v1/cart'),{headers});
  check(s.key+' denied mixed batches cause no partial cart changes',afterAttempts.ok()&&(await afterAttempts.json()).items.find(i=>i.key===item.key)?.quantity===3);
  cart=await post('cart/remove-item',{key:item.key});check(s.key+' cart removal persists',!cart.items.some(i=>i.key===item.key));
  for(const entry of cart.items)cart=await post('cart/remove-item',{key:entry.key});
  check(s.key+' cart can be emptied',cart.items.length===0);
  await p.close();
}
(async()=>{
  browser=await chromium.launch({headless:true});
  for(const s of stores.filter(s=>selected.includes(s.key))){const c=await context(s);try{await discover(c,s);await browsing(c,s);await cartAndSecurity(c,s);}finally{await c.close();}}
  check('No Mercado Pago resource requested',report.mercadoPagoResources.length===0);
  report.externalRequestsBlocked=report.externalRequests.length;report.result='passed';save();
  console.log(JSON.stringify({result:report.result,checks:report.checks.length,stores:selected,externalRequests:report.externalRequests.length}));
})().catch(error=>{report.result='failed';report.failedAt=lastCheck;save();console.error(JSON.stringify({result:'failed',failedAt:lastCheck,error:error.message}));process.exitCode=1;}).finally(async()=>{if(browser)await browser.close();});
