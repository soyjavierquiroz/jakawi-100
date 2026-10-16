import fs from 'node:fs';
import assert from 'node:assert/strict';
import puppeteer from '/tmp/jakawi-brand-release/browser/node_modules/puppeteer-core/lib/puppeteer/puppeteer-core.js';
const root='/home/jakawi.com/backups/global-pagination-v1',stage=process.env.QA_STAGE??'candidate';
const browser=await puppeteer.launch({executablePath:'/root/.cache/puppeteer/chrome/linux-155.0.8059.39/chrome-linux64/chrome',headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
const result={status:'FAIL',stage,checks:[],errors:[],writes:0};
let name='explore';
try{
 const page=await browser.newPage();await page.setCacheEnabled(false);await page.setBypassServiceWorker(true);page.on('pageerror',e=>result.errors.push(e.stack ?? e.message));await page.setRequestInterception(true);
 page.on('request',req=>{try{const url=new URL(req.url());if(!['GET','HEAD'].includes(req.method()))return req.respond({status:200,contentType:'application/json',body:'{}'});if(req.isNavigationRequest()&&req.frame()===page.mainFrame())return req.respond({status:200,contentType:'text/html',body:fs.readFileSync(`${root}/${stage}-${name}.html`)});if(url.pathname.startsWith('/build/')){if(stage==='live')return req.continue();const file=`${root}/public${url.pathname}`;return req.respond({status:200,contentType:file.endsWith('.css')?'text/css':'application/javascript',body:fs.readFileSync(file)});}if(url.hostname==='jakawi.com' && ['jakawi-logo.svg','jakawi-mark.svg','jakawi-wordmark.svg','favicon.svg','favicon.ico'].includes(url.pathname.slice(1)))return req.respond({status:200,contentType:url.pathname.endsWith('.svg')?'image/svg+xml':'image/x-icon',body:fs.readFileSync(`${root}/public${url.pathname}`)});return req.respond({status:204});}catch(e){result.errors.push(e.stack ?? e.message);return req.abort();}});
 for(const width of [390,1440]){await page.setViewport({width,height:900});for(name of ['explore','partners','locations','benefits','preview-one','preview-two','preview-three']){await page.goto('https://jakawi.com'+JSON.parse(fs.readFileSync(`${root}/${stage}-smoke.json`)).find(route=>route.name===name).path,{waitUntil:'networkidle0'});await page.waitForSelector('#app > *');const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth);assert(!overflow,`${name} ${width} overflow`);await page.screenshot({path:`${root}/${stage}-${name}-${width}.png`});result.checks.push({name,width,overflow});}}
 assert.deepEqual(result.errors,[]);result.status='PASS';
}finally{await browser.close();fs.writeFileSync(`${root}/${stage}-browser.json`,JSON.stringify(result,null,2));}
console.log(JSON.stringify(result));
