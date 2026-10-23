'use strict';
const fs=require('node:fs'),assert=require('node:assert/strict');
const base='http://localhost:8184';
const report={result:'running',origin:base,checks:[],httpsTested:false,hostCacheTested:false};
function check(name,passed){report.checks.push({name,passed:Boolean(passed)});assert.ok(passed,name);}
(async()=>{
  for(const [route,name]of [['/cart/','cart'],['/checkout/','checkout'],['/my-account/','account'],['/?rest_route=/wc/store/v1/cart','Store API cart']]) {
    const r=await fetch(base+route,{redirect:'manual'});
    check(name+' responds',r.status>=200&&r.status<400);
    const cache=r.headers.get('cache-control')||'';
    check(name+' excludes shared caching',/no-store|private/.test(cache)&&!/(?:^|[,\s])public|s-maxage=[1-9]/i.test(cache));
    check(name+' carries noindex',/noindex/.test(r.headers.get('x-robots-tag')||''));
  }
  for(const route of ['/wp-config.php','/forbidden-backup.sql']) {
    const r=await fetch(base+route);check('Sensitive file path is blocked '+route,r.status===403||r.status===404);
  }
  const robots=await fetch(base+'/robots.txt');check('DEMO robots disallows crawling',/Disallow:\s*\//.test(await robots.text()));
  const sitemap=await fetch(base+'/wp-sitemap.xml');check('Native sitemap stays disabled for DEMO',sitemap.status===404);
  const home=await fetch(base+'/');
  const showcase=await fetch(base+'/?commerce-base-showcase=1');
  check('Excluded optional development showcase responds 404',showcase.status===404);
  const demoImage=await fetch(base+'/wp-content/themes/commerce-base/assets/images/editorial-specimen.webp');
  check('Excluded editorial DEMO image is absent',demoImage.status===404);
  check('Home has restrictive framing header',(home.headers.get('x-frame-options')||'').toUpperCase()==='SAMEORIGIN');
  check('Home prevents MIME sniffing',home.headers.get('x-content-type-options')==='nosniff');
  check('Home is noindex',/noindex/.test(home.headers.get('x-robots-tag')||''));
  check('PHP version is not disclosed',!home.headers.get('x-powered-by'));
  report.result='passed';
})().catch(()=>{report.result='failed';process.exitCode=1;}).finally(()=>{
  fs.mkdirSync('docs/evidence/h11',{recursive:true});fs.writeFileSync('docs/evidence/h11/http-cache.json',JSON.stringify(report,null,2)+'\n');
  console.log(report.result+' H11 HTTP/cache checks: '+report.checks.filter(x=>x.passed).length+'/'+report.checks.length);
});
