/** Scan current candidates against historical + D1 runtime values; never prints a secret. */
'use strict';
require('./verify-h11-secrets.cjs');
const fs=require('node:fs'),path=require('node:path'),cp=require('node:child_process');
const sensitive=new Set();
const add=value=>{if(typeof value==='string'&&value.length>=10)sensitive.add(value);};
function walk(value){
  if(!value||typeof value!=='object')return;
  for(const [key,item]of Object.entries(value)){
    if(/sha256|digest|fingerprint/i.test(key))continue;
    if(typeof item==='string'){
      if(/password|nonce|token|(?:^|_)key$|cookie|username|authorization|cart-token/i.test(key))add(item);
      for(const match of item.matchAll(/wc_order_[A-Za-z0-9]+|[?&](?:key|token|_wpnonce)=([^&#\s"'<>]+)/g))add(decodeURIComponent(match[1]||match[0]));
    }else walk(item);
  }
}
for(const brand of ['trama','origen']){
  const runtime=path.join('.local/d1',brand),secrets=path.join(runtime,'secrets'),privateDir=path.join(runtime,'private');
  if(fs.existsSync(secrets))for(const file of fs.readdirSync(secrets))add(fs.readFileSync(path.join(secrets,file),'utf8').trim());
  if(fs.existsSync(privateDir))for(const file of fs.readdirSync(privateDir).filter(x=>x.endsWith('.json')))walk(JSON.parse(fs.readFileSync(path.join(privateDir,file),'utf8')));
}
const files=[...new Set(cp.execFileSync('git',['ls-files','--cached','--others','--exclude-standard'],{encoding:'utf8'}).trim().split(/\r?\n/))];
let textFiles=0;
for(const file of files){
  if(!fs.existsSync(file)||/\.(?:png|jpe?g|webp|woff2)$/i.test(file))continue;
  const text=fs.readFileSync(file,'utf8');textFiles++;
  if([...sensitive].some(value=>text.includes(value)))throw Error('Private D1 runtime value in '+file);
  if(/(?:^|\/)(?:\.env(?!\.example)|wp-config\.php|uploads\/|backups\/)|\.(?:sql|sql\.gz|log|zip|tar\.gz)$/.test(file))throw Error('Runtime artifact candidate: '+file);
}
const report={result:'passed',scope:'Tracked and untracked nonignored candidates; known historical and D1 runtime secrets plus credential patterns',textFiles,d1KnownRuntimeValuesChecked:sensitive.size,privateValuesExposed:false,limitations:'No pattern scanner proves absence of unknown secrets. Fresh-lab privacy assertions and review of the staged diff are also required.'};
fs.mkdirSync('docs/evidence/d1',{recursive:true});fs.writeFileSync('docs/evidence/d1/secrets.json',JSON.stringify(report,null,2)+'\n');
console.log('PASS D1 runtime values and forbidden artifacts absent from '+textFiles+' candidate text files.');
