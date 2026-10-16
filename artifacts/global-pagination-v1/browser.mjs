import fs from 'node:fs';
import assert from 'node:assert/strict';
import puppeteer from '/tmp/jakawi-brand-release/browser/node_modules/puppeteer-core/lib/puppeteer/puppeteer-core.js';
const root='/home/jakawi.com/backups/global-pagination-v1';
const rawFixtures=JSON.parse(fs.readFileSync(`${root}/fixtures.json`));
const fixtures=Object.fromEntries(Object.entries(rawFixtures).map(([key,value])=>{const url=new URL(key,'https://jakawi.com');url.searchParams.sort();return [url.pathname+url.search,value]}));
const manifest=JSON.parse(fs.readFileSync(`${root}/public/build/manifest.json`));
const entry=manifest['resources/js/app.tsx'];
const css=[...new Set([...Object.values(manifest).flatMap(x=>x.css??[])])];
const html=(data)=>`<!DOCTYPE html><html lang="es"><head><meta name="viewport" content="width=device-width,initial-scale=1">${css.map(x=>`<link rel="stylesheet" href="/build/${x}">`).join('')}<script type="module" src="/build/${entry.file}"></script></head><body><div id="app" data-page="${JSON.stringify(data).replaceAll('&','&amp;').replaceAll('"','&quot;').replaceAll('<','&lt;')}"></div></body></html>`;
const browser=await puppeteer.launch({executablePath:'/root/.cache/puppeteer/chrome/linux-155.0.8059.39/chrome-linux64/chrome',headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
const result={status:'FAIL',checks:[],errors:[],requests:[]};
const sleep=ms=>new Promise(r=>setTimeout(r,ms));
try {
 const page=await browser.newPage();
 page.on('pageerror',e=>result.errors.push(e.message));
 await page.setRequestInterception(true);
 page.on('request',async req=>{
  try {
   const url=new URL(req.url());
   if(url.pathname.startsWith('/build/')){const file=`${root}/public${url.pathname}`;return req.respond({status:200,contentType:file.endsWith('.css')?'text/css':'application/javascript',body:fs.readFileSync(file)});}
   if(!['GET','HEAD'].includes(req.method()))return req.respond({status:200,contentType:'application/json',body:'{}'});
   const params=[...url.searchParams.entries()].filter(([,value])=>value!=='');
   params.sort(([a],[b])=>a.localeCompare(b));
   const key=url.pathname+(params.length?'?'+new URLSearchParams(params):'');
   const data=fixtures[key]??fixtures[url.pathname];
   if(data && url.hostname==='jakawi.com'){
    const copy=structuredClone(data);copy.url=url.pathname+url.search;copy.version=fixtures['/explorar'].version;
    if(req.headers()['x-inertia']){
     result.requests.push(key);
     const partial=req.headers()['x-inertia-partial-data'];
     if(partial){const allowed=partial.split(',');copy.props=Object.fromEntries(Object.entries(copy.props).filter(([key])=>allowed.includes(key)));}
     if(req.headers()['x-inertia-reset'])copy.mergeProps=[];
     return req.respond({status:200,headers:{'X-Inertia':'true'},contentType:'application/json',body:JSON.stringify(copy)});
    }
    return req.respond({status:200,contentType:'text/html',body:html(copy)});
   }
   return req.respond({status:204});
  }catch(e){result.errors.push(e.message);return req.abort();}
 });
 const navigate=async url=>{await page.goto('https://jakawi.com'+url,{waitUntil:'networkidle0'});await page.waitForSelector('#app > *');};
 const count=()=>page.$$eval('.explorer-card',x=>x.length);
 for(const width of [375,390,430,1024,1440]){
  await page.setViewport({width,height:900});await navigate('/explorar');assert.equal(await count(),18);
  const btn=await page.$('button.discovery-primary-action');assert(btn);await btn.click();await page.waitForFunction(()=>document.querySelectorAll('.explorer-card').length===36);assert.equal(await count(),36);
  assert(!await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth));
  await page.screenshot({path:`${root}/explore-${width}.png`});
  // Double activation in the same tick must produce one request.
  const before=result.requests.length;
  await page.$eval('button.discovery-primary-action',el=>{el.click();el.click();});await page.waitForFunction(()=>document.querySelectorAll('.explorer-card').length===54);assert.equal(result.requests.length-before,1);
  const identities=await page.$$eval('.explorer-card',els=>els.map(el=>el.getAttribute('href')));assert.equal(new Set(identities).size,54);
  // Changing sort replaces accumulated results and resets the URL page.
  await page.select('select[aria-label="Ordenar oportunidades"]','name');await page.waitForFunction(()=>document.querySelectorAll('.explorer-card').length===18);assert(!new URL(page.url()).searchParams.has('page'));
  await page.$eval('button.discovery-primary-action',el=>el.click());await page.waitForFunction(()=>document.querySelectorAll('.explorer-card').length===36);
  const scroll=await page.evaluate(()=>{window.scrollTo(0,800);return window.scrollY;});
  // A normal navigation and browser back restores the accumulated response and position.
  await page.evaluate(()=>window.Inertia); // no global store is introduced
  await page.$eval('button',()=>{});
  await navigate('/admin/benefits');
  await page.goBack({waitUntil:'networkidle0'});await page.waitForSelector('.explorer-card');
  assert.equal(await count(),36);assert(Math.abs((await page.evaluate(()=>scrollY))-scroll)<10);
  await page.goForward({waitUntil:'networkidle0'});await page.waitForSelector('nav[aria-label="Paginación"]');
  assert(!await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth));
  await page.screenshot({path:`${root}/admin-${width}.png`});
  result.checks.push({width,first:18,appended:54,duplicates:0,doubleClick:'PASS',sortReset:'PASS',history:'PASS',overflow:false});
 }
 // Explicit final page, empty result and filters with no production writes.
 await navigate('/explorar?type=challenges');await page.$eval('button.discovery-primary-action',el=>el.click());await page.waitForFunction(()=>document.querySelectorAll('.explorer-card').length===30);assert.equal(await page.$('button.discovery-primary-action'),null);
 await navigate('/explorar?q=missing');assert.equal(await count(),0);assert.equal(await page.$('button.discovery-primary-action'),null);
 for(const url of ['/admin/partners','/admin/locations','/admin/campaigns','/admin/memberships','/admin/redemptions','/admin/reward-rules','/admin/expansion','/admin/solicitudes-membresia']) {await navigate(url);assert(!await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth));}
 assert.deepEqual(result.errors,[]);result.status='PASS';
}finally{await browser.close();fs.writeFileSync(`${root}/browser.json`,JSON.stringify(result,null,2));}
console.log(JSON.stringify(result));
