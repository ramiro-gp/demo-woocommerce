/** Focused encoded-script and mixed-form regressions; never targets an existing shop. */
'use strict';
const fs=require('node:fs'),assert=require('node:assert/strict');
const report={result:'running',checks:[],origins:['http://localhost:8190','http://localhost:8191'],externalServices:false};
let current='initialization';
function check(name,ok){current=name;report.checks.push({name,passed:Boolean(ok)});assert.ok(ok,name);}
function explanation(text){try{text=JSON.stringify(JSON.parse(text));}catch{}return /tienda de demostraci[oó]n|commerce_demo_disabled/i.test(text);}
async function denied(base,route,init={}){
  const response=await fetch(base+route,{...init,redirect:'manual'}),text=await response.text();
  // Apache can reject a sensitive script before WordPress renders the guard explanation.
  check(base+' '+(init.method||'GET')+' '+route+' denied on server',response.status===403);
  check(base+' '+route+' noindex response',/noindex/.test(response.headers.get('x-robots-tag')||''));
}
(async()=>{
  for(const base of report.origins){
    for(const route of ['/%77p-login.php','/%77p-signup.php','/%77p-activate.php','/%78mlrpc.php','/%77p-cron.php','/wc%2dapi/WC_Gateway_BACS/'])await denied(base,route);
    await denied(base,'/?rest_route=/wc/v2/system_status/tools',{method:'OPTIONS'});
    const productsResponse=await fetch(base+'/?rest_route=/wc/store/v1/products');
    check(base+' catalog still available',productsResponse.ok);const products=await productsResponse.json(),simple=products.find(p=>p.type==='simple'&&p.is_purchasable&&p.is_in_stock);
    check(base+' simple fixture available',Boolean(simple));
    for(const extra of [{register:'1',email:'mixed@d1.example.test'},{action:'register',user_login:'d1-visitor'},{action:'edit_address',billing_address_1:'Calle ficticia 123'},{login:'1',username:'d1-visitor'},{save_account_details:'1',account_email:'mixed@d1.example.test'},{woocommerce_checkout_place_order:'1',payment_method:'bacs'}]){
      const body=new URLSearchParams({'add-to-cart':String(simple.id),quantity:'1',...extra});
      await denied(base,'/',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});
    }
    const cart=await fetch(base+'/?rest_route=/wc/store/v1/cart');
    check(base+' cart remains available for token-protected negatives',cart.ok);const token=cart.headers.get('cart-token');
    check(base+' private cart token issued',Boolean(token));
    await denied(base,'/?rest_route=/wc/store/v1/checkout',{method:'PATCH',headers:{'Cart-Token':token,'Content-Type':'application/json'},body:JSON.stringify({payment_method:'bacs'})});
    await denied(base,'/?rest_route=/wc/store/v1/checkout',{method:'POST',headers:{'Cart-Token':token,'X-HTTP-Method-Override':'PATCH','Content-Type':'application/json'},body:JSON.stringify({payment_method:'bacs'})});
    for(const body of [{requests:'scalar'},{requests:[42]},{requests:[{path:'/wc/store/v1/checkout',method:'POST',body:'scalar'}]}])await denied(base,'/?rest_route=/wc/store/v1/batch',{method:'POST',headers:{'Cart-Token':token,'Content-Type':'application/json'},body:JSON.stringify(body)});
  }
  report.result='passed';
})().catch(error=>{report.result='failed';report.failedAt=current;console.error(error.message);process.exitCode=1;}).finally(()=>{
  fs.mkdirSync('docs/evidence/d1',{recursive:true});fs.writeFileSync('docs/evidence/d1/entrypoints.json',JSON.stringify(report,null,2)+'\n');
  console.log(JSON.stringify({result:report.result,checks:report.checks.length,failedAt:report.failedAt}));
});
