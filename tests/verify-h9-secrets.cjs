'use strict';
const fs=require('node:fs'),cp=require('node:child_process'),path=require('node:path');const secrets=[];
for(const dir of fs.readdirSync('.local',{withFileTypes:true}).filter(x=>x.isDirectory()).map(x=>path.join('.local',x.name,'secrets'))){if(fs.existsSync(dir))for(const f of fs.readdirSync(dir)){const v=fs.readFileSync(path.join(dir,f),'utf8').trim();if(v.length>=16)secrets.push(v)}}
for(const f of ['.local/h9-audit/backups/h9-fixtures.json','.local/local/backups/h8-2-qa-private.json','.local/local/backups/h8-1-qa-fixtures.json']){if(!fs.existsSync(f))continue;const walk=x=>{if(typeof x==='object'&&x)for(const[k,v]of Object.entries(x)){if(/password|cookie|nonce|^key$/.test(k)&&typeof v==='string'&&v.length>10)secrets.push(v);else walk(v)}};walk(JSON.parse(fs.readFileSync(f)))}
const files=cp.execFileSync('git',['ls-files','--cached','--others','--exclude-standard'],{encoding:'utf8'}).trim().split(/\r?\n/);let count=0;
for(const f of files){if(!fs.existsSync(f)||/\.(png|webp|woff2)$/i.test(f))continue;const s=fs.readFileSync(f,'utf8');if(secrets.some(x=>s.includes(x))||/(?:APP_USR-|TEST-)[A-Za-z0-9-]{25,}|gh[pousr]_[A-Za-z0-9]{25,}|-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/.test(s))throw Error('Potential private value in '+f);count++}
console.log('PASS '+count+' text files; known local secret values and token patterns absent; runtime ignored.');
