'use strict';
// Regression scan against actual H11 runtime secrets; never prints those values.
require('./verify-h10-secrets.cjs');
const fs=require('node:fs'),path=require('node:path'),cp=require('node:child_process');
const secrets=new Set();
const add=value=>{if(typeof value==='string'&&value.length>=10)secrets.add(value);};
for(const instance of ['qa','restore','repeat']) {
  const dir=path.join('.local/h11',instance,'secrets');
  if(fs.existsSync(dir))for(const file of fs.readdirSync(dir))add(fs.readFileSync(path.join(dir,file),'utf8').trim());
}
function walk(value) {
  if(!value||typeof value!=='object')return;
  for(const [key,item]of Object.entries(value)) {
    if(/sha256|digest|fingerprint/i.test(key))continue;
    if(typeof item==='string') {
      if(/password|(?:^|_)nonce|(?:^|_)token|(?:^|_)key$|cookie|username|cart-token/i.test(key))add(item);
      for(const match of item.matchAll(/wc_order_[A-Za-z0-9]+|[?&](?:key|token|_wpnonce)=([^&#\s"'<>]+)/g))add(decodeURIComponent(match[1]||match[0]));
    } else walk(item);
  }
}
const privateDir='.local/h11/private';
if(fs.existsSync(privateDir))for(const file of fs.readdirSync(privateDir).filter(x=>x.endsWith('.json')))walk(JSON.parse(fs.readFileSync(path.join(privateDir,file),'utf8')));
const files=cp.execFileSync('git',['ls-files','--cached','--others','--exclude-standard'],{encoding:'utf8'}).trim().split(/\r?\n/);
for(const file of files) {
  if(!fs.existsSync(file)||/\.(?:png|jpe?g|webp|woff2)$/i.test(file))continue;
  const content=fs.readFileSync(file,'utf8');
  if([...secrets].some(value=>content.includes(value)))throw Error('Private H11 value in '+file);
  if(/(?:^|\/)(?:\.env(?!\.example)|wp-config\.php|uploads\/|backups\/)|\.(?:sql|sql\.gz|log|zip|tar\.gz)$/.test(file))throw Error('Runtime artifact candidate: '+file);
}
console.log('PASS H11 runtime credentials, private tokens and forbidden artifacts absent from tracked/candidate files.');
