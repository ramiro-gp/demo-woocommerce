/** D1.1 native seller/order audit. Synthetic, loopback-only lab; credentials stay in ignored files. */
'use strict';
const fs=require('node:fs'),path=require('node:path'),cp=require('node:child_process'),assert=require('node:assert/strict');
process.env.PLAYWRIGHT_BROWSERS_PATH||=path.join(process.env.LOCALAPPDATA||'C:\\Users\\ramiro\\AppData\\Local','ms-playwright');
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'..'),privateDir=path.join(root,'.local/d1-seller/origen/private'),out=path.join(root,'docs/evidence/d1-seller');
const access=JSON.parse(fs.readFileSync(path.join(privateDir,'access.json'),'utf8'));
assert.equal(access.installation,'d1-1-seller-audit');assert.equal(access.base,'http://localhost:8193');assert.equal(access.mail,'http://localhost:8195');
const base=access.base,mail=access.mail,phase=process.env.SELLER_PHASE||'orders';
assert.ok(['checkout','orders','legal','analytics','mail'].includes(phase));fs.mkdirSync(out,{recursive:true});
const stateFile=path.join(privateDir,'orders-state.json'),state=fs.existsSync(stateFile)?JSON.parse(fs.readFileSync(stateFile,'utf8')):{};
const report={result:'running',phase,origin:base,checks:[],screenshots:[],observations:[],externalRequestsBlocked:0,synthetic:true,realTransfers:false,realRefunds:false,externalMail:false};
let browser,last='initialization';
function check(name,value){last=name;report.checks.push({name,passed:Boolean(value)});assert.ok(value,name);}
function redact(text){for(const u of Object.values(access.users))for(const key of ['password','username'])if(u[key])text=String(text).split(u[key]).join('[private]');return String(text);}
function save(){fs.writeFileSync(path.join(out,phase==='mail'?'mail.json':phase==='legal'?'legal.json':phase==='analytics'?'analytics.json':'orders-'+phase+'.json'),JSON.stringify(report,null,2)+'\n');fs.writeFileSync(stateFile,JSON.stringify(state,null,2)+'\n',{mode:0o600});}
function wp(mode='snapshot'){
  const args=['compose','--project-directory',root,'--project-name','commerce-d1-seller-origen','--env-file',path.join(root,'.local/d1-seller/origen/.env'),'-f',path.join(root,'compose.d1-seller.yaml'),'run','--rm','-T','--no-deps','cli','wp','eval-file','/workspace/tests/seller-orders-fixture.php',mode];
  const output=cp.execFileSync('docker',args,{encoding:'utf8',maxBuffer:20*1024*1024,stdio:['ignore','pipe','pipe']});
  const parsed=JSON.parse(output.slice(output.indexOf('{')));check('Guarded seller CLI '+mode+' succeeds',parsed.result==='passed');return parsed;
}
function facts(label,mode='snapshot'){const data=wp(mode);fs.writeFileSync(path.join(out,'orders-'+label+'-facts.json'),JSON.stringify(data,null,2)+'\n');return data;}
async function context(js=true){const c=await browser.newContext({viewport:{width:1440,height:1100},reducedMotion:'reduce',serviceWorkers:'block',javaScriptEnabled:js,acceptDownloads:true});await c.route(/^https?:\/\//,async r=>{if([base,mail].includes(new URL(r.request().url()).origin))return r.continue();report.externalRequestsBlocked++;return r.abort();});return c;}
async function go(p,url){const r=await p.goto(url,{waitUntil:'networkidle',timeout:60000});check('Native page loads '+new URL(url).pathname,r&&r.status()<400);return r;}
async function shot(p,name){const account=p.locator('#wp-admin-bar-my-account');if(await account.count())await account.evaluate(e=>{e.style.visibility='hidden';});const file=name+'.png';const mask=Object.values(access.users).map(user=>p.getByText(user.username,{exact:true}));await p.screenshot({path:path.join(out,file),fullPage:true,mask,maskColor:'#333333'});report.screenshots.push(file);}
async function login(c,role){const p=await c.newPage();await go(p,base+'/wp-login.php');await p.locator('#user_login').fill(access.users[role].username);await p.locator('#user_pass').fill(access.users[role].password);await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}),p.locator('#wp-submit').click()]);check(role+' native login works',await p.locator('#wpadminbar').count()>0||role==='customer'&&await p.locator('.woocommerce-MyAccount-navigation').count()>0);return p;}
function orderUrl(id){return base+'/wp-admin/admin.php?page=wc-orders&action=edit&id='+id;}
function ownOrder(data,id){return data.orders.find(o=>o.id===id);}
async function changeStatus(p,id,status){await go(p,orderUrl(id));await p.locator('#order_status').selectOption('wc-'+status);await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}),p.locator('button.save_order').click()]);const data=facts(status+'-'+id);check('Native UI persists '+status,ownOrder(data,id)?.status===status);return data;}
async function checkout(){
  const initial=facts('seed','seed');state.fixture=initial.fixture;save();
  const plans=[{key:'main',quantity:2,variation:true,coupon:true,shipping:'flat_rate'},{key:'cancel',quantity:6,shipping:'free_shipping'},{key:'refund',quantity:2,shipping:'local_pickup'}];
  for(const plan of plans){
    if(state.orders?.[plan.key]){check('Own '+plan.key+' checkout preserved from prior successful phase',Boolean(ownOrder(wp(),state.orders[plan.key])));continue;}
    const c=await context();const p=await login(c,'customer');
    const api=route=>base+'/?rest_route=/wc/store/v1/'+route;
    const cartResponse=await c.request.get(api('cart'));check('Native cart available',cartResponse.ok());const token=cartResponse.headers()['cart-token'];check('Cart token kept private',Boolean(token));const headers={'Cart-Token':token};
    const call=async(route,data)=>{const r=await c.request.post(api(route),{headers,data});check('Native cart '+route+' succeeds',r.ok());return r.json();};
    let cart=await cartResponse.json();
    check('Only this order fixture occupies the synthetic customer cart',cart.items.every(i=>[initial.fixture.products.simple,...Object.values(initial.fixture.products.variations)].includes(i.id)));
    for(const coupon of cart.coupons||[])cart=await call('cart/remove-coupon',{code:coupon.code});
    for(const item of cart.items)cart=await call('cart/remove-item',{key:item.key});
    if(plan.variation){
      const r=await c.request.get(api('products/'+initial.fixture.products.variable));check('Own variable product available',r.ok());const product=await r.json();await go(p,product.permalink);
      for(const select of await p.locator('.variations select').all()){const value=await select.locator('option').evaluateAll(xs=>xs.find(o=>o.value==='roble-qa')?.value||xs.find(o=>o.value&&!o.disabled)?.value);await select.selectOption(value,{force:true});}
      await p.locator('.single_add_to_cart_button:not(.disabled)').waitFor();check('Own native variation resolved',Number(await p.locator('input.variation_id').inputValue())===initial.fixture.products.variations.roble);
      await p.locator('.single_add_to_cart_button').click();await p.waitForLoadState('networkidle');
    }
    cart=await call('cart/add-item',{id:initial.fixture.products.simple,quantity:plan.quantity});
    const address={first_name:'Persona QA',last_name:'D1.1',company:'Datos ficticios · no entregar',address_1:'Calle de pruebas 123',address_2:'Laboratorio local',city:'Ciudad DEMO',state:'C',postcode:'1414',country:'AR',phone:'1100000000'};
    cart=await call('cart/update-customer',{billing_address:{...address,email:access.users.customer.email},shipping_address:address});
    if(plan.coupon)cart=await call('cart/apply-coupon',{code:initial.fixture.coupon.code});
    const group=cart.shipping_rates[0],rate=group?.shipping_rates.find(x=>x.rate_id.startsWith(plan.shipping+':'));
    check(plan.key+' chosen delivery rate available',Boolean(rate));cart=await call('cart/select-shipping-rate',{package_id:group.package_id,rate_id:rate.rate_id});
    await go(p,base+'/?page_id='+access.pages.checkout);check('Native Checkout Block appears',await p.locator('.wc-block-checkout').count()>0);check('Only fictitious BACS offered',/Transferencia|BACS/i.test(await p.locator('body').innerText())&&!/Mercado Pago/i.test(await p.locator('body').innerText()));
    const reserved=facts(plan.key+'-checkout-reservation');const ownReservations=reserved.reservations.filter(x=>[initial.fixture.products.simple,initial.fixture.products.variations.roble].includes(Number(x.product_id)));
    check(plan.key+' merely opening checkout does not reserve physical stock',ownReservations.length===0);
    if(plan.key==='main')await shot(p,'orders-bacs-checkout-qa');
    for(const checkbox of await p.locator('.wc-block-checkout__terms input[type="checkbox"]').all())await checkbox.check();
    const native=p.waitForResponse(r=>r.request().method()==='POST'&&/wc\/store\/v1\/checkout(?:\?|&|$|\/)/.test(decodeURIComponent(r.url())),{timeout:60000});
    await p.locator('.wc-block-components-checkout-place-order-button').click();const placed=await native;check(plan.key+' native place-order succeeds',placed.ok());const order=await placed.json();
    check(plan.key+' BACS receives an on-hold order',order.order_id>0&&order.status==='on-hold'&&order.payment_method==='bacs');state.orders||={};state.orders[plan.key]=order.order_id;save();
    const after=facts(plan.key+'-received');check(plan.key+' stored buyer/delivery data exists',ownOrder(after,order.order_id)?.buyer_is_synthetic&&ownOrder(after,order.order_id)?.has_shipping_address);
    check(plan.key+' paid-order reservation is released',!after.reservations.some(x=>Number(x.order_id)===order.order_id));
    if(plan.key==='main'){check('Main simple stock reduces 8→6 and Roble variation6→5',after.stock.simple.quantity===6&&after.stock.roble.quantity===5);}
    // Cancellation must restore its six units before the refund checkout can reserve two.
    if(plan.key==='cancel'){
      check('Cancellation fixture exhausts remaining simple stock',after.stock.simple.quantity===0&&after.stock.simple.status==='outofstock');
      const manager=await context(),mp=await login(manager,'manager');const cancelled=await changeStatus(mp,order.order_id,'cancelled');check('Native cancellation restores six units',cancelled.stock.simple.quantity===6&&cancelled.stock.simple.status==='instock');await shot(mp,'orders-cancelled-restock');await manager.close();
    }
    await c.close();
  }
  const probe=facts('reservation-probe','reservation-probe').reservation_probe;
  check('Native pending-order reservation/release works without reducing physical stock',probe?.reserved_rows.length===1&&probe.held_quantity===1&&probe.released&&probe.physical_stock_unchanged);
  report.observations.push('WC11.2 no reserva inventario por abrir el checkout. La reserva ocurre durante el POST final; BACS En espera descuenta stock y libera la reserva. La prueba separada de reservar/liberar un pedido pendiente se ejecutó con funciones nativas por CLI, sin inventar una pantalla de reservas.');
}
async function refund(p,id,amount,label){
  const before=ownOrder(wp(),id).total_refunded;await go(p,orderUrl(id));await p.locator('button.refund-items').click();await p.locator('#refund_amount').waitFor({state:'visible'});
  const item=p.locator('#order_line_items tr.item').first();await item.locator('.refund_order_item_qty').fill('1');await item.locator('.refund_order_item_qty').dispatchEvent('change');await item.locator('.refund_line_total').fill(String(amount));await item.locator('.refund_line_total').dispatchEvent('change');await p.locator('#refund_amount').fill(String(amount));await p.locator('#refund_reason').fill('D11O · '+label+' ficticio. Registro QA sin devolución real de dinero.');await p.locator('#restock_refunded_items').check();
  check('BACS exposes manual refund only',await p.locator('.do-api-refund').count()===0&&await p.locator('.do-manual-refund').isVisible());await shot(p,'orders-refund-'+label+'-form');
  p.once('dialog',d=>d.accept());const response=p.waitForResponse(r=>r.request().method()==='POST'&&r.request().postData()?.includes('action=woocommerce_refund_line_items'),{timeout:45000});await p.locator('.do-manual-refund').click();const r=await response;await p.waitForLoadState('networkidle');await go(p,orderUrl(id));const after=facts('refund-'+label);check('Native '+label+' manual refund persists',r.ok()&&ownOrder(after,id).total_refunded===before+amount);return after;
}
async function orders(){
  check('Three native BACS orders created first',Boolean(state.orders?.main&&state.orders?.cancel&&state.orders?.refund));
  const c=await context(),p=await login(c,'manager');await go(p,base+'/wp-admin/admin.php?page=wc-orders');await shot(p,'orders-manager-list');
  await go(p,orderUrl(state.orders.main));
  const text=await p.locator('body').innerText();check('Manager sees buyer and shipping address',/Persona QA/.test(text)&&/Calle de pruebas 123/.test(text));check('Manager sees decoration variation and delivery',/Roble QA/.test(text)&&/Terminaci[oó]n QA/.test(text)&&/Envío|envío|Tarifa|QA|fijo/i.test(text));check('Manager identifies BACS payment',await p.locator('#_payment_method').inputValue()==='bacs');
  await shot(p,'orders-manager-detail');
  const before=wp();const own=ownOrder(before,state.orders.main);
  if(!own.has_private_note){await p.locator('#add_order_note').fill('D11O · preparación QA: transferencia ficticia verificada sólo para simular el flujo; no hubo dinero real.');await p.locator('#order_note_type').selectOption('');await p.locator('button.add_note').click();await p.getByText('D11O · preparación QA:',{exact:false}).first().waitFor();}
  if(!own.has_customer_note){await p.locator('#add_order_note').fill('D11O · aviso ficticio: comenzamos la preparación del pedido QA sin envío real.');await p.locator('#order_note_type').selectOption('customer');await p.locator('button.add_note').click();await p.getByText('D11O · aviso ficticio:',{exact:false}).first().waitFor();}
  let data=wp();check('Private and customer notes are distinct and persistent',ownOrder(data,state.orders.main).has_private_note&&ownOrder(data,state.orders.main).has_customer_note);
  const stockBeforeStatus=data.stock.simple.quantity;
  if(ownOrder(data,state.orders.main).status==='on-hold')data=await changeStatus(p,state.orders.main,'processing');
  if(ownOrder(data,state.orders.main).status==='processing')data=await changeStatus(p,state.orders.main,'completed');
  check('Completing main order does not reduce stock twice',data.stock.simple.quantity===stockBeforeStatus&&data.stock.roble.quantity===5);await shot(p,'orders-manager-completed');
  const refundOrder=ownOrder(data,state.orders.refund);
  if(refundOrder.status==='on-hold')await changeStatus(p,state.orders.refund,'processing');
  data=wp();const ref=ownOrder(data,state.orders.refund);
  if(ref.refund_count===0){data=await refund(p,state.orders.refund,10000,'parcial');}
  if(ownOrder(data,state.orders.refund).refund_count===1){check('Partial refund restores exactly one unit and keeps processing',data.stock.simple.quantity===5&&ownOrder(data,state.orders.refund).status==='processing'&&ownOrder(data,state.orders.refund).total_refunded===10000);}
  if(ownOrder(data,state.orders.refund).refund_count===1){data=await refund(p,state.orders.refund,10000,'total-restante');}
  check('Remaining full manual refund is recorded without transferring money',ownOrder(data,state.orders.refund).status==='refunded'&&ownOrder(data,state.orders.refund).total_refunded===20000&&ownOrder(data,state.orders.refund).refund_count===2);
  check('Refund restock restores both units exactly',data.stock.simple.quantity===6&&data.stock.roble.quantity===5);await shot(p,'orders-manager-refunded');
  report.orderStates=data.orders.map(o=>({id:o.id,status:o.status,total:o.total,totalRefunded:o.total_refunded,paymentMethod:o.payment_method}));report.inventory={initial:state.fixture.initial_stock,final:data.stock};
  report.responsive=[];await go(p,orderUrl(state.orders.main));
  for(const width of [1024,390]){await p.setViewportSize({width,height:900});const geometry=await p.evaluate(()=>({viewport:innerWidth,documentWidth:document.documentElement.scrollWidth,statusVisible:Boolean(document.querySelector('#order_status')),itemsScrollable:[...document.querySelectorAll('.woocommerce_order_items_wrapper')].some(e=>e.scrollWidth>e.clientWidth+1)}));report.responsive.push(geometry);await shot(p,'orders-manager-detail-'+width);}
  report.observations.push('Procesando y Completado son estados; el control de acreditación BACS y el envío físico requieren una acción humana. No hay estado de envío/tracking propio en la plantilla.','El botón Reembolsar manualmente registra la operación. No envía dinero por BACS. Reponer inventario es una elección explícita en ese formulario.');
  await c.close();
}
async function legal(){
  check('Own completed order is available for synthetic association',Boolean(state.orders?.main));
  if(!state.legalCode){const c=await context(false),p=await c.newPage();await go(p,base+'/?page_id='+access.pages.withdrawal);check('Original legal form available only in separate seller lab',await p.locator('.cc-withdrawal-form').isVisible());await p.locator('#cc-name').fill('Persona QA D1.1');await p.locator('#cc-email').fill(access.users.customer.email);await p.locator('#cc-reference').fill('Pedido QA #'+state.orders.main);await p.locator('#cc-message').fill('Solicitud ficticia de laboratorio. Sin cancelación, reembolso ni trámite real.');await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}),p.locator('.cc-withdrawal-form button[type="submit"]').click()]);state.legalCode=(await p.locator('.cc-receipt-code').innerText()).trim();check('Synthetic intake returns its receipt code',/^ARR-\d{8}-[A-F0-9]{20}$/.test(state.legalCode));check('Receipt does not claim cancellation or money returned',/No significa que est[eé] aprobada/.test(await p.locator('body').innerText()));save();await c.close();}
  for(const role of ['manager','admin']){
    const c=await context(),p=await login(c,role);await go(p,base+'/wp-admin/admin.php?page=commerce-legal');check(role+' can list legal requests',await p.getByRole('heading',{name:'Solicitudes de arrepentimiento',exact:true}).count()===1);await shot(p,'legal-'+role+'-list');await p.getByRole('link',{name:state.legalCode,exact:true}).click();await p.waitForLoadState('networkidle');
    const status=role==='manager'?'review':'awaiting';await p.locator('[name="status"]').selectOption(status);await p.locator('[name="order_id"]').fill(String(state.orders.main));await p.locator('[name="verified"]').check();await p.locator('[name="note"]').fill(role==='manager'?'D11O QA: identidad y relación con el pedido verificadas con datos ficticios del laboratorio. Sin documentación personal ni operación financiera.':'D11O QA Admin: revisión ficticia registrada. Esperando información simulada, sin cancelar ni reembolsar el pedido.');await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}),p.locator('button[name="operation"][value="update"]').click()]);check(role+' legal state saved',await p.locator('[name="status"]').inputValue()===status);check(role+' verified order association uses native HPOS link',await p.locator('.wrap a[href*="wc-orders"]').count()===1);check(role+' sees audit notes',await p.locator('.wrap ol li').count()>=2);await shot(p,'legal-'+role+'-detail');await c.close();
  }
  const data=facts('legal-managed');const request=data.legal.find(r=>r.code===state.legalCode);check('Legal workflow preserves completed order/payment state',request?.status==='awaiting'&&Number(request.order_id)===state.orders.main&&ownOrder(data,state.orders.main).status==='completed');check('Both legal notification transports succeeded to isolated Mailpit',request?.consumer_mail==='sent'&&request.admin_mail==='sent');report.legal={id:Number(request.id),status:request.status,orderId:Number(request.order_id),consumerMail:request.consumer_mail,merchantMail:request.admin_mail};
}
async function analytics(){
  const baseline=facts('analytics-synchronized','sync-analytics'),day=baseline.business_date;
  const c=await context(),p=await login(c,'manager');
  const routes=[['overview','/analytics/overview'],['revenue','/analytics/revenue'],['orders','/analytics/orders'],['products','/analytics/products'],['coupons','/analytics/coupons'],['customers','/analytics/customers']];
  report.reports=[];report.nativeMetrics=[];const pending=[];
  p.on('response',response=>{const u=new URL(response.url()),route=decodeURIComponent(u.searchParams.get('rest_route')||u.pathname);if(!response.ok()||!route.includes('/wc-analytics/reports/'))return;pending.push((async()=>{try{const data=await response.json(),totals=data?.totals;const rows=Array.isArray(data)?data.map(row=>Object.fromEntries(Object.entries(row).filter(([key])=>['product_id','variation_id','coupon_id','orders_count','items_sold','net_revenue','amount','customer_id','total_spend','total_orders'].includes(key)))):undefined;report.nativeMetrics.push({route,after:u.searchParams.get('after'),before:u.searchParams.get('before'),totals:totals||undefined,rows});}catch{}})());});
  for(const [key,route]of routes){
    await go(p,base+'/wp-admin/admin.php?page=wc-admin&path='+encodeURIComponent(route)+'&period=custom&compare=previous_period&after='+day+'&before='+day);
    await p.locator('.woocommerce-layout__main').waitFor({timeout:45000});
    const understood=p.getByRole('button',{name:/^Entendido$|^Got it$/i}).first();
    if(await understood.isVisible()){await shot(p,'analytics-'+key+'-onboarding');await understood.click();await p.waitForLoadState('networkidle');}
    check('Native Analytics '+key+' page is rendered',await p.locator('.woocommerce-layout__main').innerText().then(t=>t.length>40&&!/Ha ocurrido un error cr[ií]tico/.test(t)));
    const text=await p.locator('.woocommerce-layout__main').innerText();report.reports.push({key,text:redact(text).slice(0,11000)});await shot(p,'analytics-'+key);
    if(key==='products'){
      const button=p.locator('button.woocommerce-table__download-button').first();check('Native product report offers CSV download',await button.isVisible()&&await button.isEnabled());
      const download=p.waitForEvent('download',{timeout:30000});await button.click();const file=await download;const destination=path.join(privateDir,'analytics-products.csv');await file.saveAs(destination);const csv=fs.readFileSync(destination,'utf8');
      check('Native Analytics CSV contains QA product rows',csv.split(/\r?\n/).filter(Boolean).length>=2&&/D11O/.test(csv));
      report.csvExport={nativeButton:true,privateArtifact:true,rows:csv.split(/\r?\n/).filter(Boolean).length-1,headers:csv.split(/\r?\n/)[0],sha256:require('node:crypto').createHash('sha256').update(csv).digest('hex')};
    }
  }
  await Promise.all(pending);
  const metric=report.nativeMetrics.find(r=>/\/orders\/stats/.test(r.route)&&r.after?.startsWith(day)&&Number.isFinite(r.totals?.net_revenue)&&r.totals?.num_items_sold!==undefined);
  const expectedNet=baseline.orders.filter(o=>['completed','processing','on-hold','refunded'].includes(o.status)&&o.date_paid_set).reduce((sum,o)=>sum+o.total-o.shipping_total-o.total_refunded,0);
  check('Native Analytics current-day totals were observed',Boolean(metric));
  check('Analytics net revenue reconciles with synthetic paid/refunded ledger',Number(metric.totals.net_revenue)===expectedNet);report.reconciliation={businessDate:day,expectedNetFromSyntheticOrders:expectedNet,analyticsNetRevenue:Number(metric.totals.net_revenue),analyticsOrderCount:metric.totals.orders_count,analyticsItemCount:metric.totals.num_items_sold,realRevenue:false};
  report.observations.push('Datos exclusivos de pedidos y reembolsos QA del laboratorio. Se ejecutó sincronización mediante datastores oficiales porque el cron del laboratorio está desactivado; no se hicieron consultas financieras reales.');await c.close();
}
async function emails(){
  const listResponse=await fetch(mail+'/api/v1/messages?limit=500');check('Isolated Mailpit responds',listResponse.ok);const list=(await listResponse.json()).messages;check('Native workflow produced captured mail',Array.isArray(list)&&list.length>0);
  const messages=await Promise.all(list.map(async item=>{const [r,h]=await Promise.all([fetch(mail+'/api/v1/message/'+item.ID),fetch(mail+'/api/v1/message/'+item.ID+'/headers')]);check('Captured message is readable',r.ok&&h.ok);const message=await r.json();message.Headers=await h.json();return message;}));
  check('All recipients synthetic .test',messages.every(m=>[...(m.To||[]),...(m.Cc||[]),...(m.Bcc||[])].every(r=>/@[^@]+\.test$/i.test(r.Address))));check('No QA password appears in messages',messages.every(m=>Object.values(access.users).every(u=>!String(m.HTML||'').includes(u.password)&&!String(m.Text||'').includes(u.password))));
  check('Every sender is also synthetic',messages.every(m=>/@[^@]+\.test$/i.test(m.From?.Address||'')));
  check('Captured mail never links to historical or public D1 shops',messages.every(m=>!/localhost:(?:8080|8082|8190|8191)(?:\/|\b)/.test(String(m.HTML||'')+String(m.Text||''))));
  const types=m=>{const key=Object.keys(m.Headers||{}).find(k=>k.toLowerCase()==='x-seller-qa-type');return key?[m.Headers[key]].flat():[];};
  for(const type of ['new_order','cancelled_order','customer_on_hold_order','customer_processing_order','customer_completed_order','customer_cancelled_order','customer_refunded_order','customer_note'])check('Native '+type+' transport captured',messages.some(m=>types(m).includes(type)));
  const text=m=>String(m.Text||'')+' '+String(m.Subject||'');
  check('Private seller note is never emailed',messages.every(m=>!text(m).includes('D11O · preparación QA')));
  check('Customer-facing note is present in its native email',messages.some(m=>types(m).includes('customer_note')&&text(m).includes('D11O · aviso ficticio')));
  for(const [name,rx]of [['merchant new order',/Nuevo pedido|New order/i],['merchant cancellation',/Pedido cancelado|cancelled order|cancelado/i],['processing',/procesando|preparaci[oó]n/i],['completed',/completado|Finalizamos la gesti[oó]n/i],['manual refund clarification',/registro no confirma por s[ií] solo|no confirma.*dinero/i],['consumer legal receipt',/Constancia de recepci[oó]n/i],['merchant legal notification',/Nueva solicitud de arrepentimiento/i]])check(name+' email captured',messages.some(m=>rx.test(text(m))));
  check('Native low-stock and exhausted-stock merchant alerts captured',messages.some(m=>/Producto con poco stock/.test(m.Subject))&&messages.some(m=>/Producto agotado/.test(m.Subject)));
  report.messages=messages.map(m=>({subject:m.Subject,types:types(m),recipientCount:(m.To||[]).length,hasHtml:Boolean(m.HTML),hasText:Boolean(m.Text),synthetic:true,refundClarifiesMoney:/no confirma.*dinero|registro no confirma por s[ií] solo/i.test(text(m)),cancelClarifiesMoney:/no devuelve dinero autom[aá]ticamente/i.test(text(m)),onHoldClarifiesPayment:/pago queda pendiente de confirmaci[oó]n/i.test(text(m))}));
  report.observations.push('El asunto nativo de Completado dice «está en camino» en es_AR; no demuestra envío. La configuración nativa de email permite corregirlo sin desarrollar un panel ni un plugin. Los cuerpos de Commerce Communications distinguen pago pendiente, gestión finalizada y reembolso registrado sin acreditar dinero.');
  const c=await context(),p=await c.newPage();await go(p,mail+'/');await shot(p,'mailpit-workflow-list');
  for(const [key,predicate]of [['merchant-new-order',m=>types(m).includes('new_order')],['merchant-cancelled',m=>types(m).includes('cancelled_order')],['customer-completed',m=>types(m).includes('customer_completed_order')],['customer-refund',m=>types(m).includes('customer_refunded_order')],['merchant-legal',m=>/Nueva solicitud de arrepentimiento/.test(m.Subject)]]){
    const message=messages.find(predicate);check('Mailpit '+key+' message available',Boolean(message));await go(p,mail+'/');await p.getByText(message.Subject,{exact:true}).first().click();await p.waitForLoadState('networkidle');await shot(p,'mail-'+key);
  }
  await c.close();
}
(async()=>{browser=await chromium.launch({headless:true});if(phase==='checkout')await checkout();if(phase==='orders')await orders();if(phase==='legal')await legal();if(phase==='analytics')await analytics();if(phase==='mail')await emails();report.result='passed';save();console.log(JSON.stringify({result:report.result,phase,checks:report.checks.length,screenshots:report.screenshots.length}));})().catch(error=>{report.result='failed';report.failedAt=last;save();fs.writeFileSync(path.join(privateDir,'orders-'+phase+'-failure.json'),JSON.stringify({message:error.message,stack:error.stack},null,2));console.error(JSON.stringify({result:'failed',phase,failedAt:last,error:redact(error.message).slice(0,900)}));process.exitCode=1;}).finally(async()=>{if(browser)await browser.close();});
