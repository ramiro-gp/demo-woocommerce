/** Run only after staging-on in both fixed disposable D1 labs; tests public behavior, not hosting. */
'use strict';
const fs=require('node:fs'),assert=require('node:assert/strict');
const origins=['http://localhost:8190','http://localhost:8191'];
const report={result:'running',scope:'Public DEMO running with staging WP environment in local D1 containers',checks:[],remoteDeployment:false};
let current='initialization';
function check(name,value){current=name;report.checks.push({name,passed:Boolean(value)});assert.ok(value,name);}
function explanation(text){try{text=JSON.stringify(JSON.parse(text));}catch{}return /tienda de demostraci[oó]n|commerce_demo_disabled/i.test(text);}
(async()=>{
  for(const base of origins){
    const home=await fetch(base+'/');check(base+' staging home available',home.ok);const html=await home.text();
    const pagesResponse=await fetch(base+'/?rest_route=/wp/v2/pages&per_page=100');
    check(base+' staging public page discovery works',pagesResponse.ok);const pages=await pagesResponse.json();
    const definitions=[['privacy',/^(?:privacidad-demo|politica-de-privacidad|privacy-policy)$/],['terms',/^(?:terminos-demo|terminos|terminos-y-condiciones)$/],['shipping',/^(?:envios-demo|envios)$/],['returns',/^(?:cambios-demo|cambios-y-devoluciones)$/],['withdrawal',/^(?:arrepentimiento-demo|arrepentimiento|boton-de-arrepentimiento)$/]];
    for(const [key,slug]of definitions){
      const page=pages.find(p=>slug.test(p.slug));
      check(base+' '+key+' reviewed page remains discoverable',Boolean(page?.id));
      check(base+' '+key+' legal link remains rendered',html.includes(page.link));
      const response=await fetch(page.link),body=await response.text();
      check(base+' '+key+' legal GET available in staging',response.status===200);
      check(base+' '+key+' explains DEMO scope',/demostraci[oó]n/i.test(body)&&/No se realizan compras|no recibe compras|no se reciben solicitudes/i.test(body));
      check(base+' '+key+' noindex',/noindex/.test(response.headers.get('x-robots-tag')||''));
      check(base+' '+key+' has no public legal intake form',!/<form\b[^>]*cc-withdrawal-form/i.test(body));
      const direct=await fetch(base+'/?rest_route=/wp/v2/pages/'+page.id);
      check(base+' '+key+' REST remains readable in staging',direct.status===200);
      const blocked=await fetch(page.link,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'cc_name=Persona+DEMO&cc_email=stage%40d1.example.test&cc_message=Solicitud+ficticia'});
      check(base+' '+key+' public POST still denied',blocked.status===403&&explanation(await blocked.text()));
    }
    const checkout=pages.find(p=>/^(?:checkout|finalizar-compra)$/.test(p.slug));
    check(base+' staging checkout page exists',Boolean(checkout));const blocked=await fetch(checkout.link);
    check(base+' staging checkout remains denied',blocked.status===403&&explanation(await blocked.text()));
  }
  report.result='passed';
})().catch(error=>{report.result='failed';report.failedAt=current;console.error(error.message);process.exitCode=1;}).finally(()=>{
  fs.mkdirSync('docs/evidence/d1',{recursive:true});fs.writeFileSync('docs/evidence/d1/staging.json',JSON.stringify(report,null,2)+'\n');
  console.log(JSON.stringify({result:report.result,checks:report.checks.length,failedAt:report.failedAt}));
});
