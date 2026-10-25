/** Read-only size and component inventory of allowlisted local public D1 labs. */
'use strict';const fs=require('node:fs'),cp=require('node:child_process');
const run=args=>cp.execFileSync('docker',args,{encoding:'utf8',stdio:['ignore','pipe','pipe']}).trim();
const stores=[];for(const brand of ['trama','origen']){
 const container='commerce-d1-'+brand+'-web-1';const fields=run(['exec',container,'sh','-c','du -sk /var/www/html; du -sk /var/www/html/wp-content/uploads; find /var/www/html -type f | wc -l; find /var/www/html | wc -l']).split(/\r?\n/).map(x=>Number(x.split(/\s/)[0]));
 const dbBytes=Number(run(['compose','--project-name','commerce-d1-'+brand,'--env-file','.local/d1/'+brand+'/.env','-f','compose.d1.yaml','run','--rm','-T','--no-deps','cli','wp','db','size','--size_format=b']));
 if(!fields.every(x=>x>0)||!dbBytes)throw new Error('Invalid inventory');stores.push({brand,container,filesKiB:fields[0],uploadsKiBIncluded:fields[1],regularFiles:fields[2],allPathsIncludingDirectories:fields[3],dbBytes});
}
const manifest=JSON.parse(fs.readFileSync('.local/h11-release/1.0.0/manifest.json','utf8'));const packages=manifest.packages.map(({slug,version,zip,sha256})=>{const bytes=fs.readFileSync('.local/h11-release/1.0.0/'+zip);const digest=require('node:crypto').createHash('sha256').update(bytes).digest('hex');if(digest!==sha256)throw new Error('Package checksum '+slug);return{slug,version,sha256,zipBytes:bytes.length};});
const report={checkedAtUtc:new Date().toISOString(),scope:'Local public fixture labs; not remote capacity',stores,packages,remoteChanges:false};fs.writeFileSync('docs/evidence/d2/inventory.json',JSON.stringify(report,null,2)+'\n');console.log(JSON.stringify(report));
