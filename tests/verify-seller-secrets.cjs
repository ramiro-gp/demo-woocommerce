/** D1.1 candidate scan. Reads ignored runtime values, writes only the new seller evidence. */
'use strict';
require('./verify-h11-secrets.cjs'); // Read-only historical regression checks; never rewrites D1 evidence.
const fs=require('node:fs'),path=require('node:path'),cp=require('node:child_process');
const values=new Set(),sources={d1SecretFiles:0,sellerSecretFiles:0,privateJsonFiles:0};
const sensitiveKey=/password|passwd|(?:^|[_-])pwd(?:$|[_-])|(?:^|[_-])pass(?:$|[_-])|user_login|user_pass|username|cookie|nonce|token|authorization|(?:^|[_-])key$|(?:^|[_-])secret$|session(?:[_-]?(?:id|key|secret))?$/i;
function add(value){if(typeof value==='string'&&value.trim().length>=10)values.add(value.trim());}
function decoded(value){try{return decodeURIComponent(value);}catch{return value;}}
function walk(value,sensitive=false){
  if(typeof value==='string'){
    if(sensitive)add(value);
    for(const match of value.matchAll(/wc_order_[A-Za-z0-9]+|[?&](?:key|token|_wpnonce|nonce|password)=([^&#\s"'<>]+)/g))add(decoded(match[1]||match[0]));
    for(const match of value.matchAll(/\beyJ[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\b/g))add(match[0]);
    return;
  }
  if(!value||typeof value!=='object')return;
  // FormData and header inventories sometimes serialize a sensitive field as {name,value}.
  if(typeof value.name==='string'&&sensitiveKey.test(value.name)&&Object.hasOwn(value,'value'))walk(value.value,true);
  if(Array.isArray(value)&&value.length===2&&typeof value[0]==='string'&&sensitiveKey.test(value[0]))walk(value[1],true);
  for(const [key,item]of Object.entries(value)){
    if(/sha256|digest|fingerprint/i.test(key))continue;
    walk(item,sensitive||sensitiveKey.test(key));
  }
}
function privateFiles(dir){
  if(!fs.existsSync(dir))return;
  for(const entry of fs.readdirSync(dir,{withFileTypes:true})){
    const file=path.join(dir,entry.name);
    if(entry.isDirectory())privateFiles(file);
    else if(entry.isFile()&&entry.name.endsWith('.json')){walk(JSON.parse(fs.readFileSync(file,'utf8')));sources.privateJsonFiles++;}
  }
}
for(const family of ['d1','d1-seller'])for(const brand of ['trama','origen']){
  const runtime=path.join('.local',family,brand),dir=path.join(runtime,'secrets');
  if(fs.existsSync(dir))for(const entry of fs.readdirSync(dir,{withFileTypes:true}))if(entry.isFile()){add(fs.readFileSync(path.join(dir,entry.name),'utf8'));sources[family==='d1'?'d1SecretFiles':'sellerSecretFiles']++;}
  privateFiles(path.join(runtime,'private'));
}
const files=[...new Set(cp.execFileSync('git',['ls-files','-z','--cached','--others','--exclude-standard'],{encoding:'utf8'}).split('\0').filter(Boolean))];
const credentialPattern=/(?:APP_USR-|TEST-)[A-Za-z0-9-]{25,}|gh[pousr]_[A-Za-z0-9]{25,}|github_pat_[A-Za-z0-9_]{40,}|sk-(?:proj-)?[A-Za-z0-9_-]{35,}|sk_(?:live|test)_[A-Za-z0-9]{20,}|AKIA[0-9A-Z]{16}|-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/;
let textFiles=0,binaryFiles=0;
for(const file of files){
  if(!fs.existsSync(file))continue;
  const normalized=file.replaceAll('\\','/'),base=path.posix.basename(normalized);
  if(/(?:^|\/)(?:\.local|private|secrets|backups)(?:\/|$)|(?:^|\/)wp-config\.php$|(?:^|\/)uploads\/|\.(?:sql(?:\.gz)?|log|zip|tar\.gz)$/i.test(normalized)||(base.startsWith('.env')&&base!=='.env.example'))throw Error('Forbidden runtime candidate: '+file);
  if(/\.(?:png|jpe?g|webp|gif|ico|woff2?|mp4|pdf)$/i.test(file)){binaryFiles++;continue;}
  const content=fs.readFileSync(file,'utf8');textFiles++;
  if([...values].some(value=>content.includes(value)))throw Error('Known private seller/D1 value in candidate: '+file);
  if(credentialPattern.test(content))throw Error('Potential credential pattern in candidate: '+file);
}
const report={result:'passed',scope:'Tracked and untracked nonignored candidates; historical H11 regression plus current D1 and seller runtime credentials/private JSON',textFiles,binaryFiles,knownPrivateValuesChecked:values.size,sources,privateValuesPrinted:false,previousD1EvidenceRewritten:false,limitations:'Pattern scans do not establish absence of unknown secrets. PNG pixels are not text-scanned; screenshots require visual privacy review. Synthetic .test contact details are intentional QA data.'};
fs.mkdirSync('docs/evidence/d1-seller',{recursive:true});fs.writeFileSync('docs/evidence/d1-seller/secrets.json',JSON.stringify(report,null,2)+'\n');
console.log('PASS seller/D1 known private values and credential patterns absent from '+textFiles+' candidate text files; runtime artifacts excluded.');
