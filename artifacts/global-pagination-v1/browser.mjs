import fs from 'node:fs';
import assert from 'node:assert/strict';
import puppeteer from '/tmp/jakawi-brand-release/browser/node_modules/puppeteer-core/lib/puppeteer/puppeteer-core.js';
const root='/home/jakawi.com/backups/global-pagination-v1';
const rawFixtures=JSON.parse(fs.readFileSync(`${root}/fixtures.json`));
const fixtures=Object.fromEntries(Object.entries(rawFixtures).map(([key,value])=>{const url=new URL(key,'https://jakawi.com');url.searchParams.sort();return [url.pathname+url.search,value]}));
const manifest=JSON.parse(fs.readFileSync(`${root}/public/build/manifest.json`));
const entry=manifest['resources/js/app.tsx'];
const css=[...new Set([manifest['resources/css/app.css'].file, ...Object.values(manifest).flatMap(x=>x.css??[])])];
const html=(data)=>`<!DOCTYPE html><html lang="es"><head><meta name="viewport" content="width=device-width,initial-scale=1">${css.map(x=>`<link rel="stylesheet" href="/build/${x}">`).join('')}<script type="module" src="/build/${entry.file}"></script></head><body><div id="app"></div><script data-page="app" type="application/json">${JSON.stringify(data).replaceAll('<','\\u003c')}</script></body></html>`;
const browser=await puppeteer.launch({executablePath:'/root/.cache/puppeteer/chrome/linux-155.0.8059.39/chrome-linux64/chrome',headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
const result={status:'FAIL',checks:[],errors:[],requests:[]};
const sleep=ms=>new Promise(r=>setTimeout(r,ms));
try {
 const page=await browser.newPage();await page.setCacheEnabled(false);await page.setBypassServiceWorker(true);
 page.on('pageerror',e=>result.errors.push(e.stack ?? e.message));
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
   if(url.hostname==='jakawi.com' && ['jakawi-logo.svg','jakawi-mark.svg','jakawi-wordmark.svg','favicon.svg','favicon.ico'].includes(url.pathname.slice(1)))return req.respond({status:200,contentType:url.pathname.endsWith('.svg')?'image/svg+xml':'image/x-icon',body:fs.readFileSync(`${root}/public${url.pathname}`)});
   return req.respond({status:204});
  }catch(e){result.errors.push(e.stack ?? e.message);return req.abort();}
 });
 const navigate=async url=>{console.log('route',url);await page.goto('https://jakawi.com'+url,{waitUntil:'networkidle0'});await page.waitForSelector('#app > *');};
 const count=()=>page.$$eval('.explorer-card',x=>x.length);
 if(!process.env.QA_ONLY_EXTRA) for(const width of [375,390,430,1024,1440]){
  await page.setViewport({width,height:900});await navigate('/explorar');assert.equal(await count(),18);
  const btn=await page.$('button.discovery-primary-action');assert(btn);await btn.click();await page.waitForFunction(()=>document.querySelectorAll('.explorer-card').length===36);assert.equal(await count(),36);
  if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth)){console.log('overflow',await page.evaluate(()=>({width:innerWidth,scroll:document.documentElement.scrollWidth,elements:[...document.querySelectorAll('body *')].filter(el=>el.getBoundingClientRect().right>innerWidth+1).slice(-15).map(el=>({tag:el.tagName,cls:el.className,width:el.getBoundingClientRect().width,text:el.textContent?.slice(0,80)}))})));await page.screenshot({path:`${root}/overflow-${width}.png`});throw new Error('admin overflow');}
  await page.screenshot({path:`${root}/explore-${width}.png`});
  // Double activation in the same tick must produce one request.
  const before=result.requests.length;
  await page.$eval('button.discovery-primary-action',el=>{el.click();el.click();});await page.waitForFunction(()=>document.querySelectorAll('.explorer-card').length===54);assert.equal(result.requests.length-before,1);
  const identities=await page.$$eval('.explorer-card',els=>els.map(el=>el.getAttribute('href')));assert.equal(new Set(identities).size,54);
  // Changing sort replaces accumulated results and resets the URL page.
  await page.select('select[aria-label="Ordenar oportunidades"]','name');await page.waitForFunction(()=>document.querySelectorAll('.explorer-card').length===18 && !document.querySelector('button.discovery-primary-action')?.disabled);assert(!new URL(page.url()).searchParams.has('page'));assert(await page.$eval('.explorer-card',el=>el.getAttribute('href').includes('/retos/')));await sleep(200);
  await page.$eval('button.discovery-primary-action',el=>el.click());await page.waitForFunction(()=>document.querySelectorAll('.explorer-card').length===36 && !document.querySelector('button.discovery-primary-action')?.disabled);
  await sleep(200);
  const scroll=await page.evaluate(()=>{window.scrollTo(0,800);return window.scrollY;});
  // A normal navigation and browser back restores the accumulated response and position.
  await navigate('/admin/benefits');
  await page.goBack({waitUntil:'networkidle0'});await page.waitForSelector('.explorer-card');
  assert.equal(await count(),36);assert(Math.abs((await page.evaluate(()=>scrollY))-scroll)<10);
  await page.goForward({waitUntil:'networkidle0'});await page.waitForSelector('nav[aria-label="Paginación"]');
  if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth)){console.log('overflow',await page.evaluate(()=>({width:innerWidth,scroll:document.documentElement.scrollWidth,elements:[...document.querySelectorAll('body *')].filter(el=>el.getBoundingClientRect().right>innerWidth+1).slice(-15).map(el=>({tag:el.tagName,cls:el.className,width:el.getBoundingClientRect().width,text:el.textContent?.slice(0,80)}))})));await page.screenshot({path:`${root}/overflow-${width}.png`});throw new Error('admin overflow');}
  await page.$eval('nav[aria-label="Paginación"]',nav=>[...nav.querySelectorAll('a')].find(a=>a.textContent==='Siguiente').click());await page.waitForFunction(()=>document.querySelector('nav[aria-label="Paginación"]')?.textContent?.includes('26–50'));
  await page.screenshot({path:`${root}/admin-${width}.png`});
  result.checks.push({width,first:18,appended:54,duplicates:0,doubleClick:'PASS',sortReset:'PASS',history:'PASS',overflow:false});
 }
 // Explicit final page, empty result and filters with no production writes.
 if(!process.env.QA_ONLY_EXTRA){await navigate('/explorar?type=challenges');await page.$eval('button.discovery-primary-action',el=>el.click());await page.waitForFunction(()=>document.querySelectorAll('.explorer-card').length===30);await page.waitForFunction(()=>!document.querySelector('button.discovery-primary-action'));assert.equal(await page.$('button.discovery-primary-action'),null);
 await navigate('/explorar?q=missing');assert.equal(await count(),0);assert.equal(await page.$$eval('button.discovery-primary-action',buttons=>buttons.some(button=>button.textContent?.includes('CARGAR MÁS'))),false);}
 for(const url of ['/admin/partners','/admin/locations','/admin/campaigns','/admin/memberships','/admin/redemptions','/admin/reward-rules','/admin/expansion','/admin/solicitudes-membresia']) {await navigate(url);assert(!await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth));}
 assert.deepEqual(result.errors,[]);result.status='PASS';
}finally{await browser.close();fs.writeFileSync(`${root}/browser.json`,JSON.stringify(result,null,2));}
console.log(JSON.stringify(result));
