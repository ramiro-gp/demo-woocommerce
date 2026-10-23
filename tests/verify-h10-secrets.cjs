'use strict';
require('./verify-h9-secrets.cjs');
const fs=require('node:fs'),cp=require('node:child_process'),path=require('node:path');
const sensitive=[];const walk=x=>{if(!x||typeof x!=='object')return;for(const[k,v]of Object.entries(x)){if(/sha256|fingerprint/i.test(k))continue;if(typeof v==='string'){if(/password|nonce|(?:^|_)key$|token|username/i.test(k)&&v.length>=10)sensitive.push(v);for(const match of v.matchAll(/wc_order_[A-Za-z0-9]+|[?&](?:key|token|_wpnonce)=([^&#\s"'<>]+)/g))sensitive.push(decodeURIComponent(match[1]||match[0]));}else walk(v)}};
for(const instance of ['origen-casa','h10-repeat']){const dir=path.join('.local',instance,'backups');if(fs.existsSync(dir))for(const file of fs.readdirSync(dir).filter(f=>/^h10.*\.json$/.test(f))){try{walk(JSON.parse(fs.readFileSync(path.join(dir,file),'utf8')))}catch{}}}
const files=cp.execFileSync('git',['ls-files','--cached','--others','--exclude-standard'],{encoding:'utf8'}).trim().split(/\r?\n/);for(const file of files){if(!fs.existsSync(file)||/\.(?:png|jpe?g|webp|woff2)$/i.test(file))continue;const text=fs.readFileSync(file,'utf8');if(sensitive.some(value=>text.includes(value)))throw Error('Private H10 credential/token in '+file)}
console.log('PASS H10 private credentials/order keys absent from tracked and candidate text files.');
