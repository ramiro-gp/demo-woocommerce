'use strict';
const fs=require('node:fs');
(async()=>{const report=[];for(const route of ['/','/wp-content/uploads/','/wp-content/plugins/','/wp-config.php','/.env','/backup.sql','/?rest_route=/wp/v2/users','/?author=1','/xmlrpc.php','/?rest_route=/wc/store/v1/cart']){
 const r=await fetch('http://localhost:8080'+route,{redirect:'manual'}),t=await r.text();
 report.push({route,status:r.status,directoryIndex:t.includes('Index of'),publicUserSlugs:t.includes('"slug"'),headers:Object.fromEntries([...r.headers].filter(([k])=>/^(server|x-|referrer|content-security|cache-control)/.test(k)))});
}fs.mkdirSync('docs/evidence/h9-1',{recursive:true});fs.writeFileSync('docs/evidence/h9-1/http-'+(process.argv[2]||'before')+'.json',JSON.stringify(report,null,2)+'\n');console.log(JSON.stringify(report.map(({route,status,directoryIndex,publicUserSlugs})=>({route,status,directoryIndex,publicUserSlugs}))));})().catch(e=>{console.error(e.message);process.exitCode=1});
