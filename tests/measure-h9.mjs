/** Cold-cache local Lighthouse comparison. Raw traces stay private in .local. */
import fs from 'node:fs';import os from 'node:os';import {createRequire} from 'node:module';
import lighthouse from '../.local/h9-tools/node_modules/lighthouse/core/index.js';
import * as launcher from '../.local/h9-tools/node_modules/chrome-launcher/dist/index.js';
const require=createRequire(import.meta.url);process.env.PLAYWRIGHT_BROWSERS_PATH||='C:\\Users\\ramiro\\AppData\\Local\\ms-playwright';const {chromium}=require('playwright');
const phase=process.argv[2]||'before',base='http://localhost:8199',fx=JSON.parse(fs.readFileSync('.local/h9-audit/backups/h9-fixtures.json'));
const pages=[['home','/'],['catalog','/?post_type=product'],['category','/?product_cat=remeras'],['simple','/?product=bolso-transito'],['variable','/?product=remera-base'],['cart','/?page_id=8'],['checkout','/?page_id=9'],['account','/?page_id=10'],['privacy','/?page_id=3'],['terms','/?page_id=511'],['withdrawal','/?page_id=514']];
const selected=process.argv[3]&&process.argv[3]!=='all'?process.argv[3].split(','):undefined,reportPath='docs/evidence/h9-2/lighthouse-'+phase+'.json';
const repeats=Math.max(1,Math.min(3,Number(process.argv[4])||1));
const report=selected&&fs.existsSync(reportPath)?JSON.parse(fs.readFileSync(reportPath)):{phase,generatedUTC:new Date().toISOString(),environment:{os:os.platform()+' '+os.release(),cpu:os.cpus()[0].model,logicalCpus:os.cpus().length,memoryGB:Math.round(os.totalmem()/1024**3),lighthouse:'13.5.0',origin:base,php:'8.4.27',external:'all HTTPS blocked in both phases; no external script execution',cache:'browser cache cleared after cart preparation per navigation; storage preserved for isolated cart; server/object caches warm',network:'Lighthouse simulated default mobile and desktop profiles',runsPerPage:1,fieldINP:null},pages:[]};
if(selected){report.pages=report.pages.filter(p=>!selected.includes(p.page));report.environment.cache='browser cache cold per navigation; cart/checkout prepared by HTTP without asset downloads, then browser cache cleared; server caches warm';}
report.environment.cache='Disk cache cleared and renderer memory evicted through Network.setCacheDisabled true/false on all existing targets per navigation; cart prepared by HTTP without asset downloads; storage preserved; server caches warm';
const chrome=await launcher.launch({chromePath:chromium.executablePath(),chromeFlags:['--headless','--no-sandbox','--disable-dev-shm-usage']});let b;
try{b=await chromium.connectOverCDP('http://127.0.0.1:'+chrome.port);const c=b.contexts()[0];
for(const device of ['mobile','desktop']){for(const [name,route]of pages){
 if(selected&&!selected.includes(name))continue;
 const samples=[],runs=['cart','checkout'].includes(name)||selected?.includes(name)?repeats:1;
 for(let sample=0;sample<runs;sample++){
 await c.clearCookies();const prep=await c.newPage();const session=await c.newCDPSession(prep);
 if(['cart','checkout'].includes(name)){await c.request.get(base+'/?add-to-cart='+fx.product);}
 await session.send('Network.clearBrowserCache');
 // Match Lighthouse's own clearBrowserCaches: evict renderer memory as well
 // as disk cache, including an existing target reused by the next navigation.
 for(const page of c.pages()) {const cache=await c.newCDPSession(page);await cache.send('Network.setCacheDisabled',{cacheDisabled:true});await cache.send('Network.setCacheDisabled',{cacheDisabled:false});await cache.detach();}
 await prep.close();
 const config=device==='desktop'?{extends:'lighthouse:default',settings:{formFactor:'desktop',screenEmulation:{mobile:false,width:1366,height:900,deviceScaleFactor:1,disabled:false},throttling:{rttMs:40,throughputKbps:10240,cpuSlowdownMultiplier:1,requestLatencyMs:0,downloadThroughputKbps:0,uploadThroughputKbps:0}}}:undefined;
 const result=await lighthouse(base+route,{port:chrome.port,output:'json',logLevel:'error',onlyCategories:['performance','accessibility','best-practices','seo'],disableStorageReset:true,blockedUrlPatterns:['https://*'],maxWaitForLoad:30000},config);
 const l=result.lhr,a=l.audits;fs.mkdirSync('.local/h9-audit/lighthouse',{recursive:true});fs.writeFileSync('.local/h9-audit/lighthouse/'+phase+'-'+device+'-'+name+'-'+sample+'.json',JSON.stringify(l));
 const numeric=id=>a[id]?.numericValue??null;const resources=a['network-requests']?.details?.items||[];
 const row={page:name,route,device,status:l.runtimeError?.code||'ok',scores:Object.fromEntries(Object.entries(l.categories).map(([key,v])=>[key,Math.round(v.score*100)])),lcpMs:numeric('largest-contentful-paint'),cls:numeric('cumulative-layout-shift'),ttfbMs:numeric('server-response-time'),tbtMs:numeric('total-blocking-time'),transferBytes:resources.reduce((s,r)=>s+(r.transferSize||0),0),resourceBytes:resources.reduce((s,r)=>s+(r.resourceSize||0),0),requests:resources.length,jsExecutionMs:numeric('bootup-time'),unusedCssBytes:a['unused-css-rules']?.details?.overallSavingsBytes??null,unusedJsBytes:a['unused-javascript']?.details?.overallSavingsBytes??null,failedAudits:Object.values(a).filter(v=>v.score!==null&&v.score<1&&v.scoreDisplayMode!=='informative').map(v=>({id:v.id,title:v.title})),config:l.configSettings,runWarnings:l.runWarnings};
 samples.push(row);console.log(JSON.stringify({phase,page:name,device,sample:sample+1,scores:row.scores,lcpMs:Math.round(row.lcpMs),requests:row.requests}));
 }
 const row={...samples.slice().sort((a,b)=>a.lcpMs-b.lcpMs)[Math.floor(samples.length/2)]};
 if(runs>1){row.aggregation='Representative run at median LCP of '+runs;row.samples=samples.map(({scores,lcpMs,cls,ttfbMs,tbtMs,transferBytes,requests})=>({scores,lcpMs,cls,ttfbMs,tbtMs,transferBytes,requests}));}
 report.pages.push(row);fs.mkdirSync('docs/evidence/h9-2',{recursive:true});fs.writeFileSync(reportPath,JSON.stringify(report,null,2)+'\n');
}}
}finally{if(b)await b.close();await chrome.kill();}
